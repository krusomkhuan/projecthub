<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

define('APP_ROOT', dirname(__DIR__));
$GLOBALS['config'] = require APP_ROOT . '/config.php';
define('BASE_URL', rtrim($GLOBALS['config']['base_url'], '/'));
define('UPLOAD_DIR', APP_ROOT . '/uploads');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PROJECTHUBSESS');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => BASE_URL . '/']);
    session_start();
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

if (!is_file(APP_ROOT . '/install/install.lock') && basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
    header('Location: ' . BASE_URL . '/install/install.php');
    exit;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config'];
        $pdo = new PDO(
            "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}
