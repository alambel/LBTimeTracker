<div class="page-header">
    <h1>Résumé</h1>
    <form method="get" class="filter-form">
        <input type="hidden" name="action" value="summary">
        <label>Du <input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>Au <input type="date" name="to" value="<?= e($to) ?>"></label>
        <button type="submit">Filtrer</button>
        <a href="index.php?action=summary&from=<?= e($from) ?>&to=<?= e($to) ?>&format=csv" class="btn-link">Export CSV</a>
    </form>
</div>

<?php if (empty($rows)): ?>
    <div class="empty-state"><p>Aucune saisie sur cette période.</p></div>
<?php else: ?>

<div class="summary-kpis">
    <div class="kpi">
        <div class="kpi-label">Demi-journées</div>
        <div class="kpi-value"><?= (int)$total ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Jours équivalents</div>
        <div class="kpi-value"><?= number_format($total / 2, 1) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Projets</div>
        <div class="kpi-value"><?= count($rows) ?></div>
    </div>
</div>

<table class="summary-table">
    <thead>
        <tr>
            <th>Projet</th>
            <th class="num">Demi-journées</th>
            <th class="num">Jours équiv.</th>
            <th class="num">% du total</th>
            <th class="bar-col">Répartition</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): $pct = $total ? 100 * $r['half_days'] / $total : 0; ?>
            <tr>
                <td>
                    <span class="color-dot" style="background:<?= e($r['color']) ?>"></span>
                    <?= e($r['name']) ?>
                </td>
                <td class="num"><?= (int)$r['half_days'] ?></td>
                <td class="num"><?= number_format((float)$r['full_days'], 1) ?></td>
                <td class="num"><?= number_format($pct, 1) ?> %</td>
                <td class="bar-col">
                    <div class="bar"><div class="bar-fill" style="width:<?= number_format($pct, 2, '.', '') ?>%;background:<?= e($r['color']) ?>"></div></div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php endif; ?>
