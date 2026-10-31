<?php require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Kontak dan Lokasi';
$activePage = 'kontak';
require __DIR__ . '/_header.php';
$safeMap = 'https://www.google.com/maps/d/embed?mid=1eSRrdHhUkSdX2se1R-QjZQa16UeT_Zk&ehbc=2E312F'; ?>

<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Kami siap membantu</span>
        <h1>Kontak dan Lokasi</h1>
        <p>Gunakan informasi kontak resmi sekolah untuk mendapatkan bantuan dan informasi.</p>
            <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <div class="contact-cards">
            <div class="contact-card">
                <span class="tile-icon">
                    <i class="bi bi-geo-alt"></i>
                </span>
                <h3>Alamat</h3>
                <p><?= nl2br(e($portalProfile['alamat'] ?: 'Alamat sekolah belum diatur.')) ?></p>
            </div>

            <div class="contact-card">
                <span class="tile-icon">
                    <i class="bi bi-telephone"></i>
                </span>
                <h3>Telepon / Email</h3>
                <p><?= e($portalProfile['telepon'] ?: 'Nomor belum diatur.') ?>
                    <br>
                    <?= e($portalProfile['email'] ?: 'Email belum diatur.') ?>
                </p>
            </div>

            <div class="contact-card">
                <span class="tile-icon">
                    <i class="bi bi-clock"></i>
                </span>
                <h3>Jam Layanan</h3>
                <p><?= nl2br(e($portalProfile['jam_layanan'] ?: 'Jam layanan belum diatur. Silakan hubungi sekolah.')) ?></p>
            </div>
        </div>
        <section class="location-showcase" aria-labelledby="contactMapTitle">
            <div class="location-map-column">
                <iframe class="map-frame" src="<?= e($safeMap) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi SMP Negeri 4 Pirime" allowfullscreen></iframe>
                <a class="location-map-open" href="https://www.google.com/maps/d/u/0/viewer?mid=1eSRrdHhUkSdX2se1R-QjZQa16UeT_Zk" target="_blank" rel="noopener noreferrer"><i class="bi bi-map-fill" aria-hidden="true"></i> Buka di Maps</a>
            </div>
            <div class="location-details">
                <span class="location-badge">Lokasi Resmi Sekolah</span>
                <h2 id="contactMapTitle"><?= e($portalProfile['nama']) ?></h2>
                <p class="location-description">Kunjungi <?= e($portalProfile['nama']) ?>. Gunakan peta dan petunjuk arah untuk menemukan lokasi sekolah.</p>
                <ul class="location-facts">
                    <li><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><span><?= e($portalProfile['alamat'] ?: 'Alamat sekolah belum diatur.') ?></span></li>
                    <li><i class="bi bi-building-fill" aria-hidden="true"></i><span>Sekolah Menengah Pertama</span></li>
                    <li><i class="bi bi-signpost-2-fill" aria-hidden="true"></i><span>Hubungi sekolah untuk informasi kunjungan dan akses lokasi.</span></li>
                </ul>
                <a class="location-directions" href="https://www.google.com/maps/dir/?api=1&amp;destination=-3.969%2C138.5385" target="_blank" rel="noopener noreferrer"><i class="bi bi-send-fill" aria-hidden="true"></i> Petunjuk Arah</a>
            </div>
        </section>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
