<?php
$portalProfile = $portalProfile ?? portal_profile($pdo);
$pageTitle = $pageTitle ?? 'Portal Sekolah';
$activePage = $activePage ?? '';
$pageDescription = $pageDescription ?? ('Informasi resmi ' . ($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') . ' mengenai ' . mb_strtolower($pageTitle, 'UTF-8') . ', layanan sekolah, dan kegiatan pendidikan.');
$pageDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $pageDescription)) ?? '');
if (mb_strlen($pageDescription, 'UTF-8') > 160) $pageDescription = mb_substr($pageDescription, 0, 157, 'UTF-8') . '...';
$portalSocialLinks = portal_social_links($pdo);
$requestHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$isLocalPortalHost = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($requestHost, '.test');
$shouldNoIndex = $isLocalPortalHost || in_array(basename((string) ($_SERVER['PHP_SELF'] ?? '')), ['404.php', 'cek_pendaftaran.php', 'pencarian.php'], true);
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="<?= $shouldNoIndex ? 'noindex, nofollow' : 'index, follow' ?>">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>">
    <meta property="og:title" content="<?= e($pageTitle . ' | ' . ($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime')) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="theme-color" content="#123b66">
    <title><?= e($pageTitle) ?> | <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?></title>
    <link rel="icon" type="image/png" href="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png?v=<?= (int)filemtime(__DIR__ . '/../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/public.css?v=<?= (int)filemtime(__DIR__ . '/../assets/public.css') ?>" rel="stylesheet">
    <?php if ($activePage === 'profil'): ?>
        <link href="../assets/school-profile.css?v=<?= (int)filemtime(__DIR__ . '/../assets/school-profile.css') ?>" rel="stylesheet"><?php endif; ?>
    <link href="../assets/schema.css" rel="stylesheet">
</head>

