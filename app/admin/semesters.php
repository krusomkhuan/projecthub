<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');

$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));
if (is_post()) {
    csrf_check();
    $nd = fn(string $k) => input($k) === '' ? null : input($k);
    switch (input('action')) {
        case 'add_semester':
            try {
                q('INSERT INTO semesters (term, academic_year, start_date, end_date) VALUES (?,?,?,?)', [int_param('term'), int_param('academic_year'), $nd('start_date'), $nd('end_date')]);
                $newId = (int)db()->lastInsertId();
                if (isset($_POST['copy_periods']) && $semId) {
                    q('INSERT INTO report_periods (semester_id, seq, title, description, max_score) SELECT ?, seq, title, description, max_score FROM report_periods WHERE semester_id = ?', [$newId, $semId]);
                }
                log_activity('เพิ่มภาคเรียน ' . int_param('term') . '/' . int_param('academic_year'));
                flash('success', 'เพิ่มภาคเรียนแล้ว');
                redirect('app/admin/semesters.php?semester=' . $newId);
            } catch (PDOException $ex) {
                flash('danger', 'มีภาคเรียนนี้อยู่แล้ว');
            }
            break;
        case 'update_semester':
            q('UPDATE semesters SET start_date = ?, end_date = ?, is_closed = ? WHERE id = ?', [$nd('start_date'), $nd('end_date'), (int)isset($_POST['is_closed']), $semId]);
            if (isset($_POST['is_active'])) {
                q('UPDATE semesters SET is_active = (id = ?)', [$semId]);
            }
            flash('success', 'บันทึกภาคเรียนแล้ว');
            break;
        case 'delete_semester':
            if (q_val('SELECT COUNT(*) FROM sections WHERE semester_id = ?', [$semId])) {
                flash('danger', 'ไม่สามารถลบได้ เนื่องจากมีรายวิชาโครงงานในภาคเรียนนี้');
            } else {
                q('DELETE FROM semesters WHERE id = ?', [$semId]);
                flash('success', 'ลบภาคเรียนแล้ว');
                redirect('app/admin/semesters.php');
            }
            break;
        case 'save_period':
            $data = [int_param('seq', 1), input('title'), input('description'), $nd('open_date'), $nd('due_date'), (float)input('max_score', '10')];
            if ($data[1] === '') { flash('danger', 'กรุณาระบุชื่อรอบการรายงาน'); break; }
            if ($pid = int_param('period_id')) {
                q('UPDATE report_periods SET seq=?, title=?, description=?, open_date=?, due_date=?, max_score=? WHERE id=? AND semester_id=?', [...$data, $pid, $semId]);
            } else {
                q('INSERT INTO report_periods (seq, title, description, open_date, due_date, max_score, semester_id) VALUES (?,?,?,?,?,?,?)', [...$data, $semId]);
            }
            log_activity('บันทึกกำหนดการรายงาน: ' . $data[1]);
            flash('success', 'บันทึกกำหนดการรายงานแล้ว');
            break;
        case 'delete_period':
            q('DELETE FROM report_periods WHERE id = ? AND semester_id = ?', [int_param('period_id'), $semId]);
            flash('success', 'ลบรอบการรายงานแล้ว');
            break;
    }
    redirect('app/admin/semesters.php?semester=' . $semId);
}

