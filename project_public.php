<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$p = find_project(int_param('id'));
if (!$p || !$p['is_public']) { http_response_code(404); exit('ไม่พบผลงาน'); }
$sem = q_one('SELECT * FROM semesters WHERE id = ?', [$p['semester_id']]);
$members = project_members((int)$p['id']);
$pageTitle = $p['title'];
require __DIR__ . '/inc/public_header.php';
?>
<div class="container py-4" style="max-width:900px">
  <a href="<?= url('showcase.php') ?>" class="small">← ผลงานโครงงานทั้งหมด</a>
  <div class="card mt-2"><div class="card-body p-4">
    <span class="badge text-bg-warning mb-2"><?= e($p['category'] ?: 'โครงงาน') ?></span>
    <h1 class="h3 fw-bold"><?= e($p['title']) ?></h1>
    <?php if ($p['title_en']): ?><div class="text-muted fst-italic mb-3"><?= e($p['title_en']) ?></div><?php endif; ?>
    <div class="row small mb-3">
      <div class="col-md-6"><strong>ระดับ / แผนกวิชา:</strong> <?= e($p['level'] . ' ' . $p['department']) ?></div>
      <div class="col-md-6"><strong>ภาคเรียน:</strong> <?= e(semester_label($sem)) ?></div>
      <div class="col-md-6"><strong>ครูที่ปรึกษา:</strong> <?= e(person($p, 'a_') ?: person($p, 't_')) ?></div>
    </div>
    <h2 class="h6 fw-bold">ผู้จัดทำ</h2>
    <ul><?php foreach ($members as $m): ?><li><?= e(person($m)) ?></li><?php endforeach; ?></ul>
    <?php foreach (['objective' => 'วัตถุประสงค์', 'scope' => 'ขอบเขตของโครงงาน', 'expected' => 'ผลที่ได้รับ'] as $k => $lbl): if (!$p[$k]) continue; ?>
      <h2 class="h6 fw-bold mt-3"><?= $lbl ?></h2><div class="prewrap"><?= e($p[$k]) ?></div>
    <?php endforeach; ?>
  </div></div>
</div>
<?php require __DIR__ . '/inc/public_footer.php';
