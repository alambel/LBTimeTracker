<?php
$palette = ['#c45a2e', '#2d4a3e', '#c9a24a', '#5a6f8a', '#8a6a4a', '#a14a3a', '#6a8a5a', '#a8a094'];
?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Taxonomie</div>
        <h1 class="lbtt-page-title">projets.</h1>
    </div>
</div>

<?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="lbtt-proj-new" data-new-project>
    <input type="hidden" name="op" value="create">
    <input type="hidden" name="color" value="<?= e($palette[0]) ?>" data-new-project-color>
    <div class="lbtt-proj-new-input">
        <label class="lbtt-label" for="new-proj-name">Nouveau projet</label>
        <input id="new-proj-name" class="lbtt-input" type="text" name="name" required placeholder="Nom — ex. Refonte Carnet…">
    </div>
    <div>
        <span class="lbtt-label" style="margin-bottom: 4px;">Couleur</span>
        <div class="lbtt-color-swatches" role="radiogroup" aria-label="Couleur">
            <?php foreach ($palette as $i => $c): ?>
                <button type="button"
                        class="lbtt-color-swatch<?= $i === 0 ? ' selected' : '' ?>"
                        style="background: <?= e($c) ?>;"
                        data-color="<?= e($c) ?>"
                        aria-label="<?= e($c) ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
    <button type="submit" class="lbtt-btn lbtt-btn-primary">Ajouter →</button>
</form>

<?php if (empty($projects)): ?>
    <div class="lbtt-cal-tip">
        <span class="lbtt-chip">Vide</span>
        <span class="lbtt-cal-tip-text">Aucun projet pour l'instant.</span>
    </div>
<?php else: ?>
<div class="lbtt-proj-table">
    <?php foreach ($projects as $p):
        $count = $entryCounts[(int)$p['id']] ?? 0;
        $rowCls = 'lbtt-proj-row' . (!empty($p['archived']) ? ' archived' : '');
    ?>
        <form method="post" class="<?= $rowCls ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="name" value="<?= e($p['name']) ?>">
            <input type="hidden" name="color" value="<?= e($p['color']) ?>">
            <div class="lbtt-proj-swatch" style="color: <?= e($p['color']) ?>;"></div>
            <input class="lbtt-proj-edit" type="text" value="<?= e($p['name']) ?>"
                   data-proj-name data-initial="<?= e($p['name']) ?>">
            <div class="lbtt-proj-hex col-hide-mobile"><?= e(strtoupper($p['color'])) ?></div>
            <div class="lbtt-proj-count col-hide-mobile"><?= (int)$count ?> saisie<?= $count > 1 ? 's' : '' ?></div>
            <button type="submit" name="op" value="archive_toggle" class="lbtt-btn lbtt-btn-ghost" style="font-size: 9.5px;">
                <?= !empty($p['archived']) ? '↑ Restaurer' : '▢ Archiver' ?>
            </button>
            <button type="submit" name="op" value="delete" class="lbtt-btn lbtt-btn-ghost lbtt-btn-danger col-hide-mobile" style="font-size: 9.5px;"
                    onclick="return confirm('Supprimer « <?= e(addslashes($p['name'])) ?> » et ses <?= (int)$count ?> saisie(s) ?');">
                Supprimer
            </button>
        </form>
    <?php endforeach; ?>
</div>
<div class="lbtt-proj-cascade-warn">
    ⚠ Supprimer un projet efface aussi toutes ses saisies (CASCADE).
</div>
<?php endif; ?>
