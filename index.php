<?php
declare(strict_types=1);

define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('DB_PATH', DATA_DIR . '/timetrack.sqlite');
define('CONFIG_PATH', BASE_DIR . '/config.php');

require BASE_DIR . '/lib/helpers.php';
require BASE_DIR . '/lib/db.php';
require BASE_DIR . '/lib/auth.php';
require BASE_DIR . '/lib/api.php';
require BASE_DIR . '/lib/setup.php';
require BASE_DIR . '/lib/render.php';

if (!file_exists(CONFIG_PATH)) {
    handle_setup();
    exit;
}

$config = require CONFIG_PATH;
date_default_timezone_set($config['timezone'] ?? 'Europe/Zurich');

session_name($config['session_name'] ?? 'lbtt');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$db = db_init(DB_PATH);
$action = $_GET['action'] ?? 'calendar';

if (str_starts_with($action, 'api_')) {
    require_auth();
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
