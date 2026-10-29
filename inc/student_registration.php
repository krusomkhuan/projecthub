<?php
declare(strict_types=1);

function registration_semester(): ?array
{
    return q_one('SELECT * FROM semesters WHERE is_active = 1 AND is_closed = 0 ORDER BY academic_year DESC, term DESC LIMIT 1');
}

/** กลุ่มเรียนทั้งหมด พร้อม has_section = เปิดรายวิชาโครงงานในภาคเรียนนี้แล้วหรือไม่ */
function registration_classrooms(int $semesterId): array
{
    return q_all('SELECT c.*, EXISTS (SELECT 1 FROM sections s WHERE s.classroom_id = c.id AND s.semester_id = ?) AS has_section
        FROM classrooms c ORDER BY c.level, c.code', [$semesterId]);
}

function registration_projects(int $semesterId, int $classroomId): array
{
    return q_all('SELECT p.id, p.title, p.status, s.subject_code, s.subject_name
        FROM projects p JOIN sections s ON s.id = p.section_id
        WHERE s.semester_id = ? AND s.classroom_id = ? AND p.status IN ("proposed","revise","approved")
        ORDER BY p.id', [$semesterId, $classroomId]);
}

/** Called inside a transaction, with the open semester locked first. */
function attach_student_to_project(int $studentId, int $projectId, int $semesterId): array
{
    $student = q_one('SELECT id, classroom_id FROM users WHERE id = ? AND role = "student" AND active = 1 FOR UPDATE', [$studentId]);
    if (!$student) {
        throw new InvalidArgumentException('ไม่พบข้อมูลนักเรียนที่ใช้งานได้');
    }
    $existing = q_val('SELECT p.id FROM project_members m JOIN projects p ON p.id = m.project_id JOIN sections s ON s.id = p.section_id
        WHERE m.student_id = ? AND s.semester_id = ? AND p.status <> "cancelled" LIMIT 1', [$studentId, $semesterId]);
    if ($existing) {
        throw new InvalidArgumentException('คุณมีกลุ่มโครงงานในภาคเรียนนี้แล้ว กรุณาติดต่อครูหากต้องการเปลี่ยนกลุ่ม');
    }
    $project = q_one('SELECT p.* FROM projects p JOIN sections s ON s.id = p.section_id
        WHERE p.id = ? AND s.semester_id = ? AND s.classroom_id = ? AND p.status IN ("proposed","revise","approved") FOR UPDATE',
        [$projectId, $semesterId, (int)$student['classroom_id']]);
    if (!$project) {
        throw new InvalidArgumentException('กรุณาเลือกกลุ่มโครงงานของกลุ่มเรียนตนเองในภาคเรียนปัจจุบัน');
    }
    q('INSERT INTO project_members (project_id, student_id, is_leader) VALUES (?,?,0)', [$projectId, $studentId]);
    return $project;
}

function register_student(array $data): array
{
    foreach (['student_code', 'full_name', 'prefix', 'password', 'password_confirm', 'classroom_id', 'project_id'] as $field) {
        if (isset($data[$field]) && !is_scalar($data[$field])) {
            throw new InvalidArgumentException('ข้อมูลสมัครสมาชิกไม่ถูกต้อง กรุณาตรวจสอบแบบฟอร์ม');
        }
    }
    $code = trim((string)($data['student_code'] ?? ''));
    $name = trim((string)($data['full_name'] ?? ''));
    $prefix = trim((string)($data['prefix'] ?? ''));
    $password = (string)($data['password'] ?? '');
    if (!preg_match('/\A[A-Za-z0-9_-]{1,20}\z/', $code)) {
        throw new InvalidArgumentException('กรุณาระบุรหัสนักศึกษา ไม่เกิน 20 ตัว ใช้ตัวเลข ตัวอักษรอังกฤษ - หรือ _');
    }
    if ($name === '' || mb_strlen($name) > 150 || !in_array($prefix, ['', 'นาย', 'นางสาว', 'นาง'], true)) {
        throw new InvalidArgumentException('กรุณาตรวจคำนำหน้าและชื่อ–นามสกุล ไม่เกิน 150 ตัวอักษร');
    }
    if (mb_strlen($password) < 8 || strlen($password) > 72) {
        throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร และไม่ยาวเกินไป');
    }
    if ($password !== (string)($data['password_confirm'] ?? '')) {
        throw new InvalidArgumentException('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
    }
    $semester = registration_semester();
    if (!$semester) {
        throw new InvalidArgumentException('ยังไม่เปิดภาคเรียนสำหรับสมัครสมาชิก กรุณาติดต่อครูผู้สอน');
    }
    $classroomId = (int)($data['classroom_id'] ?? 0);
    $projectId = (int)($data['project_id'] ?? 0);
    if ($projectId < 0) {
        throw new InvalidArgumentException('กรุณาเลือกกลุ่มโครงงานให้ถูกต้อง');
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    db()->beginTransaction();
    try {
        $open = q_one('SELECT id FROM semesters WHERE id = ? AND is_active = 1 AND is_closed = 0 FOR UPDATE', [$semester['id']]);
        if (!$open) {
            throw new InvalidArgumentException('ภาคเรียนนี้ปิดรับสมัครแล้ว กรุณาติดต่อครู');
        }
        $classroom = q_one('SELECT c.* FROM classrooms c WHERE c.id = ?', [$classroomId]);
        if (!$classroom) {
            throw new InvalidArgumentException('กรุณาเลือกกลุ่มเรียนของตนเอง');
        }
        if (q_val('SELECT id FROM users WHERE username = ? OR student_code = ? LIMIT 1', [$code, $code])) {
            throw new InvalidArgumentException('รหัสนักศึกษานี้มีบัญชีแล้ว กรุณาเข้าสู่ระบบหรือติดต่อครูเพื่อขอรหัสผ่าน');
        }
        q('INSERT INTO users (username, student_code, prefix, full_name, role, classroom_id, department, major, password_hash, must_change_password, active)
            VALUES (?,?,?,?,"student",?,?,?,?,0,1)', [$code, $code, $prefix, $name, $classroomId, $classroom['department'], $classroom['major'], $hash]);
        $id = (int)db()->lastInsertId();
        $project = $projectId ? attach_student_to_project($id, $projectId, (int)$semester['id']) : null;
        q('INSERT INTO activity_logs (user_id, action) VALUES (?,?)', [$id, 'สมัครสมาชิกนักเรียน' . ($project ? ' และเข้าร่วมโครงงาน #' . $projectId : '')]);
        db()->commit();
        return ['id' => $id, 'project_id' => $projectId, 'project_status' => $project['status'] ?? null];
    } catch (Throwable $e) {
        if (db()->inTransaction()) { db()->rollBack(); }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            throw new InvalidArgumentException('ไม่สามารถสมัครได้ ข้อมูลซ้ำหรือมีการเปลี่ยนแปลง กรุณาตรวจสอบและลองใหม่');
        }
        throw $e;
    }
}
