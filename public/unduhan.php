<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$pageTitle = 'Pusat Unduhan';
$activePage = 'unduhan';
$ready = portal_schema_ready($pdo, 'portal_dokumen_publik', ['judul', 'kategori', 'nama_file', 'status']);
$items = $ready
    ? portal_rows($pdo, "SELECT id, judul, kategori, deskripsi, published_at FROM portal_dokumen_publik WHERE status='Terbit' AND (published_at IS NULL OR published_at<=NOW()) ORDER BY published_at DESC, id DESC")
    : [];

require __DIR__ . '/_header.php';
public_page_hero(
    'Dokumen resmi',
    $pageTitle,
    'Unduh dokumen dan panduan yang diterbitkan secara resmi oleh sekolah.',
    $activePage
);
?>
<section class="content">
    <div class="wrap">
        <?php if (!$ready): ?>
            <?php public_service_notice('Pusat unduhan belum dapat dimuat. Dokumen resmi akan tersedia setelah diperiksa dan diterbitkan sekolah.'); ?>
        <?php else: ?>
            <?php
            public_text_cards(
                $items,
                'Belum ada dokumen publik',
                'Dokumen yang telah disetujui sekolah akan muncul di sini.',
                static function (array $item): void {
            ?>
                <article class="card public-feature-card">
                    <span class="tile-icon"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i></span>
                    <span class="kicker"><?= e($item['kategori']) ?></span>
                    <h2><?= e($item['judul']) ?></h2>
                    <p><?= e($item['deskripsi'] ?: 'Dokumen resmi sekolah.') ?></p>
                    <a class="text-link" href="../media/public_document.php?id=<?= (int)$item['id'] ?>">
                        Unduh dokumen <i class="bi bi-download" aria-hidden="true"></i>
                    </a>
                </article>
            <?php
                }
            );
            ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>