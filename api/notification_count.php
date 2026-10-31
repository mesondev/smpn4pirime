<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
if (!can_view_module('notifikasi')) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
$userId = (int)($_SESSION['user_id'] ?? 0);
$hasRecipient = db_has_column($pdo, 'notifikasi', 'recipient_user_id');
$hasArchive = db_has_column($pdo, 'notifikasi', 'archived_at');
$canSeeLegacy = in_array(current_role(), ['admin', 'operator'], true);
$scopeSql = $hasRecipient ? '(recipient_user_id=?' . ($canSeeLegacy ? ' OR recipient_user_id IS NULL' : '') . ')' : ($canSeeLegacy ? '1=1' : '1=0');
$scopeParams = $hasRecipient ? [$userId] : [];
$archiveSql = $hasArchive ? ' AND archived_at IS NULL' : '';
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifikasi WHERE dibaca=0' . $archiveSql . ' AND ' . $scopeSql);
$countStmt->execute($scopeParams);
$count = (int)$countStmt->fetchColumn();
$after = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT);
$after = max(0, (int)($after ?: 0));
$latestStmt = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM notifikasi WHERE ' . $scopeSql);
$latestStmt->execute($scopeParams);
$latestId = (int)$latestStmt->fetchColumn();
$stmt = $pdo->prepare('SELECT id,judul,pesan,tautan,created_at FROM notifikasi WHERE id>?' . $archiveSql . ' AND ' . $scopeSql . ' ORDER BY id DESC LIMIT 5');
$stmt->execute([$after, ...$scopeParams]);
$items = $stmt->fetchAll();
echo json_encode(['count' => $count, 'latest_id' => $latestId, 'items' => $items], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
