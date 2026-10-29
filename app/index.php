<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login();
$sem = active_semester();
$semId = (int)($sem['id'] ?? 0);
$pageTitle = 'แดชบอร์ด';

if ($me['role'] === 'admin') {
    require APP_ROOT . '/inc/backup.php';
    try {
        if ($auto = backup_auto_if_due()) {
            flash('info', "ระบบสำรองข้อมูลอัตโนมัติแล้ว ($auto)");
        }
    } catch (Throwable $ex) {
        flash('danger', 'สำรองข้อมูลอัตโนมัติไม่สำเร็จ: ' . $ex->getMessage());
    }
}

$audience = $me['role'] === 'student' ? ['all', 'student'] : ($me['role'] === 'teacher' ? ['all', 'teacher'] : ['all', 'teacher', 'student']);
$news = q_all('SELECT * FROM announcements WHERE audience IN (' . implode(',', array_fill(0, count($audience), '?')) . ') ORDER BY created_at DESC LIMIT 5', $audience);

require APP_ROOT . '/inc/header.php';
?>
<div class="page-head">
  <div>
    <h1>สวัสดี <?= e(person($me)) ?></h1>
    <div class="text-muted"><?= e(ROLE_LABELS[$me['role']]) ?> · <?= e(semester_label($sem)) ?></div>
  </div>
</div>

