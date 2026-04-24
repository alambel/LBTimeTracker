<?php
$palette = ['#c45a2e', '#2d4a3e', '#c9a24a', '#5a6f8a', '#8a6a4a', '#a14a3a', '#6a8a5a', '#a8a094'];
$meId = current_user_id();
?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Taxonomie</div>
        <h1 class="lbtt-page-title">projets.</h1>
    </div>
</div>

<?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>

<?php if (!empty($flash)):
    $isInvited = ($flash['kind'] ?? '') === 'invited';
?>
<div class="lbtt-cal-tip" style="margin-bottom: 10px;">
    <span class="lbtt-chip lbtt-chip-accent"><?= $isInvited ? 'Invitation' : 'OK' ?></span>
    <span class="lbtt-cal-tip-text">
        <?= e($flash['msg'] ?? '') ?>
        <?php if (!empty($flash['url'])): ?>
            <br><span style="font-family: var(--mono); font-size: 11px;">Lien :
                <a href="<?= e($flash['url']) ?>" style="text-decoration: underline;"><?= e($flash['url']) ?></a>
            </span>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>

<form method="post" class="lbtt-proj-new" data-new-project>
    <?= csrf_field() ?>
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
        <span class="lbtt-cal-tip-text">Aucun projet pour l'instant. Crée-en un ou demande à un admin de t'ajouter.</span>
    </div>
