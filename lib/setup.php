<?php
function handle_setup(): void {
    $error = null;
    $form = [
        'username' => $_POST['username'] ?? '',
        'db_host' => $_POST['db_host'] ?? 'localhost',
        'db_port' => $_POST['db_port'] ?? '3306',
        'db_name' => $_POST['db_name'] ?? '',
        'db_user' => $_POST['db_user'] ?? '',
        'timezone' => $_POST['timezone'] ?? 'Europe/Zurich',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $u = trim($form['username']);
        $p = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');
        $dbHost = trim($form['db_host']);
        $dbPort = (int)$form['db_port'];
        $dbName = trim($form['db_name']);
        $dbUser = trim($form['db_user']);
        $dbPass = (string)($_POST['db_password'] ?? '');
        $tz = trim($form['timezone']) ?: 'Europe/Zurich';

        if ($u === '' || $p === '') {
            $error = 'Nom d\'utilisateur et mot de passe admin requis.';
        } elseif ($p !== $p2) {
            $error = 'Les mots de passe admin ne correspondent pas.';
        } elseif (strlen($p) < 6) {
            $error = 'Mot de passe admin trop court (6 caractères minimum).';
        } elseif ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPort <= 0) {
            $error = 'Paramètres base de données incomplets.';
        } elseif (!@date_default_timezone_set($tz)) {
            $error = 'Timezone invalide (ex: Europe/Zurich).';
        } elseif (!is_writable(BASE_DIR)) {
            $error = 'Le répertoire de l\'application n\'est pas accessible en écriture.';
        } else {
            $dbConfig = [
                'host' => $dbHost,
                'port' => $dbPort,
                'dbname' => $dbName,
                'user' => $dbUser,
                'password' => $dbPass,
                'charset' => 'utf8mb4',
            ];
            try {
                $testDb = db_init($dbConfig);
                unset($testDb);
            } catch (Throwable $e) {
                $error = 'Connexion MariaDB impossible : ' . $e->getMessage();
            }

            if ($error === null) {
                $hash = password_hash($p, PASSWORD_BCRYPT);
                $config = [
                    'username' => $u,
                    'password_hash' => $hash,
                    'session_name' => 'lbtt',
                    'timezone' => $tz,
                    'db' => $dbConfig,
                ];
                $content = "<?php\n// LB Time Tracker — configuration\n// Généré au premier setup. Supprimer ce fichier force une reconfiguration.\nreturn " . var_export($config, true) . ";\n";
                if (file_put_contents(CONFIG_PATH, $content) === false) {
                    $error = 'Impossible d\'écrire config.php (permissions du répertoire ?).';
                } else {
                    @chmod(CONFIG_PATH, 0600);
                    header('Location: index.php?action=login');
                    exit;
                }
            }
        }
    }
    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuration — LB Time Tracker</title>
    <link rel="icon" type="image/svg+xml" href="assets/icon.svg">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
    <form method="post" class="auth-form setup-form">
        <h1><?= render_app_icon(32, 'app-icon brand-icon') ?><span>LB Time Tracker</span></h1>
        <p class="auth-subtitle">Configuration initiale</p>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

        <fieldset>
            <legend>Administrateur</legend>
            <label>Nom d'utilisateur
                <input type="text" name="username" required autofocus value="<?= e($form['username']) ?>" autocomplete="username">
            </label>
            <label>Mot de passe (6 car. min.)
                <input type="password" name="password" required minlength="6" autocomplete="new-password">
            </label>
            <label>Confirmer
                <input type="password" name="password2" required minlength="6" autocomplete="new-password">
            </label>
        </fieldset>

        <fieldset>
            <legend>Base de données MariaDB / MySQL</legend>
            <div class="grid-2">
                <label>Hôte
                    <input type="text" name="db_host" required value="<?= e($form['db_host']) ?>" placeholder="localhost">
                </label>
                <label>Port
                    <input type="number" name="db_port" required value="<?= e($form['db_port']) ?>" min="1" max="65535">
                </label>
            </div>
            <label>Nom de la base
                <input type="text" name="db_name" required value="<?= e($form['db_name']) ?>">
            </label>
            <label>Utilisateur
                <input type="text" name="db_user" required value="<?= e($form['db_user']) ?>" autocomplete="off">
            </label>
            <label>Mot de passe
                <input type="password" name="db_password" autocomplete="new-password">
            </label>
            <p class="hint">La base doit exister et l'utilisateur doit avoir les droits <code>SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES</code>.</p>
        </fieldset>

        <fieldset>
            <legend>Paramètres</legend>
            <label>Timezone
                <input type="text" name="timezone" value="<?= e($form['timezone']) ?>" placeholder="Europe/Zurich">
            </label>
        </fieldset>

        <button type="submit">Tester la connexion et enregistrer</button>
    </form>
    <?= format_deployment_footer() ?>
</body>
</html><?php
}
