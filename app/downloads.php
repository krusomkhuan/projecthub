<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login();

// ครู/บุคลากรเห็นแบบฟอร์มของครูด้วย นักเรียนเห็นเฉพาะของนักเรียน
$aud = $me['role'] === 'student' ? ['all', 'student'] : ($me['role'] === 'teacher' ? ['all', 'teacher'] : ['all', 'teacher', 'student']);
$in = implode(',', array_fill(0, count($aud), '?'));

if ($get = int_param('get')) {
    $f = q_one("SELECT * FROM form_files WHERE id = ? AND audience IN ($in)", [$get, ...$aud]);
    if (!$f) { http_response_code(404); exit('ไม่พบไฟล์'); }
    send_form_file($f);
}

$kw = input('q');
$params = $aud;
$where = "audience IN ($in)";
if ($kw !== '') { $where .= ' AND (title LIKE ? OR description LIKE ?)'; array_push($params, "%$kw%", "%$kw%"); }
$groups = [];
foreach (q_all("SELECT * FROM form_files WHERE $where ORDER BY sort_order, title", $params) as $f) {
    $groups[$f['category']][] = $f;
}
uksort($groups, fn($a, $b) => (array_flip(FORM_CATEGORIES)[$a] ?? 99) <=> (array_flip(FORM_CATEGORIES)[$b] ?? 99));
$pageTitle = 'ดาวน์โหลดแบบฟอร์ม';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1><i class="bi bi-cloud-download"></i> ดาวน์โหลดแบบฟอร์ม</h1>
  <form class="d-flex gap-2"><input name="q" class="form-control" placeholder="ค้นหาแบบฟอร์ม" value="<?= e($kw) ?>"><button class="btn btn-primary"><i class="bi bi-search"></i></button></form>
</div>
<?php foreach ($groups as $cat => $items): ?>
  <div class="card mb-3"><div class="card-header"><?= e($cat) ?></div>
    <ul class="list-group list-group-flush">
      <?php foreach ($items as $f): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
          <div class="d-flex gap-3 align-items-center"><i class="bi bi-<?= file_icon($f['file_name']) ?> fs-3"></i>
            <div><div class="fw-semibold"><?= e($f['title']) ?></div>
              <?php if ($f['description']): ?><div class="small text-muted"><?= e($f['description']) ?></div><?php endif; ?>
              <div class="small text-muted"><?= strtoupper(e(pathinfo($f['file_name'], PATHINFO_EXTENSION))) ?> · <?= number_format($f['file_size'] / 1024, 0) ?> KB · ปรับปรุง <?= thai_date($f['updated_at']) ?></div></div></div>
          <a href="?get=<?= $f['id'] ?>" class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-download"></i> ดาวน์โหลด</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
<?php if (!$groups): ?><div class="card card-body text-muted">ยังไม่มีแบบฟอร์มให้ดาวน์โหลด<?= is_role('admin') ? ' · <a href="' . url('app/admin/downloads.php') . '">เพิ่มแบบฟอร์ม</a>' : '' ?></div><?php endif; ?>
<?php require APP_ROOT . '/inc/footer.php';
