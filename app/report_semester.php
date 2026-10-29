<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login('admin', 'research', 'academic', 'strategy', 'teacher');

$semId = int_param('semester', (int)(active_semester()['id'] ?? 0));
$sem = q_one('SELECT * FROM semesters WHERE id = ?', [$semId]);
$dept = input('department');

// หัวหน้างานวิจัยฯ (และผู้ดูแลระบบ) เป็นผู้เขียนสรุปผลรายงานสิ้นภาคเรียน
$canSummarize = is_role('research', 'admin');
if (is_post() && $canSummarize && input('action') === 'summary') {
    csrf_check();
    $submit = input('status') === 'submitted';
    q('INSERT INTO semester_summaries (semester_id, department, summary, problems, suggestions, status, written_by, submitted_at) VALUES (?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE summary = VALUES(summary), problems = VALUES(problems), suggestions = VALUES(suggestions), status = VALUES(status),
                               written_by = VALUES(written_by), submitted_at = IF(VALUES(status) = "submitted", COALESCE(submitted_at, NOW()), NULL)',
        [$semId, $dept, input('summary'), input('problems'), input('suggestions'), $submit ? 'submitted' : 'draft', $me['id'], $submit ? date('Y-m-d H:i:s') : null]);
    log_activity(($submit ? 'ส่งสรุปรายงานสิ้นภาคเรียน ' : 'บันทึกร่างสรุปรายงานสิ้นภาคเรียน ') . semester_label($sem) . ($dept ? " แผนก$dept" : ''));
    flash('success', $submit ? 'บันทึกและส่งสรุปรายงานสิ้นภาคเรียนแล้ว' : 'บันทึกร่างสรุปรายงานแล้ว');
    redirect('app/report_semester.php?' . http_build_query(['semester' => $semId, 'department' => $dept]) . '#summary');
}
$summary = q_one('SELECT ss.*, u.prefix, u.full_name FROM semester_summaries ss LEFT JOIN users u ON u.id = ss.written_by WHERE ss.semester_id = ? AND ss.department = ?', [$semId, $dept]);

$params = [$semId];
$where = 's.semester_id = ? AND p.status <> "cancelled"';
if ($me['role'] === 'teacher') { $where .= ' AND (s.teacher_id = ? OR p.advisor_id = ?)'; $params[] = $me['id']; $params[] = $me['id']; }
if ($dept !== '') { $where .= ' AND c.department = ?'; $params[] = $dept; }

