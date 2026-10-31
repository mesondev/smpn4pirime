<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (current_role() !== 'siswa') {
    http_response_code(403);
    exit('Halaman ini hanya untuk akun siswa.');
}

$accountStmt = $pdo->prepare('SELECT role,siswa_id,aktif FROM users WHERE id=? LIMIT 1');
$accountStmt->execute([(int)$_SESSION['user_id']]);
$account = $accountStmt->fetch();
if (!$account || $account['role'] !== 'siswa' || (int)$account['aktif'] !== 1) {
    http_response_code(403);
    exit('Akun siswa tidak aktif atau perannya telah berubah. Silakan masuk kembali.');
}

$studentId = (int)($account['siswa_id'] ?? 0);
if ($studentId < 1) {
    http_response_code(403);
    exit('Akun ini belum ditautkan ke data siswa. Hubungi Super Admin sekolah.');
}

$studentStmt = $pdo->prepare(
    'SELECT s.id,s.nis,s.nisn,s.nama,s.jenis_kelamin,s.tanggal_lahir,s.alamat,s.status,
            s.kelas_id,k.nama_kelas,k.tingkat,k.tahun_ajaran_id,ta.nama AS tahun_ajaran,
            g.nama AS wali_nama
     FROM siswa s
     JOIN kelas k ON k.id=s.kelas_id
     LEFT JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id
     LEFT JOIN guru g ON g.id=k.wali_guru_id
     WHERE s.id=? LIMIT 1'
);
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch();
if (!$student) {
    http_response_code(404);
    exit('Data siswa tertaut tidak ditemukan. Hubungi Super Admin sekolah.');
}

$view = (string)($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'jadwal', 'absensi', 'nilai'], true)) $view = 'dashboard';
$schoolDays = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$scheduleDayFilter = in_array((string)($_GET['hari'] ?? ''), $schoolDays, true) ? (string)$_GET['hari'] : '';
$attendanceStatusFilter = in_array((string)($_GET['status'] ?? ''), ['Hadir','Izin','Sakit','Alpa'], true) ? (string)$_GET['status'] : '';
$attendanceStartFilter = (string)($_GET['mulai'] ?? '');
$attendanceEndFilter = (string)($_GET['sampai'] ?? '');
$attendanceMonthFilter = (string)($_GET['bulan'] ?? '');
$validDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$validDate($attendanceStartFilter)) $attendanceStartFilter = '';
if (!$validDate($attendanceEndFilter)) $attendanceEndFilter = '';
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $attendanceMonthFilter)) $attendanceMonthFilter = '';
$gradeYearFilter = ctype_digit((string)($_GET['tahun_ajaran_id'] ?? '')) ? (int)$_GET['tahun_ajaran_id'] : 0;
$gradeSemesterFilter = in_array((string)($_GET['semester'] ?? ''), ['1','2'], true) ? (string)$_GET['semester'] : '';
$gradeSubjectFilter = ctype_digit((string)($_GET['mapel_id'] ?? '')) ? (int)$_GET['mapel_id'] : 0;
$gradeFilterYears = [];
$gradeFilterSubjects = [];
$pageTitle = match ($view) {
    'jadwal' => 'Jadwal Pelajaran',
    'absensi' => 'Riwayat Absensi',
    'nilai' => 'Nilai Saya',
    default => 'Dashboard Siswa',
};
$studentId = (int)$student['id'];
$classId = (int)$student['kelas_id'];
$schoolYearId = (int)$student['tahun_ajaran_id'];
$rows = [];
$weekdayNames = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];
$todayName = $weekdayNames[date('l')] ?? 'Hari ini';
$biodataStatus = null;
$biodataSchema = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_pengguna'")->fetchColumn();
if ((int)$biodataSchema === 1) {
    $biodataStmt = $pdo->prepare('SELECT status_verifikasi FROM biodata_pengguna WHERE user_id=?');
    $biodataStmt->execute([(int)$_SESSION['user_id']]);
    $biodataStatus = $biodataStmt->fetchColumn() ?: 'belum_lengkap';
}

$scheduleSql = "SELECT j.hari,j.jam_mulai,j.jam_selesai,m.nama_mapel,g.nama AS guru,j.ruang
    FROM jadwal_pelajaran j
    JOIN mapel m ON m.id=j.mapel_id
    LEFT JOIN guru g ON g.id=j.guru_id
    WHERE j.kelas_id=? AND j.tahun_ajaran_id=?
    ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'),j.jam_mulai";

