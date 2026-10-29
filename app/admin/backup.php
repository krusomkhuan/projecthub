<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
require APP_ROOT . '/inc/backup.php';
$me = require_login('admin');
ini_set('memory_limit', '1024M');

if ($dl = input('download')) {
    $path = backup_path($dl);
    if (!$path) { http_response_code(404); exit('ไม่พบไฟล์สำรอง'); }
    log_activity("ดาวน์โหลดไฟล์สำรอง $dl");
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $dl . '"');
    readfile($path);
    exit;
}

if (is_post()) {
    csrf_check();
    try {
        switch (input('action')) {
            case 'create':
                $name = backup_create('manual', input('scope') !== 'db');
                log_activity("สำรองข้อมูล $name");
                flash('success', "สำรองข้อมูลเรียบร้อย: $name");
                break;
            case 'settings':
                foreach (['backup_auto_days' => (string)max(0, int_param('backup_auto_days')), 'backup_keep' => (string)max(1, int_param('backup_keep', 10)),
                          'backup_auto_files' => isset($_POST['backup_auto_files']) ? '1' : '0'] as $k => $v) {
                    q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$k, $v]);
                }
                flash('success', 'บันทึกการตั้งค่าการสำรองข้อมูลแล้ว');
                break;
            case 'delete':
                if ($path = backup_path(input('name'))) {
                    unlink($path);
                    log_activity('ลบไฟล์สำรอง ' . input('name'));
                    flash('success', 'ลบไฟล์สำรองแล้ว');
                }
                break;
            case 'upload':
                $f = $_FILES['backup'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK || strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) !== 'zip') {
                    throw new RuntimeException('กรุณาเลือกไฟล์สำรอง .zip (ขนาดไม่เกิน ' . ini_get('upload_max_filesize') . ')');
                }
                $name = 'projecthub_' . date('Ymd_His') . '_upload.zip';
                move_uploaded_file($f['tmp_name'], backup_dir() . '/' . $name);
                log_activity("อัปโหลดไฟล์สำรอง $name");
                flash('success', "อัปโหลดไฟล์สำรองแล้ว ($name) กดปุ่ม \"กู้คืน\" ในรายการเพื่อนำข้อมูลกลับมาใช้");
                break;
            case 'restore':
                $path = backup_path(input('name'));
                if (!$path) { throw new RuntimeException('ไม่พบไฟล์สำรอง'); }
                if (input('confirm_text') !== 'กู้คืน') { throw new RuntimeException('กรุณาพิมพ์คำว่า "กู้คืน" เพื่อยืนยัน'); }
                // สำรองข้อมูลปัจจุบันไว้ก่อน เผื่อต้องย้อนกลับ
                $safety = backup_create('prerestore', true);
                [$stmts, $files, $meta] = backup_restore($path);
                log_activity('กู้คืนข้อมูลจาก ' . input('name') . " (สำรองก่อนกู้คืน: $safety)");
                flash('success', "กู้คืนข้อมูลเรียบร้อย ($stmts คำสั่ง, ไฟล์แนบ $files ไฟล์) · ข้อมูลก่อนกู้คืนถูกเก็บไว้ที่ $safety");
                break;
        }
    } catch (Throwable $ex) {
        flash('danger', 'ไม่สำเร็จ: ' . $ex->getMessage());
    }
    redirect('app/admin/backup.php');
}

