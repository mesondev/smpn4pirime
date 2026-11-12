<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Visi dan Misi';
$activePage = 'visi-misi';
$directionItems = [
    ['title' => 'Visi', 'field' => 'visi', 'status' => 'visi_status', 'icon' => 'compass-fill', 'class' => 'vision'],
    ['title' => 'Misi', 'field' => 'misi', 'status' => 'misi_status', 'icon' => 'list-check', 'class' => 'mission'],
    ['title' => 'Tujuan', 'field' => 'tujuan', 'status' => 'tujuan_status', 'icon' => 'bullseye', 'class' => 'objectives'],
];
$statusLabels = ['rancangan' => 'Rancangan', 'perlu_verifikasi' => 'Perlu verifikasi', 'resmi' => 'Disahkan'];
$publishedCount = 0;
foreach ($directionItems as &$item) {
    $item['status_value'] = strtolower(trim((string)($portalProfile[$item['status']] ?? 'perlu_verifikasi')));
    $item['content'] = $item['status_value'] === 'resmi' ? trim((string)($portalProfile[$item['field']] ?? '')) : '';
    $item['lines'] = preg_split('/\R+/u', $item['content'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $item['label'] = $statusLabels[$item['status_value']] ?? 'Perlu verifikasi';
    if ($item['content'] !== '') $publishedCount++;
}
unset($item);
require __DIR__ . '/_header.php';
?>
<section class="page-hero profile-page-hero">
    <div class="wrap">
        <span class="eyebrow">Profil sekolah</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Arah pendidikan dan hasil yang dituju <?= e($portalProfile['nama']) ?> menurut rumusan yang telah disahkan.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content profile-subpage-content">
    <div class="wrap">
        <div class="profile-direction-intro"><span class="profile-story-icon" aria-hidden="true"><i class="bi bi-signpost-split-fill"></i></span><div><strong>Arah pendidikan sekolah</strong><p>Rumusan hanya ditampilkan setelah statusnya disahkan. Naskah draf dan materi yang menunggu verifikasi tidak disajikan sebagai kebijakan resmi.</p></div></div>
        <div class="profile-direction-grid">
            <?php foreach ($directionItems as $item): ?>
                <article class="profile-direction-card profile-direction-card--<?= e($item['class']) ?>" aria-labelledby="direction-<?= e($item['class']) ?>">
                    <div class="profile-direction-card__heading"><span class="profile-story-icon" aria-hidden="true"><i class="bi bi-<?= e($item['icon']) ?>"></i></span><div><span class="kicker">Arah pendidikan</span><h2 id="direction-<?= e($item['class']) ?>"><?= e($item['title']) ?></h2></div><span class="profile-status-pill <?= $item['content'] !== '' ? 'is-published' : 'is-pending' ?>"><?= e($item['label']) ?></span></div>
                    <?php if ($item['content'] === ''): ?>
                        <div class="profile-pending-state" role="status"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong><?= e($item['title']) ?> resmi belum tersedia</strong><p>Konten ini akan ditampilkan setelah rumusannya diperiksa dan disahkan oleh sekolah.</p></div></div>
                    <?php elseif ($item['class'] === 'vision'): ?>
                        <blockquote><?= nl2br(e($item['content'])) ?></blockquote>
                    <?php elseif (count($item['lines']) > 1): ?>
                        <ol class="profile-direction-list"><?php foreach ($item['lines'] as $line): ?><li><?= e(preg_replace('/^\s*(?:\d+[.)]|[-•])\s*/u', '', $line)) ?></li><?php endforeach; ?></ol>
                    <?php else: ?>
                        <p class="profile-direction-copy"><?= nl2br(e($item['content'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="profile-source-note"><i class="bi bi-info-circle-fill" aria-hidden="true"></i> <?= $publishedCount ?> dari 3 rumusan telah berstatus resmi pada profil sekolah.</p>
        <div class="profile-bottom-cta"><div><span class="kicker">Informasi lebih lanjut</span><strong>Perlu penjelasan tentang arah pendidikan?</strong></div><a href="kontak.php" class="btn-primary">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
