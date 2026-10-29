<?php
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'ProjectHub';
$me = current_user();
$script = $_SERVER['SCRIPT_NAME'];

$menu = [['app/index.php', 'speedometer2', 'แดชบอร์ด'], ['index.php', 'globe2', 'ดูหน้าเว็บ', true]];
if ($me) {
    switch ($me['role']) {
        case 'admin':
            $menu[] = ['app/projects.php', 'kanban', 'โครงงานทั้งหมด'];
            $menu[] = ['app/oversight.php', 'clipboard-check', 'ตรวจติดตามโครงงาน'];
            $menu[] = ['app/report_semester.php', 'file-earmark-bar-graph', 'รายงานสิ้นภาคเรียน'];
            $menu[] = ['-', '', 'ข้อมูลพื้นฐาน'];
            $menu[] = ['app/admin/semesters.php', 'calendar3', 'ปีการศึกษา / กำหนดการรายงาน'];
            $menu[] = ['app/admin/departments.php', 'mortarboard', 'สาขาวิชา / แผนกวิชา'];
            $menu[] = ['app/admin/users.php', 'person', 'ครูและบุคลากร'];
            $menu[] = ['app/admin/classrooms.php', 'people', 'กลุ่มเรียน'];
            $menu[] = ['app/admin/sections.php', 'journal-bookmark', 'รายวิชาโครงงาน'];
            $menu[] = ['app/admin/teams.php', 'diagram-3', 'กลุ่มโครงงาน'];
            $menu[] = ['-', '', 'นำเข้าข้อมูล'];
            $menu[] = ['app/admin/import.php?type=departments', 'building', 'แผนกวิชา'];
            $menu[] = ['app/admin/import.php?type=majors', 'diagram-2', 'สาขาวิชา'];
            $menu[] = ['app/admin/import.php?type=teachers', 'person-plus', 'ครูผู้สอน'];
            $menu[] = ['app/admin/import.php?type=classrooms', 'people', 'กลุ่มเรียน'];
            $menu[] = ['app/admin/import.php?type=students', 'mortarboard', 'นักเรียน นักศึกษา'];
            $menu[] = ['app/admin/import.php?type=sections', 'journal-plus', 'รายวิชาโครงงาน'];
            $menu[] = ['-', '', 'การจัดการระบบ'];
            $menu[] = ['app/admin/announcements.php', 'megaphone', 'ประกาศ'];
            $menu[] = ['app/admin/downloads.php', 'folder2-open', 'จัดการแบบฟอร์มดาวน์โหลด'];
            $menu[] = ['app/admin/backup.php', 'database-check', 'สำรองและกู้คืนข้อมูล'];
            $menu[] = ['app/admin/settings.php', 'gear', 'ตั้งค่าระบบ'];
            $menu[] = ['app/admin/logs.php', 'clock-history', 'บันทึกการใช้งาน'];
            break;
        case 'research':
        case 'academic':
        case 'strategy':
            $menu[] = ['app/projects.php', 'kanban', 'โครงงานทั้งหมด'];
            $menu[] = ['app/oversight.php', 'clipboard-check', 'ตรวจติดตามโครงงาน'];
            $menu[] = ['app/report_semester.php', 'file-earmark-bar-graph', 'รายงานสิ้นภาคเรียน'];
            break;
        case 'teacher':
            $menu[] = ['app/teacher/sections.php', 'journal-bookmark', 'รายวิชาที่สอน'];
            $menu[] = ['app/teacher/teaching.php', 'pencil-square', 'แจ้งรายวิชาที่สอน'];
            $menu[] = ['app/projects.php', 'kanban', 'โครงงานในความดูแล'];
            $menu[] = ['app/teacher/pending.php', 'inbox', 'รายงานรอตรวจ'];
            $menu[] = ['app/report_semester.php', 'file-earmark-bar-graph', 'รายงานสิ้นภาคเรียน'];
            $menu[] = ['app/admin/announcements.php', 'megaphone', 'ประกาศ'];
            break;
        case 'student':
            $menu[] = ['app/projects.php', 'kanban', 'โครงงานของฉัน'];
            $menu[] = ['app/student_join.php', 'people', 'เลือกกลุ่มโครงงาน'];
            $menu[] = ['app/project_form.php', 'plus-circle', 'เสนอหัวข้อโครงงาน'];
            break;
    }
    $menu[] = ['-', '', 'ช่วยเหลือ'];
    $menu[] = ['app/downloads.php', 'cloud-download', 'ดาวน์โหลดแบบฟอร์ม'];
    $menu[] = ['manual.php', 'book', 'คู่มือการใช้งาน'];
    $_openIssues = $me['role'] === 'admin' ? (int)q_val('SELECT COUNT(*) FROM issues WHERE status IN ("open","in_progress")') : 0;
    $menu[] = ['app/issues.php', 'exclamation-triangle', 'แจ้งปัญหาการใช้งาน' . ($_openIssues ? " ($_openIssues)" : '')];
    $menu[] = ['-', '', 'บัญชีผู้ใช้'];
    $menu[] = ['app/profile.php', 'person-circle', 'ข้อมูลส่วนตัว / รหัสผ่าน'];
    $menu[] = ['logout.php', 'box-arrow-right', 'ออกจากระบบ'];
}
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ProjectHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/app.css') ?>" rel="stylesheet">
</head>
<body class="app-body">
<nav class="navbar navbar-dark app-topbar d-lg-none no-print">
  <div class="container-fluid">
    <button class="btn btn-outline-light btn-sm" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list"></i></button>
    <span class="navbar-brand mb-0 h1">ProjectHub</span>
  </div>
