<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('kontak.php');
}
verify_csrf();
if (!empty($_POST['website'])) {
    redirect('kontak.php');
}
$last = (int)($_SESSION['_public_message_last'] ?? 0);
if ($last && time() - $last < 60) {
    flash('Mohon tunggu sebentar sebelum mengirim pesan baru.', 'error');
    redirect('kontak.php#messageFormTitle');
}
$name = trim((string)($_POST['nama'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['telepon'] ?? ''));
$category = trim((string)($_POST['kategori'] ?? 'Pertanyaan umum'));
$subject = trim((string)($_POST['subjek'] ?? ''));
$message = trim((string)($_POST['isi'] ?? ''));
$categories = ['Pertanyaan umum', 'Informasi akademik', 'PPDB / SPMB', 'Kerja sama', 'Lainnya'];
if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180 || ($phone !== '' && !preg_match('/^[0-9+() .-]{7,30}$/', $phone)) || !in_array($category, $categories, true) || mb_strlen($subject) < 3 || mb_strlen($subject) > 180 || mb_strlen($message) < 5 || mb_strlen($message) > 3000 || ($_POST['setuju_privasi'] ?? '') !== '1') {
    flash('Periksa kembali data pesan Anda.', 'error');
    redirect('kontak.php#messageFormTitle');
}
if (!portal_schema_ready($pdo, 'pesan_kontak', ['nama_pengirim', 'email_pengirim', 'subjek']) || !portal_schema_ready($pdo, 'pesan_kontak_balasan', ['pesan_id', 'pengirim', 'isi'])) {
    flash('Formulir pesan belum siap. Silakan hubungi sekolah melalui telepon atau email.', 'error');
    redirect('kontak.php#messageFormTitle');
}
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO pesan_kontak (nama_pengirim,email_pengirim,telepon_pengirim,kategori,subjek,status) VALUES (?,?,?,?,?,'baru')");
    $stmt->execute([$name, $email, $phone !== '' ? $phone : null, $category, $subject]);
    $messageId = (int)$pdo->lastInsertId();
    $reply = $pdo->prepare("INSERT INTO pesan_kontak_balasan (pesan_id,pengirim,isi,status_email) VALUES (?,'pengunjung',?,'tidak_berlaku')");
    $reply->execute([$messageId, $message]);
    $pdo->commit();
    $_SESSION['_public_message_last'] = time();
    flash('Pesan Anda sudah diterima. Petugas sekolah akan menindaklanjuti melalui alamat email yang Anda berikan.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    app_log('error', 'Public contact message could not be saved', ['type' => get_class($e)]);
    flash('Pesan belum dapat disimpan. Silakan coba lagi atau hubungi sekolah.', 'error');
}
redirect('kontak.php#messageFormTitle');
