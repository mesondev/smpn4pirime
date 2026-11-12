<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Pengumuman';
$activePage = 'pengumuman';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$dateFrom = trim((string)($_GET['tanggal_mulai'] ?? ''));
$dateTo = trim((string)($_GET['tanggal_selesai'] ?? ''));
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$dateValid = static function (string $date): bool {
    if ($date === '') return true;
    $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date;
};
$filterError = '';
if (!$dateValid($dateFrom)) { $dateFrom = ''; $filterError = 'Tanggal mulai tidak valid.'; }
if (!$dateValid($dateTo)) { $dateTo = ''; $filterError = 'Tanggal selesai tidak valid.'; }
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) $filterError = 'Tanggal mulai tidak boleh melewati tanggal selesai.';
$params = [];
$where = "status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE())";
if ($q !== '') {
    $where .= ' AND judul LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($dateFrom !== '') { $where .= ' AND COALESCE(mulai_tayang,DATE(created_at))>=?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where .= ' AND COALESCE(mulai_tayang,DATE(created_at))<=?'; $params[] = $dateTo; }
$total = 0;
$pageCount = 1;
$items = [];
if ($filterError === '') {
    $count = $pdo->prepare("SELECT COUNT(*) FROM pengumuman WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pageCount = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $perPage;
    $items = portal_rows($pdo, "SELECT id,judul,isi,COALESCE(mulai_tayang,DATE(created_at)) tanggal FROM pengumuman WHERE $where ORDER BY COALESCE(mulai_tayang,DATE(created_at)) DESC,id DESC LIMIT $perPage OFFSET $offset", $params);
}
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
        <form class="search-form public-announcement-filter" method="get" role="search">
            <label class="visually-hidden" for="announcementQuery">Cari judul pengumuman</label><input id="announcementQuery" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="Cari judul pengumuman">
            <label for="noticeFrom">Dari</label><input id="noticeFrom" type="date" name="tanggal_mulai" value="<?= e($dateFrom) ?>">
            <label for="noticeTo">Sampai</label><input id="noticeTo" type="date" name="tanggal_selesai" value="<?= e($dateTo) ?>">
            <button type="submit">Terapkan</button><?php if ($q !== '' || $dateFrom !== '' || $dateTo !== ''): ?><a class="btn-primary" href="pengumuman.php">Reset</a><?php endif; ?>
        </form>
        <?php if ($filterError !== ''): ?><p class="form-message form-message-error" role="alert"><?= e($filterError) ?></p><?php endif; ?>
        <?php if ($filterError === ''): ?><p class="listing-summary" aria-live="polite">Menampilkan <?= count($items) ?> dari <?= number_format($total, 0, ',', '.') ?> pengumuman aktif.</p><?php endif; ?>
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
        <?php if ($pageCount > 1 && $filterError === ''): ?><nav class="public-pagination" aria-label="Halaman pengumuman"><?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?><a href="?<?= e(http_build_query(['q' => $q, 'tanggal_mulai' => $dateFrom, 'tanggal_selesai' => $dateTo, 'page' => $number])) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
