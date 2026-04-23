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
        } elseif (mb_strlen($u) > 64) {
            $error = 'Nom d\'utilisateur trop long (64 car. max).';
        } elseif ($p !== $p2) {
            $error = 'Les mots de passe admin ne correspondent pas.';
        } elseif (strlen($p) < 6) {
            $error = 'Mot de passe admin trop court (6 caractères minimum).';
        } elseif (strlen($p) > 128) {
            $error = 'Mot de passe trop long (128 car. max).';
        } elseif ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPort <= 0 || $dbPort > 65535) {
            $error = 'Paramètres base de données incomplets ou invalides.';
        } elseif (!valid_timezone($tz)) {
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
                // Pour le setup on garde le message (premier run, l'admin débugue sa config)
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
                    header('Location: index.php?action=login&setup=done');
                    exit;
                }
            }
        }
    }
    ?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fafaf7">
    <title>Configuration — LB Time Tracker</title>
    <link rel="icon" type="image/svg+xml" href="assets/icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&family=JetBrains+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
</head>
<body>
<form method="post" class="lbtt-setup" data-setup-wizard>
    <div class="lbtt-setup-head">
        <div class="lbtt-label" data-setup-eyebrow>Installation · <span data-step-num>1</span>/4</div>
        <div class="lbtt-page-title" style="font-size: 32px;">configuration.</div>
    </div>
    <div class="lbtt-setup-progress" aria-hidden="true">
        <div class="done" data-progress="0"></div>
        <div data-progress="1"></div>
        <div data-progress="2"></div>
        <div data-progress="3"></div>
    </div>
    <div class="lbtt-setup-body">
        <?php if ($error): ?><div class="lbtt-error"><?= e($error) ?></div><?php endif; ?>

        <div class="lbtt-setup-step active" data-step="0">
            <div class="lbtt-setup-field">
                <label class="lbtt-label" for="setup-username">Utilisateur admin</label>
                <input id="setup-username" class="lbtt-input" type="text" name="username" required value="<?= e($form['username']) ?>" autocomplete="username">
            </div>
            <div class="lbtt-setup-field">
                <label class="lbtt-label" for="setup-password">Mot de passe (6 car. min.)</label>
                <input id="setup-password" class="lbtt-input" type="password" name="password" required minlength="6" autocomplete="new-password">
            </div>
            <div class="lbtt-setup-field">
                <label class="lbtt-label" for="setup-password2">Confirmer</label>
                <input id="setup-password2" class="lbtt-input" type="password" name="password2" required minlength="6" autocomplete="new-password">
            </div>
            <p class="lbtt-setup-note">
                Hash bcrypt stocké dans <span class="lbtt-mono">config.php</span> (chmod 0600).
            </p>
        </div>

        <div class="lbtt-setup-step" data-step="1">
            <div class="lbtt-setup-grid">
                <div class="lbtt-setup-field full">
                    <label class="lbtt-label" for="setup-db-host">Hôte</label>
                    <input id="setup-db-host" class="lbtt-input" type="text" name="db_host" required value="<?= e($form['db_host']) ?>" placeholder="localhost">
                </div>
                <div class="lbtt-setup-field">
                    <label class="lbtt-label" for="setup-db-port">Port</label>
                    <input id="setup-db-port" class="lbtt-input" type="number" name="db_port" required value="<?= e($form['db_port']) ?>" min="1" max="65535">
                </div>
                <div class="lbtt-setup-field">
                    <label class="lbtt-label" for="setup-db-name">Base</label>
                    <input id="setup-db-name" class="lbtt-input" type="text" name="db_name" required value="<?= e($form['db_name']) ?>">
                </div>
                <div class="lbtt-setup-field">
                    <label class="lbtt-label" for="setup-db-user">User</label>
                    <input id="setup-db-user" class="lbtt-input" type="text" name="db_user" required value="<?= e($form['db_user']) ?>" autocomplete="off">
                </div>
                <div class="lbtt-setup-field">
                    <label class="lbtt-label" for="setup-db-pass">Mdp</label>
                    <input id="setup-db-pass" class="lbtt-input" type="password" name="db_password" autocomplete="new-password">
                </div>
            </div>
            <p class="lbtt-setup-note">
                L'utilisateur DB doit avoir
                <span class="lbtt-mono">SELECT · INSERT · UPDATE · DELETE · CREATE · ALTER · INDEX · REFERENCES</span>.
            </p>
        </div>

        <div class="lbtt-setup-step" data-step="2">
            <div class="lbtt-setup-field">
                <label class="lbtt-label" for="setup-tz">Timezone</label>
                <input id="setup-tz" class="lbtt-input" type="text" name="timezone" value="<?= e($form['timezone']) ?>" placeholder="Europe/Zurich">
            </div>
            <p class="lbtt-setup-note">
                Utilisée pour l'agrégation quotidienne et les bornes de période (format
                <span class="lbtt-mono">Continent/Ville</span>).
            </p>
        </div>

        <div class="lbtt-setup-step" data-step="3">
            <div class="lbtt-setup-success">
                <div class="lbtt-setup-tick">✓</div>
                <div class="lbtt-serif" style="font-size: 22px; margin-bottom: 6px; text-transform: lowercase;">Prêt à installer.</div>
                <p style="font-size: 12px; color: var(--lbtt-muted);">
                    L'étape suivante teste la connexion, crée les tables et écrit
                    <span class="lbtt-mono">config.php</span>.
                </p>
            </div>
        </div>
    </div>
    <div class="lbtt-setup-actions">
        <button type="button" class="lbtt-btn lbtt-btn-ghost" data-setup-prev hidden>← Précédent</button>
        <button type="button" class="lbtt-btn lbtt-btn-primary" data-setup-next>Continuer →</button>
        <button type="submit" class="lbtt-btn lbtt-btn-primary" data-setup-submit hidden>Tester et installer →</button>
    </div>
</form>
<?= format_deployment_footer() ?>
<script src="<?= asset_url('assets/app.js') ?>"></script>
</body>
</html><?php
}
