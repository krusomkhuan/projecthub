<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');
$total = (int)q_val('SELECT COUNT(*) FROM activity_logs');
[$page, $pages, $offset, $limit] = paginate($total, 50);
$logs = q_all("SELECT l.*, u.prefix, u.full_name, u.role FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT $limit OFFSET $offset");
$pageTitle = 'บันทึกการใช้งาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>บันทึกการใช้งาน</h1></div>
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>บทบาท</th><th>กิจกรรม</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr><td class="text-nowrap"><?= thai_date($l['created_at'], true) ?></td><td><?= e($l['full_name'] ? person($l) : 'ระบบ') ?></td><td class="small"><?= e(ROLE_LABELS[$l['role']] ?? '') ?></td><td><?= e($l['action']) ?></td></tr><?php endforeach; ?>
  <?php if (!$logs): ?><tr><td colspan="4" class="text-muted">ไม่มีข้อมูล</td></tr><?php endif; ?>
</tbody></table></div></div>
<div class="mt-3"><?= pagination_links($page, $pages) ?></div>
<?php require APP_ROOT . '/inc/footer.php';
