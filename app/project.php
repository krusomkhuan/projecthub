<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login();

$id = int_param('id');
$p = find_project($id);
$access = $p ? project_access($p) : 'none';
if ($access === 'none') { http_response_code(404); exit('ไม่พบโครงงาน หรือคุณไม่มีสิทธิ์เข้าถึง'); }
$canTeach = $access === 'teacher' || is_role('admin');

if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'status' && $canTeach) {
        $st = input('status');
        if (isset(PROJECT_STATUS[$st])) {
            q('UPDATE projects SET status = ?, status_note = ? WHERE id = ?', [$st, input('note'), $id]);
            log_activity("เปลี่ยนสถานะโครงงาน #$id เป็น " . PROJECT_STATUS[$st][0]);
            flash('success', 'อัปเดตสถานะโครงงานแล้ว');
        }
    } elseif ($action === 'review' && $canTeach) {
        $rid = int_param('report_id');
        $st = input('status');
        $score = input('score') === '' ? null : (float)input('score');
        if (isset(REPORT_STATUS[$st])) {
            q('UPDATE progress_reports SET status = ?, score = ?, teacher_comment = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND project_id = ?',
                [$st, $score, input('comment'), $me['id'], $rid, $id]);
            log_activity("ตรวจรายงานความก้าวหน้า โครงงาน #$id: " . REPORT_STATUS[$st][0]);
            flash('success', 'บันทึกผลการตรวจรายงานแล้ว');
        }
    } elseif ($action === 'final' && $canTeach) {
        $score = input('final_score') === '' ? null : max(0, min(100, (float)input('final_score')));
        $grade = input('final_grade');
        $grade = in_array($grade, GRADE_OPTIONS, true) ? $grade : grade_from_score($score);
        $completed = isset($_POST['completed']);
        q('UPDATE projects SET final_score = ?, final_grade = ?, final_comment = ?, progress = ?, is_public = ?, status = ? WHERE id = ?',
            [$score, $grade ?: null, input('final_comment'), max(0, min(100, int_param('progress'))), (int)isset($_POST['is_public']),
             $completed ? 'completed' : ($p['status'] === 'completed' ? 'approved' : $p['status']), $id]);
        log_activity("ประเมินผลปลายภาค โครงงาน #$id คะแนน " . ($score ?? '-'));
        flash('success', 'บันทึกผลการประเมินแล้ว');
    } elseif ($action === 'oversight' && is_overseer()) {
        $res = input('result');
        if (isset(REVIEW_RESULT[$res])) {
            q('INSERT INTO oversight_reviews (project_id, reviewer_id, unit, result, comment) VALUES (?,?,?,?,?)', [$id, $me['id'], $me['role'], $res, input('comment')]);
            log_activity("บันทึกผลตรวจติดตามโครงงาน #$id (" . ROLE_LABELS[$me['role']] . ')');
            flash('success', 'บันทึกผลการตรวจติดตามแล้ว');
        }
    } elseif ($action === 'delete_review' && is_overseer()) {
        q('DELETE FROM oversight_reviews WHERE id = ? AND project_id = ? AND (reviewer_id = ? OR ? = "admin")', [int_param('review_id'), $id, $me['id'], $me['role']]);
        flash('success', 'ลบผลการตรวจแล้ว');
    } elseif ($action === 'delete' && $canTeach) {
        q('DELETE FROM projects WHERE id = ?', [$id]);
        log_activity("ลบโครงงาน #$id {$p['title']}");
        flash('success', 'ลบโครงงานแล้ว');
        redirect('app/projects.php');
    }
    redirect('app/project.php?id=' . $id);
}

