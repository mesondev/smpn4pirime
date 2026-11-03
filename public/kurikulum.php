<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pageTitle = 'Kurikulum';
$activePage = 'kurikulum';
require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Informasi akademik</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Informasi kurikulum akan diterbitkan setelah dikonfirmasi oleh sekolah.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <article class="card public-feature-card">
            <span class="tile-icon"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i></span>
            <h2>Konfirmasi informasi kurikulum</h2>
            <p>Portal belum memuat rincian kurikulum dan pelaksanaannya. Hubungi sekolah untuk memperoleh keterangan resmi tahun ajaran berjalan.</p>
            <a class="btn-primary" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right"></i></a>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>