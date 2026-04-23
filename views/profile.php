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

    <div class="lbtt-profile-card">
        <div class="lbtt-label" style="margin-bottom: 8px;">Identité</div>
        <div style="font-family: var(--mono); font-size: 12px; color: var(--lbtt-muted); margin-bottom: 12px;">
            <?= e($me['username']) ?> · mode <?= e($me['slot_mode']) ?>
            <?php if (!empty($me['is_app_admin'])): ?> · <span class="lbtt-role-badge lbtt-role-admin">app admin</span><?php endif; ?>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="email">
            <label>
                <span class="lbtt-label">Email</span>
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