$members = project_members($id);
$periods = project_periods($id, (int)$p['semester_id']);
$reviews = q_all('SELECT o.*, u.prefix, u.full_name FROM oversight_reviews o JOIN users u ON u.id = o.reviewer_id WHERE o.project_id = ? ORDER BY o.created_at DESC', [$id]);
$sem = q_one('SELECT * FROM semesters WHERE id = ?', [$p['semester_id']]);
$totalScore = 0.0; $totalMax = 0.0;
foreach ($periods as $pr) { $totalMax += (float)$pr['max_score']; $totalScore += (float)($pr['report']['score'] ?? 0); }
$pageTitle = $p['title'];
require APP_ROOT . '/inc/header.php';
?>
<div class="print-only text-center mb-3"><h4>แบบสรุปโครงงาน รายวิชาโครงงาน · <?= e(college_name()) ?></h4><div><?= e(semester_label($sem)) ?></div></div>
<div class="page-head">
  <div>
    <h1><?= e($p['title']) ?></h1>
    <?php if ($p['title_en']): ?><div class="text-muted"><?= e($p['title_en']) ?></div><?php endif; ?>
    <div class="mt-1"><?= badge(PROJECT_STATUS, $p['status']) ?> <span class="badge text-bg-light border"><?= e($p['category'] ?: 'ไม่ระบุประเภท') ?></span> <span class="small text-muted"><?= e(semester_label($sem)) ?></span></div>
  </div>
  <div class="d-flex gap-2 no-print">
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
    <?php if ($canTeach || ($access === 'member' && in_array($p['status'], ['proposed', 'revise'], true))): ?>
      <a class="btn btn-outline-primary" href="<?= url('app/project_form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> แก้ไข</a>
    <?php endif; ?>
    <?php if ($canTeach): ?>
      <form method="post" data-confirm="ยืนยันการลบโครงงานนี้ รวมถึงรายงานทั้งหมด?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
    <?php endif; ?>
  </div>
</div>

