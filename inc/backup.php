<?php
declare(strict_types=1);

/*
 * สำรอง / กู้คืนข้อมูล ProjectHub
 * ไฟล์สำรองเป็น .zip ประกอบด้วย database.sql (ทุกตาราง) และไฟล์แนบใน uploads/
 * เขียนเองทั้งหมดเพื่อไม่ต้องพึ่งส่วนขยาย zip หรือ mysqldump
 */

define('BACKUP_DIR', APP_ROOT . '/backups');

/** เขียนไฟล์ ZIP แบบสตรีมลงดิสก์ (บีบอัดแบบ deflate) */
final class ZipWriter
{
    private $fh;
    private array $central = [];
    private int $offset = 0;

    public function __construct(string $path)
    {
        $this->fh = fopen($path, 'wb');
        if (!$this->fh) {
            throw new RuntimeException('ไม่สามารถสร้างไฟล์สำรองได้');
        }
    }

    public function add(string $name, string $data): void
    {
        $crc = crc32($data);
        $deflated = (string)gzdeflate($data, 6);
        [$time, $date] = $this->dosTime();
        // bit 11 = ชื่อไฟล์เป็น UTF-8
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $time, $date, $crc, strlen($deflated), strlen($data), strlen($name), 0);
        fwrite($this->fh, $local . $name . $deflated);
        $this->central[] = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $time, $date, $crc, strlen($deflated), strlen($data),
                strlen($name), 0, 0, 0, 0, 0, $this->offset) . $name;
        $this->offset += strlen($local) + strlen($name) + strlen($deflated);
    }

    public function close(): void
    {
        $cd = implode('', $this->central);
        fwrite($this->fh, $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, count($this->central), count($this->central), strlen($cd), $this->offset, 0));
        fclose($this->fh);
    }

    private function dosTime(): array
    {
        $t = getdate();
        return [($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2), (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday']];
    }
}

function backup_dir(): string
{
    if (!is_dir(BACKUP_DIR)) {
        mkdir(BACKUP_DIR, 0775, true);
    }
    if (!is_file(BACKUP_DIR . '/.htaccess')) {
        file_put_contents(BACKUP_DIR . '/.htaccess', "Require all denied\n");
    }
    return BACKUP_DIR;
}

/** ส่งออกฐานข้อมูลทั้งหมดเป็น SQL — หนึ่งคำสั่งต่อหนึ่งบรรทัด เพื่อให้กู้คืนได้ง่าย */
function backup_dump_sql(): string
{
    $pdo = db();
    $c = $GLOBALS['config'];
    $out = "-- ProjectHub backup\n-- version: " . APP_VERSION . "\n-- database: {$c['db_name']}\n-- created: " . date('Y-m-d H:i:s') . "\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$table]) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        $out .= "DROP TABLE IF EXISTS `$table`;\n" . str_replace(["\r", "\n"], ' ', $create) . ";\n";
        $st = $pdo->query("SELECT * FROM `$table`");
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
            if (count($batch) >= 200) {
                $out .= "INSERT INTO `$table` VALUES " . implode(',', $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) {
            $out .= "INSERT INTO `$table` VALUES " . implode(',', $batch) . ";\n";
        }
    }
    return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
}

/** สร้างไฟล์สำรอง คืนค่าชื่อไฟล์ */
function backup_create(string $kind = 'manual', bool $withFiles = true): string
{
    set_time_limit(0);
    $name = 'projecthub_' . date('Ymd_His') . '_' . $kind . ($withFiles ? '' : '_db') . '.zip';
    $zip = new ZipWriter(backup_dir() . '/' . $name);
    $zip->add('database.sql', backup_dump_sql());
    $count = 0;
    if ($withFiles && is_dir(UPLOAD_DIR)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(UPLOAD_DIR) + 1));
            if ($file->isFile() && $rel !== '.htaccess') {
                $zip->add('uploads/' . $rel, (string)file_get_contents($file->getPathname()));
                $count++;
            }
        }
    }
    $zip->add('backup.json', (string)json_encode([
        'app' => 'ProjectHub', 'version' => APP_VERSION, 'created' => date('c'), 'kind' => $kind, 'files' => $count, 'college' => college_name(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $zip->close();
    q('INSERT INTO settings (name, value) VALUES ("last_backup", ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [date('Y-m-d H:i:s')]);
    backup_prune();
    return $name;
}

function backup_list(): array
{
    $list = [];
    foreach (glob(backup_dir() . '/projecthub_*.zip') ?: [] as $path) {
        $list[] = ['name' => basename($path), 'size' => filesize($path), 'time' => filemtime($path),
                   'kind' => str_contains($path, '_auto') ? 'auto' : (str_contains($path, '_prerestore') ? 'prerestore' : (str_contains($path, '_upload') ? 'upload' : 'manual'))];
    }
    usort($list, fn($a, $b) => $b['time'] <=> $a['time']);
    return $list;
}

/** คืนค่า path จริงของไฟล์สำรอง (ป้องกันการอ้างถึงไฟล์นอกโฟลเดอร์) */
function backup_path(string $name): ?string
{
    if (!preg_match('/^projecthub_[\w-]+\.zip$/', $name)) {
        return null;
    }
    $path = backup_dir() . '/' . $name;
    return is_file($path) ? $path : null;
}

/** ลบไฟล์สำรองอัตโนมัติที่เก่าเกินจำนวนที่กำหนด (ไฟล์ที่สำรองเองไม่ถูกลบ) */
function backup_prune(): void
{
    $keep = max(1, (int)setting('backup_keep', '10'));
    $auto = array_values(array_filter(backup_list(), fn($b) => in_array($b['kind'], ['auto', 'prerestore'], true)));
    foreach (array_slice($auto, $keep) as $b) {
        @unlink(BACKUP_DIR . '/' . $b['name']);
    }
}

/** กู้คืนจากไฟล์สำรอง: แทนที่ฐานข้อมูลทั้งหมด และเขียนไฟล์แนบทับ */
function backup_restore(string $path): array
{
    set_time_limit(0);
    $entries = zip_entries((string)file_get_contents($path), fn($n) => $n === 'database.sql' || $n === 'backup.json' || str_starts_with($n, 'uploads/'));
    if (!isset($entries['database.sql']) || !str_starts_with($entries['database.sql'], '-- ProjectHub backup')) {
        throw new RuntimeException('ไฟล์นี้ไม่ใช่ไฟล์สำรองของ ProjectHub');
    }
    $pdo = db();
    $statements = 0;
    foreach (explode("\n", $entries['database.sql']) as $line) {
        $line = rtrim($line, "\r");
        if ($line === '' || str_starts_with($line, '--')) {
            continue;
        }
        $pdo->exec($line);
        $statements++;
    }
    $files = 0;
    foreach ($entries as $name => $data) {
        if (!str_starts_with($name, 'uploads/') || str_ends_with($name, '/')) {
            continue;
        }
        $rel = substr($name, 8);
        if (str_contains($rel, '..') || $rel === '.htaccess') {
            continue;
        }
        $target = UPLOAD_DIR . '/' . $rel;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        file_put_contents($target, $data);
        $files++;
    }
    return [$statements, $files, json_decode($entries['backup.json'] ?? '{}', true) ?: []];
}

/** สำรองอัตโนมัติเมื่อถึงรอบ (เรียกจากหน้าแดชบอร์ดผู้ดูแลระบบ หรือ Task Scheduler) */
function backup_auto_if_due(): ?string
{
    $days = (int)setting('backup_auto_days', '1');
    if ($days <= 0) {
        return null;
    }
    $last = setting('last_auto_backup');
    if ($last !== '' && strtotime($last) > time() - $days * 86400 + 600) {
        return null;
    }
    q('INSERT INTO settings (name, value) VALUES ("last_auto_backup", ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [date('Y-m-d H:i:s')]);
    return backup_create('auto', setting('backup_auto_files', '1') === '1');
}

function format_bytes(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : number_format($b / 1024, 1) . ' KB';
}
