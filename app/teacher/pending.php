<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
$me = require_login('teacher');

$rows = q_all('SELECT r.*, p.title, rp.title AS period_title, rp.due_date, c.code
    FROM progress_reports r JOIN projects p ON p.id = r.project_id JOIN sections s ON s.id = p.section_id
    JOIN classrooms c ON c.id = s.classroom_id JOIN report_periods rp ON rp.id = r.period_id
    WHERE r.status = "submitted" AND (s.teacher_id = ? OR p.advisor_id = ?) ORDER BY r.submitted_at', [$me['id'], $me['id']]);
$proposals = q_all('SELECT p.id, p.title, p.created_at, c.code FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id
    WHERE p.status = "proposed" AND (s.teacher_id = ? OR p.advisor_id = ?) ORDER BY p.created_at', [$me['id'], $me['id']]);
$pageTitle = 'รายงานรอตรวจ';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>งานรอตรวจ</h1></div>
<div class="card mb-3">
  <div class="card-header">หัวข้อโครงงานรอพิจารณา (<?= count($proposals) ?>)</div>
  <div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>ชื่อโครงงาน</th><th>กลุ่มเรียน</th><th>เสนอเมื่อ</th></tr></thead><tbody>
    <?php foreach ($proposals as $p): ?><tr><td><a href="<?= url('app/project.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a></td><td><?= e($p['code']) ?></td><td><?= thai_date($p['created_at'], true) ?></td></tr><?php endforeach; ?>
    <?php if (!$proposals): ?><tr><td colspan="3" class="text-muted">ไม่มีรายการ</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<div class="card">
  <div class="card-header">รายงานความก้าวหน้ารอตรวจ (<?= count($rows) ?>)</div>
  <div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>โครงงาน</th><th>กลุ่มเรียน</th><th>รอบรายงาน</th><th>ส่งเมื่อ</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): $late = $r['due_date'] && substr($r['submitted_at'], 0, 10) > $r['due_date']; ?>
      <tr><td><?= e($r['title']) ?></td><td><?= e($r['code']) ?></td><td><?= e($r['period_title']) ?></td>
        <td><?= thai_date($r['submitted_at'], true) ?><?= $late ? ' <span class="badge text-bg-danger">ส่งช้า</span>' : '' ?></td>
        <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= url('app/project.php?id=' . $r['project_id']) ?>">ตรวจ</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-muted">ไม่มีรายงานรอตรวจ</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
