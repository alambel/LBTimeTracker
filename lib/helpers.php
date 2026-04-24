<?php
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function valid_email(string $s): bool {
    if ($s === '' || strlen($s) > 255) return false;
    return filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
}

function normalize_email(string $s): string {
    return mb_strtolower(trim($s), 'UTF-8');
}

/** Nom affiché : « Prénom Nom » si renseigné, sinon username. */
function display_name(array $user): string {
    $fn = trim((string)($user['first_name'] ?? ''));
    $ln = trim((string)($user['last_name'] ?? ''));
    $full = trim($fn . ' ' . $ln);
    if ($full !== '') return $full;
    return (string)($user['username'] ?? '');
}

/** Initiales (1 à 2 caractères) à partir d'un nom. */
function user_initials(string $name): string {
    $parts = preg_split('/[\s._\-]+/u', $name) ?: [$name];
    $out = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $out .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($out, 'UTF-8') >= 2) break;
    }
    return $out !== '' ? $out : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
}

/** Couleur déterministe par user_id (teinte HSL) — utilisée si pas d'avatar. */
function user_color_hsl(int $userId): string {
    $h = (int)($userId * 137 % 360);
    return 'hsl(' . $h . ' 55% 48%)';
}

/** URL relative de l'avatar d'un user (ou null si pas défini). */
function avatar_url(array $user): ?string {
    if (empty($user['avatar_path'])) return null;
    return 'index.php?action=avatar&id=' . (int)$user['id'];
}

/** URL absolue de l'app (pour liens dans emails).
 *
 * Priorité à `config.canonical_host` (ex : `https://time.njs.ch`) pour éviter
 * le host-header injection : sans valeur canonique, un attaquant qui force
 * `Host: evil.com` verrait les liens d'invitation pointer vers son domaine.
 */
function app_url(): string {
    global $config;
    $canonical = is_array($config ?? null) ? (string)($config['canonical_host'] ?? '') : '';
    if ($canonical !== '') {
        // Doit commencer par http:// ou https://, sinon on ignore
        if (preg_match('#^https?://[^/\s]+#i', $canonical)) {
            return rtrim($canonical, '/') . '/index.php';
        }
    }
    $scheme = (function_exists('is_https') && is_https()) ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Sanitise basique : uniquement [a-z0-9.:-]
    $host = preg_replace('/[^A-Za-z0-9.:_\-]/', '', $host) ?: 'localhost';
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $scheme . '://' . $host . ($dir === '' ? '' : $dir) . '/index.php';
}

function month_title(string $month): string {
    $months = [
        '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
        '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
        '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre',
    ];
    [$y, $m] = explode('-', $month);
    return ($months[$m] ?? $m) . ' ' . $y;
}

function shift_month(string $month, int $delta): string {
    $d = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if (!$d) { return $month; }
    $sign = $delta >= 0 ? '+' : '';
    $d->modify($sign . $delta . ' month');
    return $d->format('Y-m');
}

function month_bounds(string $month): array {
    $first = $month . '-01';
    $last = date('Y-m-t', strtotime($first));
    return [$first, $last];
}

function valid_date(string $s): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { return false; }
    $t = strtotime($s);
    return $t !== false;
}

/**
 * Slot modes — each user picks one.
 *  hd2  : 2 tranches × 4h (matin / aprem)
 *  hd4  : 4 tranches × 4h (AM/PM/EV/NT — historique)
 *  hr10 : 10 tranches × 1h
 *
 * Codes uniques entre modes → la durée s'infère du code seul.
 */
function slot_modes(): array {
    return [
        'hd2' => [
            'label'   => 'Demi-journées (2 × 4 h)',
            'codes'   => ['D1', 'D2'],
            'names'   => ['D1' => 'Matin', 'D2' => 'Après-midi'],
            'hours'   => ['D1' => '08–12', 'D2' => '13–17'],
            'minutes' => 240,
        ],
        'hd4' => [
            'label'   => 'Quarts de journée (4 × 4 h)',
            'codes'   => ['AM', 'PM', 'EV', 'NT'],
            'names'   => ['AM' => 'Matin', 'PM' => 'Après-midi', 'EV' => 'Soir', 'NT' => 'Nuit'],
            'hours'   => ['AM' => '08–12', 'PM' => '13–17', 'EV' => '17–21', 'NT' => '22–02'],
            'minutes' => 240,
        ],
        'hr10' => [
            'label'   => 'Heures (10 × 1 h)',
            'codes'   => ['H01','H02','H03','H04','H05','H06','H07','H08','H09','H10'],
            'names'   => [
                'H01' => '08 h', 'H02' => '09 h', 'H03' => '10 h', 'H04' => '11 h', 'H05' => '12 h',
                'H06' => '13 h', 'H07' => '14 h', 'H08' => '15 h', 'H09' => '16 h', 'H10' => '17 h',
            ],
            'hours'   => [
                'H01' => '08–09', 'H02' => '09–10', 'H03' => '10–11', 'H04' => '11–12', 'H05' => '12–13',
                'H06' => '13–14', 'H07' => '14–15', 'H08' => '15–16', 'H09' => '16–17', 'H10' => '17–18',
            ],
            'minutes' => 60,
        ],
    ];
}

