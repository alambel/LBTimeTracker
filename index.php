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
        handle_login($config);
        break;
    case 'logout':
        handle_logout();
        header('Location: index.php?action=login');
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
    default:
        http_response_code(404);
        echo '404 not found';
}
