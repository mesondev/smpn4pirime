<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Direktori Guru';
$activePage = 'guru';
$teacherImages = public_teacher_images();
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
    $offset = ($page - 1) * $perPage;
    $photoSelect = isset($columns['foto']) ? ',foto' : '';
    $list = $pdo->prepare('SELECT id,nama' . $photoSelect . ' FROM guru WHERE ' . $where . ' ORDER BY nama,id LIMIT ' . $perPage . ' OFFSET ' . $offset);
    $list->execute($params);
    $teachers = $list->fetchAll();
}
require __DIR__ . '/_header.php';

?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Tenaga pendidik</span>
        <h1>Direktori Guru</h1>
        <p>Profil guru aktif yang telah disetujui pengelola sekolah untuk ditampilkan kepada masyarakat.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <?php if (!$ready): ?>
            <?php public_service_notice('Direktori guru belum dapat dimuat. Untuk pertanyaan mengenai tenaga pendidik, silakan hubungi sekolah.'); ?>
        <?php else: ?>
            <form class="search-form" method="get" role="search">
                <input name="q" value="<?= e($query) ?>" placeholder="Cari nama guru" aria-label="Cari nama guru">
                <button type="submit">Cari</button>
                <?php if ($query !== ''): ?>
                    <a class="btn-primary" style="background:var(--paper)" href="guru.php">Reset</a>
                <?php endif; ?>
            </form>
            <?php if (!$teachers): ?>
                <?php public_empty_panel('people', $query !== '' ? 'Guru tidak ditemukan' : 'Profil guru belum tersedia', $query !== '' ? 'Periksa ejaan nama atau coba kata kunci yang lebih singkat.' : 'Profil guru akan ditampilkan setelah disetujui untuk publikasi.'); ?>

            <?php else: ?>
                <div class="cards">
                    <?php foreach ($teachers as $teacher): $photo = (string)($teacher['foto'] ?? '');
                        $photo = preg_match('~^uploads/guru/[a-f0-9-]+\.(?:jpg|png|webp)$~i', $photo) ? '../media/guru.php?id=' . (int)$teacher['id'] : ''; ?>
                        <article class="card story-card" style="text-align:center;padding:24px">
                            <div style="height:156px;display:grid;place-items:center;margin-bottom:16px">
                                <?php if ($photo): ?>
                                    <img src="<?= e($photo) ?>" alt="Foto <?= e($teacher['nama']) ?>" style="width:112px;height:140px;object-fit:cover;border-radius:14px" loading="lazy">
                                <?php else: ?>
                                    <span class="tile-icon" style="width:96px;height:96px;border-radius:50%;font-size:2.5rem">
                                        <i class="bi bi-person-fill"></i>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h2 style="font-size:1.05rem;margin:0">
                                <?= e($teacher['nama']) ?></h2>
                            <p class="article-meta" style="margin:6px 0 0">Tenaga Pendidik</p>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($pageCount > 1): ?>
                    <nav aria-label="Halaman direktori" style="display:flex;justify-content:center;gap:8px;margin-top:28px">
                        <?php for ($number = max(1, $page - 2); $number <= min($pageCount, $page + 2); $number++): ?>
                            <a class="<?= $number === $page ? 'staff-link' : 'text-link' ?>" href="?<?= e(http_build_query(['q' => $query, 'page' => $number])) ?>">
                                <?= $number ?>
                            </a><?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!$teachers && $query === ''): ?>
            <section class="public-image-section" aria-label="Foto tenaga pendidik">
                <div class="section-head public-inline-heading">
                    <div><span class="kicker">Tenaga pendidik</span>
                        <h2>Potret guru sekolah</h2>
                        <p>Foto berasal dari folder aset guru. Nama dan profil individual akan ditampilkan setelah data jabatan dan izin publikasi dikonfirmasi.</p>
                    </div>
                </div>
                <div class="teacher-reference-grid">
                    <?php foreach ($teacherImages as $index => $image): ?>
                        <figure class="card teacher-reference-card"><img src="<?= e($image['src']) ?>" alt="<?= e($image['alt']) ?>" loading="lazy" decoding="async">
                            <figcaption><strong>Tenaga pendidik</strong><span>Foto <?= $index + 1 ?> · nama menunggu konfirmasi</span></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
