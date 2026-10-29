<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require_login('admin');

// ส่งออกข้อมูลในรูปแบบคอลัมน์เดียวกับไฟล์นำเข้า (แก้ไขใน Excel แล้วนำเข้ากลับได้)
// คอลัมน์ท้ายที่มีเครื่องหมาย * เป็นข้อมูลประกอบ ระบบไม่ใช้ตอนนำเข้า
$type = input('type');
$sem = active_semester();
$split = function (string $name): array {
    $parts = explode(' ', trim((string)preg_replace('/\s+/u', ' ', $name)), 2);
    return [$parts[0], $parts[1] ?? ''];
};

switch ($type) {
    case 'departments':
        $title = 'แผนกวิชา';
        $header = ['ชื่อแผนกวิชา', 'รหัสแผนก', 'จำนวนสาขาวิชา*'];
        $rows = array_map(fn($r) => [$r['name'], $r['code'], $r['majors']],
            q_all('SELECT d.name, d.code, (SELECT COUNT(*) FROM majors m WHERE m.department_id = d.id) AS majors FROM departments d ORDER BY d.name'));
        break;
    case 'majors':
        $title = 'สาขาวิชา';
        $header = ['แผนกวิชา', 'ชื่อสาขาวิชา'];
        $rows = array_map(fn($r) => [$r['dept'], $r['name']],
            q_all('SELECT d.name AS dept, m.name FROM majors m JOIN departments d ON d.id = m.department_id ORDER BY d.name, m.name'));
        break;
    case 'teachers':
        $title = 'ครูผู้สอน';
        $header = ['รหัสประจำตัว', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'แผนกวิชา', 'สาขาวิชา', 'ตำแหน่ง', 'วุฒิการศึกษา', 'หน้าที่พิเศษ', 'โทรศัพท์*', 'อีเมล*', 'สถานะ*', 'เข้าระบบล่าสุด*'];
        $rows = [];
        foreach (q_all('SELECT * FROM users WHERE role = "teacher" ORDER BY username') as $u) {
            [$first, $last] = $split($u['full_name']);
            $rows[] = [$u['username'], $u['prefix'], $first, $last, $u['department'], $u['major'], $u['position'], $u['education'], $u['duty'],
                       $u['phone'], $u['email'], $u['active'] ? 'ใช้งาน' : 'ระงับ', $u['last_login'] ? thai_date($u['last_login'], true) : 'ยังไม่เคย'];
        }
        break;
    case 'classrooms':
        $title = 'กลุ่มเรียน';
        $header = ['รหัสกลุ่มเรียน', 'ชื่อกลุ่ม', 'ระดับชั้น', 'สาขาวิชา', 'ปีการศึกษา', 'ครูที่ปรึกษา', 'แผนกวิชา*', 'จำนวนนักเรียน*'];
        $rows = array_map(fn($r) => [$r['code'], $r['name'], $r['level'], $r['major'], $r['academic_year'], $r['advisor_name'], $r['department'], $r['students']],
            q_all('SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.classroom_id = c.id AND u.role = "student" AND u.active = 1) AS students FROM classrooms c ORDER BY c.level, c.code'));
        break;
    case 'students':
        $title = 'นักเรียน นักศึกษา';
        $header = ['รหัสนักศึกษา', 'คำนำหน้า', 'ชื่อ-สกุล', 'รหัสกลุ่มเรียน', 'ชื่อกลุ่มเรียน', 'ระดับ', 'แผนกวิชา', 'สาขาวิชา*', 'สถานะ*', 'เข้าระบบล่าสุด*'];
        $params = [];
        $where = 'u.role = "student"';
        if ($cid = int_param('classroom')) { $where .= ' AND u.classroom_id = ?'; $params[] = $cid; }
        $rows = array_map(fn($u) => [$u['student_code'] ?: $u['username'], $u['prefix'], $u['full_name'], $u['code'], $u['cname'], $u['level'], $u['department'] ?: $u['cdept'],
                                     $u['major'] ?: $u['cmajor'], $u['active'] ? 'ใช้งาน' : 'ระงับ', $u['last_login'] ? thai_date($u['last_login'], true) : 'ยังไม่เคย'],
            q_all("SELECT u.*, c.code, c.name AS cname, c.level, c.department AS cdept, c.major AS cmajor FROM users u LEFT JOIN classrooms c ON c.id = u.classroom_id
                   WHERE $where ORDER BY c.level, c.code, u.student_code", $params));
        break;
    case 'sections':
        $semId = int_param('semester', (int)($sem['id'] ?? 0));
        $sem = q_one('SELECT * FROM semesters WHERE id = ?', [$semId]);
        $title = 'รายวิชาโครงงาน ' . str_replace('/', '-', semester_label($sem));
        $sheet = 'รายวิชาโครงงาน'; // ชื่อแผ่นงาน Excel ยาวได้ไม่เกิน 31 ตัวอักษร
        $header = ['ชื่อผู้ใช้ครู', 'รหัสกลุ่มเรียน', 'รหัสวิชา', 'ชื่อวิชา', 'ชื่อครู*', 'ชื่อกลุ่มเรียน*', 'จำนวนกลุ่มโครงงาน*'];
        $rows = array_map(fn($r) => [$r['username'], $r['code'], $r['subject_code'], $r['subject_name'], person($r), $r['cname'], $r['projects']],
            q_all('SELECT s.*, t.username, t.prefix, t.full_name, c.code, c.name AS cname, (SELECT COUNT(*) FROM projects p WHERE p.section_id = s.id) AS projects
                   FROM sections s JOIN users t ON t.id = s.teacher_id JOIN classrooms c ON c.id = s.classroom_id WHERE s.semester_id = ? ORDER BY c.code', [$semId]));
        break;
    default:
        http_response_code(404);
        exit('ไม่พบประเภทข้อมูล');
}

log_activity("ส่งออกข้อมูล$title (" . count($rows) . ' รายการ)');
$filename = 'ProjectHub_' . $title . '_' . date('Ymd');
if (input('format') === 'csv') {
    send_csv($filename . '.csv', $header, $rows);
}
send_xlsx($filename . '.xlsx', $header, $rows, $sheet ?? $title);
