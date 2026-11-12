<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Sejarah Sekolah';
$activePage = 'sejarah';
$historyStatus = strtolower(trim((string)($portalProfile['sejarah_status'] ?? 'perlu_verifikasi')));
$history = trim((string)($portalProfile['sejarah'] ?? ''));
$historyPublished = $history !== '' && in_array($historyStatus, ['resmi', 'terverifikasi'], true);
$historyStatusLabel = $historyPublished ? 'Telah diverifikasi' : ($history !== '' ? 'Menunggu verifikasi' : 'Belum diterbitkan');
require __DIR__ . '/_header.php';
?>
<section class="page-hero profile-page-hero">
    <div class="wrap">
        <span class="eyebrow">Profil sekolah</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Catatan perjalanan <?= e($portalProfile['nama']) ?> berdasarkan arsip dan keterangan yang disahkan sekolah.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content profile-subpage-content">
    <div class="wrap profile-subpage-wrap">
        <article class="profile-story-card" aria-labelledby="schoolHistoryTitle">
            <div class="profile-story-heading">
                <span class="profile-story-icon" aria-hidden="true"><i class="bi bi-journal-richtext"></i></span>
                <div><span class="kicker">Arsip dan perjalanan</span><h2 id="schoolHistoryTitle">Perjalanan sekolah</h2></div>
                <span class="profile-status-pill <?= $historyPublished ? 'is-published' : 'is-pending' ?>"><i class="bi <?= $historyPublished ? 'bi-patch-check-fill' : 'bi-hourglass-split' ?>" aria-hidden="true"></i><?= e($historyStatusLabel) ?></span>
            </div>
            <?php if ($historyPublished): ?>
                <div class="profile-story-copy public-rich-text"><?= nl2br(e($history)) ?></div>
                <p class="profile-source-note"><i class="bi bi-info-circle-fill" aria-hidden="true"></i> Riwayat ini ditampilkan dari profil sekolah yang telah diberi status publikasi resmi.</p>
            <?php elseif ($history !== ''): ?>
                <div class="profile-pending-state" role="status"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>Naskah sejarah belum ditayangkan</strong><p>Informasi sudah tersimpan, tetapi menunggu pencocokan dengan SK pendirian, arsip sekolah, atau keterangan resmi Kepala Sekolah.</p></div></div>
            <?php else: ?>
                <div class="profile-pending-state" role="status"><i class="bi bi-journal-text" aria-hidden="true"></i><div><strong>Riwayat sekolah belum diterbitkan</strong><p>Tahun pendirian dan catatan perjalanan akan ditambahkan setelah diverifikasi dengan arsip atau dokumen sekolah. Halaman ini tidak mengisi sejarah berdasarkan perkiraan.</p></div></div>
            <?php endif; ?>
            <a class="text-link" href="profil.php#schoolIdentityTitle">Lihat identitas sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </article>
        <aside class="profile-related-card" aria-label="Informasi terkait profil">
            <span class="kicker">Jelajahi profil</span>
            <h2>Informasi sekolah lainnya</h2>
            <a href="profil.php#schoolIdentityTitle"><i class="bi bi-card-list" aria-hidden="true"></i> Identitas sekolah</a>
            <a href="visi-misi.php"><i class="bi bi-compass-fill" aria-hidden="true"></i> Visi dan misi</a>
            <a href="struktur.php"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i> Struktur organisasi</a>
            <a href="kontak.php"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i> Hubungi sekolah</a>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
