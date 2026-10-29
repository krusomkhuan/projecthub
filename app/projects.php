<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login();
$pageTitle = 'โครงงาน';

$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));
$status = input('status');
$dept = input('department');
$teacher = int_param('teacher');
$kw = input('q');

$params = [];
$where = [project_scope_sql($params)];
if ($semId && $me['role'] !== 'student') { $where[] = 's.semester_id = ?'; $params[] = $semId; }
if ($status !== '') { $where[] = 'p.status = ?'; $params[] = $status; }
if ($dept !== '') { $where[] = 'c.department = ?'; $params[] = $dept; }
if ($teacher) { $where[] = 's.teacher_id = ?'; $params[] = $teacher; }
if ($kw !== '') {
    $where[] = '(p.title LIKE ? OR p.title_en LIKE ? OR p.id IN (SELECT m.project_id FROM project_members m JOIN users u ON u.id = m.student_id WHERE u.full_name LIKE ? OR u.student_code LIKE ?))';
    array_push($params, "%$kw%", "%$kw%", "%$kw%", "%$kw%");
}
$sql = 'FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id
        LEFT JOIN users a ON a.id = p.advisor_id WHERE ' . implode(' AND ', $where);
$select = 'SELECT p.*, s.semester_id, c.code AS classroom_code, c.name AS classroom_name, c.department,
           t.prefix t_prefix, t.full_name t_full_name, a.prefix a_prefix, a.full_name a_full_name,
           (SELECT COUNT(*) FROM progress_reports r WHERE r.project_id = p.id) AS reports,
           (SELECT COUNT(*) FROM report_periods rp WHERE rp.semester_id = s.semester_id) AS periods ';

if (input('export') === 'csv') {
    $rows = [];
    foreach (q_all($select . $sql . ' ORDER BY c.code, p.id', $params) as $p) {
        $rows[] = [$p['id'], $p['title'], $p['category'], $p['classroom_code'] . ' ' . $p['classroom_name'], $p['department'],
            members_text((int)$p['id']), person($p, 't_'), person($p, 'a_'), PROJECT_STATUS[$p['status']][0], $p['progress'],
            $p['reports'] . '/' . $p['periods'], $p['final_score'], $p['final_grade']];
    }
    send_csv('projects.csv', ['รหัส', 'ชื่อโครงงาน', 'ประเภท', 'กลุ่มเรียน', 'แผนกวิชา', 'สมาชิก', 'ครูผู้สอน', 'ครูที่ปรึกษา', 'สถานะ', 'ความก้าวหน้า%', 'ส่งรายงาน', 'คะแนน', 'เกรด'], $rows);
}

$total = (int)q_val('SELECT COUNT(*) ' . $sql, $params);
[$page, $pages, $offset, $limit] = paginate($total, 30);
$projects = q_all($select . $sql . " ORDER BY p.updated_at DESC LIMIT $limit OFFSET $offset", $params);
$departments = q_all('SELECT DISTINCT department FROM classrooms WHERE department <> "" ORDER BY department');
$teachers = is_overseer() ? q_all('SELECT id, prefix, full_name FROM users WHERE role = "teacher" AND active = 1 ORDER BY full_name') : [];

require APP_ROOT . '/inc/header.php';
?>
<div class="page-head">
  <h1><?= $me['role'] === 'student' ? 'โครงงานของฉัน' : 'โครงงาน' ?> <span class="text-muted fs-6">(<?= $total ?>)</span></h1>
  <div class="d-flex gap-2">
    <?php if (is_role('teacher', 'admin', 'student')): ?><a class="btn btn-primary" href="<?= url('app/project_form.php') ?>"><i class="bi bi-plus-circle"></i> <?= $me['role'] === 'student' ? 'เสนอหัวข้อโครงงาน' : 'เพิ่มโครงงาน' ?></a><?php endif; ?>
    <?php if ($me['role'] !== 'student'): ?><a class="btn btn-outline-success" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-filetype-csv"></i> ส่งออก CSV</a><?php endif; ?>
  </div>
</div>

<?php if ($me['role'] !== 'student'): ?>
<form class="card card-body mb-3 no-print">
  <div class="row g-2">
    <div class="col-md-2"><select name="semester" class="form-select"><?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $semId ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><select name="status" class="form-select"><option value="">ทุกสถานะ</option><?= select_options(PROJECT_STATUS, $status) ?></select></div>
    <div class="col-md-2"><select name="department" class="form-select"><option value="">ทุกแผนกวิชา</option><?php foreach ($departments as $d): ?><option<?= $d['department'] === $dept ? ' selected' : '' ?>><?= e($d['department']) ?></option><?php endforeach; ?></select></div>
    <?php if ($teachers): ?><div class="col-md-2"><select name="teacher" class="form-select"><option value="">ครูผู้สอนทุกคน</option><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"<?= (int)$t['id'] === $teacher ? ' selected' : '' ?>><?= e(person($t)) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="col-md"><input name="q" class="form-control" placeholder="ค้นหาชื่อโครงงาน / ชื่อหรือรหัสนักศึกษา" value="<?= e($kw) ?>"></div>
    <div class="col-md-auto"><button class="btn btn-primary"><i class="bi bi-search"></i> ค้นหา</button></div>
  </div>
</form>
<?php endif; ?>

<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>ชื่อโครงงาน</th><th>กลุ่มเรียน</th><th>ครูผู้สอน / ที่ปรึกษา</th><th>สถานะ</th><th>ความก้าวหน้า</th><th>ส่งรายงาน</th><th>เกรด</th></tr></thead>
  <tbody>
  <?php foreach ($projects as $p): ?>
    <tr>
      <td><a href="<?= url('app/project.php?id=' . $p['id']) ?>" class="fw-semibold"><?= e($p['title']) ?></a><div class="small text-muted"><?= e($p['category']) ?></div></td>
      <td><?= e($p['classroom_code']) ?><div class="small text-muted"><?= e($p['department']) ?></div></td>
      <td><?= e(person($p, 't_')) ?><?php if ($p['a_full_name'] && $p['a_full_name'] !== $p['t_full_name']): ?><div class="small text-muted">ที่ปรึกษา: <?= e(person($p, 'a_')) ?></div><?php endif; ?></td>
      <td><?= badge(PROJECT_STATUS, $p['status']) ?></td>
      <td style="min-width:110px"><div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:<?= (int)$p['progress'] ?>%"></div></div><div class="small text-muted"><?= (int)$p['progress'] ?>%</div></td>
      <td><?= $p['reports'] ?>/<?= $p['periods'] ?></td>
      <td><?= e($p['final_grade'] ?? '-') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$projects): ?><tr><td colspan="7" class="text-center text-muted py-4">ไม่พบโครงงาน</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<div class="mt-3"><?= pagination_links($page, $pages) ?></div>
<?php require APP_ROOT . '/inc/footer.php';
