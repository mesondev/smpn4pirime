<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id < 1) {
    http_response_code(404);
    exit;
}
try {
    $stmt = $pdo->prepare("SELECT nama_file,judul FROM portal_dokumen_publik WHERE id=? AND status='Terbit' AND (published_at IS NULL OR published_at<=NOW()) LIMIT 1");
    $stmt->execute([$id]);
    $document = $stmt->fetch();
} catch (PDOException $e) {
    $document = false;
}
if (!$document || !preg_match('/^[a-f0-9]{40}\.pdf$/i', (string)$document['nama_file'])) {
    http_response_code(404);
    exit;
}
$root = realpath(__DIR__ . '/../storage/public_documents');
$path = $root ? realpath($root . DIRECTORY_SEPARATOR . $document['nama_file']) : false;
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
if ($finfo->file($path) !== 'application/pdf') {
    http_response_code(404);
    exit;
}
header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="dokumen-sekolah.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');
readfile($path);
