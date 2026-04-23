<?php
/**
 * Sécurité : headers HTTP, CSRF, rate limit login, validations strictes.
 */

function send_security_headers(): void {
    if (headers_sent()) return;
    // Anti-clickjacking, MIME sniffing, referer leak
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    // CSP — 'unsafe-inline' sur style-src uniquement (colors projets en inline style) ;
    // scripts restent strictement 'self'.
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self'; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "object-src 'none'"
    );
}

/** HTTPS détecté (via proxy type Cloudflare ou direct TLS). */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (strtolower((string)$xfp) === 'https') return true;
    if (($_SERVER['HTTP_CF_VISITOR'] ?? '') && str_contains((string)$_SERVER['HTTP_CF_VISITOR'], 'https')) return true;
    return false;
}

/** IP client "vraie" si derrière Cloudflare, sinon REMOTE_ADDR. */
function client_ip(): string {
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    if (is_string($cf) && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ==================== CSRF ==================== */

function csrf_token(): string {
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(string $submitted): bool {
    $expected = $_SESSION['csrf'] ?? '';
    if (!is_string($expected) || $expected === '' || $submitted === '') return false;
    return hash_equals($expected, $submitted);
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check_form_or_die(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $token = (string)($_POST['_csrf'] ?? '');
    if (!csrf_valid($token)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Jeton CSRF invalide — rafraîchir la page et réessayer.';
        exit;
    }
}

function csrf_check_api_or_die(): void {
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!csrf_valid($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'csrf']);
        exit;
    }
}

/* ==================== Rate limit login ==================== */

function _ratelimit_file(): string {
    $dir = BASE_DIR . '/data/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        @file_put_contents(BASE_DIR . '/data/.htaccess', "Require all denied\n");
    }
    return $dir . '/' . hash('sha256', client_ip()) . '.json';
}

function _ratelimit_read(): array {
    $default = ['count' => 0, 'first' => time(), 'blocked_until' => 0];
    $file = _ratelimit_file();
    if (!is_readable($file)) return $default;
    $data = json_decode((string)@file_get_contents($file), true);
    if (!is_array($data)) return $default;
    return array_merge($default, $data);
}

function _ratelimit_write(array $data): void {
    @file_put_contents(_ratelimit_file(), json_encode($data));
}

/**
 * Renvoie un message d'erreur bloquant ou null.
 * Seuil : 10 échecs en 10 min → blocage 15 min.
 */
function login_ratelimit_check(): ?string {
    $d = _ratelimit_read();
    $now = time();
    if ($d['blocked_until'] > $now) {
        $wait = $d['blocked_until'] - $now;
        return 'Trop de tentatives. Réessayer dans ' . $wait . 's.';
    }
    // Fenêtre glissante : reset si première tentative > 10 min
    if ($now - (int)$d['first'] > 600) {
        _ratelimit_write(['count' => 0, 'first' => $now, 'blocked_until' => 0]);
    }
    return null;
}

function login_ratelimit_register_failure(): void {
    $d = _ratelimit_read();
    $now = time();
    if ($now - (int)$d['first'] > 600) {
        $d = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    }
    $d['count'] = (int)$d['count'] + 1;
    $d['last'] = $now;
    if ($d['count'] >= 10) {
        $d['blocked_until'] = $now + 900; // 15 min
    }
    _ratelimit_write($d);
}

function login_ratelimit_reset(): void {
    @unlink(_ratelimit_file());
}

/* ==================== Validations ==================== */

function valid_hex_color(string $s): bool {
    return (bool)preg_match('/^#[0-9a-f]{6}$/i', $s);
}

function sanitize_hex_color(string $s, string $default = '#c45a2e'): string {
    return valid_hex_color($s) ? strtolower($s) : $default;
}

function valid_timezone(string $tz): bool {
    return in_array($tz, timezone_identifiers_list(), true);
}

function sanitize_name(string $s, int $maxLen = 100): string {
    $s = trim($s);
    // Remove control chars except tab/space
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

function sanitize_note(string $s, int $maxLen = 200): string {
    $s = trim($s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

/** Protège contre CSV injection (formules Excel/LibreOffice). */
function sanitize_csv_cell(string $s): string {
    if ($s === '') return $s;
    $first = $s[0];
    if (in_array($first, ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
        return "'" . $s;
    }
    return $s;
}
