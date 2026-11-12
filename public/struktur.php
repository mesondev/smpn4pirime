<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'Struktur Organisasi';
$activePage = 'struktur';
$structureReady = portal_schema_ready($pdo, 'portal_struktur_sekolah', ['id', 'nama', 'jabatan', 'status', 'urutan', 'foto_url']);
$hasHierarchy = $structureReady && portal_schema_ready($pdo, 'portal_struktur_sekolah', ['induk_id']);
$hasCategory = $structureReady && portal_schema_ready($pdo, 'portal_struktur_sekolah', ['kategori']);
$hasType = $structureReady && portal_schema_ready($pdo, 'portal_struktur_sekolah', ['jenis']);
$structureColumns = ['id', 'nama', 'jabatan', 'foto_url'];
if ($hasHierarchy) $structureColumns[] = 'induk_id';
if ($hasCategory) $structureColumns[] = 'kategori';
if ($hasType) $structureColumns[] = 'jenis';
$items = $structureReady
    ? portal_rows($pdo, 'SELECT ' . implode(', ', $structureColumns) . " FROM portal_struktur_sekolah WHERE status='Terbit' ORDER BY urutan, nama")
    : [];
$teacherDirectoryReady = portal_schema_ready($pdo, 'guru', ['id', 'nama', 'foto', 'status', 'tampil_publik']);
$publicTeachers = $teacherDirectoryReady
    ? portal_rows($pdo, "SELECT id, nama, foto FROM guru WHERE status='Aktif' AND tampil_publik=1 ORDER BY nama, id")
    : [];

$principal = [];
$vicePrincipals = [];
$leaders = [];
$staff = [];
$teachersFromStructure = [];
$units = [];
foreach ($items as $item) {
    $title = mb_strtolower(trim((string)$item['jabatan']), 'UTF-8');
    $category = mb_strtolower(trim((string)($item['kategori'] ?? '')), 'UTF-8');
    $type = mb_strtolower(trim((string)($item['jenis'] ?? '')), 'UTF-8');
    if ($type === 'unit' || preg_match('/\b(unit|layanan)\b/u', $category)) {
        $units[] = $item;
    } elseif (preg_match('/\bwakil\b/u', $title) && preg_match('/\bkepala\b/u', $title)) {
        $vicePrincipals[] = $item;
    } elseif (preg_match('/\bkepala sekolah\b/u', $title) || preg_match('/\b(pelaksana tugas kepala|pimpinan utama)\b/u', $title)) {
        $principal[] = $item;
    } elseif (preg_match('/\b(pimpinan|koordinator)\b/u', $title)) {
        $leaders[] = $item;
    } elseif (preg_match('/\b(guru|pendidik|teacher)\b/u', $title)) {
        $teachersFromStructure[] = $item;
    } else {
        $staff[] = $item;
    }
}

$renderPeople = static function (array $people, string $class = ''): void {
    if (!$people) {
        echo '<div class="structure-empty"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><p>Data yang telah disetujui untuk bagian ini belum tersedia.</p></div>';
        return;
    }
    echo '<div class="structure-people-grid ' . e($class) . '">';
    foreach ($people as $person) {
        $photo = (string)($person['photo_src'] ?? portal_image($person['foto_url'] ?? '', 'struktur', (int)$person['id']));
        echo '<article class="structure-person-card">';
        echo '<div class="structure-person-photo">';
        if ($photo !== '') {
            echo '<img src="' . e($photo) . '" alt="Foto ' . e($person['nama']) . '" loading="lazy" decoding="async">';
        } else {
            echo '<span aria-hidden="true"><i class="bi bi-person-fill"></i></span>';
        }
        echo '</div><span class="structure-person-role">' . e($person['jabatan']) . '</span>';
        echo '<h3>' . e($person['nama']) . '</h3>';
        echo '</article>';
    }
    echo '</div>';
};
$principalPerson = $principal[0] ?? null;
$principalPhoto = $principalPerson ? portal_image($principalPerson['foto_url'] ?? '', 'struktur', (int)$principalPerson['id']) : '';
$schoolAddress = portal_school_address($portalProfile);
$directoryOnlyTeachers = array_values(array_filter($publicTeachers, static function (array $teacher) use ($items): bool {
    $teacherName = mb_strtolower(trim((string)$teacher['nama']), 'UTF-8');
    foreach ($items as $item) if (mb_strtolower(trim((string)$item['nama']), 'UTF-8') === $teacherName) return false;
    return true;
}));

