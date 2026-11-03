<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Profil Sekolah';
$activePage = 'profil';
$misiItems = preg_split('/\R+/u', trim((string)$portalProfile['misi']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$tujuanItems = preg_split('/\R+/u', trim((string)$portalProfile['tujuan']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$statusLabels = ['rancangan' => 'Rancangan, belum disahkan', 'perlu_verifikasi' => 'Perlu verifikasi sekolah', 'resmi' => 'Resmi disahkan'];
$visiStatus = (string)$portalProfile['visi_status'];
$misiStatus = (string)$portalProfile['misi_status'];
$tujuanStatus = (string)$portalProfile['tujuan_status'];
$sejarahStatus = (string)$portalProfile['sejarah_status'];
$akreditasiStatus = (string)$portalProfile['akreditasi_status'];
$addressRows = [
    ['Nama sekolah', $portalProfile['nama']],
    ['NPSN', $portalProfile['npsn']],
    ['Jenjang pendidikan', $portalProfile['jenjang']],
    ['Status sekolah', $portalProfile['status_sekolah']],
    ['Alamat jalan', $portalProfile['alamat_jalan']],
    ['Kampung / desa', $portalProfile['kampung']],
    ['Distrik', $portalProfile['distrik']],
    ['Kabupaten', $portalProfile['kabupaten']],
    ['Provinsi', $portalProfile['provinsi']],
    ['Kode pos', $portalProfile['kode_pos'] ?: 'Belum tercatat'],
    ['Naungan', $portalProfile['naungan']],
    ['Telepon sekolah', $portalProfile['telepon']],
    ['Email sekolah', $portalProfile['email']],
    ['Akreditasi', $portalProfile['akreditasi'] . ' · ' . ($statusLabels[$akreditasiStatus] ?? 'Perlu verifikasi')],
];
require __DIR__ . '/_header.php';
?>
<section class="page-hero page-hero--profile">
    <div class="wrap">
        <span class="eyebrow">Mengenal sekolah</span>
        <h1>Profil Sekolah</h1>
        <p>Identitas, arah pendidikan, dan informasi resmi <?= e($portalProfile['nama']) ?>.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content school-profile-content">
    <div class="wrap school-profile-wrap">
        <section class="school-about-card" aria-labelledby="schoolAboutTitle">
            <div class="school-about-copy">
                <span class="kicker">Tentang sekolah</span>
                <h2 id="schoolAboutTitle"><?= e($portalProfile['nama']) ?></h2>
                <div class="read-more-group" data-read-more-group>
                    <p>SMP Negeri 4 Pirime merupakan lembaga pendidikan formal tingkat Sekolah Menengah Pertama yang berstatus negeri dan berada di Distrik Pirime, Kabupaten Lanny Jaya, Provinsi Papua Pegunungan.</p>
                    <p>Sekolah melayani pendidikan bagi generasi muda di wilayah Pirime dan sekitarnya.</p>
                    <p>Lingkungan sekolah mendukung peserta didik memperoleh pengetahuan, mengembangkan keterampilan, membangun karakter, dan mempersiapkan diri untuk melanjutkan pendidikan.</p>
                    <p>Melalui pembelajaran, pembinaan karakter, dan pengembangan potensi, sekolah berupaya berkontribusi bagi kualitas sumber daya manusia di Kabupaten Lanny Jaya.</p>
                </div>
            </div>
            <aside class="school-about-visual" aria-label="Identitas SMP Negeri 4 Pirime">
                <span class="about-visual-glow" aria-hidden="true"></span>
                <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang resmi SMP Negeri 4 Pirime" loading="lazy">
                <strong>SMP Negeri 4 Pirime</strong>
                <span>Distrik Pirime · Kabupaten Lanny Jaya</span>
            </aside>
        </section>

        <section class="school-identity-card" aria-labelledby="schoolIdentityTitle">
            <div class="school-section-heading">
                <div><span class="kicker">Data satuan pendidikan</span>
                    <h2 id="schoolIdentityTitle">Identitas Sekolah</h2>
                    <p>Ringkasan identitas dan lokasi SMP Negeri 4 Pirime.</p>
                </div>
                <span class="school-identity-badge"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> NPSN <?= e($portalProfile['npsn']) ?></span>
            </div>
            <div class="school-table-scroll" role="region" aria-label="Tabel identitas sekolah" tabindex="0">
                <table class="school-identity-table">
                    <thead>
                        <tr>
                            <th scope="col">Informasi</th>
                            <th scope="col">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($addressRows as [$label, $value]): ?><tr>
                                <th scope="row"><?= e($label) ?></th>
                                <td><?php if ($label === 'Telepon sekolah' && portal_phone_uri((string)$value) !== ''): ?><a href="tel:<?= e(portal_phone_uri((string)$value)) ?>"><?= e($value) ?></a><?php elseif ($label === 'Email sekolah' && portal_email_address((string)$value) !== ''): ?><a href="mailto:<?= e(portal_email_address((string)$value)) ?>"><?= e($value) ?></a><?php else: ?><?= e($value ?: 'Belum tercatat') ?><?php endif; ?><?php if ($label === 'Akreditasi' && $akreditasiStatus !== 'terverifikasi'): ?><span class="school-data-note">Periksa sertifikat akreditasi terbaru sebelum menyatakan data ini resmi.</span><?php endif; ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <p class="school-source-note"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><span>Nama, NPSN, alamat, kampung, distrik, kabupaten, provinsi, status, dan jenjang merujuk pada <a href="https://referensi.data.kemendikdasmen.go.id/residu/satuanpendidikan/detail/60303637" target="_blank" rel="noopener noreferrer">Data Pendidikan Kemendikdasmen</a>. Kode pos belum dicantumkan karena belum tersedia pada data rujukan yang diperiksa.</span></p>
        </section>

        <section class="school-direction-grid" id="visi-misi" aria-label="Arah pendidikan sekolah">
            <article class="school-info-card school-vision-card">
                <span class="school-info-icon"><i class="bi bi-compass-fill" aria-hidden="true"></i></span>
                <div class="school-info-title">
                    <div><span class="kicker">Arah pendidikan</span>
                        <h2>Visi Sekolah</h2>
                    </div><span class="school-status <?= e($visiStatus) ?>"><?= e($statusLabels[$visiStatus] ?? 'Perlu verifikasi') ?></span>
                </div>
                <blockquote><?= nl2br(e($portalProfile['visi'] ?: 'Visi sekolah belum diisi.')) ?></blockquote>
                <?php if ($visiStatus !== 'resmi'): ?><p class="school-draft-note"><i class="bi bi-pencil-square" aria-hidden="true"></i><?= $visiStatus === 'rancangan' ? 'Teks visi ini adalah rancangan awal. Mohon cocokkan dan sahkan melalui dokumen resmi sekolah.' : 'Teks visi ini belum diverifikasi dengan dokumen resmi sekolah.' ?></p><?php endif; ?>
            </article>
            <article class="school-info-card school-mission-card">
                <span class="school-info-icon"><i class="bi bi-list-check" aria-hidden="true"></i></span>
                <div class="school-info-title">
                    <div><span class="kicker">Langkah pelaksanaan</span>
                        <h2>Misi Sekolah</h2>
                    </div><span class="school-status <?= e($misiStatus) ?>"><?= e($statusLabels[$misiStatus] ?? 'Perlu verifikasi') ?></span>
                </div>
                <?php if ($misiItems): ?><ol class="school-mission-list"><?php foreach ($misiItems as $item): ?><li><?= e(preg_replace('/^\s*\d+[.)]\s*/u', '', $item)) ?></li><?php endforeach; ?></ol><?php else: ?><p class="school-empty-copy">Misi sekolah belum diisi.</p><?php endif; ?>
                <?php if ($misiStatus !== 'resmi'): ?><p class="school-draft-note"><i class="bi bi-pencil-square" aria-hidden="true"></i><?= $misiStatus === 'rancangan' ? 'Daftar misi ini merupakan rancangan dan perlu disesuaikan dengan keputusan resmi sekolah.' : 'Daftar misi ini belum diverifikasi dengan dokumen resmi sekolah.' ?></p><?php endif; ?>
            </article>
            <article class="school-info-card school-objectives-card">
                <span class="school-info-icon"><i class="bi bi-bullseye" aria-hidden="true"></i></span>
                <div class="school-info-title">
                    <div><span class="kicker">Hasil yang dituju</span>
                        <h2>Tujuan Sekolah</h2>
                    </div><span class="school-status <?= e($tujuanStatus) ?>"><?= e($statusLabels[$tujuanStatus] ?? 'Perlu verifikasi') ?></span>
                </div>
                <?php if ($tujuanItems): ?><ul class="school-objectives-list"><?php foreach ($tujuanItems as $item): ?><li><?= e(preg_replace('/^\s*[-•]\s*/u', '', preg_replace('/^\s*\d+[.)]\s*/u', '', $item))) ?></li><?php endforeach; ?></ul><?php else: ?><p class="school-empty-copy">Tujuan sekolah belum diisi.</p><?php endif; ?>
                <?php if ($tujuanStatus !== 'resmi'): ?><p class="school-draft-note"><i class="bi bi-pencil-square" aria-hidden="true"></i><?= $tujuanStatus === 'rancangan' ? 'Daftar tujuan ini merupakan rancangan awal dan perlu disahkan sekolah.' : 'Daftar tujuan ini belum diverifikasi dengan dokumen resmi sekolah.' ?></p><?php endif; ?>
            </article>
        </section>

        <section class="school-more-section" aria-labelledby="schoolMoreTitle">
            <div class="school-section-heading">
                <div><span class="kicker">Informasi pendukung</span>
                    <h2 id="schoolMoreTitle">Kenali lingkungan sekolah</h2>
                    <p>Informasi berikut akan dilengkapi setelah data dan dokumen pendukung disetujui sekolah.</p>
                </div>
            </div>
            <div class="school-more-grid">
                <article class="school-info-card school-leadership-card" id="kepala-sekolah"><span class="school-info-icon"><i class="bi bi-person-badge-fill" aria-hidden="true"></i></span>
                    <div class="school-info-title">
                        <div><span class="kicker">Pimpinan sekolah</span>
                            <h3>Kepala Sekolah dan Guru</h3>
                        </div>
                    </div>
                    <div class="school-staff-photo-grid"><?php foreach (public_school_staff_images() as $index => $staffImage): ?><img src="<?= e($staffImage['src']) ?>" alt="<?= e($staffImage['alt']) ?>" loading="lazy" decoding="async"><?php endforeach; ?></div>
                    <p class="school-empty-copy">Foto bersumber dari folder aset sambutan sekolah. Nama serta jabatan pada foto menunggu konfirmasi sebelum dicantumkan.</p><a class="text-link" href="guru.php">Lihat direktori guru <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </article>
                <article class="school-info-card" id="struktur-organisasi"><span class="school-info-icon"><i class="bi bi-diagram-3-fill" aria-hidden="true"></i></span>
                    <div class="school-info-title">
                        <div><span class="kicker">Tata kelola</span>
                            <h3>Struktur Organisasi</h3>
                        </div>
                    </div>
                    <p class="school-empty-copy">Bagan organisasi akan dimuat setelah susunan tugas dan pejabat sekolah dikonfirmasi.</p>
                </article>
                <article class="school-info-card" id="sarana-prasarana"><span class="school-info-icon"><i class="bi bi-buildings-fill" aria-hidden="true"></i></span>
                    <div class="school-info-title">
                        <div><span class="kicker">Fasilitas belajar</span>
                            <h3>Sarana dan Prasarana</h3>
                        </div>
                    </div>
                    <p class="school-empty-copy">Daftar fasilitas dan dokumentasinya akan ditambahkan setelah inventaris sekolah diperiksa.</p>
                </article>
            </div>
        </section>

        <section class="school-history-card" aria-labelledby="schoolHistoryTitle">
            <div class="school-history-icon"><i class="bi bi-book-half" aria-hidden="true"></i></div>
            <div>
                <div class="school-history-heading">
                    <div><span class="kicker">Perjalanan sekolah</span>
                        <h2 id="schoolHistoryTitle">Sejarah Singkat</h2>
                    </div><span class="school-status <?= e($sejarahStatus) ?>"><?= e($statusLabels[$sejarahStatus] ?? 'Perlu verifikasi') ?></span>
                </div>
                <?php if (trim((string)$portalProfile['sejarah']) !== '' && $sejarahStatus === 'resmi'): ?><div class="school-history-copy"><?= nl2br(e($portalProfile['sejarah'])) ?></div>
                <?php elseif (trim((string)$portalProfile['sejarah']) !== ''): ?><p class="school-empty-copy">Ada riwayat yang tersimpan, tetapi belum ditayangkan karena menunggu pencocokan dengan SK pendirian, arsip sekolah, atau keterangan resmi Kepala Sekolah.</p>
                <?php else: ?><p class="school-empty-copy">Sejarah berdirinya SMP Negeri 4 Pirime akan ditambahkan setelah tahun pendirian dan riwayat sekolah dicocokkan dengan SK pendirian, arsip sekolah, atau keterangan resmi Kepala Sekolah. Informasi sejarah tidak dibuat berdasarkan perkiraan.</p><?php endif; ?>
            </div>
        </section>

        <section class="school-profile-contact" aria-label="Kontak dan lokasi sekolah">
            <div><span class="kicker">Perlu informasi lebih lanjut?</span><strong>Hubungi SMP Negeri 4 Pirime</strong></div>
            <a href="kontak.php"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i> Hubungi Sekolah</a>
        </section>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>