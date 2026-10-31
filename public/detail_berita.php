<?php require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$item = portal_row($pdo, "SELECT id,judul,ringkasan,isi,kategori,gambar_url,COALESCE(published_at,created_at) tanggal FROM berita_sekolah WHERE id=? AND status='Terbit'", [(int)($_GET['id'] ?? 0)]);
if (!$item) http_response_code(404);
$pageTitle = $item['judul'] ?? 'Berita tidak ditemukan';
$activePage = 'berita';
require __DIR__ . '/_header.php';
$img = portal_image($item['gambar_url'] ?? '', 'berita', (int)($item['id'] ?? 0)); ?>

<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">
            <?= e($item['kategori'] ?? 'Berita Sekolah') ?>
        </span>
        <h1>
            <?= e($item['judul'] ?? 'Berita tidak ditemukan') ?>
        </h1>
            <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content">
    <div class="wrap">
        <article class="article">
            <?php if ($item): ?>
                <p class="article-meta">
                    <?= e(portal_date($item['tanggal'])) ?>
                </p>
                <?php if ($img): ?>
                    <img class="article-image" src="
                    <?= e($img) ?>" alt="
                    <?= e($item['judul']) ?>">
                <?php endif; ?>
                <?php if ($item['ringkasan']): ?>
                    <p><strong>
                            <?= e($item['ringkasan']) ?>
                        </strong>
                    </p>
                <?php endif; ?>
                <div class="article-body">
                    <?= nl2br(e($item['isi'])) ?>
                </div><?php else: ?>
                <div class="empty">Berita ini tidak tersedia atau belum diterbitkan.</div>
            <?php endif; ?>
            <p style="margin-top:30px">
                <a class="text-link" href="berita.php">← Kembali ke berita</a>
            </p>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
