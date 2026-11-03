<?php require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$item = portal_row($pdo, "SELECT id,judul,isi,COALESCE(mulai_tayang,DATE(created_at)) tanggal FROM pengumuman WHERE id=? AND status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE())", [(int)($_GET['id'] ?? 0)]);
if (!$item) {
    http_response_code(404);
}
$pageTitle = $item['judul'] ?? 'Pengumuman tidak ditemukan';
$activePage = 'pengumuman';
require __DIR__ . '/_header.php'; ?>

<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Pengumuman</span>
        <h1><?= e($item['judul'] ?? 'Informasi tidak ditemukan') ?></h1>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <article class="article">
            <p class="article-meta">
                <?= e(portal_date($item['tanggal'] ?? null)) ?>
            </p>

            <?php if ($item): ?>
                <div class="article-body">
                    <?= nl2br(e($item['isi'])) ?>
                </div>

            <?php else: ?>
                <div class="empty">Pengumuman ini tidak tersedia atau sudah tidak berlaku.</div>
            <?php endif; ?>
            <p style="margin-top:30px">
                <a class="text-link" href="pengumuman.php">← Kembali ke pengumuman</a>
            </p>
        </article>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
