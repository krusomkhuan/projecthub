<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
$me = require_login('admin');
$staffRoles = array_diff_key(ROLE_LABELS, ['student' => 1]);

if (is_post()) {
    csrf_check();
    $id = int_param('id');
    $action = input('action');
    if ($action === 'save') {
        $role = input('role');
        $username = input('username');
        if (!isset($staffRoles[$role]) || $username === '' || input('full_name') === '') {
            flash('danger', 'กรุณากรอกชื่อผู้ใช้ ชื่อ-สกุล และบทบาท');
            redirect('app/admin/users.php' . ($id ? '?edit=' . $id : ''));
        }
        if (q_val('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])) {
            flash('danger', 'ชื่อผู้ใช้นี้มีอยู่แล้ว');
            redirect('app/admin/users.php' . ($id ? '?edit=' . $id : ''));
        }
        $data = [$username, input('prefix'), input('full_name'), $role, input('position'), input('department'), input('phone'), input('email'), input('major'), input('education'), input('duty')];
        if ($id) {
            if ($id === (int)$me['id'] && $role !== 'admin') { $data[3] = 'admin'; }
            q('UPDATE users SET username=?, prefix=?, full_name=?, role=?, position=?, department=?, phone=?, email=?, major=?, education=?, duty=? WHERE id=? AND role <> "student"', [...$data, $id]);
            log_activity("แก้ไขผู้ใช้ $username");
        } else {
            $pw = (string)($_POST['password'] ?? '') ?: $username;
            q('INSERT INTO users (username, prefix, full_name, role, position, department, phone, email, major, education, duty, password_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [...$data, password_hash($pw, PASSWORD_DEFAULT)]);
            log_activity("เพิ่มผู้ใช้ $username (" . ROLE_LABELS[$role] . ')');
        }
        flash('success', 'บันทึกข้อมูลผู้ใช้แล้ว');
    } elseif ($action === 'reset' && $id !== (int)$me['id']) {
        $u = q_one('SELECT username FROM users WHERE id = ?', [$id]);
        q('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($u['username'], PASSWORD_DEFAULT), $id]);
        log_activity("รีเซ็ตรหัสผ่าน {$u['username']}");
        flash('success', 'รีเซ็ตรหัสผ่านเป็นชื่อผู้ใช้แล้ว ผู้ใช้ต้องเปลี่ยนรหัสผ่านเมื่อเข้าระบบ');
    } elseif ($action === 'toggle' && $id !== (int)$me['id']) {
        q('UPDATE users SET active = 1 - active WHERE id = ?', [$id]);
        flash('success', 'เปลี่ยนสถานะการใช้งานแล้ว');
    } elseif ($action === 'delete') {
        $ids = $id ? [$id] : array_map('intval', (array)($_POST['ids'] ?? []));
        $deleted = [];
        $blocked = [];
        foreach ($ids as $uid) {
            $u = q_one('SELECT id, username, prefix, full_name, role FROM users WHERE id = ? AND role <> "student"', [$uid]);
            if (!$u) { continue; }
            $name = person($u) . " ({$u['username']})";
            if ($uid === (int)$me['id']) { $blocked[] = "$name: ลบบัญชีของตนเองไม่ได้"; continue; }
            if ($u['role'] === 'admin' && (int)q_val('SELECT COUNT(*) FROM users WHERE role = "admin" AND active = 1') <= 1) { $blocked[] = "$name: ต้องมีผู้ดูแลระบบอย่างน้อย 1 คน"; continue; }
            // ครูที่ยังสอนรายวิชาโครงงานอยู่ลบไม่ได้ เพราะโครงงานและรายงานอ้างถึงครูคนนี้
            $secs = (int)q_val('SELECT COUNT(*) FROM sections WHERE teacher_id = ?', [$uid]);
            if ($secs) { $blocked[] = "$name: ยังเป็นครูผู้สอนรายวิชาโครงงาน $secs กลุ่ม (เปลี่ยนครูผู้สอนก่อน หรือใช้ปุ่มระงับแทน)"; continue; }
            db()->beginTransaction();
            q('UPDATE classrooms SET advisor_id = NULL WHERE advisor_id = ?', [$uid]);
            q('DELETE FROM users WHERE id = ?', [$uid]); // ครูที่ปรึกษาโครงงานถูกตั้งเป็นว่างอัตโนมัติ (ON DELETE SET NULL)
            db()->commit();
            $deleted[] = $name;
        }
        if ($deleted) {
            log_activity('ลบผู้ใช้ ' . count($deleted) . ' คน: ' . implode(', ', array_slice($deleted, 0, 10)));
            flash('success', 'ลบผู้ใช้แล้ว ' . count($deleted) . ' คน');
        }
        if ($blocked) {
            flash('warning', 'ลบไม่ได้ ' . count($blocked) . ' คน — ' . implode(' · ', array_slice($blocked, 0, 10)));
        }
        if (!$deleted && !$blocked) {
            flash('info', 'ไม่ได้เลือกผู้ใช้');
        }
    }
    redirect('app/admin/users.php?role=' . input('return_role'));
}

$roleFilter = input('role');
$kw = input('q');
$params = [];
$where = 'role <> "student"';
if (isset($staffRoles[$roleFilter])) { $where .= ' AND role = ?'; $params[] = $roleFilter; }
if ($kw !== '') { $where .= ' AND (full_name LIKE ? OR username LIKE ? OR department LIKE ?)'; array_push($params, "%$kw%", "%$kw%", "%$kw%"); }
$users = q_all("SELECT u.*, (SELECT COUNT(*) FROM sections s WHERE s.teacher_id = u.id) AS sections FROM users u WHERE $where ORDER BY FIELD(role,'admin','research','academic','strategy','teacher'), full_name", $params);
$edit = int_param('edit') ? q_one('SELECT * FROM users WHERE id = ? AND role <> "student"', [int_param('edit')]) : null;
$pageTitle = 'ครูและบุคลากร';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>ครูและบุคลากร</h1><a href="<?= url('app/admin/import.php?type=teachers') ?>" class="btn btn-outline-success"><i class="bi bi-upload"></i> นำเข้าครูผู้สอน</a></div>
<div class="row g-3">
  <div class="col-lg-8">
    <form class="card card-body mb-3"><div class="row g-2">
      <div class="col-md-4"><select name="role" class="form-select"><option value="">ทุกบทบาท</option><?= select_options($staffRoles, $roleFilter) ?></select></div>
      <div class="col-md"><input name="q" class="form-control" placeholder="ค้นหาชื่อ / ชื่อผู้ใช้ / แผนก" value="<?= e($kw) ?>"></div>
      <div class="col-md-auto"><button class="btn btn-primary">ค้นหา</button></div>
    </div></form>
    <form method="post" id="bulkForm" data-confirm="ลบผู้ใช้ที่เลือกทั้งหมด? การลบไม่สามารถย้อนกลับได้"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"></form>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="small text-muted">ทั้งหมด <?= count($users) ?> คน · เลือกแล้ว <span id="selCount">0</span> คน</span>
      <button form="bulkForm" class="btn btn-sm btn-danger" id="bulkDelete" disabled><i class="bi bi-trash"></i> ลบที่เลือก</button>
    </div>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
      <thead><tr><th style="width:2rem"><input type="checkbox" class="form-check-input" id="selAll" title="เลือกทั้งหมด"></th><th>ชื่อ-สกุล</th><th>ชื่อผู้ใช้</th><th>บทบาท</th><th>แผนก/ฝ่าย</th><th>เข้าระบบล่าสุด</th><th></th></tr></thead><tbody>
      <?php foreach ($users as $u): ?>
        <tr class="<?= $u['active'] ? '' : 'table-secondary' ?>">
          <td><?php if ((int)$u['id'] !== (int)$me['id']): ?><input type="checkbox" class="form-check-input sel" name="ids[]" value="<?= $u['id'] ?>" form="bulkForm"><?php endif; ?></td>
          <td><?= e(person($u)) ?><div class="small text-muted"><?= e(implode(' · ', array_filter([$u['position'], $u['education'], $u['duty'] !== $u['position'] ? $u['duty'] : '']))) ?></div></td>
          <td><?= e($u['username']) ?></td><td class="small"><?= e(ROLE_LABELS[$u['role']]) ?><?= $u['sections'] ? ' <span class="badge text-bg-light border">' . $u['sections'] . ' กลุ่ม</span>' : '' ?></td>
          <td class="small"><?= e($u['department']) ?></td><td class="small"><?= $u['last_login'] ? thai_date($u['last_login'], true) : '<span class="text-muted">ยังไม่เคย</span>' ?></td>
          <td class="text-end text-nowrap">
            <a href="?edit=<?= $u['id'] ?>&role=<?= e($roleFilter) ?>" class="btn btn-sm btn-outline-primary" title="แก้ไข"><i class="bi bi-pencil"></i></a>
            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
            <form method="post" class="d-inline" data-confirm="รีเซ็ตรหัสผ่านเป็นชื่อผู้ใช้?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><button class="btn btn-sm btn-outline-warning" title="รีเซ็ตรหัสผ่าน"><i class="bi bi-key"></i></button></form>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><button class="btn btn-sm btn-outline-<?= $u['active'] ? 'secondary' : 'success' ?>" title="<?= $u['active'] ? 'ระงับการใช้งาน' : 'เปิดใช้งาน' ?>"><i class="bi bi-<?= $u['active'] ? 'pause-circle' : 'play-circle' ?>"></i></button></form>
            <form method="post" class="d-inline" data-confirm="ลบ <?= e(person($u)) ?> ออกจากระบบ? การลบไม่สามารถย้อนกลับได้"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>"><button class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="7" class="text-muted">ไม่พบข้อมูล</td></tr><?php endif; ?>
    </tbody></table></div></div>
  </div>
  <div class="col-lg-4"><div class="card"><div class="card-header"><?= $edit ? 'แก้ไขผู้ใช้' : 'เพิ่มครู / บุคลากร' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>"><input type="hidden" name="return_role" value="<?= e($roleFilter) ?>">
      <div class="mb-2"><label class="form-label small">บทบาท</label><select name="role" class="form-select"><?= select_options($staffRoles, $edit['role'] ?? 'teacher') ?></select></div>
      <div class="row g-2 mb-2">
        <div class="col-4"><label class="form-label small">คำนำหน้า</label><input name="prefix" class="form-control" value="<?= e($edit['prefix'] ?? '') ?>" placeholder="นาย/นาง"></div>
        <div class="col-8"><label class="form-label small">ชื่อ-สกุล *</label><input name="full_name" class="form-control" value="<?= e($edit['full_name'] ?? '') ?>" required></div>
      </div>
      <div class="mb-2"><label class="form-label small">ชื่อผู้ใช้ *</label><input name="username" class="form-control" value="<?= e($edit['username'] ?? '') ?>" required></div>
      <?php if (!$edit): ?><div class="mb-2"><label class="form-label small">รหัสผ่านเริ่มต้น (เว้นว่าง = ใช้ชื่อผู้ใช้)</label><input type="password" name="password" class="form-control" autocomplete="new-password"></div><?php endif; ?>
      <div class="mb-2"><label class="form-label small">ตำแหน่ง</label><input name="position" class="form-control" value="<?= e($edit['position'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label small">แผนกวิชา / ฝ่าย</label><input name="department" class="form-control" list="deptList" value="<?= e($edit['department'] ?? '') ?>">
        <datalist id="deptList"><?php foreach (q_all('SELECT name FROM departments ORDER BY name') as $d): ?><option value="<?= e($d['name']) ?>"><?php endforeach; ?></datalist></div>
      <div class="mb-2"><label class="form-label small">สาขาวิชา</label><input name="major" class="form-control" value="<?= e($edit['major'] ?? '') ?>"></div>
      <div class="row g-2 mb-2">
        <div class="col-5"><label class="form-label small">วุฒิการศึกษา</label><input name="education" class="form-control" value="<?= e($edit['education'] ?? '') ?>"></div>
        <div class="col-7"><label class="form-label small">หน้าที่พิเศษ</label><input name="duty" class="form-control" value="<?= e($edit['duty'] ?? '') ?>"></div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6"><label class="form-label small">โทรศัพท์</label><input name="phone" class="form-control" value="<?= e($edit['phone'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label small">อีเมล</label><input name="email" type="email" class="form-control" value="<?= e($edit['email'] ?? '') ?>"></div>
      </div>
      <button class="btn btn-primary w-100">บันทึก</button>
      <?php if ($edit): ?><a href="?" class="btn btn-light w-100 mt-2">ยกเลิก</a><?php endif; ?>
    </form>
  </div></div></div>
</div>
<?php
$pageScripts = <<<'JS'
<script>
(function () {
  var all = document.getElementById('selAll'), boxes = document.querySelectorAll('.sel'),
      btn = document.getElementById('bulkDelete'), cnt = document.getElementById('selCount');
  function update() {
    var n = document.querySelectorAll('.sel:checked').length;
    cnt.textContent = n; btn.disabled = n === 0;
    all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length;
  }
  all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); update(); });
  boxes.forEach(function (b) { b.addEventListener('change', update); });
})();
</script>
JS;
require APP_ROOT . '/inc/footer.php';
