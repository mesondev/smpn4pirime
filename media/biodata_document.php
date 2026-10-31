<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_login();

$viewerId = (int)($_SESSION['user_id'] ?? 0);
$targetId = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT) ?: 0;
$field = (string)($_GET['field'] ?? '');
$allowedFields = ['dokumen_akta_path', 'dokumen_kk_path', 'dokumen_identitas_ortu_path', 'dokumen_ijazah_sd_path', 'dokumen_kip_path', 'dokumen_pendukung_path'];
if ($targetId < 1 || !in_array($field, $allowedFields, true)) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}
$reviewAllowed = in_array(current_role(), ['admin', 'operator'], true);
if ($targetId !== $viewerId && !$reviewAllowed) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke dokumen ini.');
}
$targetCheck = $pdo->prepare('SELECT role,aktif FROM users WHERE id=? LIMIT 1');
$targetCheck->execute([$targetId]);
$targetUser = $targetCheck->fetch();
if (!$targetUser || !(int)$targetUser['aktif'] || ($targetId !== $viewerId && current_role() === 'operator' && !in_array($targetUser['role'], ['kepala_sekolah', 'guru', 'siswa'], true))) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke dokumen ini.');
}
$statement = $pdo->prepare("SELECT `$field` FROM biodata_pengguna WHERE user_id=? LIMIT 1");
$statement->execute([$targetId]);
$relativePath = (string)($statement->fetchColumn() ?: '');
if (!preg_match('~^storage/biodata_documents/([a-f0-9]{40})\.(pdf|jpg|png)$~i', $relativePath, $match)) {
    http_response_code(404);
    exit('Dokumen belum diunggah.');
}
$root = realpath(dirname(__DIR__) . '/storage/biodata_documents');
$file = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1] . '.' . strtolower($match[2])) : false;
if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
$contentType = match ($mime) {
    'application/pdf' => 'application/pdf',
    'image/jpeg' => 'image/jpeg',
    'image/png' => 'image/png',
    default => 'application/octet-stream'
};
header('Content-Type: ' . $contentType);
header('Content-Length: ' . (string)filesize($file));
header('Content-Disposition: inline; filename="dokumen-biodata-' . $targetId . '.' . $match[2] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($file);
