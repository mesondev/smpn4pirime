<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pageTitle = 'Hasil Seleksi';
$activePage = 'ppdb';
require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Penerimaan peserta didik</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Hasil permohonan hanya dapat diperiksa oleh pendaftar menggunakan kunci status pribadi.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <article class="card public-feature-card">
            <span class="tile-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
            <h2>Periksa hasil secara pribadi</h2>
            <p>Nama calon siswa dan hasil seleksi tidak ditampilkan dalam daftar terbuka. Gunakan nomor pendaftaran dan kunci yang diberikan saat mengirim permohonan.</p>
            <a class="btn-primary" href="cek_pendaftaran.php">Periksa status dan hasil <i class="bi bi-arrow-right"></i></a>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>