<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Akademik';
$activePage = 'akademik';
$year = portal_row($pdo, 'SELECT nama,tanggal_mulai,tanggal_selesai FROM tahun_ajaran WHERE aktif=1 ORDER BY id DESC LIMIT 1');
$academicStats = portal_public_stats($pdo);
$activeClassCount = $year ? $academicStats['kelas'] : null;
$events = portal_schema_ready($pdo, 'kalender_akademik', ['id', 'judul', 'kategori', 'tanggal_mulai', 'tanggal_selesai', 'status'])
    ? portal_rows($pdo, "SELECT judul,kategori,tanggal_mulai,tanggal_selesai,deskripsi FROM kalender_akademik WHERE tampil_publik=1 AND status<>'Dibatalkan' AND COALESCE(tanggal_selesai,tanggal_mulai)>=CURDATE() ORDER BY tanggal_mulai LIMIT 4") : [];
require __DIR__ . '/_header.php'; ?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Pembelajaran dan layanan pendidikan</span>
        <h1>Informasi Akademik</h1>
        <p>Ringkasan informasi akademik SMP Negeri 4 Pirime. Data hanya ditampilkan apabila tersedia dan telah disiapkan untuk publikasi.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>
<section class="content academic-content">
    <div class="wrap">
        <div class="academic-year-banner">
            <span class="academic-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
            <div><span class="kicker">Tahun ajaran aktif</span><strong><?= e($year['nama'] ?? 'Belum ditetapkan') ?></strong>
                <?php if (!empty($year['tanggal_mulai']) && !empty($year['tanggal_selesai'])): ?><span><?= e(portal_date($year['tanggal_mulai'])) ?> – <?= e(portal_date($year['tanggal_selesai'])) ?></span><?php else: ?><span>Informasi periode belum tersedia.</span><?php endif; ?>
            </div>
            <a class="text-link" href="agenda.php">Lihat kalender <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>

        <div class="academic-grid">
            <article class="academic-card" id="kurikulum">
                <span class="academic-card-icon"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i></span><span class="kicker">Kurikulum</span>
                <h2>Program pembelajaran</h2>
                <p>Informasi kurikulum yang berlaku akan ditampilkan setelah dokumen dan penerapannya dikonfirmasi oleh sekolah.</p>
                <span class="academic-status"><i class="bi bi-info-circle" aria-hidden="true"></i> Menunggu verifikasi sekolah</span>
            </article>
            <article class="academic-card" id="peserta-didik">
                <span class="academic-card-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span><span class="kicker">Peserta didik</span>
                <h2>Data ringkasan</h2>
                <p>Portal hanya menampilkan angka agregat. Nama, nomor induk, nilai, dan data pribadi siswa tetap terlindungi.</p>
                <div class="academic-metrics">
                    <div><strong><?= $academicStats['siswa'] === null ? '—' : number_format($academicStats['siswa'], 0, ',', '.') ?></strong><span>Siswa aktif</span></div>
                    <div><strong><?= $activeClassCount === null ? '—' : number_format($activeClassCount, 0, ',', '.') ?></strong><span>Rombel aktif</span></div>
                </div>
            </article>
            <article class="academic-card" id="rombongan-belajar">
                <span class="academic-card-icon"><i class="bi bi-door-open-fill" aria-hidden="true"></i></span><span class="kicker">Rombongan belajar</span>
                <h2>Informasi kelas</h2>
                <p><?= $activeClassCount === null ? 'Data tahun ajaran aktif atau rombongan belajar belum tersedia.' : 'Terdapat ' . number_format($activeClassCount, 0, ',', '.') . ' rombongan belajar pada tahun ajaran aktif.' ?> Rincian nama kelas tidak dibuka untuk menjaga privasi peserta didik.</p>
            </article>
            <article class="academic-card" id="jadwal-pelajaran">
                <span class="academic-card-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span><span class="kicker">Jadwal pelajaran</span>
                <h2>Jadwal kelas</h2>
                <p>Jadwal akan dipublikasikan setelah sekolah mengatur persetujuan tayang. Hubungi sekolah untuk memperoleh informasi yang berlaku.</p>
                <a class="text-link" href="kontak.php">Tanyakan ke sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </article>
            <article class="academic-card" id="kalender-pendidikan">
                <span class="academic-card-icon"><i class="bi bi-calendar-event-fill" aria-hidden="true"></i></span><span class="kicker">Kalender pendidikan</span>
                <h2>Agenda mendatang</h2>
                <?php if ($events): ?><ul class="academic-event-list"><?php foreach ($events as $event): ?><li><time><?= e(portal_date($event['tanggal_mulai'])) ?></time>
                                <div><strong><?= e($event['judul']) ?></strong><span><?= e($event['kategori']) ?></span></div>
                            </li><?php endforeach; ?></ul><a class="text-link" href="agenda.php">Semua agenda <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <?php else: ?><p>Belum ada agenda mendatang yang disetujui untuk ditampilkan.</p><a class="text-link" href="agenda.php">Buka kalender akademik <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
            </article>
            <article class="academic-card" id="ekstrakurikuler">
                <span class="academic-card-icon"><i class="bi bi-stars" aria-hidden="true"></i></span><span class="kicker">Pengembangan diri</span>
                <h2>Ekstrakurikuler</h2>
                <p>Daftar kegiatan ekstrakurikuler akan ditambahkan setelah jenis kegiatan, jadwal, pembina, dan informasi pendaftarannya dikonfirmasi sekolah.</p>
                <span class="academic-status"><i class="bi bi-info-circle" aria-hidden="true"></i> Informasi belum dipublikasikan</span>
            </article>
        </div>
        <aside class="academic-contact-note"><i class="bi bi-chat-square-text-fill" aria-hidden="true"></i>
            <div><strong>Perlu informasi akademik?</strong><span>Hubungi admin sekolah untuk memperoleh keterangan terbaru.</span></div><a class="btn-primary" href="kontak.php">Hubungi sekolah</a>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>