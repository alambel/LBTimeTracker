<?php
$periodCodes = period_codes();
$fullDays = $total / 2;
$projectsActiveWithEntries = count($rows);
?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Rapport · période filtrée</div>
        <h1 class="lbtt-page-title">résumé.</h1>
    </div>
    <a class="lbtt-btn lbtt-btn-primary"
       href="index.php?action=summary&from=<?= e($from) ?>&to=<?= e($to) ?>&format=csv">↓ EXPORT CSV</a>
</div>

<form method="get" class="lbtt-summary-filter">
    <input type="hidden" name="action" value="summary">
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
    <div class="meta"><?= (int)$daysCount ?> JOURS · <?= (int)$total ?> DEMI-JOURNÉES</div>
</form>

<div class="lbtt-kpis">
    <div class="lbtt-kpi">
        <div class="lbtt-label">Demi-journées</div>
        <div class="lbtt-kpi-val"><?= (int)$total ?></div>
        <div class="lbtt-kpi-sub">SUR <?= (int)$possibleHalfDays ?> POSSIBLES</div>
    </div>
    <div class="lbtt-kpi">
        <div class="lbtt-label">Jours équiv.</div>
        <div class="lbtt-kpi-val"><?= number_format($fullDays, 1, ',', ' ') ?></div>
        <div class="lbtt-kpi-sub"><?= number_format($possibleHalfDays / 2, 0, '', ' ') ?> J. MAX</div>
    </div>
    <div class="lbtt-kpi">
        <div class="lbtt-label">Projets actifs</div>
        <div class="lbtt-kpi-val"><?= (int)$projectsActiveWithEntries ?></div>
        <div class="lbtt-kpi-sub"><?= (int)$archivedCount ?> ARCHIVÉ<?= $archivedCount > 1 ? 'S' : '' ?></div>
    </div>
    <div class="lbtt-kpi">
        <div class="lbtt-label">Complétude</div>
        <div class="lbtt-kpi-val"><?= number_format($completeness, 0) ?>%</div>
        <div class="lbtt-kpi-sub">DEMI-J. REMPLIES</div>
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
        <div class="lbtt-label">Demi-journées</div>
        <div class="lbtt-label">Jours éq.</div>
        <div class="lbtt-label">%</div>
        <div class="lbtt-label col-bar">Répartition</div>
    </div>
    <?php foreach ($rows as $r): $pct = $total ? 100 * $r['half_days'] / $total : 0; ?>
        <div class="lbtt-table-row">
            <div class="lbtt-proj-cell">
                <span class="sw" style="background: <?= e($r['color']) ?>;"></span>
                <span class="nm"><?= e($r['name']) ?></span>
            </div>
            <div class="lbtt-num lbtt-num-md"><?= (int)$r['half_days'] ?></div>
            <div class="lbtt-num lbtt-num-md"><?= number_format((float)$r['full_days'], 1, ',', ' ') ?></div>
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
