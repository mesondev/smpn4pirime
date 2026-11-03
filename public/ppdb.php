<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pageTitle = 'Informasi PPDB / SPMB';
$activePage = 'ppdb';
$periodReady = portal_schema_ready($pdo, 'ppdb_periode', [
    'tahun_ajaran',
    'judul',
    'mulai',
    'selesai',
    'status',
]);
$period = $periodReady
    ? portal_row(
        $pdo,
        "SELECT id, tahun_ajaran, judul, informasi, persyaratan, mulai, selesai
         FROM ppdb_periode
         WHERE status='Terbit' AND mulai<=CURDATE() AND selesai>=CURDATE()
         ORDER BY mulai DESC LIMIT 1"
    )
    : [];

$applicationCode = $_SESSION['ppdb_application_code'] ?? null;
$applicationKey = $_SESSION['ppdb_application_key'] ?? null;
unset($_SESSION['ppdb_application_code'], $_SESSION['ppdb_application_key']);
$formFlash = take_flash();

require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Penerimaan peserta didik baru</span>
        <h1>PPDB / SPMB</h1>
        <p>Informasi penerimaan peserta didik baru yang diterbitkan secara resmi oleh sekolah.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap">
        <?php if ($formFlash): ?>
            <div class="form-message <?= $formFlash['type'] === 'error' ? 'form-message-error' : '' ?>" role="status">
                <?= e($formFlash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($applicationCode && $applicationKey): ?>
            <div class="admission-success">
                <span class="tile-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
                <h2>Permohonan berhasil dikirim</h2>
                <p>Simpan dua informasi ini. Kunci status hanya ditampilkan sekali dan diperlukan untuk memeriksa perkembangan permohonan.</p>
                <dl>
                    <div>
                        <dt>Nomor pendaftaran</dt>
                        <dd><?= e($applicationCode) ?></dd>
                    </div>
                    <div>
                        <dt>Kunci status</dt>
                        <dd><code><?= e($applicationKey) ?></code></dd>
                    </div>
                </dl>
                <a class="btn-primary" href="cek_pendaftaran.php">Periksa status <i class="bi bi-arrow-right"></i></a>
            </div>
        <?php endif; ?>

        <?php if ($period): ?>
            <div class="admission-period" id="informasi-ppdb">
                <span class="kicker">Pendaftaran dibuka · Tahun ajaran <?= e($period['tahun_ajaran']) ?></span>
                <h2><?= e($period['judul']) ?></h2>
                <p><?= nl2br(e($period['informasi'] ?: 'Silakan isi data calon peserta didik dan wali untuk memulai permohonan.')) ?></p>
                <p class="admission-dates">
                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                    <?= e(portal_date($period['mulai'])) ?> – <?= e(portal_date($period['selesai'])) ?>
                </p>
                <div class="admission-requirements" id="persyaratan">
                    <h3>Persyaratan</h3>
                    <p><?= $period['persyaratan'] ? nl2br(e($period['persyaratan'])) : 'Persyaratan resmi belum ditambahkan oleh sekolah.' ?></p>
                </div>
            </div>

            <form id="formulir" class="public-form admission-form" action="proses_ppdb.php" method="post">
                <h2>Formulir permohonan</h2>
                <p>Data ini digunakan petugas untuk menindaklanjuti pendaftaran. Berkas asli diserahkan melalui prosedur yang ditetapkan sekolah.</p>
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="periode_id" value="<?= (int)$period['id'] ?>">

                <div class="form-honeypot" aria-hidden="true">
                    <label for="website">Jangan isi kolom ini</label>
                    <input id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-grid">
                    <label>
                        Nama calon peserta didik
                        <input name="nama_calon" maxlength="150" required autocomplete="name">
                    </label>
                    <label>
                        NISN <span>(jika sudah tersedia)</span>
                        <input name="nisn" inputmode="numeric" maxlength="20">
                    </label>
                    <label>
                        Nama orang tua / wali
                        <input name="nama_wali" maxlength="150" required autocomplete="name">
                    </label>
                    <label>
                        Nomor telepon orang tua / wali
                        <input name="telepon_wali" type="tel" maxlength="30" required autocomplete="tel">
                    </label>
                </div>

                <label class="privacy-consent">
                    <input type="checkbox" name="setuju_privasi" value="1" required>
                    <span>Saya memahami penggunaan data sesuai <a href="privasi.php">Kebijakan Privasi</a>.</span>
                </label>
                <button class="btn-primary" type="submit">Kirim permohonan <i class="bi bi-send"></i></button>
            </form>
        <?php else: ?>
            <div class="admission-closed" id="informasi-ppdb">
                <span class="tile-icon"><i class="bi bi-calendar2-x" aria-hidden="true"></i></span>
                <h2 id="formulir">Belum ada periode pendaftaran yang dibuka</h2>
                <p>Jadwal, persyaratan, dan prosedur akan ditampilkan setelah diumumkan dan diaktifkan oleh sekolah. Hubungi sekolah untuk memperoleh informasi terkini.</p>
                <div class="admission-requirements" id="persyaratan">
                    <h3>Persyaratan</h3>
                    <p>Persyaratan resmi belum tersedia karena periode pendaftaran belum dibuka.</p>
                </div>
                <a class="btn-primary" href="kontak.php">Kontak sekolah <i class="bi bi-arrow-right"></i></a>
            </div>
        <?php endif; ?>

        <div class="admission-lookup-callout">
            <div>
                <strong>Sudah mendaftar?</strong>
                <span>Siapkan nomor pendaftaran dan kunci status untuk melihat perkembangan permohonan.</span>
            </div>
            <a class="btn-light" href="cek_pendaftaran.php">Cek status <i class="bi bi-search"></i></a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>