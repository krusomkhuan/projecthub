<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = empty($_SESSION['uid']) ? null
            : q_one('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']]);
    }
    return $user;
}

function uid(): int
{
    return (int)(current_user()['id'] ?? 0);
}

function role(): string
{
    return current_user()['role'] ?? '';
}

function is_role(string ...$roles): bool
{
    return in_array(role(), $roles, true);
}

/** ผู้ที่มองเห็นข้อมูลทั้งวิทยาลัย: ผู้ดูแลระบบ หัวหน้างานวิจัยฯ ฝ่ายวิชาการ ฝ่ายยุทธศาสตร์ฯ */
function is_overseer(): bool
{
    return is_role('admin', 'research', 'academic', 'strategy');
}

function require_login(string ...$roles): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    if ($u['must_change_password'] && !str_ends_with($_SERVER['SCRIPT_NAME'], '/app/profile.php')) {
        flash('warning', 'กรุณาเปลี่ยนรหัสผ่านก่อนเริ่มใช้งาน');
        redirect('app/profile.php');
    }
    // ครูต้องแจ้งรายวิชาที่สอนของภาคเรียนปัจจุบันก่อนใช้งานส่วนอื่น
    if ($u['role'] === 'teacher' && !preg_match('#/app/(teacher/teaching|profile)\.php$#', $_SERVER['SCRIPT_NAME']) && teacher_needs_declaration((int)$u['id'])) {
        flash('info', 'กรุณาแจ้งรายวิชาที่สอนในภาคเรียนนี้ก่อนเริ่มใช้งาน');
        redirect('app/teacher/teaching.php');
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        require APP_ROOT . '/inc/header.php';
        echo '<div class="alert alert-danger">คุณไม่มีสิทธิ์เข้าถึงหน้านี้</div>';
        require APP_ROOT . '/inc/footer.php';
        exit;
    }
    return $u;
}

/** ภาคเรียนที่เปิดใช้งานและยังไม่ปิดภาค (ใช้แจ้งรายวิชาที่สอน) */
function teaching_semester(): ?array
{
    return q_one('SELECT * FROM semesters WHERE is_active = 1 AND is_closed = 0 ORDER BY academic_year DESC, term DESC LIMIT 1');
}

function teacher_needs_declaration(int $teacherId): bool
{
    $sem = teaching_semester();
    return $sem && !q_val('SELECT 1 FROM teacher_declarations WHERE teacher_id = ? AND semester_id = ?', [$teacherId, $sem['id']]);
}

function attempt_login(string $username, string $password): bool
{
    $u = q_one('SELECT * FROM users WHERE (username = ? OR student_code = ?) AND active = 1', [$username, $username]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
    return true;
}

/** ระดับสิทธิ์ต่อโครงงาน: 'none' | 'member' | 'teacher' | 'overseer' */
function project_access(array $project): string
{
    $u = current_user();
    if (!$u) {
        return 'none';
    }
    if (is_overseer()) {
        return 'overseer';
    }
    if ($u['role'] === 'teacher') {
        $teaches = q_val('SELECT teacher_id FROM sections WHERE id = ?', [$project['section_id']]);
        return ((int)$teaches === (int)$u['id'] || (int)$project['advisor_id'] === (int)$u['id']) ? 'teacher' : 'none';
    }
    if ($u['role'] === 'student') {
        $m = q_val('SELECT 1 FROM project_members WHERE project_id = ? AND student_id = ?', [$project['id'], $u['id']]);
        return $m ? 'member' : 'none';
    }
    return 'none';
}

/** เงื่อนไข SQL จำกัดโครงงานที่ผู้ใช้ปัจจุบันมองเห็น (ใช้กับ alias p = projects, s = sections) */
function project_scope_sql(array &$params): string
{
    if (is_overseer()) {
        return '1=1';
    }
    if (is_role('teacher')) {
        $params[] = uid();
        $params[] = uid();
        return '(s.teacher_id = ? OR p.advisor_id = ?)';
    }
    $params[] = uid();
    return 'p.id IN (SELECT project_id FROM project_members WHERE student_id = ?)';
}