$attendanceSummary = [];
$attendanceSummaryStmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi WHERE siswa_id=? GROUP BY status');
$attendanceSummaryStmt->execute([$studentId]);
foreach ($attendanceSummaryStmt->fetchAll() as $attendanceItem) $attendanceSummary[$attendanceItem['status']] = (int)$attendanceItem['jumlah'];
$attendanceMonthSummary = [];
$attendanceMonthStmt = $pdo->prepare("SELECT status,COUNT(*) AS jumlah FROM absensi WHERE siswa_id=? AND tanggal>=DATE_FORMAT(CURDATE(),'%Y-%m-01') AND tanggal<DATE_ADD(DATE_FORMAT(CURDATE(),'%Y-%m-01'),INTERVAL 1 MONTH) GROUP BY status");
$attendanceMonthStmt->execute([$studentId]);
foreach ($attendanceMonthStmt->fetchAll() as $attendanceItem) $attendanceMonthSummary[$attendanceItem['status']] = (int)$attendanceItem['jumlah'];

$gradeColumns = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema=DATABASE() AND table_name='nilai' AND column_name IN ('nilai_uts','nilai_uas')");
$gradeColumns->execute();
$hasWeightedGrades = (int)$gradeColumns->fetchColumn() === 2;
$gradePublicationReady = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='nilai' AND column_name='status_publikasi'")->fetchColumn() === 1;
$publishedGradeFilter = $gradePublicationReady ? " AND n.status_publikasi='Terbit'" : '';
$gradeWeights = ['bobot_tugas' => 30.0, 'bobot_uts' => 30.0, 'bobot_uas' => 40.0];
if ($hasWeightedGrades) {
    $weightRow = $pdo->query('SELECT bobot_tugas,bobot_uts,bobot_uas FROM profil_sekolah ORDER BY id LIMIT 1')->fetch();
    if ($weightRow) $gradeWeights = array_map('floatval', $weightRow);
}
$gradeSql = $hasWeightedGrades
    ? "SELECT m.id AS mapel_id,m.nama_mapel,n.tahun_ajaran_id,ta.nama AS tahun_ajaran,IF(n.semester='1','Ganjil','Genap') AS semester,
              n.nilai_tugas,n.nilai_uts,n.nilai_uas,n.nilai_ujian AS nilai_legacy,m.kkm,
              CASE WHEN n.nilai_uts IS NOT NULL AND n.nilai_uas IS NOT NULL
                   THEN (n.nilai_tugas*? + n.nilai_uts*? + n.nilai_uas*?)/100
                   ELSE (n.nilai_tugas+n.nilai_ujian)/2 END AS nilai_akhir
       FROM nilai n JOIN mapel m ON m.id=n.mapel_id JOIN tahun_ajaran ta ON ta.id=n.tahun_ajaran_id
       WHERE n.siswa_id=?$publishedGradeFilter"
    : "SELECT m.id AS mapel_id,m.nama_mapel,n.tahun_ajaran_id,ta.nama AS tahun_ajaran,IF(n.semester='1','Ganjil','Genap') AS semester,
              n.nilai_tugas,NULL AS nilai_uts,NULL AS nilai_uas,n.nilai_ujian AS nilai_legacy,m.kkm,
              (n.nilai_tugas+n.nilai_ujian)/2 AS nilai_akhir
       FROM nilai n JOIN mapel m ON m.id=n.mapel_id JOIN tahun_ajaran ta ON ta.id=n.tahun_ajaran_id
       WHERE n.siswa_id=?$publishedGradeFilter";

