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

        <section class="dev-report-section" aria-labelledby="layanan-tambahan">
            <span class="kicker">Layanan tambahan</span>
            <h2 id="layanan-tambahan">Halaman publik yang baru dikembangkan</h2>
            <div class="dev-report-grid">
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-stars"></i></span>
                    <h3>Ekstrakurikuler</h3>
                    <p>Daftar kegiatan dapat diterbitkan dengan keterangan pembina, jadwal, kategori, dan persyaratan setelah datanya disetujui sekolah.</p><a href="ekstrakurikuler.php">Buka kegiatan <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-diagram-3"></i></span>
                    <h3>Struktur organisasi</h3>
                    <p>Menampilkan nama dan jabatan yang telah dipublikasikan oleh sekolah.</p><a href="struktur.php">Buka struktur <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-buildings"></i></span>
                    <h3>Fasilitas sekolah</h3>
                    <p>Daftar sarana dan prasarana sekolah yang telah diperiksa.</p><a href="fasilitas.php">Buka fasilitas <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-award"></i></span>
                    <h3>Prestasi terverifikasi</h3>
                    <p>Prestasi hanya tampil apabila bukti telah diverifikasi dan izin publikasi tercatat.</p><a href="prestasi.php">Buka prestasi <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-file-earmark-arrow-down"></i></span>
                    <h3>Pusat unduhan</h3>
                    <p>Dokumen PDF yang disetujui dapat diunduh melalui pemeriksaan status publik dan lokasi file privat.</p><a href="unduhan.php">Buka unduhan <span aria-hidden="true">→</span></a>
                </article>
                <article class="dev-report-card"><span class="tile-icon"><i class="bi bi-shield-lock"></i></span>
                    <h3>Kebijakan privasi</h3>
                    <p>Menjelaskan penggunaan data formulir kontak dan pendaftaran.</p><a href="privasi.php">Baca kebijakan <span aria-hidden="true">→</span></a>
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
                <p><strong>PPDB daring hanya dibuka untuk periode resmi.</strong> Formulir aktif jika sekolah mengisi dan menerbitkan periode pendaftaran. Pendaftar menerima nomor dan kunci rahasia untuk mengecek status.</p>
                <p><strong>Dokumen pendaftaran tidak diunggah lewat portal.</strong> Berkas diserahkan mengikuti prosedur sekolah. Hasil seleksi tidak ditampilkan dalam daftar terbuka untuk melindungi data calon siswa.</p>
                <p><strong>Konten dinamis perlu diisi oleh pengelola.</strong> Terapkan migrasi <code>database/migrations/2026_11_02_public_services.sql</code> terlebih dahulu. Setelah itu, data kegiatan, fasilitas, struktur, prestasi, dokumen, tautan sosial, dan periode PPDB perlu dimasukkan serta disetujui melalui pengelolaan database.</p>
            </div>
        </section>

        <section class="dev-report-section" aria-labelledby="lanjutan">
            <span class="kicker">Rekomendasi pengembangan</span>
            <h2 id="lanjutan">Prioritas berikutnya</h2>
            <ol class="dev-report-roadmap">
                <li><strong>Lengkapi dan rutin perbarui konten.</strong> Tetapkan penanggung jawab serta jadwal pembaruan profil, berita, pengumuman, agenda, galeri, dan kontak.</li>
                <li><strong>Isi dan tinjau konten layanan baru.</strong> Masukkan data ekstrakurikuler, fasilitas, struktur, prestasi yang terverifikasi, dokumen PDF, dan tautan media sosial resmi.</li>
                <li><strong>Aktifkan PPDB sesuai kebijakan sekolah.</strong> Terapkan migrasi layanan, masukkan periode dan persyaratan resmi, lakukan uji terbatas, serta pastikan petugas dapat menangani permohonan masuk.</li>
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