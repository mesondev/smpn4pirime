<?php
$portalProfile = $portalProfile ?? portal_profile($pdo);
$pageTitle = $pageTitle ?? 'Portal Sekolah';
$activePage = $activePage ?? '';
?>
<!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Informasi resmi <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>: profil, pengumuman, berita, agenda, dan layanan sekolah.">
<meta name="theme-color" content="#123b66"><title><?= e($pageTitle) ?> | <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><link href="../assets/public.css" rel="stylesheet"><link href="../assets/schema.css" rel="stylesheet">
</head><body>
<div class="topline"><div class="wrap topline-inner"><span><i class="bi bi-geo-alt"></i> <?= e($portalProfile['alamat'] ?? '') ?></span><span class="top-contact"><?= e($portalProfile['email'] ?? '') ?> <?= !empty($portalProfile['telepon']) ? ' · ' . e($portalProfile['telepon']) : '' ?></span></div></div>
<header class="site-header"><div class="wrap nav-wrap"><a class="brand" href="index.php"><img src="../assets/logo-sims-pirime.png" alt=""><span><?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?><small>Portal Informasi Resmi</small></span></a>
<button class="nav-toggle" type="button" aria-label="Buka navigasi" aria-expanded="false"><i class="bi bi-list"></i></button>
<nav class="main-nav" aria-label="Navigasi utama"><a class="<?= $activePage==='home'?'active':'' ?>" href="index.php">Beranda</a><a class="<?= $activePage==='profil'?'active':'' ?>" href="profil.php">Profil</a><a class="<?= $activePage==='guru'?'active':'' ?>" href="guru.php">Guru</a><a class="<?= $activePage==='pengumuman'?'active':'' ?>" href="pengumuman.php">Pengumuman</a><a class="<?= $activePage==='berita'?'active':'' ?>" href="berita.php">Berita</a><a class="<?= $activePage==='agenda'?'active':'' ?>" href="agenda.php">Agenda</a><a class="<?= $activePage==='galeri'?'active':'' ?>" href="galeri.php">Galeri</a><a href="ppdb.php">PPDB</a><a href="kontak.php">Kontak</a></nav>
<a class="staff-link" href="../login.php"><i class="bi bi-box-arrow-in-right"></i> SIMS</a></div></header>
<main>
