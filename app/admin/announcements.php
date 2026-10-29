<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
$me = require_login('admin', 'teacher');
$isAdmin = $me['role'] === 'admin';
$ownerSql = $isAdmin ? '' : ' AND created_by = ?';
$ownerParams = $isAdmin ? [] : [(int)$me['id']];
$audiences = ['all' => 'ทุกคน', 'teacher' => 'ครูผู้สอน', 'student' => 'นักเรียน นักศึกษา'];

if (is_post()) {
    csrf_check();
    $id = int_param('id');
    if ($id < 0 || (input('action') === 'delete' && !$id)
        || ($id && !q_one('SELECT id FROM announcements WHERE id = ?' . $ownerSql, [$id, ...$ownerParams]))) {
        http_response_code(404);
        exit('ไม่พบประกาศที่คุณมีสิทธิ์จัดการ');
    }
    if (input('action') === 'delete') {
        q('DELETE FROM announcements WHERE id = ?' . $ownerSql, [$id, ...$ownerParams]);
        log_activity('ลบประกาศ #' . $id);
        flash('success', 'ลบประกาศแล้ว');
    } elseif (input('title') !== '') {
        $aud = isset($audiences[input('audience')]) ? input('audience') : 'all';
        $data = [input('title'), input('body'), $aud, (int)isset($_POST['is_public'])];
        if ($id) {
            q('UPDATE announcements SET title=?, body=?, audience=?, is_public=? WHERE id=?' . $ownerSql, [...$data, $id, ...$ownerParams]);
        } else {
            q('INSERT INTO announcements (title, body, audience, is_public, created_by) VALUES (?,?,?,?,?)', [...$data, $me['id']]);
        }
        log_activity('บันทึกประกาศ: ' . $data[0]);
        flash('success', 'บันทึกประกาศแล้ว');
    }
    redirect('app/admin/announcements.php');
}
$items = q_all('SELECT * FROM announcements' . ($isAdmin ? '' : ' WHERE created_by = ?') . ' ORDER BY created_at DESC', $ownerParams);
$edit = null;
if ($editId = int_param('edit')) {
    $edit = q_one('SELECT * FROM announcements WHERE id = ?' . $ownerSql, [$editId, ...$ownerParams]);
    if (!$edit) {
        http_response_code(404);
        exit('ไม่พบประกาศที่คุณมีสิทธิ์จัดการ');
    }
}
$pageTitle = 'ประกาศ';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1>ประกาศ / ข่าวสาร</h1></div>
<?php if (!$isAdmin): ?>
<p class="text-muted">เพิ่มประกาศ เลือกผู้รับ และจัดการประกาศของคุณได้ที่นี่ หากต้องการแสดงบนหน้าเว็บสาธารณะ ให้เลือกช่องเผยแพร่ก่อนบันทึก</p>
<?php endif; ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><ul class="list-group list-group-flush">
    <?php foreach ($items as $a): ?>
      <li class="list-group-item"><div class="d-flex justify-content-between gap-2">
        <div><strong><?= e($a['title']) ?></strong> <span class="badge text-bg-light border"><?= e($audiences[$a['audience']]) ?></span><?= $a['is_public'] ? ' <span class="badge text-bg-info">หน้าบ้าน</span>' : '' ?>
          <div class="small text-muted"><?= thai_date($a['created_at'], true) ?></div></div>
        <div class="text-nowrap"><a href="?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="ลบประกาศนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></div>
      </div><div class="prewrap small mt-1"><?= e($a['body']) ?></div></li>
    <?php endforeach; ?>
    <?php if (!$items): ?><li class="list-group-item text-muted"><?= $isAdmin ? 'ยังไม่มีประกาศ' : 'คุณยังไม่มีประกาศ' ?></li><?php endif; ?>
  </ul></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header"><?= $edit ? 'แก้ไขประกาศ' : 'เพิ่มประกาศ' ?></div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
      <div class="mb-2"><label class="form-label small">หัวข้อ</label><input name="title" class="form-control" value="<?= e($edit['title'] ?? '') ?>" maxlength="255" required></div>
      <div class="mb-2"><label class="form-label small">รายละเอียด</label><textarea name="body" class="form-control" rows="6"><?= e($edit['body'] ?? '') ?></textarea></div>
      <div class="mb-2"><label class="form-label small">ผู้รับ</label><select name="audience" class="form-select"><?= select_options($audiences, $edit['audience'] ?? 'all') ?></select></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_public" id="pub"<?= ($edit['is_public'] ?? ($isAdmin ? 1 : 0)) ? ' checked' : '' ?>><label class="form-check-label" for="pub">แสดงที่หน้าเว็บสาธารณะ</label></div>
      <button class="btn btn-primary w-100">บันทึก</button>
    </form>
  </div></div></div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
