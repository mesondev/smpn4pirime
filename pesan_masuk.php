<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/config/database.php';
require_module_access('pesan_masuk');
require_once __DIR__ . '/includes/contact_mail.php';

// State halaman dipakai untuk menampilkan kesalahan dan percakapan terpilih.
$error = '';
$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$thread = null;
$replies = [];

// Proses tindakan admin sebelum mengambil data untuk ditampilkan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $selectedId = filter_input(INPUT_POST, 'pesan_id', FILTER_VALIDATE_INT) ?: 0;
    $action = (string) ($_POST['action'] ?? '');

    // Pastikan percakapan yang akan diubah benar-benar ada.
    $findThread = $pdo->prepare('SELECT * FROM pesan_kontak WHERE id = ?');
    $findThread->execute([$selectedId]);
    $target = $findThread->fetch();

    if (!$target) {
        $error = 'Percakapan tidak ditemukan.';
    } elseif ($action === 'balas') {
        // Validasi balasan sebelum menyimpan atau mengirim email.
        $reply = trim((string) ($_POST['balasan'] ?? ''));

        if ($reply === '' || mb_strlen($reply, 'UTF-8') > 10000) {
            $error = 'Balasan wajib diisi dan maksimal 10.000 karakter.';
        } else {
            try {
                $pdo->beginTransaction();
                $insertReply = $pdo->prepare(
                    "INSERT INTO pesan_kontak_balasan
                        (pesan_id, pengirim, admin_user_id, isi, status_email)
                     VALUES (?, 'admin', ?, ?, 'menunggu')"
                );
                $insertReply->execute([$selectedId, (int) ($_SESSION['user_id'] ?? 0), $reply]);
                $replyId = (int) $pdo->lastInsertId();
                $updateThread = $pdo->prepare("UPDATE pesan_kontak SET status='dibalas', dibaca_admin=1 WHERE id=?");
                $updateThread->execute([$selectedId]);

                $emailBody = "Halo {$target['nama_pengirim']},\r\n\r\n"
                    . "Admin Utama SMP Negeri 4 Pirime membalas pesan Anda tentang: {$target['subjek']}\r\n\r\n"
                    . $reply . "\r\n\r\nSalam,\r\nAdmin Utama\r\nSMP Negeri 4 Pirime\r\n"
                    . "Telepon: 08134893823\r\nEmail: smpn4pirime@gmail.com\r\n";
                $queue = $pdo->prepare('INSERT INTO outbound_email_queue (reply_id,recipient,subject,body) VALUES (?,?,?,?)');
                $queue->execute([$replyId, (string)$target['email_pengirim'], 'Balasan sekolah: ' . (string)$target['subjek'], $emailBody]);
                record_activity($pdo, 'membalas pesan kontak publik', 'pesan_kontak', $selectedId, 'Balasan tersimpan dan masuk antrean email.');
                $pdo->commit();
                flash('Balasan tersimpan dan masuk antrean. Pengiriman email berjalan melalui worker XAMPP; statusnya diperbarui setelah diproses.', 'success');
                redirect('index.php?module=pesan_masuk&id=' . $selectedId);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                app_log('error', 'Contact reply queue insert failed', ['type' => get_class($exception)]);
                $error = 'Balasan gagal disimpan ke antrean. Silakan coba kembali.';
            }
        }
    } elseif (in_array($action, ['tutup', 'buka_kembali'], true)) {
        // Ubah status percakapan tanpa menghapus isi pesan atau balasan.
        $status = $action === 'tutup' ? 'ditutup' : 'dibalas';
        $updateThread = $pdo->prepare(
            'UPDATE pesan_kontak SET status = ?, dibaca_admin = 1 WHERE id = ?'
        );
        $updateThread->execute([$status, $selectedId]);

        record_activity(
            $pdo,
            $action === 'tutup'
                ? 'menutup pesan kontak publik'
                : 'membuka kembali pesan kontak publik',
            'pesan_kontak',
            $selectedId
        );

        flash(
            $action === 'tutup' ? 'Percakapan ditutup.' : 'Percakapan dibuka kembali.',
            'success'
        );
        redirect('index.php?module=pesan_masuk&id=' . $selectedId);
    } else {
        $error = 'Tindakan pesan tidak valid.';
    }
}

// Batasi filter ke status yang didukung aplikasi.
$statusFilter = (string) ($_GET['status'] ?? 'aktif');
if (!in_array($statusFilter, ['aktif', 'baru', 'dibalas', 'ditutup', 'semua'], true)) {
    $statusFilter = 'aktif';
}

