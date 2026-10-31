<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

$teacherId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($teacherId < 1) {
    http_response_code(404);
    exit;
}
try {
    $schema = array_column($pdo->query('SHOW COLUMNS FROM guru')->fetchAll(), null, 'Field');
} catch (PDOException $e) {
    http_response_code(404);
    exit;
}
if (!isset($schema['foto'])) {
    http_response_code(404);
    exit;
}
$publicColumn = isset($schema['tampil_publik']);
$stmt = $pdo->prepare('SELECT foto,status' . ($publicColumn ? ',tampil_publik' : '') . ' FROM guru WHERE id=? LIMIT 1');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch();
if (!$teacher || !is_string($teacher['foto'] ?? null) || !preg_match('~^uploads/guru/([a-f0-9-]+\.(?:jpg|png|webp))$~i', $teacher['foto'], $match)) {
    http_response_code(404);
    exit;
}
$isStaff = false;
if (is_logged_in()) {
    $actorStmt = $pdo->prepare('SELECT role,aktif FROM users WHERE id=?');
    $actorStmt->execute([(int)$_SESSION['user_id']]);
    $actor = $actorStmt->fetch();
    $isStaff = $actor && (int)$actor['aktif'] === 1 && in_array($actor['role'], ['admin', 'operator'], true);
}
$isPublic = ($teacher['status'] ?? '') === 'Aktif' && $publicColumn && (int)($teacher['tampil_publik'] ?? 0) === 1;
if (!$isStaff && !$isPublic) {
    http_response_code(404);
    exit;
}
$uploadRoot = realpath(__DIR__ . '/../uploads/guru');
$imagePath = $uploadRoot ? realpath($uploadRoot . DIRECTORY_SEPARATOR . $match[1]) : false;
if (!$uploadRoot || !$imagePath || !str_starts_with($imagePath, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($imagePath)) {
    http_response_code(404);
    exit;
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($imagePath);
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$imageInfo = @getimagesize($imagePath);
if (!in_array($mime, $allowed, true) || !$imageInfo || ($imageInfo['mime'] ?? '') !== $mime) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($imagePath));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
header($isPublic ? 'Cache-Control: public, max-age=300' : 'Cache-Control: private, no-store');
readfile($imagePath);
