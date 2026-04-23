<?php
function current_user(): ?string {
    return $_SESSION['user'] ?? null;
}

function require_auth(): void {
    if (!current_user()) {
        if (str_starts_with($_GET['action'] ?? '', 'api_')) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'unauthorized']);
        } else {
            header('Location: index.php?action=login');
        }
        exit;
    }
}

function handle_login(array $config): void {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        login_ratelimit_gc();
        // Rate limit d'abord (avant CSRF pour ne pas coûter de bcrypt sur flood)
        $blocked = login_ratelimit_check();
        if ($blocked !== null) {
            $error = $blocked;
        } elseif (!csrf_valid((string)($_POST['_csrf'] ?? ''))) {
            $error = 'Jeton de formulaire expiré. Rafraîchir la page et réessayer.';
        } else {
            $u = trim($_POST['username'] ?? '');
            $p = (string)($_POST['password'] ?? '');
            $expectedUser = (string)($config['username'] ?? '');
            $hash = (string)($config['password_hash'] ?? '');
            if ($u !== '' && hash_equals($expectedUser, $u) && password_verify($p, $hash)) {
                session_regenerate_id(true);
                $_SESSION['user'] = $u;
                login_ratelimit_reset();
                // Upgrade bcrypt hash si le cost a évolué (défault PHP augmente avec les versions)
                if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
                    maybe_rehash_password($config, $p);
                }
                header('Location: index.php?action=calendar');
                exit;
            }
            login_ratelimit_register_failure();
            // Ralentit le brute force (timing constant, pas info enumeration)
            usleep(300000); // 300 ms
            $error = 'Identifiants incorrects';
        }
    }
    $title = 'Connexion — LBTimeTracker';
    include BASE_DIR . '/views/login.php';
}

/** Ré-hash le mot de passe avec le cost bcrypt courant et réécrit config.php. */
function maybe_rehash_password(array $config, string $plainPassword): void {
    try {
        $newHash = password_hash($plainPassword, PASSWORD_BCRYPT);
        $config['password_hash'] = $newHash;
        $content = "<?php\n// LB Time Tracker — configuration\n// Mis à jour automatiquement (rehash bcrypt).\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents(CONFIG_PATH, $content) !== false) {
            @chmod(CONFIG_PATH, 0600);
        }
    } catch (Throwable $e) {
        error_log('LBTT rehash failed: ' . $e->getMessage());
    }
}

function handle_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
