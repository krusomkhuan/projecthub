<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');

$cid = int_param('id');
if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'save_class') {
        $major = input('major');
        $advisor = (string)preg_replace('/\s+/u', ' ', input('advisor_name'));
        $dept = (string)(q_val('SELECT d.name FROM majors m JOIN departments d ON d.id = m.department_id WHERE m.name = ? ORDER BY m.id LIMIT 1', [$major])
            ?? q_val('SELECT name FROM departments WHERE name = ?', [$major]) ?? input('department'));
        $advisorId = $advisor === '' ? null
            : q_val('SELECT id FROM users WHERE role = "teacher" AND (full_name = ? OR CONCAT(prefix, full_name) = ?) LIMIT 1', [$advisor, $advisor]);
        $year = input('academic_year');
        $data = [input('code'), input('name'), input('level'), $dept, $major, ctype_digit($year) ? (int)$year : null, $advisor, $advisorId];
        if ($data[0] === '' || $data[1] === '') { flash('danger', 'กรุณาระบุรหัสและชื่อกลุ่มเรียน'); redirect('app/admin/classrooms.php'); }
        try {
            if ($cid) {
                q('UPDATE classrooms SET code=?, name=?, level=?, department=?, major=?, academic_year=?, advisor_name=?, advisor_id=? WHERE id=?', [...$data, $cid]);
            } else {
                q('INSERT INTO classrooms (code, name, level, department, major, academic_year, advisor_name, advisor_id) VALUES (?,?,?,?,?,?,?,?)', $data);
                $cid = (int)db()->lastInsertId();
            }
            flash('success', 'บันทึกกลุ่มเรียนแล้ว');
        } catch (PDOException $ex) {
            flash('danger', 'รหัสกลุ่มเรียนซ้ำ');
        }
    } elseif ($action === 'delete_class') {
        if (q_val('SELECT COUNT(*) FROM users WHERE classroom_id = ?', [$cid]) || q_val('SELECT COUNT(*) FROM sections WHERE classroom_id = ?', [$cid])) {
            flash('danger', 'ลบไม่ได้ เนื่องจากมีนักเรียนหรือรายวิชาโครงงานในกลุ่มนี้');
        } else {
            q('DELETE FROM classrooms WHERE id = ?', [$cid]);
            flash('success', 'ลบกลุ่มเรียนแล้ว');
            redirect('app/admin/classrooms.php');
        }
    } elseif ($action === 'save_student') {
        $sid = int_param('student_id');
        $code = input('student_code');
        if ($code === '' || input('full_name') === '') { flash('danger', 'กรุณาระบุรหัสนักศึกษาและชื่อ-สกุล'); redirect('app/admin/classrooms.php?id=' . $cid); }
        if (q_val('SELECT id FROM users WHERE (student_code = ? OR username = ?) AND id <> ?', [$code, $code, $sid])) {
            flash('danger', 'รหัสนักศึกษานี้มีในระบบแล้ว');
        } elseif ($sid) {
            q('UPDATE users SET username=?, student_code=?, prefix=?, full_name=?, classroom_id=? WHERE id=? AND role="student"', [$code, $code, input('prefix'), input('full_name'), int_param('classroom_id') ?: $cid, $sid]);
            flash('success', 'บันทึกข้อมูลนักเรียนแล้ว');
        } else {
            q('INSERT INTO users (username, student_code, prefix, full_name, role, classroom_id, password_hash) VALUES (?,?,?,?,"student",?,?)',
                [$code, $code, input('prefix'), input('full_name'), $cid, password_hash($code, PASSWORD_DEFAULT)]);
            flash('success', 'เพิ่มนักเรียนแล้ว (รหัสผ่านเริ่มต้น = รหัสนักศึกษา)');
        }
    } elseif ($action === 'reset') {
        $u = q_one('SELECT username FROM users WHERE id = ? AND role = "student"', [int_param('student_id')]);
        if ($u) {
            q('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($u['username'], PASSWORD_DEFAULT), int_param('student_id')]);
            flash('success', 'รีเซ็ตรหัสผ่านเป็นรหัสนักศึกษาแล้ว');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE users SET active = 1 - active WHERE id = ? AND role = "student"', [int_param('student_id')]);
        flash('success', 'เปลี่ยนสถานะแล้ว');
    }
    redirect('app/admin/classrooms.php' . ($cid ? '?id=' . $cid : ''));
}

$classrooms = q_all('SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.classroom_id = c.id AND u.role = "student" AND u.active = 1) AS students FROM classrooms c ORDER BY c.department, c.code');
$majorGroups = [];
foreach (q_all('SELECT d.name AS dept, m.name FROM majors m JOIN departments d ON d.id = m.department_id ORDER BY d.name, m.name') as $m) {
    $majorGroups[$m['dept']][] = $m['name'];
}
$teacherNames = array_column(q_all('SELECT full_name FROM users WHERE role = "teacher" AND active = 1 ORDER BY full_name'), 'full_name');
$cur = $cid ? q_one('SELECT * FROM classrooms WHERE id = ?', [$cid]) : null;
$students = $cur ? q_all('SELECT * FROM users WHERE role = "student" AND classroom_id = ? ORDER BY student_code', [$cid]) : [];
$editStudent = int_param('student') ? q_one('SELECT * FROM users WHERE id = ? AND role = "student"', [int_param('student')]) : null;
$pageTitle = 'กลุ่มผู้เรียน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>กลุ่มผู้เรียน</h1><a href="<?= url('app/admin/import.php?type=classrooms') ?>" class="btn btn-outline-success"><i class="bi bi-upload"></i> นำเข้ากลุ่มผู้เรียน</a></div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">กลุ่มเรียนทั้งหมด (<?= count($classrooms) ?>)</div>
      <div class="list-group list-group-flush" style="max-height:480px;overflow:auto">
        <?php foreach ($classrooms as $c): ?>
          <a href="?id=<?= $c['id'] ?>" class="list-group-item list-group-item-action<?= (int)$c['id'] === $cid ? ' active' : '' ?>">
            <div class="d-flex justify-content-between"><strong><?= e($c['code']) ?></strong><span class="badge text-bg-light border"><?= $c['students'] ?> คน</span></div>
            <div class="small"><?= e($c['name']) ?> · <?= e($c['level']) ?><?= $c['major'] ? ' · ' . e($c['major']) : '' ?></div></a>
        <?php endforeach; ?>
        <?php if (!$classrooms): ?><div class="list-group-item text-muted">ยังไม่มีกลุ่มเรียน</div><?php endif; ?>
      </div>
    </div>
    <div class="card"><div class="card-header"><?= $cur ? 'แก้ไขกลุ่มเรียน' : 'เพิ่มกลุ่มเรียน' ?></div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_class"><input type="hidden" name="id" value="<?= $cid ?: '' ?>">
        <div class="mb-2"><label class="form-label small">รหัสกลุ่มเรียน *</label><input name="code" class="form-control" value="<?= e($cur['code'] ?? '') ?>" required></div>
        <div class="mb-2"><label class="form-label small">ชื่อกลุ่มเรียน *</label><input name="name" class="form-control" value="<?= e($cur['name'] ?? '') ?>" placeholder="เช่น ปวส.2/1 เทคโนโลยีสารสนเทศ" required></div>
        <div class="row g-2 mb-3">
          <div class="col-5"><label class="form-label small">ระดับชั้น</label><input name="level" class="form-control" list="levels" value="<?= e($cur['level'] ?? '') ?>" placeholder="ปวช.3">
            <datalist id="levels"><?php foreach (['ปวช.1', 'ปวช.2', 'ปวช.3', 'ปวส.1', 'ปวส.2'] as $l): ?><option value="<?= $l ?>"><?php endforeach; ?></datalist></div>
          <div class="col-7"><label class="form-label small">ปีการศึกษา</label><input name="academic_year" type="number" class="form-control" value="<?= e($cur['academic_year'] ?? '') ?>" placeholder="<?= date('Y') + 543 ?>"></div>
          <div class="col-12"><label class="form-label small">สาขาวิชา</label><select name="major" class="form-select"><option value="">-- เลือกสาขาวิชา --</option>
            <?php foreach ($majorGroups as $deptName => $names): ?><optgroup label="<?= e($deptName) ?>"><?php foreach ($names as $mn): ?><option<?= $mn === ($cur['major'] ?? '') ? ' selected' : '' ?>><?= e($mn) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            <?php if (!empty($cur['major']) && !in_array($cur['major'], array_merge([], ...array_values($majorGroups)), true)): ?><option selected><?= e($cur['major']) ?></option><?php endif; ?>
          </select><input type="hidden" name="department" value="<?= e($cur['department'] ?? '') ?>">
            <?php if (!$majorGroups): ?><div class="form-text">ยังไม่มีสาขาวิชา — <a href="<?= url('app/admin/import.php?type=majors') ?>">นำเข้าสาขาวิชา</a></div><?php endif; ?></div>
          <div class="col-12"><label class="form-label small">ครูที่ปรึกษา</label><input name="advisor_name" class="form-control" list="teacherNames" value="<?= e($cur['advisor_name'] ?? '') ?>">
            <datalist id="teacherNames"><?php foreach ($teacherNames as $tn): ?><option value="<?= e($tn) ?>"><?php endforeach; ?></datalist></div>
        </div>
        <button class="btn btn-primary w-100">บันทึก</button>
        <?php if ($cur): ?><a href="?" class="btn btn-light w-100 mt-2">+ เพิ่มกลุ่มเรียนใหม่</a><?php endif; ?>
      </form>
      <?php if ($cur): ?><form method="post" class="text-end mt-2" data-confirm="ลบกลุ่มเรียนนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_class"><input type="hidden" name="id" value="<?= $cid ?>"><button class="btn btn-link btn-sm text-danger">ลบกลุ่มเรียน</button></form><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-8">
  <?php if ($cur): ?>
    <div class="card mb-3"><div class="card-header"><?= e($cur['code'] . ' ' . $cur['name']) ?> · <?= e($cur['department']) ?> (<?= count($students) ?> คน)
      <div class="small fw-normal text-muted">สาขาวิชา <?= e($cur['major'] ?: '-') ?> · ปีการศึกษา <?= e($cur['academic_year'] ?? '-') ?> · ครูที่ปรึกษา <?= e($cur['advisor_name'] ?: '-') ?><?= $cur['advisor_name'] && !$cur['advisor_id'] ? ' <span class="badge text-bg-warning">ยังไม่เชื่อมบัญชีครู</span>' : '' ?></div></div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>รหัสนักศึกษา</th><th>ชื่อ-สกุล</th><th>เข้าระบบล่าสุด</th><th></th></tr></thead><tbody>
        <?php foreach ($students as $s): ?>
          <tr class="<?= $s['active'] ? '' : 'table-secondary' ?>"><td><?= e($s['student_code']) ?></td><td><?= e(person($s)) ?></td>
            <td class="small"><?= $s['last_login'] ? thai_date($s['last_login'], true) : '<span class="text-muted">ยังไม่เคย</span>' ?></td>
            <td class="text-end text-nowrap">
              <a href="?id=<?= $cid ?>&student=<?= $s['id'] ?>#sform" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" data-confirm="รีเซ็ตรหัสผ่านเป็นรหัสนักศึกษา?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-key"></i></button></form>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="student_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-<?= $s['active'] ? 'danger' : 'success' ?>"><i class="bi bi-<?= $s['active'] ? 'pause-circle' : 'play-circle' ?>"></i></button></form>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="4" class="text-muted">ยังไม่มีนักเรียนในกลุ่มนี้</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>
    <div class="card" id="sform"><div class="card-header"><?= $editStudent ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียนในกลุ่มนี้' ?></div><div class="card-body">
      <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_student"><input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="student_id" value="<?= $editStudent['id'] ?? '' ?>">
        <div class="col-md-3"><label class="form-label small">รหัสนักศึกษา *</label><input name="student_code" class="form-control" value="<?= e($editStudent['student_code'] ?? '') ?>" required></div>
        <div class="col-md-2"><label class="form-label small">คำนำหน้า</label><input name="prefix" class="form-control" value="<?= e($editStudent['prefix'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label small">ชื่อ-สกุล *</label><input name="full_name" class="form-control" value="<?= e($editStudent['full_name'] ?? '') ?>" required></div>
        <?php if ($editStudent): ?><div class="col-md-3"><label class="form-label small">ย้ายกลุ่มเรียน</label><select name="classroom_id" class="form-select"><?php foreach ($classrooms as $c): ?><option value="<?= $c['id'] ?>"<?= (int)$c['id'] === $cid ? ' selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div class="col-12"><button class="btn btn-primary">บันทึก</button> <?php if ($editStudent): ?><a href="?id=<?= $cid ?>" class="btn btn-light">ยกเลิก</a><?php endif; ?></div>
      </form>
    </div></div>
  <?php else: ?>
    <div class="card card-body text-muted">เลือกกลุ่มเรียนทางซ้ายเพื่อดูรายชื่อผู้เรียน</div>
  <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
