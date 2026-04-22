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
    $projects = get_projects($db);
    $prevMonth = shift_month($month, -1);
    $nextMonth = shift_month($month, +1);
    $title = 'Calendrier — ' . month_title($month);
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
                $r['name'],
                $r['half_days'],
                number_format((float)$r['full_days'], 1, '.', ''),
                number_format($pct, 1, '.', ''),
            ]);
        }
        fclose($out);
        return;
    }

    $title = 'Résumé — LB Time Tracker';
    $page = 'summary';
    ob_start();
    include BASE_DIR . '/views/summary.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content);
}

function render_projects(PDO $db): void {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $op = $_POST['op'] ?? '';
        try {
            if ($op === 'create') {
                $name = trim($_POST['name'] ?? '');
                $color = $_POST['color'] ?? '#4a90e2';
                if ($name === '') { throw new RuntimeException('Nom requis'); }
                create_project($db, $name, $color);
            } elseif ($op === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $color = $_POST['color'] ?? '#4a90e2';
                $archived = !empty($_POST['archived']);
                if ($id <= 0 || $name === '') { throw new RuntimeException('Paramètres invalides'); }
                update_project($db, $id, $name, $color, $archived);
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
    $title = 'Projets — LB Time Tracker';
    $page = 'projects';
    ob_start();
    include BASE_DIR . '/views/projects.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content);
}
