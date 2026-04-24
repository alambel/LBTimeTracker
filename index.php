<?php
declare(strict_types=1);

define('BASE_DIR', __DIR__);
define('CONFIG_PATH', BASE_DIR . '/config.php');

require BASE_DIR . '/lib/helpers.php';
require BASE_DIR . '/lib/security.php';
require BASE_DIR . '/lib/version.php';
require BASE_DIR . '/lib/db.php';
require BASE_DIR . '/lib/auth.php';
require BASE_DIR . '/lib/api.php';
require BASE_DIR . '/lib/setup.php';
require BASE_DIR . '/lib/render.php';
require BASE_DIR . '/lib/mail.php';
require BASE_DIR . '/lib/image.php';

send_security_headers();

if (!file_exists(CONFIG_PATH)) {
    handle_setup();
    exit;
}

$config = require CONFIG_PATH;
if (!is_array($config) || empty($config['db']) || !is_array($config['db'])) {
    http_response_code(500);
    echo 'Configuration invalide. Supprimer config.php pour relancer le setup.';
    exit;
}

date_default_timezone_set($config['timezone'] ?? 'Europe/Zurich');

session_name($config['session_name'] ?? 'lbtt');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

try {
    $db = db_init($config['db']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('LBTT db_init failed: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    echo "Service momentanément indisponible.\n";
    echo "Contactez l'administrateur.";
    exit;
}

// Fallback routing : si pas d'?action= (ex: serveur PHP built-in qui n'applique
// pas .htaccess), on parse REQUEST_URI pour mapper les URLs SEO-friendly.
if (!isset($_GET['action'])) {
    $uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    if ($scriptDir !== '' && str_starts_with($uri, $scriptDir . '/')) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = rtrim($uri, '/') ?: '/';
    if (preg_match('#^/signup/invite/([A-Za-z0-9_\-]+)$#', $uri, $m)) {
        $_GET['action'] = 'signup'; $_GET['invite'] = $m[1];
    } elseif (preg_match('#^/verify-email/([A-Za-z0-9_\-]+)$#', $uri, $m)) {
        $_GET['action'] = 'verify_email'; $_GET['token'] = $m[1];
    } elseif (preg_match('#^/team/(\d+)$#', $uri, $m)) {
        $_GET['action'] = 'team'; $_GET['id'] = $m[1];
    } elseif (preg_match('#^/avatar/(\d+)\.jpg$#', $uri, $m)) {
        $_GET['action'] = 'avatar'; $_GET['id'] = $m[1];
    } elseif ($uri === '/api/entries') {
        $_GET['action'] = 'api_entries';
    } elseif ($uri === '/api/save-entry') {
        $_GET['action'] = 'api_save_entry';
    } elseif (preg_match('#^/(login|signup|logout|calendar|summary|projects|profile|users)$#', $uri, $m)) {
        $_GET['action'] = $m[1];
    }
}

$action = $_GET['action'] ?? 'calendar';

if (str_starts_with($action, 'api_')) {
    require_auth();
    csrf_check_api_or_die();
    header('Content-Type: application/json; charset=utf-8');
    api_dispatch($action, $db);
    exit;
}

switch ($action) {
    case 'login':
        handle_login($db);
        break;
    case 'signup':
        handle_signup($db);
        break;
    case 'verify_email':
        handle_verify_email($db);
        break;
    case 'logout':
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            csrf_check_form_or_die();
            handle_logout();
            header('Location: ' . url('login'));
            exit;
        }
        require_auth();
        render_logout_confirm($db);
        exit;
    case 'calendar':
        require_auth();
        render_calendar($db);
        break;
    case 'summary':
        require_auth();
        render_summary($db);
        break;
    case 'projects':
        require_auth();
        render_projects($db);
        break;
    case 'team':
        require_auth();
        render_project_team($db);
        break;
    case 'profile':
        require_auth();
        render_profile($db);
        break;
    case 'users':
        require_auth();
        render_users_admin($db);
        break;
    case 'avatar':
        require_auth();
        $targetId = (int)($_GET['id'] ?? 0);
        $target = $targetId > 0 ? get_user($db, $targetId) : null;
        if (!$target || empty($target['avatar_path']) || !stream_avatar((string)$target['avatar_path'])) {
            http_response_code(404);
            exit;
        }
        exit;
    default:
        http_response_code(404);
        echo '404 not found';
}