</nav>
<div class="d-flex">
  <aside class="offcanvas-lg offcanvas-start app-sidebar no-print" id="sidebar" tabindex="-1">
    <div class="sidebar-brand">
      <a href="<?= url('index.php') ?>" class="text-decoration-none text-white">
        <div class="fs-4 fw-bold"><i class="bi bi-lightbulb-fill text-warning"></i> ProjectHub</div>
        <div class="small opacity-75"><?= e(college_name()) ?></div>
        <div class="small opacity-50">v<?= APP_VERSION ?></div>
      </a>
    </div>
    <?php if ($me): ?>
    <div class="sidebar-user">
      <div class="fw-semibold text-truncate"><?= e(person($me)) ?></div>
      <div class="small opacity-75"><?= e(ROLE_LABELS[$me['role']]) ?></div>
    </div>
    <?php endif; ?>
    <ul class="nav flex-column sidebar-nav">
      <?php foreach ($menu as $_item): [$_href, $_icon, $_label] = $_item; $_newTab = $_item[3] ?? false; ?>
        <?php if ($_href === '-'): ?>
          <li class="sidebar-heading"><?= e($_label) ?></li>
        <?php else:
            // ลิงก์ที่มี query (เช่น import.php?type=...) จะ active เมื่อพารามิเตอร์ตรงกันด้วย
            parse_str((string)parse_url($_href, PHP_URL_QUERY), $_q);
            // เทียบ path เต็มจาก BASE_URL เพื่อไม่ให้ index.php ของหน้าเว็บไปตรงกับ app/index.php
            $_isActive = !$_newTab && $script === BASE_URL . '/' . strtok($_href, '?');
            foreach ($_q as $_k => $_v) { $_isActive = $_isActive && ($_GET[$_k] ?? '') === $_v; } ?>
          <li class="nav-item"><a class="nav-link<?= $_isActive ? ' active' : '' ?>" href="<?= url($_href) ?>"<?= $_newTab ? ' target="_blank" rel="noopener"' : '' ?>><i class="bi bi-<?= $_icon ?>"></i> <?= e($_label) ?><?= $_newTab ? ' <i class="bi bi-box-arrow-up-right small opacity-50"></i>' : '' ?></a></li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </aside>
  <main class="app-main flex-grow-1">
    <?php foreach (take_flashes() as [$_ft, $_fm]): ?>
      <div class="alert alert-<?= e($_ft) ?> alert-dismissible fade show no-print"><?= e($_fm) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endforeach; ?>
