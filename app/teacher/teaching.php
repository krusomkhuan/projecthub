<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
$me = require_login('teacher');
$sem = teaching_semester();
$semId = (int)($sem['id'] ?? 0);

if (is_post() && $sem) {
    csrf_check();
    $teaches = input('teaches') === '1';
    $selected = array_values(array_unique(array_map('intval', (array)($_POST['classrooms'] ?? []))));
    $subjectCode = mb_substr(input('subject_code'), 0, 30);
    $subjectName = mb_substr(input('subject_name', 'โครงงาน'), 0, 200) ?: 'โครงงาน';
    if (input('teaches') === '') {
        flash('danger', 'กรุณาเลือกว่าสอนรายวิชาโครงงานในภาคเรียนนี้หรือไม่');
        redirect('app/teacher/teaching.php');
    }
    if ($teaches && !$selected) {
        flash('danger', 'กรุณาเลือกกลุ่มผู้เรียนที่สอนอย่างน้อย 1 กลุ่ม');
        redirect('app/teacher/teaching.php');
    }
    $first = !q_val('SELECT 1 FROM teacher_declarations WHERE teacher_id = ? AND semester_id = ?', [$me['id'], $semId]);
    $warnings = [];
    $added = $removed = 0;

    db()->beginTransaction();
    $desired = $teaches ? $selected : [];
    // กลุ่มที่เคยเลือกไว้แต่ไม่ได้เลือกแล้ว: นำออก เว้นแต่มีกลุ่มโครงงานแล้ว
    foreach (q_all('SELECT s.id, s.classroom_id, c.name, (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects
                    FROM sections s JOIN classrooms c ON c.id = s.classroom_id WHERE s.teacher_id = ? AND s.semester_id = ? FOR UPDATE', [$me['id'], $semId]) as $s) {
        if (in_array((int)$s['classroom_id'], $desired, true)) {
            q('UPDATE sections SET subject_code = ?, subject_name = ? WHERE id = ?', [$subjectCode, $subjectName, $s['id']]);
            continue;
        }
        if ($s['projects']) {
            $warnings[] = "{$s['name']} มีกลุ่มโครงงานแล้ว {$s['projects']} กลุ่ม จึงยังคงไว้ (ติดต่อผู้ดูแลระบบหากต้องการย้ายให้ครูท่านอื่น)";
            continue;
        }
        q('DELETE FROM sections WHERE id = ?', [$s['id']]);
        $removed++;
    }
    // กลุ่มที่เลือกใหม่: เพิ่ม หากยังไม่มีครูท่านอื่นสอน
    foreach ($desired as $cid) {
        $owner = q_one('SELECT s.teacher_id, t.prefix, t.full_name, c.name FROM sections s JOIN users t ON t.id = s.teacher_id JOIN classrooms c ON c.id = s.classroom_id
                        WHERE s.semester_id = ? AND s.classroom_id = ? LIMIT 1 FOR UPDATE', [$semId, $cid]);
        if ($owner && (int)$owner['teacher_id'] === (int)$me['id']) {
            continue;
        }
        if ($owner) {
            $warnings[] = "{$owner['name']} มีครูผู้สอนแล้ว (" . person($owner) . ')';
            continue;
        }
        if (!q_val('SELECT 1 FROM classrooms WHERE id = ?', [$cid])) {
            continue;
        }
        q('INSERT INTO sections (semester_id, classroom_id, teacher_id, subject_code, subject_name) VALUES (?,?,?,?,?)', [$semId, $cid, $me['id'], $subjectCode, $subjectName]);
        $added++;
    }
    $count = (int)q_val('SELECT COUNT(*) FROM sections WHERE teacher_id = ? AND semester_id = ?', [$me['id'], $semId]);
    q('INSERT INTO teacher_declarations (teacher_id, semester_id, teaches_project) VALUES (?,?,?) ON DUPLICATE KEY UPDATE teaches_project = VALUES(teaches_project)',
        [$me['id'], $semId, (int)($count > 0)]);
    db()->commit();

    log_activity(($first ? 'แจ้ง' : 'แก้ไข') . 'รายวิชาที่สอน ' . semester_label($sem) . ': ' . ($count ? "สอนโครงงาน $count กลุ่ม (เพิ่ม $added นำออก $removed)" : 'ไม่ได้สอนโครงงาน'));
    flash('success', $count ? "บันทึกแล้ว: สอนรายวิชาโครงงาน $count กลุ่มผู้เรียน" : 'บันทึกแล้ว: ไม่ได้สอนรายวิชาโครงงานในภาคเรียนนี้');
    if ($warnings) {
        flash('warning', implode(' · ', $warnings));
    }
    redirect($first && $count ? 'app/teacher/sections.php' : 'app/teacher/teaching.php');
}

$decl = $sem ? q_one('SELECT * FROM teacher_declarations WHERE teacher_id = ? AND semester_id = ?', [$me['id'], $semId]) : null;
$mine = [];
$subject = ['subject_code' => '', 'subject_name' => 'โครงงาน'];
$taken = [];
if ($sem) {
    foreach (q_all('SELECT s.*, (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects FROM sections s WHERE s.teacher_id = ? AND s.semester_id = ?', [$me['id'], $semId]) as $s) {
        $mine[(int)$s['classroom_id']] = $s;
        $subject = $s;
    }
    foreach (q_all('SELECT s.classroom_id, t.prefix, t.full_name FROM sections s JOIN users t ON t.id = s.teacher_id WHERE s.semester_id = ? AND s.teacher_id <> ?', [$semId, $me['id']]) as $t) {
        $taken[(int)$t['classroom_id']] = person($t);
    }
}
$classrooms = q_all('SELECT * FROM classrooms ORDER BY level, code');
$teaches = $decl ? (bool)$decl['teaches_project'] : ($mine ? true : null);
$pageTitle = 'แจ้งรายวิชาที่สอน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><div><h1><i class="bi bi-pencil-square"></i> แจ้งรายวิชาที่สอน</h1>
  <div class="text-muted"><?= e(semester_label($sem)) ?><?= $decl ? ' · แจ้งล่าสุด ' . thai_date($decl['updated_at'], true) : '' ?></div></div></div>

<?php if (!$sem): ?>
  <div class="card card-body text-muted">ยังไม่เปิดภาคเรียน กรุณาติดต่อผู้ดูแลระบบ</div>
<?php else: ?>
<?php if (!$decl): ?>
  <div class="alert alert-info"><i class="bi bi-info-circle"></i> <strong>ยินดีต้อนรับ</strong> กรุณาแจ้งว่าท่านสอนรายวิชาโครงงานใน<?= e(semester_label($sem)) ?>หรือไม่ และสอนกลุ่มผู้เรียนใดบ้าง ระบบจะสร้างรายวิชาให้อัตโนมัติ แก้ไขได้ภายหลังที่เมนู "แจ้งรายวิชาที่สอน"</div>
<?php endif; ?>
<form method="post" id="teachForm"><?= csrf_field() ?>
  <div class="card mb-3"><div class="card-header">1. ท่านสอนรายวิชาโครงงานในภาคเรียนนี้หรือไม่?</div><div class="card-body">
    <div class="row g-2">
      <div class="col-md-6"><input type="radio" class="btn-check" name="teaches" id="tYes" value="1"<?= $teaches === true ? ' checked' : '' ?>>
        <label class="btn btn-outline-success w-100 py-3" for="tYes"><i class="bi bi-check-circle fs-4 d-block"></i>สอนรายวิชาโครงงาน</label></div>
      <div class="col-md-6"><input type="radio" class="btn-check" name="teaches" id="tNo" value="0"<?= $teaches === false ? ' checked' : '' ?>>
        <label class="btn btn-outline-secondary w-100 py-3" for="tNo"><i class="bi bi-x-circle fs-4 d-block"></i>ไม่ได้สอนรายวิชาโครงงาน</label></div>
    </div>
  </div></div>

  <div id="step2" class="<?= $teaches === true ? '' : 'd-none' ?>">
    <div class="card mb-3"><div class="card-header">2. รายวิชา</div><div class="card-body row g-2">
      <div class="col-md-4"><label class="form-label small">รหัสวิชา (ถ้ามี)</label><input name="subject_code" class="form-control" value="<?= e($subject['subject_code']) ?>" placeholder="เช่น 30000-8001"></div>
      <div class="col-md-8"><label class="form-label small">ชื่อวิชา</label><input name="subject_name" class="form-control" value="<?= e($subject['subject_name']) ?>"></div>
    </div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between flex-wrap gap-2"><span>3. เลือกกลุ่มผู้เรียนที่สอน</span><span class="small text-muted">เลือกแล้ว <strong id="selCount">0</strong> กลุ่ม</span></div>
      <div class="card-body">
        <div class="input-group mb-2"><span class="input-group-text"><i class="bi bi-search"></i></span>
          <input id="classSearch" class="form-control" placeholder="พิมพ์เลขกลุ่ม เช่น 2/1, ชื่อกลุ่ม เช่น ส.คอม, สาขา หรือรหัสกลุ่มเรียน" autocomplete="off">
          <button type="button" class="btn btn-outline-secondary" id="onlyMine">แสดงเฉพาะที่เลือก</button></div>
        <div id="classList" class="border rounded" style="max-height:420px;overflow:auto">
          <?php $level = null; foreach ($classrooms as $c): $cid = (int)$c['id'];
              $isMine = isset($mine[$cid]); $locked = $isMine && $mine[$cid]['projects'] > 0; $owner = $taken[$cid] ?? null; ?>
            <?php if ($c['level'] !== $level): $level = $c['level']; ?><div class="px-3 py-1 bg-light fw-semibold small border-bottom level-head" data-level="<?= e($level) ?>"><?= e($level ?: 'ไม่ระบุระดับ') ?></div><?php endif; ?>
            <label class="d-flex align-items-center gap-2 px-3 py-2 border-bottom class-item<?= $owner ? ' text-muted' : '' ?>" data-level="<?= e($c['level']) ?>"
                   data-search="<?= e(mb_strtolower(preg_replace('/[\s.]+/u', '', $c['code'] . $c['name'] . $c['level'] . $c['major'] . $c['department']))) ?>">
              <input type="checkbox" class="form-check-input mt-0 cls" name="classrooms[]" value="<?= $cid ?>" data-name="<?= e($c['name']) ?>"
                <?= $isMine ? ' checked' : '' ?><?= $owner ? ' disabled' : '' ?><?= $locked ? ' data-locked="1"' : '' ?>>
              <span class="flex-grow-1"><strong><?= e($c['name']) ?></strong> <span class="small text-muted">· <?= e($c['major'] ?: $c['department'] ?: '-') ?> · รหัส <?= e($c['code']) ?></span></span>
              <?php if ($owner): ?><span class="badge text-bg-light border">สอนโดย <?= e($owner) ?></span>
              <?php elseif ($locked): ?><span class="badge text-bg-warning" title="มีกลุ่มโครงงานแล้ว นำออกไม่ได้">มีกลุ่มโครงงาน <?= $mine[$cid]['projects'] ?> กลุ่ม</span><?php endif; ?>
            </label>
          <?php endforeach; ?>
          <div id="noMatch" class="p-3 text-muted d-none">ไม่พบกลุ่มเรียนที่ค้นหา</div>
        </div>
        <div class="small text-muted mt-2">กลุ่มสีเทามีครูท่านอื่นสอนแล้ว เลือกไม่ได้ · กลุ่มที่มีกลุ่มโครงงานแล้วนำออกไม่ได้ (ติดต่อผู้ดูแลระบบ)</div>
      </div>
    </div>
    <div class="card mb-3 border-success"><div class="card-body"><div class="small fw-semibold mb-1">ตรวจสอบก่อนบันทึก — กลุ่มผู้เรียนที่เลือก:</div><div id="selChips" class="d-flex flex-wrap gap-1"></div></div></div>
  </div>

  <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
  <?php if ($decl): ?><a href="<?= url('app/teacher/sections.php') ?>" class="btn btn-light btn-lg">ยกเลิก</a><?php endif; ?>
</form>
<?php endif; ?>
<?php
$pageScripts = <<<'JS'
<script>
(function () {
  var form = document.getElementById('teachForm'); if (!form) return;
  var step2 = document.getElementById('step2'), search = document.getElementById('classSearch'),
      boxes = [].slice.call(document.querySelectorAll('.cls')), chips = document.getElementById('selChips'),
      count = document.getElementById('selCount'), onlyBtn = document.getElementById('onlyMine'), only = false;
  form.querySelectorAll('input[name=teaches]').forEach(function (r) {
    r.addEventListener('change', function () { step2.classList.toggle('d-none', r.value !== '1' || !r.checked); });
  });
  function refreshChips() {
    var sel = boxes.filter(function (b) { return b.checked; });
    count.textContent = sel.length;
    chips.innerHTML = '';
    sel.forEach(function (b) { var s = document.createElement('span'); s.className = 'badge text-bg-success'; s.textContent = b.dataset.name; chips.appendChild(s); });
    if (!sel.length) chips.innerHTML = '<span class="text-muted small">ยังไม่ได้เลือก</span>';
  }
  function filter() {
    var q = search.value.toLowerCase().replace(/[\s.]+/g, ''), shown = 0, levels = {};
    document.querySelectorAll('.class-item').forEach(function (el) {
      var ok = (!q || el.dataset.search.indexOf(q) !== -1) && (!only || el.querySelector('.cls').checked);
      el.classList.toggle('d-none', !ok); el.classList.toggle('d-flex', ok);
      if (ok) { shown++; levels[el.dataset.level] = 1; }
    });
    document.querySelectorAll('.level-head').forEach(function (h) { h.classList.toggle('d-none', !levels[h.dataset.level]); });
    document.getElementById('noMatch').classList.toggle('d-none', shown > 0);
  }
  boxes.forEach(function (b) {
    b.addEventListener('click', function (ev) {
      if (b.dataset.locked && !b.checked) { ev.preventDefault(); alert('กลุ่มนี้มีกลุ่มโครงงานแล้ว นำออกไม่ได้ กรุณาติดต่อผู้ดูแลระบบ'); }
    });
    b.addEventListener('change', function () { refreshChips(); if (only) filter(); });
  });
  search.addEventListener('input', filter);
  search.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') ev.preventDefault(); });
  onlyBtn.addEventListener('click', function () { only = !only; onlyBtn.classList.toggle('active', only); filter(); });
  form.addEventListener('submit', function (ev) {
    var yes = document.getElementById('tYes').checked, no = document.getElementById('tNo').checked,
        n = boxes.filter(function (b) { return b.checked; }).length;
    if (!yes && !no) { ev.preventDefault(); alert('กรุณาเลือกว่าสอนรายวิชาโครงงานหรือไม่'); return; }
    if (yes && !n) { ev.preventDefault(); alert('กรุณาเลือกกลุ่มผู้เรียนที่สอนอย่างน้อย 1 กลุ่ม'); return; }
    if (!confirm(yes ? 'ยืนยันสอนรายวิชาโครงงาน ' + n + ' กลุ่มผู้เรียน?' : 'ยืนยันว่าไม่ได้สอนรายวิชาโครงงานในภาคเรียนนี้?')) ev.preventDefault();
  });
  refreshChips();
})();
</script>
JS;
require APP_ROOT . '/inc/footer.php';
