<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$type = (string)($_GET['type'] ?? '');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$tables = ['berita' => ['table' => 'berita_sekolah'], 'galeri' => ['table' => 'galeri_sekolah']];
if ($id < 1 || !isset($tables[$type])) { http_response_code(404); exit; }

try {
    $stmt = $pdo->prepare('SELECT gambar_url,status FROM `' . $tables[$type]['table'] . '` WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
} catch (PDOException $e) { http_response_code(404); exit; }

$isStaff = false;
if (is_logged_in()) {
    $actorStmt = $pdo->prepare('SELECT role,aktif FROM users WHERE id=?');
    $actorStmt->execute([(int)$_SESSION['user_id']]);
    $actor = $actorStmt->fetch();
    $isStaff = $actor && (int)$actor['aktif'] === 1 && $actor['role'] === 'admin';
}
$isPublic = ($record['status'] ?? '') === 'Terbit';
if (!$record || (!$isStaff && !$isPublic) || !preg_match('~^uploads/portal/([a-f0-9]{40}\.(?:jpg|png|webp))$~i', (string)$record['gambar_url'], $match)) {
    http_response_code(404);
    exit;
}

$root = realpath(__DIR__ . '/../uploads/portal');
$path = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) { http_response_code(404); exit; }
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);
$image = @getimagesize($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !$image || ($image['mime'] ?? '') !== $mime) { http_response_code(404); exit; }

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
header($isPublic ? 'Cache-Control: public, max-age=300' : 'Cache-Control: private, no-store');
readfile($path);
