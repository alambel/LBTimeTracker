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

<!-- Identity hero -->
<form method="post" enctype="multipart/form-data" class="lbtt-identity-hero">
    <?= csrf_field() ?>
    <input type="hidden" name="op" value="identity">

    <div class="lbtt-identity-hero-avatar">
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
        <?php if (!empty($me['avatar_path'])): ?>
            <button type="submit" name="op" value="remove_avatar" class="lbtt-btn lbtt-btn-ghost lbtt-btn-danger"
                    style="font-size: 10px; margin-top: 4px;"
                    data-confirm="Supprimer la photo de profil ?">Retirer</button>
        <?php endif; ?>
        <div class="lbtt-file-hint">JPEG · PNG · WebP · 3 Mo max</div>
    </div>

    <div class="lbtt-identity-hero-main">
        <div class="lbtt-identity-meta">
            <span class="lbtt-mono lbtt-identity-username">@<?= e($me['username']) ?></span>
            <span class="lbtt-role-badge"><?= e($me['slot_mode']) ?></span>
            <?php if (!empty($me['is_app_admin'])): ?>
                <span class="lbtt-role-badge lbtt-role-admin">app admin</span>
            <?php endif; ?>
            <?php if (!empty($me['email_verified_at'])): ?>
                <span class="lbtt-role-badge" title="Email vérifié">✓ email</span>
            <?php endif; ?>
        </div>

        <div class="lbtt-identity-fields-grid">
            <label class="lbtt-field-big">
                <span class="lbtt-label">Prénom</span>
                <input class="lbtt-input lbtt-input-lg" type="text" name="first_name" maxlength="64"
                       value="<?= e($me['first_name'] ?? '') ?>" autocomplete="given-name"
                       placeholder="Ex : André">
            </label>
            <label class="lbtt-field-big">
                <span class="lbtt-label">Nom</span>
                <input class="lbtt-input lbtt-input-lg" type="text" name="last_name" maxlength="64"
                       value="<?= e($me['last_name'] ?? '') ?>" autocomplete="family-name"
                       placeholder="Ex : Lambel">
            </label>
        </div>

        <div class="lbtt-identity-hero-actions">
            <button type="submit" class="lbtt-btn lbtt-btn-primary">Enregistrer l'identité</button>
        </div>
    </div>
</form>

<!-- Settings grid -->
<div class="lbtt-profile-grid">

    <div class="lbtt-profile-card">
        <div class="lbtt-label" style="margin-bottom: 10px;">Email</div>
        <?php if (!empty($me['email'])): ?>
            <div style="margin-bottom: 12px; font-size: 12px;">
                <?php if (!empty($me['email_verified_at'])): ?>
                    <span class="lbtt-chip lbtt-chip-accent">✓ Vérifié</span>
                    <span style="color: var(--lbtt-muted);">depuis <?= e(date('d/m/Y', strtotime((string)$me['email_verified_at']))) ?></span>
                <?php else: ?>
                    <span class="lbtt-chip">non vérifié</span>
                    <form method="post" style="display: inline; margin-left: 6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="op" value="resend_verify">
                        <button type="submit" class="lbtt-btn lbtt-btn-ghost" style="font-size: 10px;">Renvoyer le lien</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
        <div class="lbtt-label" style="margin-bottom: 10px;">Granularité des créneaux</div>
        <?php
            $allowedModes = allowed_slot_modes_for_user($me);
            $currentMode = (string)($me['slot_mode'] ?? default_slot_mode());
            $currentAllowed = isset($allowedModes[$currentMode]);
            $allModes = slot_modes();
        ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="slot_mode">
            <select class="lbtt-select" name="slot_mode">
                <?php foreach ($allowedModes as $key => $cfg): ?>
                    <option value="<?= e($key) ?>" <?= $currentMode === $key ? 'selected' : '' ?>>
                        <?= e($cfg['label']) ?>
                    </option>
                <?php endforeach; ?>
                <?php if (!$currentAllowed && isset($allModes[$currentMode])): ?>
                    <option value="<?= e($currentMode) ?>" selected disabled>
                        <?= e($allModes[$currentMode]['label']) ?> — actuel, réservé admin
                    </option>
                <?php endif; ?>
            </select>
            <?php if (!$currentAllowed): ?>
                <div class="footnote" style="margin-top: 6px; color: var(--lbtt-accent-ink);">
                    Ton mode actuel est réservé aux app admins — tu peux passer à un mode standard, mais pas revenir dessus.
                </div>
            <?php else: ?>
                <div class="footnote" style="margin-top: 6px;">
                    <?= empty($me['is_app_admin'])
                        ? 'Tranches d\'1 h ou demi-journées de 4 h. Le mode 4 × 4 h est réservé aux app admins.'
                        : 'Tous les modes disponibles (tu es app admin).' ?>
                </div>
            <?php endif; ?>
            <button type="submit" class="lbtt-btn lbtt-btn-primary" style="margin-top: 10px;">Enregistrer</button>
        </form>
    </div>

    <div class="lbtt-profile-card">
        <div class="lbtt-label" style="margin-bottom: 10px;">Mot de passe</div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="password">
            <label><span class="lbtt-label">Actuel</span>
                <input class="lbtt-input" type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label><span class="lbtt-label">Nouveau (10 car. min.)</span>
                <input class="lbtt-input" type="password" name="new_password" required minlength="10" maxlength="128" autocomplete="new-password">
            </label>
            <label><span class="lbtt-label">Confirmer</span>
                <input class="lbtt-input" type="password" name="new_password2" required minlength="10" maxlength="128" autocomplete="new-password">
            </label>
            <button type="submit" class="lbtt-btn lbtt-btn-primary" style="margin-top: 10px;">Mettre à jour</button>
        </form>
    </div>

    <div class="lbtt-profile-card lbtt-profile-card-danger">
        <div class="lbtt-label" style="margin-bottom: 10px;">Session</div>
        <p style="font-size: 12px; color: var(--lbtt-muted); margin: 0 0 10px;">
            Se déconnecter clôt la session actuelle. Tes données sont conservées.
        </p>
        <form method="post" action="index.php?action=logout" data-confirm-logout>
            <?= csrf_field() ?>
            <button type="submit" class="lbtt-btn lbtt-btn-danger">Se déconnecter →</button>
        </form>
    </div>
</div>
