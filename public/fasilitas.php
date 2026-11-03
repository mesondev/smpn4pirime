<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Fasilitas Sekolah';
$activePage = 'fasilitas';
$ready = portal_schema_ready($pdo, 'portal_fasilitas', ['nama', 'status', 'urutan', 'gambar_url']);
$facilityImages = public_facility_images();
$items = $ready
    ? portal_rows($pdo, "SELECT id, nama, deskripsi, gambar_url FROM portal_fasilitas WHERE status='Terbit' ORDER BY urutan, nama")
    : [];

require __DIR__ . '/_header.php';
public_page_hero(
    'Sarana dan prasarana',
    $pageTitle,
    'Informasi fasilitas sekolah yang telah diperiksa dan disetujui untuk ditampilkan.',
    $activePage
);
?>
<section class="content">
    <div class="wrap">
        <div class="section-head public-inline-heading">
            <div><span class="kicker">Ruang belajar</span>
                <h2>Fasilitas sekolah</h2>
                <p>Kenali ruang dan sarana yang mendukung kegiatan belajar.</p>
            </div>
        </div>
        <?php if (!$ready): ?>
            <?php public_service_notice('Data fasilitas belum dapat dimuat. Daftar dan foto fasilitas akan ditampilkan setelah datanya diperiksa dan disetujui sekolah.'); ?>
        <?php elseif (!$items): ?>
            <?php public_empty_panel('buildings', 'Belum ada data fasilitas terverifikasi', 'Foto di bawah merupakan aset referensi. Data fasilitas aktual akan ditampilkan setelah dikonfirmasi sekolah.'); ?>
        <?php else: ?>
            <div class="cards public-feature-grid public-live-facilities">
                <?php foreach ($items as $item): $image = portal_image($item['gambar_url'], 'fasilitas', (int)$item['id']); ?>
                    <article class="card public-feature-card">
                        <?php if ($image): ?><img class="public-feature-image" src="<?= e($image) ?>" alt="Fasilitas sekolah: <?= e($item['nama']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="tile-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span><?php endif; ?>
                        <span class="kicker">Informasi sekolah</span>
                        <h2><?= e($item['nama']) ?></h2>
                        <?php if ($item['deskripsi']): ?><p><?= e($item['deskripsi']) ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="section-head public-inline-heading public-reference-heading">
            <div><span class="kicker">Galeri sarana</span>
                <h2>Ruang dan fasilitas belajar</h2>
                <p>Foto referensi sesuai aset fasilitas yang tersedia di portal.</p>
            </div>
        </div>
        <div class="cards public-feature-grid">
            <?php foreach ($facilityImages as $image): ?>
                <figure class="card public-feature-card public-image-card">
                    <img class="public-feature-image" src="<?= e($image['src']) ?>" alt="<?= e($image['alt']) ?>" loading="lazy" decoding="async">
                    <figcaption><span class="kicker">Foto fasilitas</span>
                        <h3><?= e($image['title']) ?></h3>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <p class="public-image-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Foto referensi ini bersumber dari aset portal. Pengelola sekolah dapat menggantinya dengan foto terbaru dari lokasi sekolah.</p>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>