<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
$me = require_login('teacher');
$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));

$sections = q_all('SELECT s.*, c.code, c.name AS classroom_name, c.level, c.department,
        (SELECT COUNT(*) FROM users u WHERE u.classroom_id = s.classroom_id AND u.role = "student" AND u.active = 1) AS students,
        (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects,
        (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id AND p.status = "completed") AS completed,
        (SELECT COUNT(*) FROM progress_reports r JOIN projects p ON p.id = r.project_id WHERE p.section_id = s.id AND r.status = "submitted") AS pending
    FROM sections s JOIN classrooms c ON c.id = s.classroom_id WHERE s.teacher_id = ? AND s.semester_id = ? ORDER BY c.code', [$me['id'], $semId]);
$pageTitle = 'รายวิชาที่สอน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>รายวิชาโครงงานที่สอน</h1>
  <form class="d-flex gap-2"><a href="<?= url('app/teacher/teaching.php') ?>" class="btn btn-outline-primary text-nowrap"><i class="bi bi-pencil-square"></i> แจ้ง / แก้ไขกลุ่มที่สอน</a><select name="semester" class="form-select" onchange="this.form.submit()"><?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $semId ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?></select></form>
</div>
<div class="row g-3">
<?php foreach ($sections as $s): ?>
  <div class="col-md-6 col-xl-4"><a href="<?= url('app/section.php?id=' . $s['id']) ?>" class="text-decoration-none text-reset"><div class="card h-100 showcase-card"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h5 mb-1"><?= e($s['code']) ?></h2><?php if ($s['pending']): ?><span class="badge text-bg-warning">รอตรวจ <?= $s['pending'] ?></span><?php endif; ?></div>
    <div class="text-muted small mb-3"><?= e($s['classroom_name']) ?> · <?= e($s['level'] . ' ' . $s['department']) ?><br><?= e($s['subject_code'] . ' ' . $s['subject_name']) ?></div>
    <div class="d-flex justify-content-between text-center">
      <div><div class="fw-bold fs-5"><?= $s['students'] ?></div><div class="small text-muted">ผู้เรียน</div></div>
      <div><div class="fw-bold fs-5"><?= $s['projects'] ?></div><div class="small text-muted">โครงงาน</div></div>
      <div><div class="fw-bold fs-5 text-success"><?= $s['completed'] ?></div><div class="small text-muted">เสร็จสมบูรณ์</div></div>
    </div>
  </div></div></a></div>
<?php endforeach; ?>
<?php if (!$sections): ?><div class="col-12"><div class="card card-body text-muted">ไม่มีรายวิชาที่ได้รับมอบหมายในภาคเรียนนี้</div></div><?php endif; ?>
</div>
<?php require APP_ROOT . '/inc/footer.php';
