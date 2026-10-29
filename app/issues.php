<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/bootstrap.php';
$me = require_login();
$isAdmin = is_role('admin');

const ISSUE_STATUS = [
    'open'        => ['รอดำเนินการ', 'warning'],
    'in_progress' => ['กำลังแก้ไข', 'primary'],
    'resolved'    => ['แก้ไขแล้ว', 'success'],
    'closed'      => ['ปิดเรื่อง', 'secondary'],
];
const ISSUE_CATEGORIES = ['เข้าสู่ระบบ / รหัสผ่าน', 'ข้อมูลไม่ถูกต้อง', 'ส่งรายงาน / อัปโหลดไฟล์', 'นำเข้าข้อมูล', 'หน้าจอแสดงผลผิดปกติ', 'ข้อเสนอแนะ', 'อื่น ๆ'];

// ไฟล์แนบ: เจ้าของเรื่องหรือผู้ดูแลระบบเท่านั้น
if ($fid = int_param('file')) {
    $iss = q_one('SELECT * FROM issues WHERE id = ?', [$fid]);
    if (!$iss || !$iss['file_path'] || (!$isAdmin && (int)$iss['user_id'] !== (int)$me['id']) || !is_file(UPLOAD_DIR . '/' . $iss['file_path'])) {
        http_response_code(404);
        exit('ไม่พบไฟล์');
    }
    $path = UPLOAD_DIR . '/' . $iss['file_path'];
    header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream'));
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($iss['file_name']));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'create') {
        if (input('title') === '' || input('detail') === '') {
            flash('danger', 'กรุณาระบุหัวข้อและรายละเอียดปัญหา');
        } else {
            try {
                $file = save_upload('file', 'issues');
                $cat = in_array(input('category'), ISSUE_CATEGORIES, true) ? input('category') : 'อื่น ๆ';
                q('INSERT INTO issues (user_id, category, title, detail, page_url, file_name, file_path) VALUES (?,?,?,?,?,?,?)',
                    [$me['id'], $cat, mb_substr(input('title'), 0, 255), input('detail'), mb_substr(input('page_url'), 0, 500), $file[0] ?? '', $file[1] ?? '']);
                log_activity('แจ้งปัญหาการใช้งาน: ' . input('title'));
                flash('success', 'ส่งเรื่องแจ้งปัญหาแล้ว ผู้ดูแลระบบจะตรวจสอบและตอบกลับในหน้านี้');
            } catch (RuntimeException $ex) {
                flash('danger', $ex->getMessage());
            }
        }
    } elseif ($action === 'reply' && $isAdmin) {
        $st = isset(ISSUE_STATUS[input('status')]) ? input('status') : 'in_progress';
        q('UPDATE issues SET status = ?, admin_reply = ?, replied_by = ?, replied_at = NOW() WHERE id = ?', [$st, input('reply'), $me['id'], int_param('id')]);
        log_activity('ตอบกลับเรื่องแจ้งปัญหา #' . int_param('id') . ' (' . ISSUE_STATUS[$st][0] . ')');
        flash('success', 'บันทึกการตอบกลับแล้ว');
    } elseif ($action === 'close') {
        // ผู้แจ้งปิดเรื่องของตนเองได้ เมื่อปัญหาได้รับการแก้ไขแล้ว
        q('UPDATE issues SET status = "closed" WHERE id = ? AND (user_id = ? OR ?)', [int_param('id'), $me['id'], (int)$isAdmin]);
        flash('success', 'ปิดเรื่องแล้ว');
    } elseif ($action === 'delete' && $isAdmin) {
        $iss = q_one('SELECT file_path FROM issues WHERE id = ?', [int_param('id')]);
        if ($iss && $iss['file_path']) { @unlink(UPLOAD_DIR . '/' . $iss['file_path']); }
        q('DELETE FROM issues WHERE id = ?', [int_param('id')]);
        flash('success', 'ลบเรื่องแล้ว');
    }
    redirect('app/issues.php' . (input('status_filter') !== '' ? '?status=' . urlencode(input('status_filter')) : ''));
}

