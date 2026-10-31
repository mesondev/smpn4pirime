<?php
require_once __DIR__ . '/_common.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Laporan Pengembangan Portal';
require __DIR__ . '/_header.php'; ?>

<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Informasi portal</span>
        <h1>Laporan Pengembangan</h1>
        <p>Ringkasan layanan, fitur, dan peningkatan yang telah tersedia pada portal informasi
            <?= e($portalProfile['nama']) ?>, beserta hal yang masih perlu dikembangkan.</p>
            <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <article class="dev-report-intro">
            <div>
                <span class="kicker">Ruang lingkup laporan</span>
                <h2>Portal informasi publik sekolah</h2>
                <p>Laporan ini menjelaskan bagian portal yang dapat diakses masyarakat. Ketersediaan isi seperti berita, agenda, foto, dan informasi kontak bergantung pada data yang dimasukkan serta disetujui oleh pihak sekolah.</p>
            </div>
            <p class="dev-report-date"><strong>Diperbarui</strong><br><?= e(date('d F Y')) ?></p>
        </article>

        <section class="dev-report-section" aria-labelledby="fitur-publik">
            <span class="kicker">Yang sudah dikembangkan</span>
            <h2 id="fitur-publik">Halaman dan layanan publik</h2>
            <div class="dev-report-grid">
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-house"></i></span>
                    <h3>Beranda</h3>
                    <p>Menampilkan sambutan sekolah, pengumuman terbaru, berita pilihan, agenda mendatang, dan jalan pintas menuju informasi utama.</p><a href="index.php">Buka beranda <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-buildings"></i></span>
                    <h3>Profil sekolah</h3>
                    <p>Memuat identitas sekolah, sejarah, visi, misi, sambutan, alamat, serta kontak yang telah diatur.</p><a href="profil.php">Buka profil <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-person-badge"></i></span>
                    <h3>Direktori guru</h3>
                    <p>Menyediakan pencarian dan halaman bertahap untuk profil guru aktif yang telah diizinkan dan disetujui untuk ditampilkan.</p><a href="guru.php">Buka direktori guru <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-megaphone"></i></span>
                    <h3>Pengumuman</h3>
                    <p>Menampilkan pengumuman yang telah diterbitkan dan masih dalam masa tayang, dilengkapi pencarian judul dan halaman detail.</p><a href="pengumuman.php">Buka pengumuman <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-newspaper"></i></span>
                    <h3>Berita dan kegiatan</h3>
                    <p>Menyajikan berita sekolah dengan kategori, tanggal, ringkasan, gambar, pencarian, dan halaman untuk membaca berita secara lengkap.</p><a href="berita.php">Buka berita <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-calendar-event"></i></span>
                    <h3>Kalender akademik</h3>
                    <p>Menampilkan agenda publik berdasarkan tahun, termasuk kategori, rentang tanggal, dan deskripsi kegiatan.</p><a href="agenda.php">Buka kalender <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-images"></i></span>
                    <h3>Galeri kegiatan</h3>
                    <p>Menampilkan foto kegiatan yang telah diterbitkan, berikut keterangan dan tanggal apabila tersedia.</p><a href="galeri.php">Buka galeri <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-mortarboard"></i></span>
                    <h3>Informasi PPDB</h3>
                    <p>Memberi arahan kepada calon peserta didik dan keluarga untuk memperoleh jadwal, persyaratan, serta prosedur terbaru dari kanal resmi sekolah.</p><a href="ppdb.php">Buka informasi PPDB <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-geo-alt"></i></span>
                    <h3>Kontak dan lokasi</h3>
                    <p>Menyediakan alamat, telepon, email, jam layanan, serta peta apabila tautan peta resmi telah diatur.</p><a href="kontak.php">Buka kontak sekolah <span aria-hidden="true">→</span></a>
                </article>
            </div>
        </section>

        <section class="dev-report-section" aria-labelledby="peningkatan">
            <span class="kicker">Pengalaman dan pengelolaan</span>
            <h2 id="peningkatan">Peningkatan yang sudah diterapkan</h2>
            <div class="dev-report-columns">
                <article class="dev-report-note">
                    <h3>Tampilan responsif</h3>
                    <p>Tata letak menyesuaikan layar komputer, tablet, dan ponsel. Navigasi seluler, daftar kartu, formulir, gambar, dan bagian footer dirancang agar tetap mudah digunakan pada layar kecil.</p>
                </article>
                <article class="dev-report-note">
                    <h3>Konten terbit dan masa tayang</h3>
                    <p>Halaman publik hanya menyajikan konten yang berstatus terbit. Pengumuman mengikuti tanggal mulai dan akhir tayang, sedangkan agenda yang dibatalkan tidak ditampilkan sebagai agenda aktif.</p>
                </article>
                <article class="dev-report-note">
                    <h3>Moderasi dan privasi</h3>
                    <p>Konten seperti berita, galeri, pengumuman, dan agenda perlu melalui pengelolaan sebelum tampil. Profil guru dibatasi pada profil aktif yang disetujui. Foto siswa sebaiknya diterbitkan hanya setelah persetujuan publikasi dipastikan.</p>
                </article>
                <article class="dev-report-note">
                    <h3>Keterbacaan dan akses</h3>
                    <p>Halaman menggunakan navigasi yang konsisten, label untuk elemen penting, dukungan fokus papan ketik, dan pengaturan gerak yang menghormati preferensi pengguna.</p>
                </article>
            </div>
        </section>

        <section class="dev-report-section" aria-labelledby="catatan-fitur">
            <span class="kicker">Status dan batas layanan</span>
            <h2 id="catatan-fitur">Hal yang perlu diketahui pengunjung</h2>
            <div class="dev-report-callout">
                <p><strong>Isi portal mengikuti pembaruan sekolah.</strong> Jika suatu bagian belum berisi data, artinya konten belum tersedia atau belum diterbitkan oleh pengelola.</p>
                <p><strong>PPDB daring belum tersedia pada halaman ini.</strong> Saat ini halaman PPDB berisi informasi dan rujukan kontak; jadwal, persyaratan, dan prosedur tahun berjalan perlu dikonfirmasi langsung kepada sekolah.</p>
                <p><strong>Portal ini adalah kanal informasi.</strong> Pengunjung tidak diminta mengirim berkas pendaftaran atau data pribadi siswa melalui halaman PPDB.</p>
            </div>
        </section>

        <section class="dev-report-section" aria-labelledby="lanjutan">
            <span class="kicker">Rekomendasi pengembangan</span>
            <h2 id="lanjutan">Prioritas berikutnya</h2>
            <ol class="dev-report-roadmap">
                <li><strong>Lengkapi dan rutin perbarui konten.</strong> Tetapkan penanggung jawab serta jadwal pembaruan profil, berita, pengumuman, agenda, galeri, dan kontak.</li>
                <li><strong>Siapkan layanan PPDB sesuai kebijakan sekolah.</strong> Publikasikan jadwal dan persyaratan yang telah diverifikasi; bila pendaftaran daring akan dibuat, siapkan alur, perlindungan data, dan petunjuk bantuan terlebih dahulu.</li>
                <li><strong>Perkuat aksesibilitas.</strong> Tinjau kontras warna, ukuran teks, teks alternatif gambar, navigasi papan ketik, dan penggunaan pembaca layar bersama pengguna.</li>
                <li><strong>Perluas pemeriksaan sebelum rilis.</strong> Uji semua halaman dan alur berdasarkan peran pengguna pada perangkat ponsel dan komputer, lalu dokumentasikan hasil dan tindak lanjutnya.</li>
                <li><strong>Jaga keberlanjutan layanan.</strong> Buat jadwal pencadangan dan uji pemulihan berkala, pantau tautan atau informasi kedaluwarsa, serta siapkan prosedur penanganan gangguan.</li>
                <li><strong>Tingkatkan transparansi pengelolaan.</strong> Pertimbangkan riwayat perubahan dan tanggal pembaruan konten agar informasi lama dapat dikenali dan ditinjau.</li>
            </ol>
        </section>

        <p class="dev-report-contact">Ada informasi yang perlu diperbarui? Silakan sampaikan melalui <a href="kontak.php">halaman kontak sekolah</a>.</p>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>