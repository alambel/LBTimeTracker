<?php $meId = current_user_id(); ?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Administration</div>
        <h1 class="lbtt-page-title">utilisateurs.</h1>
    </div>
</div>

<?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>

<div class="lbtt-cal-tip">
    <span class="lbtt-chip">Archivage</span>
    <span class="lbtt-cal-tip-text">Les utilisateurs ne sont jamais supprimés — archive = plus d'accès, données conservées.</span>
</div>

<div class="lbtt-table">
    <div class="lbtt-table-head">
        <div class="lbtt-label">Utilisateur</div>
        <div class="lbtt-label">Mode</div>
        <div class="lbtt-label">App admin</div>
        <div class="lbtt-label">État</div>
        <div class="lbtt-label">Actions</div>
    </div>
    <?php foreach ($users as $u):
        $isMe = (int)$u['id'] === (int)$meId;
        $uAvatar = avatar_url($u);
        $uInitials = user_initials(display_name($u));
        $uColor = user_color_hsl((int)$u['id']);
    ?>
        <div class="lbtt-table-row">
            <div class="lbtt-proj-cell">
                <?php if ($uAvatar): ?>
                    <span class="lbtt-team-avatar has-image"><img src="<?= e($uAvatar) ?>" alt=""></span>
                <?php else: ?>
                    <span class="lbtt-team-avatar" style="background: <?= e($uColor) ?>;"><?= e($uInitials) ?></span>
                <?php endif; ?>
                <span class="nm">
                    <?= e(display_name($u)) ?>
                    <span style="color: var(--lbtt-muted); font-size: 11px;"> @<?= e($u['username']) ?></span>
                    <?php if ($isMe): ?> <span style="color: var(--lbtt-muted);">(moi)</span><?php endif; ?>
                </span>
            </div>
            <div style="font-family: var(--mono); font-size: 11px;"><?= e($u['slot_mode']) ?></div>
            <div>
                <form method="post" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="toggle_app_admin">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="lbtt-btn lbtt-btn-ghost" style="font-size: 11px;"
                            onclick="return confirm('<?= empty($u['is_app_admin']) ? 'Promouvoir' : 'Retirer' ?> app admin ?');">
                        <?= !empty($u['is_app_admin']) ? '✓ admin' : 'non' ?>
                    </button>
                </form>
            </div>
            <div><?= !empty($u['archived']) ? '<span class="lbtt-chip">archivé</span>' : '<span class="lbtt-chip lbtt-chip-accent">actif</span>' ?></div>
            <div>
                <form method="post" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="archive_toggle">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="lbtt-btn lbtt-btn-ghost" style="font-size: 11px;"
                            onclick="return confirm('<?= !empty($u['archived']) ? 'Restaurer' : 'Archiver' ?> <?= e(addslashes($u['username'])) ?> ?');">
                        <?= !empty($u['archived']) ? '↑ Restaurer' : '▢ Archiver' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