$semesters = all_semesters();
$cur = q_one('SELECT * FROM semesters WHERE id = ?', [$semId]);
$periods = $cur ? q_all('SELECT rp.*, (SELECT COUNT(*) FROM progress_reports r WHERE r.period_id = rp.id) AS sent FROM report_periods rp WHERE semester_id = ? ORDER BY seq, id', [$semId]) : [];
$edit = int_param('edit') ? q_one('SELECT * FROM report_periods WHERE id = ?', [int_param('edit')]) : null;
$pageTitle = 'ภาคเรียนและกำหนดการรายงาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>ภาคเรียนและกำหนดการรายงานความก้าวหน้า</h1></div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">ภาคเรียน</div>
      <div class="list-group list-group-flush">
        <?php foreach ($semesters as $s): ?>
          <a href="?semester=<?= $s['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between<?= (int)$s['id'] === $semId ? ' active' : '' ?>"><?= e(semester_label($s)) ?>
            <span><?= $s['is_active'] ? '<span class="badge text-bg-success">ใช้งาน</span>' : '' ?><?= $s['is_closed'] ? ' <span class="badge text-bg-secondary">ปิดภาค</span>' : '' ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card"><div class="card-header">เพิ่มภาคเรียน</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add_semester"><input type="hidden" name="semester" value="<?= $semId ?>">
        <div class="row g-2 mb-2">
          <div class="col-5"><label class="form-label small">ภาคเรียนที่</label><select name="term" class="form-select"><option>1</option><option>2</option><option>3</option></select></div>
          <div class="col-7"><label class="form-label small">ปีการศึกษา</label><input type="number" name="academic_year" class="form-control" value="<?= date('Y') + 543 ?>" required></div>
          <div class="col-6"><label class="form-label small">เริ่ม</label><input type="date" name="start_date" class="form-control"></div>
          <div class="col-6"><label class="form-label small">สิ้นสุด</label><input type="date" name="end_date" class="form-control"></div>
        </div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="copy_periods" id="cpp" checked><label class="form-check-label small" for="cpp">คัดลอกรอบการรายงานจากภาคเรียนที่เลือกอยู่</label></div>
        <button class="btn btn-primary w-100">เพิ่มภาคเรียน</button>
      </form>
    </div></div>
  </div>

  <div class="col-lg-8">
  <?php if ($cur): ?>
    <div class="card mb-3"><div class="card-header"><?= e(semester_label($cur)) ?></div><div class="card-body">
      <form method="post" class="row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="action" value="update_semester"><input type="hidden" name="semester" value="<?= $semId ?>">
        <div class="col-md-3"><label class="form-label small">วันเริ่มภาคเรียน</label><input type="date" name="start_date" class="form-control" value="<?= e($cur['start_date']) ?>"></div>
        <div class="col-md-3"><label class="form-label small">วันสิ้นภาคเรียน</label><input type="date" name="end_date" class="form-control" value="<?= e($cur['end_date']) ?>"></div>
        <div class="col-md-4">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="ia"<?= $cur['is_active'] ? ' checked' : '' ?>><label class="form-check-label" for="ia">เป็นภาคเรียนปัจจุบัน</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="is_closed" id="ic"<?= $cur['is_closed'] ? ' checked' : '' ?>><label class="form-check-label" for="ic">ปิดภาคเรียน (สิ้นเทอม)</label></div>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">บันทึก</button></div>
      </form>
      <form method="post" class="mt-2 text-end" data-confirm="ลบภาคเรียนนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_semester"><input type="hidden" name="semester" value="<?= $semId ?>"><button class="btn btn-link btn-sm text-danger">ลบภาคเรียน</button></form>
    </div></div>

    <div class="card mb-3">
      <div class="card-header">รอบการรายงานความก้าวหน้า (รายงานเป็นระยะ)</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>#</th><th>รอบการรายงาน</th><th>เปิดส่ง</th><th>กำหนดส่ง</th><th>คะแนนเต็ม</th><th>ส่งแล้ว</th><th></th></tr></thead><tbody>
        <?php foreach ($periods as $pr): ?>
          <tr><td><?= $pr['seq'] ?></td><td><?= e($pr['title']) ?><?php if ($pr['description']): ?><div class="small text-muted text-truncate" style="max-width:280px"><?= e($pr['description']) ?></div><?php endif; ?></td>
            <td><?= thai_date($pr['open_date']) ?></td><td><?= thai_date($pr['due_date']) ?></td><td><?= e($pr['max_score']) ?></td><td><?= $pr['sent'] ?></td>
            <td class="text-end text-nowrap"><a href="?semester=<?= $semId ?>&edit=<?= $pr['id'] ?>#pform" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" data-confirm="ลบรอบการรายงานนี้ (รายงานที่ส่งแล้วจะถูกลบด้วย)?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_period"><input type="hidden" name="semester" value="<?= $semId ?>"><input type="hidden" name="period_id" value="<?= $pr['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$periods): ?><tr><td colspan="7" class="text-muted">ยังไม่มีรอบการรายงาน</td></tr><?php endif; ?>
      </tbody></table></div>
    </div>

    <div class="card" id="pform"><div class="card-header"><?= $edit ? 'แก้ไขรอบการรายงาน' : 'เพิ่มรอบการรายงาน' ?></div><div class="card-body">
      <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="save_period"><input type="hidden" name="semester" value="<?= $semId ?>"><input type="hidden" name="period_id" value="<?= $edit['id'] ?? '' ?>">
        <div class="col-md-2"><label class="form-label small">ลำดับ</label><input type="number" name="seq" class="form-control" value="<?= e((string)($edit['seq'] ?? count($periods) + 1)) ?>"></div>
        <div class="col-md-10"><label class="form-label small">ชื่อรอบการรายงาน</label><input name="title" class="form-control" value="<?= e($edit['title'] ?? '') ?>" required></div>
        <div class="col-12"><label class="form-label small">รายละเอียด / สิ่งที่ต้องส่ง</label><textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="col-md-4"><label class="form-label small">วันเปิดส่ง</label><input type="date" name="open_date" class="form-control" value="<?= e($edit['open_date'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label small">วันกำหนดส่ง</label><input type="date" name="due_date" class="form-control" value="<?= e($edit['due_date'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label small">คะแนนเต็ม</label><input type="number" step="0.5" name="max_score" class="form-control" value="<?= e((string)($edit['max_score'] ?? 10)) ?>"></div>
        <div class="col-12"><button class="btn btn-primary">บันทึก</button> <?php if ($edit): ?><a href="?semester=<?= $semId ?>" class="btn btn-light">ยกเลิก</a><?php endif; ?></div>
      </form>
    </div></div>
  <?php else: ?>
    <div class="card card-body text-muted">กรุณาเพิ่มภาคเรียน</div>
  <?php endif; ?>
  </div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
