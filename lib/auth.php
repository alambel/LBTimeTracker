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
            header('Location: ' . url('login'));
        }
        exit;
    }
    // Pages authentifiées : pas de cache (proxies / back-button)
    send_private_cache_headers();
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
            // Le champ du form s'appelle "email" mais on accepte aussi un
            // username pour les comptes legacy (migrés depuis config.php
            // avant le passage à l'email).
            $identifier = trim((string)($_POST['email'] ?? $_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $row = null;
            if ($identifier !== '') {
                $normalized = normalize_email($identifier);
                if (valid_email($normalized)) {
                    $row = get_user_by_email($db, $normalized);
                } else {
                    $row = get_user_by_username($db, $identifier);
                }
            }

            // Hash dummy stable (bcrypt cost 12 d'un plaintext aléatoire) : on
            // fait toujours tourner password_verify pour éviter l'énumération
            // par timing (user absent → pas de bcrypt = réponse rapide).
            // Ce hash ne correspond à aucun mot de passe utilisable.
            $DUMMY_HASH = '$2y$12$H/HZyeLgUZQT0QG.QgNtwe3NGnF6NhG4zgbreZncdyRxRJia2OnX.';
            $hashToCheck = ($row && empty($row['archived']))
                ? (string)$row['password_hash']
                : $DUMMY_HASH;
            $verified = password_verify($p, $hashToCheck);

            if ($row && empty($row['archived']) && $verified) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$row['id'];
                // Stocke l'email si dispo, sinon fallback username (legacy)
                $_SESSION['user'] = (string)($row['email'] ?: $row['username']);
                login_ratelimit_reset();
                if (password_needs_rehash((string)$row['password_hash'], PASSWORD_BCRYPT)) {
                    try {
                        update_user_password($db, (int)$row['id'], password_hash($p, PASSWORD_BCRYPT));
                    } catch (Throwable $e) {
                        error_log('LBTT rehash failed: ' . $e->getMessage());
                    }
                }
                header('Location: ' . url('calendar'));
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
    $form = ['email' => '', 'first_name' => '', 'last_name' => ''];

    // Pré-remplissage via invitation (?invite=TOKEN)
    $inviteToken = (string)($_GET['invite'] ?? $_POST['invite_token'] ?? '');
    $invitation = null;
    if ($inviteToken !== '') {
        $invitation = get_invitation_by_token($db, $inviteToken);
        if ($invitation) {
            $form['email'] = (string)$invitation['email'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check_form_or_die();
        login_ratelimit_gc();
        $blocked = login_ratelimit_check();

        // Honeypot : champ caché que seuls les bots remplissent.
        // Si rempli → réponse générique, pas d'inscription.
        $honeypot = (string)($_POST['website'] ?? '');
        if ($honeypot !== '') {
            login_ratelimit_register_failure();
            usleep(500000);
            $error = 'Inscription impossible.';
        } elseif ($blocked !== null) {
            $error = $blocked;
        } else {
            $email = normalize_email((string)($_POST['email'] ?? ''));
            $firstName = sanitize_name((string)($_POST['first_name'] ?? ''), 64);
            $lastName = sanitize_name((string)($_POST['last_name'] ?? ''), 64);
            $p = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            // Granularité fixée à l'inscription : nouveaux comptes en hr10.
            $mode = default_slot_mode();
            $form['email'] = $email;
            $form['first_name'] = $firstName;
            $form['last_name'] = $lastName;

            // Si invitation : l'email est figé (celui de l'invitation)
            if ($invitation) {
                $email = normalize_email((string)$invitation['email']);
                $form['email'] = $email;
            }

            if (!valid_email($email)) {
                $error = 'Adresse email invalide.';
            } elseif (($pwErr = validate_password_policy($p)) !== null) {
                $error = $pwErr;
            } elseif ($p !== $p2) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif (get_user_by_email($db, $email)) {
                $error = 'Cette adresse email a déjà un compte.';
            } else {
                $hash = password_hash($p, PASSWORD_BCRYPT);
                // Username auto-généré depuis l'email (usage interne, plus affiché)
                $u = username_from_email($db, $email);
                try {
                    $uid = create_user($db, $u, $hash, false, $mode, $email);
                    if ($firstName !== '' || $lastName !== '') {
                        update_user_name($db, $uid, $firstName === '' ? null : $firstName, $lastName === '' ? null : $lastName);
                    }
                } catch (Throwable $e) {
                    $error = 'Création impossible (' . $e->getMessage() . ').';
                    $uid = 0;
                }
                if ($uid > 0) {
                    // Ne consomme QUE l'invitation du token fourni (preuve de réception
                    // de l'email). Les autres invitations en attente pour cet email ne
                    // sont PAS acceptées automatiquement : l'user doit cliquer leur
                    // propre lien (sinon, un attaquant qui signup avec l'email d'une
                    // victime prend tous ses projets — cf. audit sécu #3).
                    if ($invitation && (int)$invitation['id'] > 0) {
                        try { consume_invitation_for_user($db, (int)$invitation['id'], $uid); }
                        catch (Throwable $e) { error_log('LBTT consume invitation failed: ' . $e->getMessage()); }
                    }

                    // Envoi d'un mail de vérification email (non bloquant).
                    // Si l'user vient d'une invitation, son email est déjà prouvé
                    // (il a reçu le mail d'invitation) → marque-le vérifié direct.
                    if ($invitation && (int)$invitation['id'] > 0) {
                        $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')
                           ->execute([$uid]);
                    } else {
                        try {
                            $tok = set_email_verify_token($db, $uid);
                            $verifyUrl = app_url('verify_email', ['token' => $tok]);
                            send_email_verification($email, $verifyUrl, $firstName !== '' ? $firstName : $u);
                        } catch (Throwable $e) {
                            error_log('LBTT send_email_verification failed: ' . $e->getMessage());
                        }
                    }

                    session_regenerate_id(true);
                    $_SESSION['uid'] = $uid;
                    $_SESSION['user'] = $email;
                    header('Location: ' . url('calendar'));
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

function handle_verify_email(PDO $db): void {
    $token = (string)($_GET['token'] ?? '');
    $uid = verify_email_by_token($db, $token);
    $title = 'Vérification email — LBTimeTracker';
    $ok = $uid !== null;
    ob_start(); ?>
    <div class="lbtt-page-head">
        <div>
            <div class="lbtt-label">Vérification email</div>
            <h1 class="lbtt-page-title"><?= $ok ? 'email confirmé.' : 'lien invalide.' ?></h1>
        </div>
    </div>
    <div class="lbtt-cal-tip">
        <span class="lbtt-chip <?= $ok ? 'lbtt-chip-accent' : '' ?>"><?= $ok ? 'OK' : 'Erreur' ?></span>
        <span class="lbtt-cal-tip-text">
            <?= $ok
                ? 'Ton adresse email est maintenant vérifiée.'
                : 'Le lien est invalide, expiré, ou déjà utilisé. Depuis ton profil tu peux en redemander un.' ?>
        </span>
    </div>
    <p style="margin-top: 14px;">
        <a class="lbtt-btn lbtt-btn-primary" href="<?= e(url(current_user_id() ? 'calendar' : 'login')) ?>">
            <?= current_user_id() ? 'Retour au calendrier' : 'Se connecter' ?> →
        </a>
    </p>
    <?php
    $content = ob_get_clean();
    render_layout($title, current_user_id() ? 'profile' : '', $content, $db);
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
