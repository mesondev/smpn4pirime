<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Visi dan Misi';
$activePage = 'visi-misi';
$vision = ($portalProfile['visi_status'] ?? '') === 'resmi'
    ? trim((string)($portalProfile['visi'] ?? ''))
    : '';
$mission = ($portalProfile['misi_status'] ?? '') === 'resmi'
    ? trim((string)($portalProfile['misi'] ?? ''))
    : '';
$objectives = ($portalProfile['tujuan_status'] ?? '') === 'resmi'
    ? trim((string)($portalProfile['tujuan'] ?? ''))
    : '';

require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Arah pendidikan</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Landasan dan tujuan pendidikan sekolah yang telah disahkan.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap public-feature-grid">
        <article class="card public-feature-card">
            <span class="tile-icon"><i class="bi bi-bullseye" aria-hidden="true"></i></span>
            <h2>Visi</h2>
            <p><?= $vision !== '' ? nl2br(e($vision)) : 'Visi resmi belum disetujui untuk ditampilkan.' ?></p>
        </article>
        <article class="card public-feature-card">
            <span class="tile-icon"><i class="bi bi-list-check" aria-hidden="true"></i></span>
            <h2>Misi</h2>
            <p><?= $mission !== '' ? nl2br(e($mission)) : 'Misi resmi belum disetujui untuk ditampilkan.' ?></p>
        </article>
        <article class="card public-feature-card">
            <span class="tile-icon"><i class="bi bi-flag" aria-hidden="true"></i></span>
            <h2>Tujuan</h2>
            <p><?= $objectives !== '' ? nl2br(e($objectives)) : 'Tujuan resmi belum disetujui untuk ditampilkan.' ?></p>
        </article>
    </div>
    <?php if ($vision === '' || $mission === '' || $objectives === ''): ?>
        <div class="wrap public-approval-note"><i class="bi bi-shield-check" aria-hidden="true"></i>
            <p>Bagian yang belum tampil menunggu dokumen resmi sekolah. Hubungi sekolah untuk memperoleh keterangan yang sudah disahkan.</p><a class="text-link" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>