<?php
$portalProfile = $portalProfile ?? portal_profile($pdo);
$pageTitle = $pageTitle ?? 'Portal Sekolah';
$activePage = $activePage ?? '';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Informasi resmi <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>: profil, pengumuman, berita, agenda, dan layanan sekolah.">
    <meta name="theme-color" content="#123b66">
    <title><?= e($pageTitle) ?> | <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/public.css?v=<?= (int)filemtime(__DIR__ . '/../assets/public.css') ?>" rel="stylesheet">
    <link href="../assets/schema.css" rel="stylesheet">
</head>

<body>
    <div class="topline">
        <?php $portalPhone = preg_replace('/\D+/', '', (string)($portalProfile['telepon'] ?? '')); if (str_starts_with($portalPhone, '0')) $portalPhone = '62' . substr($portalPhone, 1); $portalNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Jayapura')); $portalWeekdays = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; $portalMonths = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember']; ?>
        <div class="wrap topline-inner">
            <div class="topline-contact-group">
                <div class="top-social-links" aria-label="Media sosial sekolah">
                    <?php foreach (['twitter-x','facebook','linkedin','instagram','youtube'] as $socialIcon): ?><button class="top-social-link social-icon-button" type="button" data-social-animate aria-label="<?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?>, tautan sekolah belum diatur" title="<?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?>"><i class="bi bi-<?= e($socialIcon) ?>" aria-hidden="true"></i></button><?php endforeach; ?>
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
                <a class="<?= $activePage === 'profil' ? 'active' : '' ?>" href="profil.php">Profil</a>
                <a class="<?= $activePage === 'guru' ? 'active' : '' ?>" href="guru.php">Guru</a>
                <a class="<?= $activePage === 'pengumuman' ? 'active' : '' ?>" href="pengumuman.php">Pengumuman</a>
                <a class="<?= $activePage === 'berita' ? 'active' : '' ?>" href="berita.php">Berita</a>
                <a class="<?= $activePage === 'agenda' ? 'active' : '' ?>" href="agenda.php">Agenda</a>
                <a class="<?= $activePage === 'galeri' ? 'active' : '' ?>" href="galeri.php">Galeri</a>
                <a class="<?= $activePage === 'ppdb' ? 'active' : '' ?>" href="ppdb.php">PPDB</a>
                <a class="<?= $activePage === 'kontak' ? 'active' : '' ?>" href="kontak.php">Kontak</a>
            </nav>
            <a class="staff-link" href="../login.php">
                <i class="bi bi-box-arrow-in-right"></i> SIMS</a>
        </div>
    </header>
    <main>
