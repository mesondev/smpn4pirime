<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$pageTitle = 'Cek Status Pendaftaran';
$activePage = 'ppdb';
$result = null;
$lookupMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $lastLookup = (int)($_SESSION['_ppdb_lookup_last'] ?? 0);
    if ($lastLookup && time() - $lastLookup < 5) {
        $lookupMessage = 'Terlalu banyak percobaan. Silakan tunggu sebentar.';
    } else {
        $_SESSION['_ppdb_lookup_last'] = time();
        $code = strtoupper(trim((string)($_POST['nomor_pendaftaran'] ?? '')));
        $key = strtoupper(trim((string)($_POST['kunci_status'] ?? '')));
        $schemaReady = portal_schema_ready($pdo, 'ppdb_pendaftar', [
            'nomor_pendaftaran',
            'kunci_status_hash',
            'status',
        ]);

        if (
            preg_match('/^PPDB\d{4}[A-F0-9]{8}$/', $code)
            && preg_match('/^[A-F0-9]{16}$/', $key)
            && $schemaReady
        ) {
            $row = portal_row(
                $pdo,
                'SELECT nomor_pendaftaran, kunci_status_hash, status, catatan_publik, dibuat_pada
                 FROM ppdb_pendaftar WHERE nomor_pendaftaran=? LIMIT 1',
                [$code]
            );

            if ($row && password_verify($key, $row['kunci_status_hash'])) {
                $result = $row;
            }
        }

        if (!$result) {
            $lookupMessage = 'Data tidak ditemukan. Periksa kembali nomor pendaftaran dan kunci status.';
        }
    }
}

require __DIR__ . '/_header.php';
?>
<section class="page-hero">
    <div class="wrap">
        <span class="eyebrow">Layanan pendaftaran</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Masukkan nomor dan kunci yang diberikan saat mengirim permohonan.</p>
        <?= portal_breadcrumb_markup($activePage, $pageTitle) ?>
    </div>
</section>

<section class="content">
    <div class="wrap admission-lookup-wrap">
        <form class="public-form admission-form" method="post">
            <h2>Lacak permohonan</h2>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <label>
                Nomor pendaftaran
                <input name="nomor_pendaftaran" required maxlength="16" autocomplete="off" placeholder="Contoh: PPDB2601A1B2C3D4">
            </label>
            <label>
                Kunci status
                <input name="kunci_status" required maxlength="16" autocomplete="off" placeholder="16 karakter">
            </label>
            <button class="btn-primary" type="submit">Tampilkan status <i class="bi bi-search"></i></button>
            <?php if ($lookupMessage !== ''): ?>
                <p class="form-message form-message-error" role="alert"><?= e($lookupMessage) ?></p>
            <?php endif; ?>
        </form>

        <?php if ($result): ?>
            <article class="admission-status-card">
                <span class="kicker">Status permohonan</span>
                <h2><?= e($result['status']) ?></h2>
                <p>Nomor pendaftaran: <strong><?= e($result['nomor_pendaftaran']) ?></strong></p>
                <p>Dikirim pada <?= e(portal_date($result['dibuat_pada'], 'd M Y')) ?>.</p>
                <?php if ($result['catatan_publik']): ?>
                    <p><?= e($result['catatan_publik']) ?></p>
                <?php endif; ?>
                <p>Untuk informasi lebih lanjut, hubungi <a href="kontak.php">sekolah</a>.</p>
            </article>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>