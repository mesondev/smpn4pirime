<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Worker email hanya dapat dijalankan melalui CLI.\n");
}
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/contact_mail.php';

if (!db_has_column($pdo, 'outbound_email_queue', 'reply_id')) {
    fwrite(STDERR, "Skema antrean email belum tersedia. Jalankan migrasi terlebih dahulu.\n");
    exit(2);
}
$limit = 20;
foreach ($argv as $argument) {
    if (preg_match('/^--limit=(\d{1,3})$/', $argument, $match)) $limit = min(100, max(1, (int)$match[1]));
}
$lock = (int)$pdo->query("SELECT GET_LOCK('sims_contact_mail_worker',0)")->fetchColumn();
if ($lock !== 1) {
    fwrite(STDOUT, "Worker lain sedang memproses antrean.\n");
    exit(0);
}
$sent = 0;
$retried = 0;
$failed = 0;
try {
    $pdo->exec("UPDATE outbound_email_queue SET status='failed',last_error='Worker berhenti saat percobaan terakhir.',locked_at=NULL WHERE status='sending' AND attempts>=max_attempts AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $pdo->exec("UPDATE pesan_kontak_balasan b JOIN outbound_email_queue q ON q.reply_id=b.id SET b.status_email='gagal' WHERE q.status='failed' AND b.status_email='menunggu'");
    for ($count = 0; $count < $limit; $count++) {
        $pdo->beginTransaction();
        $stmt = $pdo->query("SELECT id,reply_id,recipient,subject,body,attempts,max_attempts FROM outbound_email_queue WHERE ((status='queued' AND next_attempt_at<=NOW()) OR (status='sending' AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE))) AND attempts<max_attempts ORDER BY next_attempt_at,id LIMIT 1 FOR UPDATE");
        $job = $stmt->fetch();
        if (!$job) {
            $pdo->commit();
            break;
        }
        $attempt = (int)$job['attempts'] + 1;
        $claim = $pdo->prepare("UPDATE outbound_email_queue SET status='sending',attempts=?,locked_at=NOW() WHERE id=?");
        $claim->execute([$attempt, (int)$job['id']]);
        $pdo->commit();

        $delivered = send_school_contact_reply((string)$job['recipient'], (string)$job['subject'], (string)$job['body']);
        $pdo->beginTransaction();
        if ($delivered) {
            $done = $pdo->prepare("UPDATE outbound_email_queue SET status='sent',sent_at=NOW(),locked_at=NULL,last_error=NULL WHERE id=?");
            $done->execute([(int)$job['id']]);
            $reply = $pdo->prepare("UPDATE pesan_kontak_balasan SET status_email='terkirim' WHERE id=?");
            $reply->execute([(int)$job['reply_id']]);
            $sent++;
        } elseif ($attempt >= (int)$job['max_attempts']) {
            $done = $pdo->prepare("UPDATE outbound_email_queue SET status='failed',locked_at=NULL,last_error='Transport mail menolak pengiriman setelah batas percobaan.' WHERE id=?");
            $done->execute([(int)$job['id']]);
            $reply = $pdo->prepare("UPDATE pesan_kontak_balasan SET status_email='gagal' WHERE id=?");
            $reply->execute([(int)$job['reply_id']]);
            $failed++;
        } else {
            $delaySeconds = min(3600, 60 * (2 ** ($attempt - 1)));
            $done = $pdo->prepare("UPDATE outbound_email_queue SET status='queued',locked_at=NULL,next_attempt_at=DATE_ADD(NOW(),INTERVAL {$delaySeconds} SECOND),last_error='Transport mail belum menerima pengiriman; akan dicoba kembali.' WHERE id=?");
            $done->execute([(int)$job['id']]);
            $retried++;
        }
        $pdo->commit();
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    app_log('error', 'Contact mail worker failed', ['type' => get_class($exception)]);
    fwrite(STDERR, "Worker email gagal. Periksa log aplikasi.\n");
    $pdo->query("SELECT RELEASE_LOCK('sims_contact_mail_worker')");
    exit(1);
}
$pdo->query("SELECT RELEASE_LOCK('sims_contact_mail_worker')");
fwrite(STDOUT, "Email diproses: {$sent} terkirim, {$retried} dijadwalkan ulang, {$failed} gagal permanen.\n");
