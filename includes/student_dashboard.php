<?php
$semesterChart = [];
foreach ($dashboard['current_grades'] as $gradeRow) {
    $subjectKey = (int)$gradeRow['mapel_id'];
    if (!isset($semesterChart[$subjectKey])) $semesterChart[$subjectKey] = ['name' => $gradeRow['nama_mapel'], 'Ganjil' => null, 'Genap' => null];
    $semesterChart[$subjectKey][$gradeRow['semester']] = (float)$gradeRow['nilai_akhir'];
}
$semesterChart = array_slice(array_values($semesterChart), 0, 5);
$taskTotal = (int)$dashboard['task_stats']['total'];
$taskSubmitted = max(0, (int)$dashboard['task_stats']['submitted'] - (int)$dashboard['task_stats']['submitted_late']);
$taskLate = (int)$dashboard['task_stats']['overdue'];
$taskPending = (int)$dashboard['task_stats']['pending'];
$taskDoneAngle = $taskTotal > 0 ? round(360 * $taskSubmitted / $taskTotal) : 0;
$taskLateAngle = $taskTotal > 0 ? min(360, $taskDoneAngle + round(360 * $taskLate / $taskTotal)) : 0;
$profileStatus = match ($biodataStatus) {
    'terverifikasi' => ['Terverifikasi', 'verified'],
    'menunggu_verifikasi' => ['Menunggu verifikasi', 'pending'],
    'perlu_perbaikan' => ['Perlu perbaikan', 'needs-update'],
    'ditolak' => ['Perlu diperbarui', 'needs-update'],
    'belum_lengkap' => ['Belum lengkap', 'needs-update'],
    default => ['Belum diperiksa', 'pending'],
};
?>
<div class="student-dashboard-layout">
    <div class="student-dashboard-main">
        <section class="student-hero" aria-labelledby="studentWelcome">
            <div class="student-hero-copy">
                <span class="student-hero-kicker">PORTAL AKADEMIK · SMP NEGERI 4 PIRIME</span>
                <h2 id="studentWelcome"><?= e($greeting) ?>, <?= e($student['nama']) ?> <span aria-hidden="true">👋</span></h2>
                <p>Semangat belajar hari ini. Satu langkah kecil membawamu lebih dekat pada cita-cita.</p>
                <div class="student-hero-meta"><span><i class="bi bi-calendar3" aria-hidden="true"></i><?= e($todayDateLabel) ?></span><span><i class="bi bi-mortarboard" aria-hidden="true"></i>Tahun Ajaran <?= e($student['tahun_ajaran'] ?? 'belum ditentukan') ?></span><span><i class="bi bi-journal-bookmark" aria-hidden="true"></i>Data nilai terbaru: <?= e($latestSemesterLabel) ?></span></div>
            </div>
            <div class="student-hero-next">
                <div class="student-hero-next-title"><span class="student-icon-chip blue"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                    <div><strong>Pelajaran Hari Ini</strong><small><?= e($todayName) ?> · <?= count($dashboard['today_schedule']) ?> jadwal</small></div>
                </div>
                <?php if ($dashboard['today_schedule']): foreach (array_slice($dashboard['today_schedule'], 0, 2) as $index => $lesson): $state = date('H:i:s') >= $lesson['jam_selesai'] ? 'Selesai' : (date('H:i:s') >= $lesson['jam_mulai'] ? 'Berlangsung' : ($index === 0 ? 'Berikutnya' : 'Akan datang')); ?>
                        <div class="student-next-lesson"><span class="student-icon-chip <?= $state === 'Berlangsung' ? 'green' : 'light' ?>"><i class="bi <?= $state === 'Berlangsung' ? 'bi-check2-square' : 'bi-book' ?>" aria-hidden="true"></i></span>
                            <div><strong><?= e($lesson['nama_mapel']) ?></strong><small><?= e(substr((string)$lesson['jam_mulai'], 0, 5)) ?>–<?= e(substr((string)$lesson['jam_selesai'], 0, 5)) ?></small></div><span class="student-status <?= $state === 'Berlangsung' ? 'live' : 'waiting' ?>"><?= e($state) ?></span>
                        </div>
                    <?php endforeach;
                else: ?><div class="student-next-empty"><i class="bi bi-cup-hot" aria-hidden="true"></i><span>Tidak ada pelajaran terjadwal hari ini.</span></div><?php endif; ?>
            </div>
        </section>

        <?php require __DIR__ . '/student_biodata_panel.php'; ?>

        <section class="student-kpi-grid" aria-label="Ringkasan akademik">
            <a class="student-kpi-card accent-green" href="student.php?view=absensi&amp;bulan=<?= e(date('Y-m')) ?>">
                <span class="student-kpi-top">
                    <i class="bi bi-person-check" aria-hidden="true"></i>
                    <small>Kehadiran Bulan Ini</small>
                </span>
                <strong>
                    <?= $monthAttendanceRate === null ? '—' : e((string)$monthAttendanceRate) . '%' ?>
                </strong>
                <span>
                    <?= $monthAttendancePresent ?> hadir dari
                    <?= $monthAttendanceTotal ?> catatan
                </span>
                <small class="student-kpi-link">Lihat absensi
                    <i class="bi bi-arrow-right"></i>
                </small>
            </a>
            <a class="student-kpi-card accent-blue"
                href="learning.php?view=tugas&status_tugas=belum">
                <span class="student-kpi-top">
                    <i class="bi bi-journal-arrow-up"
                        aria-hidden="true"></i>
                    <small>Tugas Belum Dikumpulkan</small>
                </span>
                <strong>
                    <?= $taskPending ?>
                </strong>
                <span>dari <?= $taskTotal ?> tugas terbit</span>
                <small class="student-kpi-link">Buka tugas
                    <i class="bi bi-arrow-right"></i>
                </small>
            </a>
            <a class="student-kpi-card accent-orange"
                href="learning.php?view=tugas&status_tugas=terlambat">
                <span class="student-kpi-top">
                    <i class="bi bi-clock-history" aria-hidden="true">

                    </i><small>Tugas Terlambat</small>
                </span>
                <strong>
                    <?= $taskLate ?>
                </strong>
                <span>melewati batas pengumpulan</span>
                <small class="student-kpi-link">Lihat tugas
                    <i class="bi bi-arrow-right"></i>
                </small>
            </a>
            <a class="student-kpi-card accent-purple" href="student.php?view=nilai">
                <span class="student-kpi-top">
                    <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                    <small>Nilai Rata-rata</small>
                </span>
                <strong>
                    <?= $currentGradeAverage === null ? '—' : e(number_format($currentGradeAverage, 1, ',', '.')) ?>
                </strong>
                <span>
                    <?= $currentGradeSubjectCount ?> mata pelajaran · tahun aktif
                </span>
                <small class="student-kpi-link">
                    Lihat nilai
                    <i class="bi bi-arrow-right"></i>
                </small>
            </a>
            <a class="student-kpi-card accent-teal" href="student.php?view=nilai">
                <span class="student-kpi-top">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <small>Mapel Tanpa Nilai Terbit</small>
                </span>
                <strong>
                    <?= $unpublishedSubjectCount ?>
                </strong>
                <span>dari <?= $subjectTotal ?> mapel terjadwal</span>
                <small class="student-kpi-link">
                    Lihat nilai
                    <i class="bi bi-arrow-right"></i>
                </small>
            </a>
            <a class="student-kpi-card accent-rose" href="student.php?view=nilai">
                <span class="student-kpi-top">
                    <i class="bi bi-award" aria-hidden="true"></i>
                    <small>Pencapaian KKM</small>
                </span>
                <strong>
                    <?= $kkmDenominator ? $currentKkmReached . '/' . $kkmDenominator : '—' ?>
                </strong>
                <span>nilai mencapai KKM</span>
                <small class="student-kpi-link">
                    Lihat detail
                    <i class="bi bi-arrow-right"></i>
                </small>
            </a>
        </section>

        <section class="student-section-card" aria-labelledby="studentProgressTitle">
                <div class="student-section-heading">
                    <div><span class="student-section-icon"><i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="studentProgressTitle">Progres Belajar</h2>
                            <p>Ringkasan berdasarkan nilai, absensi, dan tugas pada data sekolah.</p>
                        </div>
                    </div><a href="student.php?view=nilai">Lihat detail <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="student-progress-grid">
                    <article class="student-chart-card">
                        <h3>Nilai per Semester</h3>
                        <p class="student-chart-caption">Nilai akhir tiap mata pelajaran · tahun ajaran aktif</p>
                        <?php if (!$semesterChart): ?><div class="student-chart-empty">Belum ada nilai terbit untuk ditampilkan.</div><?php else: ?><div class="student-semester-legend"><span><i class="legend-blue"></i>Ganjil</span><span><i class="legend-sky"></i>Genap</span></div>
                            <div class="student-semester-bars"><?php foreach ($semesterChart as $subject): ?><div class="student-semester-row"><span title="<?= e($subject['name']) ?>"><?= e($subject['name']) ?></span>
                                        <div class="student-pair-bars"><?php foreach (['Ganjil' => 'blue', 'Genap' => 'sky'] as $semester => $tone): $score = $subject[$semester]; ?><span class="student-pair-bar <?= $tone ?>" style="width:<?= $score === null ? 0 : max(3, min(100, (float)$score)) ?>%" title="<?= e($semester) ?>: <?= $score === null ? 'belum ada nilai' : e(number_format($score, 1, ',', '.')) ?>"><?= $score === null ? '' : e(number_format($score, 0, ',', '.')) ?></span><?php endforeach; ?></div>
                                    </div><?php endforeach; ?></div><?php endif; ?>
                    </article>
                    <article class="student-chart-card">
                        <h3>Tren Kehadiran</h3>
                        <p class="student-chart-caption">Persentase hadir · empat minggu terakhir</p>
                        <?php if (!array_filter($dashboard['weekly_attendance'], static fn($week) => $week['rate'] !== null)): ?><div class="student-chart-empty">Belum ada catatan absensi empat minggu ini.</div><?php else: ?><div class="student-week-bars"><?php foreach ($dashboard['weekly_attendance'] as $week): ?><div class="student-week-column">
                                        <div class="student-week-track"><span style="height:<?= $week['rate'] === null ? 0 : (int)$week['rate'] ?>%"></span></div><strong><?= $week['rate'] === null ? '—' : (int)$week['rate'] . '%' ?></strong><small><?= e($week['label']) ?></small>
                                    </div><?php endforeach; ?></div>
                            <div class="student-chart-legend"><i class="legend-green"></i>Hadir</div><?php endif; ?>
                    </article>
                    <article class="student-chart-card">
                        <h3>Progres Tugas</h3>
                        <p class="student-chart-caption">Status tugas yang diterbitkan</p>
                        <?php if (!$taskTotal): ?><div class="student-chart-empty">Belum ada tugas terbit di kelas Anda.</div><?php else: ?><div class="student-task-donut-wrap">
                                <div class="student-task-donut" style="--task-done:<?= $taskDoneAngle ?>deg;--task-late:<?= $taskLateAngle ?>deg"><span><strong><?= $taskSubmitted ?>/<?= $taskTotal ?></strong><small>Terkumpul tepat waktu</small></span></div>
                                <div class="student-task-legend"><span><i class="legend-green"></i>Terkumpul <strong><?= $taskSubmitted ?></strong></span><span><i class="legend-blue"></i>Belum <strong><?= $taskPending ?></strong></span><span><i class="legend-red"></i>Terlambat <strong><?= $taskLate ?></strong></span></div>
                            </div><?php endif; ?>
                    </article>
                    <article class="student-chart-card">
                        <h3>Status KKM</h3>
                        <p class="student-chart-caption">Mata pelajaran dengan nilai terbit</p>
                        <?php if (!$currentGradeSubjectMap): ?>
                            <div class="student-chart-empty">Belum ada nilai untuk dibandingkan dengan KKM.</div>
                        <?php else: ?>
                            <div class="student-kkm-list">
                                <?php foreach (array_slice(array_values($currentGradeSubjectMap), 0, 6) as $subjectGrade): $score = (float)$subjectGrade['nilai_akhir'];
                                    $target = (float)$subjectGrade['kkm']; ?>
                                    <div class="student-kkm-row">
                                        <div>
                                            <span><?= e($subjectGrade['nama_mapel']) ?></span>
                                            <strong class="
                                        <?= $score >= $target ? 'is-achieved' : 'is-below' ?>">
                                                <?= e(number_format($score, 0, ',', '.')) ?> /
                                                <?= e(number_format($target, 0, ',', '.')) ?></strong>
                                        </div>
                                        <div class="student-kkm-track">
                                            <span class="<?= $score >= $target ? 'is-achieved' : 'is-below' ?>" style="width:<?= max(0, min(100, $score)) ?>%"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
        </section>

            <div class="student-content-grid">
                <section class="student-section-card">
                    <div class="student-section-heading">
                        <div><span class="student-section-icon">
                                <i class="bi bi-calendar-week" aria-hidden="true"></i></span>
                            <div>
                                <h2>Jadwal Pelajaran Hari Ini</h2>
                                <p><?= e($todayDateLabel) ?> · Kelas <?= e($student['nama_kelas']) ?></p>
                            </div>
                        </div><a href="student.php?view=jadwal">Lihat semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!$dashboard['today_schedule']): ?><div class="student-chart-empty">Tidak ada jadwal untuk hari ini. Jadwal kelas dapat dilihat melalui tombol “Lihat semua”.</div><?php else: ?><div class="table-responsive">
                            <table class="table student-modern-table mb-0">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Waktu</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Guru</th>
                                        <th>Ruang</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($dashboard['today_schedule'] as $index => $lesson): $state = date('H:i:s') >= $lesson['jam_selesai'] ? 'Selesai' : (date('H:i:s') >= $lesson['jam_mulai'] ? 'Berlangsung' : 'Berikutnya'); ?><tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= e(substr((string)$lesson['jam_mulai'], 0, 5)) ?>–<?= e(substr((string)$lesson['jam_selesai'], 0, 5)) ?></td>
                                            <td><strong><?= e($lesson['nama_mapel']) ?></strong></td>
                                            <td><?= e($lesson['guru'] ?: 'Belum ditetapkan') ?></td>
                                            <td><?= e($lesson['ruang'] ?: '—') ?></td>
                                            <td><span class="student-status <?= $state === 'Berlangsung' ? 'live' : 'waiting' ?>"><?= e($state) ?></span></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </section>
                <section class="student-section-card">
                    <div class="student-section-heading">
                        <div><span class="student-section-icon orange"><i class="bi bi-clipboard2-check" aria-hidden="true"></i></span>
                            <div>
                                <h2>Tugas Terdekat</h2>
                                <p><?= $taskPending ?> tugas menunggu dikumpulkan · <?= $taskLate ?> terlambat</p>
                            </div>
                        </div><a href="learning.php?view=tugas">Lihat semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!$dashboard['learning_available']): ?>
                        <div class="student-chart-empty">Modul pembelajaran belum tersedia pada database ini.</div>
                    <?php elseif (!$dashboard['tasks']): ?>
                        <div class="student-chart-empty">Belum ada tugas terbit untuk kelas Anda.</div>
                    <?php else: ?>
                        <div class="student-task-list">
                            <?php foreach ($dashboard['tasks'] as $index => $task): ?>
                                <?php
                                $submitted = !empty($task['submission_id']);
                                $late = $submitted
                                    && $task['tenggat']
                                    && strtotime((string)$task['dikumpulkan_at']) > strtotime((string)$task['tenggat']);
                                $overdue = !$submitted
                                    && $task['tenggat']
                                    && strtotime((string)$task['tenggat']) < time();
                                $status = $task['nilai_tugas'] !== null
                                    ? 'Sudah dinilai'
                                    : ($late
                                        ? 'Terkumpul terlambat'
                                        : ($submitted ? 'Terkumpul' : ($overdue ? 'Terlambat' : 'Belum')));
                                ?>
                                <a class="student-task-row" href="learning.php?view=tugas">
                                    <span class="student-task-number"><?= $index + 1 ?></span>
                                    <span class="student-task-info">
                                        <strong><?= e($task['judul']) ?></strong>
                                        <small><?= e($task['nama_mapel']) ?> · <?= e($task['nama_guru']) ?></small>
                                    </span>
                                    <span class="student-task-deadline">
                                        <?= $task['tenggat'] ? e(date('d M Y', strtotime($task['tenggat']))) : 'Tanpa tenggat' ?>
                                    </span>
                                    <span class="student-task-state <?= $overdue || $late ? 'late' : ($submitted ? 'done' : 'pending') ?>">
                                        <?= e($status) ?>
                                    </span>
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="student-content-grid student-content-grid-secondary">
                <section class="student-section-card">
                    <div class="student-section-heading">
                        <div><span class="student-section-icon purple"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                            <div>
                                <h2>Pengumuman Sekolah</h2>
                                <p>Informasi resmi sekolah yang sedang berlaku.</p>
                            </div>
                        </div><a href="public/pengumuman.php">Lihat semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!$dashboard['announcements']): ?><div class="student-chart-empty">Belum ada pengumuman aktif.</div><?php else: ?><div class="student-compact-list"><?php foreach (array_slice($dashboard['announcements'], 0, 3) as $notice): ?><a href="public/pengumuman.php"><strong><?= e($notice['judul']) ?></strong><span><?= e(date('d M Y', strtotime($notice['tanggal']))) ?> · <?= e($studentShortText((string)$notice['isi'], 120)) ?></span></a><?php endforeach; ?></div><?php endif; ?>
                </section>
                <section class="student-section-card">
                    <div class="student-section-heading">
                        <div><span class="student-section-icon blue"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
                            <div>
                                <h2>Materi Pembelajaran</h2>
                                <p>Materi terbaru dari guru kelas Anda.</p>
                            </div>
                        </div><a href="learning.php?view=materi">Lihat semua <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <?php if (!$dashboard['learning_available'] || !$dashboard['materials']): ?><div class="student-chart-empty"><?= $dashboard['learning_available'] ? 'Belum ada materi terbit.' : 'Modul pembelajaran belum tersedia.' ?></div><?php else: ?><div class="student-compact-list"><?php foreach ($dashboard['materials'] as $material): ?><a href="<?= !empty($material['file_path']) ? 'media/academic.php?type=materi&id=' . (int)$material['id'] : 'learning.php?view=materi' ?>"><strong><?= e($material['judul']) ?></strong><span><?= e($material['nama_mapel']) ?> · <?= e($material['nama_guru']) ?><?= $material['file_name'] ? ' · ' . e($material['file_name']) : '' ?></span></a><?php endforeach; ?></div><?php endif; ?>
                </section>
            </div>
    </div>

    <aside class="student-dashboard-rail" aria-label="Informasi dan akses siswa">
        <section class="student-section-card student-profile-card">
            <div class="student-profile-heading">
                <?php if (!empty($_SESSION['profile_photo'])): ?><img src="media/profile.php?id=<?= (int)$_SESSION['user_id'] ?>" alt="Foto <?= e($student['nama']) ?>"><?php else: ?><span class="student-profile-avatar"><?= e($studentInitial($student['nama'])) ?></span><?php endif; ?>
                <div>
                    <h2><?= e($student['nama']) ?></h2>
                    <p>NIS <?= e($student['nis']) ?></p>
                    <p>NISN <?= e($student['nisn'] ?: 'Belum diisi') ?></p>
                </div>
            </div>
            <dl class="student-profile-details">
                <div>
                    <dt><i class="bi bi-people"></i>Kelas</dt>
                    <dd><?= e($student['nama_kelas']) ?></dd>
                </div>
                <div>
                    <dt><i class="bi bi-calendar3"></i>Tahun Ajaran</dt>
                    <dd><?= e($student['tahun_ajaran'] ?? 'Belum ditentukan') ?></dd>
                </div>
                <div>
                    <dt><i class="bi bi-person-workspace"></i>Wali Kelas</dt>
                    <dd><?= e($student['wali_nama'] ?: 'Belum ditetapkan') ?></dd>
                </div>
            </dl>
            <div class="student-profile-badges"><span class="<?= $profileStatus[1] ?>"><i class="bi bi-person-check-fill"></i><?= e($profileStatus[0]) ?></span><span class="verified"><i class="bi bi-shield-check"></i>Akun aktif</span></div><a class="student-profile-button" href="biodata.php">Lihat profil lengkap <i class="bi bi-arrow-right"></i></a>
        </section>

        <section class="student-section-card student-quick-card">
            <div class="student-section-heading compact">
                <div>
                    <div>
                        <h2>Akses Cepat</h2>
                        <p>Buka layanan akademik</p>
                    </div>
                </div>
            </div>
            <div class="student-quick-grid">
                <a href="student.php?view=jadwal"><i class="bi bi-calendar-week"></i><span>Jadwal</span></a><a href="student.php?view=absensi"><i class="bi bi-check-square-fill green"></i><span>Absensi</span></a><a href="student.php?view=nilai"><i class="bi bi-journal-check"></i><span>Nilai</span></a><a href="learning.php?view=materi"><i class="bi bi-book-half"></i><span>Materi</span></a><a href="learning.php?view=tugas"><i class="bi bi-card-checklist"></i><span>Tugas</span></a><a href="learning.php?view=pengumpulan"><i class="bi bi-clock-history"></i><span>Riwayat</span></a><a href="biodata.php"><i class="bi bi-person-vcard"></i><span>Profil</span></a><a href="index.php?module=notifikasi"><i class="bi bi-bell-fill"></i><span>Notifikasi</span><?php if ($unreadNotifications > 0): ?><b><?= min($unreadNotifications, 99) ?></b><?php endif; ?></a><a href="public/agenda.php"><i class="bi bi-calendar-event"></i><span>Agenda</span></a>
            </div>
        </section>

        <section class="student-section-card student-reminder-card">
            <div class="student-section-heading">
                <div><span class="student-section-icon orange"><i class="bi bi-bell" aria-hidden="true"></i></span>
                    <div>
                        <h2>Notifikasi & Pengingat</h2>
                        <p>Informasi terbaru untuk Anda</p>
                    </div>
                </div><a href="index.php?module=notifikasi">Semua <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($dashboard['notifications']): ?>
                <div class="student-reminder-list">
                    <?php foreach ($dashboard['notifications'] as $notice): ?>
                        <?php
                        $noticeUrl = preg_match(
                            '~^(?:index\.php|reports\.php|student\.php|teacher\.php|learning\.php|public/[a-zA-Z0-9_.-]+\.php)(?:\?[a-zA-Z0-9_=&%.-]*)?$~',
                            (string)($notice['tautan'] ?? '')
                        ) ? (string)$notice['tautan'] : 'index.php?module=notifikasi';
                        ?>
                        <a href="<?= e($noticeUrl) ?>">
                            <span class="student-reminder-icon <?= (int)$notice['dibaca'] === 0 ? 'unread' : 'read' ?>">
                                <i class="bi bi-bell"></i>
                            </span>
                            <span>
                                <strong><?= e($notice['judul']) ?></strong>
                                <small><?= e($studentShortText((string)$notice['pesan'], 85)) ?></small>
                            </span>
                            <time><?= e(date('d M', strtotime($notice['created_at']))) ?></time>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($dashboard['tasks']): ?>
                <div class="student-reminder-list">
                    <?php foreach (array_slice($dashboard['tasks'], 0, 3) as $task): ?>
                        <?php
                        $submitted = !empty($task['submission_id']);
                        $overdue = !$submitted
                            && $task['tenggat']
                            && strtotime((string)$task['tenggat']) < time();
                        ?>
                        <a href="learning.php?view=tugas">
                            <span class="student-reminder-icon <?= $overdue ? 'unread' : 'task' ?>">
                                <i class="bi <?= $overdue ? 'bi-exclamation-circle' : 'bi-clipboard-check' ?>"></i>
                            </span>
                            <span>
                                <strong><?= e($task['judul']) ?></strong>
                                <small>
                                    <?= e($task['nama_mapel']) ?> ·
                                    <?= $task['tenggat']
                                        ? ($overdue ? 'Terlambat · ' : 'Tenggat ') . e(date('d M Y', strtotime($task['tenggat'])))
                                        : 'Tugas baru' ?>
                                </small>
                            </span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="student-chart-empty compact">Belum ada notifikasi atau pengingat baru.</div>
            <?php endif; ?>
        </section>

        <div class="student-help-note"><i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <p>Jika informasi belum muncul, pastikan data sudah dimasukkan sekolah. Hubungi wali kelas bila perlu bantuan.</p><span>Semester yang ditampilkan mengikuti data nilai terbit: <?= e($latestSemesterLabel) ?></span>
        </div>
    </aside>
</div>
