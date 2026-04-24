<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a1a1a">
    <title><?= e($title ?? 'Créer un compte — LBTimeTracker') ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" type="image/svg+xml" href="assets/icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&family=JetBrains+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
</head>
<body>
<div class="lbtt-login">
    <div class="lbtt-login-left">
        <div class="lbtt-login-head">
            <span class="lbtt-brand-mark"></span>
            <span class="lbtt-mono">LBTIMETRACKER · V1.0</span>
        </div>
        <div>
            <div class="lbtt-login-wordmark">
                LBTime<br><span class="accent">Tracker</span>
            </div>
            <p class="lbtt-login-tagline">
                Choisis ta granularité de créneaux et commence à suivre ton temps. Tu pourras partager des projets avec d'autres utilisateurs.
            </p>
        </div>
        <div class="lbtt-login-meta">SIGNUP PUBLIC · BCRYPT · SESSION LAX</div>
        <div class="lbtt-login-stripe" aria-hidden="true">
            <div style="background: #c45a2e;"></div>
            <div style="background: #2d4a3e;"></div>
            <div style="background: #5a6f8a;"></div>
            <div style="background: #0a0a0a;"></div>
        </div>
    </div>
    <div class="lbtt-login-right">
        <form method="post" action="index.php?action=signup" class="lbtt-login-form">
            <?= csrf_field() ?>
            <?php if ($invitation ?? null): ?>
                <input type="hidden" name="invite_token" value="<?= e($inviteToken) ?>">
            <?php endif; ?>
            <!-- Honeypot (anti-bot) : ne pas remplir. Caché en CSS + aria-hidden. -->
            <div class="lbtt-hp" aria-hidden="true">
                <label>Site web (laisser vide)
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
            </div>
            <div class="lbtt-label eyebrow">Inscription</div>
            <h1>créer un compte.</h1>
            <?php if ($invitation ?? null): ?>
                <div class="lbtt-cal-tip" style="margin-bottom: 10px;">
                    <span class="lbtt-chip lbtt-chip-accent">Invitation</span>
                    <span class="lbtt-cal-tip-text">
                        Tu as été invité·e à rejoindre <strong><?= e($invitation['project_name']) ?></strong>
                        (rôle : <?= e($invitation['role']) ?>). Crée ton compte pour accepter.
                    </span>
                </div>
            <?php endif; ?>
            <?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>
            <label>
                <span class="lbtt-label">Email</span>
                <input class="lbtt-input" type="email" name="email" required autofocus maxlength="255"
                       autocomplete="email"
                       value="<?= e($form['email'] ?? '') ?>"
                       <?= ($invitation ?? null) ? 'readonly' : '' ?>>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <label>
                    <span class="lbtt-label">Prénom</span>
                    <input class="lbtt-input" type="text" name="first_name" maxlength="64"
                           autocomplete="given-name"
                           value="<?= e($form['first_name'] ?? '') ?>">
                </label>
                <label>
                    <span class="lbtt-label">Nom</span>
                    <input class="lbtt-input" type="text" name="last_name" maxlength="64"
                           autocomplete="family-name"
                           value="<?= e($form['last_name'] ?? '') ?>">
                </label>
            </div>
            <label>
                <span class="lbtt-label">Mot de passe (10 car. min.)</span>
                <input class="lbtt-input" type="password" name="password" required minlength="10" maxlength="128" autocomplete="new-password">
            </label>
            <label>
                <span class="lbtt-label">Confirmer</span>
                <input class="lbtt-input" type="password" name="password2" required minlength="10" maxlength="128" autocomplete="new-password">
            </label>
            <div class="footnote" style="margin-top: 4px; font-size: 11px;">
                Suivi par tranches d'une heure · modifiable ensuite depuis ton profil.
            </div>
            <button type="submit" class="lbtt-btn lbtt-btn-primary lbtt-btn-block">CRÉER →</button>
            <div class="footnote" style="margin-top: 10px;">
                <a href="index.php?action=login" style="color: var(--lbtt-muted); text-decoration: underline;">← J'ai déjà un compte</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
