<?php
declare(strict_types=1);

const ROLE_LABELS = [
    'admin'    => 'ผู้ดูแลระบบ',
    'research' => 'หัวหน้างานวิจัย พัฒนานวัตกรรมและสิ่งประดิษฐ์',
    'academic' => 'ฝ่ายวิชาการ',
    'strategy' => 'ฝ่ายยุทธศาสตร์และแผนงาน',
    'teacher'  => 'ครูผู้สอนวิชาโครงงาน',
    'student'  => 'นักเรียน นักศึกษา',
];

const PROJECT_STATUS = [
    'proposed'  => ['รอพิจารณาหัวข้อ', 'warning'],
    'revise'    => ['ให้แก้ไขหัวข้อ', 'danger'],
    'approved'  => ['อนุมัติ / กำลังดำเนินการ', 'primary'],
    'completed' => ['เสร็จสมบูรณ์', 'success'],
    'cancelled' => ['ยกเลิก', 'secondary'],
];

const REPORT_STATUS = [
    'submitted' => ['รอตรวจ', 'warning'],
    'approved'  => ['ผ่าน', 'success'],
    'revise'    => ['ให้แก้ไข', 'danger'],
];

const REVIEW_RESULT = [
    'pass'    => ['ผ่าน / เหมาะสม', 'success'],
    'improve' => ['ควรปรับปรุง', 'warning'],
    'fail'    => ['ไม่ผ่าน', 'danger'],
];

const PROJECT_CATEGORIES = [
    'สิ่งประดิษฐ์', 'นวัตกรรม', 'งานวิจัย', 'ซอฟต์แวร์ / แอปพลิเคชัน',
    'ธุรกิจ / บริการ', 'สร้างสรรค์ / ศิลปะ', 'ปรับปรุง / พัฒนาผลิตภัณฑ์', 'อื่น ๆ',
];

const GRADE_OPTIONS = ['4', '3.5', '3', '2.5', '2', '1.5', '1', '0', 'ขร', 'มส', 'มผ'];

const ALLOWED_UPLOADS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar', 'mp4'];

// เวอร์ชันของระบบ: ปรับเลขทุกครั้งที่ออกรุ่นใหม่ (หลัก.รอง.แก้ไข)
const APP_VERSION = '1.0.1';
const APP_RELEASE_DATE = '2026-10-30';
const DEVELOPER_CREDIT ='ผู้พัฒนาระบบ : ครูสมควร สากำ แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล วิทยาลัยการอาชีพโพนทอง';

/** @param mixed $s */
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') || str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

function back(string $fallback = 'app/index.php'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('คำขอไม่ถูกต้อง (CSRF) กรุณาย้อนกลับแล้วลองใหม่');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

/** @return mixed */
function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function input(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $_GET[$key] ?? $default));
}

function int_param(string $key, int $default = 0): int
{
    return (int)($_POST[$key] ?? $_GET[$key] ?? $default);
}

function log_activity(string $action): void
{
    q('INSERT INTO activity_logs (user_id, action) VALUES (?, ?)', [current_user()['id'] ?? null, mb_substr($action, 0, 255)]);
}

function setting(string $name, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT name, value FROM settings') as $r) {
            $cache[$r['name']] = (string)$r['value'];
        }
    }
    return $cache[$name] ?? $default;
}

function college_name(): string
{
    return setting('college_name', 'วิทยาลัยการอาชีพโพนทอง');
}

const THAI_MONTHS = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

function thai_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '-';
    }
    $t = strtotime($date);
    $s = (int)date('j', $t) . ' ' . THAI_MONTHS[(int)date('n', $t)] . ' ' . ((int)date('Y', $t) + 543);
    return $withTime ? $s . ' ' . date('H:i', $t) . ' น.' : $s;
}

function badge(array $map, ?string $key): string
{
    if ($key === null || !isset($map[$key])) {
        return '<span class="badge text-bg-light border">-</span>';
    }
    [$label, $color] = $map[$key];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}

function person(array $u, string $prefix = ''): string
{
    return trim(($u[$prefix . 'prefix'] ?? '') . ($u[$prefix . 'full_name'] ?? ''));
}

