<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login('student', 'teacher', 'admin');

$p = find_project(int_param('project'));
$access = $p ? project_access($p) : 'none';
if (!in_array($access, ['member', 'teacher', 'overseer'], true)) { http_response_code(404); exit('ไม่พบโครงงาน'); }
$period = q_one('SELECT * FROM report_periods WHERE id = ? AND semester_id = ?', [int_param('period'), $p['semester_id']]);
if (!$period) { http_response_code(404); exit('ไม่พบรอบการรายงาน'); }
$report = q_one('SELECT * FROM progress_reports WHERE project_id = ? AND period_id = ?', [$p['id'], $period['id']]);

if ($p['status'] !== 'approved' && $access === 'member') {
    flash('warning', 'ส่งรายงานได้เมื่อหัวข้อโครงงานได้รับการอนุมัติแล้ว และยังไม่ปิดโครงงาน');
    redirect('app/project.php?id=' . $p['id']);
}
if ($report && $report['status'] === 'approved' && $access === 'member') {
    flash('info', 'รายงานรอบนี้ผ่านการตรวจแล้ว ไม่สามารถแก้ไขได้');
    redirect('app/project.php?id=' . $p['id']);
}

$error = '';
if (is_post()) {
    csrf_check();
    $summary = input('summary');
    $link = input('link_url');
    if ($summary === '') {
        $error = 'กรุณาสรุปผลการดำเนินงาน';
    } elseif ($link !== '' && !preg_match('#^https?://#i', $link)) {
        $error = 'ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://';
    } else {
        try {
            $file = save_upload('file', 'reports/' . $p['id']);
            $fields = [$summary, max(0, min(100, int_param('progress'))), input('problems'), input('next_plan'), $link];
            if ($report) {
                if ($file && $report['file_path']) { @unlink(UPLOAD_DIR . '/' . $report['file_path']); }
                q('UPDATE progress_reports SET summary=?, progress=?, problems=?, next_plan=?, link_url=?, file_name=?, file_path=?, status="submitted", submitted_by=?, submitted_at=NOW() WHERE id=?',
                    [...$fields, $file[0] ?? $report['file_name'], $file[1] ?? $report['file_path'], $me['id'], $report['id']]);
            } else {
                q('INSERT INTO progress_reports (summary, progress, problems, next_plan, link_url, file_name, file_path, project_id, period_id, submitted_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [...$fields, $file[0] ?? '', $file[1] ?? '', $p['id'], $period['id'], $me['id']]);
            }
            q('UPDATE projects SET progress = GREATEST(progress, ?) WHERE id = ?', [$fields[1], $p['id']]);
            log_activity("ส่ง{$period['title']} โครงงาน #{$p['id']}");
            flash('success', 'ส่งรายงานเรียบร้อย รอครูตรวจ');
            redirect('app/project.php?id=' . $p['id']);
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();
        }
    }
}
$v = fn(string $k, string $d = '') => e((string)($_POST[$k] ?? $report[$k] ?? $d));
$pageTitle = 'ส่งรายงานความก้าวหน้า';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><div><h1><?= e($period['title']) ?></h1><div class="text-muted"><?= e($p['title']) ?> · กำหนดส่ง <?= thai_date($period['due_date']) ?></div></div></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($report && $report['status'] === 'revise'): ?><div class="alert alert-warning"><strong>ครูให้แก้ไข:</strong> <?= e($report['teacher_comment']) ?></div><?php endif; ?>
<?php if ($period['description']): ?><div class="alert alert-info prewrap"><?= e($period['description']) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card card-body"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">สรุปผลการดำเนินงานในช่วงนี้ *</label><textarea name="summary" class="form-control" rows="6" required><?= $v('summary') ?></textarea></div>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label">ปัญหา / อุปสรรค</label><textarea name="problems" class="form-control" rows="3"><?= $v('problems') ?></textarea></div>
    <div class="col-md-6"><label class="form-label">แผนการดำเนินงานต่อไป</label><textarea name="next_plan" class="form-control" rows="3"><?= $v('next_plan') ?></textarea></div>
  </div>
  <div class="mb-3"><label class="form-label">ความก้าวหน้าของโครงงาน: <span id="pv"><?= $v('progress', (string)$p['progress']) ?></span>%</label>
    <input type="range" class="form-range" name="progress" min="0" max="100" step="5" value="<?= $v('progress', (string)$p['progress']) ?>" oninput="document.getElementById('pv').textContent=this.value"></div>
  <div class="row g-3 mb-3">
    <div class="col-md-6"><label class="form-label">ไฟล์แนบ (<?= implode(', ', ALLOWED_UPLOADS) ?> ไม่เกิน <?= (int)$GLOBALS['config']['max_upload_mb'] ?> MB)</label><input type="file" name="file" class="form-control">
      <?php if (!empty($report['file_name'])): ?><div class="small text-muted mt-1">ไฟล์เดิม: <?= e($report['file_name']) ?> (อัปโหลดใหม่เพื่อแทนที่)</div><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label">ลิงก์ผลงาน (Google Drive, YouTube ฯลฯ)</label><input type="url" name="link_url" class="form-control" value="<?= $v('link_url') ?>" placeholder="https://"></div>
  </div>
  <div><button class="btn btn-primary"><i class="bi bi-send"></i> ส่งรายงาน</button> <a href="<?= url('app/project.php?id=' . $p['id']) ?>" class="btn btn-light">ยกเลิก</a></div>
</form>
<?php require APP_ROOT . '/inc/footer.php';
