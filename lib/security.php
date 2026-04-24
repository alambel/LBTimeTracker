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
    // HSTS — force HTTPS pendant 1 an pour tout le sous-domaine
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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

/** Headers pour page authentifiée : empêche cache partagé / back-button leak. */
function send_private_cache_headers(): void {
    if (headers_sent()) return;
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
}

/** HTTPS détecté (via proxy type Cloudflare ou direct TLS). */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (strtolower((string)$xfp) === 'https') return true;
    if (($_SERVER['HTTP_CF_VISITOR'] ?? '') && str_contains((string)$_SERVER['HTTP_CF_VISITOR'], 'https')) return true;
    return false;
}

/** IP client "vraie" si derrière Cloudflare, sinon REMOTE_ADDR.
 *
 * Par défaut, on ignore `CF-Connecting-IP` : si l'app n'est PAS derrière CF,
 * un attaquant peut l'injecter lui-même à chaque requête et bypasser le
 * rate-limit (chaque bucket étant indexé par IP).
 *
 * Pour réactiver (si vraiment derrière Cloudflare), ajouter dans config.php :
 *     'trust_cloudflare' => true,
 */
function client_ip(): string {
    global $config;
    $trustCf = is_array($config ?? null) && !empty($config['trust_cloudflare']);
    if ($trustCf) {
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if (is_string($cf) && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ==================== CSRF ==================== */

function csrf_token(): string {
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Force la rotation du token CSRF (après password change, logout partiel, etc.). */
function csrf_rotate(): void {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
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

/* ==================== Rate limit invitations (par user) ==================== */

function _invite_ratelimit_file(int $userId): string {
    $dir = BASE_DIR . '/data/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        @file_put_contents(BASE_DIR . '/data/.htaccess', "Require all denied\n");
    }
    return $dir . '/invite_u' . $userId . '.json';
}

/**
 * Renvoie null si l'envoi est autorisé, sinon un message d'erreur.
 * Limite : 20 invitations par user sur 1 heure glissante.
 */
function invite_ratelimit_check(int $userId): ?string {
    $file = _invite_ratelimit_file($userId);
    $now = time();
    $windowStart = $now - 3600;
    $entries = [];
    if (is_readable($file)) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (is_array($data)) {
            foreach ($data as $t) {
                if (is_int($t) && $t >= $windowStart) $entries[] = $t;
            }
        }
    }
    if (count($entries) >= 20) {
        $oldest = min($entries);
        $wait = max(1, 3600 - ($now - $oldest));
        $min = ceil($wait / 60);
        return 'Trop d\'invitations envoyées. Réessaie dans ' . $min . ' min.';
    }
    return null;
}

function invite_ratelimit_register(int $userId): void {
    $file = _invite_ratelimit_file($userId);
    $now = time();
    $windowStart = $now - 3600;
    $entries = [];
    if (is_readable($file)) {
        $data = json_decode((string)@file_get_contents($file), true);
        if (is_array($data)) {
            foreach ($data as $t) {
                if (is_int($t) && $t >= $windowStart) $entries[] = $t;
            }
        }
    }
    $entries[] = $now;
    @file_put_contents($file, json_encode($entries));
}

/** GC best-effort des fichiers rate-limit anciens (>24h sans activité). */
function login_ratelimit_gc(): void {
    $dir = BASE_DIR . '/data/ratelimit';
    if (!is_dir($dir)) return;
    // 1 chance sur 50 par requête pour éviter d'impacter les perfs
    if (random_int(1, 50) !== 1) return;
    $cutoff = time() - 86400;
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        $m = @filemtime($f);
        if ($m !== false && $m < $cutoff) {
            @unlink($f);
        }
    }
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

/**
 * Politique mot de passe : min 10 car., max 128, pas dans la blocklist
 * (top pwd leakés). Renvoie null si OK, message d'erreur sinon.
 */
function validate_password_policy(string $pwd): ?string {
    $len = strlen($pwd);
    if ($len < 10) return 'Mot de passe trop court (10 caractères minimum).';
    if ($len > 128) return 'Mot de passe trop long (128 caractères max.).';
    if (is_common_password($pwd)) return 'Mot de passe trop commun — choisis-en un plus unique.';
    return null;
}

function is_common_password(string $pwd): bool {
    static $list = null;
    if ($list === null) {
        // Top 100 passwords leakés (extrait) — source SecLists 2024.
        $list = array_flip([
            '123456','password','12345678','qwerty','123456789','12345','1234','111111','1234567','dragon',
            'baseball','abc123','football','monkey','letmein','696969','shadow','master','666666','qwertyuiop',
            '123321','mustang','1234567890','michael','654321','superman','1qaz2wsx','7777777','121212',
            '000000','qazwsx','123qwe','killer','trustno1','jordan','jennifer','zxcvbnm','asdfgh','hunter',
            'buster','soccer','harley','batman','andrew','tigger','sunshine','iloveyou','2000','charlie',
            'robert','thomas','hockey','ranger','daniel','starwars','klaster','112233','george','computer',
            'michelle','jessica','pepper','1111','zxcvbn','555555','11111111','131313','freedom','777777',
            'pass','maggie','159753','aaaaaa','ginger','princess','joshua','cheese','amanda','summer',
            'love','ashley','nicole','chelsea','biteme','matthew','access','yankees','987654321','dallas',
            'austin','thunder','taylor','matrix','minecraft','azerty','motdepasse','soleil','admin','bonjour',
            'camille','nicolas','chocolat','doudou','loulou',
        ]);
    }
    return isset($list[strtolower($pwd)]);
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
