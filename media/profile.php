<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id < 1 || !is_logged_in()) {
    http_response_code(404);
    exit;
}
try {
    $actorStmt = $pdo->prepare('SELECT role,aktif FROM users WHERE id=?');
    $actorStmt->execute([(int)$_SESSION['user_id']]);
    $actor = $actorStmt->fetch();
} catch (PDOException $e) {
    http_response_code(404);
    exit;
}
if (!$actor || (int)$actor['aktif'] !== 1 || ((int)$_SESSION['user_id'] !== $id && $actor['role'] !== 'admin')) {
    http_response_code(404);
    exit;
}
try {
    $stmt = $pdo->prepare('SELECT foto_path FROM users WHERE id=? AND aktif=1');
    $stmt->execute([$id]);
    $relative = $stmt->fetchColumn();
} catch (PDOException $e) {
    http_response_code(404);
    exit;
}
if (!is_string($relative) || !preg_match('~^uploads/profil/([a-f0-9]{40}\.(?:jpg|png|webp))$~i', $relative, $match)) {
    http_response_code(404);
    exit;
}
$root = realpath(__DIR__ . '/../uploads/profil');
$path = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
$image = @getimagesize($path);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !$image || ($image['mime'] ?? '') !== $mime) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline');
header('Cache-Control: private, no-store');
readfile($path);
