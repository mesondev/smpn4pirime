<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

function portal_rows(PDO $pdo, string $sql, array $params = []): array {
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    catch (PDOException $e) { return []; }
}
function portal_schema_ready(PDO $pdo, string $table, array $columns = []): bool {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $stmt->execute([$table]);
        if ((int)$stmt->fetchColumn() !== 1) return false;
        foreach ($columns as $column) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
            $check->execute([$table, $column]);
            if ((int)$check->fetchColumn() !== 1) return false;
        }
        return true;
    } catch (PDOException $e) { return false; }
}
function portal_row(PDO $pdo, string $sql, array $params = []): array {
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetch() ?: []; }
    catch (PDOException $e) { return []; }
}
function portal_profile(PDO $pdo): array {
    return array_replace(['nama' => 'SMP Negeri 4 Pirime', 'alamat' => '', 'telepon' => '', 'email' => '', 'sejarah' => '', 'visi' => '', 'misi' => '', 'sambutan' => '', 'jam_layanan' => '', 'peta_url' => ''], portal_row($pdo, 'SELECT * FROM profil_sekolah WHERE id=1 LIMIT 1'));
}
function portal_date(?string $date, string $format = 'd M Y'): string {
    if (!$date) return '';
    $time = strtotime($date);
    return $time ? date($format, $time) : '';
}
function portal_image(?string $url, string $type = '', int $id = 0): string {
    $url = trim((string)$url);
    if (preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $url) && in_array($type, ['berita', 'galeri'], true) && $id > 0) {
        return '../media/portal.php?type=' . $type . '&id=' . $id;
    }
    return '';
}
function portal_excerpt(string $text, int $length = 180): string {
    $text = trim(strip_tags($text));
    return mb_strlen($text, 'UTF-8') > $length ? mb_substr($text, 0, $length, 'UTF-8') . '…' : $text;
}
