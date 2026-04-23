<?php
function render_layout(string $title, string $page, string $content): void {
    include BASE_DIR . '/views/layout.php';
}

function render_calendar(PDO $db): void {
    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    [$from, $to] = month_bounds($month);
    $entries = get_entries_between($db, $from, $to);
    $byKey = [];
    foreach ($entries as $ent) {
        $byKey[$ent['date'] . '_' . $ent['period']] = $ent;
    }
    $projects = get_projects($db);             // active only
    $cells = build_month_cells($month);
    [$year, $mm] = array_map('intval', explode('-', $month));
    $monthLabel = month_name_fr($mm);
    $prevMonth = shift_month($month, -1);
    $nextMonth = shift_month($month, +1);

    // Compute dominant project and total half-days
    $byProjectCount = [];
    foreach ($entries as $ent) {
        $pid = (int)$ent['project_id'];
        $byProjectCount[$pid] = ($byProjectCount[$pid] ?? 0) + 1;
    }
    arsort($byProjectCount);
    $topId = array_key_first($byProjectCount);
    $topProject = null;
    if ($topId !== null) {
        $topProject = get_project($db, (int)$topId);
    }
    $totalHalfDays = count($entries);

    $title = 'Calendrier — ' . month_name_fr($mm) . ' ' . $year;
    $page = 'calendar';

    ob_start();
    include BASE_DIR . '/views/calendar.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content);
}

function render_summary(PDO $db): void {
    $from = $_GET['from'] ?? date('Y-m') . '-01';
    $to = $_GET['to'] ?? date('Y-m-t');
    if (!valid_date($from)) { $from = date('Y-m') . '-01'; }
    if (!valid_date($to)) { $to = date('Y-m-t'); }

    $rows = summary_between($db, $from, $to);
    $total = 0;
    foreach ($rows as $r) { $total += (int)$r['half_days']; }

    if (($_GET['format'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="timetrack_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Projet', 'Demi-journées', 'Jours équivalents', '% du total']);
        foreach ($rows as $r) {
            $pct = $total ? 100 * $r['half_days'] / $total : 0;
            fputcsv($out, [
                sanitize_csv_cell((string)$r['name']),
                $r['half_days'],
                number_format((float)$r['full_days'], 1, '.', ''),
                number_format($pct, 1, '.', ''),
            ]);
        }
        fclose($out);
        return;
    }

    // Ribbon data
    $rangeDays = date_range($from, $to);
    $daysCount = count($rangeDays);
    $possibleHalfDays = $daysCount * 4;
    $completeness = $possibleHalfDays > 0 ? ($total / $possibleHalfDays) * 100 : 0;

    $entries = get_entries_between($db, $from, $to);
    $byKey = [];
    foreach ($entries as $ent) {
        $byKey[$ent['date'] . '_' . $ent['period']] = $ent;
    }

    $archivedCount = 0;
    $allProjects = get_projects($db, true);
    foreach ($allProjects as $p) {
        if (!empty($p['archived'])) $archivedCount++;
    }

    // Short French date labels for ribbon ends
    $fromLabel = strtoupper(date('d M', strtotime($from)));
    $toLabel = strtoupper(date('d M', strtotime($to)));
    // French month abbreviations
    $fromLabel = _fr_short_date($from);
    $toLabel = _fr_short_date($to);

    $title = 'Résumé — LBTimeTracker';
    $page = 'summary';
    ob_start();
    include BASE_DIR . '/views/summary.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content);
}

function _fr_short_date(string $date): string {
    $abbr = ['', 'JAN.', 'FÉV.', 'MARS', 'AVR.', 'MAI', 'JUIN', 'JUIL.', 'AOÛT', 'SEPT.', 'OCT.', 'NOV.', 'DÉC.'];
    $dt = new DateTime($date);
    return $dt->format('d') . ' ' . $abbr[(int)$dt->format('n')];
}

function render_projects(PDO $db): void {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check_form_or_die();
        $op = $_POST['op'] ?? '';
        try {
            if ($op === 'create') {
                $name = sanitize_name((string)($_POST['name'] ?? ''));
                $color = sanitize_hex_color((string)($_POST['color'] ?? ''));
                if ($name === '') { throw new RuntimeException('Nom requis'); }
                create_project($db, $name, $color);
            } elseif ($op === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = sanitize_name((string)($_POST['name'] ?? ''));
                $color = sanitize_hex_color((string)($_POST['color'] ?? ''));
                $archived = !empty($_POST['archived']);
                if ($id <= 0 || $name === '') { throw new RuntimeException('Paramètres invalides'); }
                update_project($db, $id, $name, $color, $archived);
            } elseif ($op === 'archive_toggle') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) { throw new RuntimeException('ID invalide'); }
                $p = get_project($db, $id);
                if ($p) {
                    update_project($db, $id, $p['name'], $p['color'], empty($p['archived']));
                }
            } elseif ($op === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) { throw new RuntimeException('ID invalide'); }
                delete_project($db, $id);
            }
            header('Location: index.php?action=projects');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $projects = get_projects($db, true);
    $entryCounts = project_entry_counts($db);
    $title = 'Projets — LBTimeTracker';
    $page = 'projects';
    ob_start();
    include BASE_DIR . '/views/projects.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content);
}
