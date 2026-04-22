<?php
function handle_setup(): void {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $u = trim($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');
        if ($u === '' || $p === '') {
            $error = 'Nom d\'utilisateur et mot de passe requis';
        } elseif ($p !== $p2) {
            $error = 'Les mots de passe ne correspondent pas';
        } elseif (strlen($p) < 6) {
            $error = 'Mot de passe trop court (6 caractères minimum)';
        } elseif (!is_writable(BASE_DIR)) {
            $error = 'Le répertoire de l\'application n\'est pas accessible en écriture.';
        } else {
            $hash = password_hash($p, PASSWORD_BCRYPT);
            $config = [
                'username' => $u,
                'password_hash' => $hash,
                'session_name' => 'lbtt',
                'timezone' => 'Europe/Zurich',
            ];
            $content = "<?php\n// LB Time Tracker — configuration\n// Généré automatiquement. Supprimer ce fichier force un nouveau setup.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents(CONFIG_PATH, $content) === false) {
                $error = 'Impossible d\'écrire config.php';
            } else {
                @chmod(CONFIG_PATH, 0600);
                header('Location: index.php?action=login');
                exit;
            }
        }
    }
    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuration — LB Time Tracker</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <form method="post" class="auth-form">
        <h1>⏱ LB Time Tracker</h1>
        <p class="auth-subtitle">Configuration initiale — créer l'utilisateur.</p>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <label>Nom d'utilisateur
            <input type="text" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username">
        </label>
        <label>Mot de passe (6 car. min.)
            <input type="password" name="password" required minlength="6" autocomplete="new-password">
        </label>
        <label>Confirmer
            <input type="password" name="password2" required minlength="6" autocomplete="new-password">
        </label>
        <button type="submit">Créer et continuer</button>
    </form>
</body>
</html><?php
}
