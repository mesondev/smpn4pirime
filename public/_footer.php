</main>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div>
            <a class="brand footer-brand" href="index.php">
                <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang SMP Negeri 4 Pirime">
                <span><?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>
                    <small>Portal Informasi Resmi</small>
                </span>
            </a>
            <p class="footer-copy">Sumber informasi resmi sekolah untuk siswa, orang tua, dan masyarakat.</p>
            <div class="footer-social-links" aria-label="Media sosial sekolah">
                <?php foreach (['facebook','instagram','youtube','twitter-x','linkedin'] as $socialIcon): ?><button class="footer-social-button social-icon-button" type="button" data-social-animate aria-label="<?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?>, tautan sekolah belum diatur" title="<?= e(ucwords(str_replace('-', ' ', $socialIcon))) ?>"><i class="bi bi-<?= e($socialIcon) ?>" aria-hidden="true"></i></button><?php endforeach; ?>
            </div>
        </div>
        <div>
            <h2>Jelajahi</h2>
            <a href="profil.php">Profil Sekolah</a>
            <a href="guru.php">Direktori Guru</a>
            <a href="berita.php">Berita & Kegiatan</a>
            <a href="agenda.php">Kalender Akademik</a>
            <a href="laporan_pengembangan.php">Laporan Pengembangan Portal</a>
        </div>
        <div>
            <h2>Hubungi Sekolah</h2>
            <p><?= nl2br(e($portalProfile['alamat'] ?? 'Alamat sekolah belum diatur.')) ?></p>
            <?php if (!empty($portalProfile['telepon'])): ?><p><?= e($portalProfile['telepon']) ?></p>
            <?php endif; ?>
            <?php if (!empty($portalProfile['email'])): ?>
                <p>
                    <?= e($portalProfile['email']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="wrap">&copy;
            <?= date('Y') ?>
            <?= e($portalProfile['nama'] ?? 'SMP Negeri 4 Pirime') ?>. Informasi yang ditampilkan telah dipublikasikan oleh sekolah.</div>
    </div>
</footer>
<script src="../assets/public.js?v=<?= (int)filemtime(__DIR__ . '/../assets/public.js') ?>" defer></script>
</body>

</html>
