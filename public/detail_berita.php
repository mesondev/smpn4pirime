<?php require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$item = portal_row($pdo, "SELECT id,judul,ringkasan,isi,kategori,gambar_url,COALESCE(published_at,created_at) tanggal,updated_at FROM berita_sekolah WHERE id=? AND status='Terbit'", [(int)($_GET['id'] ?? 0)]);
if (!$item) http_response_code(404);
$related = $item ? portal_rows($pdo, "SELECT id,judul,ringkasan,kategori,gambar_url,COALESCE(published_at,created_at) tanggal FROM berita_sekolah WHERE status='Terbit' AND id<>? AND kategori=? ORDER BY COALESCE(published_at,created_at) DESC,id DESC LIMIT 3", [(int)$item['id'], (string)$item['kategori']]) : [];
$popular = portal_rows($pdo, "SELECT id,judul,kategori,gambar_url,COALESCE(published_at,created_at) tanggal FROM berita_sekolah WHERE status='Terbit'" . ($item ? " AND id<>" . (int)$item['id'] : '') . " ORDER BY COALESCE(published_at,created_at) DESC,id DESC LIMIT 4");
$latest = portal_rows($pdo, "SELECT id,judul,COALESCE(published_at,created_at) tanggal FROM berita_sekolah WHERE status='Terbit'" . ($item ? " AND id<>" . (int)$item['id'] : '') . " ORDER BY COALESCE(published_at,created_at) DESC,id DESC LIMIT 5");
$pageTitle = $item['judul'] ?? 'Berita tidak ditemukan';
$activePage = 'berita';
require __DIR__ . '/_header.php';
$img = portal_image($item['gambar_url'] ?? '', 'berita', (int)($item['id'] ?? 0)); ?>

<section class="page-hero article-detail-hero">
    <div class="wrap">
        <span class="eyebrow">Artikel dan informasi resmi</span>
        <h1><?= $item ? 'Detail Artikel Berita' : 'Berita tidak ditemukan' ?></h1>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content article-detail-content">
    <div class="wrap">
        <div class="article-detail-layout"><article class="article article-detail-main">
            <?php if ($item): ?>
                <div class="article-detail-heading"><span class="article-category"><?= e($item['kategori'] ?: 'Berita Sekolah') ?></span><h2><?= e($item['judul']) ?></h2><p class="article-meta"><i class="bi bi-calendar3" aria-hidden="true"></i> Diterbitkan <?= e(portal_date($item['tanggal'])) ?><?php if (!empty($item['updated_at']) && substr((string)$item['updated_at'], 0, 10) > substr((string)$item['tanggal'], 0, 10)): ?> · Diperbarui <?= e(portal_date($item['updated_at'])) ?><?php endif; ?></p></div>
                <?php if ($img): ?>
                    <img class="article-image" src="<?= e($img) ?>" alt="<?= e($item['judul']) ?>" loading="eager" decoding="async">
                <?php endif; ?>
                <?php if ($item['ringkasan']): ?>
                    <p><strong>
                            <?= e($item['ringkasan']) ?>
                        </strong>
                    </p>
                <?php endif; ?>
                <div class="article-body article-detail-body">
                    <?= nl2br(e($item['isi'])) ?>
                </div><?php else: ?>
                <div class="empty">Berita ini tidak tersedia atau belum diterbitkan.</div>
            <?php endif; ?>
            <?php if ($item): ?><div class="article-share"><h3><i class="bi bi-share" aria-hidden="true"></i> Bagikan artikel ini</h3><p>Bantu sebarkan informasi ini kepada keluarga dan teman.</p><div class="article-share-links"><a class="share-facebook" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook" aria-hidden="true"></i> Facebook</a><a class="share-whatsapp" href="https://wa.me/?text=<?= rawurlencode($item['judul'] . ' — ' . ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''))) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp</a><a class="share-copy" href="mailto:?subject=<?= rawurlencode($item['judul']) ?>&body=<?= rawurlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> Email</a></div></div><?php endif; ?><p class="article-back"><a class="text-link" href="berita.php">← Kembali ke berita</a></p>
        </article><aside class="article-detail-sidebar" aria-label="Berita lainnya"><?php if ($popular): ?><section class="article-sidebar-card"><h2><i class="bi bi-fire" aria-hidden="true"></i> Artikel Populer</h2><div class="article-popular-list"><?php foreach ($popular as $story): $storyImage = portal_image($story['gambar_url'], 'berita', (int)$story['id']); ?><a class="article-popular-item" href="detail_berita.php?id=<?= (int)$story['id'] ?>"><?php if ($storyImage): ?><img src="<?= e($storyImage) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="article-popular-placeholder"><i class="bi bi-newspaper" aria-hidden="true"></i></span><?php endif; ?><span><strong><?= e($story['judul']) ?></strong><small><?= e(portal_date($story['tanggal'])) ?></small></span></a><?php endforeach; ?></div></section><?php endif; ?><?php if ($latest): ?><section class="article-sidebar-card"><h2><i class="bi bi-clock-history" aria-hidden="true"></i> Artikel Terbaru</h2><div class="article-latest-list"><?php foreach ($latest as $story): ?><a href="detail_berita.php?id=<?= (int)$story['id'] ?>"><strong><?= e($story['judul']) ?></strong><small><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(portal_date($story['tanggal'])) ?></small></a><?php endforeach; ?></div></section><?php endif; ?></aside></div>
        <?php if ($related): ?><section class="related-content" aria-labelledby="relatedNewsTitle"><div class="section-head"><div><span class="kicker">Lanjut membaca</span><h2 id="relatedNewsTitle">Berita terkait</h2></div></div><div class="cards"><?php foreach ($related as $story): $storyImage = portal_image($story['gambar_url'], 'berita', (int)$story['id']); ?><article class="card story-card"><?php if ($storyImage): ?><img class="story-image" src="<?= e($storyImage) ?>" alt="<?= e($story['judul']) ?>" loading="lazy" decoding="async"><?php endif; ?><div class="story-body"><span class="date-label"><?= e($story['kategori']) ?> · <?= e(portal_date($story['tanggal'])) ?></span><h3><a href="detail_berita.php?id=<?= (int)$story['id'] ?>"><?= e($story['judul']) ?></a></h3><p><?= e(portal_excerpt($story['ringkasan'], 130)) ?></p></div></article><?php endforeach; ?></div></section><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
