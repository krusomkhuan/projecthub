<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$news = q_all('SELECT * FROM announcements WHERE is_public = 1 ORDER BY created_at DESC LIMIT 50');
$pageTitle = 'ประกาศ';
require __DIR__ . '/inc/public_header.php';
?>
<div class="container py-4" style="max-width:860px">
  <h1 class="h3 fw-bold mb-3">ประกาศ / ข่าวสาร</h1>
  <?php foreach ($news as $n): ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5 fw-bold mb-1"><?= e($n['title']) ?></h2>
      <div class="small text-muted mb-2"><?= thai_date($n['created_at'], true) ?></div>
      <div class="prewrap"><?= e($n['body']) ?></div>
    </div></div>
  <?php endforeach; ?>
  <?php if (!$news): ?><div class="text-muted">ยังไม่มีประกาศ</div><?php endif; ?>
</div>
<?php require __DIR__ . '/inc/public_footer.php';
