<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');

$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));
if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'add') {
        $added = 0;
        foreach ((array)($_POST['classrooms'] ?? []) as $cid) {
            $ok = q('INSERT IGNORE INTO sections (semester_id, classroom_id, teacher_id, subject_code, subject_name) VALUES (?,?,?,?,?)',
                [$semId, (int)$cid, int_param('teacher_id'), input('subject_code'), input('subject_name', 'โครงงาน')])->rowCount();
            $added += $ok;
        }
        log_activity("มอบหมายรายวิชาโครงงาน $added กลุ่มเรียน");
        flash($added ? 'success' : 'warning', $added ? "เพิ่มรายวิชาโครงงาน $added กลุ่มเรียน" : 'ไม่มีรายการที่เพิ่ม (อาจซ้ำกับที่มีอยู่)');
    } elseif ($action === 'update') {
        $sec = q_one('SELECT s.*, (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects FROM sections s WHERE s.id = ?', [int_param('id')]);
        $cid = int_param('classroom_id') ?: (int)($sec['classroom_id'] ?? 0);
        if (!$sec) {
            flash('danger', 'ไม่พบรายวิชา');
        } elseif ($cid !== (int)$sec['classroom_id'] && $sec['projects']) {
            // สมาชิกโครงงานเป็นนักเรียนของกลุ่มเดิม จึงย้ายกลุ่มเรียนไม่ได้
            flash('danger', 'เปลี่ยนกลุ่มเรียนไม่ได้ เนื่องจากมีกลุ่มโครงงานในรายวิชานี้แล้ว');
        } elseif ($cid !== (int)$sec['classroom_id'] && ($dup = q_one('SELECT t.prefix, t.full_name FROM sections s JOIN users t ON t.id = s.teacher_id WHERE s.semester_id = ? AND s.classroom_id = ? AND s.id <> ?', [$sec['semester_id'], $cid, $sec['id']]))) {
            flash('danger', 'กลุ่มเรียนนี้มีรายวิชาโครงงานแล้ว (ครูผู้สอน ' . person($dup) . ')');
        } else {
            q('UPDATE sections SET classroom_id = ?, teacher_id = ?, subject_code = ?, subject_name = ? WHERE id = ?', [$cid, int_param('teacher_id'), input('subject_code'), input('subject_name'), $sec['id']]);
            log_activity('แก้ไขรายวิชาโครงงาน #' . $sec['id']);
            flash('success', 'บันทึกแล้ว');
        }
    } elseif ($action === 'delete') {
        if (q_val('SELECT COUNT(*) FROM projects WHERE section_id = ?', [int_param('id')])) {
            flash('danger', 'ไม่สามารถลบได้ เนื่องจากมีโครงงานในรายวิชานี้');
        } else {
            q('DELETE FROM sections WHERE id = ?', [int_param('id')]);
            flash('success', 'ลบแล้ว');
        }
    }
    redirect('app/admin/sections.php?semester=' . $semId);
}

