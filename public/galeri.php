<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Galeri Kegiatan';
$activePage = 'galeri';
$schemaReady = portal_schema_ready($pdo, 'galeri_sekolah', ['id', 'judul', 'keterangan', 'gambar_url', 'tanggal_kegiatan', 'status', 'created_at']);
$items = $schemaReady ? portal_rows($pdo, "SELECT id,judul,keterangan,gambar_url,tanggal_kegiatan FROM galeri_sekolah WHERE status='Terbit' ORDER BY tanggal_kegiatan DESC,id DESC") : [];
$items = array_values(array_filter($items, static fn(array $item): bool => portal_image($item['gambar_url'] ?? '', 'galeri', (int)$item['id']) !== ''));
require __DIR__ . '/_header.php'; ?>
<section class="page-hero gallery-page-hero">
    <div class="wrap">
        <span class="eyebrow">Dokumentasi kehidupan kampus</span>
        <h1>Galeri &amp; Media Sekolah</h1>
        <p>Galeri kegiatan belajar, kebersamaan siswa, fasilitas, dan lingkungan SMP Negeri 4 Pirime yang telah diterbitkan untuk publik.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content gallery-page-content">
    <div class="wrap">
        <header class="gallery-section-heading"><span><i class="bi bi-camera-fill" aria-hidden="true"></i> Dokumentasi sekolah</span><h2>Aktivitas &amp; Fasilitas Sekolah</h2><p>Momen kegiatan yang telah dipilih dan diterbitkan oleh sekolah.</p></header>
        <?php if (!$schemaReady): ?>
            <?php public_service_notice('Dokumentasi kegiatan belum dapat dimuat. Silakan kunjungi kembali setelah data tersedia.'); ?>
        <?php elseif (!$items): ?>
            <?php public_empty_panel('images', 'Belum ada dokumentasi kegiatan', 'Foto kegiatan akan ditampilkan setelah dipilih dan diterbitkan oleh sekolah.'); ?>
        <?php else: ?>
            <div class="cards">
                <?php foreach ($items as $g): $img = portal_image($g['gambar_url'], 'galeri', (int)$g['id']); if (!$img) continue; ?><figure class="card story-card public-gallery-card"><img class="story-image" src="<?= e($img) ?>" alt="Dokumentasi sekolah: <?= e($g['judul']) ?>" loading="lazy" decoding="async"><figcaption class="story-body"><h3><?= e($g['judul']) ?></h3><p><?= e($g['keterangan']) ?><?= $g['tanggal_kegiatan'] ? ' · ' . e(portal_date($g['tanggal_kegiatan'])) : '' ?></p></figcaption></figure><?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php if ($items): ?><p class="public-image-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Foto ditayangkan setelah disetujui untuk publikasi. Persetujuan wali murid diperiksa bila diperlukan.</p><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
