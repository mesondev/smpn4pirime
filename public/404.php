<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Halaman Tidak Ditemukan';
$activePage = '';
http_response_code(404);
require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Kesalahan 404</span>
        <h1>Halaman tidak ditemukan</h1>
        <p>Tautan mungkin berubah atau halaman sudah tidak tersedia.</p>
        <?= portal_breadcrumb_markup('', $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <article class="card public-feature-card">
            <h2>Kembali ke informasi sekolah</h2>
            <p>Gunakan navigasi portal untuk menemukan halaman yang Anda cari.</p>
            <a class="btn-primary" href="index.php">Kembali ke beranda <i class="bi bi-house"></i></a>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>