<?php else: ?>
<div class="lbtt-proj-list">
    <?php foreach ($projects as $p):
        $pid = (int)$p['id'];
        $count = $entryCounts[$pid] ?? 0;
        $members = $membersByProject[$pid] ?? [];
        $myRole = $p['my_role'] ?? 'member';
        $isAdmin = ($myRole === 'admin');
        $rowCls = 'lbtt-proj-card' . (!empty($p['archived']) ? ' archived' : '');
    ?>
        <div class="<?= $rowCls ?>" data-project-card data-project-link="index.php?action=team&amp;id=<?= $pid ?>"
             role="link" tabindex="0"
             aria-label="Voir l'équipe du projet <?= e($p['name']) ?>">
            <div class="lbtt-proj-card-head">
                <div class="lbtt-proj-swatch" style="color: <?= e($p['color']) ?>;"></div>
                <div class="lbtt-proj-card-name">
                    <span class="lbtt-proj-title"><?= e($p['name']) ?></span>
                    <span class="lbtt-proj-sub">
                        <?= (int)$count ?> saisie<?= $count > 1 ? 's' : '' ?> · <?= count($members) ?> membre<?= count($members) > 1 ? 's' : '' ?>
                        <?php if (!empty($p['archived'])): ?> · <span class="lbtt-chip">Archivé</span><?php endif; ?>
                    </span>
                </div>
                <div class="lbtt-proj-card-actions">
                    <?php if ($isAdmin): ?>
                        <button type="button" class="lbtt-btn lbtt-btn-ghost" style="font-size: 10px;" data-toggle-project-manage>Gérer ▾</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lbtt-proj-members">
                <?php foreach ($members as $m):
                    $isMe = ((int)$m['id'] === (int)$meId);
                    $mCls = 'lbtt-tag' . ($m['role'] === 'admin' ? ' is-admin' : '');
                ?>
                    <span class="<?= $mCls ?>" title="<?= e($m['username']) ?> — <?= e($m['role']) ?>">
                        <span class="lbtt-tag-dot" style="background: <?= e($p['color']) ?>;"></span>
                        <?= e($m['username']) ?>
                        <span class="lbtt-role-badge lbtt-role-<?= e($m['role']) ?>"><?= $m['role'] === 'admin' ? 'admin' : 'member' ?></span>
                        <?php if (!empty($m['archived'])): ?><span class="lbtt-chip" style="margin-left: 4px;">archivé</span><?php endif; ?>
                        <?php if ($isMe): ?><span style="color: var(--lbtt-muted);">(moi)</span><?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <?php if ($isAdmin): ?>
            <div class="lbtt-proj-manage" hidden data-project-manage>
                <!-- renommer / couleur / archiver -->
                <form method="post" class="lbtt-proj-manage-row">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $pid ?>">
                    <input type="hidden" name="archived" value="<?= !empty($p['archived']) ? 1 : 0 ?>">
                    <div class="lbtt-proj-manage-inputs">
                        <label class="lbtt-label">Nom
                            <input class="lbtt-input" type="text" name="name" value="<?= e($p['name']) ?>" maxlength="100" required>
                        </label>
                        <label class="lbtt-label">Couleur
                            <input class="lbtt-input" type="text" name="color" value="<?= e($p['color']) ?>" pattern="#[0-9a-fA-F]{6}" required>
                        </label>
                    </div>
                    <div class="lbtt-proj-manage-btns">
                        <button type="submit" name="op" value="update" class="lbtt-btn lbtt-btn-primary" style="font-size: 11px;">Enregistrer</button>
                        <button type="submit" name="op" value="archive_toggle" class="lbtt-btn lbtt-btn-ghost" style="font-size: 11px;"
                                data-confirm="<?= !empty($p['archived']) ? 'Restaurer' : 'Archiver' ?> ce projet ?">
                            <?= !empty($p['archived']) ? '↑ Restaurer' : '▢ Archiver' ?>
                        </button>
                    </div>
                </form>

                <!-- gestion des membres -->
                <div class="lbtt-proj-members-mgmt">
                    <div class="lbtt-label">Membres</div>
                    <?php foreach ($members as $m):
                        $targetId = (int)$m['id'];
                        $isMe = $targetId === (int)$meId;
                    ?>
                        <form method="post" class="lbtt-proj-member-row">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $pid ?>">
                            <input type="hidden" name="user_id" value="<?= $targetId ?>">
                            <span class="lbtt-proj-member-name"><?= e($m['username']) ?><?php if ($isMe): ?> <span style="color: var(--lbtt-muted);">(moi)</span><?php endif; ?></span>
                            <select name="role" class="lbtt-select" style="font-size: 11px; padding: 4px 6px;">
                                <option value="admin" <?= $m['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
                                <option value="member" <?= $m['role'] === 'member' ? 'selected' : '' ?>>member</option>
                            </select>
                            <button type="submit" name="op" value="set_member_role" class="lbtt-btn lbtt-btn-ghost" style="font-size: 10px;">Rôle</button>
                            <button type="submit" name="op" value="remove_member" class="lbtt-btn lbtt-btn-ghost lbtt-btn-danger" style="font-size: 10px;"
                                    data-confirm="Retirer <?= e($m['username']) ?> du projet ?">Retirer</button>
                        </form>
                    <?php endforeach; ?>

                    <form method="post" class="lbtt-proj-add-member">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="invite">
                        <input type="hidden" name="id" value="<?= $pid ?>">
                        <input class="lbtt-input" type="email" name="invite_email" required placeholder="email à inviter" maxlength="255">
                        <select name="invite_role" class="lbtt-select" style="font-size: 11px;">
                            <option value="member">member</option>
                            <option value="admin">admin</option>
                        </select>
                        <button type="submit" class="lbtt-btn lbtt-btn-primary" style="font-size: 11px;">+ Inviter</button>
                    </form>

                    <?php $pendingInvites = $invitationsByProject[$pid] ?? [];
                          if (!empty($pendingInvites)): ?>
                        <div class="lbtt-label" style="margin-top: 10px;">Invitations en attente</div>
                        <?php foreach ($pendingInvites as $inv):
                            $inviteUrl = 'index.php?action=signup&invite=' . urlencode((string)$inv['token']);
                        ?>
                            <div class="lbtt-proj-invite-row">
                                <span class="lbtt-proj-invite-email"><?= e($inv['email']) ?></span>
                                <span class="lbtt-role-badge lbtt-role-<?= e($inv['role']) ?>"><?= e($inv['role']) ?></span>
                                <span class="lbtt-proj-invite-expires">exp. <?= e(date('d M', strtotime((string)$inv['expires_at']))) ?></span>
                                <a href="<?= e($inviteUrl) ?>" class="lbtt-btn lbtt-btn-ghost" style="font-size: 10px;" target="_blank" rel="noopener">Lien</a>
                                <form method="post" style="display: inline; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="op" value="revoke_invitation">
                                    <input type="hidden" name="id" value="<?= $pid ?>">
                                    <input type="hidden" name="invitation_id" value="<?= (int)$inv['id'] ?>">
                                    <button type="submit" class="lbtt-btn lbtt-btn-ghost lbtt-btn-danger" style="font-size: 10px;"
                                            data-confirm="Révoquer l'invitation pour <?= e($inv['email']) ?> ?">
                                        Révoquer
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
                <form method="post" style="margin-top: 6px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="op" value="remove_member">
                    <input type="hidden" name="id" value="<?= $pid ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$meId ?>">
                    <button type="submit" class="lbtt-btn lbtt-btn-ghost lbtt-btn-danger" style="font-size: 10px;"
                            data-confirm="Quitter ce projet ?">Quitter le projet</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
