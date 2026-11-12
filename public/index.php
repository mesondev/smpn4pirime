<?php
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$schoolAddress = portal_school_address($portalProfile);
$publicStats = portal_public_stats($pdo);
$homeLeaders = portal_public_school_leaders($pdo);
$greetingApproved = portal_schema_ready($pdo, 'profil_sekolah', ['sambutan', 'sambutan_status'])
    && strtolower((string)($portalProfile['sambutan_status'] ?? '')) === 'resmi';
$activePage = 'home';
$pageTitle = 'Beranda';
$notices = portal_rows($pdo, "SELECT id,judul,isi,COALESCE(mulai_tayang,DATE(created_at)) AS tanggal FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE()) ORDER BY COALESCE(mulai_tayang,DATE(created_at)) DESC,id DESC LIMIT 3");
$portalNewsReady = portal_schema_ready($pdo, 'berita_sekolah', ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at']);
$stories = $portalNewsReady ? portal_rows($pdo, "SELECT id,judul,ringkasan,isi,kategori,gambar_url,COALESCE(published_at,created_at) AS tanggal FROM berita_sekolah WHERE status='Terbit' ORDER BY COALESCE(published_at,created_at) DESC,id DESC LIMIT 3") : [];
$events = portal_rows($pdo, "SELECT judul,kategori,tanggal_mulai,tanggal_selesai,deskripsi FROM kalender_akademik WHERE tampil_publik=1 AND status<>'Dibatalkan' AND COALESCE(tanggal_selesai,tanggal_mulai)>=CURDATE() ORDER BY tanggal_mulai LIMIT 4");
$galleryReady = portal_schema_ready($pdo, 'galeri_sekolah', ['id', 'judul', 'keterangan', 'gambar_url', 'tanggal_kegiatan', 'status', 'created_at']);
$galleryItems = $galleryReady ? portal_rows($pdo, "SELECT id,judul,keterangan,gambar_url,tanggal_kegiatan FROM galeri_sekolah WHERE status='Terbit' ORDER BY tanggal_kegiatan DESC,id DESC LIMIT 3") : [];
$galleryItems = array_values(array_filter($galleryItems, static fn(array $photo): bool => portal_image($photo['gambar_url'] ?? '', 'galeri', (int)($photo['id'] ?? 0)) !== ''));
$homeTeachersReady = portal_schema_ready($pdo, 'guru', ['id', 'nama', 'status', 'tampil_publik']);
$homeTeachers = $homeTeachersReady ? portal_rows($pdo, "SELECT id,nama,foto FROM guru WHERE status='Aktif' AND tampil_publik=1 ORDER BY nama,id LIMIT 4") : [];
$homeFacilitiesReady = portal_schema_ready($pdo, 'portal_fasilitas', ['id', 'nama', 'deskripsi', 'gambar_url', 'status', 'urutan']);
$homeFacilities = $homeFacilitiesReady ? portal_rows($pdo, "SELECT id,nama,deskripsi,gambar_url FROM portal_fasilitas WHERE status='Terbit' ORDER BY urutan,nama LIMIT 4") : [];
$publicExtrasReady = portal_schema_ready($pdo, 'portal_ekstrakurikuler', ['nama', 'status', 'urutan']);
$publicExtras = $publicExtrasReady ? portal_rows($pdo, "SELECT nama,kategori,jadwal FROM portal_ekstrakurikuler WHERE status='Terbit' ORDER BY urutan,nama LIMIT 3") : [];
$publicAwardsReady = portal_schema_ready($pdo, 'portal_prestasi_publik', ['judul', 'penerima', 'status', 'bukti_terverifikasi', 'izin_publikasi']);
$publicAwards = $publicAwardsReady ? portal_rows($pdo, "SELECT judul,penerima,tanggal FROM portal_prestasi_publik WHERE status='Terbit' AND bukti_terverifikasi=1 AND izin_publikasi=1 ORDER BY tanggal DESC,id DESC LIMIT 2") : [];
$publicDownloadsReady = portal_schema_ready($pdo, 'portal_dokumen_publik', ['judul', 'kategori', 'nama_file', 'status']);
$publicDownloads = $publicDownloadsReady ? portal_rows($pdo, "SELECT id,judul,kategori FROM portal_dokumen_publik WHERE status='Terbit' AND (published_at IS NULL OR published_at<=NOW()) ORDER BY published_at DESC,id DESC LIMIT 3") : [];
require __DIR__ . '/_header.php'; ?>

<?php
$heroSlides = [
    ['label' => 'Portal Informasi Resmi', 'title' => 'Selamat datang di ' . $portalProfile['nama'], 'text' => $greetingApproved && !empty($portalProfile['sambutan']) ? portal_excerpt($portalProfile['sambutan'], 170) : 'Temukan profil, informasi akademik, kabar, dan layanan sekolah melalui portal ini.', 'primary' => 'Kenali sekolah', 'primary_url' => 'profil.php', 'secondary' => 'Direktori guru', 'secondary_url' => 'guru.php', 'icon' => 'bi-mortarboard-fill'],
    ['label' => 'Akademik', 'title' => 'Informasi pembelajaran', 'text' => 'Lihat informasi akademik, jadwal publik, dan agenda yang telah diterbitkan sekolah.', 'primary' => 'Buka informasi akademik', 'primary_url' => 'akademik.php', 'secondary' => 'Lihat jadwal', 'secondary_url' => 'jadwal.php', 'icon' => 'bi-book-half'],
    ['label' => 'Informasi Sekolah', 'title' => 'Ikuti kabar dan pengumuman terbaru', 'text' => 'Temukan informasi resmi, berita kegiatan, serta pengumuman sekolah dalam satu portal.', 'primary' => 'Baca pengumuman', 'primary_url' => 'pengumuman.php', 'secondary' => 'Berita sekolah', 'secondary_url' => 'berita.php', 'icon' => 'bi-megaphone-fill'],
    ['label' => 'Agenda Pendidikan', 'title' => 'Rencanakan kegiatan bersama sekolah', 'text' => 'Periksa agenda akademik dan catat kegiatan sekolah yang akan datang.', 'primary' => 'Lihat agenda', 'primary_url' => 'agenda.php', 'secondary' => 'Galeri sekolah', 'secondary_url' => 'galeri.php', 'icon' => 'bi-calendar2-week-fill'],
    ['label' => 'Layanan sekolah', 'title' => 'Informasi dan layanan publik', 'text' => 'Jelajahi profil, berita, agenda, layanan penerimaan, dan kontak sekolah dari menu portal.', 'primary' => 'Hubungi sekolah', 'primary_url' => 'kontak.php', 'secondary' => 'Lihat PPDB / SPMB', 'secondary_url' => 'ppdb.php', 'icon' => 'bi-people-fill'],
];
?>
<section class="hero hero--slideshow" data-hero-carousel tabindex="0" aria-keyshortcuts="ArrowLeft ArrowRight" aria-label="Sorotan informasi sekolah. Ketuk sisi kiri atau kanan, atau gunakan tombol panah kiri dan kanan untuk mengganti slide.">
    <div class="hero-slides">
        <?php foreach ($heroSlides as $slideIndex => $slide): ?>
            <article class="hero-slide<?= $slideIndex === 0 ? ' is-active' : '' ?>" data-hero-slide aria-hidden="<?= $slideIndex === 0 ? 'false' : 'true' ?>">
                <div class="wrap hero-content">
                    <span class="hero-slide-icon" aria-hidden="true"><i class="bi <?= e($slide['icon']) ?>"></i></span>
                    <span class="eyebrow"><?= e($slide['label']) ?></span>
                    <h1><?= e($slide['title']) ?></h1>
                    <p><?= e($slide['text']) ?></p>
                    <div class="button-row">
                        <a class="btn-primary" href="<?= e($slide['primary_url']) ?>"><?= e($slide['primary']) ?> <i class="bi bi-arrow-right"></i></a>
                        <a class="btn-light" href="<?= e($slide['secondary_url']) ?>"><?= e($slide['secondary']) ?></a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="hero-pagination" role="group" aria-label="Pilih slide sorotan">
        <?php foreach ($heroSlides as $slideIndex => $slide): ?>
            <button type="button" data-hero-dot="<?= $slideIndex ?>" aria-label="Tampilkan slide <?= $slideIndex + 1 ?>" aria-pressed="<?= $slideIndex === 0 ? 'true' : 'false' ?>" class="<?= $slideIndex === 0 ? 'is-active' : '' ?>"></button>
        <?php endforeach; ?>
    </div>
    <span class="hero-progress" aria-hidden="true"><span data-hero-progress></span></span>
</section>
<section class="public-stats" aria-label="Ringkasan sekolah">
    <div class="wrap public-stats-grid">
        <a class="public-stat" href="guru.php"><span class="public-stat-icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span><strong><?= $publicStats['guru'] === null ? '—' : number_format($publicStats['guru'], 0, ',', '.') ?></strong><span>Guru aktif</span><i class="bi bi-arrow-up-right public-stat-arrow" aria-hidden="true"></i></a>
        <a class="public-stat" href="profil.php"><span class="public-stat-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span><strong><?= $publicStats['siswa'] === null ? '—' : number_format($publicStats['siswa'], 0, ',', '.') ?></strong><span>Siswa aktif</span><i class="bi bi-arrow-up-right public-stat-arrow" aria-hidden="true"></i></a>
        <a class="public-stat" href="agenda.php"><span class="public-stat-icon"><i class="bi bi-door-open" aria-hidden="true"></i></span><strong><?= $publicStats['kelas'] === null ? '—' : number_format($publicStats['kelas'], 0, ',', '.') ?></strong><span>Kelas tahun ajaran aktif</span><i class="bi bi-arrow-up-right public-stat-arrow" aria-hidden="true"></i></a>
        <a class="public-stat" href="pengumuman.php"><span class="public-stat-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span><strong><?= $publicStats['pengumuman'] === null ? '—' : number_format($publicStats['pengumuman'], 0, ',', '.') ?></strong><span>Pengumuman aktif</span><i class="bi bi-arrow-up-right public-stat-arrow" aria-hidden="true"></i></a>
    </div>
</section>
<section class="section home-profile-summary" aria-labelledby="homeProfileTitle">
    <div class="wrap home-profile-summary__inner">
        <div class="home-profile-summary__copy">
            <span class="kicker">Profil singkat</span>
            <h2 id="homeProfileTitle">Mengenal <?= e($portalProfile['nama']) ?></h2>
            <p>Portal ini menyajikan identitas, informasi akademik, berita, agenda, dan layanan sekolah berdasarkan data yang tersedia untuk publik.</p>
            <a class="text-link" href="profil.php">Lihat profil dan identitas sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        <figure class="home-profile-summary__image">
            <img src="../assets/image/public-optimized/facility-building.jpg" alt="Gedung dan lingkungan sekolah SMP Negeri 4 Pirime" loading="lazy" decoding="async">
            <figcaption><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> Lingkungan sekolah</figcaption>
        </figure>
        <dl class="home-profile-summary__facts">
            <div><dt>NPSN</dt><dd><?= e($portalProfile['npsn'] ?: 'Belum tercatat') ?></dd></div>
            <div><dt>Jenjang</dt><dd><?= e($portalProfile['jenjang'] ?: 'Belum tercatat') ?></dd></div>
            <div><dt>Status</dt><dd><?= e($portalProfile['status_sekolah'] ?: 'Belum tercatat') ?></dd></div>
            <div><dt>Lokasi</dt><dd><?= e($schoolAddress ?: 'Belum tercatat') ?></dd></div>
        </dl>
    </div>
</section>
<?php if ($homeLeaders || $notices): ?>
<section class="section section-soft home-welcome-section">
    <div class="wrap two-col">
        <div class="welcome-panel"><span class="eyebrow">Sambutan kepala sekolah</span>
            <?php if ($homeLeaders): $homePrincipal = $homeLeaders[0]; $homePrincipalPhoto = portal_image($homePrincipal['foto_url'] ?? '', 'struktur', (int)$homePrincipal['id']); ?><?php if ($homePrincipalPhoto): ?><img class="home-principal-photo" src="<?= e($homePrincipalPhoto) ?>" alt="Foto <?= e($homePrincipal['nama']) ?>" loading="lazy" decoding="async"><?php endif; ?><h2><?= e($homePrincipal['nama']) ?></h2><p class="article-meta"><?= e($homePrincipal['jabatan']) ?></p>
                <?php if ($greetingApproved && trim((string)$portalProfile['sambutan']) !== ''): ?><p><?= nl2br(e($portalProfile['sambutan'])) ?></p><?php else: ?><p>Naskah sambutan resmi belum diterbitkan atau belum memiliki status persetujuan.</p><?php endif; ?>
            <?php else: ?><h2><?= e($portalProfile['nama']) ?></h2><p>Informasi pimpinan dan sambutan akan ditampilkan setelah disahkan dan diterbitkan oleh sekolah.</p><?php endif; ?><a class="btn-light" href="profil.php#kepala-sekolah">Profil kepala sekolah <i class="bi bi-arrow-right"></i></a>
        </div>
        <div>
            <div class="section-head">
                <div><span class="kicker">Informasi terbaru</span>
                    <h2>Pengumuman</h2>
                </div><a class="text-link" href="pengumuman.php">Lihat semua →</a>
            </div>
            <div class="notice-list"><?php if (!$notices): ?><?php public_empty_panel('megaphone', 'Belum ada pengumuman terbaru', 'Pengumuman sekolah akan ditampilkan di sini setelah diterbitkan.'); ?><?php else: foreach ($notices as $n): ?><a class="notice-item" href="detail_pengumuman.php?id=<?= (int)$n['id'] ?>"><span class="date-label"><?= e(portal_date($n['tanggal'])) ?></span>
                    <h3><?= e($n['judul']) ?></h3>
                    <p><?= e(portal_excerpt($n['isi'], 110)) ?></p>
                </a><?php endforeach;
                                                                                                                                                                                                        endif; ?></div>
        </div>
    </div>
</section>
<?php endif; ?>
<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Layanan informasi</span>
                <h2>Semua informasi sekolah, lebih mudah ditemukan</h2>
                <p>Portal resmi untuk mengenal sekolah, mengikuti informasi terbaru, dan mengetahui agenda pendidikan.</p>
            </div>
        </div>
        <div class="tiles">
            <a class="tile" href="profil.php">
                <span class="tile-icon">
                    <i class="bi bi-buildings"></i>
                </span>
                <h3>Profil Sekolah</h3>
                <p>Kenali identitas, sejarah, visi, dan misi sekolah.</p>
            </a><a class="tile" href="pengumuman.php">
                <span class="tile-icon">
                    <i class="bi bi-megaphone"></i>
                </span>
                <h3>Pengumuman</h3>
                <p>Informasi resmi terbaru untuk warga sekolah.</p>
            </a><a class="tile" href="agenda.php">
                <span class="tile-icon">
                    <i class="bi bi-calendar-event"></i>
                </span>
                <h3>Agenda Akademik</h3>
                <p>Jadwal kegiatan dan kalender sekolah.</p>
            </a><a class="tile" href="guru.php">
                <span class="tile-icon">
                    <i class="bi bi-person-badge"></i>
                </span>
                <h3>Direktori Guru</h3>
                <p>Profil tenaga pendidik yang telah disetujui.</p>
            </a><a class="tile" href="akademik.php">
                <span class="tile-icon"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i></span>
                <h3>Informasi Akademik</h3>
                <p>Ringkasan peserta didik, agenda, dan program pembelajaran.</p>
            </a><a class="tile" href="galeri.php">
                <span class="tile-icon"><i class="bi bi-images" aria-hidden="true"></i></span>
                <h3>Galeri Sekolah</h3>
                <p>Dokumentasi kegiatan yang telah disetujui untuk publikasi.</p>
            </a>
        </div>
    </div>
</section>
<section class="section section-soft home-programs-section">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Program dan layanan</span>
                <h2>Ruang untuk belajar dan berkembang</h2>
                <p>Temukan kegiatan sekolah, pencapaian yang telah disetujui, serta layanan penerimaan peserta didik.</p>
            </div>
        </div>
        <div class="cards public-feature-grid">
            <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
                <h2>Ekstrakurikuler</h2><?php if ($publicExtras): ?><ul class="home-public-list"><?php foreach ($publicExtras as $extra): ?><li><strong><?= e($extra['nama']) ?></strong><span><?= e($extra['kategori'] ?: ($extra['jadwal'] ?: 'Kegiatan sekolah')) ?></span></li><?php endforeach; ?></ul><?php else: ?><p>Informasi kegiatan akan muncul setelah dipublikasikan sekolah.</p><?php endif; ?><a class="text-link" href="ekstrakurikuler.php">Lihat kegiatan <i class="bi bi-arrow-right"></i></a>
            </article>
            <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-award-fill" aria-hidden="true"></i></span>
                <h2>Prestasi sekolah</h2><?php if ($publicAwards): ?><ul class="home-public-list"><?php foreach ($publicAwards as $award): ?><li><strong><?= e($award['judul']) ?></strong><span><?= e($award['penerima']) ?><?= $award['tanggal'] ? ' · ' . e(portal_date($award['tanggal'], 'Y')) : '' ?></span></li><?php endforeach; ?></ul><?php else: ?><p>Prestasi yang sudah diverifikasi dan disetujui akan ditampilkan di sini.</p><?php endif; ?><a class="text-link" href="prestasi.php">Lihat prestasi <i class="bi bi-arrow-right"></i></a>
            </article>
            <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                <h2>PPDB / SPMB</h2>
                <p><?= portal_row($pdo, "SELECT id FROM ppdb_periode WHERE status='Terbit' AND mulai<=CURDATE() AND selesai>=CURDATE() LIMIT 1") ? 'Periode pendaftaran sedang dibuka. Periksa informasi dan persyaratan resmi.' : 'Informasi pendaftaran tersedia saat sekolah menerbitkan periode resmi.' ?></p><a class="text-link" href="ppdb.php">Informasi pendaftaran <i class="bi bi-arrow-right"></i></a><a class="text-link" href="cek_pendaftaran.php">Cek status permohonan <i class="bi bi-search"></i></a>
            </article>
            <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i></span>
                <h2>Pusat unduhan</h2><?php if ($publicDownloads): ?><ul class="home-public-list"><?php foreach ($publicDownloads as $doc): ?><li><a href="../media/public_document.php?id=<?= (int)$doc['id'] ?>"><?= e($doc['judul']) ?></a><span><?= e($doc['kategori']) ?></span></li><?php endforeach; ?></ul><?php else: ?><p>Dokumen resmi yang siap dibagikan akan tersedia di pusat unduhan.</p><?php endif; ?><a class="text-link" href="unduhan.php">Semua dokumen <i class="bi bi-arrow-right"></i></a>
            </article>
        </div>
    </div>
</section>


<section class="section home-news-section">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Cerita sekolah</span>
                <h2>Berita dan kegiatan</h2>
            </div><a class="text-link" href="berita.php">Jelajahi berita →</a>
        </div><?php if (!$portalNewsReady): ?><?php public_service_notice('Informasi berita sedang tidak tersedia. Silakan kunjungi kembali nanti atau hubungi sekolah untuk mendapatkan kabar terbaru.'); ?><?php endif; ?><div class="cards"><?php if (!$stories && $portalNewsReady): ?><?php public_empty_panel('journal-text', 'Belum ada berita untuk ditampilkan', 'Berita terbaru akan muncul setelah dipublikasikan oleh pengelola sekolah.'); ?><?php else: foreach ($stories as $s): $img = portal_image($s['gambar_url'], 'berita', (int)$s['id']); ?><article class="card story-card"><?php if ($img): ?><img class="story-image" src="<?= e($img) ?>" alt="<?= e($s['judul']) ?>" loading="lazy"><?php else: ?><div class="story-image" aria-hidden="true"></div><?php endif; ?><div class="story-body"><span class="date-label"><?= e($s['kategori']) ?> · <?= e(portal_date($s['tanggal'])) ?></span>
                    <h3><a href="detail_berita.php?id=<?= (int)$s['id'] ?>"><?= e($s['judul']) ?></a></h3>
                    <p><?= e(portal_excerpt($s['ringkasan'] ?: $s['isi'], 130)) ?></p>
                </div>
            </article><?php endforeach;
                                                                                                                                                                                                                                                                                                                                                                                                                                                        endif; ?></div>
    </div>
</section>

<?php if ($events): ?>
<section class="section section-soft">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Catat tanggalnya</span>
                <h2>Agenda terdekat</h2>
            </div><a class="text-link" href="agenda.php">Kalender lengkap →</a>
        </div>
        <div class="card agenda-card"><?php if (!$events): ?><?php public_empty_panel('calendar2-event', 'Belum ada agenda terdekat', 'Agenda yang telah disetujui sekolah akan muncul di bagian ini.'); ?><?php else: foreach ($events as $ev): ?><div class="agenda-row">
                <div class="agenda-date"><?= e(portal_date($ev['tanggal_mulai'], 'd M')) ?><?= $ev['tanggal_selesai'] ? ' – ' . e(portal_date($ev['tanggal_selesai'], 'd M')) : '' ?></div>
                <div>
                    <h3><?= e($ev['judul']) ?></h3>
                    <p><?= e($ev['kategori']) ?><?= $ev['deskripsi'] ? ' · ' . e(portal_excerpt($ev['deskripsi'], 120)) : '' ?></p>
                </div>
            </div><?php endforeach;
                                                                                                                                                                                                        endif; ?></div>
    </div>
</section>
<?php endif; ?>
<?php if ($galleryItems): ?>
<section class="section home-gallery-section">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Galeri pembelajaran</span>
                <h2>Kegiatan belajar dan berkembang</h2>
                <p>Bagian ini hanya menampilkan dokumentasi aktual yang telah diterbitkan dan disetujui sekolah.</p>
            </div><a class="text-link" href="galeri.php">Buka galeri <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="cards home-gallery-cards">
            <?php foreach ($galleryItems as $photo): $image = portal_image($photo['gambar_url'], 'galeri', (int)$photo['id']);
                if (!$image) continue; ?><a class="card home-gallery-card" href="galeri.php"><img src="<?= e($image) ?>" alt="Dokumentasi sekolah: <?= e($photo['judul']) ?>" loading="lazy" decoding="async"><span><strong><?= e($photo['judul']) ?></strong><small><?= e(portal_date($photo['tanggal_kegiatan'])) ?> · dokumentasi sekolah</small></span></a><?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php if ($homeTeachers): ?>
<section class="section section-soft">
    <div class="wrap">
        <div class="section-head"><div><span class="kicker">Tenaga pendidik</span><h2>Guru yang disetujui untuk ditampilkan</h2><p>Direktori ini hanya memuat profil guru aktif yang diizinkan untuk publikasi.</p></div><a class="text-link" href="guru.php">Buka direktori <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        <?php if (!$homeTeachersReady): ?><?php public_service_notice('Direktori guru belum dapat dimuat.'); ?><?php elseif (!$homeTeachers): ?><?php public_empty_panel('people', 'Direktori publik belum tersedia', 'Profil guru tampil setelah sekolah menyetujui data untuk publikasi.'); ?><?php else: ?><div class="cards public-feature-grid"><?php foreach ($homeTeachers as $teacher): $photoPath = (string)($teacher['foto'] ?? ''); $photo = preg_match('~^uploads/guru/[a-f0-9-]+\.(?:jpg|png|webp)$~i', $photoPath) ? '../media/guru.php?id=' . (int)$teacher['id'] : ''; ?><article class="card public-feature-card home-teacher-card"><?php if ($photo): ?><img class="public-feature-image" src="<?= e($photo) ?>" alt="Foto <?= e($teacher['nama']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="tile-icon"><i class="bi bi-person-fill" aria-hidden="true"></i></span><?php endif; ?><h3><?= e($teacher['nama']) ?></h3><p>Tenaga pendidik</p></article><?php endforeach; ?></div><?php endif; ?>
    </div>
</section>
<?php endif; ?>
<?php if ($homeFacilities): ?>
<section class="section">
    <div class="wrap">
        <div class="section-head"><div><span class="kicker">Sarana dan prasarana</span><h2>Fasilitas sekolah</h2><p>Informasi fasilitas ditampilkan setelah dicatat dan diterbitkan oleh pengelola sekolah.</p></div><a class="text-link" href="fasilitas.php">Lihat fasilitas <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        <?php if (!$homeFacilitiesReady): ?><?php public_service_notice('Informasi fasilitas belum dapat dimuat.'); ?><?php elseif (!$homeFacilities): ?><?php public_empty_panel('buildings', 'Belum ada fasilitas terverifikasi untuk ditampilkan', 'Daftar fasilitas sekolah akan muncul setelah inventaris dan foto diperiksa.'); ?><?php else: ?><div class="cards public-feature-grid"><?php foreach ($homeFacilities as $facility): $facilityPhoto = portal_image($facility['gambar_url'], 'fasilitas', (int)$facility['id']); ?><article class="card public-feature-card"><?php if ($facilityPhoto): ?><img class="public-feature-image" src="<?= e($facilityPhoto) ?>" alt="Fasilitas sekolah: <?= e($facility['nama']) ?>" loading="lazy" decoding="async"><?php else: ?><span class="tile-icon"><i class="bi bi-buildings" aria-hidden="true"></i></span><?php endif; ?><h3><?= e($facility['nama']) ?></h3><?php if ($facility['deskripsi']): ?><p><?= e($facility['deskripsi']) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
    </div>
</section>
<?php endif; ?>
<section class="section section-soft home-contact-section">
    <div class="wrap home-contact-panel">
        <div><span class="kicker">Kunjungi dan hubungi kami</span>
            <h2>Informasi lokasi <?= e($portalProfile['nama']) ?></h2>
            <p><?= e($schoolAddress ?: 'Alamat sekolah belum diatur.') ?></p>
            <div class="button-row"><a class="btn-primary" href="kontak.php#contactMapTitle">Lihat lokasi <i class="bi bi-geo-alt-fill" aria-hidden="true"></i></a><?php $homePhone = portal_phone_uri($portalProfile['telepon'] ?? null);
                                                                                                                                                                    if ($homePhone !== ''): ?><a class="btn-light" href="tel:<?= e($homePhone) ?>">Telepon sekolah</a><?php endif; ?></div>
        </div>
        <div class="home-contact-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i><span></span></div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
