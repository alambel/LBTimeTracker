<?php
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function period_codes(): array {
    return ['AM', 'PM', 'EV', 'NT'];
}

function period_labels(): array {
    return [
        'AM' => 'Matin',
        'PM' => 'Après-midi',
        'EV' => 'Soir',
        'NT' => 'Nuit',
    ];
}

function valid_period(string $p): bool {
    return in_array($p, period_codes(), true);
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