function valid_slot_mode(string $m): bool {
    return array_key_exists($m, slot_modes());
}

function slot_mode_config(string $mode): array {
    $modes = slot_modes();
    return $modes[$mode] ?? $modes['hd4'];
}

/** Codes de créneau pour un mode donné (ex. ['AM','PM','EV','NT']). */
function period_codes(string $mode = 'hd4'): array {
    return slot_mode_config($mode)['codes'];
}

/** Labels humains pour un mode donné. */
function period_labels(string $mode = 'hd4'): array {
    return slot_mode_config($mode)['names'];
}

/** Plages horaires indicatives pour un mode donné. */
function period_hours(string $mode = 'hd4'): array {
    return slot_mode_config($mode)['hours'];
}

/** Valide un code dans un mode donné (strict). */
function valid_period(string $p, string $mode = 'hd4'): bool {
    return in_array($p, period_codes($mode), true);
}

/** Valide un code dans *n'importe quel* mode connu (utile pour agrégats multi-users). */
function valid_period_any(string $p): bool {
    foreach (slot_modes() as $cfg) {
        if (in_array($p, $cfg['codes'], true)) return true;
    }
    return false;
}

/** Minutes associées à un code (dérivé de sa famille). */
function slot_minutes_for_code(string $code): int {
    foreach (slot_modes() as $cfg) {
        if (in_array($code, $cfg['codes'], true)) return (int)$cfg['minutes'];
    }
    return 0;
}

function month_name_fr(int $m): string {
    $names = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return $names[$m] ?? '';
}

function weekday_letters_fr(): array {
    return ['L', 'M', 'M', 'J', 'V', 'S', 'D']; // lundi-first
}

function weekday_names_fr_long(): array {
    return ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
}

/**
 * Build a month grid starting on Monday.
 * Returns an array of cells — each is null (empty) or array:
 *   ['day'=>int, 'date'=>'YYYY-MM-DD', 'dow'=>1..7, 'weekend'=>bool, 'today'=>bool]
 */
function build_month_cells(string $month): array {
    [$firstDay, $lastDay] = month_bounds($month);
    $first = new DateTime($firstDay);
    $last = new DateTime($lastDay);
    $offset = ((int)$first->format('N')) - 1; // Mon=0..Sun=6
    $today = date('Y-m-d');
    $cells = [];
    for ($i = 0; $i < $offset; $i++) $cells[] = null;
    $cursor = clone $first;
    while ($cursor <= $last) {
        $dow = (int)$cursor->format('N');
        $cells[] = [
            'day' => (int)$cursor->format('j'),
            'date' => $cursor->format('Y-m-d'),
            'dow' => $dow,
            'weekend' => $dow >= 6,
            'today' => $cursor->format('Y-m-d') === $today,
        ];
        $cursor->modify('+1 day');
    }
    while (count($cells) % 7 !== 0) $cells[] = null;
    return $cells;
}

function days_in_month(string $month): int {
    [$firstDay, $lastDay] = month_bounds($month);
    return (int)(new DateTime($lastDay))->format('j');
}

/** List of dates (YYYY-MM-DD) between $from and $to inclusive. */
function date_range(string $from, string $to): array {
    $out = [];
    $cursor = new DateTime($from);
    $end = new DateTime($to);
    while ($cursor <= $end) {
        $out[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }
    return $out;
}

function render_nav_icon(string $name): string {
    $paths = [
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
        'summary' => '<line x1="5" y1="20" x2="5" y2="13"/><line x1="12" y1="20" x2="12" y2="7"/><line x1="19" y1="20" x2="19" y2="16"/>',
        'projects' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    $path = $paths[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function render_app_icon(int $size = 24, string $class = 'app-icon'): string {
    $s = (int)$size;
    return '<svg class="' . e($class) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="4" width="' . $s . '" height="' . $s . '" aria-hidden="true">'
        . '<circle cx="32" cy="32" r="28"/>'
        . '<g stroke-width="3" stroke-linecap="round">'
        . '<line x1="32" y1="7" x2="32" y2="11"/>'
        . '<line x1="32" y1="53" x2="32" y2="57"/>'
        . '<line x1="7" y1="32" x2="11" y2="32"/>'
        . '<line x1="53" y1="32" x2="57" y2="32"/>'
        . '</g>'
        . '<path d="M23 18 L23 44 L41 44" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}