// Pilih klausa status dari daftar tetap agar nilai URL tidak masuk ke SQL.
$where = match ($statusFilter) {
    'baru' => "WHERE m.status = 'baru'",
    'dibalas' => "WHERE m.status = 'dibalas'",
    'ditutup' => "WHERE m.status = 'ditutup'",
    'aktif' => "WHERE m.status <> 'ditutup'",
    default => '',
};

// Ambil paling banyak 50 percakapan dan cuplikan balasan terakhirnya.
$threadsStmt = $pdo->query(
    "SELECT
        m.*,
        (SELECT b.isi
         FROM pesan_kontak_balasan b
         WHERE b.pesan_id = m.id
         ORDER BY b.id DESC
         LIMIT 1) AS cuplikan,
        (SELECT b.pengirim
         FROM pesan_kontak_balasan b
         WHERE b.pesan_id = m.id
         ORDER BY b.id DESC
         LIMIT 1) AS pengirim_terakhir
     FROM pesan_kontak m
     $where
     ORDER BY m.dibaca_admin ASC, m.diperbarui_pada DESC
     LIMIT 50"
);
$threads = $threadsStmt->fetchAll();

// Muat isi percakapan dan tandai pesan serta notifikasi sebagai sudah dibaca.
if ($selectedId > 0) {
    $threadStmt = $pdo->prepare('SELECT * FROM pesan_kontak WHERE id = ?');
    $threadStmt->execute([$selectedId]);
    $thread = $threadStmt->fetch() ?: null;

    if ($thread) {
        $markThreadRead = $pdo->prepare(
            'UPDATE pesan_kontak SET dibaca_admin = 1 WHERE id = ?'
        );
        $markThreadRead->execute([$selectedId]);

        $noticeLink = 'index.php?module=pesan_masuk&id=' . $selectedId;
        $markNoticeRead = $pdo->prepare(
            'UPDATE notifikasi
             SET dibaca = 1
             WHERE recipient_user_id = ? AND tautan = ? AND dibaca = 0'
        );
        $markNoticeRead->execute([
            (int) ($_SESSION['user_id'] ?? 0),
            $noticeLink,
        ]);

        $repliesStmt = $pdo->prepare(
            'SELECT b.*, u.nama_lengkap AS admin_nama
             FROM pesan_kontak_balasan b
             LEFT JOIN users u ON u.id = b.admin_user_id
             WHERE b.pesan_id = ?
             ORDER BY b.id'
        );
        $repliesStmt->execute([$selectedId]);
        $replies = $repliesStmt->fetchAll();
    }
}

