<?php
require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$publicStats = portal_public_stats($pdo);
$activePage = 'home';
$pageTitle = 'Beranda';
$notices = portal_rows($pdo, "SELECT id,judul,isi,COALESCE(mulai_tayang,DATE(created_at)) AS tanggal FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE()) ORDER BY COALESCE(mulai_tayang,DATE(created_at)) DESC,id DESC LIMIT 3");
$portalNewsReady = portal_schema_ready($pdo, 'berita_sekolah', ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at']);
$stories = $portalNewsReady ? portal_rows($pdo, "SELECT id,judul,ringkasan,isi,kategori,gambar_url,COALESCE(published_at,created_at) AS tanggal FROM berita_sekolah WHERE status='Terbit' ORDER BY COALESCE(published_at,created_at) DESC,id DESC LIMIT 3") : [];
$events = portal_rows($pdo, "SELECT judul,kategori,tanggal_mulai,tanggal_selesai,deskripsi FROM kalender_akademik WHERE tampil_publik=1 AND status<>'Dibatalkan' AND COALESCE(tanggal_selesai,tanggal_mulai)>=CURDATE() ORDER BY tanggal_mulai LIMIT 4");
require __DIR__ . '/_header.php'; ?>

<?php
$heroSlides = [
    ['label' => 'Portal Informasi Resmi', 'title' => 'Selamat datang di ' . $portalProfile['nama'], 'text' => !empty($portalProfile['sambutan']) ? portal_excerpt($portalProfile['sambutan'], 170) : 'Membangun generasi berprestasi, berkarakter, dan siap menghadapi masa depan.', 'primary' => 'Kenali sekolah', 'primary_url' => 'profil.php', 'secondary' => 'Direktori guru', 'secondary_url' => 'guru.php', 'icon' => 'bi-mortarboard-fill'],
    ['label' => 'Belajar dan Bertumbuh', 'title' => 'Ruang belajar untuk masa depan', 'text' => 'Kami mendukung setiap siswa untuk mengembangkan ilmu, karakter, kreativitas, dan cita-cita.', 'primary' => 'Lihat profil sekolah', 'primary_url' => 'profil.php', 'secondary' => 'Informasi sekolah', 'secondary_url' => 'kontak.php', 'icon' => 'bi-book-half'],
    ['label' => 'Informasi Sekolah', 'title' => 'Ikuti kabar dan pengumuman terbaru', 'text' => 'Temukan informasi resmi, berita kegiatan, serta pengumuman sekolah dalam satu portal.', 'primary' => 'Baca pengumuman', 'primary_url' => 'pengumuman.php', 'secondary' => 'Berita sekolah', 'secondary_url' => 'berita.php', 'icon' => 'bi-megaphone-fill'],
    ['label' => 'Agenda Pendidikan', 'title' => 'Rencanakan kegiatan bersama sekolah', 'text' => 'Periksa agenda akademik dan catat kegiatan sekolah yang akan datang.', 'primary' => 'Lihat agenda', 'primary_url' => 'agenda.php', 'secondary' => 'Galeri sekolah', 'secondary_url' => 'galeri.php', 'icon' => 'bi-calendar2-week-fill'],
    ['label' => 'Bersama SMP Negeri 4 Pirime', 'title' => 'Bersama membangun generasi unggul', 'text' => 'Jelajahi portal resmi SMP Negeri 4 Pirime untuk mengenal sekolah dan layanan informasinya.', 'primary' => 'Hubungi sekolah', 'primary_url' => 'kontak.php', 'secondary' => 'Kenali sekolah', 'secondary_url' => 'profil.php', 'icon' => 'bi-people-fill'],
];
?>
<section class="hero hero--slideshow" data-hero-carousel aria-label="Sorotan informasi sekolah">
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
    <button class="hero-arrow hero-arrow--prev" type="button" data-hero-prev aria-label="Slide sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
    <button class="hero-arrow hero-arrow--next" type="button" data-hero-next aria-label="Slide berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
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
            </a>
        </div>
    </div>
</section>
<section class="section section-soft">
    <div class="wrap two-col">
        <div class="welcome-panel"><span class="eyebrow">Sambutan kepala sekolah</span>
            <h2><?= e($portalProfile['nama']) ?></h2>
            <p><?= nl2br(e($portalProfile['sambutan'] ?: 'Selamat datang di portal informasi resmi sekolah. Kami berkomitmen menyediakan informasi yang akurat, terbuka, dan bermanfaat bagi seluruh masyarakat sekolah.')) ?></p><a class="btn-light" href="profil.php">Tentang sekolah <i class="bi bi-arrow-right"></i></a>
        </div>
        <div>
            <div class="section-head">
                <div><span class="kicker">Informasi terbaru</span>
                    <h2>Pengumuman</h2>
                </div><a class="text-link" href="pengumuman.php">Lihat semua →</a>
            </div>
            <div class="notice-list"><?php if (!$notices): ?><div class="empty">Belum ada pengumuman publik.</div><?php else: foreach ($notices as $n): ?><a class="notice-item" href="detail_pengumuman.php?id=<?= (int)$n['id'] ?>"><span class="date-label"><?= e(portal_date($n['tanggal'])) ?></span>
                            <h3><?= e($n['judul']) ?></h3>
                            <p><?= e(portal_excerpt($n['isi'], 110)) ?></p>
                        </a><?php endforeach;
                                                                                                                endif; ?></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Cerita sekolah</span>
                <h2>Berita dan kegiatan</h2>
            </div><a class="text-link" href="berita.php">Jelajahi berita →</a>
        </div><?php if (!$portalNewsReady): ?><div class="schema-alert">Fitur berita belum aktif. Administrator perlu mengimpor migrasi portal publik ke database.</div><?php endif; ?><div class="cards"><?php if (!$stories && $portalNewsReady): ?><div class="empty" style="grid-column:1/-1">Berita sekolah akan tampil di sini setelah diterbitkan.</div><?php else: foreach ($stories as $s): $img = portal_image($s['gambar_url'], 'berita', (int)$s['id']); ?><article class="card story-card"><?php if ($img): ?><img class="story-image" src="<?= e($img) ?>" alt="<?= e($s['judul']) ?>" loading="lazy"><?php else: ?><div class="story-image" aria-hidden="true"></div><?php endif; ?><div class="story-body"><span class="date-label"><?= e($s['kategori']) ?> · <?= e(portal_date($s['tanggal'])) ?></span>
                            <h3><a href="detail_berita.php?id=<?= (int)$s['id'] ?>"><?= e($s['judul']) ?></a></h3>
                            <p><?= e(portal_excerpt($s['ringkasan'] ?: $s['isi'], 130)) ?></p>
                        </div>
                    </article><?php endforeach;
                                                                                                                                                                                                                                                                                                                                                            endif; ?></div>
    </div>
</section>

<section class="section section-soft">
    <div class="wrap">
        <div class="section-head">
            <div><span class="kicker">Catat tanggalnya</span>
                <h2>Agenda terdekat</h2>
            </div><a class="text-link" href="agenda.php">Kalender lengkap →</a>
        </div>
        <div class="card"><?php if (!$events): ?><div class="empty">Belum ada agenda publik mendatang.</div><?php else: foreach ($events as $ev): ?><div class="agenda-row">
                        <div class="agenda-date"><?= e(portal_date($ev['tanggal_mulai'], 'd M')) ?><?= $ev['tanggal_selesai'] ? ' – ' . e(portal_date($ev['tanggal_selesai'], 'd M')) : '' ?></div>
                        <div>
                            <h3><?= e($ev['judul']) ?></h3>
                            <p><?= e($ev['kategori']) ?><?= $ev['deskripsi'] ? ' · ' . e(portal_excerpt($ev['deskripsi'], 120)) : '' ?></p>
                        </div>
                    </div><?php endforeach;
                                                                                                        endif; ?></div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
