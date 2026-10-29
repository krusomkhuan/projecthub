<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');

$id = int_param('id');
if (is_post()) {
    csrf_check();
    switch (input('action')) {
        case 'save_dept':
            $name = input('name');
            if ($name === '') { flash('danger', 'กรุณาระบุชื่อแผนกวิชา'); break; }
            if (q_val('SELECT id FROM departments WHERE name = ? AND id <> ?', [$name, $id])) { flash('danger', 'มีแผนกวิชานี้อยู่แล้ว'); break; }
            if ($id) {
                $old = (string)q_val('SELECT name FROM departments WHERE id = ?', [$id]);
                db()->beginTransaction();
                q('UPDATE departments SET name = ?, code = ? WHERE id = ?', [$name, input('code'), $id]);
                // กลุ่มเรียนและบุคลากรเก็บชื่อแผนกเป็นข้อความ จึงเปลี่ยนชื่อตามไปด้วย
                q('UPDATE classrooms SET department = ? WHERE department = ?', [$name, $old]);
                q('UPDATE users SET department = ? WHERE department = ?', [$name, $old]);
                db()->commit();
            } else {
                q('INSERT INTO departments (name, code) VALUES (?, ?)', [$name, input('code')]);
                $id = (int)db()->lastInsertId();
            }
            log_activity("บันทึกแผนกวิชา $name");
            flash('success', 'บันทึกแผนกวิชาแล้ว');
            break;
        case 'delete_dept':
            $name = (string)q_val('SELECT name FROM departments WHERE id = ?', [$id]);
            if (q_val('SELECT COUNT(*) FROM classrooms WHERE department = ?', [$name])) {
                flash('danger', 'ลบไม่ได้ เนื่องจากมีกลุ่มเรียนในแผนกวิชานี้');
                break;
            }
            q('DELETE FROM departments WHERE id = ?', [$id]);
            log_activity("ลบแผนกวิชา $name");
            flash('success', 'ลบแผนกวิชาแล้ว');
            redirect('app/admin/departments.php');
        case 'save_major':
            $name = input('major_name');
            $mid = int_param('major_id');
            if ($name === '') { flash('danger', 'กรุณาระบุชื่อสาขาวิชา'); break; }
            if (q_val('SELECT id FROM majors WHERE department_id = ? AND name = ? AND id <> ?', [$id, $name, $mid])) { flash('danger', 'มีสาขาวิชานี้ในแผนกแล้ว'); break; }
            if ($mid) {
                q('UPDATE majors SET name = ? WHERE id = ? AND department_id = ?', [$name, $mid, $id]);
            } else {
                q('INSERT INTO majors (department_id, name) VALUES (?, ?)', [$id, $name]);
            }
            flash('success', 'บันทึกสาขาวิชาแล้ว');
            break;
        case 'delete_major':
            q('DELETE FROM majors WHERE id = ? AND department_id = ?', [int_param('major_id'), $id]);
            flash('success', 'ลบสาขาวิชาแล้ว');
            break;
    }
    redirect('app/admin/departments.php' . ($id ? '?id=' . $id : ''));
}

