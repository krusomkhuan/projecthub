<?php
// สำรองข้อมูลจาก Command Line / Windows Task Scheduler
//   C:\xampp\php\php.exe C:\xampp\htdocs\projecthub\backup_cli.php          สำรองเมื่อถึงรอบตามการตั้งค่า
//   C:\xampp\php\php.exe C:\xampp\htdocs\projecthub\backup_cli.php --force  สำรองทันที
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$_SERVER['SCRIPT_NAME'] = 'install.php'; // ข้ามการตรวจหน้าติดตั้งของ bootstrap ขณะรันจาก CLI
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/backup.php';

$name = in_array('--force', $argv, true) ? backup_create('auto', setting('backup_auto_files', '1') === '1') : backup_auto_if_due();
echo $name ? "backup created: $name\n" : "not due yet (see backup settings)\n";