if ($view === 'jadwal') {
    $filteredScheduleSql = $scheduleSql;
    $scheduleParams = [$classId, $schoolYearId];
    if ($scheduleDayFilter !== '') {
        $filteredScheduleSql = str_replace('ORDER BY FIELD', 'AND j.hari=? ORDER BY FIELD', $filteredScheduleSql);
        $scheduleParams[] = $scheduleDayFilter;
    }
    $q = $pdo->prepare($filteredScheduleSql);
    $q->execute($scheduleParams);
    $rows = $q->fetchAll();
} elseif ($view === 'absensi') {
    $attendanceSql = 'SELECT tanggal,status,keterangan FROM absensi WHERE siswa_id=?';
    $attendanceParams = [$studentId];
    if ($attendanceStartFilter !== '') { $attendanceSql .= ' AND tanggal>=?'; $attendanceParams[] = $attendanceStartFilter; }
    if ($attendanceEndFilter !== '') { $attendanceSql .= ' AND tanggal<=?'; $attendanceParams[] = $attendanceEndFilter; }
    if ($attendanceMonthFilter !== '') { $attendanceSql .= " AND DATE_FORMAT(tanggal,'%Y-%m')=?"; $attendanceParams[] = $attendanceMonthFilter; }
    if ($attendanceStatusFilter !== '') { $attendanceSql .= ' AND status=?'; $attendanceParams[] = $attendanceStatusFilter; }
    $attendanceSql .= ' ORDER BY tanggal DESC,id DESC';
    $q = $pdo->prepare($attendanceSql);
    $q->execute($attendanceParams);
    $rows = $q->fetchAll();
} elseif ($view === 'nilai') {
    $yearsStmt = $pdo->prepare("SELECT DISTINCT ta.id,ta.nama,ta.tanggal_mulai FROM nilai n JOIN tahun_ajaran ta ON ta.id=n.tahun_ajaran_id WHERE n.siswa_id=?$publishedGradeFilter ORDER BY ta.tanggal_mulai DESC");
    $yearsStmt->execute([$studentId]);
    $gradeFilterYears = $yearsStmt->fetchAll();
    $subjectsStmt = $pdo->prepare("SELECT DISTINCT m.id,m.nama_mapel FROM nilai n JOIN mapel m ON m.id=n.mapel_id WHERE n.siswa_id=?$publishedGradeFilter ORDER BY m.nama_mapel");
    $subjectsStmt->execute([$studentId]);
    $gradeFilterSubjects = $subjectsStmt->fetchAll();
    $filteredGradeSql = $gradeSql;
    $gradeParams = $hasWeightedGrades
        ? [(float)$gradeWeights['bobot_tugas'],(float)$gradeWeights['bobot_uts'],(float)$gradeWeights['bobot_uas'],$studentId]
        : [$studentId];
    if ($gradeYearFilter > 0) { $filteredGradeSql .= ' AND n.tahun_ajaran_id=?'; $gradeParams[] = $gradeYearFilter; }
    if ($gradeSemesterFilter !== '') { $filteredGradeSql .= ' AND n.semester=?'; $gradeParams[] = $gradeSemesterFilter; }
    if ($gradeSubjectFilter > 0) { $filteredGradeSql .= ' AND n.mapel_id=?'; $gradeParams[] = $gradeSubjectFilter; }
    $filteredGradeSql .= ' ORDER BY ta.tanggal_mulai DESC,n.semester DESC,m.nama_mapel';
    $q = $pdo->prepare($filteredGradeSql);
    $q->execute($gradeParams);
    $rows = $q->fetchAll();
}

