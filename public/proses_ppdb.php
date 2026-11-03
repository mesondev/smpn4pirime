<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('ppdb.php');
}
verify_csrf();
if (!empty($_POST['website'])) {
    redirect('ppdb.php');
}
$last = (int)($_SESSION['_ppdb_last_submit'] ?? 0);
if ($last && time() - $last < 30) {
    flash('Mohon tunggu sebentar sebelum mengirim permohonan berikutnya.', 'error');
    redirect('ppdb.php');
}
$periodId = filter_var($_POST['periode_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$name = trim((string)($_POST['nama_calon'] ?? ''));
$nisn = preg_replace('/\D+/', '', (string)($_POST['nisn'] ?? ''));
$guardian = trim((string)($_POST['nama_wali'] ?? ''));
$phone = trim((string)($_POST['telepon_wali'] ?? ''));
if (!$periodId || mb_strlen($name) < 2 || mb_strlen($name) > 150 || ($nisn !== '' && !preg_match('/^\d{8,20}$/', $nisn)) || mb_strlen($guardian) < 2 || mb_strlen($guardian) > 150 || !preg_match('/^[0-9+() .-]{7,30}$/', $phone) || ($_POST['setuju_privasi'] ?? '') !== '1') {
    flash('Periksa kembali isian formulir pendaftaran.', 'error');
    redirect('ppdb.php');
}
if (!portal_schema_ready($pdo, 'ppdb_pendaftar', ['periode_id', 'nomor_pendaftaran', 'kunci_status_hash']) || !portal_schema_ready($pdo, 'ppdb_periode', ['id', 'status', 'mulai', 'selesai'])) {
    flash('Layanan pendaftaran belum siap. Silakan hubungi sekolah.', 'error');
    redirect('ppdb.php');
}
try {
    $period = portal_row($pdo, "SELECT id FROM ppdb_periode WHERE id=? AND status='Terbit' AND mulai<=CURDATE() AND selesai>=CURDATE()", [$periodId]);
    if (!$period) {
        flash('Periode pendaftaran sudah ditutup atau belum dibuka.', 'error');
        redirect('ppdb.php');
    }
    $code = 'PPDB' . date('ym') . strtoupper(bin2hex(random_bytes(4)));
    $key = strtoupper(bin2hex(random_bytes(8)));
    $insert = $pdo->prepare('INSERT INTO ppdb_pendaftar (periode_id,nomor_pendaftaran,kunci_status_hash,nama_calon,nisn,nama_wali,telepon_wali) VALUES (?,?,?,?,?,?,?)');
    $insert->execute([$periodId, $code, password_hash($key, PASSWORD_DEFAULT), $name, $nisn !== '' ? $nisn : null, $guardian, $phone]);
    $_SESSION['_ppdb_last_submit'] = time();
    $_SESSION['ppdb_application_code'] = $code;
    $_SESSION['ppdb_application_key'] = $key;
    redirect('ppdb.php#pageContent');
} catch (PDOException $e) {
    app_log('error', 'Public admission submission failed', ['type' => get_class($e)]);
    flash('Permohonan belum dapat disimpan. Silakan coba kembali atau hubungi sekolah.', 'error');
    redirect('ppdb.php');
}
