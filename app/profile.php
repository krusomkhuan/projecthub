<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login();
$pageTitle = 'ข้อมูลส่วนตัว';

if (is_post()) {
    csrf_check();
    if (input('action') === 'profile') {
        q('UPDATE users SET phone = ?, email = ? WHERE id = ?', [input('phone'), input('email'), $me['id']]);
        flash('success', 'บันทึกข้อมูลแล้ว');
    } else {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify($cur, $me['password_hash'])) {
            flash('danger', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        } elseif (mb_strlen($new) < 6 || $new !== ($_POST['confirm'] ?? '')) {
            flash('danger', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร และยืนยันให้ตรงกัน');
        } elseif ($new === $cur) {
            flash('danger', 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม');
        } else {
            q('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            log_activity('เปลี่ยนรหัสผ่าน');
            flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อย');
            redirect('app/index.php');
        }
    }
    redirect('app/profile.php');
}
$classroom = $me['classroom_id'] ? q_one('SELECT * FROM classrooms WHERE id = ?', [$me['classroom_id']]) : null;
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>ข้อมูลส่วนตัว</h1></div>
<div class="row g-3">
  <div class="col-lg-6"><div class="card"><div class="card-body">
    <dl class="row mb-3">
      <dt class="col-4">ชื่อ-สกุล</dt><dd class="col-8"><?= e(person($me)) ?></dd>
      <dt class="col-4">ชื่อผู้ใช้</dt><dd class="col-8"><?= e($me['username']) ?></dd>
      <dt class="col-4">บทบาท</dt><dd class="col-8"><?= e(ROLE_LABELS[$me['role']]) ?></dd>
      <?php if ($me['position']): ?><dt class="col-4">ตำแหน่ง</dt><dd class="col-8"><?= e($me['position']) ?></dd><?php endif; ?>
      <?php if ($me['department']): ?><dt class="col-4">แผนก/ฝ่าย</dt><dd class="col-8"><?= e($me['department']) ?></dd><?php endif; ?>
      <?php if ($classroom): ?><dt class="col-4">กลุ่มเรียน</dt><dd class="col-8"><?= e($classroom['code'] . ' ' . $classroom['name']) ?></dd><?php endif; ?>
    </dl>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="mb-2"><label class="form-label">โทรศัพท์</label><input class="form-control" name="phone" value="<?= e($me['phone']) ?>"></div>
      <div class="mb-3"><label class="form-label">อีเมล</label><input type="email" class="form-control" name="email" value="<?= e($me['email']) ?>"></div>
      <button class="btn btn-outline-primary">บันทึก</button>
    </form>
  </div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header">เปลี่ยนรหัสผ่าน</div><div class="card-body">
    <?php if ($me['must_change_password']): ?><div class="alert alert-warning">บัญชีนี้ใช้รหัสผ่านเริ่มต้น กรุณาตั้งรหัสผ่านใหม่</div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="mb-2"><label class="form-label">รหัสผ่านปัจจุบัน</label><input type="password" class="form-control" name="current" required></div>
      <div class="mb-2"><label class="form-label">รหัสผ่านใหม่</label><input type="password" class="form-control" name="new" minlength="6" required></div>
      <div class="mb-3"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input type="password" class="form-control" name="confirm" minlength="6" required></div>
      <button class="btn btn-primary">เปลี่ยนรหัสผ่าน</button>
    </form>
  </div></div></div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