<?php if ($p['status_note'] && in_array($p['status'], ['revise', 'cancelled'], true)): ?><div class="alert alert-danger"><strong>หมายเหตุจากครู:</strong> <?= e($p['status_note']) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
      <div class="row small mb-3">
        <div class="col-sm-6"><span class="text-muted">กลุ่มเรียน:</span> <?= e($p['classroom_name']) ?> (<?= e($p['level']) ?>)</div>
        <div class="col-sm-6"><span class="text-muted">แผนกวิชา:</span> <?= e($p['department'] ?: '-') ?></div>
        <div class="col-sm-6"><span class="text-muted">รายวิชา:</span> <?= e($p['subject_code'] . ' ' . $p['subject_name']) ?></div>
        <div class="col-sm-6"><span class="text-muted">ครูผู้สอน:</span> <?= e(person($p, 't_')) ?></div>
        <div class="col-sm-6"><span class="text-muted">ครูที่ปรึกษา:</span> <?= e(person($p, 'a_') ?: '-') ?></div>
        <div class="col-sm-6"><span class="text-muted">ความก้าวหน้า:</span> <?= (int)$p['progress'] ?>%</div>
      </div>
      <?php foreach (['objective' => 'วัตถุประสงค์', 'scope' => 'ขอบเขตของโครงงาน', 'expected' => 'ผลที่คาดว่าจะได้รับ'] as $k => $lbl): if (!$p[$k]) continue; ?>
        <h6 class="fw-semibold mt-3"><?= $lbl ?></h6><div class="prewrap"><?= e($p[$k]) ?></div>
      <?php endforeach; ?>
    </div></div>

    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between"><span>รายงานความก้าวหน้าตามระยะ</span><span class="small text-muted">คะแนนรวม <?= $totalScore ?>/<?= $totalMax ?></span></div>
      <div class="card-body"><div class="timeline">
      <?php foreach ($periods as $pr): $r = $pr['report']; [$lbl, $col] = period_state($pr); ?>
        <div class="timeline-item <?= $r ? ['approved' => 'done', 'submitted' => 'wait', 'revise' => 'fix'][$r['status']] : '' ?>">
          <div class="d-flex flex-wrap justify-content-between gap-2">
            <div><strong><?= e($pr['title']) ?></strong> <span class="badge text-bg-<?= $col ?><?= $col === 'light' ? ' border' : '' ?>"><?= $lbl ?></span>
              <div class="small text-muted">กำหนดส่ง <?= thai_date($pr['due_date']) ?><?= $r ? ' · ส่งเมื่อ ' . thai_date($r['submitted_at'], true) : '' ?></div></div>
            <?php if ($access === 'member' && $p['status'] === 'approved' && (!$r || $r['status'] !== 'approved')): ?>
              <a class="btn btn-sm btn-primary no-print" href="<?= url('app/report_submit.php?project=' . $id . '&period=' . $pr['id']) ?>"><?= $r ? 'แก้ไข/ส่งใหม่' : 'ส่งรายงาน' ?></a>
            <?php endif; ?>
          </div>
          <?php if ($r): ?>
            <div class="border rounded p-2 mt-2 bg-light-subtle small">
              <div class="prewrap"><?= e($r['summary']) ?></div>
              <?php if ($r['problems']): ?><div class="mt-2"><span class="fw-semibold">ปัญหา/อุปสรรค:</span> <span class="prewrap"><?= e($r['problems']) ?></span></div><?php endif; ?>
              <?php if ($r['next_plan']): ?><div class="mt-1"><span class="fw-semibold">แผนงานต่อไป:</span> <span class="prewrap"><?= e($r['next_plan']) ?></span></div><?php endif; ?>
              <div class="mt-2 d-flex flex-wrap gap-3">
                <span>ความก้าวหน้า <?= (int)$r['progress'] ?>%</span>
                <?php if ($r['file_path']): ?><a href="<?= url('app/download.php?id=' . $r['id']) ?>"><i class="bi bi-paperclip"></i> <?= e($r['file_name']) ?></a><?php endif; ?>
                <?php if ($r['link_url']): ?><a href="<?= e($r['link_url']) ?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> ลิงก์ผลงาน</a><?php endif; ?>
              </div>
              <?php if ($r['reviewed_at']): ?>
                <div class="mt-2 p-2 rounded bg-white border"><span class="fw-semibold">ผลการตรวจ:</span> <?= badge(REPORT_STATUS, $r['status']) ?> คะแนน <?= e($r['score'] ?? '-') ?>/<?= e($pr['max_score']) ?>
                  <?php if ($r['teacher_comment']): ?><div class="prewrap"><?= e($r['teacher_comment']) ?></div><?php endif; ?></div>
              <?php endif; ?>
              <?php if ($canTeach): ?>
                <form method="post" class="row g-2 mt-2 no-print"><?= csrf_field() ?><input type="hidden" name="action" value="review"><input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                  <div class="col-sm-3"><select name="status" class="form-select form-select-sm"><?= select_options(REPORT_STATUS, $r['status'] === 'submitted' ? 'approved' : $r['status']) ?></select></div>
                  <div class="col-sm-2"><input type="number" step="0.5" min="0" max="<?= e($pr['max_score']) ?>" name="score" class="form-control form-control-sm" placeholder="คะแนน" value="<?= e($r['score']) ?>"></div>
                  <div class="col-sm-5"><input name="comment" class="form-control form-control-sm" placeholder="ข้อเสนอแนะ" value="<?= e($r['teacher_comment']) ?>"></div>
                  <div class="col-sm-2"><button class="btn btn-sm btn-success w-100">บันทึกผล</button></div>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$periods): ?><div class="text-muted">ยังไม่ได้กำหนดรอบการรายงานของภาคเรียนนี้</div><?php endif; ?>
      </div></div>
    </div>

    <div class="card mb-3">
      <div class="card-header">ผลการตรวจติดตาม (ฝ่ายวิชาการ / ฝ่ายยุทธศาสตร์และแผนงาน / งานวิจัยฯ)</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($reviews as $rv): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between gap-2"><div><?= badge(REVIEW_RESULT, $rv['result']) ?> <strong><?= e(ROLE_LABELS[$rv['unit']]) ?></strong> · <?= e(person($rv)) ?></div>
              <div class="small text-muted text-nowrap"><?= thai_date($rv['created_at'], true) ?>
                <?php if (is_overseer() && ((int)$rv['reviewer_id'] === (int)$me['id'] || is_role('admin'))): ?>
                  <form method="post" class="d-inline no-print" data-confirm="ลบผลการตรวจนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_review"><input type="hidden" name="review_id" value="<?= $rv['id'] ?>"><button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-circle"></i></button></form>
                <?php endif; ?></div></div>
            <?php if ($rv['comment']): ?><div class="prewrap small mt-1"><?= e($rv['comment']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
        <?php if (!$reviews): ?><li class="list-group-item text-muted">ยังไม่มีการตรวจติดตาม</li><?php endif; ?>
      </ul>
      <?php if (is_overseer()): ?>
        <div class="card-body border-top no-print">
          <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="oversight">
            <div class="col-md-3"><select name="result" class="form-select"><?= select_options(REVIEW_RESULT, 'pass') ?></select></div>
            <div class="col-md-7"><textarea name="comment" class="form-control" rows="1" placeholder="ความเห็น / ข้อเสนอแนะในนาม<?= e(ROLE_LABELS[$me['role']]) ?>"></textarea></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">บันทึกผลตรวจ</button></div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">สมาชิกกลุ่ม (<?= count($members) ?> คน)</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($members as $m): ?><li class="list-group-item"><?= e(person($m)) ?> <?= $m['is_leader'] ? '<span class="badge text-bg-warning">หัวหน้า</span>' : '' ?><div class="small text-muted"><?= e($m['student_code']) ?></div></li><?php endforeach; ?>
      </ul>
    </div>

    <?php if ($canTeach): ?>
    <div class="card mb-3 no-print">
      <div class="card-header">พิจารณาหัวข้อโครงงาน</div>
      <div class="card-body">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
          <select name="status" class="form-select mb-2"><?= select_options(PROJECT_STATUS, $p['status'] === 'proposed' ? 'approved' : $p['status']) ?></select>
          <textarea name="note" class="form-control mb-2" rows="2" placeholder="หมายเหตุ (เช่น สิ่งที่ต้องแก้ไข)"><?= e($p['status_note']) ?></textarea>
          <button class="btn btn-primary w-100">บันทึกสถานะ</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-header">ผลการประเมินปลายภาคเรียน</div>
      <div class="card-body">
        <?php if ($canTeach): ?>
          <form method="post" class="no-print"><?= csrf_field() ?><input type="hidden" name="action" value="final">
            <div class="row g-2 mb-2">
              <div class="col-6"><label class="form-label small">คะแนน (เต็ม 100)</label><input type="number" step="0.01" min="0" max="100" name="final_score" class="form-control" value="<?= e($p['final_score']) ?>" data-grade-target="#final_grade"></div>
              <div class="col-6"><label class="form-label small">ผลการเรียน</label><select name="final_grade" id="final_grade" class="form-select"><option value="">-</option><?= select_options(GRADE_OPTIONS, $p['final_grade'], false) ?></select></div>
            </div>
            <label class="form-label small">ความก้าวหน้าโดยรวม (%)</label>
            <input type="range" class="form-range" name="progress" min="0" max="100" step="5" value="<?= (int)$p['progress'] ?>" oninput="this.nextElementSibling.textContent=this.value+'%'"><div class="small text-end mb-2"><?= (int)$p['progress'] ?>%</div>
            <textarea name="final_comment" class="form-control mb-2" rows="2" placeholder="ความเห็นของครู"><?= e($p['final_comment']) ?></textarea>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="completed" id="cp"<?= $p['status'] === 'completed' ? ' checked' : '' ?>><label class="form-check-label" for="cp">โครงงานเสร็จสมบูรณ์</label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_public" id="pub"<?= $p['is_public'] ? ' checked' : '' ?>><label class="form-check-label" for="pub">เผยแพร่ในหน้าผลงานโครงงาน (หน้าบ้าน)</label></div>
            <button class="btn btn-success w-100">บันทึกผลการประเมิน</button>
          </form>
        <?php else: ?>
          <div>คะแนน: <strong><?= e($p['final_score'] ?? '-') ?></strong> · ผลการเรียน: <strong><?= e($p['final_grade'] ?? '-') ?></strong></div>
          <?php if ($p['final_comment']): ?><div class="prewrap small mt-2"><?= e($p['final_comment']) ?></div><?php endif; ?>
        <?php endif; ?>
        <div class="print-only">คะแนน <?= e($p['final_score'] ?? '-') ?> · ผลการเรียน <?= e($p['final_grade'] ?? '-') ?> <div class="prewrap"><?= e($p['final_comment']) ?></div></div>
      </div>
    </div>
  </div>
</div>
<div class="print-only mt-5"><div class="row text-center">
  <div class="col">ลงชื่อ.......................................<br>(<?= e(person($p, 't_')) ?>)<br>ครูผู้สอน</div>
  <div class="col">ลงชื่อ.......................................<br>(.......................................)<br>หัวหน้างานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์</div>
</div></div>
<?php require APP_ROOT . '/inc/footer.php';
