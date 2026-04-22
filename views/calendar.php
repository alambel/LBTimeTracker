<div class="page-header">
    <div class="month-nav">
        <a href="index.php?action=calendar&month=<?= e($prevMonth) ?>" class="btn-icon" title="Mois précédent">‹</a>
        <h1><?= e(month_title($month)) ?></h1>
        <a href="index.php?action=calendar&month=<?= e($nextMonth) ?>" class="btn-icon" title="Mois suivant">›</a>
        <a href="index.php?action=calendar" class="btn-link">Aujourd'hui</a>
    </div>
</div>

<?php if (empty($projects)): ?>
    <div class="empty-state">
        <p>Aucun projet disponible. <a href="index.php?action=projects">Créer un projet</a> pour commencer à saisir du temps.</p>
    </div>
<?php else: ?>

<?php
    $projectsJson = json_encode(
        array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'color' => $p['color']], $projects),
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
?>
<div class="calendar"
     data-month="<?= e($month) ?>"
     data-projects='<?= e($projectsJson) ?>'>
    <div class="cal-header">
        <div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
    </div>
    <div class="cal-grid">
        <?php
        [$firstDay, $lastDay] = month_bounds($month);
        $first = new DateTime($firstDay);
        $last = new DateTime($lastDay);
        $startOffset = ((int)$first->format('N')) - 1;
        for ($i = 0; $i < $startOffset; $i++) {
            echo '<div class="cal-cell cal-empty"></div>';
        }
        $cursor = clone $first;
        $today = date('Y-m-d');
        $periods = period_codes();
        $weekdayLabels = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        while ($cursor <= $last) {
            $date = $cursor->format('Y-m-d');
            $isToday = $date === $today;
            $isWeekend = (int)$cursor->format('N') >= 6;
            $classes = 'cal-cell' . ($isToday ? ' today' : '') . ($isWeekend ? ' weekend' : '');
            echo '<div class="' . $classes . '">';
            echo '<div class="cal-date">';
            echo '<span class="cal-weekday">' . $weekdayLabels[(int)$cursor->format('N')] . '</span>';
            echo '<span class="cal-daynum">' . $cursor->format('j') . '</span>';
            echo '</div>';
            foreach ($periods as $period) {
                $entry = $byKey[$date . '_' . $period] ?? null;
                $style = $entry ? ' style="background:' . e($entry['project_color']) . '"' : '';
                $text = $entry ? e($entry['project_name']) : '—';
                $note = $entry ? (string)($entry['note'] ?? '') : '';
                $title = $entry ? e($entry['project_name']) . ($note !== '' ? ' — ' . e($note) : '') : '';
                echo '<button type="button" class="cal-slot' . ($entry ? ' filled' : '') . '"' . $style .
                    ' data-date="' . e($date) . '"' .
                    ' data-period="' . $period . '"' .
                    ' data-project-id="' . (int)($entry['project_id'] ?? 0) . '"' .
                    ' data-note="' . e($note) . '"' .
                    ' title="' . $title . '">' .
                    '<span class="slot-label">' . $period . '</span>' .
                    '<span class="slot-project">' . $text . '</span>' .
                    '</button>';
            }
            echo '</div>';
            $cursor->modify('+1 day');
        }
        ?>
    </div>
</div>

<dialog id="slot-dialog" aria-labelledby="sd-heading">
    <form method="dialog" id="slot-form">
        <div class="sheet-handle" aria-hidden="true"></div>
        <h3 id="sd-heading">
            <strong id="sd-date"></strong>
            <span id="sd-period" class="sd-period-badge"></span>
        </h3>
        <div class="project-grid" id="sd-project-grid" role="radiogroup" aria-label="Projet">
            <!-- rempli par JS -->
        </div>
        <label class="sd-note-label">Note <span class="hint-inline">(facultatif)</span>
            <input type="text" id="sd-note" maxlength="200" placeholder="Ex: sprint X, bug Y…" autocomplete="off">
        </label>
        <div class="dialog-actions">
            <button type="button" id="sd-cancel">Annuler</button>
            <button type="button" id="sd-save" class="primary">Enregistrer</button>
        </div>
    </form>
</dialog>

<?php endif; ?>
