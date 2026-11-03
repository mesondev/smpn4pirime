<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

function portal_log_query_failure(string $operation, PDOException $exception): void
{
    app_log('error', 'Public portal database query failed', [
        'operation' => $operation,
        'exception' => $exception->getMessage(),
    ]);
}

function portal_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        portal_log_query_failure('fetch rows', $e);
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
        portal_log_query_failure('check schema', $e);
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
        portal_log_query_failure('fetch row', $e);
        return [];
    }
}
function portal_profile(PDO $pdo): array
{
    return array_replace(['nama' => 'SMP Negeri 4 Pirime', 'npsn' => '60303637', 'alamat' => '', 'alamat_jalan' => 'Jln. Milimbo-Tiom', 'kampung' => 'Umbanume', 'distrik' => 'Pirime', 'kabupaten' => 'Lanny Jaya', 'provinsi' => 'Papua Pegunungan', 'kode_pos' => '', 'status_sekolah' => 'Negeri', 'jenjang' => 'SMP', 'naungan' => 'Pemerintah Daerah', 'akreditasi' => 'C', 'akreditasi_status' => 'perlu_verifikasi', 'visi_status' => 'rancangan', 'misi_status' => 'rancangan', 'tujuan_status' => 'rancangan', 'sejarah_status' => 'perlu_verifikasi', 'tujuan' => '', 'telepon' => '', 'email' => '', 'sejarah' => '', 'visi' => '', 'misi' => '', 'sambutan' => '', 'jam_layanan' => '', 'peta_url' => ''], portal_row($pdo, 'SELECT * FROM profil_sekolah WHERE id=1 LIMIT 1'));
}
function portal_phone_uri(?string $phone): string
{
    $normalized = preg_replace('/[^0-9+]/', '', trim((string)$phone)) ?? '';
    return preg_match('/^\+?[0-9]{7,20}$/', $normalized) ? $normalized : '';
}
function portal_whatsapp_number(?string $phone): string
{
    $number = preg_replace('/\D+/', '', trim((string)$phone)) ?? '';
    if (str_starts_with($number, '0')) $number = '62' . substr($number, 1);
    return preg_match('/^[0-9]{7,20}$/', $number) ? $number : '';
}
function portal_email_address(?string $email): string
{
    $email = trim((string)$email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}
// Expose aggregate public counts only, and leave unavailable metrics blank when a schema is incomplete.
function portal_public_stats(PDO $pdo): array
{
    $stats = ['siswa' => null, 'guru' => null, 'kelas' => null, 'pengumuman' => null];
    $count = static function (string $sql) use ($pdo): ?int {
        try {
            return (int)$pdo->query($sql)->fetchColumn();
        } catch (PDOException $e) {
            portal_log_query_failure('count public statistic', $e);
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
    if (preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $url) && in_array($type, ['berita', 'galeri', 'fasilitas', 'struktur'], true) && $id > 0) {
        return '../media/portal.php?type=' . $type . '&id=' . $id;
    }
    return '';
}
function portal_excerpt(string $text, int $length = 180): string
{
    $text = trim(strip_tags($text));
    return mb_strlen($text, 'UTF-8') > $length ? mb_substr($text, 0, $length, 'UTF-8') . '…' : $text;
}

function portal_social_links(PDO $pdo): array
{
    if (!portal_schema_ready($pdo, 'portal_tautan_sosial', ['platform', 'url', 'status'])) return [];
    $allowed = ['facebook', 'instagram', 'youtube', 'tiktok', 'twitter-x'];
    $links = [];
    foreach (portal_rows($pdo, "SELECT platform,url FROM portal_tautan_sosial WHERE status='aktif' ORDER BY id") as $row) {
        $platform = (string)$row['platform'];
        $url = filter_var($row['url'], FILTER_VALIDATE_URL);
        if (in_array($platform, $allowed, true) && $url && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['https'], true)) $links[$platform] = $url;
    }
    return $links;
}
