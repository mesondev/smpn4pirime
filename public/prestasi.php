<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$pageTitle = 'Prestasi Sekolah';
$activePage = 'prestasi';
$ready = portal_schema_ready($pdo, 'portal_prestasi_publik', [
    'judul',
    'penerima',
    'status',
    'bukti_terverifikasi',
    'izin_publikasi',
]);
$items = $ready
    ? portal_rows($pdo, "SELECT judul, penerima, kategori, tingkat, peringkat, penyelenggara, tanggal, deskripsi
        FROM portal_prestasi_publik
        WHERE status='Terbit' AND bukti_terverifikasi=1 AND izin_publikasi=1
        ORDER BY tanggal DESC, id DESC")
    : [];

require __DIR__ . '/_header.php';
public_page_hero(
    'Capaian warga sekolah',
    $pageTitle,
    'Apresiasi bagi siswa dan warga sekolah atas pencapaian yang sudah diverifikasi dan disetujui untuk dipublikasikan.',
    $activePage
);
?>
<section class="content">
    <div class="wrap">
        <?php if (!$ready): ?>
            <?php public_service_notice('Daftar prestasi belum dapat dimuat. Prestasi hanya ditayangkan setelah bukti dan izin publikasinya diverifikasi.'); ?>
        <?php else: ?>
            <?php
            public_text_cards(
                $items,
                'Belum ada prestasi yang disetujui untuk ditampilkan',
                'Prestasi ditayangkan setelah bukti diverifikasi dan izin publikasi diperoleh.',
                static function (array $item): void {
            ?>
                <article class="card public-feature-card">
                    <span class="tile-icon"><i class="bi bi-award-fill" aria-hidden="true"></i></span>
                    <span class="kicker">
                        <?= e($item['kategori'] ?: 'Prestasi sekolah') ?>
                        <?= $item['tanggal'] ? ' · ' . e(portal_date($item['tanggal'], 'Y')) : '' ?>
                    </span>
                    <h2><?= e($item['judul']) ?></h2>
                    <p>
                        <strong><?= e($item['penerima']) ?></strong>
                        <?= $item['peringkat'] ? ' · ' . e($item['peringkat']) : '' ?>
                    </p>
                    <?php if ($item['tingkat']): ?>
                        <p>
                            Tingkat <?= e($item['tingkat']) ?>
                            <?= $item['penyelenggara'] ? ' · ' . e($item['penyelenggara']) : '' ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($item['deskripsi']): ?>
                        <p><?= e($item['deskripsi']) ?></p>
                    <?php endif; ?>
                </article>
            <?php
                }
            );
            ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>