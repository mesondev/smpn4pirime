<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Direktori Guru';
$activePage = 'guru';
$query = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$teachers = [];
$total = 0;
$pageCount = 1;
$ready = false;
try {
    $columns = array_column($pdo->query('SHOW COLUMNS FROM guru')->fetchAll(), null, 'Field');
    $ready = isset($columns['nama'], $columns['status'], $columns['tampil_publik']);
} catch (PDOException $e) {
    http_response_code(503);
}
if ($ready) {
    $where = "status='Aktif' AND tampil_publik=1";
    $params = [];
    if ($query !== '') {
        $where .= ' AND nama LIKE ?';
        $params[] = '%' . $query . '%';
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM guru WHERE ' . $where);
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pageCount = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pageCount);
    $resultFrom = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
    $resultTo = min($total, $page * $perPage);
    $offset = ($page - 1) * $perPage;
    $photoSelect = isset($columns['foto']) ? ',foto' : '';
    $list = $pdo->prepare('SELECT id,nama' . $photoSelect . ' FROM guru WHERE ' . $where . ' ORDER BY nama,id LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $list->execute($params);
    $teachers = $list->fetchAll();
}
require __DIR__ . '/_header.php';

?>
<section class="page-hero directory-page-hero">
    <div class="wrap">
        <span class="eyebrow">Profil sekolah</span>
        <h1>Direktori Guru</h1>
        <p>Kenali tenaga pendidik yang telah menyetujui profilnya untuk ditampilkan kepada masyarakat.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content profile-subpage-content directory-page-content">
    <div class="wrap profile-directory-wrap">
        <div class="profile-directory-intro"><span class="profile-story-icon" aria-hidden="true"><i class="bi bi-person-workspace"></i></span><div><strong>Guru dan tenaga kependidikan</strong><p>Direktori hanya memuat profil aktif yang ditandai boleh ditampilkan oleh pengelola. Informasi pribadi dan kontak langsung personel tidak dipublikasikan.</p></div></div>
        <?php if (!$ready): ?>
            <?php public_service_notice('Direktori guru belum dapat dimuat. Untuk pertanyaan mengenai tenaga pendidik, silakan hubungi sekolah.'); ?>
        <?php else: ?>
            <form class="search-form profile-directory-search" method="get" role="search">
                <label for="teacherQuery">Cari nama</label><input id="teacherQuery" name="q" maxlength="100" value="<?= e($query) ?>" placeholder="Ketik nama guru">
                <button type="submit"><i class="bi bi-search" aria-hidden="true"></i> Cari</button>
                <?php if ($query !== ''): ?><a class="profile-reset-link" href="guru.php">Hapus pencarian</a><?php endif; ?>
            </form>
            <?php if ($total > 0): ?><p class="profile-result-count" role="status">Menampilkan <?= number_format($resultFrom, 0, ',', '.') ?>–<?= number_format($resultTo, 0, ',', '.') ?> dari <?= number_format($total, 0, ',', '.') ?> profil yang disetujui.</p><?php endif; ?>
            <?php if (!$teachers): ?>
                <?php public_empty_panel('people', $query !== '' ? 'Guru tidak ditemukan' : 'Profil guru belum tersedia', $query !== '' ? 'Periksa ejaan nama atau coba kata kunci yang lebih singkat.' : 'Profil guru akan ditampilkan setelah disetujui untuk publikasi.'); ?>

            <?php else: ?>
                <div class="cards profile-teacher-grid">
                    <?php foreach ($teachers as $teacher): $photo = (string)($teacher['foto'] ?? '');
                        $photo = preg_match('~^uploads/guru/[a-f0-9-]+\.(?:jpg|png|webp)$~i', $photo) ? '../media/guru.php?id=' . (int)$teacher['id'] : ''; ?>
                        <article class="card profile-teacher-card">
                            <div class="profile-teacher-photo">
                                <?php if ($photo): ?>
                                    <img src="<?= e($photo) ?>" alt="Foto <?= e($teacher['nama']) ?>" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span aria-hidden="true">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="kicker">Tenaga pendidik</span>
                            <h2><?= e($teacher['nama']) ?></h2>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($pageCount > 1): ?>
                    <nav class="profile-pagination" aria-label="Halaman direktori guru">
                        <?php if ($page > 1): ?><a href="?<?= e(http_build_query(['q' => $query, 'page' => $page - 1])) ?>" rel="prev"><i class="bi bi-arrow-left" aria-hidden="true"></i> Sebelumnya</a><?php endif; ?>
                        <?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?>
                            <a class="<?= $number === $page ? 'is-current' : '' ?>" href="?<?= e(http_build_query(['q' => $query, 'page' => $number])) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a><?php endfor; ?>
                        <?php if ($page < $pageCount): ?><a href="?<?= e(http_build_query(['q' => $query, 'page' => $page + 1])) ?>" rel="next">Berikutnya <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
