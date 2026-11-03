<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Galeri Kegiatan';
$activePage = 'galeri';
$schemaReady = portal_schema_ready($pdo, 'galeri_sekolah', ['id', 'judul', 'keterangan', 'gambar_url', 'tanggal_kegiatan', 'status', 'created_at']);
$items = $schemaReady ? portal_rows($pdo, "SELECT id,judul,keterangan,gambar_url,tanggal_kegiatan FROM galeri_sekolah WHERE status='Terbit' ORDER BY tanggal_kegiatan DESC,id DESC") : [];
$illustrationImages = public_gallery_images();
require __DIR__ . '/_header.php'; ?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Momen sekolah</span>
        <h1>Galeri Kegiatan</h1>
        <p>Galeri gambar pendukung kegiatan dan pembelajaran.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content">
    <div class="wrap">
        <?php if (!$schemaReady): ?><?php public_service_notice('Dokumentasi kegiatan sekolah belum dapat dimuat. Foto ilustrasi tetap tersedia di bawah sebagai gambaran kegiatan belajar.'); ?><?php elseif (!$items): ?>
        <?php public_empty_panel('images', 'Dokumentasi sekolah belum diterbitkan', 'Gambar berikut adalah ilustrasi. Dokumentasi kegiatan aktual akan ditampilkan setelah dipilih dan disetujui sekolah.'); ?>
    <?php endif; ?>
    <div class="cards">
        <?php foreach ($items as $g): $img = portal_image($g['gambar_url'], 'galeri', (int)$g['id']);
            if (!$img) continue; ?><figure class="card story-card public-gallery-card"><img class="story-image" src="<?= e($img) ?>" alt="Dokumentasi sekolah: <?= e($g['judul']) ?>" loading="lazy" decoding="async">
                <figcaption class="story-body">
                    <h3><?= e($g['judul']) ?></h3>
                    <p><?= e($g['keterangan']) ?>
                        <?= $g['tanggal_kegiatan'] ? ' · ' . e(portal_date($g['tanggal_kegiatan'])) : '' ?></p>
                </figcaption>
            </figure>
        <?php endforeach; ?>
        <?php foreach ($illustrationImages as $image): ?>
            <figure class="card story-card public-gallery-card public-gallery-card--illustration">
                <img class="story-image" src="<?= e($image['src']) ?>" alt="<?= e($image['category'] . ': ' . $image['title']) ?>" loading="lazy" decoding="async">
                <figcaption class="story-body"><span class="kicker">Foto ilustrasi</span>
                    <h2><?= e($image['title']) ?></h2>
                    <p><?= e($image['category']) ?> · bukan dokumentasi kegiatan aktual sekolah.</p>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <p class="public-image-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Foto berlabel ilustrasi berasal dari aset pendukung portal dan bukan dokumentasi kegiatan aktual SMP Negeri 4 Pirime. Dokumentasi asli hanya ditayangkan setelah disetujui sekolah dan wali murid bila diperlukan.</p>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
