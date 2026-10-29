<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
require APP_ROOT . '/inc/student_registration.php';
$me = require_login('student');
$semester = registration_semester();
$existing = $semester ? q_val('SELECT p.id FROM projects p JOIN project_members m ON m.project_id = p.id JOIN sections s ON s.id = p.section_id WHERE m.student_id = ? AND s.semester_id = ? AND p.status <> "cancelled" LIMIT 1', [$me['id'], $semester['id']]) : null;
if ($existing) { flash('info', 'คุณมีกลุ่มโครงงานในภาคเรียนนี้แล้ว'); redirect('app/project.php?id=' . $existing); }
$error = '';
if (is_post()) {
    csrf_check();
    try {
        db()->beginTransaction();
        if (!$semester || !q_one('SELECT id FROM semesters WHERE id = ? AND is_active = 1 AND is_closed = 0 FOR UPDATE', [$semester['id']])) {
            throw new InvalidArgumentException('ยังไม่เปิดให้เข้าร่วมกลุ่มในภาคเรียนนี้');
        }
        $project = attach_student_to_project((int)$me['id'], int_param('project_id'), (int)$semester['id']);
        log_activity('เข้าร่วมโครงงาน #' . $project['id']);
        db()->commit();
        flash('success', 'เข้าร่วมกลุ่มโครงงานแล้ว ส่งงานได้เมื่อหัวข้อโครงงานได้รับอนุมัติ');
        redirect('app/project.php?id=' . $project['id']);
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'เข้าร่วมกลุ่มไม่สำเร็จ กรุณาติดต่อครู';
    }
}
$projects = $semester ? registration_projects((int)$semester['id'], (int)$me['classroom_id']) : [];
$pageTitle = 'เลือกกลุ่มโครงงาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>เลือกกลุ่มโครงงาน</h1></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="card card-body">
<p>เลือกกลุ่มโครงงานของตนเองใน <?= e(semester_label($semester)) ?> หากต้องการเปลี่ยนกลุ่มเรียนหรือย้ายกลุ่มโครงงาน กรุณาติดต่อครูผู้สอน</p>
<?php if ($projects): ?><form method="post"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="project_id">กลุ่มโครงงาน</label><select id="project_id" name="project_id" class="form-select" required><option value="">เลือกกลุ่มโครงงาน</option><?php foreach ($projects as $project): ?><option value="<?= $project['id'] ?>"><?= e($project['title'] . ' · ' . $project['subject_code']) ?></option><?php endforeach; ?></select></div><button class="btn btn-primary">เข้าร่วมกลุ่มโครงงาน</button></form>
<?php else: ?><div class="alert alert-info">ยังไม่มีกลุ่มโครงงานที่เปิดให้เข้าร่วม กรุณาติดต่อครู หรือเสนอหัวข้อโครงงานใหม่</div><?php endif; ?>
<p class="mt-3 mb-0"><a href="<?= url('app/project_form.php') ?>">เสนอหัวข้อโครงงานใหม่</a></p>
</div>
<?php require APP_ROOT . '/inc/footer.php';
