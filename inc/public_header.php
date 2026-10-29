<?php
$pageTitle = $pageTitle ?? 'ProjectHub';
$me = current_user();
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ProjectHub <?= e(college_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/app.css') ?>" rel="stylesheet">
</head>
<body class="public-body">
<nav class="navbar navbar-expand-lg navbar-dark public-nav">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= url('index.php') ?>"><i class="bi bi-lightbulb-fill text-warning"></i> ProjectHub</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#pubnav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="pubnav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= url('index.php') ?>">หน้าแรก</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('showcase.php') ?>">ผลงานโครงงาน</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('news.php') ?>">ประกาศ</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('downloads.php') ?>">ดาวน์โหลด</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('manual.php') ?>">คู่มือการใช้งาน</a></li>
      </ul>
      <?php if ($me): ?>
        <a class="btn btn-warning btn-sm fw-semibold" href="<?= url('app/index.php') ?>"><i class="bi bi-speedometer2"></i> เข้าสู่ระบบจัดการ</a>
      <?php else: ?>
        <a class="btn btn-outline-light btn-sm me-2" href="<?= url('register.php') ?>">สมัครสมาชิกนักเรียน</a>
        <a class="btn btn-warning btn-sm fw-semibold" href="<?= url('login.php') ?>"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