$sections = q_all('SELECT s.*, c.code, c.name AS classroom_name, c.department, t.prefix, t.full_name,
        (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects,
        (SELECT COUNT(*) FROM users u WHERE u.classroom_id = s.classroom_id AND u.role = "student" AND u.active = 1) AS students
    FROM sections s JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id WHERE s.semester_id = ? ORDER BY c.code', [$semId]);
$teachers = q_all('SELECT id, prefix, full_name FROM users WHERE role = "teacher" AND active = 1 ORDER BY full_name');
$classrooms = q_all('SELECT * FROM classrooms ORDER BY code');
$edit = int_param('edit');
// สถานะการแจ้งรายวิชาที่สอนของครู ในภาคเรียนที่เลือก
$declStatus = ['yes' => [], 'no' => [], 'none' => []];
foreach (q_all('SELECT u.prefix, u.full_name, d.teaches_project FROM users u LEFT JOIN teacher_declarations d ON d.teacher_id = u.id AND d.semester_id = ?
                WHERE u.role = "teacher" AND u.active = 1 ORDER BY u.full_name', [$semId]) as $t) {
    $declStatus[$t['teaches_project'] === null ? 'none' : ($t['teaches_project'] ? 'yes' : 'no')][] = person($t);
}
$pageTitle = 'รายวิชาโครงงาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>รายวิชาโครงงาน (มอบหมายครูผู้สอน)</h1>
  <form><select name="semester" class="form-select" onchange="this.form.submit()"><?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $semId ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?></select></form>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>กลุ่มเรียน</th><th>รายวิชา</th><th>ครูผู้สอน</th><th class="text-end">ผู้เรียน</th><th class="text-end">โครงงาน</th><th></th></tr></thead><tbody>
    <?php foreach ($sections as $s): ?>
      <?php if ($edit === (int)$s['id']): ?>
        <tr><td colspan="6"><form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $s['id'] ?>">
          <div class="col-md-2"><select name="classroom_id" class="form-select form-select-sm" title="กลุ่มเรียน"<?= $s['projects'] ? ' disabled' : '' ?>>
            <?php foreach ($classrooms as $c): ?><option value="<?= $c['id'] ?>"<?= (int)$c['id'] === (int)$s['classroom_id'] ? ' selected' : '' ?>><?= e($c['name'] . ' (' . $c['code'] . ')') ?></option><?php endforeach; ?>
          </select><?php if ($s['projects']): ?><div class="small text-muted">มีกลุ่มโครงงานแล้ว เปลี่ยนกลุ่มไม่ได้</div><?php endif; ?></div>
          <div class="col-md-2"><input name="subject_code" class="form-control form-control-sm" value="<?= e($s['subject_code']) ?>"></div>
          <div class="col-md-3"><input name="subject_name" class="form-control form-control-sm" value="<?= e($s['subject_name']) ?>"></div>
          <div class="col-md-3"><select name="teacher_id" class="form-select form-select-sm"><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"<?= (int)$t['id'] === (int)$s['teacher_id'] ? ' selected' : '' ?>><?= e(person($t)) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><button class="btn btn-sm btn-primary">บันทึก</button> <a href="?semester=<?= $semId ?>" class="btn btn-sm btn-light">ยกเลิก</a></div>
        </form></td></tr>
      <?php else: ?>
        <tr><td><a href="<?= url('app/section.php?id=' . $s['id']) ?>"><?= e($s['code']) ?></a><div class="small text-muted"><?= e($s['classroom_name']) ?></div></td>
          <td><?= e($s['subject_code']) ?><div class="small text-muted"><?= e($s['subject_name']) ?></div></td><td><?= e(person($s)) ?></td>
          <td class="text-end"><?= $s['students'] ?></td><td class="text-end"><?= $s['projects'] ?></td>
          <td class="text-end text-nowrap"><a href="?semester=<?= $semId ?>&edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            <form method="post" class="d-inline" data-confirm="ลบรายวิชานี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$sections): ?><tr><td colspan="6" class="text-muted">ยังไม่มีรายวิชาโครงงานในภาคเรียนนี้</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4">
  <div class="card mb-3"><div class="card-header">การแจ้งรายวิชาที่สอนของครู</div><div class="card-body small">
    <div class="d-flex justify-content-between"><span><span class="badge text-bg-success">&nbsp;</span> แจ้งว่าสอนโครงงาน</span><strong><?= count($declStatus['yes']) ?></strong></div>
    <div class="d-flex justify-content-between"><span><span class="badge text-bg-secondary">&nbsp;</span> แจ้งว่าไม่ได้สอน</span><strong><?= count($declStatus['no']) ?></strong></div>
    <div class="d-flex justify-content-between"><span><span class="badge text-bg-warning">&nbsp;</span> ยังไม่แจ้ง</span><strong><?= count($declStatus['none']) ?></strong></div>
    <?php if ($declStatus['none']): ?>
      <details class="mt-2"><summary>รายชื่อครูที่ยังไม่แจ้ง</summary><div class="text-muted mt-1"><?= e(implode(', ', $declStatus['none'])) ?></div></details>
    <?php endif; ?>
    <div class="text-muted mt-2">ครูแจ้งเองเมื่อเข้าสู่ระบบครั้งแรก และแก้ไขได้ที่เมนู "แจ้งรายวิชาที่สอน"</div>
  </div></div>
  <div class="card"><div class="card-header">เพิ่มรายวิชาโครงงาน</div><div class="card-body">
    <?php if (!$teachers || !$classrooms): ?><div class="alert alert-warning small">กรุณานำเข้าครูผู้สอนและกลุ่มผู้เรียนก่อน · <a href="<?= url('app/admin/import.php') ?>">นำเข้าข้อมูล</a></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="mb-2"><label class="form-label small">ครูผู้สอน</label><select name="teacher_id" class="form-select" required><option value="">-- เลือก --</option><?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e(person($t)) ?></option><?php endforeach; ?></select></div>
      <div class="row g-2 mb-2">
        <div class="col-5"><label class="form-label small">รหัสวิชา</label><input name="subject_code" class="form-control" placeholder="เช่น 30000-8001"></div>
        <div class="col-7"><label class="form-label small">ชื่อวิชา</label><input name="subject_name" class="form-control" value="โครงงาน"></div>
      </div>
      <label class="form-label small">กลุ่มเรียน (เลือกได้หลายกลุ่ม)</label>
      <div class="border rounded p-2 mb-2" style="max-height:300px;overflow:auto">
        <?php foreach ($classrooms as $c): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="classrooms[]" value="<?= $c['id'] ?>" id="c<?= $c['id'] ?>"><label class="form-check-label small" for="c<?= $c['id'] ?>"><?= e($c['code'] . ' ' . $c['name']) ?></label></div><?php endforeach; ?>
      </div>
      <button class="btn btn-primary w-100">เพิ่ม</button>
    </form>
  </div></div></div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
