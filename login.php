<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

if (current_user()) {
    redirect('app/index.php');
}
$error = '';
if (is_post()) {
    csrf_check();
    if (attempt_login(input('username'), (string)($_POST['password'] ?? ''))) {
        $next = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        redirect($next !== '' && str_starts_with($next, BASE_URL . '/') ? $next : 'app/index.php');
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>เข้าสู่ระบบ · ProjectHub</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/app.css') ?>" rel="stylesheet"></head>
<body><div class="login-wrap"><div class="card p-4" style="max-width:420px;width:100%">
<div class="text-center mb-3">
  <div class="fs-3 fw-bold" style="color:var(--ph-navy)"><i class="bi bi-lightbulb-fill text-warning"></i> ProjectHub</div>
  <div class="text-muted"><?= e(college_name()) ?></div>
</div>
<?php foreach (take_flashes() as [$t, $m]): ?><div class="alert alert-<?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post"><?= csrf_field() ?>
<div class="mb-2"><label class="form-label">ชื่อผู้ใช้ / รหัสนักศึกษา</label><input class="form-control" name="username" required autofocus></div>
<div class="mb-3"><label class="form-label">รหัสผ่าน</label><input type="password" class="form-control" name="password" required></div>
<button class="btn btn-primary w-100">เข้าสู่ระบบ</button>
</form>
<div class="text-center mt-3"><a href="<?= url('register.php') ?>">สมัครสมาชิกนักเรียน นักศึกษา</a></div>
<div class="text-center mt-3"><a href="<?= url('index.php') ?>" class="small">กลับหน้าแรก</a></div>
<div class="text-center text-muted mt-3 pt-3 border-top" style="font-size:.8rem">ProjectHub เวอร์ชัน <?= APP_VERSION ?><br><?= e(DEVELOPER_CREDIT) ?></div>
</div></div></body></html>