function active_semester(): ?array
{
    static $sem = false;
    if ($sem === false) {
        $sem = q_one('SELECT * FROM semesters WHERE is_active = 1 ORDER BY academic_year DESC, term DESC LIMIT 1')
            ?? q_one('SELECT * FROM semesters ORDER BY academic_year DESC, term DESC LIMIT 1');
    }
    return $sem;
}

function semester_label(?array $s): string
{
    return $s ? "ภาคเรียนที่ {$s['term']}/{$s['academic_year']}" : '-';
}

function all_semesters(): array
{
    return q_all('SELECT * FROM semesters ORDER BY academic_year DESC, term DESC');
}

function grade_from_score(?float $score): string
{
    if ($score === null) {
        return '';
    }
    foreach ([80 => '4', 75 => '3.5', 70 => '3', 65 => '2.5', 60 => '2', 55 => '1.5', 50 => '1'] as $min => $grade) {
        if ($score >= $min) {
            return $grade;
        }
    }
    return '0';
}

/** อ่านข้อมูลแบบตาราง จากไฟล์ CSV ที่อัปโหลด หรือข้อความที่วางจาก Excel (คั่นด้วย Tab) */
function read_table_input(string $fileField = 'file', string $textField = 'paste'): array
{
    $raw = '';
    $upload = $_FILES[$fileField] ?? null;
    if (!empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name']) && strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION)) === 'xlsx') {
        return xlsx_rows($upload['tmp_name']);
    }
    if (!empty($_FILES[$fileField]['tmp_name']) && is_uploaded_file($_FILES[$fileField]['tmp_name'])) {
        $raw = (string)file_get_contents($_FILES[$fileField]['tmp_name']);
    } elseif (trim((string)($_POST[$textField] ?? '')) !== '') {
        $raw = (string)$_POST[$textField];
    }
    if ($raw === '') {
        return [];
    }
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    if (!mb_check_encoding($raw, 'UTF-8')) {
        // Excel ภาษาไทยบน Windows มักบันทึก CSV เป็น TIS-620 / Windows-874
        $raw = (string)iconv('CP874', 'UTF-8//IGNORE', $raw);
    }
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $firstLine = strtok($raw, "\n") ?: '';
    $delim = substr_count($firstLine, "\t") > 0 ? "\t" : (substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',');

    $rows = [];
    $fh = fopen('php://memory', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    while (($cols = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
        $cols = array_map(fn($c) => trim((string)$c), $cols);
        if (implode('', $cols) === '') {
            continue;
        }
        $rows[] = $cols;
    }
    fclose($fh);
    return $rows;
}

/** ตัดแถวหัวตารางออก หากคอลัมน์แรกตรงกับชื่อหัวตารางที่รู้จัก */
function strip_header(array $rows, array $headerWords): array
{
    $first = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $rows[0][0] ?? '')));
    if ($rows && in_array($first, array_map('mb_strtolower', $headerWords), true)) {
        array_shift($rows);
    }
    return $rows;
}

function send_csv(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $r) {
        fputcsv($out, $r, ',', '"', '');
    }
    fclose($out);
    exit;
}

/** บันทึกไฟล์แนบ คืนค่า [ชื่อเดิม, path ภายใน uploads] หรือ null หากไม่มีไฟล์ */
function save_upload(string $field, string $subdir): ?array
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . $f['error'] . ')');
    }
    $max = (int)$GLOBALS['config']['max_upload_mb'] * 1024 * 1024;
    if ($f['size'] > $max) {
        throw new RuntimeException('ไฟล์มีขนาดเกิน ' . $GLOBALS['config']['max_upload_mb'] . ' MB');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_UPLOADS, true)) {
        throw new RuntimeException('ไม่อนุญาตไฟล์ประเภท .' . $ext);
    }
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $stored = $subdir . '/' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
    }
    return [mb_substr(basename($f['name']), 0, 255), $stored];
}

