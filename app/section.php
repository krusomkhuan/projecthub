<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login('admin', 'teacher', 'research', 'academic', 'strategy');

$sec = q_one('SELECT s.*, c.code, c.name AS classroom_name, c.level, c.department, t.prefix, t.full_name
              FROM sections s JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id WHERE s.id = ?', [int_param('id')]);
if (!$sec || ($me['role'] === 'teacher' && (int)$sec['teacher_id'] !== (int)$me['id'])) { http_response_code(404); exit('ไม่พบรายวิชา'); }
$canManage = is_role('admin') || (int)$sec['teacher_id'] === (int)$me['id'];

if (is_post() && $canManage && input('action') === 'import_teams') {
    csrf_check();
    [$count, $skipped] = import_project_teams($sec, read_table_input());
    flash('success', 'นำเข้ากลุ่มโครงงาน ' . $count . ' กลุ่ม');
    if ($skipped) { flash('warning', 'ข้ามบางรายการ: ' . implode('; ', array_slice($skipped, 0, 15))); }
    redirect('app/section.php?id=' . $sec['id']);
}

// ครูผู้สอนนำเข้า / เพิ่มนักเรียนเข้ากลุ่มเรียนที่ตนสอน (นักเรียนสมัครเองได้ที่หน้าสมัครสมาชิก)
if (is_post() && $canManage && in_array(input('action'), ['import_students', 'add_student', 'reset_student'], true)) {
    csrf_check();
    $classroom = q_one('SELECT * FROM classrooms WHERE id = ?', [$sec['classroom_id']]);
    if (input('action') === 'reset_student') {
        $st = q_one('SELECT id, username FROM users WHERE id = ? AND role = "student" AND classroom_id = ?', [int_param('student_id'), $sec['classroom_id']]);
        if ($st) {
            q('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($st['username'], PASSWORD_DEFAULT), $st['id']]);
            log_activity("รีเซ็ตรหัสผ่านนักเรียน {$st['username']}");
            flash('success', "รีเซ็ตรหัสผ่านของ {$st['username']} เป็นรหัสนักศึกษาแล้ว");
        }
    } else {
        try {
            $rows = input('action') === 'add_student' ? [[input('student_code'), input('prefix'), input('full_name')]] : read_table_input();
            if (!$rows) {
                throw new RuntimeException('ไม่พบข้อมูล กรุณาเลือกไฟล์หรือวางข้อมูล');
            }
            db()->beginTransaction();
            [$added, $updated, $skipped] = import_class_students($classroom, $rows);
            db()->commit();
            log_activity("นำเข้านักเรียนกลุ่ม {$classroom['name']}: เพิ่ม $added ปรับปรุง $updated");
            if ($added || $updated) {
                flash('success', "นักเรียนกลุ่ม {$classroom['name']}: เพิ่มใหม่ $added คน · ปรับปรุง $updated คน (รหัสผ่านเริ่มต้น = รหัสนักศึกษา)");
            }
            if ($skipped) {
                flash('warning', 'ข้าม ' . count($skipped) . ' รายการ: ' . implode('; ', array_slice($skipped, 0, 15)));
            }
        } catch (RuntimeException $ex) {
            if (db()->inTransaction()) { db()->rollBack(); }
            flash('danger', $ex->getMessage());
        }
    }
    redirect('app/section.php?id=' . $sec['id'] . '#students');
}

$students = q_all('SELECT u.id, u.prefix, u.full_name, u.student_code, u.last_login,
        (SELECT p.id FROM project_members m JOIN projects p ON p.id = m.project_id JOIN sections s2 ON s2.id = p.section_id WHERE m.student_id = u.id AND s2.semester_id = ? AND p.status <> "cancelled" LIMIT 1) AS project_id
    FROM users u WHERE u.role = "student" AND u.active = 1 AND u.classroom_id = ? ORDER BY u.student_code', [$sec['semester_id'], $sec['classroom_id']]);
$projects = q_all('SELECT p.*, (SELECT COUNT(*) FROM progress_reports r WHERE r.project_id = p.id) AS reports FROM projects p WHERE p.section_id = ? ORDER BY p.id', [$sec['id']]);
$periodCount = (int)q_val('SELECT COUNT(*) FROM report_periods WHERE semester_id = ?', [$sec['semester_id']]);
$titles = array_column($projects, 'title', 'id');
$noProject = count(array_filter($students, fn($s) => !$s['project_id']));
$pageTitle = $sec['code'] . ' ' . $sec['classroom_name'];
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head">
  <div><h1><?= e($sec['code'] . ' ' . $sec['classroom_name']) ?></h1>
    <div class="text-muted"><?= e($sec['subject_code'] . ' ' . $sec['subject_name']) ?> · <?= e($sec['level'] . ' ' . $sec['department']) ?> · ครูผู้สอน <?= e(person($sec)) ?></div></div>
  <?php if ($canManage): ?><a href="<?= url('app/project_form.php?section=' . $sec['id']) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> เพิ่มโครงงาน</a><?php endif; ?>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">โครงงานในกลุ่มเรียน (<?= count($projects) ?>)</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>ชื่อโครงงาน</th><th>สมาชิก</th><th>สถานะ</th><th>รายงาน</th><th>เกรด</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr><td><a href="<?= url('app/project.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a></td>
            <td class="small"><?= e(implode(', ', array_map(fn($m) => $m['full_name'], project_members((int)$p['id'])))) ?></td>
            <td><?= badge(PROJECT_STATUS, $p['status']) ?></td><td><?= $p['reports'] ?>/<?= $periodCount ?></td><td><?= e($p['final_grade'] ?? '-') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?><tr><td colspan="5" class="text-muted">ยังไม่มีโครงงาน</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <?php if ($canManage): ?>
    <div class="card">
      <div class="card-header">นำเข้ากลุ่มโครงงานของผู้เรียน</div>
      <div class="card-body">
        <p class="small text-muted mb-2">คอลัมน์: <strong>กลุ่มที่ | ชื่อโครงงาน | รหัสนักศึกษา | หัวหน้า (1 = ใช่)</strong> — แถวที่มีเลขกลุ่มเดียวกันจะรวมเป็นโครงงานเดียว
          · <a href="<?= url('app/admin/template.php?type=teams') ?>">ดาวน์โหลดไฟล์ตัวอย่าง</a></p>
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import_teams">
          <input type="file" name="file" accept=".xlsx,.csv,.txt" class="form-control mb-2" title="Excel หรือ CSV">
          <textarea name="paste" class="form-control mb-2" rows="4" placeholder="หรือคัดลอกตารางจาก Excel มาวางที่นี่"></textarea>
          <button class="btn btn-success"><i class="bi bi-upload"></i> นำเข้า</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3" id="students">
    <div class="card-header d-flex justify-content-between"><span>รายชื่อผู้เรียน (<?= count($students) ?>)</span><?php if ($noProject): ?><span class="badge text-bg-danger">ยังไม่มีโครงงาน <?= $noProject ?></span><?php endif; ?></div>
    <ul class="list-group list-group-flush small" style="max-height:520px;overflow:auto">
      <?php foreach ($students as $s): ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between gap-2"><span><?= e($s['student_code'] . ' ' . person($s)) ?></span>
            <?= $s['project_id'] ? '<a href="' . url('app/project.php?id=' . $s['project_id']) . '" class="text-truncate" style="max-width:45%">' . e($titles[$s['project_id']] ?? 'ดูโครงงาน') . '</a>' : '<span class="text-danger">ยังไม่มีโครงงาน</span>' ?></div>
          <div class="d-flex justify-content-between align-items-center text-muted">
            <span><?= $s['last_login'] ? '<i class="bi bi-check-circle text-success"></i> เข้าระบบแล้ว' : '<i class="bi bi-dash-circle"></i> ยังไม่เคยเข้าระบบ' ?></span>
            <?php if ($canManage): ?><form method="post" class="d-inline" data-confirm="รีเซ็ตรหัสผ่านของ <?= e($s['student_code']) ?> เป็นรหัสนักศึกษา?"><?= csrf_field() ?><input type="hidden" name="action" value="reset_student"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button class="btn btn-link btn-sm p-0 text-warning" title="รีเซ็ตรหัสผ่าน"><i class="bi bi-key"></i> รีเซ็ตรหัสผ่าน</button></form><?php endif; ?>
          </div></li>
      <?php endforeach; ?>
      <?php if (!$students): ?><li class="list-group-item text-muted">ยังไม่มีนักเรียน — นำเข้าด้านล่าง หรือให้นักเรียนสมัครสมาชิกเอง</li><?php endif; ?>
    </ul>
    </div>
    <?php if ($canManage): ?>
    <div class="card">
      <div class="card-header">เพิ่ม / นำเข้านักเรียนกลุ่ม <?= e($sec['classroom_name']) ?></div>
      <div class="card-body">
        <form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="add_student">
          <div class="col-4"><input name="student_code" class="form-control form-control-sm" placeholder="รหัสนักศึกษา" required pattern="[A-Za-z0-9_\-]{1,20}"></div>
          <div class="col-3"><select name="prefix" class="form-select form-select-sm"><?= select_options(['' => 'คำนำหน้า', 'นาย' => 'นาย', 'นางสาว' => 'นางสาว', 'นาง' => 'นาง'], '') ?></select></div>
          <div class="col-5"><input name="full_name" class="form-control form-control-sm" placeholder="ชื่อ นามสกุล" required></div>
          <div class="col-12"><button class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> เพิ่มทีละคน</button></div>
        </form>
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="import_students">
          <p class="small text-muted mb-2">นำเข้าหลายคน คอลัมน์: <strong>รหัสนักศึกษา | คำนำหน้า | ชื่อ-สกุล</strong> (หรือแยก ชื่อ | นามสกุล) · <a href="<?= url('app/admin/template.php?type=class_students') ?>">ไฟล์ตัวอย่าง</a></p>
          <input type="file" name="file" accept=".xlsx,.csv,.txt" class="form-control form-control-sm mb-2">
          <textarea name="paste" class="form-control form-control-sm mb-2" rows="3" placeholder="หรือคัดลอกตารางจาก Excel มาวางที่นี่"></textarea>
          <button class="btn btn-sm btn-success"><i class="bi bi-upload"></i> นำเข้านักเรียน</button>
        </form>
        <p class="small text-muted mt-2 mb-0">รหัสผ่านเริ่มต้น = รหัสนักศึกษา (บังคับเปลี่ยนเมื่อเข้าครั้งแรก) · นักเรียนสมัครเองได้ที่หน้า <a href="<?= url('register.php') ?>" target="_blank">สมัครสมาชิกนักเรียน</a> · นักเรียนที่อยู่กลุ่มอื่นแล้วจะไม่ถูกย้าย</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
