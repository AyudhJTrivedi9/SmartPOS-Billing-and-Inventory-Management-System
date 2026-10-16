<?php
declare(strict_types=1);

/**
 * Application configuration.
 * Change APP_ENV to 'production' when deploying anywhere public.
 */
define('APP_NAME', 'SmartPOS');
define('APP_ENV', 'development');          // 'development' or 'production'
// Leave empty to auto-detect the folder name inside htdocs (works with ANY folder name).
// Or force a value, e.g. '/myfolder' (no trailing slash).
$__baseOverride = '';
if ($__baseOverride !== '') {
    define('BASE_URL', rtrim($__baseOverride, '/'));
} else {
    $__doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $__root = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
    $__rel  = ($__doc !== '' && str_starts_with($__root, $__doc)) ? substr($__root, strlen($__doc)) : '/smartpos';
    define('BASE_URL', rtrim($__rel, '/'));
}
define('CURRENCY', '₹');
define('LOW_STOCK_THRESHOLD', 10);         // Stock <= this value is "low stock"
define('PER_PAGE', 10);                    // Rows per page in listings

define('ROOT_PATH', dirname(__DIR__));
define('LOG_DIR', ROOT_PATH . '/storage/logs');
define('LOG_FILE', LOG_DIR . '/app.log');

date_default_timezone_set('Asia/Kolkata');

if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0775, true);
}

// Never print PHP/SQL errors to the browser. They go to storage/logs/app.log.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_FILE);

// Secure session settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('smartpos_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}