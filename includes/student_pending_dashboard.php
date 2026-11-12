<?php
$studentGreeting = (int)date('G') < 11 ? 'Selamat pagi' : ((int)date('G') < 15 ? 'Selamat siang' : ((int)date('G') < 19 ? 'Selamat sore' : 'Selamat malam'));
?>
<div class="student-dashboard-pending">
    <section class="student-hero student-pending-hero" aria-labelledby="studentPendingWelcome">
        <div class="student-hero-copy">
            <span class="student-hero-kicker">PORTAL AKADEMIK · SMP NEGERI 4 PIRIME</span>
            <h1 id="studentPendingWelcome"><?= e($studentGreeting) ?>, <?= e($student['nama']) ?></h1>
            <p>Lengkapi dan periksa biodata Anda. Semua data yang sudah disimpan tetap terlihat di halaman ini selama menunggu persetujuan.</p>
            <div class="student-hero-meta"><span><i class="bi bi-mortarboard" aria-hidden="true"></i><?= e($student['nama_kelas'] ?: 'Kelas belum ditetapkan') ?></span><span><i class="bi bi-person-vcard" aria-hidden="true"></i>NIS <?= e($student['nis']) ?></span></div>
        </div>
        <div class="student-pending-process">
            <span class="student-pending-process-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <div><strong>Langkah berikutnya</strong><p>Setelah semua data wajib terisi, admin dapat langsung memeriksa dan menyetujui biodata dari Manajemen Pengguna.</p></div>
            <a href="biodata.php" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Buka formulir biodata</a>
        </div>
    </section>
    <?php require __DIR__ . '/student_biodata_panel.php'; ?>
</div>