$departments = q_all('SELECT d.*, (SELECT COUNT(*) FROM majors m WHERE m.department_id = d.id) AS majors,
        (SELECT COUNT(*) FROM classrooms c WHERE c.department = d.name) AS classrooms
    FROM departments d ORDER BY d.name');
$cur = $id ? q_one('SELECT * FROM departments WHERE id = ?', [$id]) : null;
$majors = $cur ? q_all('SELECT * FROM majors WHERE department_id = ? ORDER BY name', [$id]) : [];
$editMajor = int_param('major');
$pageTitle = 'สาขาวิชา / แผนกวิชา';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>สาขาวิชา / แผนกวิชา</h1>
  <div class="d-flex gap-2">
    <a href="<?= url('app/admin/import.php?type=departments') ?>" class="btn btn-outline-success"><i class="bi bi-upload"></i> นำเข้าแผนกวิชา</a>
    <a href="<?= url('app/admin/import.php?type=majors') ?>" class="btn btn-outline-success"><i class="bi bi-upload"></i> นำเข้าสาขาวิชา</a>
  </div>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card mb-3"><div class="card-header">แผนกวิชา (<?= count($departments) ?>)</div>
      <div class="list-group list-group-flush" style="max-height:520px;overflow:auto">
        <?php foreach ($departments as $d): ?>
          <a href="?id=<?= $d['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= (int)$d['id'] === $id ? ' active' : '' ?>">
            <span><?= e($d['name']) ?><?= $d['code'] ? ' <span class="small opacity-75">(' . e($d['code']) . ')</span>' : '' ?></span>
            <span class="text-nowrap"><span class="badge text-bg-light border"><?= $d['majors'] ?> สาขา</span> <span class="badge text-bg-light border"><?= $d['classrooms'] ?> กลุ่ม</span></span>
          </a>
        <?php endforeach; ?>
        <?php if (!$departments): ?><div class="list-group-item text-muted">ยังไม่มีแผนกวิชา — เพิ่มด้านล่าง หรือนำเข้าจากไฟล์ Excel</div><?php endif; ?>
      </div>
    </div>
    <div class="card"><div class="card-header"><?= $cur ? 'แก้ไขแผนกวิชา' : 'เพิ่มแผนกวิชา' ?></div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_dept"><input type="hidden" name="id" value="<?= $id ?: '' ?>">
        <div class="row g-2 mb-3">
          <div class="col-8"><label class="form-label small">ชื่อแผนกวิชา *</label><input name="name" class="form-control" value="<?= e($cur['name'] ?? '') ?>" required></div>
          <div class="col-4"><label class="form-label small">รหัส</label><input name="code" class="form-control" value="<?= e($cur['code'] ?? '') ?>"></div>
        </div>
        <button class="btn btn-primary w-100">บันทึก</button>
        <?php if ($cur): ?><a href="?" class="btn btn-light w-100 mt-2">+ เพิ่มแผนกวิชาใหม่</a><?php endif; ?>
      </form>
      <?php if ($cur): ?><form method="post" class="text-end mt-2" data-confirm="ลบแผนกวิชานี้ รวมถึงสาขาวิชาทั้งหมดในแผนก?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_dept"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-link btn-sm text-danger">ลบแผนกวิชา</button></form><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
  <?php if ($cur): ?>
    <div class="card"><div class="card-header">สาขาวิชาในแผนก<?= e($cur['name']) ?> (<?= count($majors) ?>)</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($majors as $m): ?>
          <li class="list-group-item">
            <?php if ($editMajor === (int)$m['id']): ?>
              <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_major"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="major_id" value="<?= $m['id'] ?>">
                <input name="major_name" class="form-control form-control-sm" value="<?= e($m['name']) ?>" required><button class="btn btn-sm btn-primary text-nowrap">บันทึก</button><a href="?id=<?= $id ?>" class="btn btn-sm btn-light">ยกเลิก</a></form>
            <?php else: ?>
              <div class="d-flex justify-content-between align-items-center"><span><i class="bi bi-diagram-2 text-muted"></i> <?= e($m['name']) ?></span>
                <span class="text-nowrap"><a href="?id=<?= $id ?>&major=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                  <form method="post" class="d-inline" data-confirm="ลบสาขาวิชานี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_major"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="major_id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></span></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php if (!$majors): ?><li class="list-group-item text-muted">ยังไม่มีสาขาวิชา</li><?php endif; ?>
      </ul>
      <div class="card-body border-top">
        <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_major"><input type="hidden" name="id" value="<?= $id ?>">
          <input name="major_name" class="form-control" placeholder="ชื่อสาขาวิชาใหม่" required><button class="btn btn-primary text-nowrap"><i class="bi bi-plus"></i> เพิ่มสาขาวิชา</button></form>
      </div>
    </div>
  <?php else: ?>
    <div class="card card-body text-muted">เลือกแผนกวิชาทางซ้ายเพื่อดูและจัดการสาขาวิชา</div>
  <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
