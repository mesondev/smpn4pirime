<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/helpers.php';
require_login();
require_once dirname(__DIR__) . '/config/database.php';
if (!in_array(current_role(), ['admin', 'guru', 'siswa'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}
$learningSchema = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('materi_pelajaran','tugas_pelajaran','pengumpulan_tugas')")->fetchColumn();
if ($learningSchema !== 3) {
    http_response_code(503);
    exit('Modul materi belum tersedia. Hubungi Super Admin.');
}
$type = (string)($_GET['type'] ?? '');
$id = (int)($_GET['id'] ?? 0);
if (!in_array($type, ['materi', 'jawaban'], true) || $id < 1) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

if ($type === 'materi') {
    $stmt = $pdo->prepare('SELECT m.file_path,m.file_name,m.guru_id,m.kelas_id,m.status,u.guru_id AS account_guru,u.siswa_id AS account_siswa,u.aktif FROM materi_pelajaran m JOIN users u ON u.id=? WHERE m.id=? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id'], $id]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['aktif'] !== 1 || !$row['file_path']) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }
    $allowed = current_role() === 'admin';
    if (current_role() === 'guru' && (int)$row['guru_id'] === (int)$row['account_guru']) $allowed = true;
    if (current_role() === 'siswa' && $row['status'] === 'Terbit') {
        $check = $pdo->prepare("SELECT 1 FROM siswa WHERE id=? AND kelas_id=? AND status='Aktif'");
        $check->execute([(int)$row['account_siswa'], (int)$row['kelas_id']]);
        $allowed = (bool)$check->fetchColumn();
    }
} else {
    $stmt = $pdo->prepare('SELECT p.file_path,p.file_name,p.siswa_id,t.guru_id,u.guru_id AS account_guru,u.siswa_id AS account_siswa,u.aktif FROM pengumpulan_tugas p JOIN tugas_pelajaran t ON t.id=p.tugas_id JOIN users u ON u.id=? WHERE p.id=? LIMIT 1');
    $stmt->execute([(int)$_SESSION['user_id'], $id]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['aktif'] !== 1 || !$row['file_path']) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }
    $allowed = current_role() === 'admin';
    if (current_role() === 'guru' && (int)$row['guru_id'] === (int)$row['account_guru']) $allowed = true;
    if (current_role() === 'siswa' && (int)$row['siswa_id'] === (int)$row['account_siswa']) $allowed = true;
}
if (!$allowed) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke file ini.');
}
if (!preg_match('~^uploads/akademik/([a-f0-9]{40}\.(?:pdf|doc|docx|xls|xlsx|ppt|pptx|txt|jpg|png|webp))$~i', (string)$row['file_path'], $match)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}
$root = realpath(dirname(__DIR__) . '/uploads/akademik');
$file = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
$name = preg_replace('/[^\pL\pN._ -]/u', '_', basename((string)($row['file_name'] ?: 'berkas')));
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($file));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . str_replace(['"', '\\'], ['_', '_'], $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
readfile($file);
