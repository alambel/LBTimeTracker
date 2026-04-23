<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Mon compte</div>
        <h1 class="lbtt-page-title">profil.</h1>
    </div>
</div>

<?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice ?? null): ?><div class="lbtt-cal-tip"><span class="lbtt-chip lbtt-chip-accent">OK</span><span class="lbtt-cal-tip-text"><?= e($notice) ?></span></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr; gap: 18px; max-width: 520px; margin-top: 12px;">
    <form method="post" style="background: var(--lbtt-paper-2); padding: 14px; border: 1px solid var(--lbtt-rule);">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="slot_mode">
        <div class="lbtt-label" style="margin-bottom: 6px;">Granularité des créneaux</div>
        <select class="lbtt-select" name="slot_mode">
            <?php foreach (slot_modes() as $key => $cfg): ?>
                <option value="<?= e($key) ?>" <?= ($me['slot_mode'] ?? 'hd4') === $key ? 'selected' : '' ?>>
                    <?= e($cfg['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="lbtt-btn lbtt-btn-primary" style="margin-top: 10px;">Enregistrer</button>
    </form>

    <form method="post" style="background: var(--lbtt-paper-2); padding: 14px; border: 1px solid var(--lbtt-rule);">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="password">
        <div class="lbtt-label" style="margin-bottom: 6px;">Changer le mot de passe</div>
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
