<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require_login();

$r = q_one('SELECT * FROM progress_reports WHERE id = ?', [int_param('id')]);
$p = $r ? find_project((int)$r['project_id']) : null;
if (!$p || project_access($p) === 'none' || !$r['file_path'] || !is_file(UPLOAD_DIR . '/' . $r['file_path'])) {
    http_response_code(404);
    exit('ไม่พบไฟล์');
}
$path = UPLOAD_DIR . '/' . $r['file_path'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($r['file_name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