$backups = backup_list();
$kinds = ['manual' => ['สำรองด้วยตนเอง', 'primary'], 'auto' => ['อัตโนมัติ', 'info'], 'prerestore' => ['ก่อนกู้คืน', 'warning'], 'upload' => ['อัปโหลด', 'secondary']];
$cliCmd = str_replace('/', '\\', PHP_BINARY) . ' ' . str_replace('/', '\\', APP_ROOT) . '\\backup_cli.php';
$pageTitle = 'สำรองข้อมูล';
require APP_ROOT . '/inc/header.php';
?>
<div class="page-head"><div><h1><i class="bi bi-database-check"></i> สำรองและกู้คืนข้อมูล</h1>
  <div class="text-muted">สำรองล่าสุด: <?= setting('last_backup') ? thai_date(setting('last_backup'), true) : 'ยังไม่เคยสำรอง' ?></div></div>
  <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <select name="scope" class="form-select"><option value="all">ฐานข้อมูล + ไฟล์แนบ</option><option value="db">เฉพาะฐานข้อมูล</option></select>
    <button class="btn btn-primary text-nowrap"><i class="bi bi-download"></i> สำรองข้อมูลตอนนี้</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-header">ไฟล์สำรองข้อมูล (<?= count($backups) ?>)</div>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>ไฟล์</th><th>วันที่</th><th>ประเภท</th><th class="text-end">ขนาด</th><th></th></tr></thead><tbody>
        <?php foreach ($backups as $b): ?>
          <tr><td class="small"><?= e($b['name']) ?></td><td class="text-nowrap small"><?= thai_date(date('Y-m-d H:i:s', $b['time']), true) ?></td>
            <td><?= badge($kinds, $b['kind']) ?></td><td class="text-end small"><?= format_bytes($b['size']) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="?download=<?= urlencode($b['name']) ?>" title="ดาวน์โหลด"><i class="bi bi-download"></i></a>
              <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#restoreModal" data-name="<?= e($b['name']) ?>" title="กู้คืน"><i class="bi bi-arrow-counterclockwise"></i></button>
              <form method="post" class="d-inline" data-confirm="ลบไฟล์สำรองนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            </td></tr>
        <?php endforeach; ?>
        <?php if (!$backups): ?><tr><td colspan="5" class="text-muted">ยังไม่มีไฟล์สำรอง</td></tr><?php endif; ?>
      </tbody></table></div>
      <div class="card-body small text-muted border-top">ไฟล์สำรองเก็บอยู่ที่ <code><?= e(str_replace('/', '\\', BACKUP_DIR)) ?></code> (เข้าถึงจากเว็บโดยตรงไม่ได้) · ควรดาวน์โหลดเก็บไว้นอกเครื่องเซิร์ฟเวอร์ เช่น Google Drive หรือ External Drive เป็นประจำ</div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-header">สำรองอัตโนมัติ</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
        <div class="mb-2"><label class="form-label small">ความถี่</label><select name="backup_auto_days" class="form-select">
          <?= select_options(['0' => 'ปิดการสำรองอัตโนมัติ', '1' => 'ทุกวัน', '3' => 'ทุก 3 วัน', '7' => 'ทุกสัปดาห์', '30' => 'ทุกเดือน'], setting('backup_auto_days', '1')) ?></select></div>
        <div class="mb-2"><label class="form-label small">เก็บไฟล์สำรองอัตโนมัติล่าสุด (ไฟล์)</label><input type="number" min="1" max="100" name="backup_keep" class="form-control" value="<?= e(setting('backup_keep', '10')) ?>"></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="backup_auto_files" id="baf"<?= setting('backup_auto_files', '1') === '1' ? ' checked' : '' ?>><label class="form-check-label small" for="baf">รวมไฟล์แนบ (รายงาน แบบฟอร์ม ภาพแจ้งปัญหา)</label></div>
        <button class="btn btn-primary w-100">บันทึก</button>
      </form>
      <p class="small text-muted mt-3 mb-1">ระบบสำรองอัตโนมัติเมื่อผู้ดูแลระบบเปิดหน้าแดชบอร์ดและถึงรอบ เพื่อให้สำรองได้แม้ไม่มีใครเข้าระบบ ให้ตั้ง <strong>Task Scheduler</strong> ของ Windows เรียกคำสั่งนี้ทุกวัน:</p>
      <code class="small d-block p-2 bg-light rounded user-select-all"><?= e($cliCmd) ?></code>
      <p class="small text-muted mt-2 mb-0">ไฟล์ที่สำรองด้วยตนเองจะไม่ถูกลบอัตโนมัติ</p>
    </div></div>
    <div class="card"><div class="card-header">อัปโหลดไฟล์สำรองเพื่อกู้คืน</div><div class="card-body">
      <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
        <input type="file" name="backup" accept=".zip" class="form-control mb-2" required>
        <button class="btn btn-outline-primary w-100"><i class="bi bi-upload"></i> อัปโหลด</button>
      </form>
      <p class="small text-muted mt-2 mb-0">ใช้ย้ายระบบไปเครื่องใหม่ หรือกู้คืนจากไฟล์ที่เก็บไว้ภายนอก (ขนาดไม่เกิน <?= e(ini_get('upload_max_filesize')) ?>)</p>
    </div></div>
  </div>
</div>

<div class="modal fade" id="restoreModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="name" id="restoreName">
  <div class="modal-header"><h5 class="modal-title">ยืนยันการกู้คืนข้อมูล</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="alert alert-warning small mb-3"><strong>ข้อมูลปัจจุบันทั้งหมดจะถูกแทนที่</strong> ด้วยข้อมูลจากไฟล์ <span id="restoreLabel" class="fw-semibold"></span><br>ระบบจะสำรองข้อมูลปัจจุบันเก็บไว้ให้ก่อนโดยอัตโนมัติ (ประเภท "ก่อนกู้คืน")</div>
    <label class="form-label small">พิมพ์คำว่า <strong>กู้คืน</strong> เพื่อยืนยัน</label><input name="confirm_text" class="form-control" required autocomplete="off">
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-warning">กู้คืนข้อมูล</button></div>
</form></div></div>
<?php
$pageScripts = "<script>document.getElementById('restoreModal').addEventListener('show.bs.modal',function(e){var n=e.relatedTarget.getAttribute('data-name');document.getElementById('restoreName').value=n;document.getElementById('restoreLabel').textContent=n;});</script>";
require APP_ROOT . '/inc/footer.php';