require __DIR__ . '/_header.php';
?>
<section class="structure-page-hero">
    <div class="wrap structure-hero-inner">
        <span class="structure-school-mark">
            <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt=""></span>
        <span class="eyebrow">Tata kelola sekolah</span>
        <h1>Struktur Organisasi</h1>
        <p><?= e($portalProfile['nama']) ?></p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="structure-intro-band" aria-labelledby="structureIntroTitle">
    <div class="wrap">
        <span class="structure-section-tag">
            <i class="bi bi-diagram-3-fill" aria-hidden="true">

            </i> Peran dan tanggung jawab</span>
        <h2 id="structureIntroTitle">Tata Kelola Sekolah</h2>
        <p>Kenali susunan pimpinan, pendidik, tenaga kependidikan, dan unit layanan berdasarkan informasi yang telah dikonfirmasi serta diterbitkan oleh sekolah.</p>
        <div class="structure-intro-meta">
            <span><i class="bi bi-shield-check" aria-hidden="true"></i> Informasi terbit sekolah</span>
            <span><i class="bi bi-people-fill" aria-hidden="true"></i> <?= number_format(count($items) ?: count($publicTeachers), 0, ',', '.') ?> data publik</span>
        </div>
    </div>
</section>

<section class="content structure-page-content">
    <div class="wrap structure-content-wrap">
        <section class="structure-principal-feature" aria-labelledby="structurePrincipalTitle">
            <article class="structure-principal-card">
                <div class="structure-principal-photo
                <?= $principalPhoto === '' ? ' is-placeholder' : '' ?>">
                    <?php if ($principalPhoto !== ''): ?>
                        <img src="<?= e($principalPhoto) ?>" alt="Foto
                        <?= e($principalPerson['nama']) ?>,
                        <?= e($principalPerson['jabatan']) ?>" loading="eager" decoding="async">

                    <?php else: ?>
                        <div class="structure-principal-placeholder">
                            <img src="../assets/image/Fasilitas-sekolah/Guru-guru.jpg" alt="">s
                            <span>Foto pimpinan akan tampil setelah diterbitkan</span>
                        </div>
                    <?php endif; ?>
                    <span class="structure-principal-photo-label"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Kepala Sekolah</span>
                </div>
                <div class="structure-principal-copy">
                    <span class="structure-kicker">Pimpinan sekolah</span>
                    <h2 id="structurePrincipalTitle">
                        <?= $principalPerson ? e($principalPerson['nama']) : 'Kepala Sekolah' ?>
                    </h2>
                    <p class="structure-principal-role"><?= $principalPerson ? e($principalPerson['jabatan']) : 'Informasi nama dan foto menunggu persetujuan publikasi sekolah.' ?></p>
                    <p>Kepala sekolah memimpin penyelenggaraan pendidikan, mengoordinasikan layanan sekolah, serta bekerja bersama pendidik, tenaga kependidikan, peserta didik, dan keluarga.</p>
                    <a class="text-link" href="profil.php#kepala-sekolah">Tentang pimpinan sekolah
                        <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </article>
        </section>

        <section class="structure-about-school" aria-labelledby="structureAboutSchoolTitle">
            <div class="structure-about-school-mark">
                <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang sekolah">
            </div>
            <div class="structure-about-school-copy">
                <span class="structure-kicker">Tentang sekolah</span>
                <h2 id="structureAboutSchoolTitle">
                    <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>
                </h2>
                <p>Informasi tata kelola sekolah disusun untuk membantu masyarakat mengenali peran pimpinan, pendidik, dan tenaga kependidikan.</p>
                <div class="structure-school-facts">
                    <span><strong>NPSN</strong>
                        <?= e($portalProfile['npsn'] ?: 'Belum tercatat') ?></span>
                    <span><strong>Jenjang</strong>
                        <?= e($portalProfile['jenjang'] ?: 'Sekolah Menengah Pertama') ?></span>
                    <span><strong>Lokasi</strong>
                        <?= e($schoolAddress ?: 'Informasi alamat belum tersedia') ?>
                    </span>
                </div>
                <a class="text-link" href="profil.php">Baca profil sekolah
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
        <?php if (!$items && $publicTeachers): ?>
            <section class="structure-panel" aria-labelledby="structureStaffTitle">
                <div class="structure-panel-heading">
                    <span class="structure-heading-icon">
                        <i class="bi bi-people-fill" aria-hidden="true"></i></span>
                    <div><span class="structure-kicker">Pendidik dan tenaga kependidikan</span>
                        <h2 id="structureStaffTitle">Guru &amp; Tenaga Kependidikan</h2>
                        <p>Profil guru aktif yang telah disetujui untuk ditampilkan kepada masyarakat.</p>
                    </div>
                </div>
                <?php
                $teacherCards = array_map(static function (array $teacher): array {
                    $photo = (string)($teacher['foto'] ?? '');
                    $photoSrc = preg_match('~^uploads/guru/[a-f0-9-]+\.(?:jpg|png|webp)$~i', $photo)
                        ? '../media/guru.php?id=' . (int)$teacher['id']
                        : '';
                    return ['id' => (int)$teacher['id'], 'nama' => $teacher['nama'], 'jabatan' => 'Tenaga Pendidik', 'photo_src' => $photoSrc];
                }, $publicTeachers);
                $renderPeople($teacherCards, 'structure-staff-grid');
                ?>
            </section>
            <div class="structure-page-notice" role="status"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <div><strong>Bagan organisasi belum diterbitkan</strong>
                    <p>Profil guru yang tampil berasal dari direktori publik. Susunan pimpinan, unit kerja, dan hubungan antarjabatan akan muncul setelah disahkan dan diterbitkan melalui data Struktur Organisasi.</p>
                </div>
            </div>
        <?php elseif (!$structureReady || !$items): ?>
            <div class="structure-empty-state" role="status">
                <div class="structure-empty-copy">
                    <span class="structure-empty-state-icon">
                        <i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
                    <span class="structure-kicker">Informasi organisasi</span>
                    <h2><?= $structureReady ? 'Susunan organisasi sedang disiapkan' : 'Data struktur belum tersedia' ?></h2>
                    <p><?= $structureReady
                            ? 'Sekolah belum menerbitkan data struktur organisasi. Profil pimpinan, guru, tenaga kependidikan, unit, dan bagan akan muncul di sini setelah informasinya dikonfirmasi.'
                            : 'Layanan struktur belum siap dimuat. Informasi organisasi akan tampil setelah pengelola menyiapkan layanan dan menerbitkan datanya.' ?></p>
                    <a class="btn-primary" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="structure-empty-diagram" role="group" aria-label="Gambaran umum fungsi organisasi sekolah, bukan susunan resmi">
                    <span class="structure-diagram-caption">Susunan dari pimpinan hingga pendidik</span>
                    <ol class="structure-pending-hierarchy">
                        <li><span class="structure-hierarchy-icon"><i class="bi bi-person-badge-fill" aria-hidden="true"></i>
                            </span>
                            <div>
                                <strong>Kepala Sekolah</strong>
                                <small>Nama dan foto menunggu publikasi</small>
                            </div>
                        </li>
                        <li>
                            <span class="structure-hierarchy-icon">
                                <i class="bi bi-person-badge" aria-hidden="true"></i>
                            </span>
                            <div>
                                <strong>Wakil Kepala</strong>
                                <small>Nama dan bidang tugas menunggu publikasi</small>
                            </div>
                        </li>
                        <li><span class="structure-hierarchy-icon">
                                <i class="bi bi-clipboard2-check-fill" aria-hidden="true"></i>
                            </span>
                            <div>
                                <strong>Staf &amp; Tenaga Kependidikan</strong>
                                <small>Personel layanan sekolah menunggu publikasi</small>
                            </div>
                        </li>
                        <li><span class="structure-hierarchy-icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span>
                            <div><strong>Guru</strong><small>Daftar guru mengikuti persetujuan publikasi</small></div>
                        </li>
                    </ol>
                    <p class="structure-diagram-note">Bagan ini menunjukkan urutan kategori yang akan diisi. Nama personel, foto, bidang tugas, dan hubungan jabatan hanya tampil setelah disahkan sekolah.</p>
                </div>
                <div class="structure-publication-steps">
                    <h3>Bagaimana struktur resmi ditampilkan?</h3>
                    <ol>
                        <li><strong>Verifikasi:</strong> sekolah memastikan nama, jabatan, dan unit kerja.</li>
                        <li><strong>Persetujuan:</strong> hubungan koordinasi diperiksa oleh pengelola yang berwenang.</li>
                        <li><strong>Publikasi:</strong> data yang telah disetujui muncul pada bagan dan direktori ini.</li>
                    </ol>
                    <a class="text-link" href="guru.php">Lihat direktori guru yang telah disetujui <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            <?php else: ?>

                <section class="structure-panel" aria-labelledby="structureLeadersTitle">
                    <div class="structure-panel-heading">
                        <span class="structure-heading-icon"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></span>
                        <div><span class="structure-kicker">Lanjutan kepemimpinan</span>
                            <h2 id="structureLeadersTitle">Wakil Kepala &amp; Koordinator</h2>
                            <p>Susunan berikutnya ditampilkan menurut data yang telah diterbitkan sekolah.</p>
                        </div>
                    </div>
                    <?php $renderPeople(array_merge($vicePrincipals, $leaders), 'structure-leaders-grid'); ?>
                </section>

                <section class="structure-panel" aria-labelledby="structureStaffTitle">
                    <div class="structure-panel-heading">
                        <span class="structure-heading-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
                        <div><span class="structure-kicker">Layanan pendukung sekolah</span>
                            <h2 id="structureStaffTitle">Staf &amp; Tenaga Kependidikan</h2>
                            <p>Personel pendukung ditampilkan sesuai jabatan yang telah diterbitkan sekolah.</p>
                        </div>
                    </div>
                    <?php $renderPeople($staff, 'structure-staff-grid'); ?>
                </section>

                <section class="structure-panel" aria-labelledby="structureTeachersTitle">
                    <div class="structure-panel-heading">
                        <span class="structure-heading-icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span>
                        <div><span class="structure-kicker">Pendidik</span>
                            <h2 id="structureTeachersTitle">Guru</h2>
                            <p>Guru aktif yang telah menyetujui profil publiknya, disusun setelah pimpinan dan staf pendukung.</p>
                        </div>
                    </div>
                    <?php $teacherCards = array_map(static function (array $teacher): array {
                        $photo = (string)($teacher['foto'] ?? '');
                        $photoSrc = preg_match('~^uploads/guru/[a-f0-9-]+\.(?:jpg|png|webp)$~i', $photo) ? '../media/guru.php?id=' . (int)$teacher['id'] : '';
                        return ['id' => (int)$teacher['id'], 'nama' => $teacher['nama'], 'jabatan' => 'Guru', 'photo_src' => $photoSrc];
                    }, $directoryOnlyTeachers);
                    $renderPeople(array_merge($teachersFromStructure, $teacherCards), 'structure-staff-grid'); ?>
                </section>

                <section class="structure-panel" aria-labelledby="structureUnitsTitle">
                    <div class="structure-panel-heading">
                        <span class="structure-heading-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
                        <div><span class="structure-kicker">Layanan pendukung</span>
                            <h2 id="structureUnitsTitle">Unit &amp; Layanan Sekolah</h2>
                            <p>Unit kerja yang membantu kegiatan administrasi dan layanan pendidikan.</p>
                        </div>
                    </div>
                    <?php if ($units): ?>
                        <div class="structure-units-grid">
                            <?php foreach ($units as $unit): ?>
                                <article class="structure-unit-card"><span><i class="bi bi-building" aria-hidden="true"></i></span>
                                    <div>
                                        <h3><?= e($unit['nama']) ?></h3>
                                        <p><?= e($unit['jabatan']) ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="structure-empty"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                            <p>Daftar unit layanan belum diterbitkan secara terpisah oleh sekolah.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="structure-chart-panel" aria-labelledby="structureChartTitle">
                    <div class="structure-chart-heading"><span class="structure-heading-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
                        <div><span class="structure-kicker">Susunan dan koordinasi</span>
                            <h2 id="structureChartTitle">Bagan Organisasi</h2>
                            <p>Bagan mengikuti hubungan jabatan yang disahkan sekolah.</p>
                        </div>
                    </div>
                    <?php if (!$hasHierarchy): ?>
                        <div class="structure-chart-empty"><i class="bi bi-diagram-2" aria-hidden="true"></i><strong>Hubungan antarjabatan belum tersedia</strong>
                            <p>Data portal saat ini menyimpan nama dan jabatan, tetapi belum menyimpan relasi atasan dan unit. Bagan akan ditampilkan setelah hubungan tersebut ditetapkan dan diterbitkan.</p>
                        </div>
                    <?php elseif ($items): ?>
                        <?php
                        $childrenByParent = [];
                        foreach ($items as $person) {
                            $parent = (int)($person['induk_id'] ?? 0);
                            $childrenByParent[$parent][] = $person;
                        }
                        $visitedStructureIds = [];
                        $renderTree = static function (int $parentId = 0, int $depth = 0) use (&$renderTree, &$visitedStructureIds, $childrenByParent): void {
                            $children = $childrenByParent[$parentId] ?? [];
                            if (!$children || $depth > 8) return;
                            echo '<ul class="structure-tree-level structure-tree-level-' . $depth . '">';
                            foreach ($children as $person) {
                                $personId = (int)$person['id'];
                                if (isset($visitedStructureIds[$personId])) continue;
                                $visitedStructureIds[$personId] = true;
                                echo '<li><article class="structure-tree-node"><span class="structure-tree-node-icon"><i class="bi bi-person-fill" aria-hidden="true"></i></span><strong>' . e($person['jabatan']) . '</strong><span>' . e($person['nama']) . '</span></article>';
                                $renderTree($personId, $depth + 1);
                                echo '</li>';
                            }
                            echo '</ul>';
                        };
                        $renderTree();
                        if (count($visitedStructureIds) < count($items)): ?>
                            <p class="structure-tree-note">Sebagian data belum terhubung ke bagan utama. Periksa kembali atasan langsung dan status publikasinya.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="structure-chart-empty"><i class="bi bi-diagram-2" aria-hidden="true"></i><strong>Bagan belum memiliki data terbit</strong>
                            <p>Susunan bagan akan tampil setelah nama, jabatan, dan hubungan organisasinya disetujui.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="structure-closing-band" aria-labelledby="structureClosingTitle">
                    <span class="structure-closing-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <span class="structure-kicker">Bersama membangun sekolah</span>
                    <h2 id="structureClosingTitle">Kolaborasi untuk layanan pendidikan</h2>
                    <p>Setiap peran dalam organisasi sekolah mendukung kegiatan belajar dan pelayanan kepada peserta didik serta masyarakat.</p>
                    <a class="btn-light" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </section>
            <?php endif; ?>
            </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
