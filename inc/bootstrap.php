<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

define('APP_ROOT', dirname(__DIR__));
// config.php = ค่าเริ่มต้น · config.local.php (ไม่ขึ้น git) = ค่าจริงของแต่ละเครื่อง เช่น รหัสผ่านฐานข้อมูล
// PROJECTHUB_CONFIG = ใช้ไฟล์ตั้งค่าอื่นแทนทั้งหมด (สำหรับทดสอบ)
$GLOBALS['config'] = require (getenv('PROJECTHUB_CONFIG') ?: APP_ROOT . '/config.php');
if (!getenv('PROJECTHUB_CONFIG') && is_file(APP_ROOT . '/config.local.php')) {
    $GLOBALS['config'] = array_merge($GLOBALS['config'], require APP_ROOT . '/config.local.php');
}
define('BASE_URL', rtrim($GLOBALS['config']['base_url'], '/'));
define('UPLOAD_DIR', APP_ROOT . '/uploads');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PROJECTHUBSESS');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => BASE_URL . '/']);
    session_start();
}

require __DIR__ . '/compat.php';
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
        try {
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
        } catch (PDOException $ex) {
            if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'install.php' || PHP_SAPI === 'cli') {
                throw $ex;
            }
            // แสดงข้อความที่บอกวิธีแก้แทนหน้า 500 ว่าง (ไม่แสดงรหัสผ่าน)
            error_log('ProjectHub DB connection failed: ' . $ex->getMessage());
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<!doctype html><meta charset="utf-8"><div style="font-family:sans-serif;max-width:640px;margin:3rem auto">'
                . '<h2>เชื่อมต่อฐานข้อมูลไม่ได้</h2><p>กรุณาตรวจค่า <code>db_host</code>, <code>db_name</code>, <code>db_user</code>, <code>db_pass</code> ในไฟล์ <code>config.php</code> ให้ตรงกับฐานข้อมูลบนเซิร์ฟเวอร์</p>'
                . '<p style="color:#666">รหัสข้อผิดพลาด: ' . htmlspecialchars((string)$ex->getCode()) . '</p></div>';
            exit;
        }
    }
    return $pdo;
}
