<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Berita dan Kegiatan';
$activePage = 'berita';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$category = trim(mb_substr((string)($_GET['kategori'] ?? ''), 0, 80, 'UTF-8'));
$perPage = 9;
$page = max(1, (int)($_GET['page'] ?? 1));
$params = [];
$where = "status='Terbit'";
if ($q !== '') {
    $where .= ' AND (judul LIKE ? OR ringkasan LIKE ? OR kategori LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}
$schemaReady = portal_schema_ready($pdo, 'berita_sekolah', ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at']);
$categories = $schemaReady ? portal_rows($pdo, "SELECT DISTINCT kategori FROM berita_sekolah WHERE status='Terbit' AND kategori<>'' ORDER BY kategori") : [];
$categoryNames = array_column($categories, 'kategori');
if ($category !== '' && in_array($category, $categoryNames, true)) {
    $where .= ' AND kategori=?';
    $params[] = $category;
} elseif ($category !== '') {
    $category = '';
}
$total = 0;
$pageCount = 1;
$items = [];
if ($schemaReady) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM berita_sekolah WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pageCount = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $perPage;
    $items = portal_rows($pdo, "SELECT id,judul,ringkasan,isi,kategori,gambar_url,COALESCE(published_at,created_at) tanggal FROM berita_sekolah WHERE $where ORDER BY COALESCE(published_at,created_at) DESC,id DESC LIMIT $perPage OFFSET $offset", $params);
}
require __DIR__ . '/_header.php'; ?>

<section class="page-hero news-page-hero">
    <div class="wrap">
        <span class="eyebrow">Artikel dan informasi resmi</span>
        <h1>Berita Sekolah</h1>
        <p>Kegiatan, prestasi, dan cerita pembelajaran
            <?= e($portalProfile['nama']) ?>.
        </p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content news-page-content">
    <div class="wrap">
        <?php if (!$schemaReady): ?>
            <?php public_service_notice('Berita sekolah belum dapat dimuat. Silakan hubungi sekolah untuk memperoleh kabar terbaru.'); ?>
        <?php endif; ?>
        <form class="search-form public-news-filter" method="get" role="search">
            <label class="visually-hidden" for="newsQuery">Cari berita</label><input id="newsQuery" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="Cari judul, ringkasan, atau kategori">
            <label class="visually-hidden" for="newsCategory">Kategori berita</label><select id="newsCategory" name="kategori"><option value="">Semua kategori</option><?php foreach ($categoryNames as $categoryName): ?><option value="<?= e($categoryName) ?>" <?= $category === $categoryName ? 'selected' : '' ?>><?= e($categoryName) ?></option><?php endforeach; ?></select>
            <button type="submit">Terapkan</button><?php if ($q !== '' || $category !== ''): ?><a class="btn-primary" href="berita.php">Reset</a><?php endif; ?>
        </form>
        <p class="listing-summary" aria-live="polite">Menampilkan <?= count($items) ?> dari <?= number_format($total, 0, ',', '.') ?> berita terbit<?= $category !== '' ? ' · kategori ' . e($category) : '' ?>.</p>
        <div class="cards">
            <?php if (!$items && $schemaReady): ?>
                <?php public_empty_panel('journal-text', 'Belum ada berita yang diterbitkan', 'Berita sekolah akan muncul di sini setelah pengelola memeriksa dan menerbitkannya.'); ?>
                <?php else: foreach ($items as $s): $img = portal_image($s['gambar_url'], 'berita', (int)$s['id']); ?>
                    <article class="card story-card">
                        <?php if ($img): ?>
                            <img class="story-image" src="<?= e($img) ?>" alt="<?= e($s['judul']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="story-image"></div>
                        <?php endif; ?>
                        <div class="story-body">
                            <span class="date-label">
                                <?= e($s['kategori']) ?> ·
                                <?= e(portal_date($s['tanggal'])) ?>
                            </span>
                            <h3><a href="detail_berita.php?id=<?= (int)$s['id'] ?>">
                                    <?= e($s['judul']) ?></a></h3>
                            <p><?= e(portal_excerpt($s['ringkasan'] ?: $s['isi'], 155)) ?></p>
                            <a class="text-link" href="detail_berita.php?id=<?= (int)$s['id'] ?>">Baca berita →</a>
                        </div>
                    </article>
            <?php endforeach;
            endif; ?>
        </div>
        <?php if ($pageCount > 1): ?><nav class="public-pagination" aria-label="Halaman berita"><?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?><a href="?<?= e(http_build_query(['q' => $q, 'kategori' => $category, 'page' => $number])) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
