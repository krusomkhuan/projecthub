<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$stats = [
    ['kanban', q_val('SELECT COUNT(*) FROM projects WHERE status IN ("approved","completed")'), 'โครงงานทั้งหมด'],
    ['trophy', q_val('SELECT COUNT(*) FROM projects WHERE status = "completed"'), 'โครงงานที่สำเร็จ'],
    ['person-badge', q_val('SELECT COUNT(DISTINCT teacher_id) FROM sections'), 'ครูผู้สอนโครงงาน'],
    ['people', q_val('SELECT COUNT(DISTINCT student_id) FROM project_members'), 'ผู้เรียนที่ทำโครงงาน'],
];
$featured = q_all('SELECT p.id, p.title, p.category, p.objective, c.department, sm.term, sm.academic_year
    FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id JOIN semesters sm ON sm.id = s.semester_id
    WHERE p.is_public = 1 ORDER BY p.updated_at DESC LIMIT 6');
$news = q_all('SELECT * FROM announcements WHERE is_public = 1 ORDER BY created_at DESC LIMIT 4');
$pageTitle = 'หน้าแรก';
require __DIR__ . '/inc/public_header.php';
?>
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <div class="badge text-bg-warning mb-3">งานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์</div>
        <h1>ระบบบริหารจัดการรายวิชาโครงงาน<br><?= e(college_name()) ?></h1>
        <p class="lead mt-3"><?= e(setting('hero_text') ?: 'ติดตามความก้าวหน้าโครงงานของนักเรียน นักศึกษาเป็นระยะ ตรวจสอบร่วมกันโดยครูผู้สอน ฝ่ายวิชาการ และฝ่ายยุทธศาสตร์และแผนงาน พร้อมสรุปรายงานเมื่อสิ้นภาคเรียน') ?></p>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <a href="<?= url('login.php') ?>" class="btn btn-warning btn-lg fw-semibold"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</a>
          <a href="<?= url('showcase.php') ?>" class="btn btn-outline-light btn-lg">ชมผลงานโครงงาน</a>
        </div>
      </div>
      <div class="col-lg-4 d-none d-lg-block text-center"><i class="bi bi-lightbulb" style="font-size:11rem;color:rgba(244,180,0,.85)"></i></div>
    </div>
  </div>
</section>
<div class="container hero-stats">
  <div class="row g-3">
    <?php foreach ($stats as [$icon, $val, $label]): ?>
      <div class="col-6 col-lg-3"><div class="card stat-card text-center"><i class="bi bi-<?= $icon ?> fs-3 text-primary"></i><div class="stat-value"><?= number_format((int)$val) ?></div><div class="stat-label"><?= $label ?></div></div></div>
    <?php endforeach; ?>
  </div>
</div>

<div class="container mt-5">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="d-flex justify-content-between align-items-end mb-3"><h2 class="h4 fw-bold mb-0">ผลงานโครงงานล่าสุด</h2><a href="<?= url('showcase.php') ?>">ดูทั้งหมด →</a></div>
      <div class="row g-3">
        <?php foreach ($featured as $p): ?>
          <div class="col-md-6"><a href="<?= url('project_public.php?id=' . $p['id']) ?>" class="text-decoration-none text-reset"><div class="card showcase-card"><div class="card-body">
            <span class="badge text-bg-light border mb-2"><?= e($p['category'] ?: 'โครงงาน') ?></span>
            <h3 class="h6 fw-bold"><?= e($p['title']) ?></h3>
            <p class="small text-muted mb-2"><?= e(mb_strimwidth((string)$p['objective'], 0, 120, '…')) ?></p>
            <div class="small text-muted"><?= e($p['department']) ?> · <?= $p['term'] ?>/<?= $p['academic_year'] ?></div>
          </div></div></a></div>
        <?php endforeach; ?>
        <?php if (!$featured): ?><div class="col-12 text-muted">ยังไม่มีผลงานที่เผยแพร่</div><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-4">
      <h2 class="h4 fw-bold mb-3">ประกาศ</h2>
      <div class="card"><ul class="list-group list-group-flush">
        <?php foreach ($news as $n): ?><li class="list-group-item"><div class="fw-semibold"><?= e($n['title']) ?></div><div class="small text-muted"><?= thai_date($n['created_at']) ?></div><div class="small mt-1"><?= e(mb_strimwidth((string)$n['body'], 0, 140, '…')) ?></div></li><?php endforeach; ?>
        <?php if (!$news): ?><li class="list-group-item text-muted">ยังไม่มีประกาศ</li><?php endif; ?>
      </ul></div>
      <a href="<?= url('news.php') ?>" class="d-block mt-2 small">ประกาศทั้งหมด →</a>
      <div class="card mt-4"><div class="card-body small">
        <div class="fw-semibold mb-2">ขั้นตอนการทำโครงงาน</div>
        <ol class="mb-0 ps-3">
          <li>นักเรียนเสนอหัวข้อและสมาชิกกลุ่มผ่านระบบ</li>
          <li>ครูผู้สอนพิจารณาอนุมัติหัวข้อโครงงาน</li>
          <li>ส่งรายงานความก้าวหน้าตามรอบที่กำหนด</li>
          <li>ครูตรวจ ให้คะแนน และข้อเสนอแนะ</li>
          <li>ฝ่ายวิชาการและฝ่ายแผนฯ ร่วมตรวจติดตาม</li>
          <li>ประเมินผลและสรุปรายงานสิ้นภาคเรียน</li>
        </ol>
      </div></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/inc/public_footer.php';
