<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';

$lock = __DIR__ . '/install.lock';
if (is_file($lock)) {
    exit('ระบบติดตั้งแล้ว หากต้องการติดตั้งใหม่ให้ลบไฟล์ install/install.lock');
}

$error = '';
if (is_post()) {
    csrf_check();
    $username = input('username');
    $password = (string)($_POST['password'] ?? '');
    $fullName = input('full_name');
    if ($username === '' || $fullName === '' || mb_strlen($password) < 8) {
        $error = 'กรุณากรอกข้อมูลให้ครบ และรหัสผ่านอย่างน้อย 8 ตัวอักษร';
    } else {
        try {
            $c = $GLOBALS['config'];
            $root = new PDO("mysql:host={$c['db_host']};charset=utf8mb4", $c['db_user'], $c['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $root->exec("CREATE DATABASE IF NOT EXISTS `{$c['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
                db()->exec($stmt);
            }
            q('INSERT INTO users (username, password_hash, full_name, role, position, must_change_password)
               VALUES (?, ?, ?, "admin", "ผู้ดูแลระบบ", 0)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $fullName]);
            q('INSERT INTO settings (name, value) VALUES ("college_name", ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [input('college_name', 'วิทยาลัยการอาชีพโพนทอง')]);
            $y = (int)date('Y') + 543;
            $term = (int)date('n') >= 5 && (int)date('n') <= 10 ? 1 : 2;
            q('INSERT IGNORE INTO semesters (term, academic_year, is_active) VALUES (?, ?, 1)', [$term, $term === 2 && (int)date('n') < 5 ? $y - 1 : $y]);
            $semId = (int)q_val('SELECT id FROM semesters WHERE is_active = 1 LIMIT 1');
            $periods = ['รายงานความก้าวหน้า ครั้งที่ 1 (บทที่ 1–3 / เค้าโครง)', 'รายงานความก้าวหน้า ครั้งที่ 2 (การสร้าง / พัฒนาชิ้นงาน)',
                        'รายงานความก้าวหน้า ครั้งที่ 3 (ทดลอง / เก็บข้อมูล)', 'รายงานฉบับสมบูรณ์และการนำเสนอ'];
            foreach ($periods as $i => $t) {
                q('INSERT INTO report_periods (semester_id, seq, title) VALUES (?, ?, ?)', [$semId, $i + 1, $t]);
            }
            file_put_contents($lock, date('c'));
            flash('success', 'ติดตั้งระบบเรียบร้อย กรุณาเข้าสู่ระบบ');
            redirect('login.php');
        } catch (Throwable $ex) {
            $error = 'ติดตั้งไม่สำเร็จ: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ติดตั้ง ProjectHub</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= url('assets/app.css') ?>" rel="stylesheet"></head>
<body><div class="login-wrap"><div class="card p-4" style="max-width:480px;width:100%">
<h1 class="h4 fw-bold mb-1">ติดตั้งระบบ ProjectHub</h1>
<p class="text-muted small">สร้างฐานข้อมูลและบัญชีผู้ดูแลระบบ</p>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<div class="mb-2"><label class="form-label">ชื่อสถานศึกษา</label><input class="form-control" name="college_name" value="วิทยาลัยการอาชีพโพนทอง"></div>
<div class="mb-2"><label class="form-label">ชื่อ-สกุล ผู้ดูแลระบบ</label><input class="form-control" name="full_name" required></div>
<div class="mb-2"><label class="form-label">ชื่อผู้ใช้</label><input class="form-control" name="username" required></div>
<div class="mb-3"><label class="form-label">รหัสผ่าน (อย่างน้อย 8 ตัว)</label><input type="password" class="form-control" name="password" required minlength="8"></div>
<button class="btn btn-primary w-100">ติดตั้ง</button>
</form></div></div></body></html>
