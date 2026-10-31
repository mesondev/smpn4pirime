<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

function portal_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
function portal_schema_ready(PDO $pdo, string $table, array $columns = []): bool
{
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
    } catch (PDOException $e) {
        return false;
    }
}
function portal_row(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}
function portal_profile(PDO $pdo): array
{
    return array_replace(['nama' => 'SMP Negeri 4 Pirime', 'alamat' => '', 'telepon' => '', 'email' => '', 'sejarah' => '', 'visi' => '', 'misi' => '', 'sambutan' => '', 'jam_layanan' => '', 'peta_url' => ''], portal_row($pdo, 'SELECT * FROM profil_sekolah WHERE id=1 LIMIT 1'));
}
// Expose aggregate public counts only, and leave unavailable metrics blank when a schema is incomplete.
function portal_public_stats(PDO $pdo): array
{
    $stats = ['siswa' => null, 'guru' => null, 'kelas' => null, 'pengumuman' => null];
    $count = static function (string $sql) use ($pdo): ?int {
        try {
            return (int)$pdo->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            return null;
        }
    };
    if (portal_schema_ready($pdo, 'siswa', ['id', 'status'])) $stats['siswa'] = $count("SELECT COUNT(*) FROM siswa WHERE status='Aktif'");
    if (portal_schema_ready($pdo, 'guru', ['id', 'status'])) $stats['guru'] = $count("SELECT COUNT(*) FROM guru WHERE status='Aktif'");
    if (portal_schema_ready($pdo, 'kelas', ['id', 'tahun_ajaran_id']) && portal_schema_ready($pdo, 'tahun_ajaran', ['id', 'aktif'])) {
        $stats['kelas'] = $count('SELECT COUNT(*) FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id WHERE ta.aktif=1');
    }
    if (portal_schema_ready($pdo, 'pengumuman', ['id', 'status', 'mulai_tayang', 'selesai_tayang'])) {
        $stats['pengumuman'] = $count("SELECT COUNT(*) FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE())");
    }
    return $stats;
}
// Build accessible breadcrumbs and add the parent listing on article-detail pages.
function portal_breadcrumb_markup(string $activePage, string $pageTitle): string
{
    $parents = [
        'berita' => ['label' => 'Berita', 'href' => 'berita.php'],
        'pengumuman' => ['label' => 'Pengumuman', 'href' => 'pengumuman.php'],
    ];
    $parent = $parents[$activePage] ?? null;
    $isDetail = $parent !== null && !in_array($pageTitle, ['Berita dan Kegiatan', 'Pengumuman'], true);
    $crumb = '<nav class="page-breadcrumb" aria-label="Breadcrumb"><a href="index.php">Beranda</a><span aria-hidden="true">/</span>';
    if ($isDetail) {
        $crumb .= '<a href="' . e($parent['href']) . '">' . e($parent['label']) . '</a><span aria-hidden="true">/</span>';
    }
    return $crumb . '<span aria-current="page">' . e($pageTitle) . '</span></nav>';
}
function portal_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) return '';
    $time = strtotime($date);
    return $time ? date($format, $time) : '';
}
function portal_image(?string $url, string $type = '', int $id = 0): string
{
    $url = trim((string)$url);
    if (preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $url) && in_array($type, ['berita', 'galeri'], true) && $id > 0) {
        return '../media/portal.php?type=' . $type . '&id=' . $id;
    }
    return '';
}
function portal_excerpt(string $text, int $length = 180): string
{
    $text = trim(strip_tags($text));
    return mb_strlen($text, 'UTF-8') > $length ? mb_substr($text, 0, $length, 'UTF-8') . '…' : $text;
}
