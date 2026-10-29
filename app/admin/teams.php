<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');

$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));
$secId = int_param('section');

if (is_post() && input('action') === 'import') {
    csrf_check();
    $sec = q_one('SELECT s.*, c.code FROM sections s JOIN classrooms c ON c.id = s.classroom_id WHERE s.id = ?', [int_param('import_section')]);
    try {
        $rows = read_table_input();
        if (!$sec) {
            flash('danger', 'กรุณาเลือกรายวิชา / กลุ่มเรียนที่จะนำเข้า');
        } elseif (!$rows) {
            flash('danger', 'ไม่พบข้อมูล กรุณาเลือกไฟล์หรือวางข้อมูล');
        } else {
            [$count, $skipped] = import_project_teams($sec, $rows);
            flash('success', "นำเข้ากลุ่มโครงงาน {$sec['code']} จำนวน $count กลุ่ม");
            if ($skipped) { flash('warning', 'ข้ามบางรายการ: ' . implode('; ', array_slice($skipped, 0, 15))); }
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('app/admin/teams.php?semester=' . $semId . '&section=' . ($sec['id'] ?? $secId));
}

$sections = q_all('SELECT s.*, c.code, c.name AS classroom_name, c.department, t.prefix, t.full_name,
        (SELECT COUNT(*) FROM users u WHERE u.classroom_id = s.classroom_id AND u.role = "student" AND u.active = 1) AS students,
        (SELECT COUNT(DISTINCT m.student_id) FROM project_members m JOIN projects p ON p.id = m.project_id WHERE p.section_id = s.id AND p.status <> "cancelled") AS grouped,
        (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id AND p.status <> "cancelled") AS teams
    FROM sections s JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id
    WHERE s.semester_id = ? ORDER BY c.code', [$semId]);

$params = [$semId];
$where = 's.semester_id = ? AND p.status <> "cancelled"';
if ($secId) { $where .= ' AND s.id = ?'; $params[] = $secId; }
$teams = q_all("SELECT p.id, p.title, p.status, p.progress, s.id AS section_id, c.code, c.name AS classroom_name
    FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id
    WHERE $where ORDER BY c.code, p.id", $params);
$members = [];
if ($teams) {
    $ids = implode(',', array_map('intval', array_column($teams, 'id')));
    foreach (q_all("SELECT m.project_id, m.is_leader, u.prefix, u.full_name, u.student_code FROM project_members m JOIN users u ON u.id = m.student_id
                    WHERE m.project_id IN ($ids) ORDER BY m.is_leader DESC, u.student_code") as $m) {
        $members[$m['project_id']][] = $m;
    }
}
$ungrouped = $secId ? q_all('SELECT u.student_code, u.prefix, u.full_name FROM users u JOIN sections s ON s.classroom_id = u.classroom_id
    WHERE s.id = ? AND u.role = "student" AND u.active = 1 AND u.id NOT IN (
        SELECT m.student_id FROM project_members m JOIN projects p ON p.id = m.project_id JOIN sections s2 ON s2.id = p.section_id
        WHERE s2.semester_id = ? AND p.status <> "cancelled") ORDER BY u.student_code', [$secId, $semId]) : [];
$totals = ['teams' => array_sum(array_column($sections, 'teams')), 'students' => array_sum(array_column($sections, 'students')), 'grouped' => array_sum(array_column($sections, 'grouped'))];
$pageTitle = 'กลุ่มโครงงาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><div><h1><i class="bi bi-diagram-3"></i> กลุ่มโครงงาน</h1>
  <div class="text-muted">กลุ่มโครงงาน <?= $totals['teams'] ?> กลุ่ม · ผู้เรียนมีกลุ่มแล้ว <?= $totals['grouped'] ?>/<?= $totals['students'] ?> คน</div></div>
  <form class="d-flex gap-2"><select name="semester" class="form-select" onchange="this.form.submit()"><?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $semId ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?></select></form>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">กลุ่มเรียน (<?= count($sections) ?>)</div>
      <div class="list-group list-group-flush" style="max-height:460px;overflow:auto">
        <a href="?semester=<?= $semId ?>" class="list-group-item list-group-item-action<?= !$secId ? ' active' : '' ?>">ทุกกลุ่มเรียน</a>
        <?php foreach ($sections as $s): $missing = (int)$s['students'] - (int)$s['grouped']; ?>
          <a href="?semester=<?= $semId ?>&section=<?= $s['id'] ?>" class="list-group-item list-group-item-action<?= (int)$s['id'] === $secId ? ' active' : '' ?>">
            <div class="d-flex justify-content-between"><strong><?= e($s['code']) ?></strong><span class="badge text-bg-light border"><?= $s['teams'] ?> กลุ่ม</span></div>
            <div class="small"><?= e($s['classroom_name']) ?> · <?= e(person($s)) ?></div>
            <?php if ($missing > 0): ?><div class="small text-danger">ยังไม่มีกลุ่ม <?= $missing ?> คน</div><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if (!$sections): ?><div class="list-group-item text-muted small">ยังไม่มีรายวิชาโครงงานในภาคเรียนนี้ · <a href="<?= url('app/admin/sections.php') ?>">มอบหมายรายวิชา</a></div><?php endif; ?>
      </div>
    </div>
    <div class="card"><div class="card-header">นำเข้ากลุ่มโครงงาน</div><div class="card-body">
      <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import">
        <div class="mb-2"><label class="form-label small">รายวิชา / กลุ่มเรียน</label><select name="import_section" class="form-select" required>
          <option value="">-- เลือก --</option>
          <?php foreach ($sections as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $secId ? ' selected' : '' ?>><?= e($s['code'] . ' ' . $s['classroom_name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="mb-2"><label class="form-label small">ไฟล์ Excel (.xlsx) หรือ CSV</label><input type="file" name="file" accept=".xlsx,.csv,.txt" class="form-control"></div>
        <div class="mb-2"><label class="form-label small">หรือคัดลอกตารางจาก Excel มาวาง</label><textarea name="paste" class="form-control" rows="3"></textarea></div>
        <button class="btn btn-success w-100"><i class="bi bi-upload"></i> นำเข้า</button>
      </form>
      <p class="small text-muted mt-2 mb-0">คอลัมน์: <strong>กลุ่มที่ | ชื่อโครงงาน | รหัสนักศึกษา | หัวหน้า (1 = ใช่)</strong> แถวที่เลขกลุ่มเดียวกันรวมเป็นโครงงานเดียว · <a href="<?= url('app/admin/template.php?type=teams') ?>">ไฟล์ตัวอย่าง</a></p>
    </div></div>
  </div>

  <div class="col-lg-8">
    <?php if ($secId): ?><div class="mb-2 text-end"><a href="<?= url('app/project_form.php?section=' . $secId) ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> เพิ่มกลุ่มโครงงาน</a></div><?php endif; ?>
    <div class="card mb-3"><div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>#</th><th>ชื่อโครงงาน</th><?= $secId ? '' : '<th>กลุ่มเรียน</th>' ?><th>สมาชิก</th><th>สถานะ</th></tr></thead><tbody>
      <?php foreach ($teams as $i => $t): ?>
        <tr><td><?= $i + 1 ?></td><td><a href="<?= url('app/project.php?id=' . $t['id']) ?>"><?= e($t['title']) ?></a><div class="small text-muted">ความก้าวหน้า <?= (int)$t['progress'] ?>%</div></td>
          <?= $secId ? '' : '<td class="small">' . e($t['code']) . '</td>' ?>
          <td class="small"><?php foreach ($members[$t['id']] ?? [] as $m): ?><div><?= e($m['student_code'] . ' ' . person($m)) ?><?= $m['is_leader'] ? ' <span class="badge text-bg-warning">หัวหน้า</span>' : '' ?></div><?php endforeach; ?></td>
          <td><?= badge(PROJECT_STATUS, $t['status']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$teams): ?><tr><td colspan="5" class="text-muted text-center py-4">ยังไม่มีกลุ่มโครงงาน</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php if ($ungrouped): ?>
      <div class="card"><div class="card-header text-danger">ผู้เรียนที่ยังไม่มีกลุ่มโครงงาน (<?= count($ungrouped) ?>)</div>
        <div class="card-body small"><?= implode(' · ', array_map(fn($u) => e($u['student_code'] . ' ' . person($u)), $ungrouped)) ?></div></div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
