<?php
$projectsJson = json_encode(
    array_map(fn($p) => ['id' => (int)$p['id'], 'name' => $p['name'], 'color' => $p['color']], $projects),
    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);
$periodLabels = period_labels($slotMode);
$periodHours = period_hours($slotMode);
$periodCodes = period_codes($slotMode);
$slotModeJson = json_encode([
    'mode' => $slotMode,
    'codes' => $periodCodes,
    'labels' => $periodLabels,
    'hours' => $periodHours,
], JSON_UNESCAPED_UNICODE);
?>
<div class="lbtt-no-select" data-calendar
     data-projects='<?= e($projectsJson) ?>'
     data-slot-mode='<?= e($slotModeJson) ?>'
     data-month="<?= e($month) ?>">

    <div class="lbtt-page-head">
        <div>
            <div class="lbtt-label">Mois en cours · <?= (int)$year ?></div>
            <h1 class="lbtt-page-title"><?= e($monthLabel) ?>.</h1>
        </div>
        <div class="lbtt-cal-head-right">
            <div class="lbtt-cal-head-stat">
                <div class="lbtt-label">Saisi</div>
                <div class="val"><?= number_format($totalHours, 1, ',', ' ') ?><span class="unit"> h</span></div>
            </div>
            <div class="lbtt-rule-v" style="height: 40px;"></div>
            <div>
                <div class="lbtt-label">Dominant</div>
                <div class="lbtt-cal-head-dom">
                    <span class="sw" style="background: <?= e($topProject['color'] ?? 'var(--lbtt-muted)') ?>;"></span>
                    <span class="nm"><?= e($topProject['name'] ?? '—') ?></span>
                </div>
            </div>
            <div class="lbtt-rule-v" style="height: 40px;"></div>
            <div class="lbtt-cal-head-pager">
                <a class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" href="<?= e(url('calendar', ['month' => $prevMonth])) ?>">‹</a>
                <a class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" href="<?= e(url('calendar')) ?>">AUJ.</a>
                <a class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" href="<?= e(url('calendar', ['month' => $nextMonth])) ?>">›</a>
            </div>
        </div>
    </div>

    <?php if (empty($projects)): ?>
        <div class="lbtt-cal-tip">
            <span class="lbtt-chip">Aucun projet</span>
            <span class="lbtt-cal-tip-text">
                <a href="<?= e(url('projects')) ?>" style="text-decoration: underline;">Créer un projet</a> pour commencer à saisir.
            </span>
        </div>
    <?php else: ?>
        <div class="lbtt-cal-tip">
            <span class="lbtt-chip">Astuce</span>
            <span class="lbtt-cal-tip-text">
                Cliquez un créneau pour l'éditer.
            </span>
        </div>
    <?php endif; ?>

    <!-- ========== Desktop calendar ========== -->
    <div class="lbtt-cal-wrap-desktop">
        <div class="lbtt-cal">
            <div class="lbtt-cal-weekdays">
                <?php foreach (weekday_names_fr_long() as $wd): ?>
                    <div><?= e($wd) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="lbtt-cal-grid">
                <?php
                $totalCells = count($cells);
                foreach ($cells as $i => $cell):
                    $isLastRow = $i >= $totalCells - 7;
                    $lastRowCls = $isLastRow ? ' last-row' : '';
                    if ($cell === null):
                ?>
                    <div class="lbtt-cal-cell empty<?= $lastRowCls ?>"></div>
                <?php else:
                    $cls = 'lbtt-cal-cell' . $lastRowCls;
                    if ($cell['weekend']) $cls .= ' weekend';
                    if ($cell['today']) $cls .= ' today';
                ?>
                    <div class="<?= $cls ?>">
                        <div class="lbtt-cal-cell-head">
                            <span class="lbtt-cal-day-num"><?= $cell['day'] ?></span>
                            <?php if ($cell['today']): ?>
                                <span class="lbtt-chip lbtt-chip-accent" style="font-size: 7.5px; padding: 2px 5px;">Auj.</span>
                            <?php endif; ?>
                        </div>
                        <div class="lbtt-cal-slots">
                            <?php foreach ($periodCodes as $period):
                                $entry = $byKey[$cell['date'] . '_' . $period] ?? null;
                                $filled = $entry !== null;
                                $bg = $filled ? ' style="background: ' . e($entry['project_color']) . ';"' : '';
                                $nm = $filled ? e($entry['project_name']) : '—';
                                $note = $filled ? (string)($entry['note'] ?? '') : '';
                            ?>
                                <button type="button"
                                        class="lbtt-cal-slot<?= $filled ? ' filled' : '' ?>"
                                        data-slot data-ymd="<?= e($cell['date']) ?>" data-sk="<?= e($period) ?>"
                                        data-project-id="<?= $filled ? (int)$entry['project_id'] : '0' ?>"
                                        data-note="<?= e($note) ?>"
                                        <?= $bg ?>>
                                    <span class="sk"><?= e($period) ?></span>
                                    <span class="nm"><?= $nm ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <?php if (!empty($projects)): ?>
            <div class="lbtt-cal-legend">
                <?php foreach ($projects as $p): ?>
                    <span class="lbtt-tag">
                        <span class="lbtt-tag-dot" style="background: <?= e($p['color']) ?>;"></span>
                        <?= e($p['name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========== Mobile calendar ========== -->
    <?php
        $today = date('Y-m-d');
        $todayEntries = [];
        foreach ($periodCodes as $p) {
            $todayEntries[$p] = $byKey[$today . '_' . $p] ?? null;
        }
        $todayFilled = count(array_filter($todayEntries));
        $todayDay = (int)date('j');
    ?>
    <div class="lbtt-mcal-wrap">
        <div class="lbtt-mcal-today">
            <div class="lbtt-mcal-today-tile"><?= $todayDay ?></div>
            <div class="lbtt-mcal-today-body">
                <div class="lbtt-label">Aujourd'hui · <?= $todayFilled ?>/<?= count($periodCodes) ?></div>
                <div class="lbtt-mcal-today-strips">
                    <?php foreach ($periodCodes as $p): $entry = $todayEntries[$p]; $filled = $entry !== null; ?>
                        <button type="button"
                                class="lbtt-mcal-today-strip<?= $filled ? ' filled' : '' ?>"
                                data-slot data-ymd="<?= e($today) ?>" data-sk="<?= e($p) ?>"
                                data-project-id="<?= $filled ? (int)$entry['project_id'] : '0' ?>"
                                data-note="<?= e($filled ? (string)($entry['note'] ?? '') : '') ?>"
                                aria-label="<?= e($periodLabels[$p]) ?>"
                                <?= $filled ? ' style="background: ' . e($entry['project_color']) . ';"' : '' ?>>
                            <span class="sk"><?= e($p) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="lbtt-mcal-weekdays">
            <?php foreach (weekday_letters_fr() as $wd): ?><div><?= e($wd) ?></div><?php endforeach; ?>
        </div>
        <div class="lbtt-mcal-grid">
            <?php foreach ($cells as $cell):
                if ($cell === null):
            ?>
                <div class="lbtt-mcal-cell empty"></div>
            <?php else:
                $cls = 'lbtt-mcal-cell';
                if ($cell['today']) $cls .= ' today';
            ?>
                <div class="<?= $cls ?>">
                    <div class="d"><?= $cell['day'] ?></div>
                    <div class="lbtt-mcal-strips">
                        <?php foreach ($periodCodes as $period):
                            $entry = $byKey[$cell['date'] . '_' . $period] ?? null;
                            $bg = $entry ? ' style="background: ' . e($entry['project_color']) . ';"' : '';
                        ?>
                            <button type="button" data-slot
                                    data-ymd="<?= e($cell['date']) ?>"
                                    data-sk="<?= e($period) ?>"
                                    data-project-id="<?= $entry ? (int)$entry['project_id'] : '0' ?>"
                                    data-note="<?= e($entry['note'] ?? '') ?>"
                                    aria-label="<?= e($periodLabels[$period]) ?> <?= e($cell['date']) ?>"
                                    <?= $bg ?>></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; endforeach; ?>
        </div>
        <?php if (!empty($projects)): ?>
            <div class="lbtt-cal-legend" style="padding: 12px 0;">
                <?php foreach (array_slice($projects, 0, 8) as $p): ?>
                    <span class="lbtt-tag" style="font-size: 10px;">
                        <span class="lbtt-tag-dot" style="background: <?= e($p['color']) ?>;"></span>
                        <?= e($p['name']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== Edit sheet (shared) ========== -->
<div class="lbtt-sheet-overlay" id="lbtt-sheet-overlay" hidden>
    <div class="lbtt-sheet" role="dialog" aria-labelledby="lbtt-sheet-title" aria-modal="true">
        <div class="lbtt-sheet-handle" aria-hidden="true"></div>
        <div class="lbtt-sheet-head">
            <div>
                <div class="lbtt-label" id="lbtt-sheet-eyebrow"></div>
                <h3 id="lbtt-sheet-title"></h3>
            </div>
            <button type="button" class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" id="lbtt-sheet-close">Fermer</button>
        </div>
        <div class="lbtt-label" style="margin-bottom: 6px;">Projet</div>
        <div class="lbtt-sheet-projects" id="lbtt-sheet-projects"></div>
        <div class="lbtt-sheet-note" id="lbtt-sheet-note-wrap">
            <div class="lbtt-label" style="margin-bottom: 5px;">Note (facultatif)</div>
            <input class="lbtt-input" id="lbtt-sheet-note" type="text" maxlength="200" placeholder="Ex: sprint X, bug Y…">
        </div>
    </div>
</div>
