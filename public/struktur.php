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

$leaders = [];
$staff = [];
$units = [];
foreach ($items as $item) {
    $title = mb_strtolower(trim((string)$item['jabatan']), 'UTF-8');
    $category = mb_strtolower(trim((string)($item['kategori'] ?? '')), 'UTF-8');
    $type = mb_strtolower(trim((string)($item['jenis'] ?? '')), 'UTF-8');
    if ($type === 'unit' || preg_match('/\b(unit|layanan)\b/u', $category)) {
        $units[] = $item;
    } elseif (preg_match('/\b(kepala|pimpinan|wakil kepala|pelaksana tugas kepala)\b/u', $title)) {
        $leaders[] = $item;
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

require __DIR__ . '/_header.php';
?>
<section class="structure-page-hero">
    <div class="wrap structure-hero-inner">
        <span class="structure-school-mark"><img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt=""></span>
        <span class="eyebrow">Tata kelola sekolah</span>
        <h1>Struktur Organisasi</h1>
        <p><?= e($portalProfile['nama']) ?></p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="structure-intro-band" aria-labelledby="structureIntroTitle">
    <div class="wrap">
        <span class="structure-section-tag"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i> Peran dan tanggung jawab</span>
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
        <?php if (!$items && $publicTeachers): ?>
            <section class="structure-panel" aria-labelledby="structureStaffTitle">
                <div class="structure-panel-heading">
                    <span class="structure-heading-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
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
                    <span class="structure-empty-state-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
                    <span class="structure-kicker">Informasi organisasi</span>
                    <h2><?= $structureReady ? 'Susunan organisasi sedang disiapkan' : 'Data struktur belum tersedia' ?></h2>
                    <p><?= $structureReady
                            ? 'Sekolah belum menerbitkan data struktur organisasi. Profil pimpinan, guru, tenaga kependidikan, unit, dan bagan akan muncul di sini setelah informasinya dikonfirmasi.'
                            : 'Layanan struktur belum siap dimuat. Informasi organisasi akan tampil setelah pengelola menyiapkan layanan dan menerbitkan datanya.' ?></p>
                    <a class="btn-primary" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="structure-empty-diagram" aria-hidden="true">
                    <span class="structure-diagram-caption">Bagan organisasi</span>
                    <div class="structure-diagram-node structure-diagram-root"><i class="bi bi-building-fill"></i><span>Susunan sekolah</span><small>Menunggu publikasi</small></div>
                    <span class="structure-diagram-stem"></span>
                    <div class="structure-diagram-branches">
                        <div class="structure-diagram-node"><i class="bi bi-person-badge-fill"></i><span>Pimpinan</span><small>Data belum tersedia</small></div>
                        <div class="structure-diagram-node"><i class="bi bi-people-fill"></i><span>Pendidik</span><small>Data belum tersedia</small></div>
                        <div class="structure-diagram-node"><i class="bi bi-grid-1x2-fill"></i><span>Unit layanan</span><small>Data belum tersedia</small></div>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <section class="structure-panel" aria-labelledby="structureLeadersTitle">
                <div class="structure-panel-heading">
                    <span class="structure-heading-icon"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></span>
                    <div><span class="structure-kicker">Kepemimpinan sekolah</span>
                        <h2 id="structureLeadersTitle">Pimpinan Sekolah</h2>
                        <p>Pejabat pimpinan yang telah diterbitkan oleh sekolah.</p>
                    </div>
                </div>
                <?php $renderPeople($leaders, 'structure-leaders-grid'); ?>
            </section>

            <section class="structure-panel" aria-labelledby="structureStaffTitle">
                <div class="structure-panel-heading">
                    <span class="structure-heading-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
                    <div><span class="structure-kicker">Pendidik dan tenaga kependidikan</span>
                        <h2 id="structureStaffTitle">Guru &amp; Tenaga Kependidikan</h2>
                        <p>Daftar nama ditampilkan sesuai jabatan dan urutan yang ditetapkan sekolah.</p>
                    </div>
                </div>
                <?php $renderPeople($staff, 'structure-staff-grid'); ?>
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