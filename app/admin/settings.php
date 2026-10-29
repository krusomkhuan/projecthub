<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');
$fields = [
    'college_name'  => 'ชื่อสถานศึกษา',
    'research_head' => 'ชื่อหัวหน้างานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์ (ใช้ลงนามในรายงาน)',
    'academic_head' => 'ชื่อรองผู้อำนวยการฝ่ายวิชาการ',
    'strategy_head' => 'ชื่อรองผู้อำนวยการฝ่ายยุทธศาสตร์และแผนงาน',
    'director'      => 'ชื่อผู้อำนวยการวิทยาลัย',
    'hero_text'     => 'ข้อความต้อนรับหน้าแรก',
];
if (is_post()) {
    csrf_check();
    foreach ($fields as $k => $_) {
        q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$k, input($k)]);
    }
    log_activity('แก้ไขการตั้งค่าระบบ');
    flash('success', 'บันทึกการตั้งค่าแล้ว');
    redirect('app/admin/settings.php');
}
$pageTitle = 'ตั้งค่าระบบ';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>ตั้งค่าระบบ</h1></div>
<form method="post" class="card card-body" style="max-width:760px"><?= csrf_field() ?>
  <?php foreach ($fields as $k => $label): ?>
    <div class="mb-3"><label class="form-label"><?= e($label) ?></label>
      <?php if ($k === 'hero_text'): ?><textarea name="<?= $k ?>" class="form-control" rows="3"><?= e(setting($k)) ?></textarea>
      <?php else: ?><input name="<?= $k ?>" class="form-control" value="<?= e(setting($k, $k === 'college_name' ? college_name() : '')) ?>"><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div><button class="btn btn-primary">บันทึก</button></div>
</form>
<div class="card mt-3" style="max-width:760px"><div class="card-header">ข้อมูลระบบ</div>
  <table class="table table-sm mb-0"><tbody>
    <tr><th class="w-50 ps-3">ระบบ</th><td>ProjectHub เวอร์ชัน <?= APP_VERSION ?> (<?= thai_date(APP_RELEASE_DATE) ?>)</td></tr>
    <tr><th class="ps-3">ผู้พัฒนาระบบ</th><td><?= e(substr(DEVELOPER_CREDIT, strlen('ผู้พัฒนาระบบ : '))) ?></td></tr>
    <tr><th class="ps-3">PHP</th><td><?= PHP_VERSION ?></td></tr>
    <tr><th class="ps-3">ฐานข้อมูล</th><td><?= e((string)q_val('SELECT VERSION()')) ?></td></tr>
  </tbody></table>
</div>
<?php require APP_ROOT . '/inc/footer.php';
