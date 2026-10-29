<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$kw = input('q');
$cat = input('category');
$dept = input('department');
$params = [];
$where = 'p.is_public = 1';
if ($kw !== '') { $where .= ' AND (p.title LIKE ? OR p.title_en LIKE ? OR p.objective LIKE ?)'; array_push($params, "%$kw%", "%$kw%", "%$kw%"); }
if ($cat !== '') { $where .= ' AND p.category = ?'; $params[] = $cat; }
if ($dept !== '') { $where .= ' AND c.department = ?'; $params[] = $dept; }
$from = "FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id JOIN semesters sm ON sm.id = s.semester_id WHERE $where";
$total = (int)q_val("SELECT COUNT(*) $from", $params);
[$page, $pages, $offset, $limit] = paginate($total, 18);
$projects = q_all("SELECT p.id, p.title, p.title_en, p.category, p.objective, c.department, c.level, sm.term, sm.academic_year $from ORDER BY sm.academic_year DESC, sm.term DESC, p.title LIMIT $limit OFFSET $offset", $params);
$departments = q_all('SELECT DISTINCT department FROM classrooms WHERE department <> "" ORDER BY department');
$pageTitle = 'ผลงานโครงงาน';
require __DIR__ . '/inc/public_header.php';
?>
<div class="container py-4">
  <h1 class="h3 fw-bold mb-3">ผลงานโครงงานของนักเรียน นักศึกษา <span class="text-muted fs-6">(<?= $total ?>)</span></h1>
  <form class="row g-2 mb-4">
    <div class="col-md-5"><input name="q" class="form-control" placeholder="ค้นหาชื่อโครงงาน" value="<?= e($kw) ?>"></div>
    <div class="col-md-3"><select name="category" class="form-select"><option value="">ทุกประเภท</option><?= select_options(PROJECT_CATEGORIES, $cat, false) ?></select></div>
    <div class="col-md-3"><select name="department" class="form-select"><option value="">ทุกแผนกวิชา</option><?php foreach ($departments as $d): ?><option<?= $d['department'] === $dept ? ' selected' : '' ?>><?= e($d['department']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
  </form>
  <div class="row g-3">
    <?php foreach ($projects as $p): ?>
      <div class="col-md-6 col-lg-4"><a href="<?= url('project_public.php?id=' . $p['id']) ?>" class="text-decoration-none text-reset"><div class="card showcase-card"><div class="card-body">
        <span class="badge text-bg-light border mb-2"><?= e($p['category'] ?: 'โครงงาน') ?></span>
        <h2 class="h6 fw-bold mb-1"><?= e($p['title']) ?></h2>
        <?php if ($p['title_en']): ?><div class="small text-muted fst-italic mb-2"><?= e($p['title_en']) ?></div><?php endif; ?>
        <p class="small text-muted"><?= e(mb_strimwidth((string)$p['objective'], 0, 140, '…')) ?></p>
        <div class="small"><?= e($p['level'] . ' ' . $p['department']) ?> · ภาคเรียน <?= $p['term'] ?>/<?= $p['academic_year'] ?></div>
      </div></div></a></div>
    <?php endforeach; ?>
    <?php if (!$projects): ?><div class="col-12 text-muted">ไม่พบผลงาน</div><?php endif; ?>
  </div>
  <div class="mt-4"><?= pagination_links($page, $pages) ?></div>
</div>
<?php require __DIR__ . '/inc/public_footer.php';
