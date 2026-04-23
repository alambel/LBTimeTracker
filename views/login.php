<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a1a1a">
    <title><?= e($title ?? 'Connexion — LB Time Tracker') ?></title>
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
            <span class="lbtt-mono">LB — TIME TRACKER · V1.0</span>
        </div>
        <div>
            <div class="lbtt-login-wordmark">
                carnet<br><span class="accent">d'heures.</span>
            </div>
            <p class="lbtt-login-tagline">
                Suivi du temps par créneau — matin, après-midi, soir, nuit. Une couleur par projet, un CSV par mois.
            </p>
        </div>
        <div class="lbtt-login-meta">PHP · MARIADB · AUCUNE DÉPENDANCE</div>
        <div class="lbtt-login-stripe" aria-hidden="true">
            <div style="background: #c45a2e;"></div>
            <div style="background: #2d4a3e;"></div>
            <div style="background: #5a6f8a;"></div>
            <div style="background: #0a0a0a;"></div>
        </div>
    </div>
    <div class="lbtt-login-right">
        <form method="post" action="index.php?action=login" class="lbtt-login-form">
            <?= csrf_field() ?>
            <div class="lbtt-label eyebrow">Connexion</div>
            <h1>se connecter.</h1>
            <?php if ($error ?? null): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>
            <label>
                <span class="lbtt-label">Utilisateur</span>
                <input class="lbtt-input" type="text" name="username" required autofocus autocomplete="username">
            </label>
            <label>
                <span class="lbtt-label">Mot de passe</span>
                <input class="lbtt-input" type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="lbtt-btn lbtt-btn-primary lbtt-btn-block">SE CONNECTER →</button>
            <div class="footnote">BCRYPT · HTTPONLY · SAMESITE=LAX</div>
        </form>
    </div>
</div>
<?= format_deployment_footer() ?>
</body>
</html>