function paginate(int $total, int $perPage = 30): array
{
    $page = max(1, int_param('page', 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    return [$page, $pages, ($page - 1) * $perPage, $perPage];
}

function pagination_links(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $qs = $_GET;
    $html = '<nav><ul class="pagination pagination-sm flex-wrap">';
    for ($i = 1; $i <= $pages; $i++) {
        $qs['page'] = $i;
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($qs)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

function select_options(array $options, ?string $selected, bool $assoc = true): string
{
    $html = '';
    foreach ($options as $k => $v) {
        $val = $assoc ? (string)$k : (string)$v;
        $html .= '<option value="' . e($val) . '"' . ($val === (string)$selected ? ' selected' : '') . '>' . e(is_array($v) ? $v[0] : (string)$v) . '</option>';
    }
    return $html;
}

function find_project(int $id): ?array
{
    return q_one('SELECT p.*, s.semester_id, s.teacher_id, s.classroom_id, s.subject_code, s.subject_name,
                         c.name AS classroom_name, c.department, c.level,
                         t.prefix AS t_prefix, t.full_name AS t_full_name,
                         a.prefix AS a_prefix, a.full_name AS a_full_name
                  FROM projects p
                  JOIN sections s ON s.id = p.section_id
                  JOIN classrooms c ON c.id = s.classroom_id
                  JOIN users t ON t.id = s.teacher_id
                  LEFT JOIN users a ON a.id = p.advisor_id
                  WHERE p.id = ?', [$id]);
}

function project_members(int $projectId): array
{
    return q_all('SELECT u.id, u.prefix, u.full_name, u.student_code, m.is_leader
                  FROM project_members m JOIN users u ON u.id = m.student_id
                  WHERE m.project_id = ? ORDER BY m.is_leader DESC, u.student_code', [$projectId]);
}

/** กำหนดการรายงานของภาคเรียน พร้อมรายงานที่โครงงานส่งแล้ว (key 'report') */
function project_periods(int $projectId, int $semesterId): array
{
    $periods = q_all('SELECT * FROM report_periods WHERE semester_id = ? ORDER BY seq, id', [$semesterId]);
    $reports = [];
    foreach (q_all('SELECT * FROM progress_reports WHERE project_id = ?', [$projectId]) as $r) {
        $reports[$r['period_id']] = $r;
    }
    foreach ($periods as &$p) {
        $p['report'] = $reports[$p['id']] ?? null;
    }
    return $periods;
}

/** สถานะการส่งของรอบรายงาน: [label, color] */
function period_state(array $period): array
{
    $r = $period['report'];
    if ($r) {
        return REPORT_STATUS[$r['status']];
    }
    if ($period['due_date'] && $period['due_date'] < date('Y-m-d')) {
        return ['ยังไม่ส่ง (เลยกำหนด)', 'danger'];
    }
    return ['ยังไม่ส่ง', 'light'];
}

function members_text(int $projectId): string
{
    return implode(', ', array_map(fn($m) => person($m) . ' (' . $m['student_code'] . ')', project_members($projectId)));
}

/**
 * นำเข้ากลุ่มโครงงาน: แถวละ [กลุ่มที่, ชื่อโครงงาน, รหัสนักศึกษา, หัวหน้า]
 * แถวที่เลขกลุ่มเดียวกันรวมเป็นโครงงานเดียว คืนค่า [จำนวนโครงงานที่สร้าง, รายการที่ข้าม]
 */
function import_project_teams(array $sec, array $rows): array
{
    $students = [];
    foreach (q_all('SELECT id, student_code FROM users WHERE role = "student" AND classroom_id = ?', [$sec['classroom_id']]) as $s) {
        $students[$s['student_code']] = (int)$s['id'];
    }
    $taken = [];
    foreach (q_all('SELECT m.student_id FROM project_members m JOIN projects p ON p.id = m.project_id JOIN sections s ON s.id = p.section_id
                    WHERE s.semester_id = ? AND p.status <> "cancelled"', [$sec['semester_id']]) as $r) {
        $taken[(int)$r['student_id']] = true;
    }
    $groups = [];
    $skipped = [];
    foreach (strip_header($rows, ['กลุ่มที่', 'กลุ่ม', 'group', 'ลำดับ', 'no']) as $i => $c) {
        [$g, $title, $code, $leader] = array_pad($c, 4, '');
        if ($g === '' || $code === '') { $skipped[] = 'แถว ' . ($i + 1) . ': ข้อมูลไม่ครบ'; continue; }
        if (!isset($students[$code])) { $skipped[] = "รหัส $code ไม่อยู่ในกลุ่มเรียนนี้"; continue; }
        if (isset($taken[$students[$code]])) { $skipped[] = "รหัส $code มีโครงงานแล้ว"; continue; }
        $groups[$g]['title'] = $groups[$g]['title'] ?? ($title !== '' ? $title : 'โครงงานกลุ่มที่ ' . $g);
        $groups[$g]['members'][$students[$code]] = in_array(mb_strtolower($leader), ['1', 'y', 'yes', 'หัวหน้า', 'ใช่', '✓'], true);
        $taken[$students[$code]] = true;
    }
    db()->beginTransaction();
    foreach ($groups as $g) {
        q('INSERT INTO projects (section_id, title, advisor_id, status, created_by) VALUES (?, ?, ?, "approved", ?)', [$sec['id'], mb_substr($g['title'], 0, 255), $sec['teacher_id'], uid()]);
        $pid = (int)db()->lastInsertId();
        $hasLeader = in_array(true, $g['members'], true);
        $first = true;
        foreach ($g['members'] as $sid => $isLeader) {
            q('INSERT INTO project_members (project_id, student_id, is_leader) VALUES (?,?,?)', [$pid, $sid, (int)($isLeader || (!$hasLeader && $first))]);
            $first = false;
        }
    }
    db()->commit();
    log_activity('นำเข้ากลุ่มโครงงาน ' . count($groups) . " กลุ่ม ({$sec['code']})");
    return [count($groups), $skipped];
}

/** อ่านไฟล์จาก ZIP (ไม่ต้องใช้ส่วนขยาย zip ของ PHP) คืนค่า [ชื่อไฟล์ => เนื้อหา] เฉพาะที่ต้องการ */
function zip_entries(string $data, callable $want): array
{
    $eocd = strrpos($data, "PK\x05\x06");
    if ($eocd === false) {
        throw new RuntimeException('ไฟล์ Excel ไม่ถูกต้อง');
    }
    $info = unpack('vdisk/vcdisk/ventries/vtotal/Vsize/Voffset', substr($data, $eocd + 4, 16));
    $pos = $info['offset'];
    $out = [];
    for ($i = 0; $i < $info['total']; $i++) {
        $h = unpack('Vsig/vver/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen/vclen/vdisk/viattr/Veattr/Vlocal', substr($data, $pos, 46));
        $name = substr($data, $pos + 46, $h['nlen']);
        $pos += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
        if (!$want($name)) {
            continue;
        }
        $l = unpack('vnlen/velen', substr($data, $h['local'] + 26, 4));
        $raw = substr($data, $h['local'] + 30 + $l['nlen'] + $l['elen'], $h['csize']);
        $out[$name] = $h['method'] === 8 ? (string)gzinflate($raw) : $raw;
    }
    return $out;
}

/** อ่านแผ่นงานแรกของไฟล์ .xlsx เป็นอาร์เรย์ของแถว */
function xlsx_rows(string $path): array
{
    $files = zip_entries((string)file_get_contents($path), fn($n) => in_array($n, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml'], true) || str_starts_with($n, 'xl/worksheets/'));
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if (isset($files['xl/workbook.xml'], $files['xl/_rels/workbook.xml.rels'])) {
        $wb = simplexml_load_string($files['xl/workbook.xml']);
        $rid = (string)($wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
        foreach (simplexml_load_string($files['xl/_rels/workbook.xml.rels'])->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $sheetPath = 'xl/' . ltrim(str_replace('/xl/', '', (string)$rel['Target']), '/');
            }
        }
    }
    if (!isset($files[$sheetPath])) {
        throw new RuntimeException('ไม่พบแผ่นงานในไฟล์ Excel');
    }
    $text = fn(SimpleXMLElement $n) => implode('', array_map('strval', $n->xpath('.//*[local-name()="t"]') ?: []));
    $shared = [];
    if (isset($files['xl/sharedStrings.xml'])) {
        foreach (simplexml_load_string($files['xl/sharedStrings.xml'])->si as $si) {
            $shared[] = $text($si);
        }
    }
    $rows = [];
    foreach (simplexml_load_string($files[$sheetPath])->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $col = 0;
            foreach (str_split(preg_replace('/\d+/', '', (string)$c['r'])) as $ch) {
                $col = $col * 26 + ord($ch) - 64;
            }
            $t = (string)$c['t'];
            if ($t === 's') {
                $cells[$col - 1] = $shared[(int)$c->v] ?? '';
            } elseif ($t === 'inlineStr') {
                $cells[$col - 1] = $text($c->is);
            } else {
                $cells[$col - 1] = (string)$c->v;
            }
        }
        if ($cells) {
            $line = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $k => $v) {
                $line[$k] = trim($v);
            }
            $rows[] = $line;
        }
    }
    return array_values(array_filter($rows, fn($r) => implode('', $r) !== ''));
}

const FORM_CATEGORIES = ['แบบฟอร์มโครงงาน', 'แบบรายงานความก้าวหน้า', 'แบบประเมิน', 'ตัวอย่างรูปเล่มโครงงาน', 'คู่มือ / เอกสารอ้างอิง', 'อื่น ๆ'];

/** ส่งไฟล์แบบฟอร์มให้ดาวน์โหลด และนับจำนวนครั้ง */
function send_form_file(array $f): void
{
    $path = UPLOAD_DIR . '/' . $f['file_path'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('ไม่พบไฟล์');
    }
    q('UPDATE form_files SET download_count = download_count + 1 WHERE id = ?', [$f['id']]);
    header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($f['file_name']));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function file_icon(string $name): string
{
    $icons = [
        'pdf' => 'file-earmark-pdf text-danger',
        'doc' => 'file-earmark-word text-primary', 'docx' => 'file-earmark-word text-primary',
        'xls' => 'file-earmark-excel text-success', 'xlsx' => 'file-earmark-excel text-success',
        'ppt' => 'file-earmark-ppt text-warning', 'pptx' => 'file-earmark-ppt text-warning',
        'zip' => 'file-earmark-zip text-secondary', 'rar' => 'file-earmark-zip text-secondary',
        'jpg' => 'file-earmark-image text-info', 'jpeg' => 'file-earmark-image text-info', 'png' => 'file-earmark-image text-info',
    ];
    return $icons[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'file-earmark';
}

/**
 * นำเข้านักเรียนเข้ากลุ่มเรียนที่กำหนด: แถวละ [รหัสนักศึกษา, คำนำหน้า, ชื่อ-สกุล(, นามสกุล)]
 * นักเรียนที่อยู่กลุ่มเรียนอื่นแล้วจะไม่ถูกย้าย คืนค่า [เพิ่มใหม่, ปรับปรุง, รายการที่ข้าม]
 */
function import_class_students(array $classroom, array $rows): array
{
    $added = $updated = 0;
    $skipped = [];
    $clean = fn(string $v) => trim((string)preg_replace('/\s+/u', ' ', $v));
    foreach (strip_header($rows, ['รหัสนักศึกษา', 'รหัสนักเรียน', 'รหัส', 'รหัสประจำตัว', 'student_code', 'student id', 'code']) as $i => $c) {
        [$code, $prefix, $first, $last] = array_map($clean, array_pad($c, 4, ''));
        $name = trim("$first $last");
        if ($code === '' || $first === '') { $skipped[] = 'แถว ' . ($i + 1) . ': ไม่มีรหัสหรือชื่อ'; continue; }
        if (!preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $code)) { $skipped[] = "รหัส $code ไม่ถูกต้อง"; continue; }
        $ex = q_one('SELECT u.id, u.role, u.classroom_id, c.name AS classroom FROM users u LEFT JOIN classrooms c ON c.id = u.classroom_id WHERE u.student_code = ? OR u.username = ?', [$code, $code]);
        if ($ex && $ex['role'] !== 'student') { $skipped[] = "$code ซ้ำกับชื่อผู้ใช้ของบุคลากร"; continue; }
        if ($ex && $ex['classroom_id'] && (int)$ex['classroom_id'] !== (int)$classroom['id']) {
            $skipped[] = "$code $name อยู่กลุ่ม {$ex['classroom']} แล้ว (ให้ผู้ดูแลระบบย้ายกลุ่ม)";
            continue;
        }
        if ($ex) {
            q('UPDATE users SET prefix = ?, full_name = ?, classroom_id = ?, department = ?, major = ?, active = 1 WHERE id = ?', [$prefix, $name, $classroom['id'], $classroom['department'], $classroom['major'], $ex['id']]);
            $updated++;
        } else {
            q('INSERT INTO users (username, student_code, prefix, full_name, role, classroom_id, department, major, password_hash) VALUES (?,?,?,?,"student",?,?,?,?)',
                [$code, $code, $prefix, $name, $classroom['id'], $classroom['department'], $classroom['major'], password_hash($code, PASSWORD_DEFAULT, ['cost' => 8])]);
            $added++;
        }
    }
    return [$added, $updated, $skipped];
}
