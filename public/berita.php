<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Berita dan Kegiatan';
$activePage = 'berita';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$params = [];
$where = "status='Terbit'";
if ($q !== '') {
    $where .= ' AND (judul LIKE ? OR ringkasan LIKE ? OR kategori LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}
$schemaReady = portal_schema_ready($pdo, 'berita_sekolah', ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at']);
$items = $schemaReady ? portal_rows($pdo, "SELECT id,judul,ringkasan,isi,kategori,gambar_url,COALESCE(published_at,created_at) tanggal FROM berita_sekolah WHERE $where ORDER BY COALESCE(published_at,created_at) DESC,id DESC", $params) : [];
require __DIR__ . '/_header.php'; ?>

<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Kabar sekolah</span>
        <h1>Berita dan Kegiatan</h1>
        <p>Kegiatan, prestasi, dan cerita pembelajaran
            <?= e($portalProfile['nama']) ?>.
        </p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content">
    <div class="wrap">
        <?php if (!$schemaReady): ?>
            <?php public_service_notice('Berita sekolah belum dapat dimuat. Silakan hubungi sekolah untuk memperoleh kabar terbaru.'); ?>
        <?php endif; ?>
        <form class="search-form" method="get">
            <input name="q" value="<?= e($q) ?>" placeholder="Cari berita atau kategori" aria-label="Cari berita">
            <button>Cari</button>
        </form>
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
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
