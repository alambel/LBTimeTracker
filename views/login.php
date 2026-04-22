<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Connexion') ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/icon.svg">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <form method="post" action="index.php?action=login" class="auth-form">
        <h1><?= render_app_icon(32, 'app-icon brand-icon') ?><span>LB Time Tracker</span></h1>
        <?php if ($error ?? null): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <label>Utilisateur
            <input type="text" name="username" required autofocus autocomplete="username">
        </label>
        <label>Mot de passe
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit">Se connecter</button>
    </form>
    <?= format_deployment_footer() ?>
</body>
</html>