$dashboard = [
    'today_schedule' => [],
    'materials' => [], 'tasks' => [], 'announcements' => [], 'agenda' => [],
    'achievements' => [], 'lessons' => [], 'notifications' => [], 'learning_available' => false,
    'task_stats' => ['total'=>0,'pending'=>0,'overdue'=>0,'submitted'=>0,'submitted_late'=>0,'graded'=>0], 'current_grades' => [], 'weekly_attendance' => [],
];
if ($view === 'dashboard') {
    $todayScheduleSql = str_replace('WHERE j.kelas_id=? AND j.tahun_ajaran_id=?', 'WHERE j.kelas_id=? AND j.tahun_ajaran_id=? AND j.hari=?', $scheduleSql);
    $q = $pdo->prepare($todayScheduleSql);
    $q->execute([$classId,$schoolYearId,$todayName]);
    $dashboard['today_schedule'] = $q->fetchAll();

    $currentGradeSql = $gradeSql . ' AND n.tahun_ajaran_id=? ORDER BY ta.tanggal_mulai DESC,n.semester DESC,m.nama_mapel';
    $currentGradeParams = $hasWeightedGrades
        ? [(float)$gradeWeights['bobot_tugas'],(float)$gradeWeights['bobot_uts'],(float)$gradeWeights['bobot_uas'],$studentId,$schoolYearId]
        : [$studentId,$schoolYearId];
    $q = $pdo->prepare($currentGradeSql);
    $q->execute($currentGradeParams);
    $dashboard['current_grades'] = $q->fetchAll();

    $weekStmt = $pdo->prepare("SELECT YEARWEEK(tanggal,1) AS week_key,COUNT(*) AS total,SUM(status='Hadir') AS hadir FROM absensi WHERE siswa_id=? AND tanggal>=DATE_SUB(CURDATE(),INTERVAL 27 DAY) AND tanggal<=CURDATE() GROUP BY YEARWEEK(tanggal,1) ORDER BY week_key");
    $weekStmt->execute([$studentId]);
    $weekRows = [];
    foreach ($weekStmt->fetchAll() as $weekRow) $weekRows[(string)$weekRow['week_key']] = $weekRow;
    for ($weekOffset = 3; $weekOffset >= 0; $weekOffset--) {
        $weekStart = new DateTimeImmutable('monday this week');
        if ($weekOffset > 0) $weekStart = $weekStart->modify('-' . ($weekOffset * 7) . ' days');
        $weekKey = $weekStart->format('oW');
        $weekRow = $weekRows[$weekKey] ?? ['total'=>0,'hadir'=>0];
        $dashboard['weekly_attendance'][] = ['label'=>'Minggu ' . (4 - $weekOffset),'rate'=>(int)$weekRow['total'] > 0 ? round(100 * (int)$weekRow['hadir'] / (int)$weekRow['total']) : null];
    }

    $learningTables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name IN ('materi_pelajaran','tugas_pelajaran','pengumpulan_tugas')")->fetchColumn();
    if ((int)$learningTables === 3) {
        $dashboard['learning_available'] = true;
        $q = $pdo->prepare("SELECT x.id,x.judul,x.deskripsi,x.file_path,x.file_name,x.created_at,m.nama_mapel,g.nama AS nama_guru
            FROM materi_pelajaran x JOIN mapel m ON m.id=x.mapel_id JOIN guru g ON g.id=x.guru_id
            WHERE x.kelas_id=? AND x.tahun_ajaran_id=? AND x.status='Terbit'
            ORDER BY x.created_at DESC,x.id DESC LIMIT 5");
        $q->execute([$classId,$schoolYearId]);
        $dashboard['materials'] = $q->fetchAll();

        $q = $pdo->prepare("SELECT t.id,t.judul,t.instruksi,t.tenggat,t.created_at,m.nama_mapel,g.nama AS nama_guru,
                p.id AS submission_id,p.dikumpulkan_at,p.nilai AS nilai_tugas,p.umpan_balik
            FROM tugas_pelajaran t JOIN mapel m ON m.id=t.mapel_id JOIN guru g ON g.id=t.guru_id
            LEFT JOIN pengumpulan_tugas p ON p.tugas_id=t.id AND p.siswa_id=?
            WHERE t.kelas_id=? AND t.tahun_ajaran_id=? AND t.status='Terbit'
            ORDER BY CASE WHEN p.id IS NULL AND (t.tenggat IS NULL OR t.tenggat>=NOW()) THEN 0 WHEN p.id IS NULL THEN 1 ELSE 2 END,
              CASE WHEN p.id IS NULL THEN (t.tenggat IS NULL) ELSE 1 END,
              CASE WHEN p.id IS NULL THEN t.tenggat END,p.dikumpulkan_at DESC,t.created_at DESC LIMIT 5");
        $q->execute([$studentId,$classId,$schoolYearId]);
        $dashboard['tasks'] = $q->fetchAll();
        $q = $pdo->prepare("SELECT COUNT(DISTINCT t.id) AS total,
                COUNT(DISTINCT CASE WHEN p.id IS NULL AND (t.tenggat IS NULL OR t.tenggat>=NOW()) THEN t.id END) AS pending,
                COUNT(DISTINCT CASE WHEN (p.id IS NULL AND t.tenggat<NOW()) OR (p.id IS NOT NULL AND t.tenggat IS NOT NULL AND p.dikumpulkan_at>t.tenggat) THEN t.id END) AS overdue,
                COUNT(DISTINCT CASE WHEN p.id IS NOT NULL THEN t.id END) AS submitted,
                COUNT(DISTINCT CASE WHEN p.id IS NOT NULL AND t.tenggat IS NOT NULL AND p.dikumpulkan_at>t.tenggat THEN t.id END) AS submitted_late,
                COUNT(DISTINCT CASE WHEN p.nilai IS NOT NULL THEN t.id END) AS graded
            FROM tugas_pelajaran t LEFT JOIN pengumpulan_tugas p ON p.tugas_id=t.id AND p.siswa_id=?
            WHERE t.kelas_id=? AND t.tahun_ajaran_id=? AND t.status='Terbit'");
        $q->execute([$studentId,$classId,$schoolYearId]);
        $dashboard['task_stats'] = array_map('intval', $q->fetch() ?: $dashboard['task_stats']);
    }

    $scheduledSubjectsStmt = $pdo->prepare('SELECT COUNT(DISTINCT mapel_id) FROM jadwal_pelajaran WHERE kelas_id=? AND tahun_ajaran_id=?');
    $scheduledSubjectsStmt->execute([$classId,$schoolYearId]);
    $dashboard['subject_total'] = (int)$scheduledSubjectsStmt->fetchColumn();

    $q = $pdo->query("SELECT judul,isi,COALESCE(mulai_tayang,DATE(created_at)) AS tanggal
        FROM pengumuman WHERE status='Terbit'
          AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE())
          AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE())
        ORDER BY COALESCE(mulai_tayang,DATE(created_at)) DESC,id DESC LIMIT 5");
    $dashboard['announcements'] = $q->fetchAll();

    if (db_has_column($pdo, 'notifikasi', 'recipient_user_id')) {
        $noticeArchiveFilter = db_has_column($pdo, 'notifikasi', 'archived_at') ? ' AND archived_at IS NULL' : '';
        $noticeStmt = $pdo->prepare('SELECT id,judul,pesan,tautan,dibaca,created_at FROM notifikasi WHERE recipient_user_id=?' . $noticeArchiveFilter . ' ORDER BY created_at DESC,id DESC LIMIT 5');
        $noticeStmt->execute([(int)$_SESSION['user_id']]);
        $dashboard['notifications'] = $noticeStmt->fetchAll();
    }

    $calendarColumn = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name='kalender_akademik' AND column_name='tampil_publik'")->fetchColumn();
    if ((int)$calendarColumn === 1) {
        $q = $pdo->query("SELECT judul,kategori,tanggal_mulai,tanggal_selesai,status
            FROM kalender_akademik WHERE tampil_publik=1 AND status<>'Dibatalkan'
              AND tanggal_mulai>=CURDATE() AND tanggal_mulai<=DATE_ADD(CURDATE(),INTERVAL 90 DAY)
            ORDER BY tanggal_mulai LIMIT 5");
        $dashboard['agenda'] = $q->fetchAll();
    }

    $achievementTable = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name='prestasi_siswa'")->fetchColumn();
    if ((int)$achievementTable === 1) {
        $q = $pdo->prepare('SELECT nama_prestasi,bidang,tingkat,tanggal FROM prestasi_siswa WHERE siswa_id=? ORDER BY tanggal DESC,id DESC LIMIT 3');
        $q->execute([$studentId]);
        $dashboard['achievements'] = $q->fetchAll();
    }

    $lessonTable = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema=DATABASE() AND table_name='kelas_berlangsung'")->fetchColumn();
    if ((int)$lessonTable === 1) {
        $q = $pdo->prepare("SELECT kb.tanggal,kb.materi,kb.status,m.nama_mapel,g.nama AS nama_guru
            FROM kelas_berlangsung kb JOIN mapel m ON m.id=kb.mapel_id LEFT JOIN guru g ON g.id=kb.guru_id
            WHERE kb.kelas_id=? AND kb.tanggal<=CURDATE()
            ORDER BY kb.tanggal DESC,kb.id DESC LIMIT 4");
        $q->execute([$classId]);
        $dashboard['lessons'] = $q->fetchAll();
    }
}

$monthAttendanceTotal = array_sum($attendanceMonthSummary);
$monthAttendancePresent = (int)($attendanceMonthSummary['Hadir'] ?? 0);
$monthAttendanceRate = $monthAttendanceTotal > 0 ? round(100 * $monthAttendancePresent / $monthAttendanceTotal) : null;
$currentGradeAverage = null;
$currentGradeSubjectMap = [];
$currentKkmReached = 0;
foreach ($dashboard['current_grades'] as $currentGrade) {
    $subjectId = (int)$currentGrade['mapel_id'];
    if (isset($currentGradeSubjectMap[$subjectId])) continue;
    $currentGradeSubjectMap[$subjectId] = $currentGrade;
    if ((float)$currentGrade['nilai_akhir'] >= (float)$currentGrade['kkm']) $currentKkmReached++;
}
if ($currentGradeSubjectMap) {
    $currentGradeAverage = round(array_sum(array_map(static fn($item) => (float)$item['nilai_akhir'], array_values($currentGradeSubjectMap))) / count($currentGradeSubjectMap), 1);
}
$currentGradeSubjectCount = count($currentGradeSubjectMap);
$subjectTotal = (int)($dashboard['subject_total'] ?? 0);
$unpublishedSubjectCount = max(0, $subjectTotal - $currentGradeSubjectCount);
$kkmDenominator = $currentGradeSubjectCount;
$greeting = (int)date('G') < 11 ? 'Selamat pagi' : ((int)date('G') < 15 ? 'Selamat siang' : ((int)date('G') < 19 ? 'Selamat sore' : 'Selamat malam'));
$indonesianDays = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$indonesianMonths = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$todayDateLabel = ($indonesianDays[date('l')] ?? date('l')) . ', ' . date('j') . ' ' . $indonesianMonths[(int)date('n')] . ' ' . date('Y');
$latestSemesterLabel = $dashboard['current_grades'][0]['semester'] ?? 'Semester berjalan';
$studentInitial = static function (string $name): string {
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($name, 0, 1));
};
$studentShortText = static function (string $text, int $width): string {
    $text = trim(strip_tags($text));
    if (function_exists('mb_strimwidth')) return mb_strimwidth($text, 0, $width, '…', 'UTF-8');
    return strlen($text) > $width ? substr($text, 0, max(0, $width - 3)) . '...' : $text;
};

require __DIR__ . '/includes/header.php';
?>
<?php if ($view !== 'dashboard'): ?>
<div class="dashboard-heading student-dashboard-heading">
    <div>
        <span class="student-eyebrow">PORTAL AKADEMIK SMP NEGERI 4 PIRIME</span>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($student['nama']) ?> · <?= e($student['nama_kelas']) ?> · Tahun Ajaran <?= e($student['tahun_ajaran'] ?? 'belum ditetapkan') ?></p>
    </div>
    <div class="student-welcome-user">
        <?php if (!empty($_SESSION['profile_photo'])): ?><img src="media/profile.php?id=<?= (int)$_SESSION['user_id'] ?>" alt="Foto profil <?= e($student['nama']) ?>">
        <?php else: ?><span class="student-welcome-avatar"><?= e($studentInitial($student['nama'])) ?></span><?php endif; ?>
        <div><strong><?= e($student['nama_kelas']) ?></strong><small><?= e($student['tahun_ajaran'] ?? 'Tahun ajaran belum diatur') ?></small></div>
        <a class="btn btn-outline-primary btn-sm" href="biodata.php"><i class="bi bi-person-lines-fill me-1"></i>Profil</a>
    </div>
</div>
<nav class="student-shortcuts" aria-label="Akses akademik">
    <a href="student.php" class="<?= $view === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill"></i> Ringkasan</a>
    <a href="student.php?view=jadwal" class="<?= $view === 'jadwal' ? 'active' : '' ?>"><i class="bi bi-calendar-week"></i> Jadwal</a>
    <a href="student.php?view=absensi" class="<?= $view === 'absensi' ? 'active' : '' ?>"><i class="bi bi-clipboard-check"></i> Absensi</a>
    <a href="student.php?view=nilai" class="<?= $view === 'nilai' ? 'active' : '' ?>"><i class="bi bi-journal-check"></i> Nilai</a>
    <a href="learning.php?view=materi"><i class="bi bi-file-earmark-text"></i> Materi</a>
    <a href="learning.php?view=tugas"><i class="bi bi-clipboard2-check"></i> Tugas</a>
</nav>
<?php endif; ?>

<?php if ($view === 'dashboard'): ?>
    <?php require __DIR__ . '/includes/student_dashboard.php'; ?>
<?php else: ?>
    <?php if ($view === 'absensi'): ?>
        <section class="card student-panel"><div class="student-panel-heading"><div><h2>Riwayat Kehadiran</h2><p><?= count($rows) ?> catatan kehadiran siswa.</p></div></div>
            <form method="get" class="row g-2 mb-3 student-filter-form"><input type="hidden" name="view" value="absensi"><div class="col-sm-6 col-lg-3"><label class="form-label small" for="attendanceMonth">Bulan</label><input class="form-control form-control-sm" type="month" id="attendanceMonth" name="bulan" value="<?= e($attendanceMonthFilter) ?>"></div><div class="col-sm-6 col-lg-3"><label class="form-label small" for="attendanceFrom">Dari tanggal</label><input class="form-control form-control-sm" type="date" id="attendanceFrom" name="mulai" value="<?= e($attendanceStartFilter) ?>"></div><div class="col-sm-6 col-lg-3"><label class="form-label small" for="attendanceTo">Sampai tanggal</label><input class="form-control form-control-sm" type="date" id="attendanceTo" name="sampai" value="<?= e($attendanceEndFilter) ?>"></div><div class="col-sm-6 col-lg-3"><label class="form-label small" for="attendanceStatus">Status</label><select class="form-select form-select-sm" id="attendanceStatus" name="status"><option value="">Semua status</option><?php foreach (['Hadir','Izin','Sakit','Alpa'] as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= $attendanceStatusFilter === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option><?php endforeach; ?></select></div><div class="col-12 d-flex gap-2"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button><a class="btn btn-sm btn-outline-secondary" href="student.php?view=absensi">Reset</a></div></form>
            <div class="student-attendance-grid mb-3"><?php foreach (['Hadir'=>'green','Izin'=>'blue','Sakit'=>'orange','Alpa'=>'red'] as $label=>$color): ?><div class="student-attendance-item"><span class="student-dot <?= $color ?>"></span><span><?= e($label) ?></span><strong><?= (int)($attendanceSummary[$label] ?? 0) ?></strong></div><?php endforeach; ?></div>
            <div class="table-responsive"><table class="table student-table mb-0"><thead><tr><th>Tanggal</th><th>Status</th><th>Keterangan</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="3"><div class="student-empty"><i class="bi bi-clipboard-check"></i><strong>Belum ada catatan absensi</strong><span>Data akan tampil setelah guru mencatat kehadiran.</span></div></td></tr><?php else: foreach ($rows as $item): ?><tr><td><?= e(date('d/m/Y', strtotime($item['tanggal']))) ?></td><td><span class="student-status <?= $item['status'] === 'Hadir' ? 'submitted' : 'waiting' ?>"><?= e($item['status']) ?></span></td><td><?= e($item['keterangan'] ?: '—') ?></td></tr><?php endforeach; endif; ?></tbody></table></div>
        </section>
    <?php elseif ($view === 'jadwal'): ?>
        <section class="card student-panel"><div class="student-panel-heading"><div><h2>Jadwal <?= e($student['nama_kelas']) ?></h2><p>Tahun ajaran <?= e($student['tahun_ajaran'] ?? 'belum ditetapkan') ?> · <?= count($rows) ?> jam pelajaran.</p></div></div><form method="get" class="row g-2 mb-3 student-filter-form"><input type="hidden" name="view" value="jadwal"><div class="col-sm-6 col-md-4"><label for="scheduleDay" class="form-label small">Hari</label><select class="form-select form-select-sm" id="scheduleDay" name="hari"><option value="">Semua hari</option><?php foreach ($schoolDays as $day): ?><option value="<?= e($day) ?>" <?= $scheduleDayFilter === $day ? 'selected' : '' ?>><?= e($day) ?></option><?php endforeach; ?></select></div><div class="col-sm-6 col-md-4 d-flex align-items-end"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan Filter</button></div><?php if ($scheduleDayFilter !== ''): ?><div class="col-sm-6 col-md-4 d-flex align-items-end"><a class="btn btn-sm btn-outline-secondary" href="student.php?view=jadwal">Reset</a></div><?php endif; ?></form><div class="table-responsive"><table class="table student-table mb-0"><thead><tr><th>Hari</th><th>Jam</th><th>Mata Pelajaran</th><th>Guru</th><th>Ruang</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="5"><div class="student-empty"><i class="bi bi-calendar-x"></i><strong><?= $scheduleDayFilter ? 'Tidak ada jadwal pada hari ini' : 'Jadwal belum tersedia' ?></strong><span>Jadwal ini mengikuti kelas dan tahun ajaran yang tercatat pada akun Anda.</span></div></td></tr><?php else: foreach ($rows as $item): ?><tr><td><span class="student-day"><?= e($item['hari']) ?></span></td><td><?= e(substr((string)$item['jam_mulai'],0,5)) ?>–<?= e(substr((string)$item['jam_selesai'],0,5)) ?></td><td><strong><?= e($item['nama_mapel']) ?></strong></td><td><?= e($item['guru'] ?: 'Belum ditetapkan') ?></td><td><?= e($item['ruang'] ?: '—') ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    <?php else: ?>
        <section class="card student-panel"><div class="student-panel-heading"><div><h2>Rekap Nilai</h2><p>Nilai yang sudah diterbitkan untuk Anda. Draf guru tidak ditampilkan. Nilai akhir mengikuti bobot sekolah.</p></div></div><form method="get" class="row g-2 mb-3 student-filter-form"><input type="hidden" name="view" value="nilai"><div class="col-sm-6 col-lg-3"><label for="gradeYear" class="form-label small">Tahun ajaran</label><select class="form-select form-select-sm" id="gradeYear" name="tahun_ajaran_id"><option value="">Semua tahun</option><?php foreach ($gradeFilterYears as $yearOption): ?><option value="<?= (int)$yearOption['id'] ?>" <?= $gradeYearFilter === (int)$yearOption['id'] ? 'selected' : '' ?>><?= e($yearOption['nama']) ?></option><?php endforeach; ?></select></div><div class="col-sm-6 col-lg-3"><label for="gradeSemester" class="form-label small">Semester</label><select class="form-select form-select-sm" id="gradeSemester" name="semester"><option value="">Semua semester</option><option value="1" <?= $gradeSemesterFilter === '1' ? 'selected' : '' ?>>Ganjil</option><option value="2" <?= $gradeSemesterFilter === '2' ? 'selected' : '' ?>>Genap</option></select></div><div class="col-sm-6 col-lg-3"><label for="gradeSubject" class="form-label small">Mata pelajaran</label><select class="form-select form-select-sm" id="gradeSubject" name="mapel_id"><option value="">Semua mata pelajaran</option><?php foreach ($gradeFilterSubjects as $subjectOption): ?><option value="<?= (int)$subjectOption['id'] ?>" <?= $gradeSubjectFilter === (int)$subjectOption['id'] ? 'selected' : '' ?>><?= e($subjectOption['nama_mapel']) ?></option><?php endforeach; ?></select></div><div class="col-sm-6 col-lg-3 d-flex align-items-end gap-2"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button><a class="btn btn-sm btn-outline-secondary" href="student.php?view=nilai">Reset</a></div></form><div class="table-responsive"><table class="table student-table mb-0"><thead><tr><th>Mata Pelajaran</th><th>Tahun Ajaran</th><th>Semester</th><th>Tugas</th><th>UTS</th><th>UAS</th><th>Nilai Akhir</th><th>KKM</th><th>Keterangan</th></tr></thead><tbody><?php if (!$rows): ?><tr><td colspan="9"><div class="student-empty"><i class="bi bi-journal"></i><strong>Tidak ada nilai yang terbit pada filter ini</strong><span>Periksa kembali filter atau hubungi guru jika nilai seharusnya sudah diterbitkan.</span></div></td></tr><?php else: foreach ($rows as $item): $final=(float)$item['nilai_akhir']; $kkm=(float)$item['kkm']; ?><tr><td><strong><?= e($item['nama_mapel']) ?></strong></td><td><?= e($item['tahun_ajaran']) ?></td><td><?= e($item['semester']) ?></td><td><?= e(number_format((float)$item['nilai_tugas'],1)) ?></td><td><?= $item['nilai_uts'] === null ? '—' : e(number_format((float)$item['nilai_uts'],1)) ?></td><td><?= $item['nilai_uas'] === null ? e(number_format((float)$item['nilai_legacy'],1)) : e(number_format((float)$item['nilai_uas'],1)) ?></td><td><span class="student-grade <?= $final < $kkm ? 'below' : 'above' ?>"><?= e(number_format($final,1)) ?></span></td><td><?= e((string)$item['kkm']) ?></td><td><?= $final < $kkm ? '<span class="text-danger">Perlu ditingkatkan</span>' : '<span class="text-success">Tercapai</span>' ?></td></tr><?php endforeach; endif; ?></tbody></table></div></section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
