<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
if (!can_view_module('notifikasi')) {
    http_response_code(403);
    echo json_encode(['error'=>'forbidden']);
    exit;
}
$count = (int)$pdo->query('SELECT COUNT(*) FROM notifikasi WHERE dibaca=0')->fetchColumn();
echo json_encode(['count'=>$count], JSON_THROW_ON_ERROR);
