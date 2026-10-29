<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');
set_time_limit(300);

$result = null;
if (is_post()) {
    csrf_check();
    $type = input('type');
    $added = $updated = 0;
    $errors = [];
    // นำเข้าจำนวนมากใช้ cost ต่ำลงเพื่อความเร็ว ผู้ใช้ต้องเปลี่ยนรหัสผ่านเมื่อเข้าระบบครั้งแรกอยู่แล้ว
    $hash = fn(string $pw) => password_hash($pw, PASSWORD_DEFAULT, ['cost' => 8]);

    try {
        $rows = read_table_input();
    } catch (RuntimeException $ex) {
        $rows = [];
        $errors[] = $ex->getMessage();
    }
    if (!$rows && !$errors) {
        $errors[] = 'ไม่พบข้อมูล กรุณาเลือกไฟล์หรือวางข้อมูล';
    }

    db()->beginTransaction();
    if ($type === 'departments') {
        foreach (strip_header($rows, ['ชื่อแผนกวิชา', 'แผนกวิชา', 'ชื่อสาขาวิชา', 'สาขาวิชา', 'department', 'name']) as $i => $c) {
            [$name, $code] = array_pad($c, 2, '');
            if ($name === '') { continue; }
            if (q_val('SELECT id FROM departments WHERE name = ?', [$name])) {
                if ($code !== '') { q('UPDATE departments SET code = ? WHERE name = ?', [$code, $name]); }
                $updated++;
            } else {
                q('INSERT INTO departments (name, code) VALUES (?, ?)', [$name, $code]);
                $added++;
            }
        }
        $label = 'แผนกวิชา';
    } elseif ($type === 'majors') {
        $newDepts = 0;
        foreach (strip_header($rows, ['แผนกวิชา', 'ชื่อแผนกวิชา', 'department']) as $i => $c) {
            [$deptName, $majorName] = array_pad($c, 2, '');
            if ($deptName === '' || $majorName === '') { $errors[] = 'แถว ' . ($i + 1) . ': ต้องมีทั้งแผนกวิชาและชื่อสาขาวิชา'; continue; }
            $deptId = q_val('SELECT id FROM departments WHERE name = ?', [$deptName]);
            if (!$deptId) {
                q('INSERT INTO departments (name) VALUES (?)', [$deptName]);
                $deptId = db()->lastInsertId();
                $newDepts++;
            }
            if (q('INSERT IGNORE INTO majors (department_id, name) VALUES (?, ?)', [$deptId, $majorName])->rowCount()) {
                $added++;
            } else {
                $updated++;
            }
        }
        $label = "สาขาวิชา (สร้างแผนกวิชาใหม่ $newDepts แผนก)";
    } elseif ($type === 'teachers') {
        $knownDepts = array_flip(array_column(q_all('SELECT name FROM departments'), 'name'));
        $unknownDepts = [];
        $clean = fn(string $v) => trim((string)preg_replace('/\s+/u', ' ', $v));
        foreach (strip_header($rows, ['รหัสประจำตัว', 'รหัสครู', 'ชื่อผู้ใช้', 'username']) as $i => $c) {
            [$username, $prefix, $first, $last, $dept, $major, $position, $education, $duty] = array_map($clean, array_pad($c, 9, ''));
            $name = trim("$first $last");
            if ($username === '' || $first === '') { $errors[] = 'แถว ' . ($i + 1) . ': ไม่มีรหัสประจำตัวหรือชื่อ'; continue; }
            if ($dept !== '' && $knownDepts && !isset($knownDepts[$dept])) { $unknownDepts[$dept] = true; }
            $ex = q_one('SELECT id, role FROM users WHERE username = ?', [$username]);
            if ($ex && $ex['role'] === 'student') { $errors[] = "$username ซ้ำกับรหัสนักศึกษา"; continue; }
            $data = [$prefix, $name, $dept, $major, $position, $education, $duty];
            if ($ex) {
                q('UPDATE users SET prefix=?, full_name=?, department=?, major=?, position=?, education=?, duty=? WHERE id=?', [...$data, $ex['id']]);
                $updated++;
            } else {
                q('INSERT INTO users (prefix, full_name, department, major, position, education, duty, username, role, password_hash) VALUES (?,?,?,?,?,?,?,?,"teacher",?)',
                    [...$data, $username, $hash($username)]);
                $added++;
            }
        }
        if ($unknownDepts) {
            $errors[] = 'นำเข้าแล้ว แต่ชื่อแผนกวิชาต่อไปนี้ไม่ตรงกับข้อมูลแผนกวิชา: ' . implode(', ', array_keys($unknownDepts));
        }
        $label = 'ครูผู้สอน';
    } elseif ($type === 'classrooms') {
        $noAdvisor = [];
        foreach (strip_header($rows, ['รหัสกลุ่มเรียน', 'รหัสกลุ่ม', 'รหัส', 'กลุ่มเรียน', 'code']) as $i => $c) {
            [$code, $name, $level, $major, $year, $advisor] = array_pad($c, 6, '');
            if ($code === '') { $errors[] = 'แถว ' . ($i + 1) . ': ไม่มีรหัสกลุ่มเรียน'; continue; }
            $advisor = (string)preg_replace('/\s+/u', ' ', $advisor);
            // แผนกวิชาได้จากสาขาวิชา (หรือชื่อแผนกโดยตรง)
            $dept = (string)(q_val('SELECT d.name FROM majors m JOIN departments d ON d.id = m.department_id WHERE m.name = ? ORDER BY m.id LIMIT 1', [$major])
                ?? q_val('SELECT name FROM departments WHERE name = ?', [$major]) ?? '');
            $advisorId = $advisor === '' ? null
                : q_val('SELECT id FROM users WHERE role = "teacher" AND (full_name = ? OR CONCAT(prefix, full_name) = ?) LIMIT 1', [$advisor, $advisor]);
            if ($advisor !== '' && !$advisorId) { $noAdvisor[$advisor] = true; }
            $data = [$name ?: $code, $level, $dept, $major, ctype_digit($year) ? (int)$year : null, $advisor, $advisorId];
            $ex = q_val('SELECT id FROM classrooms WHERE code = ?', [$code]);
            if ($ex) {
                q('UPDATE classrooms SET name=?, level=?, department=?, major=?, academic_year=?, advisor_name=?, advisor_id=? WHERE id=?', [...$data, $ex]);
                $updated++;
            } else {
                q('INSERT INTO classrooms (name, level, department, major, academic_year, advisor_name, advisor_id, code) VALUES (?,?,?,?,?,?,?,?)', [...$data, $code]);
                $added++;
            }
        }
        if ($noAdvisor) {
            $errors[] = 'บันทึกชื่อครูที่ปรึกษาแล้ว แต่ยังไม่พบบัญชีครูชื่อ: ' . implode(', ', array_keys($noAdvisor)) . ' (นำเข้าครูผู้สอนก่อน แล้วนำเข้ากลุ่มเรียนซ้ำเพื่อเชื่อมบัญชี)';
        }
        $label = 'กลุ่มเรียน';
    } elseif ($type === 'students') {
        $classCache = [];
        $newClasses = 0;
        foreach (strip_header($rows, ['รหัสนักศึกษา', 'รหัสนักเรียน', 'รหัส', 'รหัสประจำตัว', 'student_code', 'student id', 'code']) as $i => $c) {
            [$code, $prefix, $name, $classCode, $className, $level, $dept] = array_pad($c, 7, '');
            if ($code === '' || $name === '' || $classCode === '') { $errors[] = 'แถว ' . ($i + 1) . ': ข้อมูลไม่ครบ (รหัส, ชื่อ, รหัสกลุ่มเรียน)'; continue; }
            if (!isset($classCache[$classCode])) {
                $cls = q_one('SELECT id FROM classrooms WHERE code = ?', [$classCode]);
                if ($cls) {
                    if ($className !== '') {
                        q('UPDATE classrooms SET name = ?, level = IF(? = "", level, ?), department = IF(? = "", department, ?) WHERE id = ?', [$className, $level, $level, $dept, $dept, $cls['id']]);
                    }
                    $classCache[$classCode] = (int)$cls['id'];
                } else {
                    q('INSERT INTO classrooms (code, name, level, department) VALUES (?,?,?,?)', [$classCode, $className ?: $classCode, $level, $dept]);
                    $classCache[$classCode] = (int)db()->lastInsertId();
                    $newClasses++;
                }
            }
            $ex = q_one('SELECT id, role FROM users WHERE student_code = ? OR username = ?', [$code, $code]);
            if ($ex && $ex['role'] !== 'student') { $errors[] = "$code ซ้ำกับชื่อผู้ใช้ของบุคลากร"; continue; }
            if ($ex) {
                q('UPDATE users SET prefix=?, full_name=?, classroom_id=?, active=1 WHERE id=?', [$prefix, $name, $classCache[$classCode], $ex['id']]);
                $updated++;
            } else {
                q('INSERT INTO users (username, student_code, prefix, full_name, role, classroom_id, password_hash) VALUES (?,?,?,?,"student",?,?)',
                    [$code, $code, $prefix, $name, $classCache[$classCode], $hash($code)]);
                $added++;
            }
        }
        $label = "นักเรียน (สร้างกลุ่มเรียนใหม่ $newClasses กลุ่ม)";
    } elseif ($type === 'sections') {
        $semId = int_param('semester');
        foreach (strip_header($rows, ['ชื่อผู้ใช้ครู', 'ชื่อผู้ใช้', 'username', 'ครู', 'teacher']) as $i => $c) {
            [$username, $classCode, $subCode, $subName] = array_pad($c, 4, '');
            $tid = q_val('SELECT id FROM users WHERE username = ? AND role = "teacher"', [$username]);
            $cid = q_val('SELECT id FROM classrooms WHERE code = ?', [$classCode]);
            if (!$tid || !$cid) { $errors[] = 'แถว ' . ($i + 1) . ': ไม่พบ' . (!$tid ? "ครู $username" : "กลุ่มเรียน $classCode"); continue; }
            $ex = q_val('SELECT id FROM sections WHERE semester_id = ? AND classroom_id = ? AND subject_code = ?', [$semId, $cid, $subCode]);
            if ($ex) {
                q('UPDATE sections SET teacher_id = ?, subject_name = ? WHERE id = ?', [$tid, $subName ?: 'โครงงาน', $ex]);
                $updated++;
            } else {
                q('INSERT INTO sections (semester_id, classroom_id, teacher_id, subject_code, subject_name) VALUES (?,?,?,?,?)', [$semId, $cid, $tid, $subCode, $subName ?: 'โครงงาน']);
                $added++;
            }
        }
        $label = 'รายวิชาโครงงาน';
    }
    db()->commit();
    if (isset($label)) {
        log_activity("นำเข้า$label เพิ่ม $added ปรับปรุง $updated");
        $result = compact('label', 'added', 'updated', 'errors');
    }
}
$sem = active_semester();
$types = [
    'departments' => ['building', 'แผนกวิชา', 'นำเข้าแผนกวิชา', 'ชื่อแผนกวิชา | รหัสแผนก (ถ้ามี)',
                     'ชื่อแผนกวิชาที่มีอยู่แล้วจะไม่ถูกสร้างซ้ำ'],
    'majors'     => ['diagram-2', 'สาขาวิชา', 'นำเข้าสาขาวิชา', 'แผนกวิชา | ชื่อสาขาวิชา',
                     'แผนกวิชาที่ยังไม่มีจะถูกสร้างให้อัตโนมัติ · สาขาวิชาที่ซ้ำในแผนกเดียวกันจะถูกข้าม'],
    'teachers'   => ['person', 'ครูผู้สอน', 'นำเข้าครูผู้สอนวิชาโครงงาน', 'รหัสประจำตัว | คำนำหน้า | ชื่อ | นามสกุล | แผนกวิชา | สาขาวิชา | ตำแหน่ง | วุฒิการศึกษา | หน้าที่พิเศษ',
                     'รหัสประจำตัวใช้เป็นชื่อผู้ใช้และรหัสผ่านเริ่มต้น · รหัสซ้ำจะถูกปรับปรุงข้อมูล'],
    'classrooms' => ['people', 'กลุ่มเรียน', 'นำเข้ากลุ่มเรียน', 'รหัสกลุ่มเรียน | ชื่อกลุ่ม | ระดับชั้น | สาขาวิชา | ปีการศึกษา | ครูที่ปรึกษา',
                     'แผนกวิชาได้จากสาขาวิชาอัตโนมัติ (นำเข้าสาขาวิชาก่อน) · ครูที่ปรึกษาเชื่อมกับบัญชีครูเมื่อชื่อตรงกัน · รหัสกลุ่มเรียนซ้ำจะถูกปรับปรุง'],
    'students'   => ['mortarboard', 'นักเรียน นักศึกษา', 'นำเข้านักเรียน นักศึกษา (ตามกลุ่มเรียน)', 'รหัสนักศึกษา | คำนำหน้า | ชื่อ-สกุล | รหัสกลุ่มเรียน | ชื่อกลุ่มเรียน | ระดับ | แผนกวิชา',
                     'กลุ่มเรียนที่ยังไม่มีจะถูกสร้างให้อัตโนมัติ · รหัสผ่านเริ่มต้น = รหัสนักศึกษา'],
    'sections'   => ['journal-bookmark', 'รายวิชาโครงงาน', 'นำเข้าการมอบหมายรายวิชาโครงงาน (ครู ↔ กลุ่มเรียน)', 'ชื่อผู้ใช้ครู | รหัสกลุ่มเรียน | รหัสวิชา | ชื่อวิชา',
                     'ต้องนำเข้าครูผู้สอนและกลุ่มเรียนก่อน'],
];
$type = isset($types[input('type')]) ? input('type') : array_key_first($types);
$_GET['type'] = $type; // ให้เมนูด้านข้างไฮไลต์รายการที่ถูกต้อง
[$icon, $short, $title, $cols, $note] = $types[$type];
$counts = [
    'departments' => q_val('SELECT COUNT(*) FROM departments'),
    'majors'     => q_val('SELECT COUNT(*) FROM majors'),
    'teachers'   => q_val('SELECT COUNT(*) FROM users WHERE role = "teacher"'),
    'classrooms' => q_val('SELECT COUNT(*) FROM classrooms'),
    'students'   => q_val('SELECT COUNT(*) FROM users WHERE role = "student"'),
    'sections'   => q_val('SELECT COUNT(*) FROM sections WHERE semester_id = ?', [$sem['id'] ?? 0]),
];
$pageTitle = 'นำเข้า' . $short;
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1><i class="bi bi-<?= $icon ?>"></i> <?= e($title) ?></h1></div>
<ul class="nav nav-pills flex-wrap gap-1 mb-3">
  <?php foreach ($types as $k => [$ic, $lbl]): ?>
    <li class="nav-item"><a class="nav-link<?= $k === $type ? ' active' : '' ?>" href="?type=<?= $k ?>"><i class="bi bi-<?= $ic ?>"></i> <?= e($lbl) ?> <span class="badge text-bg-light border"><?= (int)$counts[$k] ?></span></a></li>
  <?php endforeach; ?>
