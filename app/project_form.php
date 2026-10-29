<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login('admin', 'teacher', 'student');
$sem = active_semester();

$id = int_param('id');
$project = $id ? find_project($id) : null;
if ($id && !$project) { http_response_code(404); exit('ไม่พบโครงงาน'); }
if ($project) {
    $access = project_access($project);
    $editable = $access === 'overseer' || $access === 'teacher' || ($access === 'member' && in_array($project['status'], ['proposed', 'revise'], true));
    if (!$editable) { flash('danger', 'ไม่สามารถแก้ไขโครงงานนี้ได้'); redirect('app/project.php?id=' . $id); }
}
$pageTitle = $project ? 'แก้ไขโครงงาน' : ($me['role'] === 'student' ? 'เสนอหัวข้อโครงงาน' : 'เพิ่มโครงงาน');

// กลุ่มเรียน (section) ที่เลือกได้
$secSql = 'SELECT s.*, c.code, c.name AS classroom_name, t.prefix, t.full_name FROM sections s JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id WHERE s.semester_id = ?';
$secParams = [(int)($sem['id'] ?? 0)];
if ($me['role'] === 'teacher') { $secSql .= ' AND s.teacher_id = ?'; $secParams[] = $me['id']; }
if ($me['role'] === 'student') { $secSql .= ' AND s.classroom_id = ?'; $secParams[] = (int)$me['classroom_id']; }
$sections = q_all($secSql . ' ORDER BY c.code', $secParams);

$sectionId = $project ? (int)$project['section_id'] : int_param('section', count($sections) === 1 ? (int)$sections[0]['id'] : 0);
$section = $sectionId ? q_one('SELECT s.*, c.code, c.name AS classroom_name FROM sections s JOIN classrooms c ON c.id = s.classroom_id WHERE s.id = ?', [$sectionId]) : null;
if ($section && !$project && !in_array($sectionId, array_map('intval', array_column($sections, 'id')), true)) {
    $section = null;
}