$filter = input('status');
$params = [];
$where = $isAdmin ? '1=1' : 'i.user_id = ?';
if (!$isAdmin) { $params[] = $me['id']; }
if (isset(ISSUE_STATUS[$filter])) { $where .= ' AND i.status = ?'; $params[] = $filter; }
$total = (int)q_val("SELECT COUNT(*) FROM issues i WHERE $where", $params);
[$page, $pages, $offset, $limit] = paginate($total, 20);
$issues = q_all("SELECT i.*, u.prefix, u.full_name, u.role, u.username, r.prefix AS r_prefix, r.full_name AS r_full_name
    FROM issues i JOIN users u ON u.id = i.user_id LEFT JOIN users r ON r.id = i.replied_by
    WHERE $where ORDER BY FIELD(i.status, 'open', 'in_progress', 'resolved', 'closed'), i.created_at DESC LIMIT $limit OFFSET $offset", $params);
$counts = [];
foreach (q_all('SELECT status, COUNT(*) n FROM issues' . ($isAdmin ? '' : ' WHERE user_id = ?') . ' GROUP BY status', $isAdmin ? [] : [$me['id']]) as $r) {
    $counts[$r['status']] = (int)$r['n'];
}
$referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
$pageTitle = 'แจ้งปัญหาการใช้งาน';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><div><h1><i class="bi bi-exclamation-triangle"></i> แจ้งปัญหาการใช้งาน</h1>
  <div class="text-muted"><?= $isAdmin ? 'เรื่องที่ผู้ใช้แจ้งเข้ามาทั้งหมด' : 'พบปัญหาหรือมีข้อเสนอแนะ แจ้งผู้ดูแลระบบได้ที่นี่' ?></div></div></div>

<ul class="nav nav-pills flex-wrap gap-1 mb-3">
  <li class="nav-item"><a class="nav-link<?= $filter === '' ? ' active' : '' ?>" href="?">ทั้งหมด <span class="badge text-bg-light border"><?= array_sum($counts) ?></span></a></li>
  <?php foreach (ISSUE_STATUS as $k => [$lbl]): ?>
    <li class="nav-item"><a class="nav-link<?= $filter === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $lbl ?> <span class="badge text-bg-light border"><?= $counts[$k] ?? 0 ?></span></a></li>
  <?php endforeach; ?>
</ul>

<div class="row g-3">
  <div class="<?= $isAdmin ? 'col-12' : 'col-lg-7' ?>">
    <?php foreach ($issues as $i): ?>
      <div class="card mb-3"><div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2">
          <div><span class="text-muted">#<?= $i['id'] ?></span> <strong><?= e($i['title']) ?></strong> <?= badge(ISSUE_STATUS, $i['status']) ?> <span class="badge text-bg-light border"><?= e($i['category']) ?></span>
            <div class="small text-muted"><?= $isAdmin ? e(person($i)) . ' (' . e(ROLE_LABELS[$i['role']]) . ' · ' . e($i['username']) . ') · ' : '' ?><?= thai_date($i['created_at'], true) ?></div></div>
          <div class="text-nowrap">
            <?php if (!in_array($i['status'], ['closed'], true)): ?>
              <form method="post" class="d-inline" data-confirm="ปิดเรื่องนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="status_filter" value="<?= e($filter) ?>"><button class="btn btn-sm btn-outline-secondary">ปิดเรื่อง</button></form>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <form method="post" class="d-inline" data-confirm="ลบเรื่องนี้ถาวร?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="status_filter" value="<?= e($filter) ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
          </div>
        </div>
        <div class="prewrap mt-2"><?= e($i['detail']) ?></div>
        <div class="small mt-2 d-flex flex-wrap gap-3">
          <?php if ($i['page_url']): ?><span class="text-muted"><i class="bi bi-link-45deg"></i> หน้าที่พบปัญหา: <?= e($i['page_url']) ?></span><?php endif; ?>
          <?php if ($i['file_path']): ?><a href="?file=<?= $i['id'] ?>" target="_blank"><i class="bi bi-paperclip"></i> <?= e($i['file_name']) ?></a><?php endif; ?>
        </div>
        <?php if ($i['admin_reply']): ?>
          <div class="mt-3 p-2 rounded border bg-light-subtle"><div class="small fw-semibold"><i class="bi bi-reply"></i> ผู้ดูแลระบบตอบกลับ<?= $i['r_full_name'] ? ' (' . e(person($i, 'r_')) . ')' : '' ?> · <?= thai_date($i['replied_at'], true) ?></div><div class="prewrap small"><?= e($i['admin_reply']) ?></div></div>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
          <form method="post" class="row g-2 mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="status_filter" value="<?= e($filter) ?>">
            <div class="col-md-3"><select name="status" class="form-select form-select-sm"><?= select_options(ISSUE_STATUS, $i['status'] === 'open' ? 'in_progress' : $i['status']) ?></select></div>
            <div class="col-md-7"><textarea name="reply" class="form-control form-control-sm" rows="1" placeholder="ข้อความตอบกลับผู้แจ้ง"><?= e($i['admin_reply']) ?></textarea></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">บันทึก</button></div>
          </form>
        <?php endif; ?>
      </div></div>
    <?php endforeach; ?>
    <?php if (!$issues): ?><div class="card card-body text-muted">ไม่มีเรื่องแจ้งปัญหา</div><?php endif; ?>
    <?= pagination_links($page, $pages) ?>
  </div>

  <?php if (!$isAdmin): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header">แจ้งปัญหาใหม่</div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="mb-2"><label class="form-label small">ประเภทปัญหา</label><select name="category" class="form-select"><?= select_options(ISSUE_CATEGORIES, '', false) ?></select></div>
      <div class="mb-2"><label class="form-label small">หัวข้อ *</label><input name="title" class="form-control" maxlength="255" required></div>
      <div class="mb-2"><label class="form-label small">รายละเอียด * (ทำอะไรอยู่ เกิดอะไรขึ้น)</label><textarea name="detail" class="form-control" rows="5" required></textarea></div>
      <div class="mb-2"><label class="form-label small">หน้าที่พบปัญหา</label><input name="page_url" class="form-control" value="<?= e(str_contains($referer, '/app/issues.php') ? '' : (string)parse_url($referer, PHP_URL_PATH)) ?>"></div>
      <div class="mb-3"><label class="form-label small">ภาพหน้าจอ / ไฟล์ประกอบ (ถ้ามี)</label><input type="file" name="file" class="form-control" accept=".png,.jpg,.jpeg,.pdf"></div>
      <button class="btn btn-primary w-100"><i class="bi bi-send"></i> ส่งเรื่อง</button>
    </form>
  </div></div></div>
  <?php endif; ?>
</div>
<?php require APP_ROOT . '/inc/footer.php';