$pageTitle = 'Pesan Masuk';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading contact-inbox-heading">
    <div>
        <h1>Pesan Masuk</h1>
        <p>
            Percakapan layanan publik dari formulir kontak sekolah.
            Balasan akan dikirim ke alamat email pengirim.
        </p>
    </div>
    <a
        class="btn btn-outline-primary"
        href="public/kontak.php"
        target="_blank"
        rel="noopener noreferrer">
        <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
        Lihat formulir publik
    </a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-warning" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<div class="contact-inbox-layout">
    <aside class="card contact-thread-list" aria-label="Daftar percakapan">
        <div class="contact-thread-list-heading">
            <strong>
                <i class="bi bi-inbox-fill me-2" aria-hidden="true"></i>
                Kotak Masuk
            </strong>
            <span><?= count($threads) ?> percakapan</span>
        </div>

        <nav class="contact-thread-filters" aria-label="Filter percakapan">
            <?php
            $statusLabels = [
                'aktif' => 'Aktif',
                'baru' => 'Baru',
                'dibalas' => 'Dibalas',
                'ditutup' => 'Ditutup',
                'semua' => 'Semua',
            ];
            foreach ($statusLabels as $key => $label):
            ?>
                <a
                    class="<?= $statusFilter === $key ? 'active' : '' ?>"
                    href="index.php?module=pesan_masuk&amp;status=<?= e($key) ?>">
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$threads): ?>
            <div class="contact-inbox-empty">
                <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                <strong>Belum ada pesan</strong>
                <span>Pesan dari halaman kontak publik akan muncul di sini.</span>
            </div>
        <?php else: ?>
            <div class="contact-thread-items">
                <?php foreach ($threads as $item): ?>
                    <?php
                    $isUnread = (int) $item['dibaca_admin'] === 0;
                    $isSelected = (int) $item['id'] === $selectedId;
                    $threadClasses = 'contact-thread-item';
                    $threadClasses .= $isUnread ? ' is-unread' : '';
                    $threadClasses .= $isSelected ? ' is-selected' : '';
                    ?>
                    <a
                        class="<?= e($threadClasses) ?>"
                        href="index.php?module=pesan_masuk&amp;id=<?= (int) $item['id'] ?>&amp;status=<?= e($statusFilter) ?>">
                        <span class="contact-thread-avatar">
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                        </span>
                        <span class="contact-thread-summary">
                            <strong><?= e($item['nama_pengirim']) ?></strong>
                            <small><?= e($item['subjek']) ?></small>
                            <span>
                                <?= e(mb_strimwidth((string) $item['cuplikan'], 0, 90, '…', 'UTF-8')) ?>
                            </span>
                        </span>
                        <span class="contact-thread-meta">
                            <time><?= e(date('d M', strtotime($item['diperbarui_pada']))) ?></time>
                            <small class="contact-status status-<?= e($item['status']) ?>">
                                <?= e(ucfirst($item['status'])) ?>
                            </small>
                            <?php if ($isUnread): ?>
                                <b aria-label="Belum dibaca"></b>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>

    <section class="card contact-conversation" aria-label="Isi percakapan">
        <?php if (!$thread): ?>
            <div class="contact-inbox-empty conversation-empty">
                <i class="bi bi-chat-dots" aria-hidden="true"></i>
                <strong>Pilih sebuah percakapan</strong>
                <span>Pesan akan ditampilkan seperti percakapan setelah dipilih.</span>
            </div>
        <?php else: ?>
            <header class="contact-conversation-header">
                <span class="contact-thread-avatar large">
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                </span>
                <div>
                    <h2><?= e($thread['nama_pengirim']) ?></h2>
                    <p>
                        <a href="mailto:<?= e($thread['email_pengirim']) ?>">
                            <?= e($thread['email_pengirim']) ?>
                        </a>
                        <?= $thread['telepon_pengirim']
                            ? ' · ' . e($thread['telepon_pengirim'])
                            : '' ?>
                    </p>
                </div>
                <span class="contact-status status-<?= e($thread['status']) ?>">
                    <?= e(ucfirst($thread['status'])) ?>
                </span>
            </header>

            <div class="contact-conversation-subject">
                <span><?= e($thread['kategori']) ?></span>
                <strong><?= e($thread['subjek']) ?></strong>
                <small>
                    Pesan diterima
                    <?= e(date('d M Y · H:i', strtotime($thread['dibuat_pada']))) ?>
                </small>
            </div>

            <div class="contact-message-stream">
                <?php foreach ($replies as $reply): ?>
                    <?php $fromAdmin = $reply['pengirim'] === 'admin'; ?>
                    <article class="contact-message-bubble<?= $fromAdmin ? ' from-admin' : ' from-visitor' ?>">
                        <div class="contact-message-author">
                            <?= $fromAdmin
                                ? e($reply['admin_nama'] ?: 'Admin Utama')
                                : e($thread['nama_pengirim']) ?>
                            <span><?= $fromAdmin ? 'Sekolah' : 'Pengunjung' ?></span>
                        </div>
                        <p><?= nl2br(e($reply['isi'])) ?></p>
                        <footer>
                            <time>
                                <?= e(date('d M Y · H:i', strtotime($reply['dibuat_pada']))) ?>
                            </time>
                            <?php if ($fromAdmin): ?>
                                <?php $emailIcon = $reply['status_email'] === 'terkirim'
                                    ? 'bi-envelope-check'
                                    : 'bi-envelope'; ?>
                                <span class="contact-email-state <?= e($reply['status_email']) ?>">
                                    <i class="bi <?= e($emailIcon) ?>" aria-hidden="true"></i>
                                    Email <?= e($reply['status_email']) ?>
                                </span>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="contact-conversation-actions">
                <?php if ($thread['status'] !== 'ditutup'): ?>
                    <form method="post" onsubmit="return confirm('Tutup percakapan ini?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="pesan_id" value="<?= (int) $thread['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" name="action" value="tutup">
                            <i class="bi bi-archive me-1" aria-hidden="true"></i>
                            Tutup percakapan
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="pesan_id" value="<?= (int) $thread['id'] ?>">
                        <button class="btn btn-sm btn-outline-primary" name="action" value="buka_kembali">
                            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                            Buka kembali
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($thread['status'] !== 'ditutup'): ?>
                <form method="post" class="contact-reply-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="pesan_id" value="<?= (int) $thread['id'] ?>">
                    <input type="hidden" name="action" value="balas">
                    <label for="contactReply">Balas pesan</label>
                    <textarea
                        id="contactReply"
                        class="form-control"
                        name="balasan"
                        rows="4"
                        maxlength="10000"
                        placeholder="Tulis balasan untuk pengirim…"
                        required></textarea>
                    <div class="contact-reply-footer">
                        <small>
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            Balasan tersimpan di percakapan dan dikirim ke email pengirim.
                        </small>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-send-fill me-1" aria-hidden="true"></i>
                            Kirim balasan
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
