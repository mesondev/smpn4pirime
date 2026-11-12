<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$pageTitle = 'Pusat Unduhan';
$activePage = 'unduhan';
$query = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$category = trim(mb_substr((string)($_GET['kategori'] ?? ''), 0, 80, 'UTF-8'));
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$ready = portal_schema_ready($pdo, 'portal_dokumen_publik', ['judul', 'kategori', 'nama_file', 'status']);
$where = "status='Terbit' AND (published_at IS NULL OR published_at<=NOW())";
$params = [];
if ($query !== '') { $where .= ' AND (judul LIKE ? OR deskripsi LIKE ?)'; $params[] = '%' . $query . '%'; $params[] = '%' . $query . '%'; }
$categories = $ready ? portal_rows($pdo, "SELECT DISTINCT kategori FROM portal_dokumen_publik WHERE status='Terbit' AND (published_at IS NULL OR published_at<=NOW()) ORDER BY kategori") : [];
$categoryNames = array_column($categories, 'kategori');
if ($category !== '' && in_array($category, $categoryNames, true)) { $where .= ' AND kategori=?'; $params[] = $category; }
elseif ($category !== '') $category = '';
$items = [];
$total = 0;
$pageCount = 1;
if ($ready) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM portal_dokumen_publik WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pageCount = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $perPage;
    $items = portal_rows($pdo, "SELECT id,judul,kategori,deskripsi,nama_file,published_at FROM portal_dokumen_publik WHERE $where ORDER BY published_at DESC,id DESC LIMIT $perPage OFFSET $offset", $params);
}
$storageRoot = realpath(__DIR__ . '/../storage/public_documents');

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
            <form class="search-form public-download-filter" method="get" role="search"><label class="visually-hidden" for="downloadQuery">Cari dokumen</label><input id="downloadQuery" name="q" maxlength="100" value="<?= e($query) ?>" placeholder="Cari judul atau keterangan dokumen"><label class="visually-hidden" for="downloadCategory">Kategori dokumen</label><select id="downloadCategory" name="kategori"><option value="">Semua kategori</option><?php foreach ($categoryNames as $categoryName): ?><option value="<?= e($categoryName) ?>" <?= $category === $categoryName ? 'selected' : '' ?>><?= e($categoryName) ?></option><?php endforeach; ?></select><button type="submit">Cari</button><?php if ($query !== '' || $category !== ''): ?><a class="btn-primary" href="unduhan.php">Reset</a><?php endif; ?></form>
            <p class="listing-summary" aria-live="polite">Menampilkan <?= count($items) ?> dari <?= number_format($total, 0, ',', '.') ?> dokumen publik.</p>
            <?php if (!$items): ?><?php public_empty_panel('file-earmark-arrow-down', $query !== '' || $category !== '' ? 'Dokumen tidak ditemukan' : 'Belum ada dokumen publik', $query !== '' || $category !== '' ? 'Ubah kata kunci atau kategori untuk mencari dokumen lain.' : 'Dokumen yang telah disetujui sekolah akan muncul di sini.'); ?><?php else: ?><div class="cards public-feature-grid">
            <?php foreach ($items as $item): $filePath = preg_match('/^[a-f0-9]{40}\.pdf$/i', (string)$item['nama_file']) && $storageRoot ? realpath($storageRoot . DIRECTORY_SEPARATOR . $item['nama_file']) : false; $fileAvailable = $filePath && str_starts_with($filePath, $storageRoot . DIRECTORY_SEPARATOR) && is_file($filePath); $fileSize = $fileAvailable ? (int)filesize($filePath) : 0; ?>
                <article class="card public-feature-card">
                    <span class="tile-icon"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i></span>
                    <span class="kicker"><?= e($item['kategori']) ?></span>
                    <h2><?= e($item['judul']) ?></h2>
                    <p><?= e($item['deskripsi'] ?: 'Dokumen resmi sekolah.') ?></p>
                    <dl class="document-metadata"><div><dt>Format</dt><dd>PDF</dd></div><div><dt>Ukuran</dt><dd><?= $fileAvailable ? e($fileSize >= 1048576 ? number_format($fileSize / 1048576, 1, ',', '.') . ' MB' : number_format($fileSize / 1024, 0, ',', '.') . ' KB') : 'Berkas belum tersedia' ?></dd></div><div><dt>Diterbitkan</dt><dd><?= e($item['published_at'] ? portal_date($item['published_at']) : 'Tanggal belum dicatat') ?></dd></div></dl>
                    <?php if ($fileAvailable): ?><a class="text-link" href="../media/public_document.php?id=<?= (int)$item['id'] ?>">Unduh dokumen <i class="bi bi-download" aria-hidden="true"></i></a><?php else: ?><span class="document-unavailable" role="status">Berkas tidak tersedia. Hubungi sekolah.</span><?php endif; ?>
                </article>
            <?php endforeach; ?></div><?php endif; ?>
            <?php if ($pageCount > 1): ?><nav class="public-pagination" aria-label="Halaman dokumen"><?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?><a href="?<?= e(http_build_query(['q' => $query, 'kategori' => $category, 'page' => $number])) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
