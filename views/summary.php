<?php
$projectsActiveWithEntries = count($rows);
?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Rapport · période filtrée</div>
        <h1 class="lbtt-page-title">résumé.</h1>
    </div>
    <a class="lbtt-btn lbtt-btn-primary"
       href="<?= e(url('summary', ['from' => $from, 'to' => $to, 'format' => 'csv'])) ?>">↓ EXPORT CSV</a>
</div>

<form method="get" action="<?= e(url('summary')) ?>" class="lbtt-summary-filter">
    <div class="field">
        <label class="lbtt-label" for="sum-from">Du</label>
        <input id="sum-from" class="lbtt-input" type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="field">
        <label class="lbtt-label" for="sum-to">Au</label>
        <input id="sum-to" class="lbtt-input" type="date" name="to" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="lbtt-btn lbtt-btn-primary">Filtrer →</button>
    <div class="spacer"></div>
    <div class="meta"><?= (int)$daysCount ?> JOURS · <?= number_format($totalHours, 1, ',', ' ') ?> H</div>
</form>

<div class="lbtt-kpis">
    <div class="lbtt-kpi">
        <div class="lbtt-label">Heures</div>
        <div class="lbtt-kpi-val"><?= number_format($totalHours, 1, ',', ' ') ?></div>
        <div class="lbtt-kpi-sub">MODE <?= e(strtoupper($slotMode)) ?></div>
    </div>
    <div class="lbtt-kpi">
        <div class="lbtt-label">Jours équiv.</div>
        <div class="lbtt-kpi-val"><?= number_format($totalHours / 8, 1, ',', ' ') ?></div>
        <div class="lbtt-kpi-sub">BASE 8 H / J</div>
    </div>
    <div class="lbtt-kpi">
        <div class="lbtt-label">Projets actifs</div>
        <div class="lbtt-kpi-val"><?= (int)$projectsActiveWithEntries ?></div>
        <div class="lbtt-kpi-sub">SUR LA PÉRIODE</div>
    </div>
    <div class="lbtt-kpi">
        <div class="lbtt-label">Saisies</div>
        <div class="lbtt-kpi-val"><?= count($entries) ?></div>
        <div class="lbtt-kpi-sub">CRÉNEAUX</div>
    </div>
</div>

<?php if (!empty($rangeDays)): ?>
<div class="lbtt-ribbon-wrap">
    <div class="lbtt-label">Distribution quotidienne — chaque colonne = un jour, chaque bande = un créneau</div>
    <div class="lbtt-ribbon">
        <?php foreach ($rangeDays as $d): ?>
            <div class="lbtt-ribbon-col">
                <?php foreach ($periodCodes as $p):
                    $ent = $byKey[$d . '_' . $p] ?? null;
                    $bg = $ent ? ' style="background: ' . e($ent['project_color']) . ';"' : '';
                ?>
                    <div<?= $bg ?>></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="lbtt-ribbon-ends">
        <span><?= e($fromLabel) ?></span>
        <span><?= e($toLabel) ?></span>
    </div>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <div class="lbtt-cal-tip">
        <span class="lbtt-chip">Vide</span>
        <span class="lbtt-cal-tip-text">Aucune saisie sur cette période.</span>
    </div>
<?php else: ?>
<div class="lbtt-table">
    <div class="lbtt-table-head">
        <div class="lbtt-label">Projet</div>
        <div class="lbtt-label">Saisies</div>
        <div class="lbtt-label">Heures</div>
        <div class="lbtt-label">%</div>
        <div class="lbtt-label col-bar">Répartition</div>
    </div>
    <?php foreach ($rows as $r):
        $pct = $totalMinutes ? 100 * (int)$r['total_minutes'] / $totalMinutes : 0;
        $h = (int)$r['total_minutes'] / 60;
    ?>
        <div class="lbtt-table-row">
            <div class="lbtt-proj-cell">
                <span class="sw" style="background: <?= e($r['color']) ?>;"></span>
                <span class="nm"><?= e($r['name']) ?></span>
            </div>
            <div class="lbtt-num lbtt-num-md"><?= (int)$r['entry_count'] ?></div>
            <div class="lbtt-num lbtt-num-md"><?= number_format($h, 2, ',', ' ') ?></div>
            <div class="lbtt-pct"><?= number_format($pct, 1) ?>%</div>
            <div class="col-bar">
                <div class="lbtt-bar">
                    <div class="lbtt-bar-fill" style="width: <?= number_format($pct, 2, '.', '') ?>%; background: <?= e($r['color']) ?>;"></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
