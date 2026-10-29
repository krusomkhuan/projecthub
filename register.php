<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/student_registration.php';
if (current_user()) { redirect('app/index.php'); }
$semester = registration_semester();
$classrooms = $semester ? registration_classrooms((int)$semester['id']) : [];
$classroomId = int_param('classroom_id');
if (isset($_GET['projects_for'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $cid = (int)$_GET['projects_for'];
    $valid = in_array($cid, array_map('intval', array_column($classrooms, 'id')), true);
    echo json_encode($valid ? registration_projects((int)$semester['id'], $cid) : [], JSON_UNESCAPED_UNICODE);
    exit;
}
$error = '';
if (is_post()) {
    csrf_check();
    try {
        $student = register_student($_POST);
        session_regenerate_id(true);
        $_SESSION['uid'] = $student['id'];
        flash('success', 'สมัครสมาชิกสำเร็จ ใช้รหัสนักศึกษาเป็นชื่อผู้ใช้ ส่งรายงานได้เมื่อหัวข้อโครงงานได้รับอนุมัติแล้ว');
        redirect($student['project_id'] ? 'app/project.php?id=' . $student['project_id'] : 'app/project_form.php');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('ProjectHub student registration failed: ' . $e->getMessage());
        $error = 'สมัครสมาชิกไม่สำเร็จ กรุณาลองใหม่หรือติดต่อครูผู้สอน';
    }
}
$projects = $semester && $classroomId ? registration_projects((int)$semester['id'], $classroomId) : [];
$pageTitle = 'สมัครสมาชิกนักเรียน นักศึกษา';
require __DIR__ . '/inc/public_header.php';
?>
<div class="container py-4" style="max-width:760px">
  <div class="card"><div class="card-body p-4">
    <h1 class="h4 fw-bold">สมัครสมาชิกนักเรียน นักศึกษา</h1>
    <p class="text-muted">เลือกกลุ่มเรียนและกลุ่มโครงงานของตนเอง เพื่อส่งงานตามขั้นตอนของรายวิชา</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$classrooms): ?><div class="alert alert-warning">ยังไม่มีกลุ่มเรียนเปิดรับสมัครในภาคเรียนปัจจุบัน กรุณาติดต่อครูผู้สอน</div><?php else: ?>
    <form method="post"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="student_code">รหัสนักศึกษา / ชื่อผู้ใช้</label><input id="student_code" name="student_code" class="form-control" maxlength="20" value="<?= e(input('student_code')) ?>" autocomplete="username" required></div>
      <div class="row g-3 mb-3"><div class="col-sm-3"><label class="form-label" for="prefix">คำนำหน้า</label><select id="prefix" name="prefix" class="form-select"><?= select_options(['' => 'ไม่ระบุ', 'นาย' => 'นาย', 'นางสาว' => 'นางสาว', 'นาง' => 'นาง'], input('prefix')) ?></select></div>
      <div class="col-sm-9"><label class="form-label" for="full_name">ชื่อ–นามสกุล</label><input id="full_name" name="full_name" class="form-control" maxlength="150" value="<?= e(input('full_name')) ?>" autocomplete="name" required></div></div>
      <div class="mb-3">
        <label class="form-label" for="classroom_search">กลุ่มเรียน · <?= e(semester_label($semester)) ?></label>
        <div class="input-group mb-1">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input id="classroom_search" class="form-control" placeholder="พิมพ์เลขกลุ่ม เช่น 2/1, ชื่อกลุ่ม เช่น ส.คอม หรือรหัสกลุ่มเรียน" autocomplete="off">
        </div>
        <select id="classroom_id" name="classroom_id" class="form-select" size="8" required>
          <?php $level = null; foreach ($classrooms as $c): ?>
            <?php if ($c['level'] !== $level): ?><?= $level !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($c['level'] ?: 'ไม่ระบุระดับ') ?>"><?php $level = $c['level']; endif; ?>
            <option value="<?= $c['id'] ?>"<?= $classroomId === (int)$c['id'] ? ' selected' : '' ?>
              data-search="<?= e(mb_strtolower(preg_replace('/[\s.]+/u', '', $c['code'] . $c['name'] . $c['level'] . $c['major']))) ?>"><?= e($c['name'] . ' · ' . ($c['major'] ?: $c['department']) . ' · รหัส ' . $c['code'] . ($c['has_section'] ? '' : ' (ยังไม่เปิดวิชาโครงงาน)')) ?></option>
          <?php endforeach; ?><?= $level !== null ? '</optgroup>' : '' ?>
        </select>
        <div id="classroom-help" class="form-text">ทั้งหมด <?= count($classrooms) ?> กลุ่มเรียน · พิมพ์เพื่อค้นหา แล้วคลิกเลือกกลุ่มของตนเอง</div>
      </div>
      <div class="mb-3"><label class="form-label" for="project_id">กลุ่มโครงงาน</label><select id="project_id" name="project_id" class="form-select"><option value="0">ยังไม่มีกลุ่ม — เข้าร่วมหรือเสนอหัวข้อหลังสมัคร</option><?php foreach ($projects as $project): ?><option value="<?= $project['id'] ?>"<?= int_param('project_id') === (int)$project['id'] ? ' selected' : '' ?>><?= e($project['title'] . ' · ' . $project['subject_code']) ?></option><?php endforeach; ?></select><div id="project-help" class="form-text">แสดงเฉพาะกลุ่มโครงงานของกลุ่มเรียนที่เลือก การส่งงานเริ่มได้เมื่อครูอนุมัติหัวข้อแล้ว</div></div>
      <div class="row g-3 mb-3"><div class="col-sm-6"><label class="form-label" for="password">ตั้งรหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label><input id="password" type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required></div><div class="col-sm-6"><label class="form-label" for="password_confirm">ยืนยันรหัสผ่าน</label><input id="password_confirm" type="password" name="password_confirm" class="form-control" minlength="8" autocomplete="new-password" required></div></div>
      <button id="register-button" class="btn btn-primary w-100">สมัครสมาชิกและเข้าสู่ระบบ</button>
    </form>
    <?php endif; ?>
    <p class="mt-3 mb-0">มีบัญชีหรือครูนำเข้าข้อมูลไว้แล้ว? <a href="<?= url('login.php') ?>">เข้าสู่ระบบ</a> หากจำรหัสผ่านไม่ได้ กรุณาติดต่อครูหรือผู้ดูแลระบบ</p>
  </div></div>
</div>
<script>
(() => {
  const classroom = document.getElementById('classroom_id');
  if (!classroom) return;
  // ค้นหากลุ่มเรียน: ไม่สนช่องว่างและจุด เช่น "สคอม2/1" ตรงกับ "ส.คอม.2/1"
  const search = document.getElementById('classroom_search');
  const classHelp = document.getElementById('classroom-help');
  const total = classroom.options.length;
  search.addEventListener('input', () => {
    const q = search.value.toLowerCase().replace(/[\s.]+/g, '');
    const visible = [];
    for (const opt of classroom.options) {
      const show = !q || opt.dataset.search.includes(q);
      opt.hidden = !show;
      if (show) visible.push(opt);
    }
    classroom.querySelectorAll('optgroup').forEach(g => { g.hidden = ![...g.children].some(o => !o.hidden); });
    classHelp.textContent = q ? 'พบ ' + visible.length + ' จาก ' + total + ' กลุ่มเรียน' + (visible.length === 1 ? ' — เลือกให้แล้ว' : '') : 'ทั้งหมด ' + total + ' กลุ่มเรียน · พิมพ์เพื่อค้นหา แล้วคลิกเลือกกลุ่มของตนเอง';
    if (visible.length === 1 && classroom.value !== visible[0].value) {
      classroom.value = visible[0].value;
      classroom.dispatchEvent(new Event('change'));
    }
  });
  search.addEventListener('keydown', ev => { if (ev.key === 'Enter') ev.preventDefault(); });
  if (classroom.selectedOptions[0]) classroom.selectedOptions[0].scrollIntoView({ block: 'nearest' });
  const projects = document.getElementById('project_id');
  const button = document.getElementById('register-button');
  const help = document.getElementById('project-help');
  let revision = 0;
  classroom.addEventListener('change', async () => {
    const current = ++revision;
    projects.replaceChildren(new Option('ยังไม่มีกลุ่ม — เข้าร่วมหรือเสนอหัวข้อหลังสมัคร', '0'));
    button.disabled = true;
    help.textContent = 'กำลังโหลดกลุ่มโครงงาน…';
    try {
      const response = await fetch('<?= url('register.php') ?>?projects_for=' + encodeURIComponent(classroom.value));
      if (!response.ok) throw new Error('load');
      const items = await response.json();
      if (current !== revision) return;
      items.forEach(item => projects.add(new Option(item.title + ' · ' + item.subject_code, item.id)));
      help.textContent = items.length ? 'เลือกกลุ่มโครงงานของตนเอง ส่งงานได้เมื่อหัวข้ออนุมัติแล้ว' : 'ยังไม่มีกลุ่มโครงงาน สมัครแล้วเข้าร่วมหรือเสนอหัวข้อภายหลังได้';
    } catch (_) {
      if (current === revision) help.textContent = 'โหลดกลุ่มโครงงานไม่สำเร็จ กรุณาเลือกกลุ่มเรียนใหม่หรือลองภายหลัง';
    } finally {
      if (current === revision) button.disabled = false;
    }
  });
})();
</script>
<?php require __DIR__ . '/inc/public_footer.php';
