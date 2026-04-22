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