<body>
    <a class="skip-to-content" href="#pageContent">Lewati ke konten utama</a>
    <div class="topline">
        <?php $portalPhone = portal_whatsapp_number($portalProfile['telepon'] ?? null);
        $portalNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Jayapura'));
        $portalWeekdays = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $portalMonths = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']; ?>
        <div class="wrap topline-inner">
            <div class="topline-contact-group">
                <div class="top-social-links" aria-label="Media sosial sekolah">
                    <?php foreach (['facebook', 'instagram', 'youtube', 'twitter-x', 'tiktok'] as $socialIcon): ?><?php if (isset($portalSocialLinks[$socialIcon])): ?><a class="top-social-link" href="<?= e($portalSocialLinks[$socialIcon]) ?>" target="_blank" rel="noopener noreferrer" aria-label="SMP Negeri 4 Pirime di <?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?>"><i class="bi bi-<?= e($socialIcon) ?>" aria-hidden="true"></i></a><?php else: ?><span class="top-social-link is-unconfigured" title="Tautan <?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?> belum diatur" aria-label="Tautan <?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?> sekolah belum diatur"><i class="bi bi-<?= e($socialIcon) ?>" aria-hidden="true"></i></span><?php endif; ?><?php endforeach; ?>
                </div>
                <?php if ($portalPhone !== ''): ?><a class="top-contact top-whatsapp" href="https://wa.me/<?= e($portalPhone) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i><span><?= e($portalProfile['telepon']) ?></span></a><?php else: ?><span class="top-contact top-whatsapp"><i class="bi bi-whatsapp" aria-hidden="true"></i><span>Nomor WhatsApp belum diatur</span></span><?php endif; ?>
            </div>
            <div class="topline-meta">
                <span class="top-date-pill"><i class="bi bi-calendar3" aria-hidden="true"></i><time id="portalClock" data-timezone="Asia/Jayapura"><?= e($portalWeekdays[(int)$portalNow->format('w')] . ', ' . $portalNow->format('d') . ' ' . $portalMonths[(int)$portalNow->format('n')] . ' ' . $portalNow->format('Y') . ' | ' . $portalNow->format('H.i.s')) ?></time></span>
                <a class="top-weather-pill" id="portalWeather" href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" aria-label="Cuaca Pirime, memuat data cuaca" title="Data cuaca: Open-Meteo">
                    <i class="bi bi-cloud-sun-fill" id="portalWeatherIcon" aria-hidden="true"></i>
                    <strong id="portalWeatherTemperature">--°C</strong>
                    <span id="portalWeatherSummary">Memuat cuaca</span>
                    <small id="portalWeatherPlace">Pirime</small>
                </a>
            </div>
        </div>
    </div>
    <header class="site-header">
        <div class="wrap nav-wrap">
            <a class="brand" href="index.php">
                <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang SMP Negeri 4 Pirime">
                <span>
                    <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>
                    <small>Portal Informasi Resmi</small>
                </span>
            </a>
            <button class="nav-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false" aria-controls="publicNavigation">
                <i class="bi bi-list"></i></button>
            <nav class="main-nav" id="publicNavigation" aria-label="Navigasi utama">
                <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="index.php">Beranda</a>
                <details class="nav-dropdown">
                    <summary class="<?= in_array($activePage, ['profil', 'guru', 'sejarah', 'visi-misi', 'struktur', 'fasilitas'], true) ? 'active' : '' ?>">Profil <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                    <div class="nav-dropdown-menu">
                        <a href="profil.php#schoolAboutTitle"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Tentang sekolah</span></a>
                        <a href="profil.php#schoolIdentityTitle"><i class="bi bi-card-list" aria-hidden="true"></i><span>Identitas sekolah</span></a>
                        <a href="sejarah.php"><i class="bi bi-journal-text" aria-hidden="true"></i><span>Riwayat sekolah</span></a>
                        <a href="visi-misi.php"><i class="bi bi-flag" aria-hidden="true"></i><span>Visi dan misi</span></a>
                        <a href="profil.php#kepala-sekolah"><i class="bi bi-person-badge-fill" aria-hidden="true"></i><span>Kepala sekolah</span></a>
                        <a href="guru.php"><i class="bi bi-person-workspace" aria-hidden="true"></i><span>Guru & tenaga kependidikan</span></a>
                        <a href="struktur.php"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i><span>Struktur organisasi</span></a>
                        <a href="fasilitas.php"><i class="bi bi-buildings-fill" aria-hidden="true"></i><span>Fasilitas sekolah</span></a>
                        <a href="kontak.php#contactMapTitle"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><span>Lokasi sekolah</span></a>
                    </div>
                </details>
                <details class="nav-dropdown">
                    <summary class="<?= in_array($activePage, ['akademik', 'kurikulum', 'jadwal', 'ekstrakurikuler'], true) ? 'active' : '' ?>">Akademik <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                    <div class="nav-dropdown-menu">
                        <a href="akademik.php"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i><span>Ringkasan akademik</span></a>
                        <a href="kurikulum.php"><i class="bi bi-book-half" aria-hidden="true"></i><span>Kurikulum</span></a>
                        <a href="jadwal.php"><i class="bi bi-clock-fill" aria-hidden="true"></i><span>Jadwal pelajaran</span></a>
                        <a href="akademik.php#peserta-didik"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Peserta didik</span></a>
                        <a href="akademik.php#rombongan-belajar"><i class="bi bi-door-open-fill" aria-hidden="true"></i><span>Rombongan belajar</span></a>
                        <a href="agenda.php"><i class="bi bi-calendar2-week-fill" aria-hidden="true"></i><span>Kalender pendidikan</span></a>
                        <a href="ekstrakurikuler.php"><i class="bi bi-stars" aria-hidden="true"></i><span>Ekstrakurikuler</span></a>
                    </div>
                </details>
                <details class="nav-dropdown">
                    <summary class="<?= in_array($activePage, ['berita', 'pengumuman', 'agenda', 'prestasi'], true) ? 'active' : '' ?>">Informasi <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                    <div class="nav-dropdown-menu">
                        <a href="berita.php"><i class="bi bi-newspaper" aria-hidden="true"></i><span>Berita sekolah</span></a>
                        <a href="pengumuman.php"><i class="bi bi-megaphone-fill" aria-hidden="true"></i><span>Pengumuman</span></a>
                        <a href="agenda.php"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i><span>Agenda kegiatan</span></a>
                        <a href="prestasi.php"><i class="bi bi-award-fill" aria-hidden="true"></i><span>Prestasi sekolah</span></a>
                    </div>
                </details>
                <a class="<?= $activePage === 'galeri' ? 'active' : '' ?>" href="galeri.php">Galeri</a>
                <a class="<?= $activePage === 'unduhan' ? 'active' : '' ?>" href="unduhan.php">Unduhan</a>
                <details class="nav-dropdown">
                    <summary class="<?= $activePage === 'ppdb' ? 'active' : '' ?>">PPDB / SPMB <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                    <div class="nav-dropdown-menu">
                        <a href="ppdb.php#informasi-ppdb"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>Informasi dan jadwal</span></a>
                        <a href="ppdb.php#persyaratan"><i class="bi bi-file-earmark-check-fill" aria-hidden="true"></i><span>Persyaratan</span></a>
                        <a href="ppdb.php#formulir"><i class="bi bi-pencil-square" aria-hidden="true"></i><span>Isi formulir</span></a>
                        <a href="cek_pendaftaran.php"><i class="bi bi-search" aria-hidden="true"></i><span>Cek status pendaftaran</span></a>
                        <a href="hasil-seleksi.php"><i class="bi bi-clipboard2-check-fill" aria-hidden="true"></i><span>Hasil seleksi</span></a>
                    </div>
                </details>
                <a class="<?= $activePage === 'kontak' ? 'active' : '' ?>" href="kontak.php">Kontak</a>
                <a class="nav-search-link <?= $activePage === 'pencarian' ? 'active' : '' ?>" href="pencarian.php" aria-label="Pencarian"><i class="bi bi-search" aria-hidden="true"></i><span class="nav-search-label">Cari</span></a>
            </nav>
            <a class="staff-link" href="../login.php">
                <i class="bi bi-box-arrow-in-right"></i> SIMS</a>
        </div>
    </header>
    <main id="pageContent" tabindex="-1">
