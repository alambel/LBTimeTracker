<?php
function current_user_id(): ?int {
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

function current_user(): ?string {
    return $_SESSION['user'] ?? null;
}

function current_user_row(PDO $db): ?array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $uid = current_user_id();
    if (!$uid) return null;
    $row = get_user($db, $uid);
    if ($row && !empty($row['archived'])) {
        // User archivé : plus d'accès, on kill la session.
        $_SESSION = [];
        return null;
    }
    $cache = $row;
    return $cache;
}

function require_auth(): void {
    if (!current_user_id()) {
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

function require_app_admin(PDO $db): void {
    require_auth();
    $u = current_user_row($db);
    if (!$u || empty($u['is_app_admin'])) {
        http_response_code(403);
        echo 'Accès refusé (app admin requis).';
        exit;
    }
}

function handle_login(PDO $db): void {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        login_ratelimit_gc();
        $blocked = login_ratelimit_check();
        if ($blocked !== null) {
            $error = $blocked;
        } elseif (!csrf_valid((string)($_POST['_csrf'] ?? ''))) {
            $error = 'Jeton de formulaire expiré. Rafraîchir la page et réessayer.';
        } else {
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $row = $u !== '' ? get_user_by_username($db, $u) : null;
            if ($row && empty($row['archived']) && password_verify($p, (string)$row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$row['id'];
                $_SESSION['user'] = (string)$row['username'];
                login_ratelimit_reset();
                if (password_needs_rehash((string)$row['password_hash'], PASSWORD_BCRYPT)) {
                    try {
                        update_user_password($db, (int)$row['id'], password_hash($p, PASSWORD_BCRYPT));
                    } catch (Throwable $e) {
                        error_log('LBTT rehash failed: ' . $e->getMessage());
                    }
                }
                header('Location: index.php?action=calendar');
                exit;
            }
            login_ratelimit_register_failure();
            usleep(300000);
            $error = 'Identifiants incorrects';
        }
    }
    $title = 'Connexion — LBTimeTracker';
    include BASE_DIR . '/views/login.php';
}

function handle_signup(PDO $db): void {
    $error = null;
    $form = ['username' => '', 'slot_mode' => 'hd4'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check_form_or_die();
        // Rate-limit partagé avec login (abus signup == abus login)
        login_ratelimit_gc();
        $blocked = login_ratelimit_check();
        if ($blocked !== null) {
            $error = $blocked;
        } else {
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            $mode = (string)($_POST['slot_mode'] ?? 'hd4');
            $form['username'] = $u;
            $form['slot_mode'] = $mode;

            if ($u === '' || !preg_match('/^[A-Za-z0-9._-]{2,64}$/', $u)) {
                $error = 'Nom invalide (2–64 car., lettres, chiffres, . _ -).';
            } elseif (strlen($p) < 6) {
                $error = 'Mot de passe trop court (6 car. min.).';
            } elseif (strlen($p) > 128) {
                $error = 'Mot de passe trop long (128 car. max.).';
            } elseif ($p !== $p2) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif (!valid_slot_mode($mode)) {
                $error = 'Mode de créneaux invalide.';
            } elseif (get_user_by_username($db, $u)) {
                $error = 'Ce nom d\'utilisateur est déjà pris.';
            } else {
                // 1er user créé via signup : pas d'app admin (déjà créé au setup).
                $hash = password_hash($p, PASSWORD_BCRYPT);
                try {
                    $uid = create_user($db, $u, $hash, false, $mode);
                } catch (Throwable $e) {
                    $error = 'Création impossible (' . $e->getMessage() . ').';
                    $uid = 0;
                }
                if ($uid > 0) {
                    session_regenerate_id(true);
                    $_SESSION['uid'] = $uid;
                    $_SESSION['user'] = $u;
                    header('Location: index.php?action=calendar');
                    exit;
                }
            }
            if ($error !== null) {
                login_ratelimit_register_failure();
            }
        }
    }
    $title = 'Créer un compte — LBTimeTracker';
    include BASE_DIR . '/views/signup.php';
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
