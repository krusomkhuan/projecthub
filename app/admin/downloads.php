<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
$me = require_login('admin');
$audiences = ['all' => 'ทุกคน', 'teacher' => 'ครูและบุคลากร', 'student' => 'นักเรียน นักศึกษา'];

if (is_post()) {
    csrf_check();
    $id = int_param('id');
    try {
        if (input('action') === 'move') {
            // เลื่อนขึ้น/ลงภายในหมวดเดียวกัน: เรียงลำดับใหม่เป็น 1, 2, 3... แล้วสลับกับรายการข้างเคียง
            $f = q_one('SELECT id, category FROM form_files WHERE id = ?', [$id]);
            if ($f) {
                $ids = array_map('intval', array_column(q_all('SELECT id FROM form_files WHERE category = ? ORDER BY sort_order, title', [$f['category']]), 'id'));
                $pos = array_search($id, $ids, true);
                $to = $pos + (input('dir') === 'up' ? -1 : 1);
                if ($pos !== false && isset($ids[$to])) {
                    [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
                }
                foreach ($ids as $i => $fid) {
                    q('UPDATE form_files SET sort_order = ? WHERE id = ?', [$i + 1, $fid]);
                }
            }
            redirect('app/admin/downloads.php#f' . $id);
        } elseif (input('action') === 'delete') {
            $f = q_one('SELECT * FROM form_files WHERE id = ?', [$id]);
            if ($f) {
                @unlink(UPLOAD_DIR . '/' . $f['file_path']);
                q('DELETE FROM form_files WHERE id = ?', [$id]);
                log_activity('ลบแบบฟอร์ม ' . $f['title']);
                flash('success', 'ลบแบบฟอร์มแล้ว');
            }
        } else {
            $title = input('title');
            $file = save_upload('file', 'forms');
            if ($title === '' && $file) { $title = pathinfo($file[0], PATHINFO_FILENAME); }
            if ($title === '') { throw new RuntimeException('กรุณาระบุชื่อแบบฟอร์ม'); }
            $data = [$title, in_array(input('category'), FORM_CATEGORIES, true) ? input('category') : 'อื่น ๆ', input('description'),
                     isset($audiences[input('audience')]) ? input('audience') : 'all', (int)isset($_POST['is_public'])];
            $cat = $data[1];
            // เว้นช่องลำดับว่าง = ต่อท้ายหมวด (แก้ไข: คงลำดับเดิม)
            $sort = input('sort_order') !== '' ? int_param('sort_order')
                : (($id && ($cur = q_one('SELECT category, sort_order FROM form_files WHERE id = ?', [$id])) && $cur['category'] === $cat)
                    ? (int)$cur['sort_order'] : (int)q_val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_files WHERE category = ? AND id <> ?', [$cat, $id]));
            $data[] = $sort;
            if ($id) {
                $old = q_one('SELECT * FROM form_files WHERE id = ?', [$id]);
                q('UPDATE form_files SET title=?, category=?, description=?, audience=?, is_public=?, sort_order=? WHERE id=?', [...$data, $id]);
                if ($file) {
                    @unlink(UPLOAD_DIR . '/' . $old['file_path']);
                    q('UPDATE form_files SET file_name=?, file_path=?, file_size=? WHERE id=?', [$file[0], $file[1], filesize(UPLOAD_DIR . '/' . $file[1]), $id]);
                }
            } else {
                if (!$file) { throw new RuntimeException('กรุณาเลือกไฟล์แบบฟอร์ม'); }
                q('INSERT INTO form_files (title, category, description, audience, is_public, sort_order, file_name, file_path, file_size, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [...$data, $file[0], $file[1], filesize(UPLOAD_DIR . '/' . $file[1]), $me['id']]);
            }
            log_activity('บันทึกแบบฟอร์มดาวน์โหลด: ' . $title);
            flash('success', 'บันทึกแบบฟอร์มแล้ว');
        }
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
    }
    redirect('app/admin/downloads.php');
}

$files = q_all('SELECT * FROM form_files ORDER BY FIELD(category, "' . implode('","', FORM_CATEGORIES) . '"), sort_order, title');
$order = [];
foreach ($files as $f) { $order[$f['category']][] = (int)$f['id']; }
$edit = int_param('edit') ? q_one('SELECT * FROM form_files WHERE id = ?', [int_param('edit')]) : null;
$pageTitle = 'จัดการแบบฟอร์มดาวน์โหลด';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><h1><i class="bi bi-folder2-open"></i> จัดการแบบฟอร์มดาวน์โหลด</h1><a href="<?= url('app/downloads.php') ?>" class="btn btn-outline-primary"><i class="bi bi-eye"></i> ดูหน้าดาวน์โหลด</a></div>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr><th class="text-center" style="width:4.5rem">ลำดับ</th><th>แบบฟอร์ม</th><th>หมวด</th><th>ผู้ดาวน์โหลดได้</th><th class="text-end">ดาวน์โหลด</th><th></th></tr></thead><tbody>
    <?php foreach ($files as $f): $pos = array_search((int)$f['id'], $order[$f['category']], true); $last = count($order[$f['category']]) - 1; ?>
      <tr id="f<?= $f['id'] ?>">
        <td class="text-center text-nowrap">
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="up"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-light border px-1 py-0" title="เลื่อนขึ้น"<?= $pos === 0 ? ' disabled' : '' ?>><i class="bi bi-chevron-up"></i></button></form>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="down"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-light border px-1 py-0" title="เลื่อนลง"<?= $pos === $last ? ' disabled' : '' ?>><i class="bi bi-chevron-down"></i></button></form>
        </td>
        <td><i class="bi bi-<?= file_icon($f['file_name']) ?>"></i> <strong><?= e($f['title']) ?></strong>
          <div class="small text-muted"><?= e($f['file_name']) ?> · <?= number_format($f['file_size'] / 1024, 0) ?> KB</div></td>
        <td class="small"><?= e($f['category']) ?></td>
        <td class="small"><?= e($audiences[$f['audience']]) ?><?= $f['is_public'] ? ' <span class="badge text-bg-info">หน้าบ้าน</span>' : '' ?></td>
        <td class="text-end"><?= (int)$f['download_count'] ?></td>
        <td class="text-end text-nowrap"><a href="?edit=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <form method="post" class="d-inline" data-confirm="ลบแบบฟอร์มนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr>
    <?php endforeach; ?>
    <?php if (!$files): ?><tr><td colspan="6" class="text-muted">ยังไม่มีแบบฟอร์ม</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header"><?= $edit ? 'แก้ไขแบบฟอร์ม' : 'เพิ่มแบบฟอร์ม' ?></div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
      <div class="mb-2"><label class="form-label small">ไฟล์<?= $edit ? ' (เลือกใหม่เพื่อแทนที่)' : ' *' ?></label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.png"<?= $edit ? '' : ' required' ?>>
        <?php if ($edit): ?><div class="form-text">ไฟล์ปัจจุบัน: <?= e($edit['file_name']) ?></div><?php endif; ?></div>
      <div class="mb-2"><label class="form-label small">ชื่อแบบฟอร์ม (เว้นว่าง = ใช้ชื่อไฟล์)</label><input name="title" class="form-control" value="<?= e($edit['title'] ?? '') ?>"></div>
      <div class="mb-2"><label class="form-label small">หมวด</label><select name="category" class="form-select"><?= select_options(FORM_CATEGORIES, $edit['category'] ?? '', false) ?></select></div>
      <div class="mb-2"><label class="form-label small">คำอธิบาย</label><textarea name="description" class="form-control" rows="2"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div class="row g-2 mb-2">
        <div class="col-8"><label class="form-label small">ผู้ดาวน์โหลดได้</label><select name="audience" class="form-select"><?= select_options($audiences, $edit['audience'] ?? 'all') ?></select></div>
        <div class="col-4"><label class="form-label small">ลำดับ</label><input type="number" name="sort_order" class="form-control" value="<?= e((string)($edit['sort_order'] ?? '')) ?>" placeholder="ต่อท้าย"></div>
      </div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_public" id="pub"<?= !empty($edit['is_public']) ? ' checked' : '' ?>><label class="form-check-label small" for="pub">ให้ดาวน์โหลดจากหน้าเว็บสาธารณะได้ (ไม่ต้องเข้าสู่ระบบ)</label></div>
      <button class="btn btn-primary w-100">บันทึก</button>
      <?php if ($edit): ?><a href="?" class="btn btn-light w-100 mt-2">ยกเลิก</a><?php endif; ?>
    </form>
  </div></div></div>
</div>
<?php require APP_ROOT . '/inc/footer.php';