$projects = q_all("SELECT p.*, c.code AS classroom_code, c.name AS classroom_name, c.department, c.level,
        t.id AS teacher_id, t.prefix AS t_prefix, t.full_name AS t_full_name,
        (SELECT COUNT(*) FROM project_members m WHERE m.project_id = p.id) AS member_count
    FROM projects p JOIN sections s ON s.id = p.section_id JOIN classrooms c ON c.id = s.classroom_id JOIN users t ON t.id = s.teacher_id
    WHERE $where ORDER BY c.department, c.code, p.id", $params);
$periods = q_all('SELECT * FROM report_periods WHERE semester_id = ? ORDER BY seq, id', [$semId]);
$ids = array_map('intval', array_column($projects, 'id')) ?: [0];
$in = implode(',', $ids);
$reports = [];
foreach (q_all("SELECT project_id, period_id, status, score, submitted_at FROM progress_reports WHERE project_id IN ($in)") as $r) {
    $reports[$r['project_id']][$r['period_id']] = $r;
}
$reviews = [];
foreach (q_all("SELECT project_id, unit, result FROM oversight_reviews WHERE project_id IN ($in) ORDER BY created_at") as $o) {
    $reviews[$o['project_id']][$o['unit'] === 'admin' ? 'research' : $o['unit']] = $o['result'];
}

// สรุปภาพรวม
$active = array_filter($projects, fn($p) => in_array($p['status'], ['approved', 'completed'], true));
$completed = array_filter($projects, fn($p) => $p['status'] === 'completed');
$scores = array_filter(array_column($projects, 'final_score'), fn($s) => $s !== null);
$avgScore = $scores ? array_sum($scores) / count($scores) : null;
$grades = array_fill_keys(GRADE_OPTIONS, 0);
foreach ($projects as $p) { if ($p['final_grade'] !== null && isset($grades[$p['final_grade']])) { $grades[$p['final_grade']]++; } }
$students = array_sum(array_column($projects, 'member_count'));

$group = function (string $key) use ($projects, $reports, $periods): array {
    $out = [];
    foreach ($projects as $p) {
        $k = $p[$key];
        $g = &$out[$k];
        $g ??= ['label' => '', 'projects' => 0, 'students' => 0, 'completed' => 0, 'scores' => [], 'sent' => 0, 'expected' => 0];
        $g['label'] = $key === 'teacher_id' ? person($p, 't_') : ($k ?: 'ไม่ระบุ');
        $g['projects']++;
        $g['students'] += (int)$p['member_count'];
        $g['completed'] += (int)($p['status'] === 'completed');
        if ($p['final_score'] !== null) { $g['scores'][] = (float)$p['final_score']; }
        if (in_array($p['status'], ['approved', 'completed'], true)) {
            $g['expected'] += count($periods);
            $g['sent'] += count($reports[$p['id']] ?? []);
        }
        unset($g);
    }
    return $out;
};
$byDept = $group('department');
$byTeacher = $group('teacher_id');

if (input('export') === 'csv') {
    $header = ['ลำดับ', 'ชื่อโครงงาน', 'ประเภท', 'กลุ่มเรียน', 'แผนกวิชา', 'สมาชิก', 'ครูผู้สอน', 'สถานะ', 'ความก้าวหน้า%'];
    foreach ($periods as $pr) { $header[] = $pr['title']; }
    array_push($header, 'ผลตรวจงานวิจัยฯ', 'ผลตรวจฝ่ายวิชาการ', 'ผลตรวจฝ่ายแผนฯ', 'คะแนน', 'เกรด');
    $rows = [];
    foreach ($projects as $i => $p) {
        $row = [$i + 1, $p['title'], $p['category'], $p['classroom_code'], $p['department'], members_text((int)$p['id']), person($p, 't_'), PROJECT_STATUS[$p['status']][0], $p['progress']];
        foreach ($periods as $pr) {
            $r = $reports[$p['id']][$pr['id']] ?? null;
            $row[] = $r ? REPORT_STATUS[$r['status']][0] . ($r['score'] !== null ? " ({$r['score']})" : '') : 'ยังไม่ส่ง';
        }
        foreach (['research', 'academic', 'strategy'] as $u) { $row[] = isset($reviews[$p['id']][$u]) ? REVIEW_RESULT[$reviews[$p['id']][$u]][0] : ''; }
        array_push($row, $p['final_score'], $p['final_grade']);
        $rows[] = $row;
    }
    send_csv('semester_report_' . ($sem['term'] ?? '') . '_' . ($sem['academic_year'] ?? '') . '.csv', $header, $rows);
}

$departments = q_all('SELECT DISTINCT department FROM classrooms WHERE department <> "" ORDER BY department');
$pct = fn(int $a, int $b) => $b ? round($a * 100 / $b, 1) . '%' : '-';
$pageTitle = 'รายงานสิ้นภาคเรียน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head no-print">
  <h1>รายงานสิ้นภาคเรียน</h1>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
    <a class="btn btn-outline-success" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"><i class="bi bi-filetype-csv"></i> ส่งออก Excel (CSV)</a>
  </div>
</div>
<form class="card card-body mb-3 no-print"><div class="row g-2">
  <div class="col-md-3"><select name="semester" class="form-select"><?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === $semId ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="department" class="form-select"><option value="">ทุกแผนกวิชา</option><?php foreach ($departments as $d): ?><option<?= $d['department'] === $dept ? ' selected' : '' ?>><?= e($d['department']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-auto"><button class="btn btn-primary">แสดงรายงาน</button></div>
</div></form>

<div class="text-center mb-3">
  <h2 class="h4 fw-bold mb-1">รายงานผลการจัดการเรียนการสอนรายวิชาโครงงาน</h2>
  <div><?= e(semester_label($sem)) ?> · <?= e(college_name()) ?><?= $dept ? ' · แผนกวิชา' . e($dept) : '' ?><?= $me['role'] === 'teacher' ? ' · ครูผู้สอน ' . e(person($me)) : '' ?></div>
  <div class="small text-muted">งานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์ ร่วมกับฝ่ายวิชาการ และฝ่ายยุทธศาสตร์และแผนงาน · ข้อมูล ณ <?= thai_date(date('Y-m-d H:i'), true) ?></div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ([['โครงงานทั้งหมด', count($projects)], ['ผู้เรียนที่ทำโครงงาน', $students], ['เสร็จสมบูรณ์', count($completed) . ' (' . $pct(count($completed), count($projects)) . ')'], ['คะแนนเฉลี่ย', $avgScore !== null ? number_format($avgScore, 2) : '-']] as [$l, $v]): ?>
    <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-value"><?= $v ?></div><div class="stat-label"><?= $l ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-header">1. การส่งรายงานความก้าวหน้าตามระยะ (โครงงานที่อนุมัติแล้ว <?= count($active) ?> โครงงาน)</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead><tr><th>รอบการรายงาน</th><th>กำหนดส่ง</th><th class="text-end">ส่งแล้ว</th><th class="text-end">ตรงเวลา</th><th class="text-end">ผ่าน</th><th class="text-end">ร้อยละการส่ง</th></tr></thead><tbody>
      <?php foreach ($periods as $pr):
          $sent = $ontime = $passed = 0;
          foreach ($active as $p) {
              $r = $reports[$p['id']][$pr['id']] ?? null;
              if (!$r) continue;
              $sent++;
              $ontime += (int)(!$pr['due_date'] || substr($r['submitted_at'], 0, 10) <= $pr['due_date']);
              $passed += (int)($r['status'] === 'approved');
          } ?>
        <tr><td><?= e($pr['title']) ?></td><td><?= thai_date($pr['due_date']) ?></td><td class="text-end"><?= $sent ?></td><td class="text-end"><?= $ontime ?></td><td class="text-end"><?= $passed ?></td><td class="text-end"><?= $pct($sent, count($active)) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div></div></div>
  <div class="col-lg-5"><div class="card h-100"><div class="card-header">2. สถานะโครงงานและผลการเรียน</div><div class="card-body">
    <table class="table table-sm mb-2"><tbody>
      <?php foreach (PROJECT_STATUS as $k => [$l]): $n = count(array_filter($projects, fn($p) => $p['status'] === $k)); if ($k === 'cancelled') continue; ?>
        <tr><td><?= $l ?></td><td class="text-end"><?= $n ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
    <div class="small fw-semibold mb-1">การกระจายผลการเรียน (จำนวนโครงงาน)</div>
    <div class="d-flex flex-wrap gap-1"><?php foreach ($grades as $g => $n): if (!$n) continue; ?><span class="badge text-bg-light border">เกรด <?= e($g) ?>: <?= $n ?></span><?php endforeach; ?><?= array_sum($grades) ? '' : '<span class="text-muted small">ยังไม่มีการตัดเกรด</span>' ?></div>
  </div></div></div>
</div>

<?php foreach (['3. สรุปตามแผนกวิชา' => [$byDept, 'แผนกวิชา'], '4. สรุปตามครูผู้สอน' => [$byTeacher, 'ครูผู้สอน']] as $title => [$rows, $col]): ?>
<div class="card mb-3"><div class="card-header"><?= $title ?></div><div class="table-responsive"><table class="table table-sm mb-0">
  <thead><tr><th><?= $col ?></th><th class="text-end">โครงงาน</th><th class="text-end">ผู้เรียน</th><th class="text-end">เสร็จสมบูรณ์</th><th class="text-end">ร้อยละการส่งรายงาน</th><th class="text-end">คะแนนเฉลี่ย</th></tr></thead><tbody>
  <?php foreach ($rows as $g): ?>
    <tr><td><?= e($g['label']) ?></td><td class="text-end"><?= $g['projects'] ?></td><td class="text-end"><?= $g['students'] ?></td><td class="text-end"><?= $g['completed'] ?> (<?= $pct($g['completed'], $g['projects']) ?>)</td>
      <td class="text-end"><?= $pct($g['sent'], $g['expected']) ?></td><td class="text-end"><?= $g['scores'] ? number_format(array_sum($g['scores']) / count($g['scores']), 2) : '-' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">ไม่มีข้อมูล</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endforeach; ?>

<div class="card mb-3"><div class="card-header">5. รายละเอียดโครงงานรายกลุ่ม</div><div class="table-responsive"><table class="table table-sm table-bordered mb-0">
  <thead><tr><th>#</th><th>ชื่อโครงงาน</th><th>กลุ่มเรียน</th><th>ครูผู้สอน</th><th>สถานะ</th>
    <?php foreach ($periods as $pr): ?><th class="text-center" title="<?= e($pr['title']) ?>">ร.<?= $pr['seq'] ?></th><?php endforeach; ?>
    <th class="text-center">วจ.</th><th class="text-center">วช.</th><th class="text-center">ผน.</th><th class="text-end">คะแนน</th><th class="text-center">เกรด</th></tr></thead><tbody>
  <?php foreach ($projects as $i => $p): ?>
    <tr><td><?= $i + 1 ?></td><td><a href="<?= url('app/project.php?id=' . $p['id']) ?>"><?= e($p['title']) ?></a><div class="small text-muted"><?= $p['member_count'] ?> คน</div></td>
      <td><?= e($p['classroom_code']) ?></td><td class="small"><?= e(person($p, 't_')) ?></td><td><?= badge(PROJECT_STATUS, $p['status']) ?></td>
      <?php foreach ($periods as $pr): $r = $reports[$p['id']][$pr['id']] ?? null; ?>
        <td class="text-center"><?= $r ? ['approved' => '<span class="text-success">✔</span>', 'submitted' => '<span class="text-warning">●</span>', 'revise' => '<span class="text-danger">↺</span>'][$r['status']] : '<span class="text-muted">–</span>' ?></td>
      <?php endforeach; ?>
      <?php foreach (['research', 'academic', 'strategy'] as $u): $rv = $reviews[$p['id']][$u] ?? null; ?>
        <td class="text-center"><?= $rv ? ['pass' => '<span class="text-success">ผ่าน</span>', 'improve' => '<span class="text-warning">ปรับ</span>', 'fail' => '<span class="text-danger">ไม่ผ่าน</span>'][$rv] : '–' ?></td>
      <?php endforeach; ?>
      <td class="text-end"><?= e($p['final_score'] ?? '-') ?></td><td class="text-center"><?= e($p['final_grade'] ?? '-') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$projects): ?><tr><td colspan="<?= 10 + count($periods) ?>" class="text-muted">ไม่มีข้อมูลโครงงาน</td></tr><?php endif; ?>
</tbody></table></div>
<div class="card-body small text-muted">ร.1–ร.<?= count($periods) ?> = รอบรายงานความก้าวหน้า (✔ ผ่าน · ● รอตรวจ · ↺ ให้แก้ไข · – ยังไม่ส่ง) · วจ. = งานวิจัยฯ · วช. = ฝ่ายวิชาการ · ผน. = ฝ่ายยุทธศาสตร์และแผนงาน</div>
</div>

<div class="card mb-3" id="summary">
  <div class="card-header d-flex justify-content-between flex-wrap gap-2">
    <span>6. สรุปผลการดำเนินงานและข้อเสนอแนะ (หัวหน้างานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์)</span>
    <?php if ($summary): ?><span class="no-print"><?= $summary['status'] === 'submitted'
        ? '<span class="badge text-bg-success">ส่งรายงานแล้ว ' . thai_date($summary['submitted_at'], true) . '</span>'
        : '<span class="badge text-bg-warning">ฉบับร่าง</span>' ?></span><?php endif; ?>
  </div>
  <div class="card-body">
  <?php if ($canSummarize): ?>
    <form method="post" class="no-print"><?= csrf_field() ?><input type="hidden" name="action" value="summary">
      <input type="hidden" name="semester" value="<?= $semId ?>"><input type="hidden" name="department" value="<?= e($dept) ?>">
      <div class="mb-2"><label class="form-label small">สรุปผลการดำเนินงาน</label><textarea name="summary" class="form-control" rows="5" placeholder="ภาพรวมผลการจัดการเรียนการสอนรายวิชาโครงงานในภาคเรียนนี้"><?= e($summary['summary'] ?? '') ?></textarea></div>
      <div class="mb-2"><label class="form-label small">ปัญหาและอุปสรรค</label><textarea name="problems" class="form-control" rows="3"><?= e($summary['problems'] ?? '') ?></textarea></div>
      <div class="mb-3"><label class="form-label small">ข้อเสนอแนะ / แนวทางพัฒนาในภาคเรียนถัดไป</label><textarea name="suggestions" class="form-control" rows="3"><?= e($summary['suggestions'] ?? '') ?></textarea></div>
      <button name="status" value="draft" class="btn btn-outline-primary"><i class="bi bi-save"></i> บันทึกร่าง</button>
      <button name="status" value="submitted" class="btn btn-success" data-confirm-click="ยืนยันส่งสรุปรายงานสิ้นภาคเรียน?"><i class="bi bi-send-check"></i> บันทึกและส่งรายงาน</button>
      <span class="small text-muted ms-2"><?= $dept ? 'สรุปเฉพาะแผนกวิชา' . e($dept) : 'สรุปภาพรวมทั้งวิทยาลัย' ?></span>
    </form>
  <?php endif; ?>
  <div class="<?= $canSummarize ? 'print-only' : '' ?>">
    <?php if ($summary && ($summary['summary'] || $summary['problems'] || $summary['suggestions'])): ?>
      <?php foreach (['summary' => 'สรุปผลการดำเนินงาน', 'problems' => 'ปัญหาและอุปสรรค', 'suggestions' => 'ข้อเสนอแนะ / แนวทางพัฒนา'] as $k => $lbl): if (!$summary[$k]) continue; ?>
        <div class="fw-semibold mt-2"><?= $lbl ?></div><div class="prewrap"><?= e($summary[$k]) ?></div>
      <?php endforeach; ?>
      <div class="small text-muted mt-2">โดย <?= e(person($summary)) ?> · ปรับปรุง <?= thai_date($summary['updated_at'], true) ?></div>
    <?php else: ?>
      <div class="text-muted">หัวหน้างานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์ ยังไม่ได้เขียนสรุปรายงาน</div>
    <?php endif; ?>
  </div>
  </div>
</div>

<div class="mt-5 pt-3"><div class="row text-center g-4 small">
  <?php foreach ([['research_head', 'หัวหน้างานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์', 'ผู้รายงาน'], ['academic_head', 'รองผู้อำนวยการฝ่ายวิชาการ', 'ผู้ตรวจสอบ'], ['strategy_head', 'รองผู้อำนวยการฝ่ายยุทธศาสตร์และแผนงาน', 'ผู้ตรวจสอบ'], ['director', 'ผู้อำนวยการ' . college_name(), 'ผู้อนุมัติ']] as [$k, $pos, $role]): ?>
    <div class="col-6 col-md-3">ลงชื่อ...................................... <?= $role ?><br>(<?= e(setting($k) ?: '......................................') ?>)<br><?= e($pos) ?></div>
  <?php endforeach; ?>
</div></div>
<?php require APP_ROOT . '/inc/footer.php';
