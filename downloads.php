<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

if ($get = int_param('get')) {
    $f = q_one('SELECT * FROM form_files WHERE id = ? AND is_public = 1', [$get]);
    if (!$f) { http_response_code(404); exit('ไม่พบไฟล์'); }
    send_form_file($f);
}
$groups = [];
foreach (q_all('SELECT * FROM form_files WHERE is_public = 1 ORDER BY sort_order, title') as $f) {
    $groups[$f['category']][] = $f;
}
uksort($groups, fn($a, $b) => (array_flip(FORM_CATEGORIES)[$a] ?? 99) <=> (array_flip(FORM_CATEGORIES)[$b] ?? 99));
$pageTitle = 'ดาวน์โหลดแบบฟอร์ม';
require __DIR__ . '/inc/public_header.php';
?>
<div class="container py-4" style="max-width:960px">
  <h1 class="h3 fw-bold mb-3">ดาวน์โหลดแบบฟอร์ม</h1>
  <?php foreach ($groups as $cat => $items): ?>
    <h2 class="h5 fw-bold mt-4"><?= e($cat) ?></h2>
    <div class="card"><ul class="list-group list-group-flush">
      <?php foreach ($items as $f): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
          <div class="d-flex gap-3 align-items-center"><i class="bi bi-<?= file_icon($f['file_name']) ?> fs-3"></i>
            <div><div class="fw-semibold"><?= e($f['title']) ?></div><?php if ($f['description']): ?><div class="small text-muted"><?= e($f['description']) ?></div><?php endif; ?></div></div>
          <a href="?get=<?= $f['id'] ?>" class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-download"></i> ดาวน์โหลด</a>
        </li>
      <?php endforeach; ?>
    </ul></div>
  <?php endforeach; ?>
  <?php if (!$groups): ?><div class="text-muted">ยังไม่มีแบบฟอร์มที่เผยแพร่ · แบบฟอร์มบางรายการดาวน์โหลดได้หลัง<a href="<?= url('login.php') ?>">เข้าสู่ระบบ</a></div><?php endif; ?>
</div>
<?php require __DIR__ . '/inc/public_footer.php';