</ul>
<?php if ($result): ?>
  <div class="alert alert-<?= $result['errors'] ? 'warning' : 'success' ?>">
    <strong>นำเข้า<?= e($result['label']) ?>:</strong> เพิ่มใหม่ <?= $result['added'] ?> รายการ · ปรับปรุง <?= $result['updated'] ?> รายการ
    <?php if ($result['errors']): ?><ul class="mb-0 mt-2 small"><?php foreach (array_slice($result['errors'], 0, 30) as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="type" value="<?= $type ?>">
      <?php if ($type === 'sections'): ?>
        <div class="mb-3" style="max-width:280px"><label class="form-label">ภาคเรียน</label><select name="semester" class="form-select">
          <?php foreach (all_semesters() as $s): ?><option value="<?= $s['id'] ?>"<?= (int)$s['id'] === (int)($sem['id'] ?? 0) ? ' selected' : '' ?>><?= e(semester_label($s)) ?></option><?php endforeach; ?>
        </select></div>
      <?php endif; ?>
      <div class="mb-3"><label class="form-label">ไฟล์ Excel (.xlsx) หรือ CSV</label><input type="file" name="file" accept=".xlsx,.csv,.txt" class="form-control"></div>
      <div class="mb-3"><label class="form-label">หรือคัดลอกตารางจาก Excel แล้ววางที่นี่</label><textarea name="paste" class="form-control font-monospace" rows="8"></textarea></div>
      <button class="btn btn-success"><i class="bi bi-upload"></i> นำเข้า<?= e($short) ?></button>
    </form>
  </div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header">รูปแบบข้อมูล</div><div class="card-body small">
    <div class="mb-2">คอลัมน์ตามลำดับ:</div>
    <ol class="ps-3"><?php foreach (explode(' | ', $cols) as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ol>
    <p class="text-muted"><?= e($note) ?></p>
    <a class="btn btn-outline-primary btn-sm" href="<?= url('app/admin/template.php?type=' . $type) ?>"><i class="bi bi-download"></i> ดาวน์โหลดไฟล์ตัวอย่าง</a>
    <hr>
    <p class="text-muted mb-0">รองรับไฟล์ Excel (.xlsx — อ่านแผ่นงานแรก) และ CSV ทั้ง UTF-8 / TIS-620 · แถวหัวตารางจะถูกข้ามอัตโนมัติ<br>
      ลำดับที่แนะนำ: แผนกวิชา → สาขาวิชา → ครูผู้สอน → กลุ่มเรียน → นักเรียน นักศึกษา → รายวิชาโครงงาน<br>กลุ่มโครงงานของผู้เรียน นำเข้าได้ที่หน้ารายวิชาของแต่ละกลุ่มเรียน</p>
  </div></div></div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
