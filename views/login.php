<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Connexion') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <form method="post" action="index.php?action=login" class="auth-form">
        <h1>⏱ LB Time Tracker</h1>
        <?php if ($error ?? null): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <label>Utilisateur
            <input type="text" name="username" required autofocus autocomplete="username">
        </label>
        <label>Mot de passe
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit">Se connecter</button>
    </form>
</body>
</html>
