<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Pencarian';
$activePage = 'pencarian';
$query = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$results = [];
if ($query !== '') {
    $term = '%' . $query . '%';
    if (portal_schema_ready($pdo, 'berita_sekolah', ['id', 'judul', 'ringkasan', 'isi', 'status', 'published_at', 'created_at'])) {
        foreach (portal_rows($pdo, "SELECT id,judul,ringkasan,isi,COALESCE(published_at,created_at) AS tanggal FROM berita_sekolah WHERE status='Terbit' AND (judul LIKE ? OR ringkasan LIKE ? OR isi LIKE ?) ORDER BY COALESCE(published_at,created_at) DESC LIMIT 30", [$term, $term, $term]) as $row) {
            $results[] = ['type' => 'Berita', 'title' => $row['judul'], 'text' => $row['ringkasan'] ?: $row['isi'], 'date' => $row['tanggal'], 'url' => 'detail_berita.php?id=' . (int)$row['id']];
        }
    }
    if (portal_schema_ready($pdo, 'pengumuman', ['id', 'judul', 'isi', 'status', 'mulai_tayang', 'selesai_tayang', 'created_at'])) {
        foreach (portal_rows($pdo, "SELECT id,judul,isi,COALESCE(mulai_tayang,DATE(created_at)) AS tanggal FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE()) AND (judul LIKE ? OR isi LIKE ?) ORDER BY COALESCE(mulai_tayang,DATE(created_at)) DESC LIMIT 30", [$term, $term]) as $row) {
            $results[] = ['type' => 'Pengumuman', 'title' => $row['judul'], 'text' => $row['isi'], 'date' => $row['tanggal'], 'url' => 'detail_pengumuman.php?id=' . (int)$row['id']];
        }
    }
    if (portal_schema_ready($pdo, 'portal_ekstrakurikuler', ['nama', 'kategori', 'deskripsi', 'status'])) {
        foreach (portal_rows($pdo, "SELECT nama,kategori,deskripsi FROM portal_ekstrakurikuler WHERE status='Terbit' AND (nama LIKE ? OR kategori LIKE ? OR deskripsi LIKE ?) ORDER BY urutan,nama LIMIT 15", [$term, $term, $term]) as $row) {
            $results[] = ['type' => 'Ekstrakurikuler', 'title' => $row['nama'], 'text' => $row['deskripsi'] ?: $row['kategori'], 'date' => null, 'url' => 'ekstrakurikuler.php'];
        }
    }
    if (portal_schema_ready($pdo, 'portal_fasilitas', ['nama', 'deskripsi', 'status'])) {
        foreach (portal_rows($pdo, "SELECT nama,deskripsi FROM portal_fasilitas WHERE status='Terbit' AND (nama LIKE ? OR deskripsi LIKE ?) ORDER BY urutan,nama LIMIT 15", [$term, $term]) as $row) {
            $results[] = ['type' => 'Fasilitas', 'title' => $row['nama'], 'text' => $row['deskripsi'], 'date' => null, 'url' => 'fasilitas.php'];
        }
    }
    if (portal_schema_ready($pdo, 'portal_prestasi_publik', ['judul', 'penerima', 'deskripsi', 'status', 'bukti_terverifikasi', 'izin_publikasi'])) {
        foreach (portal_rows($pdo, "SELECT judul,penerima,deskripsi,tanggal FROM portal_prestasi_publik WHERE status='Terbit' AND bukti_terverifikasi=1 AND izin_publikasi=1 AND (judul LIKE ? OR penerima LIKE ? OR deskripsi LIKE ?) ORDER BY tanggal DESC LIMIT 15", [$term, $term, $term]) as $row) {
            $results[] = ['type' => 'Prestasi', 'title' => $row['judul'], 'text' => $row['penerima'] . ' · ' . $row['deskripsi'], 'date' => $row['tanggal'], 'url' => 'prestasi.php'];
        }
    }
    if (portal_schema_ready($pdo, 'portal_dokumen_publik', ['id', 'judul', 'kategori', 'deskripsi', 'status', 'published_at'])) {
        foreach (portal_rows($pdo, "SELECT id,judul,kategori,deskripsi,published_at FROM portal_dokumen_publik WHERE status='Terbit' AND (published_at IS NULL OR published_at<=NOW()) AND (judul LIKE ? OR kategori LIKE ? OR deskripsi LIKE ?) ORDER BY published_at DESC LIMIT 15", [$term, $term, $term]) as $row) {
            $results[] = ['type' => 'Dokumen', 'title' => $row['judul'], 'text' => $row['deskripsi'] ?: $row['kategori'], 'date' => $row['published_at'], 'url' => '../media/public_document.php?id=' . (int)$row['id']];
        }
    }
    usort($results, static fn(array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));
    $results = array_slice($results, 0, 30);
}
require __DIR__ . '/_header.php'; ?>
<section class="page-hero">
    <div class="wrap"><span class="eyebrow">Temukan informasi</span>
        <h1>Pencarian Portal</h1>
        <p>Cari berita, pengumuman, kegiatan, fasilitas, prestasi, dan dokumen yang telah dipublikasikan sekolah.</p><?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>
<section class="content">
    <div class="wrap public-search-content">
        <form class="search-form public-search-form" method="get" role="search"><label class="visually-hidden" for="publicSearchQuery">Kata kunci</label><input id="publicSearchQuery" name="q" value="<?= e($query) ?>" maxlength="100" placeholder="Cari informasi sekolah" required><button type="submit"><i class="bi bi-search" aria-hidden="true"></i> Cari</button></form>
        <?php if ($query !== ''): ?><p class="search-result-summary">Hasil untuk <strong>“<?= e($query) ?>”</strong>: <?= count($results) ?> konten</p>
            <div class="notice-list">
                <?php if (!$results): ?><?php public_empty_panel('search', 'Belum ada hasil yang cocok', 'Coba gunakan kata kunci lain. Anda juga dapat menghubungi sekolah jika membutuhkan informasi tertentu.'); ?><?php else: foreach ($results as $item): ?><a class="notice-item" href="<?= e($item['url']) ?>"><span class="search-result-meta"><span class="date-label"><?= e($item['type']) ?></span><time><?= e(portal_date($item['date'])) ?></time></span>
                    <h2><?= e($item['title']) ?></h2>
                    <p><?= e(portal_excerpt((string)$item['text'], 220)) ?></p><span class="text-link">Baca selengkapnya <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a><?php endforeach;
                                                                                                                                                                                                                    endif; ?>
            </div><?php else: ?><div class="empty search-hint"><i class="bi bi-search" aria-hidden="true"></i><strong>Mulai pencarian</strong><span>Masukkan kata kunci untuk menemukan berita, pengumuman, kegiatan, fasilitas, prestasi, dan dokumen sekolah.</span></div><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>