</main>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <section class="footer-brand-column" aria-label="Tentang portal">
            <a class="brand footer-brand" href="index.php">
                <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang SMP Negeri 4 Pirime">
                <span><?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?><small>Portal Informasi Resmi</small></span>
            </a>
            <p class="footer-copy">Informasi sekolah untuk siswa, orang tua, dan masyarakat.</p>
            <?php if (!empty($portalSocialLinks)): ?><div class="footer-social-links" aria-label="Media sosial sekolah">
                    <?php foreach ($portalSocialLinks as $socialIcon => $socialUrl): ?><a class="footer-social-button" href="<?= e($socialUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="SMP Negeri 4 Pirime di <?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?>"><i class="bi bi-<?= e($socialIcon) ?>" aria-hidden="true"></i></a><?php endforeach; ?>
                </div><?php endif; ?>
        </section>
        <nav class="footer-link-group" aria-label="Profil sekolah">
            <h2>Profil Sekolah</h2>
            <a href="profil.php">Tentang Sekolah</a><a href="sejarah.php">Sejarah</a><a href="visi-misi.php">Visi dan Misi</a><a href="struktur.php">Struktur Organisasi</a><a href="fasilitas.php">Fasilitas</a><a href="guru.php">Guru dan Tenaga Kependidikan</a>
        </nav>
        <nav class="footer-link-group" aria-label="Informasi akademik">
            <h2>Akademik</h2>
            <a href="akademik.php">Informasi Akademik</a><a href="kurikulum.php">Kurikulum</a><a href="jadwal.php">Jadwal Pelajaran</a><a href="agenda.php">Kalender Akademik</a><a href="ekstrakurikuler.php">Ekstrakurikuler</a><a href="ppdb.php">PPDB / SPMB</a><a href="cek_pendaftaran.php">Cek Status Pendaftaran</a>
        </nav>
        <nav class="footer-link-group" aria-label="Informasi dan layanan">
            <h2>Informasi & Layanan</h2>
            <a href="berita.php">Berita</a><a href="pengumuman.php">Pengumuman</a><a href="prestasi.php">Prestasi</a><a href="galeri.php">Galeri</a><a href="unduhan.php">Pusat Unduhan</a><a href="pencarian.php">Pencarian</a><a href="laporan_pengembangan.php">Laporan Pengembangan</a><a href="privasi.php">Kebijakan Privasi</a>
        </nav>
        <section class="footer-contact-group" aria-label="Kontak sekolah">
            <h2>Hubungi Sekolah</h2>
            <p><?= nl2br(e($portalProfile['alamat'] ?: 'Alamat sekolah belum diatur.')) ?></p>
            <?php $footerPhone = portal_phone_uri($portalProfile['telepon'] ?? null);
            $footerEmail = portal_email_address($portalProfile['email'] ?? null); ?>
            <?php if ($footerPhone !== ''): ?><a class="footer-contact-link" href="tel:<?= e($footerPhone) ?>"><i class="bi bi-telephone-fill" aria-hidden="true"></i><span><?= e($portalProfile['telepon']) ?></span></a><?php endif; ?>
            <?php if ($footerEmail !== ''): ?><a class="footer-contact-link" href="mailto:<?= e($footerEmail) ?>"><i class="bi bi-envelope-fill" aria-hidden="true"></i><span><?= e($footerEmail) ?></span></a><?php endif; ?>
            <a class="footer-contact-link" href="kontak.php#contactMapTitle"><i class="bi bi-map-fill" aria-hidden="true"></i><span>Peta dan petunjuk arah</span></a>
        </section>
    </div>
    <div class="footer-bottom">
        <div class="wrap footer-bottom-inner"><span>&copy; <?= date('Y') ?> <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?></span><span>Informasi yang ditampilkan telah dipublikasikan oleh sekolah.</span></div>
    </div>
</footer>
<script src="../assets/public.js?v=<?= (int)filemtime(__DIR__ . '/../assets/public.js') ?>" defer></script>
</body>

</html>