<?php
$avatar = avatar_url($me);
$initials = user_initials(display_name($me));
$color = user_color_hsl((int)$me['id']);
?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Mon compte</div>
        <h1 class="lbtt-page-title">profil.</h1>
    </div>
</div>

<?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice ?? null): ?>
    <div class="lbtt-cal-tip"><span class="lbtt-chip lbtt-chip-accent">OK</span><span class="lbtt-cal-tip-text"><?= e($notice) ?></span></div>
<?php endif; ?>

<?php if (empty($me['email'])): ?>
    <div class="lbtt-cal-tip" style="border-color: var(--lbtt-accent);">
        <span class="lbtt-chip lbtt-chip-accent">Action requise</span>
        <span class="lbtt-cal-tip-text">
            Ton compte n'a pas encore d'email. Ajoute-le pour pouvoir recevoir des invitations à des projets partagés.
        </span>
    </div>
<?php endif; ?>

<div class="lbtt-profile-grid">

    <div class="lbtt-profile-card lbtt-profile-identity">
        <div class="lbtt-label" style="margin-bottom: 8px;">Identité</div>

        <form method="post" enctype="multipart/form-data" class="lbtt-identity-form">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="identity">

            <div class="lbtt-identity-row">
                <div class="lbtt-identity-avatar">
                    <div class="lbtt-avatar-big" style="<?= $avatar ? '' : 'background: ' . e($color) . ';' ?>">
                        <?php if ($avatar): ?>
                            <img src="<?= e($avatar) ?>" alt="<?= e(display_name($me)) ?>">
                        <?php else: ?>
                            <span class="lbtt-avatar-initials"><?= e($initials) ?></span>
                        <?php endif; ?>
                    </div>
                    <label class="lbtt-file-label">
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="lbtt-file-input">
                        <span class="lbtt-btn lbtt-btn-ghost" style="font-size: 10px;">Choisir une photo…</span>
                    </label>
                    <div style="font-family: var(--mono); font-size: 10px; color: var(--lbtt-muted); margin-top: 4px;">
                        JPEG / PNG / WebP · 3 Mo max · recadré 256×256
                    </div>
                </div>

                <div class="lbtt-identity-fields">
                    <label>
                        <span class="lbtt-label">Prénom</span>
                        <input class="lbtt-input" type="text" name="first_name" maxlength="64"
                               value="<?= e($me['first_name'] ?? '') ?>" autocomplete="given-name">
                    </label>
                    <label>
                        <span class="lbtt-label">Nom</span>
                        <input class="lbtt-input" type="text" name="last_name" maxlength="64"
                               value="<?= e($me['last_name'] ?? '') ?>" autocomplete="family-name">
                    </label>
                    <div style="font-family: var(--mono); font-size: 11px; color: var(--lbtt-muted);">
                        username : <?= e($me['username']) ?> · mode <?= e($me['slot_mode']) ?>
                        <?php if (!empty($me['is_app_admin'])): ?> · <span class="lbtt-role-badge lbtt-role-admin">app admin</span><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lbtt-identity-actions">
                <button type="submit" class="lbtt-btn lbtt-btn-primary">Enregistrer</button>
                <?php if (!empty($me['avatar_path'])): ?>
                    <button type="submit" name="op" value="remove_avatar" class="lbtt-btn lbtt-btn-ghost lbtt-btn-danger"
                            data-confirm="Supprimer la photo de profil ?">Retirer la photo</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="lbtt-profile-card">
        <div class="lbtt-label" style="margin-bottom: 8px;">Email</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="email">
            <label>
                <span class="lbtt-label">Adresse email</span>
                <input class="lbtt-input" type="email" name="email" required maxlength="255"
                       value="<?= e($me['email'] ?? '') ?>" autocomplete="email">
            </label>
            <button type="submit" class="lbtt-btn lbtt-btn-primary" style="margin-top: 10px;">Enregistrer l'email</button>
        </form>
    </div>

    <div class="lbtt-profile-card">
        <div class="lbtt-label" style="margin-bottom: 8px;">Granularité des créneaux</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="slot_mode">
            <select class="lbtt-select" name="slot_mode">
                <?php foreach (slot_modes() as $key => $cfg): ?>
                    <option value="<?= e($key) ?>" <?= ($me['slot_mode'] ?? 'hd4') === $key ? 'selected' : '' ?>>
                        <?= e($cfg['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="lbtt-btn lbtt-btn-primary" style="margin-top: 10px;">Enregistrer</button>
        </form>
    </div>

    <div class="lbtt-profile-card">
        <div class="lbtt-label" style="margin-bottom: 8px;">Mot de passe</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="password">
            <label><span class="lbtt-label">Actuel</span>
                <input class="lbtt-input" type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label><span class="lbtt-label">Nouveau</span>
                <input class="lbtt-input" type="password" name="new_password" required minlength="6" maxlength="128" autocomplete="new-password">
            </label>
            <label><span class="lbtt-label">Confirmer</span>
                <input class="lbtt-input" type="password" name="new_password2" required minlength="6" maxlength="128" autocomplete="new-password">
            </label>
            <button type="submit" class="lbtt-btn lbtt-btn-primary" style="margin-top: 10px;">Mettre à jour</button>
        </form>
    </div>

    <div class="lbtt-profile-card lbtt-profile-card-danger">
        <div class="lbtt-label" style="margin-bottom: 8px;">Session</div>
        <p style="font-size: 12px; color: var(--lbtt-muted); margin: 0 0 10px;">
            Se déconnecter clôt la session actuelle. Tes données sont conservées.
        </p>
        <form method="post" action="index.php?action=logout" data-confirm-logout>
            <?= csrf_field() ?>
            <button type="submit" class="lbtt-btn lbtt-btn-danger">Se déconnecter →</button>
        </form>
    </div>
</div>
