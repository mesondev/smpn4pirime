<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Ekstrakurikuler';
$activePage = 'ekstrakurikuler';
$ready = portal_schema_ready($pdo, 'portal_ekstrakurikuler', ['nama', 'status', 'urutan']);
$items = $ready ? portal_rows($pdo, "SELECT nama,kategori,deskripsi,pembina,jadwal,persyaratan FROM portal_ekstrakurikuler WHERE status='Terbit' ORDER BY urutan,nama") : [];
require __DIR__ . '/_header.php';
public_page_hero('Bakat dan minat', $pageTitle, 'Kenali kegiatan pengembangan diri yang telah ditetapkan dan dipublikasikan sekolah.', $activePage); ?>
<section class="content">
    <div class="wrap">
        <?php if (!$ready): ?><?php public_service_notice('Daftar kegiatan belum dapat dimuat. Jenis kegiatan, pembina, dan jadwal akan ditampilkan setelah diverifikasi sekolah.'); ?><?php else: public_text_cards($items, 'Informasi kegiatan sedang disiapkan', 'Jenis kegiatan, pembina, dan jadwal akan ditampilkan setelah diverifikasi oleh sekolah.', static function (array $item): void { ?>
        <article class="card public-feature-card"><span class="tile-icon"><i class="bi bi-stars" aria-hidden="true"></i></span><span class="kicker"><?= e($item['kategori'] ?: 'Kegiatan sekolah') ?></span>
            <h2><?= e($item['nama']) ?></h2>
            <p><?= e($item['deskripsi'] ?: 'Informasi kegiatan belum ditambahkan.') ?></p>
            <dl><?php if ($item['pembina']): ?><div>
                        <dt>Pembina</dt>
                        <dd><?= e($item['pembina']) ?></dd>
                    </div><?php endif; ?><?php if ($item['jadwal']): ?><div>
                        <dt>Jadwal</dt>
                        <dd><?= e($item['jadwal']) ?></dd>
                    </div><?php endif; ?><?php if ($item['persyaratan']): ?><div>
                        <dt>Persyaratan</dt>
                        <dd><?= e($item['persyaratan']) ?></dd>
                    </div><?php endif; ?></dl>
        </article>
<?php });
                                                                                                                                                                                    endif; ?><div class="ppdb-contact-strip">
    <div><strong>Ingin mengetahui kegiatan yang tersedia?</strong><span>Hubungi sekolah untuk informasi terbaru.</span></div><a class="btn-primary" href="kontak.php">Kontak sekolah <i class="bi bi-arrow-right"></i></a>
</div>
    </div>
</section><?php require __DIR__ . '/_footer.php';