if (!$project && $me['role'] === 'student') {
    $has = q_val('SELECT p.id FROM projects p JOIN project_members m ON m.project_id = p.id JOIN sections s ON s.id = p.section_id
                  WHERE m.student_id = ? AND s.semester_id = ? AND p.status <> "cancelled"', [$me['id'], $sem['id'] ?? 0]);
    if ($has) { flash('info', 'คุณมีโครงงานในภาคเรียนนี้แล้ว'); redirect('app/project.php?id=' . $has); }
}

$students = $section ? q_all('SELECT id, prefix, full_name, student_code FROM users WHERE role = "student" AND active = 1 AND classroom_id = ? ORDER BY student_code', [$section['classroom_id']]) : [];
// นักเรียนที่อยู่ในโครงงานอื่นของภาคเรียนเดียวกันแล้ว
$taken = [];
if ($section) {
    foreach (q_all('SELECT m.student_id, p.title FROM project_members m JOIN projects p ON p.id = m.project_id JOIN sections s ON s.id = p.section_id
                    WHERE s.semester_id = ? AND p.status <> "cancelled" AND p.id <> ?', [$section['semester_id'], $id]) as $r) {
        $taken[(int)$r['student_id']] = $r['title'];
    }
}
$teachers = q_all('SELECT id, prefix, full_name FROM users WHERE role = "teacher" AND active = 1 ORDER BY full_name');
$currentMembers = $project ? project_members($id) : [];
$memberIds = array_map('intval', array_column($currentMembers, 'id'));
$leaderId = 0;
foreach ($currentMembers as $m) { if ($m['is_leader']) { $leaderId = (int)$m['id']; } }

$errors = [];
if (is_post() && $section) {
    csrf_check();
    $data = [
        'title' => input('title'), 'title_en' => input('title_en'), 'category' => input('category'),
        'objective' => input('objective'), 'scope' => input('scope'), 'expected' => input('expected'),
        'advisor_id' => int_param('advisor_id') ?: (int)$section['teacher_id'],
    ];
    $members = array_values(array_unique(array_map('intval', (array)($_POST['members'] ?? []))));
    if ($me['role'] === 'student' && !in_array((int)$me['id'], $members, true)) { $members[] = (int)$me['id']; }
    $leader = int_param('leader') ?: ($me['role'] === 'student' ? (int)$me['id'] : ($members[0] ?? 0));
    $validIds = array_map('intval', array_column($students, 'id'));

    if ($data['title'] === '') { $errors[] = 'กรุณาระบุชื่อโครงงาน'; }
    if (!$members) { $errors[] = 'กรุณาเลือกสมาชิกอย่างน้อย 1 คน'; }
    foreach ($members as $sid) {
        if (!in_array($sid, $validIds, true)) { $errors[] = 'มีสมาชิกที่ไม่ได้อยู่ในกลุ่มเรียนนี้'; break; }
        if (isset($taken[$sid])) { $errors[] = 'สมาชิกบางคนอยู่ในโครงงาน "' . $taken[$sid] . '" แล้ว'; break; }
    }
    if (!in_array($leader, $members, true)) { $leader = $members[0] ?? 0; }

    if (!$errors) {
        db()->beginTransaction();
        if ($project) {
            $newStatus = ($me['role'] === 'student' && $project['status'] === 'revise') ? 'proposed' : $project['status'];
            q('UPDATE projects SET title=?, title_en=?, category=?, objective=?, scope=?, expected=?, advisor_id=?, status=? WHERE id=?',
                [...array_values($data), $newStatus, $id]);
            q('DELETE FROM project_members WHERE project_id = ?', [$id]);
            log_activity("แก้ไขโครงงาน #$id {$data['title']}");
        } else {
            $status = $me['role'] === 'student' ? 'proposed' : 'approved';
            q('INSERT INTO projects (section_id, title, title_en, category, objective, scope, expected, advisor_id, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$sectionId, ...array_values($data), $status, $me['id']]);
            $id = (int)db()->lastInsertId();
            log_activity(($me['role'] === 'student' ? 'เสนอหัวข้อโครงงาน' : 'เพิ่มโครงงาน') . " #$id {$data['title']}");
        }
        foreach ($members as $sid) {
            q('INSERT INTO project_members (project_id, student_id, is_leader) VALUES (?,?,?)', [$id, $sid, (int)($sid === $leader)]);
        }
        db()->commit();
        flash('success', 'บันทึกโครงงานเรียบร้อย');
        redirect('app/project.php?id=' . $id);
    }
    $project = array_merge($project ?? [], $data);
    $memberIds = $members;
    $leaderId = $leader;
}

$v = fn(string $k) => e((string)($project[$k] ?? ''));
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1><?= e($pageTitle) ?></h1></div>
<?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>

<?php if (!$section): ?>
  <div class="card card-body">
    <?php if (!$sections): ?>
      <div class="text-muted">ยังไม่มีรายวิชาโครงงานใน<?= e(semester_label($sem)) ?> ที่คุณเข้าถึงได้ กรุณาติดต่อผู้ดูแลระบบ</div>
    <?php else: ?>
      <form class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label">เลือกกลุ่มเรียน / รายวิชาโครงงาน</label>
          <select name="section" class="form-select" required><option value="">-- เลือก --</option>
          <?php foreach ($sections as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['code'] . ' ' . $s['classroom_name'] . ' · ' . $s['subject_code'] . ' ' . $s['subject_name'] . ' (' . person($s) . ')') ?></option><?php endforeach; ?>
          </select></div>
        <div class="col-auto"><button class="btn btn-primary">ถัดไป</button></div>
      </form>
    <?php endif; ?>
  </div>
<?php else: ?>
<form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="section" value="<?= $sectionId ?>">
  <div class="col-lg-7"><div class="card"><div class="card-body">
    <div class="mb-2 small text-muted">กลุ่มเรียน <?= e($section['code'] . ' ' . $section['classroom_name']) ?> · <?= e($section['subject_code'] . ' ' . $section['subject_name']) ?></div>
    <div class="mb-3"><label class="form-label">ชื่อโครงงาน (ภาษาไทย) *</label><input class="form-control" name="title" value="<?= $v('title') ?>" required maxlength="255"></div>
    <div class="mb-3"><label class="form-label">ชื่อโครงงาน (ภาษาอังกฤษ)</label><input class="form-control" name="title_en" value="<?= $v('title_en') ?>" maxlength="255"></div>
    <div class="row g-3 mb-3">
      <div class="col-md-6"><label class="form-label">ประเภทโครงงาน</label><select name="category" class="form-select"><?= select_options(PROJECT_CATEGORIES, $project['category'] ?? '', false) ?></select></div>
      <div class="col-md-6"><label class="form-label">ครูที่ปรึกษาโครงงาน</label><select name="advisor_id" class="form-select">
        <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"<?= (int)$t['id'] === (int)($project['advisor_id'] ?? $section['teacher_id']) ? ' selected' : '' ?>><?= e(person($t)) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="mb-3"><label class="form-label">วัตถุประสงค์</label><textarea class="form-control" name="objective" rows="4"><?= $v('objective') ?></textarea></div>
    <div class="mb-3"><label class="form-label">ขอบเขตของโครงงาน</label><textarea class="form-control" name="scope" rows="3"><?= $v('scope') ?></textarea></div>
    <div class="mb-3"><label class="form-label">ผลที่คาดว่าจะได้รับ</label><textarea class="form-control" name="expected" rows="3"><?= $v('expected') ?></textarea></div>
  </div></div></div>
  <div class="col-lg-5"><div class="card">
    <div class="card-header">สมาชิกกลุ่ม (เลือกหัวหน้ากลุ่ม 1 คน)</div>
    <div class="card-body" style="max-height:520px;overflow:auto">
      <?php foreach ($students as $s): $sid = (int)$s['id']; $dis = isset($taken[$sid]); ?>
        <div class="d-flex align-items-center gap-2 py-1 border-bottom">
          <input type="checkbox" class="form-check-input mt-0" name="members[]" value="<?= $sid ?>" id="m<?= $sid ?>"<?= in_array($sid, $memberIds, true) || ($me['role'] === 'student' && $sid === (int)$me['id']) ? ' checked' : '' ?><?= $dis ? ' disabled' : '' ?>>
          <label for="m<?= $sid ?>" class="flex-grow-1<?= $dis ? ' text-muted' : '' ?>"><?= e($s['student_code'] . ' ' . person($s)) ?><?= $dis ? '<div class="small">มีโครงงานแล้ว</div>' : '' ?></label>
          <label class="small text-nowrap"><input type="radio" name="leader" value="<?= $sid ?>"<?= $sid === $leaderId || (!$leaderId && $me['role'] === 'student' && $sid === (int)$me['id']) ? ' checked' : '' ?><?= $dis ? ' disabled' : '' ?>> หัวหน้า</label>
        </div>
      <?php endforeach; ?>
      <?php if (!$students): ?><div class="text-muted">ไม่มีรายชื่อนักเรียนในกลุ่มเรียนนี้</div><?php endif; ?>
    </div>
  </div></div>
  <div class="col-12">
    <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $me['role'] === 'student' && !$id ? 'ส่งหัวข้อให้ครูพิจารณา' : 'บันทึก' ?></button>
    <a href="<?= url($id ? 'app/project.php?id=' . $id : 'app/projects.php') ?>" class="btn btn-light">ยกเลิก</a>
  </div>
</form>
<?php endif; ?>
<?php require APP_ROOT . '/inc/footer.php';