<?php if (is_overseer()):
    $byStatus = [];
    foreach (q_all('SELECT p.status, COUNT(*) n FROM projects p JOIN sections s ON s.id = p.section_id WHERE s.semester_id = ? GROUP BY p.status', [$semId]) as $r) {
        $byStatus[$r['status']] = (int)$r['n'];
    }
    $totalProjects = array_sum($byStatus);
    $stats = [
        ['kanban', 'primary', $totalProjects, 'โครงงานภาคเรียนนี้'],
        ['check2-circle', 'success', $byStatus['completed'] ?? 0, 'เสร็จสมบูรณ์'],
        ['person-badge', 'warning', q_val('SELECT COUNT(DISTINCT teacher_id) FROM sections WHERE semester_id = ?', [$semId]), 'ครูผู้สอนโครงงาน'],
        ['people', 'info', q_val('SELECT COUNT(DISTINCT m.student_id) FROM project_members m JOIN projects p ON p.id = m.project_id JOIN sections s ON s.id = p.section_id WHERE s.semester_id = ?', [$semId]), 'ผู้เรียนที่มีโครงงาน'],
    ];
    $periods = q_all('SELECT rp.*,
            (SELECT COUNT(*) FROM progress_reports r JOIN projects p ON p.id = r.project_id WHERE r.period_id = rp.id AND p.status IN ("approved","completed")) AS sent,
            (SELECT COUNT(*) FROM progress_reports r WHERE r.period_id = rp.id AND r.status = "approved") AS passed
        FROM report_periods rp WHERE rp.semester_id = ? ORDER BY seq', [$semId]);
    $activeProjects = ($byStatus['approved'] ?? 0) + ($byStatus['completed'] ?? 0);
    $depts = q_all('SELECT c.department, COUNT(p.id) n, SUM(p.status = "completed") done
        FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id
        WHERE s.semester_id = ? GROUP BY c.department ORDER BY n DESC', [$semId]);
?>
<div class="row g-3 mb-3">
  <?php foreach ($stats as [$icon, $color, $val, $label]): ?>
  <div class="col-6 col-xl-3"><div class="card stat-card d-flex flex-row align-items-center gap-3">
    <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>-emphasis"><i class="bi bi-<?= $icon ?>"></i></div>
    <div><div class="stat-value"><?= number_format((int)$val) ?></div><div class="stat-label"><?= $label ?></div></div>
  </div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">การส่งรายงานความก้าวหน้าตามระยะ (จากโครงงานที่อนุมัติแล้ว <?= $activeProjects ?> โครงงาน)</div>
      <div class="card-body">
        <?php if (!$periods): ?><div class="text-muted">ยังไม่ได้กำหนดรอบการรายงาน</div><?php endif; ?>
        <?php foreach ($periods as $p): $pct = $activeProjects ? round($p['sent'] * 100 / $activeProjects) : 0; ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between small"><span><?= e($p['title']) ?> <span class="text-muted">· กำหนดส่ง <?= thai_date($p['due_date']) ?></span></span><span><?= $p['sent'] ?>/<?= $activeProjects ?> (ผ่าน <?= $p['passed'] ?>)</span></div>
            <div class="progress" style="height:10px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header">โครงงานจำแนกตามแผนกวิชา</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>แผนกวิชา</th><th class="text-end">โครงงาน</th><th class="text-end">เสร็จสมบูรณ์</th></tr></thead>
        <tbody>
        <?php foreach ($depts as $d): ?><tr><td><?= e($d['department'] ?: '-') ?></td><td class="text-end"><?= $d['n'] ?></td><td class="text-end"><?= (int)$d['done'] ?></td></tr><?php endforeach; ?>
        <?php if (!$depts): ?><tr><td colspan="3" class="text-muted">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header">สถานะโครงงาน</div>
      <div class="card-body">
        <?php foreach (PROJECT_STATUS as $k => [$label, $color]): $n = $byStatus[$k] ?? 0; ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span><span class="badge text-bg-<?= $color ?>">&nbsp;</span> <?= $label ?></span>
            <a href="<?= url('app/projects.php?status=' . $k) ?>" class="fw-semibold"><?= $n ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if (is_role('admin')): $logs = q_all('SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8'); ?>
    <div class="card mb-3">
      <div class="card-header">กิจกรรมล่าสุด</div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($logs as $l): ?><li class="list-group-item"><span class="text-muted"><?= thai_date($l['created_at'], true) ?></span> · <?= e($l['full_name'] ?? 'ระบบ') ?>: <?= e($l['action']) ?></li><?php endforeach; ?>
        <?php if (!$logs): ?><li class="list-group-item text-muted">ยังไม่มีกิจกรรม</li><?php endif; ?>
      </ul>
    </div>
    <?php else: ?>
    <div class="card mb-3"><div class="card-body">
      <a class="btn btn-primary w-100 mb-2" href="<?= url('app/oversight.php') ?>"><i class="bi bi-clipboard-check"></i> ตรวจติดตามโครงงาน</a>
      <a class="btn btn-outline-primary w-100" href="<?= url('app/report_semester.php') ?>"><i class="bi bi-file-earmark-bar-graph"></i> รายงานสิ้นภาคเรียน</a>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($me['role'] === 'teacher'):
    $sections = q_all('SELECT s.*, c.name AS classroom_name, c.code,
            (SELECT COUNT(*) FROM users u WHERE u.classroom_id = s.classroom_id AND u.role = "student" AND u.active = 1) AS students,
            (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects
        FROM sections s JOIN classrooms c ON c.id = s.classroom_id
        WHERE s.teacher_id = ? AND s.semester_id = ? ORDER BY c.code', [$me['id'], $semId]);
    $pendingReports = (int)q_val('SELECT COUNT(*) FROM progress_reports r JOIN projects p ON p.id = r.project_id JOIN sections s ON s.id = p.section_id
        WHERE r.status = "submitted" AND (s.teacher_id = ? OR p.advisor_id = ?)', [$me['id'], $me['id']]);
    $proposals = q_all('SELECT p.id, p.title, p.created_at, c.name AS classroom_name FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id
        WHERE p.status = "proposed" AND (s.teacher_id = ? OR p.advisor_id = ?) ORDER BY p.created_at', [$me['id'], $me['id']]);
?>
<div class="row g-3 mb-3">
  <div class="col-sm-4"><div class="card stat-card"><div class="stat-value"><?= count($sections) ?></div><div class="stat-label">กลุ่มเรียนที่สอนภาคเรียนนี้</div></div></div>
  <div class="col-sm-4"><a href="<?= url('app/teacher/pending.php') ?>" class="text-decoration-none"><div class="card stat-card"><div class="stat-value text-warning"><?= $pendingReports ?></div><div class="stat-label">รายงานรอตรวจ</div></div></a></div>
  <div class="col-sm-4"><div class="card stat-card"><div class="stat-value text-danger"><?= count($proposals) ?></div><div class="stat-label">หัวข้อโครงงานรอพิจารณา</div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card">
    <div class="card-header">กลุ่มเรียนวิชาโครงงานของฉัน</div>
    <div class="table-responsive"><table class="table mb-0">
      <thead><tr><th>กลุ่มเรียน</th><th>รายวิชา</th><th class="text-end">ผู้เรียน</th><th class="text-end">โครงงาน</th></tr></thead>
      <tbody>
      <?php foreach ($sections as $s): ?>
        <tr><td><a href="<?= url('app/section.php?id=' . $s['id']) ?>"><?= e($s['code'] . ' ' . $s['classroom_name']) ?></a></td><td><?= e($s['subject_code'] . ' ' . $s['subject_name']) ?></td><td class="text-end"><?= $s['students'] ?></td><td class="text-end"><?= $s['projects'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$sections): ?><tr><td colspan="4" class="text-muted">ยังไม่มีกลุ่มเรียนที่ได้รับมอบหมายในภาคเรียนนี้</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div></div>
  <div class="col-lg-5"><div class="card">
    <div class="card-header">หัวข้อโครงงานรอพิจารณา</div>
    <ul class="list-group list-group-flush">
      <?php foreach ($proposals as $p): ?><li class="list-group-item"><a href="<?= url('app/project.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a><div class="small text-muted"><?= e($p['classroom_name']) ?> · <?= thai_date($p['created_at']) ?></div></li><?php endforeach; ?>
      <?php if (!$proposals): ?><li class="list-group-item text-muted">ไม่มีรายการ</li><?php endif; ?>
    </ul>
  </div></div>
</div>

<?php else: /* student */
    $projects = q_all('SELECT p.*, s.semester_id FROM projects p JOIN project_members m ON m.project_id = p.id JOIN sections s ON s.id = p.section_id
        WHERE m.student_id = ? ORDER BY s.semester_id DESC, p.id DESC', [$me['id']]);
?>
<?php if (!$projects): ?>
  <div class="card"><div class="card-body text-center py-5">
    <i class="bi bi-lightbulb display-4 text-warning"></i>
    <h2 class="h5 mt-3">คุณยังไม่มีโครงงาน</h2>
    <p class="text-muted">เสนอหัวข้อโครงงานร่วมกับเพื่อนในกลุ่ม แล้วรอครูผู้สอนพิจารณาอนุมัติ</p>
    <a href="<?= url('app/project_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> เสนอหัวข้อโครงงาน</a>
  </div></div>
<?php endif; ?>
<?php foreach ($projects as $p): $periods = project_periods((int)$p['id'], (int)$p['semester_id']); ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
      <a href="<?= url('app/project.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a> <?= badge(PROJECT_STATUS, $p['status']) ?>
    </div>
    <div class="card-body">
      <div class="mb-3"><div class="small text-muted mb-1">ความก้าวหน้า <?= (int)$p['progress'] ?>%</div><div class="progress"><div class="progress-bar bg-success" style="width:<?= (int)$p['progress'] ?>%"></div></div></div>
      <?php if ($p['status'] === 'revise'): ?><div class="alert alert-danger">ครูให้แก้ไขหัวข้อ: <?= e($p['status_note']) ?> <a href="<?= url('app/project_form.php?id=' . $p['id']) ?>">แก้ไขหัวข้อ</a></div><?php endif; ?>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>รอบการรายงาน</th><th>กำหนดส่ง</th><th>สถานะ</th><th>คะแนน</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($periods as $pr): [$lbl, $col] = period_state($pr); ?>
          <tr>
            <td><?= e($pr['title']) ?></td><td><?= thai_date($pr['due_date']) ?></td>
            <td><span class="badge text-bg-<?= $col ?><?= $col === 'light' ? ' border' : '' ?>"><?= $lbl ?></span></td>
            <td><?= $pr['report'] && $pr['report']['score'] !== null ? e($pr['report']['score']) . '/' . e($pr['max_score']) : '-' ?></td>
            <td class="text-end">
              <?php if (in_array($p['status'], ['approved'], true) && (!$pr['report'] || $pr['report']['status'] !== 'approved')): ?>
                <a class="btn btn-sm btn-primary" href="<?= url('app/report_submit.php?project=' . $p['id'] . '&period=' . $pr['id']) ?>"><?= $pr['report'] ? 'แก้ไข/ส่งใหม่' : 'ส่งรายงาน' ?></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($p['status'] === 'proposed'): ?><div class="text-muted small mt-2">รอครูผู้สอนอนุมัติหัวข้อ จึงจะส่งรายงานความก้าวหน้าได้</div><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($news): ?>
<div class="card mt-3">
  <div class="card-header"><i class="bi bi-megaphone"></i> ประกาศ</div>
  <ul class="list-group list-group-flush">
    <?php foreach ($news as $n): ?>
      <li class="list-group-item"><div class="fw-semibold"><?= e($n['title']) ?></div><div class="small text-muted mb-1"><?= thai_date($n['created_at']) ?></div><div class="prewrap small"><?= e($n['body']) ?></div></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/inc/footer.php';
