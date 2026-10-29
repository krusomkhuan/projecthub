<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login('admin', 'research', 'academic', 'strategy');

$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));
$filter = input('filter', 'mine');
$dept = input('department');

$params = [$semId];
$where = 's.semester_id = ? AND p.status IN ("approved","completed")';
if ($dept !== '') { $where .= ' AND c.department = ?'; $params[] = $dept; }
if ($filter === 'mine') { $where .= ' AND NOT EXISTS (SELECT 1 FROM oversight_reviews o WHERE o.project_id = p.id AND o.unit = ?)'; $params[] = $me['role']; }
if ($filter === 'improve') { $where .= ' AND EXISTS (SELECT 1 FROM oversight_reviews o WHERE o.project_id = p.id AND o.result <> "pass")'; }

$projects = q_all("SELECT p.id, p.title, p.status, p.progress, c.code, c.department, t.prefix, t.full_name,
        (SELECT COUNT(*) FROM progress_reports r WHERE r.project_id = p.id) AS reports
    FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id
    WHERE $where ORDER BY c.department, c.code, p.id", $params);
$periodCount = (int)q_val('SELECT COUNT(*) FROM report_periods WHERE semester_id = ?', [$semId]);
$latest = [];
foreach (q_all('SELECT o.project_id, o.unit, o.result FROM oversight_reviews o JOIN projects p ON p.id = o.project_id JOIN sections s ON s.id = p.section_id
                WHERE s.semester_id = ? ORDER BY o.created_at', [$semId]) as $o) {
    $latest[$o['project_id']][$o['unit'] === 'admin' ? 'research' : $o['unit']] = $o['result'];
}
$departments = q_all('SELECT DISTINCT department FROM classrooms WHERE department <> "" ORDER BY department');
$pageTitle = 'ตรวจติดตามโครงงาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><div><h1>ตรวจติดตามโครงงาน</h1><div class="text-muted">ในนาม<?= e(ROLE_LABELS[$me['role']]) ?></div></div></div>
<form class="card card-body mb-3"><div class="row g-2">
  <div class="col-md-3"><select name="semester" class="form-select"><?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $semId ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="filter" class="form-select"><?= select_options(['mine' => 'ยังไม่ได้ตรวจโดยหน่วยงานของฉัน', 'all' => 'ทั้งหมด', 'improve' => 'มีข้อควรปรับปรุง / ไม่ผ่าน'], $filter) ?></select></div>
  <div class="col-md-3"><select name="department" class="form-select"><option value="">ทุกแผนกวิชา</option><?php foreach ($departments as $d): ?><option<?= $d['department'] === $dept ? ' selected' : '' ?>><?= e($d['department']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-auto"><button class="btn btn-primary">แสดง</button></div>
</div></form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>โครงงาน</th><th>กลุ่มเรียน</th><th>ครูผู้สอน</th><th>รายงาน</th><th>ความก้าวหน้า</th><th>งานวิจัยฯ</th><th>ฝ่ายวิชาการ</th><th>ฝ่ายแผนฯ</th><th></th></tr></thead><tbody>
  <?php foreach ($projects as $p): ?>
    <tr><td><?= e($p['title']) ?> <?= $p['status'] === 'completed' ? badge(PROJECT_STATUS, 'completed') : '' ?></td>
      <td><?= e($p['code']) ?><div class="small text-muted"><?= e($p['department']) ?></div></td><td class="small"><?= e(person($p)) ?></td>
      <td><?= $p['reports'] ?>/<?= $periodCount ?></td><td><?= (int)$p['progress'] ?>%</td>
      <?php foreach (['research', 'academic', 'strategy'] as $u): ?><td><?= isset($latest[$p['id']][$u]) ? badge(REVIEW_RESULT, $latest[$p['id']][$u]) : '<span class="text-muted small">-</span>' ?></td><?php endforeach; ?>
      <td><a href="<?= url('app/project.php?id=' . $p['id']) ?>" class="btn btn-sm btn-primary">ตรวจ</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$projects): ?><tr><td colspan="9" class="text-center text-muted py-4">ไม่มีโครงงานตามเงื่อนไข</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require APP_ROOT . '/inc/footer.php';
