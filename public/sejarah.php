<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Sejarah Sekolah';
$activePage = 'sejarah';
$history = ($portalProfile['sejarah_status'] ?? '') === 'terverifikasi'
    ? trim((string)($portalProfile['sejarah'] ?? ''))
    : '';

require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Tentang sekolah</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Perjalanan SMP Negeri 4 Pirime berdasarkan informasi sekolah yang telah ditinjau.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap privacy-content">
        <article class="card public-feature-card">
            <?php if ($history !== ''): ?>
                <div class="public-rich-text"><?= nl2br(e($history)) ?></div>
            <?php else: ?>
                <h2>Informasi sejarah sedang ditinjau</h2>
                <p>Riwayat sekolah akan ditampilkan setelah informasi resmi diverifikasi dan disetujui untuk publikasi.</p>
                <a class="text-link" href="kontak.php">Tanyakan informasi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            <?php endif; ?>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>