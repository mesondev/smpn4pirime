<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Pengumuman';
$activePage = 'pengumuman';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$params = [];
$where = "status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE())";
if ($q !== '') {
    $where .= ' AND judul LIKE ?';
    $params[] = '%' . $q . '%';
}
$items = portal_rows($pdo, "SELECT id,judul,isi,COALESCE(mulai_tayang,DATE(created_at)) tanggal FROM pengumuman WHERE $where ORDER BY COALESCE(mulai_tayang,DATE(created_at)) DESC,id DESC", $params);
require __DIR__ . '/_header.php'; ?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Informasi resmi</span>
        <h1>Pengumuman Sekolah</h1>
        <p>Pengumuman yang ditampilkan telah diterbitkan dan masih berlaku.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>
<section class="content">
    <div class="wrap">
        <form class="search-form" method="get">
            <input name="q" value="<?= e($q) ?>" placeholder="Cari judul pengumuman" aria-label="Cari judul pengumuman">
            <button type="submit">Cari</button>
        </form>
        <div class="notice-list">
            <?php if (!$items): ?>
                <?php public_empty_panel('megaphone', $q !== '' ? 'Pengumuman tidak ditemukan' : 'Belum ada pengumuman aktif', $q !== '' ? 'Coba kata kunci lain atau hubungi sekolah untuk menanyakan informasi yang Anda cari.' : 'Pengumuman resmi akan ditampilkan setelah diterbitkan dan masih berlaku.'); ?>
                <?php else: foreach ($items as $item): ?>
                    <a class="notice-item" href="detail_pengumuman.php?id=<?= (int)$item['id'] ?>">
                        <span class="date-label">
                            <?= e(portal_date($item['tanggal'])) ?>
                        </span>
                        <h3><?= e($item['judul']) ?></h3>
                        <p><?= e(portal_excerpt($item['isi'], 220)) ?></p>
                    </a><?php endforeach;
                endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
