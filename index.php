<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';
// Student accounts use the dedicated, student-scoped portal for every entry URL.
// A stale `?module=...` query must not leave them on the generic dashboard.
if (current_role() === 'siswa' && (string)($_GET['module'] ?? '') !== 'notifikasi') redirect('student.php');
if (current_role() === 'guru' && empty($_GET['module'])) redirect('teacher.php');
require_once __DIR__ . '/modules/config.php';

$moduleKey = (string)($_GET['module'] ?? '');
if ($moduleKey === 'notifikasi') {
    require __DIR__ . '/notifications.php';
    exit;
}
if ($moduleKey === 'pesan_masuk') {
    require __DIR__ . '/pesan_masuk.php';
    exit;
}
if ($moduleKey === 'ppdb_admin') {
    require __DIR__ . '/ppdb_admin.php';
    exit;
}
if ($moduleKey === 'portal_admin') {
    require __DIR__ . '/portal_admin.php';
    exit;
}
if ($moduleKey === '') {
    $pageTitle = 'Dashboard';
    $today = new DateTimeImmutable('today');
    $defaultDisplayPeriod = user_display_preferences($pdo)['periode_grafik'];
    $period = (string)($_GET['periode'] ?? $defaultDisplayPeriod);
    if (!in_array($period, ['today', 'week', 'month', '3months', '6months', '12months', 'custom'], true)) $period = 'month';
    $periodLabels = ['today' => 'Hari ini', 'week' => 'Minggu ini', 'month' => '30 hari terakhir', '3months' => '3 bulan', '6months' => '6 bulan', '12months' => '12 bulan', 'custom' => 'Rentang khusus'];
    $startInput = trim((string)($_GET['mulai'] ?? ''));
    $endInput = trim((string)($_GET['sampai'] ?? ''));
    $filterError = '';
    $periodStart = match ($period) {
        'today' => $today,
        'week' => $today->modify('monday this week'),
        '3months' => $today->modify('first day of this month')->modify('-2 months'),
        '6months' => $today->modify('first day of this month')->modify('-5 months'),
        '12months' => $today->modify('first day of this month')->modify('-11 months'),
        'custom' => DateTimeImmutable::createFromFormat('!Y-m-d', $startInput) ?: $today->modify('-29 days'),
        default => $today->modify('-29 days'),
    };

    $periodEnd = match ($period) {
        'week' => $today->modify('sunday this week'),
        'custom' => DateTimeImmutable::createFromFormat('!Y-m-d', $endInput) ?: $today,
        default => $today,
    };

    if ($period === 'custom') {
        $parseDate = static fn(string $value): bool => $value !== '' && ($d = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) && $d->format('Y-m-d') === $value;
        if (!$parseDate($startInput) || !$parseDate($endInput) || $periodStart > $periodEnd || $periodStart->diff($periodEnd)->days > 365) {
            $filterError = 'Rentang khusus harus memakai tanggal valid, tanggal awal tidak boleh melewati tanggal akhir, dan maksimal satu tahun.';
            $periodStart = $today->modify('-29 days');
            $periodEnd = $today;
        }
    }

    $rangeStart = $periodStart->format('Y-m-d');
    $rangeEnd = $periodEnd->format('Y-m-d');
    $years = $pdo->query('SELECT id,nama,aktif FROM tahun_ajaran ORDER BY nama DESC')->fetchAll();
    $activeYear = '';
    $activeYearName = 'Belum diatur';
    foreach ($years as $year) if ((int)$year['aktif'] === 1) {
        $activeYear = (string)$year['id'];
        $activeYearName = (string)$year['nama'];
        break;
    }

    $schoolYearId = trim((string)($_GET['tahun_ajaran_id'] ?? $activeYear));
    $academicSemester = trim((string)($_GET['semester'] ?? ''));
    if (!in_array($academicSemester, ['', '1', '2'], true)) $academicSemester = '';
    $validYearIds = array_map(static fn($year) => (string)$year['id'], $years);
    if ($schoolYearId !== '' && !in_array($schoolYearId, $validYearIds, true)) $schoolYearId = $activeYear;

    $studentSql = "SELECT COUNT(*) FROM siswa s";
    $studentParams = [];
    if ($schoolYearId !== '') {
        $studentSql .= ' JOIN kelas k ON k.id=s.kelas_id WHERE k.tahun_ajaran_id=? AND s.status=\'Aktif\'';
        $studentParams[] = (int)$schoolYearId;
    } else {
        $studentSql .= " WHERE s.status='Aktif'";
    }

    $stmt = $pdo->prepare($studentSql);
    $stmt->execute($studentParams);
    $totalStudents = (int)$stmt->fetchColumn();
    $teacherTotal = (int)$pdo->query("SELECT COUNT(*) FROM guru WHERE status='Aktif'")->fetchColumn();
    $classSql = 'SELECT COUNT(*) FROM kelas';
    $classParams = [];
    if ($schoolYearId !== '') {
        $classSql .= ' WHERE tahun_ajaran_id=?';
        $classParams[] = (int)$schoolYearId;
    }

    $stmt = $pdo->prepare($classSql);
    $stmt->execute($classParams);
    $classTotal = (int)$stmt->fetchColumn();
    $subjectTotal = (int)$pdo->query('SELECT COUNT(*) FROM mapel')->fetchColumn();
    $schoolYearTotal = count($years);
    $adminAccountStats = ['total' => 0, 'admin' => 0, 'operator' => 0, 'kepala_sekolah' => 0, 'guru' => 0, 'siswa' => 0, 'nonaktif' => 0];
    $pendingBiodataTotal = 0;
    if (current_role() === 'operator') {
        $biodataTableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_pengguna'");
        $biodataTableCheck->execute();
        if ((int)$biodataTableCheck->fetchColumn() === 1) $pendingBiodataTotal = (int)$pdo->query("SELECT COUNT(*) FROM biodata_pengguna WHERE status_verifikasi='menunggu_verifikasi'")->fetchColumn();
    }

    if (current_role() === 'admin') {
        $accountStats = $pdo->query("SELECT COUNT(*) AS total,SUM(role='admin') AS admin,SUM(role='operator') AS operator,SUM(role='kepala_sekolah') AS kepala_sekolah,SUM(role='guru') AS guru,SUM(role='siswa') AS siswa,SUM(aktif=0) AS nonaktif FROM users")->fetch();
        if ($accountStats) foreach ($adminAccountStats as $key => $_) $adminAccountStats[$key] = (int)($accountStats[$key] ?? 0);
    }


    $studentAttendance = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
    $stmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi WHERE tanggal BETWEEN ? AND ? GROUP BY status');
    $stmt->execute([$rangeStart, $rangeEnd]);
    foreach ($stmt->fetchAll() as $row) $studentAttendance[$row['status']] = (int)$row['jumlah'];
    $teacherAttendance = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
    $stmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi_guru WHERE tanggal BETWEEN ? AND ? GROUP BY status');
    $stmt->execute([$rangeStart, $rangeEnd]);
    foreach ($stmt->fetchAll() as $row) $teacherAttendance[$row['status']] = (int)$row['jumlah'];

    $studentAttendanceTotal = array_sum($studentAttendance);
    $studentAttendanceRate = $studentAttendanceTotal > 0
        ? round(($studentAttendance['Hadir'] / $studentAttendanceTotal) * 100, 1)
        : null;
    $todayStudentAttendance = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
    $todayAttendanceRate = null;
    if (in_array(current_role(), ['admin', 'operator'], true)) {
        try {
            $todayStmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi WHERE tanggal=? GROUP BY status');
            $todayStmt->execute([$today->format('Y-m-d')]);
            foreach ($todayStmt->fetchAll() as $row) if (isset($todayStudentAttendance[$row['status']])) $todayStudentAttendance[$row['status']] = (int)$row['jumlah'];
            $todayTotal = array_sum($todayStudentAttendance);
            $todayAttendanceRate = $todayTotal > 0 ? round($todayStudentAttendance['Hadir'] * 100 / $todayTotal, 1) : null;
        } catch (PDOException $ignored) {
            $todayAttendanceRate = null;
        }
    }
    $recentOperatorStudents = [];
    $recentOperatorTeachers = [];
    $operatorClassOverview = [];
    $operatorTodaySchedule = [];
    $todayAttendanceByClass = [];
    $operatorNotifications = [];
    if (in_array(current_role(), ['admin', 'operator'], true)) {
        try {
            $recentOperatorStudents = $pdo->query("SELECT s.id,s.nis,s.nama,s.jenis_kelamin,s.status,k.nama_kelas FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id ORDER BY s.id DESC LIMIT 6")->fetchAll();
        } catch (PDOException $ignored) {
            $recentOperatorStudents = [];
        }
        try {
            $recentOperatorTeachers = $pdo->query("SELECT id,nip,nama,email,status FROM guru ORDER BY id DESC LIMIT 5")->fetchAll();
        } catch (PDOException $ignored) {
            $recentOperatorTeachers = [];
        }
        try {
            $classOverviewSql = "SELECT k.id,k.nama_kelas,COALESCE(g.nama,'Belum ditetapkan') AS wali_kelas,COUNT(CASE WHEN s.status='Aktif' THEN 1 END) AS jumlah_siswa,ta.nama AS tahun_ajaran FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id LEFT JOIN guru g ON g.id=k.wali_guru_id LEFT JOIN siswa s ON s.kelas_id=k.id";
            $classOverviewParams = [];
            if ($schoolYearId !== '') {
                $classOverviewSql .= ' WHERE k.tahun_ajaran_id=?';
                $classOverviewParams[] = (int)$schoolYearId;
            }
            $classOverviewSql .= ' GROUP BY k.id,k.nama_kelas,g.nama,ta.nama ORDER BY k.nama_kelas LIMIT 6';
            $classOverviewStmt = $pdo->prepare($classOverviewSql);
            $classOverviewStmt->execute($classOverviewParams);
            $operatorClassOverview = $classOverviewStmt->fetchAll();
        } catch (PDOException $ignored) {
            $operatorClassOverview = [];
        }
        try {
            $weekdayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
            $todayScheduleStmt = $pdo->prepare("SELECT j.jam_mulai,j.jam_selesai,j.ruang,k.nama_kelas,m.nama_mapel,g.nama AS guru FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id LEFT JOIN guru g ON g.id=j.guru_id WHERE j.hari=? ORDER BY j.jam_mulai,k.nama_kelas LIMIT 8");
            $todayScheduleStmt->execute([$weekdayNames[(int)$today->format('N')] ?? '']);
            $operatorTodaySchedule = $todayScheduleStmt->fetchAll();
        } catch (PDOException $ignored) {
            $operatorTodaySchedule = [];
        }
        try {
            $attendanceByClassStmt = $pdo->prepare("SELECT k.id,k.nama_kelas,COUNT(CASE WHEN a.status='Hadir' THEN 1 END) AS hadir,COUNT(CASE WHEN a.status='Izin' THEN 1 END) AS izin,COUNT(CASE WHEN a.status='Sakit' THEN 1 END) AS sakit,COUNT(CASE WHEN a.status='Alpa' THEN 1 END) AS alpa FROM absensi a JOIN siswa s ON s.id=a.siswa_id JOIN kelas k ON k.id=s.kelas_id WHERE a.tanggal=? GROUP BY k.id,k.nama_kelas ORDER BY k.nama_kelas LIMIT 8");
            $attendanceByClassStmt->execute([$today->format('Y-m-d')]);
            $todayAttendanceByClass = $attendanceByClassStmt->fetchAll();
        } catch (PDOException $ignored) {
            $todayAttendanceByClass = [];
        }
        try {
            $operatorNotificationsStmt = $pdo->prepare('SELECT id,judul,pesan,tautan,created_at,dibaca FROM notifikasi WHERE archived_at IS NULL AND (recipient_user_id=? OR recipient_user_id IS NULL) ORDER BY id DESC LIMIT 5');
            $operatorNotificationsStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
            $operatorNotifications = $operatorNotificationsStmt->fetchAll();
        } catch (PDOException $ignored) {
            $operatorNotifications = [];
        }
    }
    $classAttendanceRows = [];
    $gradeTrendRows = [];
    $lessonMonitoringRows = [];
    $principalAchievementRows = [];
    $principalSchoolProfile = [];
    $principalStudentGender = ['L' => null, 'P' => null];
    $principalTeacherGender = ['L' => null, 'P' => null];
    $principalTodayStudentAttendance = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];
    $principalTodayTeacherAttendance = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];
    $principalAttendanceDataAvailable = false;
    $principalUnpublishedGrades = null;
    $principalUnassignedClasses = null;
    $principalHomeroomTeachers = null;
    $principalPendingTaskReviews = null;
    $principalDraftMaterials = null;
    $principalClassesWithoutSchedule = null;
    $principalPendingBiodata = null;
    $principalTodaySchedule = [];
    $principalPendingPlans = 0;
    $principalSubmittedPlans = [];

    if (current_role() === 'kepala_sekolah') {
        try {
            $principalSchoolProfile = $pdo->query('SELECT nama,npsn,alamat,email FROM profil_sekolah ORDER BY id LIMIT 1')->fetch() ?: [];
        } catch (PDOException $ignored) {
            $principalSchoolProfile = [];
        }

        try {
            $genderStmt = $pdo->prepare("SELECT jenis_kelamin,COUNT(*) AS jumlah FROM siswa s JOIN kelas k ON k.id=s.kelas_id WHERE s.status='Aktif'" . ($schoolYearId !== '' ? ' AND k.tahun_ajaran_id=?' : '') . ' GROUP BY jenis_kelamin');
            $genderStmt->execute($schoolYearId !== '' ? [(int)$schoolYearId] : []);
            foreach ($genderStmt->fetchAll() as $row) if (isset($principalStudentGender[$row['jenis_kelamin']])) $principalStudentGender[$row['jenis_kelamin']] = (int)$row['jumlah'];
            $teacherGenderRows = $pdo->query("SELECT jenis_kelamin,COUNT(*) AS jumlah FROM guru WHERE status='Aktif' GROUP BY jenis_kelamin")->fetchAll();
            foreach ($teacherGenderRows as $row) if (isset($principalTeacherGender[$row['jenis_kelamin']])) $principalTeacherGender[$row['jenis_kelamin']] = (int)$row['jumlah'];
        } catch (PDOException $ignored) {
            $principalStudentGender = ['L' => null, 'P' => null];
            $principalTeacherGender = ['L' => null, 'P' => null];
        }

        try {
            $todayStudentSql = "SELECT a.status,COUNT(*) AS jumlah FROM absensi a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id WHERE a.tanggal=? AND s.status='Aktif'" . ($schoolYearId !== '' ? ' AND k.tahun_ajaran_id=?' : '') . ' GROUP BY a.status';
            $todayStudentStmt = $pdo->prepare($todayStudentSql);
            $todayStudentStmt->execute($schoolYearId !== '' ? [$today->format('Y-m-d'), (int)$schoolYearId] : [$today->format('Y-m-d')]);
            foreach ($todayStudentStmt->fetchAll() as $row) if (isset($principalTodayStudentAttendance[$row['status']])) $principalTodayStudentAttendance[$row['status']] = (int)$row['jumlah'];
            $todayTeacherStmt = $pdo->prepare("SELECT ag.status,COUNT(*) AS jumlah FROM absensi_guru ag JOIN guru g ON g.id=ag.guru_id WHERE ag.tanggal=? AND g.status='Aktif' GROUP BY ag.status");
            $todayTeacherStmt->execute([$today->format('Y-m-d')]);
            foreach ($todayTeacherStmt->fetchAll() as $row) if (isset($principalTodayTeacherAttendance[$row['status']])) $principalTodayTeacherAttendance[$row['status']] = (int)$row['jumlah'];
            $principalAttendanceDataAvailable = true;
        } catch (PDOException $ignored) {
            $principalAttendanceDataAvailable = false;
        }
        
        try {
            $gradeStatusSql = "SELECT COUNT(*) FROM nilai WHERE status_publikasi<>'Terbit'";
            if ($schoolYearId !== '') $gradeStatusSql .= ' AND tahun_ajaran_id=' . (int)$schoolYearId;
            $principalUnpublishedGrades = (int)$pdo->query($gradeStatusSql)->fetchColumn();
        } catch (PDOException $ignored) {
            $principalUnpublishedGrades = null;
        }
        try {
            $classCondition = $schoolYearId !== '' ? ' AND k.tahun_ajaran_id=' . (int)$schoolYearId : '';
            $principalUnassignedClasses = (int)$pdo->query('SELECT COUNT(*) FROM kelas k WHERE k.wali_guru_id IS NULL' . $classCondition)->fetchColumn();
        } catch (PDOException $ignored) {
            try {
                $principalUnassignedClasses = (int)$pdo->query('SELECT COUNT(*) FROM kelas WHERE wali_guru_id IS NULL')->fetchColumn();
            } catch (PDOException $ignoredAgain) {
                $principalUnassignedClasses = null;
            }
        }
        try {
            $principalHomeroomTeachers = (int)$pdo->query('SELECT COUNT(DISTINCT wali_guru_id) FROM kelas WHERE wali_guru_id IS NOT NULL' . ($schoolYearId !== '' ? ' AND tahun_ajaran_id=' . (int)$schoolYearId : ''))->fetchColumn();
        } catch (PDOException $ignored) {
            $principalHomeroomTeachers = null;
        }
        try {
            $taskReviewSql = 'SELECT COUNT(*) FROM pengumpulan_tugas pt JOIN tugas_pelajaran tp ON tp.id=pt.tugas_id WHERE pt.nilai IS NULL AND tp.status=\'Terbit\'';
            if ($schoolYearId !== '') $taskReviewSql .= ' AND tp.tahun_ajaran_id=' . (int)$schoolYearId;
            $principalPendingTaskReviews = (int)$pdo->query($taskReviewSql)->fetchColumn();
            $materialSql = "SELECT COUNT(*) FROM materi_pelajaran WHERE status='Draft'";
            if ($schoolYearId !== '') $materialSql .= ' AND tahun_ajaran_id=' . (int)$schoolYearId;
            $principalDraftMaterials = (int)$pdo->query($materialSql)->fetchColumn();
        } catch (PDOException $ignored) {
            $principalPendingTaskReviews = null;
            $principalDraftMaterials = null;
        }
        try {
            $scheduleSql = 'SELECT COUNT(*) FROM kelas k WHERE NOT EXISTS (SELECT 1 FROM jadwal_pelajaran j WHERE j.kelas_id=k.id)' . ($schoolYearId !== '' ? ' AND k.tahun_ajaran_id=' . (int)$schoolYearId : '');
            $principalClassesWithoutSchedule = (int)$pdo->query($scheduleSql)->fetchColumn();
        } catch (PDOException $ignored) {
            $principalClassesWithoutSchedule = null;
        }
        try {
            $principalWeekdays = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
            $principalScheduleSql = 'SELECT j.jam_mulai,j.jam_selesai,j.ruang,k.nama_kelas,m.nama_mapel,g.nama AS guru FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id LEFT JOIN guru g ON g.id=j.guru_id WHERE j.hari=?';
            $principalScheduleParams = [$principalWeekdays[(int)$today->format('N')] ?? ''];
            if ($schoolYearId !== '') {
                $principalScheduleSql .= ' AND j.tahun_ajaran_id=?';
                $principalScheduleParams[] = (int)$schoolYearId;
            }
            $principalScheduleSql .= ' ORDER BY j.jam_mulai,k.nama_kelas LIMIT 12';
            $principalScheduleStmt = $pdo->prepare($principalScheduleSql);
            $principalScheduleStmt->execute($principalScheduleParams);
            $principalTodaySchedule = $principalScheduleStmt->fetchAll();
        } catch (PDOException $ignored) {
            $principalTodaySchedule = [];
        }
        try {
            $biodataTableExists = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_pengguna'")->fetchColumn() === 1;
            if ($biodataTableExists) $principalPendingBiodata = (int)$pdo->query("SELECT COUNT(*) FROM biodata_pengguna WHERE status_verifikasi IN ('menunggu_verifikasi','perlu_perbaikan')")->fetchColumn();
        } catch (PDOException $ignored) {
            $principalPendingBiodata = null;
        }
        try {
            $pendingPlanSql = "SELECT COUNT(*) FROM rencana_ajar WHERE status='Diajukan'";
            $pendingPlanParams = [];
            if ($schoolYearId !== '') {
                $pendingPlanSql .= ' AND tahun_ajaran_id=?';
                $pendingPlanParams[] = (int)$schoolYearId;
            }
            $pendingPlanStmt = $pdo->prepare($pendingPlanSql);
            $pendingPlanStmt->execute($pendingPlanParams);
            $principalPendingPlans = (int)$pendingPlanStmt->fetchColumn();
        } catch (PDOException $ignored) {
            $principalPendingPlans = 0;
        }
        try {
            $submittedPlanSql = "SELECT ra.id,ra.judul,ra.semester,g.nama AS guru,k.nama_kelas,m.nama_mapel FROM rencana_ajar ra JOIN guru g ON g.id=ra.guru_id JOIN kelas k ON k.id=ra.kelas_id JOIN mapel m ON m.id=ra.mapel_id WHERE ra.status='Diajukan'";
            $submittedPlanParams = [];
            if ($schoolYearId !== '') {
                $submittedPlanSql .= ' AND ra.tahun_ajaran_id=?';
                $submittedPlanParams[] = (int)$schoolYearId;
            }
            $submittedPlanSql .= ' ORDER BY ra.id DESC LIMIT 5';
            $submittedPlanStmt = $pdo->prepare($submittedPlanSql);
            $submittedPlanStmt->execute($submittedPlanParams);
            $principalSubmittedPlans = $submittedPlanStmt->fetchAll();
        } catch (PDOException $ignored) {
            $principalSubmittedPlans = [];
        }
        try {
            $classAttendanceSql = "SELECT k.id,k.nama_kelas,
                COUNT(CASE WHEN a.status='Hadir' THEN 1 END) AS hadir,
                COUNT(CASE WHEN a.status='Izin' THEN 1 END) AS izin,
                COUNT(CASE WHEN a.status='Sakit' THEN 1 END) AS sakit,
                COUNT(CASE WHEN a.status='Alpa' THEN 1 END) AS alpa,
                COUNT(a.id) AS total_catatan,
                ROUND(100 * COUNT(CASE WHEN a.status='Hadir' THEN 1 END) / NULLIF(COUNT(a.id),0),1) AS persentase
                FROM kelas k
                LEFT JOIN siswa s ON s.kelas_id=k.id AND s.status='Aktif'
                LEFT JOIN absensi a ON a.siswa_id=s.id AND a.tanggal BETWEEN ? AND ?";
            $classAttendanceParams = [$rangeStart, $rangeEnd];
            if ($schoolYearId !== '') {
                $classAttendanceSql .= ' WHERE k.tahun_ajaran_id=?';
                $classAttendanceParams[] = (int)$schoolYearId;
            }
            $classAttendanceSql .= ' GROUP BY k.id,k.nama_kelas ORDER BY k.nama_kelas';
            $classAttendanceStmt = $pdo->prepare($classAttendanceSql);
            $classAttendanceStmt->execute($classAttendanceParams);
            $classAttendanceRows = $classAttendanceStmt->fetchAll();
        } catch (PDOException $ignored) {
            $classAttendanceRows = [];
        }

        try {
            $gradeColumnsStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='nilai' AND column_name IN ('nilai_uts','nilai_uas')");
            $gradeColumnsStmt->execute();
            $hasWeightedGrades = (int)$gradeColumnsStmt->fetchColumn() === 2;
            $gradeWeights = ['bobot_tugas' => 30, 'bobot_uts' => 30, 'bobot_uas' => 40];
            if ($hasWeightedGrades) {
                $weightRow = $pdo->query('SELECT bobot_tugas,bobot_uts,bobot_uas FROM profil_sekolah ORDER BY id LIMIT 1')->fetch();
                if ($weightRow) $gradeWeights = $weightRow;
            }
            $gradeExpression = $hasWeightedGrades
                ? "CASE WHEN n.nilai_uts IS NOT NULL AND n.nilai_uas IS NOT NULL THEN (n.nilai_tugas*? + n.nilai_uts*? + n.nilai_uas*?)/100 ELSE (n.nilai_tugas+n.nilai_ujian)/2 END"
                : '((n.nilai_tugas+n.nilai_ujian)/2)';
            $gradeSql = "SELECT ta.nama AS tahun_ajaran,n.semester,ROUND(AVG($gradeExpression),1) AS rata_rata,COUNT(n.id) AS jumlah_nilai FROM nilai n JOIN tahun_ajaran ta ON ta.id=n.tahun_ajaran_id";
            $gradeParams = [];
            if ($hasWeightedGrades) array_push($gradeParams, (float)$gradeWeights['bobot_tugas'], (float)$gradeWeights['bobot_uts'], (float)$gradeWeights['bobot_uas']);
            if ($schoolYearId !== '') {
                $gradeSql .= ' WHERE n.tahun_ajaran_id=?';
                $gradeParams[] = (int)$schoolYearId;
            }
            if ($academicSemester !== '') {
                $gradeSql .= $schoolYearId !== '' ? ' AND n.semester=?' : ' WHERE n.semester=?';
                $gradeParams[] = $academicSemester;
            }
            $gradeSql .= ' GROUP BY ta.id,ta.nama,ta.tanggal_mulai,n.semester ORDER BY ta.tanggal_mulai,n.semester';
            $gradeStmt = $pdo->prepare($gradeSql);
            $gradeStmt->execute($gradeParams);
            $gradeTrendRows = $gradeStmt->fetchAll();
        } catch (PDOException $ignored) {
            $gradeTrendRows = [];
        }

        try {
            $lessonSql = 'SELECT kb.tanggal,k.nama_kelas,m.nama_mapel,g.nama AS guru,kb.materi,kb.status FROM kelas_berlangsung kb JOIN kelas k ON k.id=kb.kelas_id JOIN mapel m ON m.id=kb.mapel_id LEFT JOIN guru g ON g.id=kb.guru_id WHERE kb.tanggal BETWEEN ? AND ?';
            $lessonParams = [$rangeStart, $rangeEnd];
            if ($schoolYearId !== '') {
                $lessonSql .= ' AND k.tahun_ajaran_id=?';
                $lessonParams[] = (int)$schoolYearId;
            }
            $lessonSql .= ' ORDER BY kb.tanggal DESC,kb.id DESC LIMIT 8';
            $lessonStmt = $pdo->prepare($lessonSql);
            $lessonStmt->execute($lessonParams);
            $lessonMonitoringRows = $lessonStmt->fetchAll();
        } catch (PDOException $ignored) {
            $lessonMonitoringRows = [];
        }

        try {
            $achievementSql = 'SELECT p.nama_prestasi,p.bidang,p.tingkat,p.tanggal,p.penyelenggara,s.nama AS nama_siswa,ta.nama AS tahun_ajaran FROM prestasi_siswa p LEFT JOIN siswa s ON s.id=p.siswa_id LEFT JOIN tahun_ajaran ta ON ta.id=p.tahun_ajaran_id';
            $achievementParams = [];
            if ($schoolYearId !== '') {
                $achievementSql .= ' WHERE p.tahun_ajaran_id=? OR p.tahun_ajaran_id IS NULL';
                $achievementParams[] = (int)$schoolYearId;
            }
            $achievementSql .= ' ORDER BY p.tanggal DESC,p.id DESC LIMIT 8';
            $achievementStmt = $pdo->prepare($achievementSql);
            $achievementStmt->execute($achievementParams);
            $principalAchievementRows = $achievementStmt->fetchAll();
        } catch (PDOException $ignored) {
            $principalAchievementRows = [];
        }
    }

    $stmt = $pdo->prepare("SELECT tanggal,COUNT(*) AS total,SUM(status='Hadir') AS hadir FROM absensi WHERE tanggal BETWEEN ? AND ? GROUP BY tanggal ORDER BY tanggal");
    $stmt->execute([$rangeStart, $rangeEnd]);
    $dailyRows = $stmt->fetchAll();
    $dailyMap = [];
    foreach ($dailyRows as $row) $dailyMap[$row['tanggal']] = ['total' => (int)$row['total'], 'hadir' => (int)$row['hadir']];
    $trendLabels = [];
    $trendRates = [];
    $weekBuckets = [];
    for ($date = $periodStart; $date <= $periodEnd; $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        $entry = $dailyMap[$key] ?? ['total' => 0, 'hadir' => 0];
        $trendLabels[] = $date->format('d M');
        $trendRates[] = $entry['total'] > 0 ? round($entry['hadir'] * 100 / $entry['total'], 1) : null;
        if ($entry['total'] > 0) {
            $weekKey = $date->format('o-\WW');
            if (!isset($weekBuckets[$weekKey])) $weekBuckets[$weekKey] = ['label' => 'Pekan mulai ' . $date->modify('monday this week')->format('d M'), 'hadir' => 0, 'total' => 0];
            $weekBuckets[$weekKey]['hadir'] += $entry['hadir'];
            $weekBuckets[$weekKey]['total'] += $entry['total'];
        }
    }
    $weeklyLabels = array_column(array_values($weekBuckets), 'label');
    $weeklyRates = array_map(static fn($bucket) => round($bucket['hadir'] * 100 / $bucket['total'], 1), array_values($weekBuckets));

    $studentTrendLabels = [];
    $studentTrendNew = [];
    $studentTrendTotal = [];
    $studentTrendHasData = false;
    if (in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true)) {
        $trendStart = $periodStart->modify('first day of this month');
        $trendEnd = $periodEnd->modify('first day of this month');
        $trendEndExclusive = $trendEnd->modify('+1 month');
        $monthKeys = [];
        $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        for ($month = $trendStart; $month <= $trendEnd; $month = $month->modify('+1 month')) {
            $key = $month->format('Y-m');
            $monthKeys[] = $key;
            $studentTrendLabels[] = ($monthNames[(int)$month->format('n')] ?? $month->format('M')) . ' ' . $month->format('Y');
        }
        $monthlyNewStudents = array_fill_keys($monthKeys, 0);
        try {
            $priorStudents = $pdo->prepare('SELECT COUNT(*) FROM siswa WHERE created_at < ?');
            $priorStudents->execute([$trendStart->format('Y-m-d 00:00:00')]);
            $cumulativeStudents = (int)$priorStudents->fetchColumn();
            $studentTrendStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') AS bulan,COUNT(*) AS jumlah FROM siswa WHERE created_at>=? AND created_at<? GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY bulan");
            $studentTrendStmt->execute([$trendStart->format('Y-m-d 00:00:00'), $trendEndExclusive->format('Y-m-d 00:00:00')]);
            foreach ($studentTrendStmt->fetchAll() as $row) if (isset($monthlyNewStudents[$row['bulan']])) $monthlyNewStudents[$row['bulan']] = (int)$row['jumlah'];
            foreach ($monthKeys as $key) {
                $cumulativeStudents += $monthlyNewStudents[$key];
                $studentTrendNew[] = $monthlyNewStudents[$key];
                $studentTrendTotal[] = $cumulativeStudents;
            }
            $studentTrendHasData = $cumulativeStudents > 0;
        } catch (PDOException $ignored) {
            $studentTrendLabels = [];
            $studentTrendNew = [];
            $studentTrendTotal = [];
        }
    }

    $announcements = $pdo->query("SELECT judul,isi,mulai_tayang FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE()) ORDER BY created_at DESC LIMIT 4")->fetchAll();
    $upcomingEvents = $pdo->query("SELECT judul,tanggal_mulai AS tanggal,kategori AS jenis FROM kalender_akademik WHERE tanggal_mulai>=CURDATE() AND status<>'Dibatalkan' UNION ALL SELECT nama_kegiatan,tanggal_mulai,'Kokurikuler' FROM kegiatan_kokurikuler WHERE tanggal_mulai>=CURDATE() AND status<>'Dibatalkan' ORDER BY tanggal LIMIT 5")->fetchAll();
    $schoolName = (string)$pdo->query('SELECT nama FROM profil_sekolah ORDER BY id LIMIT 1')->fetchColumn();
    if ($schoolName === '') $schoolName = 'SMP Negeri 4 Pirime';
    require __DIR__ . '/includes/header.php';
?>
    <div class="dashboard-heading">
        <div>
            <h1><?= current_role() === 'admin' ? 'Dashboard' : (current_role() === 'kepala_sekolah' ? 'Dashboard Kepala Sekolah' : 'Dashboard Operator') ?></h1>
            <p><?= current_role() === 'admin' ? e($schoolName) : (current_role() === 'kepala_sekolah' ? 'Ringkasan pemantauan sekolah dan kehadiran.' : 'Ringkasan pekerjaan administrasi sekolah.') . ' · ' . e($schoolName) ?></p>
        </div>
        <?php if (current_role() !== 'admin'): ?>
            <form method="get" class="dashboard-filter no-print">
                <div><label class="form-label small mb-1" for="tahun_ajaran_id">Tahun Ajaran</label>
                    <select class="form-select form-select-sm" id="tahun_ajaran_id" name="tahun_ajaran_id">
                        <option value="">Semua tahun</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= (int)$year['id'] ?>" <?= (string)$year['id'] === $schoolYearId ? ' selected' : '' ?>>
                                <?= e($year['nama']) ?>
                                <?= (int)$year['aktif'] === 1 ? ' · Aktif' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (current_role() === 'kepala_sekolah'): ?><div><label class="form-label small mb-1" for="semester">Semester Nilai</label><select class="form-select form-select-sm" id="semester" name="semester">
                            <option value="">Semua semester</option>
                            <option value="1" <?= $academicSemester === '1' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="2" <?= $academicSemester === '2' ? 'selected' : '' ?>>Genap</option>
                        </select></div><?php endif; ?>
                <div><label class="form-label small mb-1" for="periode">Periode Kehadiran</label>
                    <select class="form-select form-select-sm" id="periode" name="periode" onchange="this.form.submit()">
                        <?php foreach ($periodLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>"
                                <?= $period === $key ? ' selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small mb-1" for="mulai">Dari</label>
                    <input class="form-control form-control-sm" id="mulai" type="date" name="mulai" value="<?= e($periodStart->format('Y-m-d')) ?>" <?= $period === 'custom' ? '' : ' disabled' ?>>
                </div>
                <div>
                    <label class="form-label small mb-1" for="sampai">Sampai</label>
                    <input class="form-control form-control-sm" id="sampai" type="date" name="sampai" value="<?= e($periodEnd->format('Y-m-d')) ?>" <?= $period === 'custom' ? '' : ' disabled' ?>>
                </div>
                <button class="btn btn-primary btn-sm">Terapkan</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (current_role() === 'admin'): ?>
        <div class="dashboard-filter-wrap no-print mb-4">
            <form method="get" class="dashboard-filter">
                <div><label class="form-label small mb-1" for="tahun_ajaran_id">Tahun Ajaran</label><select class="form-select form-select-sm" id="tahun_ajaran_id" name="tahun_ajaran_id">
                        <option value="">Semua tahun</option><?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= (string)$year['id'] === $schoolYearId ? ' selected' : '' ?>><?= e($year['nama']) ?><?= (int)$year['aktif'] === 1 ? ' · Aktif' : '' ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="form-label small mb-1" for="periode">Periode Kehadiran</label><select class="form-select form-select-sm" id="periode" name="periode" onchange="this.form.submit()"><?php foreach ($periodLabels as $key => $label): ?><option value="<?= e($key) ?>" <?= $period === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div><label class="form-label small mb-1" for="mulai">Dari</label><input class="form-control form-control-sm" id="mulai" type="date" name="mulai" value="<?= e($periodStart->format('Y-m-d')) ?>" <?= $period === 'custom' ? '' : ' disabled' ?>></div>
                <div><label class="form-label small mb-1" for="sampai">Sampai</label><input class="form-control form-control-sm" id="sampai" type="date" name="sampai" value="<?= e($periodEnd->format('Y-m-d')) ?>" <?= $period === 'custom' ? '' : ' disabled' ?>></div>
                <button class="btn btn-primary btn-sm">Terapkan</button>
            </form>
        </div>
    <?php endif; ?>
    <?php if ($filterError !== ''): ?>
        <div class="alert alert-warning no-print">
            <?= e($filterError) ?>
        </div>
    <?php endif; ?>
    <div class="row g-3 mb-4 <?= current_role() === 'kepala_sekolah' ? 'principal-stat-grid' : (current_role() === 'admin' ? 'admin-stat-grid' : (current_role() === 'operator' ? 'dashboard-operator-stats' : '')) ?>">
        <?php $dashboardStats = current_role() === 'admin' ? [
            ['Total Siswa', $totalStudents, 'bi-mortarboard-fill', 'blue', $schoolYearId !== '' ? 'Aktif pada tahun ajaran terpilih' : 'Semua tahun ajaran'],
            ['Guru Aktif', $teacherTotal, 'bi-person-workspace', 'green', 'Data induk guru berstatus aktif'],
            ['Rombongan Belajar', $classTotal, 'bi-door-open-fill', 'orange', $schoolYearId !== '' ? 'Tahun ajaran terpilih' : 'Semua tahun ajaran'],
            ['Kehadiran Hari Ini', $todayAttendanceRate, 'bi-clipboard-check-fill', 'purple', 'Persentase dari catatan absensi hari ini'],
        ] : [
            ['Total Siswa', $totalStudents, 'bi-people-fill', 'blue', 'Aktif pada tahun terpilih'],
            ['Total Guru', $teacherTotal, 'bi-person-badge-fill', 'green', 'Guru aktif'],
            ['Rombongan Belajar', $classTotal, 'bi-door-open-fill', 'orange', $schoolYearId !== '' ? 'Tahun ajaran terpilih' : 'Semua tahun'],
            ...(current_role() === 'kepala_sekolah'
                ? [['Tahun Ajaran Aktif', $activeYearName, 'bi-calendar-range', 'purple', 'Periode akademik yang sedang berjalan'], ['Kehadiran Siswa', $studentAttendanceRate, 'bi-clipboard-check', 'green', 'Dari catatan absensi pada periode terpilih']]
                : array_merge([[current_role() === 'operator' ? 'Kehadiran Hari Ini' : 'Mata Pelajaran', current_role() === 'operator' ? $todayAttendanceRate : $subjectTotal, current_role() === 'operator' ? 'bi-clipboard-check' : 'bi-book-half', 'purple', current_role() === 'operator' ? 'Persentase dari catatan absensi hari ini' : 'Terdaftar']], current_role() === 'operator' ? [['Biodata Menunggu', $pendingBiodataTotal, 'bi-person-check', 'orange', 'Pengajuan yang perlu diperiksa']] : [])),
        ];
        foreach ($dashboardStats as [$label, $value, $icon, $color, $note]): ?>
            <div class="col-6 <?= count($dashboardStats) === 5 ? 'col-xl' : 'col-xl-3' ?>">
                <div class="card stat-card">
                    <div class="stat-card-top">
                        <span><?= e($label) ?>
                        </span>
                        <span class="stat-icon <?= e($color) ?>">
                            <i class="bi <?= e($icon) ?>">
                            </i>
                        </span>
                    </div>
                    <div class="stat-number">
                        <?= in_array($label, ['Kehadiran Siswa', 'Kehadiran Hari Ini'], true) ?
                            ($value === null ? '—' : number_format((float)$value, 1, ',', '.') . '%') : ($label === 'Tahun Ajaran Aktif' ? e($value) : number_format((int)$value, 0, ',', '.')) ?></div>
                    <div class="stat-note">
                        <?= e($note) ?></div>
                </div>
            </div><?php endforeach; ?>
    </div>

    <?php if (current_role() === 'kepala_sekolah' && ($schoolYearTotal === 0 || $totalStudents === 0 || $teacherTotal === 0 || $classTotal === 0)): ?>
        <section class="card principal-data-status mb-4" aria-labelledby="principalDataStatusTitle">
            <div class="principal-data-status-icon" aria-hidden="true">
                <i class="bi bi-info-circle-fill"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <h2 class="h5 mb-1" id="principalDataStatusTitle">Dashboard menunggu data sekolah</h2>
                <p class="mb-3 text-secondary">Ringkasan di bawah tetap berfungsi, tetapi beberapa bagian belum dapat dihitung karena data induk belum tersedia. Data tidak dibuat otomatis agar angka yang tampil tetap sesuai keadaan sekolah.</p>
                <div class="principal-data-checklist" aria-label="Status data induk sekolah">
                    <span class="principal-data-check
                    <?= $schoolYearTotal > 0 ? 'is-ready' : 'is-missing' ?>">
                        <i class="bi <?= $schoolYearTotal > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>">
                        </i>Tahun ajaran: <?= number_format($schoolYearTotal, 0, ',', '.') ?>
                    </span>
                    <span class="principal-data-check
                    <?= $totalStudents > 0 ? 'is-ready' : 'is-missing' ?>">
                        <i class="bi <?= $totalStudents > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>">
                        </i>Siswa aktif: <?= number_format($totalStudents, 0, ',', '.') ?>
                    </span>
                    <span class="principal-data-check <?= $teacherTotal > 0 ? 'is-ready' : 'is-missing' ?>">
                        <i class="bi <?= $teacherTotal > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>">
                        </i>Guru aktif: <?= number_format($teacherTotal, 0, ',', '.') ?>
                    </span>
                    <span class="principal-data-check <?= $classTotal > 0 ? 'is-ready' : 'is-missing' ?>">
                        <i class="bi <?= $classTotal > 0 ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>">
                        </i>Kelas: <?= number_format($classTotal, 0, ',', '.') ?>
                    </span>
                </div>
                <p class="small text-secondary mt-3 mb-0">Mohon Operator atau Super Admin melengkapi data tahun ajaran, kelas, guru, dan siswa. Setelah itu, jadwal, absensi, nilai, serta catatan pembelajaran akan mengisi panel terkait.</p>
            </div>
        </section>
    <?php endif; ?>

    <?php if (current_role() === 'operator'): ?>
        <section class="row g-3 mb-4 dashboard-operator-overview" aria-label="Ringkasan administrasi operator">
            <div class="col-12 col-xl-6">
                <div class="card chart-card h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="chart-title">Siswa Terbaru</h2>
                            <p class="chart-subtitle mb-0">Enam data siswa terakhir yang tercatat.</p>
                        </div><a class="btn btn-sm btn-outline-primary" href="index.php?module=siswa">Kelola siswa</a>
                    </div>
                    <?php if (!$recentOperatorStudents): ?><div class="empty-state">Belum ada data siswa.</div>
                    <?php else: ?><div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>NIS</th>
                                        <th>Nama</th>
                                        <th>Jenis Kelamin</th>
                                        <th>Kelas</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($recentOperatorStudents as $student): ?><tr>
                                            <td><?= e($student['nis'] ?: '—') ?></td>
                                            <th scope="row"><?= e($student['nama']) ?></th>
                                            <td><?= ($student['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : (($student['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : '—') ?></td>
                                            <td><?= e($student['nama_kelas'] ?? 'Belum ditempatkan') ?></td>
                                            <td><?= e($student['status']) ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </div>

            </div>
            <div class="col-12 col-xl-6">
                <div class="card chart-card h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="chart-title">Rekap Absensi Hari Ini</h2>
                            <p class="chart-subtitle mb-0"><?= e($today->format('d M Y')) ?> · hanya catatan yang sudah diinput.</p>
                        </div><a class="btn btn-sm btn-outline-primary" href="index.php?module=absensi">Buka absensi</a>
                    </div>

                    <?php if (!$todayAttendanceByClass): ?>
                        <div class="empty-state">Belum ada absensi siswa yang tercatat hari ini.</div><?php else: ?><div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Hadir</th>
                                        <th>Izin</th>
                                        <th>Sakit</th>
                                        <th>Alpa</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($todayAttendanceByClass as $attendance): ?><tr>
                                            <th scope="row"><?= e($attendance['nama_kelas']) ?></th>
                                            <td><?= (int)$attendance['hadir'] ?></td>
                                            <td><?= (int)$attendance['izin'] ?></td>
                                            <td><?= (int)$attendance['sakit'] ?></td>
                                            <td><?= (int)$attendance['alpa'] ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card chart-card h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="chart-title">Data Guru Terbaru</h2>
                            <p class="chart-subtitle mb-0">Lima profil guru terakhir yang dicatat.</p>
                        </div><a class="btn btn-sm btn-outline-primary" href="index.php?module=guru">Kelola guru</a>
                    </div>
                    <?php if (!$recentOperatorTeachers): ?>
                        <div class="empty-state">Belum ada data guru.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>NIP/NUPTK</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($recentOperatorTeachers as $teacher): ?><tr>
                                            <td><?= e($teacher['nip'] ?: '—') ?></td>
                                            <th scope="row"><?= e($teacher['nama']) ?></th>
                                            <td><?= e($teacher['email'] ?: '—') ?></td>
                                            <td><?= e($teacher['status']) ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card chart-card h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="chart-title">Data Kelas</h2>
                            <p class="chart-subtitle mb-0">Kelas pada tahun ajaran terpilih.</p>
                        </div><a class="btn btn-sm btn-outline-primary" href="index.php?module=kelas">Kelola kelas</a>
                    </div>
                    <?php if (!$operatorClassOverview): ?>
                        <div class="empty-state">Belum ada kelas pada tahun ajaran ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Wali Kelas</th>
                                        <th>Siswa Aktif</th>
                                        <th>Tahun Ajaran</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($operatorClassOverview as $class): ?><tr>
                                            <th scope="row"><?= e($class['nama_kelas']) ?></th>
                                            <td><?= e($class['wali_kelas']) ?></td>
                                            <td><?= (int)$class['jumlah_siswa'] ?></td>
                                            <td><?= e($class['tahun_ajaran']) ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="card chart-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="chart-title">Jadwal Pelajaran Hari Ini</h2>
                            <p class="chart-subtitle mb-0"><?= e($weekdayNames[(int)$today->format('N')] ?? '') ?> · jadwal terdaftar.</p>
                        </div><a class="btn btn-sm btn-outline-primary" href="reports.php?type=jadwal">Lihat seluruh jadwal</a>
                    </div>
                    <?php if (!$operatorTodaySchedule): ?>
                        <div class="empty-state">Belum ada jadwal pelajaran untuk hari ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Jam</th>
                                        <th>Kelas</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Guru</th>
                                        <th>Ruang</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($operatorTodaySchedule as $schedule): ?><tr>
                                            <td class="text-nowrap"><?= e(substr((string)$schedule['jam_mulai'], 0, 5)) ?>–<?= e(substr((string)$schedule['jam_selesai'], 0, 5)) ?></td>
                                            <td><?= e($schedule['nama_kelas']) ?></td>
                                            <td><?= e($schedule['nama_mapel']) ?></td>
                                            <td><?= e($schedule['guru'] ?: '—') ?></td>
                                            <td><?= e($schedule['ruang'] ?: '—') ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <div class="card chart-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <h2 class="chart-title">Notifikasi Administrasi</h2>
                            <p class="chart-subtitle mb-0">Pembaruan baru muncul otomatis saat notifikasi diterima.</p>
                        </div><a class="btn btn-sm btn-outline-primary" href="index.php?module=notifikasi">Lihat semua</a>
                    </div>
                    <div class="operator-dashboard-notifications" id="operatorDashboardNotifications" aria-live="polite" aria-relevant="additions text">
                        <?php if (!$operatorNotifications): ?>
                            <div class="empty-state" data-notification-empty>Belum ada notifikasi.</div>
                        <?php else: ?>
                            <?php foreach ($operatorNotifications as $notice): ?>
                                <article class="operator-notification-item" data-dashboard-notification-item="<?= (int)$notice['id'] ?>">
                                    <div><strong><?= e($notice['judul']) ?></strong>
                                        <p><?= e($notice['pesan']) ?></p>
                                    </div>
                                    <small class="text-secondary text-nowrap"><?= e(date('d M H:i', strtotime($notice['created_at']))) ?></small>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section><?php endif; ?>
    <?php if (current_role() === 'admin'): ?><div class="row g-3 mb-4 dashboard-admin-activity">
            <div class="col-xl-4">
                <section class="card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Kontrol Sistem</h2><i class="bi bi-shield-check text-primary fs-4"></i>
                    </div>
                    <details class="admin-role-breakdown mb-3">
                        <summary><span><strong><?= number_format($adminAccountStats['total'], 0, ',', '.') ?></strong> akun SIMS</span><span>Ringkasan peran <i class="bi bi-chevron-down" aria-hidden="true"></i></span></summary>
                        <dl>
                            <div>
                                <dt>Super Admin</dt>
                                <dd><?= number_format($adminAccountStats['admin'], 0, ',', '.') ?></dd>
                            </div>
                            <div>
                                <dt>Operator</dt>
                                <dd><?= number_format($adminAccountStats['operator'], 0, ',', '.') ?></dd>
                            </div>
                            <div>
                                <dt>Kepala Sekolah</dt>
                                <dd><?= number_format($adminAccountStats['kepala_sekolah'], 0, ',', '.') ?></dd>
                            </div>
                            <div>
                                <dt>Guru</dt>
                                <dd><?= number_format($adminAccountStats['guru'], 0, ',', '.') ?></dd>
                            </div>
                            <div>
                                <dt>Akun Siswa</dt>
                                <dd><?= number_format($adminAccountStats['siswa'], 0, ',', '.') ?></dd>
                            </div>
                            <div>
                                <dt>Nonaktif</dt>
                                <dd><?= number_format($adminAccountStats['nonaktif'], 0, ',', '.') ?></dd>
                            </div>
                            <div>
                                <dt>Biodata menunggu</dt>
                                <dd><?= number_format($pendingBiodataTotal, 0, ',', '.') ?></dd>
                            </div>
                        </dl>
                    </details>
                    <div class="d-grid gap-2"><a class="btn btn-outline-primary text-start" href="users.php"><i class="bi bi-person-gear me-2"></i>Manajemen Pengguna</a><a class="btn btn-outline-primary text-start" href="biodata.php"><i class="bi bi-person-check me-2"></i>Persetujuan Biodata (<?= number_format($pendingBiodataTotal, 0, ',', '.') ?> menunggu)</a><a class="btn btn-outline-primary text-start" href="passwords.php"><i class="bi bi-key me-2"></i>Reset Password</a><a class="btn btn-outline-primary text-start" href="index.php?module=guru"><i class="bi bi-person-badge me-2"></i>Data Guru</a><a class="btn btn-outline-primary text-start" href="activity.php"><i class="bi bi-clock-history me-2"></i>Log Aktivitas</a><a class="btn btn-outline-primary text-start" href="learning.php?view=materi"><i class="bi bi-mortarboard me-2"></i>Pemantauan Materi & Tugas</a><a class="btn btn-outline-primary text-start" href="index.php?module=profil_sekolah"><i class="bi bi-gear me-2"></i>Pengaturan Sekolah</a></div>
                    <section class="border rounded-3 p-3 mt-3" id="backup" aria-labelledby="dashboardBackupTitle">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div>
                                <h3 class="h6 mb-1" id="dashboardBackupTitle"><i class="bi bi-database-lock me-1 text-primary" aria-hidden="true"></i>Backup database dan berkas</h3>
                                <p class="small text-secondary mb-0">Simpan salinan <code>sims_db</code>, uploads, dan storage di luar folder aplikasi.</p>
                            </div>
                        </div>
                        <form method="post" action="backup_action.php" onsubmit="return confirm('Buat backup database dan berkas sekarang? Proses dapat memerlukan beberapa menit.');">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cloud-arrow-down me-1" aria-hidden="true"></i>Buat backup sekarang</button>
                        </form>
                    </section>
                </section>
            </div>
        </div><?php endif; ?>
    <?php if (current_role() !== 'admin' && !in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true)): ?><section class="dashboard-quick-insights mb-4" aria-labelledby="dashboardInsightsTitle">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1" id="dashboardInsightsTitle">Ikhtisar Visual Sekolah</h2>
                    <p class="small text-secondary mb-0">Ringkasan data utama dan kehadiran · <?= e($periodLabels[$period]) ?></p>
                </div><a class="small text-decoration-none" href="reports.php">Buka laporan lengkap <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <article class="card chart-card dashboard-mini-chart h-100">
                        <h3 class="chart-title">Komposisi Akun</h3>
                        <p class="chart-subtitle">Pengguna terdaftar menurut peran, termasuk akun nonaktif.</p><?php $accountRoleRows = [['Super Admin', $adminAccountStats['admin'], '#6c63ff'], ['Operator', $adminAccountStats['operator'], '#f0a43a'], ['Kepala Sekolah', $adminAccountStats['kepala_sekolah'], '#e76f9a'], ['Guru', $adminAccountStats['guru'], '#22a889'], ['Siswa', $adminAccountStats['siswa'], '#3988e8']];
                                                                                                                $accountRoleMax = max(1, ...array_column($accountRoleRows, 1)); ?><div class="dashboard-mini-bars" role="list" aria-label="Jumlah akun menurut peran"><?php foreach ($accountRoleRows as [$roleLabel, $roleCount, $roleColor]): ?><div class="dashboard-mini-bar-row" role="listitem"><span><?= e($roleLabel) ?></span>
                                    <div class="dashboard-mini-track"><i style="--bar-width:<?= round((int)$roleCount * 100 / $accountRoleMax, 1) ?>%;--bar-color:<?= e($roleColor) ?>"></i></div><strong><?= number_format((int)$roleCount, 0, ',', '.') ?></strong>
                                </div><?php endforeach; ?></div>
                        <div class="dashboard-mini-foot"><span><?= number_format($adminAccountStats['total'], 0, ',', '.') ?> akun total</span><span><?= number_format($adminAccountStats['nonaktif'], 0, ',', '.') ?> nonaktif</span></div>
                    </article>
                </div>
                <div class="col-12 col-xl-6">
                    <article class="card chart-card dashboard-mini-chart h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h3 class="chart-title">Status Kehadiran Siswa</h3>
                                <p class="chart-subtitle">Catatan absensi · <?= e($rangeStart) ?> – <?= e($rangeEnd) ?></p>
                            </div><span class="dashboard-mini-total"><?= number_format($studentAttendanceTotal, 0, ',', '.') ?><small>catatan</small></span>
                        </div><?php $attendanceColors = ['Hadir' => '#22a889', 'Izin' => '#f0a43a', 'Sakit' => '#3988e8', 'Alpa' => '#e76f6a'];
                                $attendanceMax = max(1, ...array_values($studentAttendance)); ?><?php if ($studentAttendanceTotal > 0): ?><div class="dashboard-mini-bars" role="list" aria-label="Jumlah absensi menurut status"><?php foreach ($studentAttendance as $attendanceLabel => $attendanceCount): ?><div class="dashboard-mini-bar-row" role="listitem"><span><?= e($attendanceLabel) ?></span>
                                    <div class="dashboard-mini-track"><i style="--bar-width:<?= round((int)$attendanceCount * 100 / $attendanceMax, 1) ?>%;--bar-color:<?= e($attendanceColors[$attendanceLabel]) ?>"></i></div><strong><?= number_format($attendanceCount, 0, ',', '.') ?></strong>
                                </div><?php endforeach; ?></div>
                        <div class="dashboard-mini-foot"><span>Tingkat hadir</span><strong><?= $studentAttendanceRate === null ? '—' : number_format($studentAttendanceRate, 1, ',', '.') . '%' ?></strong></div><?php else: ?><div class="dashboard-mini-empty"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Belum ada catatan absensi siswa pada periode ini.</span></div><?php endif; ?>
                    </article>
                </div>
                <div class="col-12">
                    <article class="card chart-card dashboard-mini-chart dashboard-data-health">
                        <h3 class="chart-title">Kesiapan Data Inti</h3>
                        <p class="chart-subtitle">Kelompok data yang sudah tercatat.</p><?php $healthRows = [['Siswa aktif', $totalStudents, 'bi-mortarboard', 'blue'], ['Guru aktif', $teacherTotal, 'bi-person-workspace', 'green'], ['Kelas', $classTotal, 'bi-door-open', 'orange'], ['Mata pelajaran', $subjectTotal, 'bi-book-half', 'purple']]; ?><div class="dashboard-health-grid"><?php foreach ($healthRows as [$healthLabel, $healthCount, $healthIcon, $healthColor]): ?><div class="dashboard-health-item"><span class="stat-icon <?= e($healthColor) ?>"><i class="bi <?= e($healthIcon) ?>" aria-hidden="true"></i></span>
                                    <div><strong><?= number_format((int)$healthCount, 0, ',', '.') ?></strong><small><?= e($healthLabel) ?></small></div>
                                </div><?php endforeach; ?></div>
                    </article>
                </div>
            </div>
        </section><?php endif; ?>

    <?php if (in_array(current_role(), ['admin', 'operator', 'kepala_sekolah'], true)): ?><div class="row g-3 mb-4 dashboard-analytics-primary">
            <div class="col-12 col-xl-8">
                <section class="card chart-card student-trend-card" aria-labelledby="studentIntakeTitle">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="chart-title" id="studentIntakeTitle">Tren Data Siswa</h2>
                            <p class="chart-subtitle mb-0">Data baru dicatat dan akumulasi data siswa berdasarkan waktu pencatatan di SIMS.</p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="chart-range-tabs" role="group" aria-label="Rentang grafik tren siswa">
                                <?php foreach (['12months' => '12 Bulan', '6months' => '6 Bulan', '3months' => '3 Bulan'] as $rangeKey => $rangeLabel): ?>
                                    <a class="chart-range-tab<?= $period === $rangeKey ? ' is-active' : '' ?>" href="index.php?<?= e(http_build_query(['periode' => $rangeKey, 'tahun_ajaran_id' => $schoolYearId, 'semester' => $academicSemester])) ?>" <?= $period === $rangeKey ? ' aria-current="true"' : '' ?>><?= e($rangeLabel) ?></a>
                                <?php endforeach; ?>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="reports.php?type=siswa">Lihat laporan</a>
                        </div>
                    </div>
                    <div class="chart-canvas student-trend-canvas"><?php if (!$studentTrendHasData): ?><div class="empty-chart empty-chart-plot">
                                <div class="empty-chart-message"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><strong>Grafik siswa menunggu data</strong><span>Tambahkan data siswa agar tren pencatatan dan akumulasi tampil di sini.</span><?php if (can_manage_module('siswa')): ?><a class="btn btn-sm btn-primary" href="index.php?module=siswa"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Kelola data siswa</a><?php endif; ?></div>
                            </div><?php else: ?><canvas id="studentIntakeTrend" role="img" aria-label="Grafik data siswa baru dicatat dan akumulasi siswa"></canvas><?php endif; ?></div>
                </section>
            </div>
            <div class="col-12 col-xl-4">
                <section class="card chart-card h-100" aria-labelledby="studentAttendanceSummaryTitle">
                    <h2 class="chart-title" id="studentAttendanceSummaryTitle">Ringkasan Kehadiran Siswa</h2>
                    <p class="chart-subtitle">Status absensi · <?= e($rangeStart) ?> – <?= e($rangeEnd) ?></p>
                    <div class="donut-layout">
                        <?php if (array_sum($studentAttendance) === 0): ?><div class="empty-chart empty-chart-plot w-100">
                                <div class="empty-chart-message"><i class="bi bi-pie-chart" aria-hidden="true"></i><strong>Belum ada catatan</strong><span>Ringkasan terisi setelah absensi siswa dicatat.</span></div>
                            </div><?php else: ?><div class="chart-canvas"><canvas id="studentDonut" role="img" aria-label="Ringkasan status absensi siswa"></canvas></div>
                            <ul class="chart-legend" id="studentLegend"></ul><?php endif; ?>
                    </div>
                </section>
            </div>
        </div><?php endif; ?>
    <div class="row g-3 mb-3 dashboard-analytics-secondary">
        <div class="col-12 col-xl-8">
            <div class="card chart-card h-100">
                <div>
                    <h2 class="chart-title">Tren Kehadiran Siswa</h2>
                    <div class="chart-subtitle">Persentase hadir harian · <?= e($periodLabels[$period]) ?> · <?= e($rangeStart) ?> sampai <?= e($rangeEnd) ?></div>
                </div>
                <div class="chart-canvas"><?php if (!$dailyRows): ?><div class="empty-chart empty-chart-plot">
                            <div class="empty-chart-message"><i class="bi bi-activity" aria-hidden="true"></i><strong>Belum ada tren absensi</strong><span>Grafik akan mengikuti filter periode setelah data kehadiran siswa dicatat.</span></div>
                        </div><?php else: ?><canvas id="attendanceTrend" role="img" aria-label="Tren persentase kehadiran siswa"></canvas><?php endif; ?></div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card chart-card h-100">
                <h2 class="chart-title">Kehadiran Guru</h2>
                <div class="chart-subtitle">Rekap status · <?= e($rangeStart) ?> – <?= e($rangeEnd) ?></div>
                <div class="donut-layout">
                    <?php if (array_sum($teacherAttendance) === 0): ?><div class="empty-chart empty-chart-plot w-100">
                            <div class="empty-chart-message"><i class="bi bi-person-check" aria-hidden="true"></i><strong>Belum ada catatan</strong><span>Ringkasan terisi setelah absensi guru dicatat.</span></div>
                        </div><?php else: ?><div class="chart-canvas"><canvas id="teacherDonut" role="img" aria-label="Ringkasan status absensi guru"></canvas></div>
                        <ul class="chart-legend" id="teacherLegend"></ul><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 dashboard-analytics-tertiary">
        <div class="col-12">
            <div class="card chart-card">
                <h2 class="chart-title">Persentase Kehadiran Bulanan</h2>
                <div class="chart-subtitle">Perbandingan per pekan dalam rentang yang dipilih</div>
                <div class="chart-canvas compact"><?php if (!$weeklyRates): ?><div class="empty-chart empty-chart-plot">
                            <div class="empty-chart-message"><i class="bi bi-calendar-week" aria-hidden="true"></i><strong>Belum ada data mingguan</strong><span>Masukkan catatan absensi untuk membandingkan kehadiran tiap pekan.</span></div>
                        </div><?php else: ?><canvas id="weeklyAttendance" role="img" aria-label="Perbandingan persentase kehadiran per pekan"></canvas><?php endif; ?></div>
            </div>
        </div>
    </div>
    <?php if (current_role() === 'admin'): ?>
        <section class="dashboard-quick-insights mb-4" aria-labelledby="dashboardInsightsTitle">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1" id="dashboardInsightsTitle">Ikhtisar Visual Sekolah</h2>
                    <p class="small text-secondary mb-0">Ringkasan data utama dan kehadiran · <?= e($periodLabels[$period]) ?></p>
                </div><a class="small text-decoration-none" href="reports.php">Buka laporan lengkap <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <article class="card chart-card dashboard-mini-chart h-100">
                        <h3 class="chart-title">Komposisi Akun</h3>
                        <p class="chart-subtitle">Pengguna terdaftar menurut peran, termasuk akun nonaktif.</p><?php $accountRoleRows = [['Super Admin', $adminAccountStats['admin'], '#6c63ff'], ['Operator', $adminAccountStats['operator'], '#f0a43a'], ['Kepala Sekolah', $adminAccountStats['kepala_sekolah'], '#e76f9a'], ['Guru', $adminAccountStats['guru'], '#22a889'], ['Siswa', $adminAccountStats['siswa'], '#3988e8']];
                                                                                                                $accountRoleMax = max(1, ...array_column($accountRoleRows, 1)); ?><div class="dashboard-mini-bars" role="list" aria-label="Jumlah akun menurut peran"><?php foreach ($accountRoleRows as [$roleLabel, $roleCount, $roleColor]): ?><div class="dashboard-mini-bar-row" role="listitem"><span><?= e($roleLabel) ?></span>
                                    <div class="dashboard-mini-track"><i style="--bar-width:<?= round((int)$roleCount * 100 / $accountRoleMax, 1) ?>%;--bar-color:<?= e($roleColor) ?>"></i></div><strong><?= number_format((int)$roleCount, 0, ',', '.') ?></strong>
                                </div><?php endforeach; ?></div>
                        <div class="dashboard-mini-foot"><span><?= number_format($adminAccountStats['total'], 0, ',', '.') ?> akun total</span><span><?= number_format($adminAccountStats['nonaktif'], 0, ',', '.') ?> nonaktif</span></div>
                    </article>
                </div>
                <div class="col-12 col-xl-6">
                    <article class="card chart-card dashboard-mini-chart h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h3 class="chart-title">Status Kehadiran Siswa</h3>
                                <p class="chart-subtitle">Catatan absensi · <?= e($rangeStart) ?> – <?= e($rangeEnd) ?></p>
                            </div><span class="dashboard-mini-total"><?= number_format($studentAttendanceTotal, 0, ',', '.') ?><small>catatan</small></span>
                        </div><?php $attendanceColors = ['Hadir' => '#22a889', 'Izin' => '#f0a43a', 'Sakit' => '#3988e8', 'Alpa' => '#e76f6a'];
                                $attendanceMax = max(1, ...array_values($studentAttendance)); ?><?php if ($studentAttendanceTotal > 0): ?><div class="dashboard-mini-bars" role="list" aria-label="Jumlah absensi menurut status"><?php foreach ($studentAttendance as $attendanceLabel => $attendanceCount): ?><div class="dashboard-mini-bar-row" role="listitem"><span><?= e($attendanceLabel) ?></span>
                                    <div class="dashboard-mini-track"><i style="--bar-width:<?= round((int)$attendanceCount * 100 / $attendanceMax, 1) ?>%;--bar-color:<?= e($attendanceColors[$attendanceLabel]) ?>"></i></div><strong><?= number_format($attendanceCount, 0, ',', '.') ?></strong>
                                </div><?php endforeach; ?></div>
                        <div class="dashboard-mini-foot"><span>Tingkat hadir</span><strong><?= $studentAttendanceRate === null ? '—' : number_format($studentAttendanceRate, 1, ',', '.') . '%' ?></strong></div><?php else: ?><div class="dashboard-mini-empty"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Belum ada catatan absensi siswa pada periode ini.</span></div><?php endif; ?>
                    </article>
                </div>
                <div class="col-12">
                    <article class="card chart-card dashboard-mini-chart dashboard-data-health">
                        <h3 class="chart-title">Kesiapan Data Inti</h3>
                        <p class="chart-subtitle">Kelompok data yang sudah tercatat.</p><?php $healthRows = [['Siswa aktif', $totalStudents, 'bi-mortarboard', 'blue'], ['Guru aktif', $teacherTotal, 'bi-person-workspace', 'green'], ['Kelas', $classTotal, 'bi-door-open', 'orange'], ['Mata pelajaran', $subjectTotal, 'bi-book-half', 'purple']]; ?><div class="dashboard-health-grid"><?php foreach ($healthRows as [$healthLabel, $healthCount, $healthIcon, $healthColor]): ?><div class="dashboard-health-item"><span class="stat-icon <?= e($healthColor) ?>"><i class="bi <?= e($healthIcon) ?>" aria-hidden="true"></i></span>
                                    <div><strong><?= number_format((int)$healthCount, 0, ',', '.') ?></strong><small><?= e($healthLabel) ?></small></div>
                                </div><?php endforeach; ?></div>
                    </article>
                </div>
            </div>
        </section><?php endif; ?>
    <div class="row g-3 mb-4 dashboard-announcement-agenda">
        <div class="col-lg-6">
            <div class="card chart-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="chart-title">Pengumuman</h2><?php if (can_view_module('pengumuman')): ?><a class="small text-decoration-none" href="index.php?module=pengumuman">Lihat semua</a><?php endif; ?>
                </div><?php if (!$announcements): ?><div class="empty-state">Belum ada pengumuman yang diterbitkan.</div><?php else: foreach ($announcements as $item): ?><article class="announcement-item"><strong><?= e($item['judul']) ?></strong><small><?= e($item['mulai_tayang'] ?: 'Tayang segera') ?></small>
                            <p><?= e($item['isi']) ?></p>
                        </article><?php endforeach;
                                                                                                                        endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card chart-card">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="chart-title">Agenda Mendatang</h2><?php if (can_view_module('kalender_akademik')): ?><a class="small text-decoration-none" href="index.php?module=kalender_akademik">Kalender</a><?php endif; ?>
                </div><?php if (!$upcomingEvents): ?><div class="empty-state">Belum ada agenda mendatang.</div><?php else: foreach ($upcomingEvents as $item): ?><article class="agenda-item"><strong><?= e($item['judul']) ?></strong><small><?= e($item['jenis']) ?> · <?= e(date('d M Y', strtotime($item['tanggal']))) ?></small></article><?php endforeach;
                                                                                                                                                                                                                                                                                                                                        endif; ?>
            </div>
        </div>
    </div>
    <?php if (current_role() === 'kepala_sekolah'): ?>
        <section class="mb-4 dashboard-principal-monitoring" aria-labelledby="principal-monitoring-title">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1" id="principal-monitoring-title">Ringkasan Pemantauan Sekolah</h2>
                    <p class="text-secondary mb-0">Data periode <?= e($rangeStart) ?> sampai <?= e($rangeEnd) ?><?= $schoolYearId !== '' ? ' · Tahun ajaran terpilih' : '' ?>.</p>
                </div>
                <a class="btn btn-outline-primary btn-sm align-self-start align-self-md-auto" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-1"></i>Buka semua laporan</a>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-5">
                    <div class="card chart-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h3 class="chart-title">Identitas Sekolah</h3>
                                <p class="chart-subtitle mb-0">Data profil sekolah yang tercatat.</p>
                            </div><?php if (can_view_module('profil_sekolah')): ?><a class="small text-decoration-none" href="index.php?module=profil_sekolah">Lihat profil</a><?php endif; ?>
                        </div>
                        <dl class="principal-school-facts mb-0">
                            <div>
                                <dt>Nama sekolah</dt>
                                <dd><?= e($principalSchoolProfile['nama'] ?? 'Belum tersedia') ?></dd>
                            </div>
                            <div>
                                <dt>NPSN</dt>
                                <dd><?= e($principalSchoolProfile['npsn'] ?: 'Belum tersedia') ?></dd>
                            </div>
                            <div>
                                <dt>Tahun ajaran</dt>
                                <dd><?= e($activeYearName !== '' ? $activeYearName : 'Belum diatur') ?></dd>
                            </div>
                            <div>
                                <dt>Alamat</dt>
                                <dd><?= e($principalSchoolProfile['alamat'] ?: 'Belum tersedia') ?></dd>
                            </div>
                            <div>
                                <dt>Email sekolah</dt>
                                <dd><?= e($principalSchoolProfile['email'] ?: 'Belum tersedia') ?></dd>
                            </div>
                        </dl>
                        <p class="small text-secondary mt-2 mb-0">Semester aktif dan data status operasional belum tersedia sebagai kolom khusus pada profil sekolah.</p>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card chart-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h3 class="chart-title">Komposisi Warga Sekolah</h3>
                                <p class="chart-subtitle mb-0">Jumlah dari data induk aktif · tahun ajaran <?= e($activeYearName ?: 'belum dipilih') ?>.</p>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <div class="principal-mini-stat"><small>Siswa laki-laki</small><strong><?= $principalStudentGender['L'] === null ? 'Belum tersedia' : number_format($principalStudentGender['L'], 0, ',', '.') ?></strong></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="principal-mini-stat"><small>Siswa perempuan</small><strong><?= $principalStudentGender['P'] === null ? 'Belum tersedia' : number_format($principalStudentGender['P'], 0, ',', '.') ?></strong></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="principal-mini-stat"><small>Guru wali kelas</small><strong><?= $principalHomeroomTeachers === null ? 'Belum tersedia' : number_format($principalHomeroomTeachers, 0, ',', '.') ?></strong></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="principal-mini-stat"><small>Mata pelajaran</small><strong><?= number_format($subjectTotal, 0, ',', '.') ?></strong></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="principal-mini-stat"><small>Guru laki-laki</small><strong><?= $principalTeacherGender['L'] === null ? 'Belum tersedia' : number_format($principalTeacherGender['L'], 0, ',', '.') ?></strong></div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="principal-mini-stat"><small>Guru perempuan</small><strong><?= $principalTeacherGender['P'] === null ? 'Belum tersedia' : number_format($principalTeacherGender['P'], 0, ',', '.') ?></strong></div>
                            </div>
                        </div>
                        <p class="small text-secondary mt-3 mb-0">Data tenaga kependidikan non-guru dikelola terpisah agar data guru dan relasi yang sudah ada tetap aman.</p>
                        <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-sm btn-outline-primary" href="reports.php?type=siswa"><i class="bi bi-people me-1"></i>Data siswa</a><a class="btn btn-sm btn-outline-primary" href="reports.php?type=guru"><i class="bi bi-person-badge me-1"></i>Data guru</a><?php if (can_view_module('staf')): ?><a class="btn btn-sm btn-outline-primary" href="index.php?module=staf"><i class="bi bi-person-workspace me-1"></i>Tenaga kependidikan</a><?php endif; ?><a class="btn btn-sm btn-outline-primary" href="reports.php?type=kelas"><i class="bi bi-door-open me-1"></i>Data kelas</a></div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-3" aria-label="Ringkasan kehadiran hari ini">
                <?php $studentMarkedToday = array_sum($principalTodayStudentAttendance);
                $teacherMarkedToday = array_sum($principalTodayTeacherAttendance); ?>
                <?php foreach ([['Kehadiran Siswa · hari ini', $principalTodayStudentAttendance, $totalStudents, $studentMarkedToday, 'reports.php?type=absensi'], ['Kehadiran Guru · hari ini', $principalTodayTeacherAttendance, $teacherTotal, $teacherMarkedToday, 'reports.php?type=absensi_guru']] as [$attendanceTitle, $attendanceCounts, $expectedCount, $markedCount, $attendanceLink]): ?>
                    <div class="col-12 col-xl-6">
                        <div class="card chart-card h-100">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <h3 class="chart-title"><?= e($attendanceTitle) ?></h3>
                                    <p class="chart-subtitle">Hanya catatan absensi yang sudah dimasukkan per <?= e($today->format('d/m/Y')) ?>.</p>
                                </div><a class="small text-decoration-none" href="<?= e($attendanceLink) ?>">Lihat laporan</a>
                            </div>
                            <?php if (!$principalAttendanceDataAvailable): ?><div class="empty-state">Data kehadiran hari ini belum tersedia.</div><?php else: ?><div class="principal-attendance-summary"><?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $attendanceStatus): ?><div><small><?= e($attendanceStatus) ?></small><strong><?= number_format($attendanceCounts[$attendanceStatus], 0, ',', '.') ?></strong></div><?php endforeach; ?><div class="is-pending"><small>Belum tercatat*</small><strong><?= number_format(max(0, $expectedCount - $markedCount), 0, ',', '.') ?></strong></div>
                                </div>
                                <p class="small text-secondary mb-0 mt-2">*Perkiraan selisih jumlah data induk aktif dengan catatan hari ini; periksa laporan untuk tindak lanjut.</p><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mb-3" aria-label="Daftar tindak lanjut kepala sekolah">
                <?php foreach (
                    [
                        ['Nilai belum diterbitkan', $principalUnpublishedGrades, 'bi-journal-x', 'reports.php?type=nilai', 'Tahun ajaran terpilih'],
                        ['Tugas menunggu penilaian', $principalPendingTaskReviews, 'bi-clipboard2-check', 'reports.php?type=pengumpulan&status=belum', 'Tugas terbit tanpa nilai'],
                        ['Kelas tanpa jadwal', $principalClassesWithoutSchedule, 'bi-calendar-x', 'reports.php?type=jadwal', 'Tahun ajaran terpilih'],
                        ['Materi masih draf', $principalDraftMaterials, 'bi-file-earmark-text', 'reports.php?type=materi&status=Draft', 'Perlu ditinjau guru'],
                        ['Biodata perlu pemeriksaan', $principalPendingBiodata, 'bi-person-check', '', 'Perlu ditindaklanjuti Operator'],
                        ['Kelas tanpa wali', $principalUnassignedClasses, 'bi-people', 'reports.php?type=kelas', 'Tahun ajaran terpilih'],
                    ] as [$actionLabel, $actionValue, $actionIcon, $actionUrl, $actionNote]
                ): ?>
                    <div class="col-6 col-xl-4">
                        <article class="card principal-action-card h-100"><span class="principal-action-icon"><i class="bi <?= e($actionIcon) ?>"></i></span>
                            <div><span class="principal-action-label"><?= e($actionLabel) ?></span><strong><?= $actionValue === null ? 'Belum tersedia' : number_format((int)$actionValue, 0, ',', '.') ?></strong><small><?= e($actionNote) ?></small></div><?php if ($actionUrl !== ''): ?><a href="<?= e($actionUrl) ?>" aria-label="Buka <?= e($actionLabel) ?>"><i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-xl-6">
                    <div class="card chart-card h-100">
                        <h3 class="chart-title">Rekap Kehadiran per Kelas</h3>
                        <p class="chart-subtitle">Persentase hadir dihitung dari catatan absensi yang tersedia.</p>
                        <?php if (!$classAttendanceRows): ?><div class="empty-state">Belum ada data kelas atau absensi untuk ditampilkan.</div><?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kelas</th>
                                            <th>Hadir</th>
                                            <th>Izin</th>
                                            <th>Sakit</th>
                                            <th>Alpa</th>
                                            <th>Hadir (%)</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($classAttendanceRows as $row): ?><tr>
                                                <th scope="row"><?= e($row['nama_kelas']) ?></th>
                                                <td><?= (int)$row['hadir'] ?></td>
                                                <td><?= (int)$row['izin'] ?></td>
                                                <td><?= (int)$row['sakit'] ?></td>
                                                <td><?= (int)$row['alpa'] ?></td>
                                                <td><?= $row['persentase'] === null ? '—' : e(number_format((float)$row['persentase'], 1, ',', '.') . '%') ?></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card chart-card h-100">
                        <h3 class="chart-title">Perkembangan Rata-rata Nilai</h3>
                        <p class="chart-subtitle">Ringkasan nilai tersimpan per semester, skala 0–100.</p>
                        <div class="chart-canvas compact"><?php if (!$gradeTrendRows): ?><div class="empty-chart">Belum ada nilai pada tahun ajaran terpilih.</div><?php else: ?><canvas id="principalGradeTrend" role="img" aria-label="Grafik perkembangan rata-rata nilai per semester"></canvas><?php endif; ?></div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <div class="card chart-card">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <h3 class="chart-title">Jadwal Pelajaran Hari Ini</h3>
                                <p class="chart-subtitle mb-0"><?= e($principalWeekdays[(int)$today->format('N')] ?? '') ?> · <?= e($today->format('d/m/Y')) ?> · sumber: jadwal kelas.</p>
                            </div><a class="small text-decoration-none" href="reports.php?type=jadwal">Lihat jadwal lengkap</a>
                        </div>
                        <?php if (!$principalTodaySchedule): ?><div class="empty-state">Belum ada jadwal yang tercatat untuk hari ini.</div><?php else: ?><div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Kelas</th>
                                            <th>Mata pelajaran</th>
                                            <th>Guru</th>
                                            <th>Ruang</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($principalTodaySchedule as $schedule): ?><tr>
                                                <td class="text-nowrap"><?= e(substr((string)$schedule['jam_mulai'], 0, 5)) ?>–<?= e(substr((string)$schedule['jam_selesai'], 0, 5)) ?></td>
                                                <td><?= e($schedule['nama_kelas']) ?></td>
                                                <td><?= e($schedule['nama_mapel']) ?></td>
                                                <td><?= e($schedule['guru'] ?: 'Belum ditentukan') ?></td>
                                                <td><?= e($schedule['ruang'] ?: '—') ?></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div><?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-xl-8">
                    <div class="card chart-card h-100">
                        <h3 class="chart-title">Pemantauan Pembelajaran</h3>
                        <p class="chart-subtitle">Catatan kelas berlangsung/terjadwal pada periode yang dipilih.</p>
                        <?php if (!$lessonMonitoringRows): ?><div class="empty-state">Belum ada catatan pembelajaran pada periode ini.</div><?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Kelas</th>
                                            <th>Mata pelajaran</th>
                                            <th>Guru</th>
                                            <th>Materi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lessonMonitoringRows as $lesson): ?><tr>
                                                <td class="text-nowrap"><?= e(date('d-m-Y', strtotime($lesson['tanggal']))) ?></td>
                                                <td><?= e($lesson['nama_kelas']) ?></td>
                                                <td><?= e($lesson['nama_mapel']) ?></td>
                                                <td><?= e($lesson['guru'] ?? '—') ?></td>
                                                <td><?= e($lesson['materi'] ?: '—') ?></td>
                                                <td><?= e($lesson['status'] ?: '—') ?></td>
                                            </tr><?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="card chart-card h-100">
                        <h3 class="chart-title">Akses Cepat Laporan</h3>
                        <p class="chart-subtitle">Pilih laporan untuk meninjau data sekolah lebih rinci.</p>
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary text-start" href="reports.php?type=absensi"><i class="bi bi-calendar-check me-2"></i>Laporan kehadiran siswa</a>
                            <a class="btn btn-outline-primary text-start" href="reports.php?type=absensi_guru"><i class="bi bi-person-check me-2"></i>Laporan kehadiran guru</a>
                            <a class="btn btn-outline-primary text-start" href="reports.php?type=nilai"><i class="bi bi-journal-check me-2"></i>Laporan nilai</a>
                            <a class="btn btn-outline-primary text-start" href="reports.php?type=kelas"><i class="bi bi-people me-2"></i>Laporan kelas</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-12 col-md-4">
                    <div class="card principal-action-card h-100">
                        <span class="principal-action-icon"><i class="bi bi-journal-check"></i></span>
                        <div><span class="principal-action-label">Rencana ajar menunggu tinjauan</span><strong><?= number_format($principalPendingPlans, 0, ',', '.') ?></strong><small>Pengajuan pada tahun ajaran terpilih</small></div>
                        <?php if (can_view_module('rencana_ajar')): ?><a href="index.php?module=rencana_ajar" aria-label="Buka rencana ajar"><i class="bi bi-arrow-up-right"></i></a><?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-md-8">
                    <div class="card chart-card h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h3 class="chart-title">Pengajuan Rencana Ajar</h3>
                                <p class="chart-subtitle mb-0">Pengajuan guru yang perlu ditinjau.</p>
                            </div><?php if (can_view_module('rencana_ajar')): ?><a class="small text-decoration-none" href="index.php?module=rencana_ajar">Buka semua</a><?php endif; ?>
                        </div>
                        <?php if (!$principalSubmittedPlans): ?><div class="empty-state">Tidak ada pengajuan rencana ajar yang menunggu tinjauan.</div><?php else: ?><div class="principal-activity-list"><?php foreach ($principalSubmittedPlans as $plan): ?><article><span class="principal-activity-dot" aria-hidden="true"></span>
                                        <div><strong><?= e($plan['judul']) ?></strong><small><?= e($plan['guru']) ?> · <?= e($plan['nama_kelas']) ?> · <?= e($plan['nama_mapel']) ?></small>
                                            <p>Semester <?= e($plan['semester'] === '1' ? 'Ganjil' : 'Genap') ?></p>
                                        </div>
                                    </article><?php endforeach; ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <div class="card chart-card">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <h3 class="chart-title">Prestasi Siswa</h3>
                                <p class="chart-subtitle mb-0">Capaian siswa yang tercatat pada administrasi sekolah.</p>
                            </div><?php if (can_view_module('prestasi')): ?><a class="btn btn-sm btn-outline-primary" href="index.php?module=prestasi">Kelola dan lihat semua</a><?php endif; ?>
                        </div>
                        <?php if (!$principalAchievementRows): ?><div class="empty-state">Belum ada prestasi siswa yang tercatat.</div><?php else: ?><div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Prestasi</th>
                                            <th>Siswa</th>
                                            <th>Bidang</th>
                                            <th>Tingkat</th>
                                            <th>Penyelenggara</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($principalAchievementRows as $achievement): ?><tr>
                                                <th scope="row"><?= e($achievement['nama_prestasi']) ?></th>
                                                <td><?= e($achievement['nama_siswa'] ?: 'Prestasi kelompok/sekolah') ?></td>
                                                <td><?= e($achievement['bidang'] ?: '—') ?></td>
                                                <td><span class="badge text-bg-light"><?= e($achievement['tingkat']) ?></span></td>
                                                <td><?= e($achievement['penyelenggara'] ?: '—') ?></td>
                                                <td><?= $achievement['tanggal'] ? e(date('d M Y', strtotime($achievement['tanggal']))) : '—' ?></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
    <?php if (can_view_module('kelas_berlangsung')): ?><div class="card chart-card mb-4 dashboard-class-sessions">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="chart-title">Kelas Berlangsung / Terjadwal</h2><a class="small text-decoration-none" href="index.php?module=kelas_berlangsung">Kelola kelas</a>
            </div><?php $stmt = $pdo->prepare("SELECT kb.tanggal,k.nama_kelas,m.nama_mapel,g.nama AS guru,kb.materi,kb.status FROM kelas_berlangsung kb JOIN kelas k ON k.id=kb.kelas_id JOIN mapel m ON m.id=kb.mapel_id LEFT JOIN guru g ON g.id=kb.guru_id WHERE kb.tanggal BETWEEN ? AND ? ORDER BY kb.tanggal DESC LIMIT 5");
                    $stmt->execute([$rangeStart, $rangeEnd]);
                    $liveClasses = $stmt->fetchAll(); ?><div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kelas</th>
                            <th>Mapel</th>
                            <th>Guru</th>
                            <th>Materi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody><?php if (!$liveClasses): ?><tr>
                                <td colspan="6" class="text-center text-secondary py-3">Belum ada catatan pembelajaran pada periode ini.</td>
                            </tr><?php else: foreach ($liveClasses as $lesson): ?><tr>
                                    <td><?= e(date('d-m-Y', strtotime($lesson['tanggal']))) ?></td>
                                    <td><?= e($lesson['nama_kelas']) ?></td>
                                    <td><?= e($lesson['nama_mapel']) ?></td>
                                    <td><?= e($lesson['guru'] ?? '-') ?></td>
                                    <td><?= e($lesson['materi']) ?></td>
                                    <td><?= e($lesson['status']) ?></td>
                                </tr><?php endforeach;
                                endif; ?></tbody>
                </table>
            </div>
        </div><?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
                const statuses = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
                const colors = ['#2db783', '#f0aa43', '#4d91f7', '#e66b78'];
                const student = <?= json_encode(array_values($studentAttendance), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const teacher = <?= json_encode(array_values($teacherAttendance), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const labels = <?= json_encode($trendLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const rates = <?= json_encode($trendRates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const weekLabels = <?= json_encode($weeklyLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const weekRates = <?= json_encode($weeklyRates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const gradeLabels = <?= json_encode(array_map(static fn($row) => $row['tahun_ajaran'] . ' · Semester ' . $row['semester'], $gradeTrendRows), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const gradeValues = <?= json_encode(array_map(static fn($row) => $row['rata_rata'] === null ? null : (float)$row['rata_rata'], $gradeTrendRows), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const intakeLabels = <?= json_encode($studentTrendLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const intakeNewValues = <?= json_encode($studentTrendNew, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                const intakeTotalValues = <?= json_encode($studentTrendTotal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                if (typeof Chart === 'undefined') return;
                Chart.defaults.font.family = "'DM Sans', sans-serif";
                Chart.defaults.color = '#dce8f8';
                const donut = (id, legendId, data) => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    new Chart(el, {
                            type: 'doughnut',
                            data: {
                                labels: statuses,
                                datasets: [{
                                    data,
                                    backgroundColor: colors,
                                    borderWidth: 0,
                                    hoverOffset: 5
                                }]
                            },
                            options: {
                                maintainAspectRatio: false,
                                cutout: '68%',
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => `${ctx.label}: ${ctx.raw} (${ctx.dataset.data.reduce((a,b)=>a+b,0)?Math.round(ctx.raw*100/ctx.dataset.data.reduce((a,b)=>a+b,0)):0}%)`
                                        }
                                    }
                                }
                            });
                        const list = document.getElementById(legendId);
                        const total = data.reduce((a, b) => a + b, 0); list.innerHTML = statuses.map((name, i) => `<li><span class="legend-name"><i class="legend-dot" style="background:${colors[i]}"></i>${name}</span><span class="legend-value">${data[i]} <span class="text-secondary fw-normal">${total?Math.round(data[i]*100/total):0}%</span></span></li>`).join('')
                    };
                    donut('studentDonut', 'studentLegend', student);
                    donut('teacherDonut', 'teacherLegend', teacher);
                    const line = document.getElementById('attendanceTrend');
                    if (line) new Chart(line, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Hadir',
                                data: rates,
                                borderColor: '#3478f6',
                                backgroundColor: 'rgba(52,120,246,.12)',
                                fill: true,
                                tension: .35,
                                spanGaps: false,
                                pointRadius: 3,
                                pointHoverRadius: 5
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        callback: v => v + '%'
                                    },
                                    grid: {
                                        color: 'rgba(151,177,214,.16)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ctx.raw === null ? 'Tidak ada catatan' : `${ctx.raw}% hadir`
                                    }
                                }
                            }
                        }
                    });
                    const bar = document.getElementById('weeklyAttendance');
                    if (bar) new Chart(bar, {
                        type: 'bar',
                        data: {
                            labels: weekLabels,
                            datasets: [{
                                label: 'Persentase Hadir',
                                data: weekRates,
                                backgroundColor: '#50b88b',
                                borderRadius: 7,
                                maxBarThickness: 46
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        callback: v => v + '%'
                                    },
                                    grid: {
                                        color: 'rgba(151,177,214,.16)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => `${ctx.raw}% hadir`
                                    }
                                }
                            }
                        }
                    })
                    const intake = document.getElementById('studentIntakeTrend');
                    if (intake) new Chart(intake, {
                        type: 'bar',
                        data: {
                            labels: intakeLabels,
                            datasets: [{
                                type: 'bar',
                                label: 'Data baru dicatat',
                                data: intakeNewValues,
                                backgroundColor: 'rgba(47,148,255,.64)',
                                hoverBackgroundColor: '#2f94ff',
                                borderRadius: 5,
                                maxBarThickness: 25,
                                yAxisID: 'y'
                            }, {
                                type: 'line',
                                label: 'Akumulasi data siswa',
                                data: intakeTotalValues,
                                borderColor: '#9b72ff',
                                backgroundColor: 'rgba(155,114,255,.14)',
                                pointBackgroundColor: '#d2c1ff',
                                pointBorderColor: '#9b72ff',
                                pointRadius: 3,
                                borderWidth: 3,
                                tension: .35,
                                fill: true,
                                yAxisID: 'y'
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0,
                                        color: '#b9c9e0'
                                    },
                                    grid: {
                                        color: 'rgba(151,177,214,.15)'
                                    },
                                    border: {
                                        display: false
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: '#b9c9e0',
                                        maxRotation: 0,
                                        autoSkip: true,
                                        maxTicksLimit: 12
                                    },
                                    grid: {
                                        color: 'rgba(151,177,214,.08)'
                                    },
                                    border: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    align: 'start',
                                    labels: {
                                        color: '#dce8f8',
                                        usePointStyle: true,
                                        boxWidth: 8,
                                        boxHeight: 8
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: context => `${context.dataset.label}: ${context.raw} siswa`
                                    }
                                }
                            }
                        }
                    });
                    const grade = document.getElementById('principalGradeTrend');
                    if (grade) new Chart(grade, {
                        type: 'line',
                        data: {
                            labels: gradeLabels,
                            datasets: [{
                                label: 'Rata-rata nilai',
                                data: gradeValues,
                                borderColor: '#8b5cf6',
                                backgroundColor: 'rgba(139,92,246,.12)',
                                fill: true,
                                tension: .3,
                                spanGaps: false
                            }]
                        },
                        options: {
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        callback: value => value
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: context => `${context.raw ?? 'Tidak ada nilai'}`
                                    }
                                }
                            }
                        }
                    });
                })();
    </script>

<?php require __DIR__ . '/includes/footer.php';
    exit;
}
if (!isset($modules[$moduleKey])) {
    http_response_code(404);
    exit('Modul tidak ditemukan.');
}
if ($moduleKey === 'guru') {
    require __DIR__ . '/modules/guru.php';
    exit;
}
require_module_access($moduleKey);
$cfg = $modules[$moduleKey];
$table = $cfg['table'];
$pk = $cfg['pk'];
$fields = $cfg['fields'];
$cols = $cfg['columns'];
$pageTitle = $cfg['title'];
$action = (string)($_GET['action'] ?? 'list');
$_createFormRestore = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['open_add'])) {
    $_createFormRestore = $_SESSION['_create_form_restore'][$moduleKey] ?? [];
    unset($_SESSION['_create_form_restore'][$moduleKey]);
}
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_form_action'] ?? '') === 'create') $action = 'create';
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] !== 'POST' && $moduleKey !== 'profil_sekolah') redirect('index.php?module=' . rawurlencode($moduleKey) . '&open_add=1');
$migrationRequirements = [
    'staf' => ['staff' => ['id', 'nip', 'nama', 'jenis_kelamin', 'jabatan', 'unit_kerja', 'no_hp', 'email', 'alamat', 'status', 'tanggal_masuk', 'created_at', 'updated_at']],
    'berita_sekolah' => ['berita_sekolah' => ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at', 'review_status']],
    'galeri_sekolah' => ['galeri_sekolah' => ['id', 'judul', 'keterangan', 'gambar_url', 'tanggal_kegiatan', 'status', 'created_at', 'review_status']],
    'pengumuman' => ['pengumuman' => ['review_status']],
    'kalender_akademik' => ['kalender_akademik' => ['tampil_publik', 'review_status', 'created_by']],
    'profil_sekolah' => ['profil_sekolah' => ['sejarah', 'sejarah_status', 'visi', 'misi', 'sambutan', 'jam_layanan', 'peta_url', 'alamat_jalan', 'kampung', 'distrik', 'kabupaten', 'provinsi', 'kode_pos', 'jenjang', 'status_sekolah', 'naungan', 'akreditasi', 'akreditasi_status', 'visi_status', 'misi_status', 'tujuan', 'tujuan_status']],
    'portal_ekstrakurikuler' => ['portal_ekstrakurikuler' => ['nama', 'kategori', 'deskripsi', 'pembina', 'jadwal', 'persyaratan', 'status', 'urutan']],
    'portal_fasilitas' => ['portal_fasilitas' => ['nama', 'deskripsi', 'gambar_url', 'status', 'urutan']],
    'portal_struktur_sekolah' => ['portal_struktur_sekolah' => ['nama', 'jabatan', 'foto_url', 'status', 'urutan']],
    'portal_prestasi_publik' => ['portal_prestasi_publik' => ['judul', 'penerima', 'bukti_terverifikasi', 'izin_publikasi', 'status']],
    'portal_jadwal_publik' => ['portal_jadwal_publik' => ['tahun_ajaran', 'tingkat', 'hari', 'jam_mulai', 'jam_selesai', 'mata_pelajaran', 'status']],
    'portal_dokumen_publik' => ['portal_dokumen_publik' => ['judul', 'kategori', 'deskripsi', 'nama_file', 'status', 'published_at']],
    'portal_tautan_sosial' => ['portal_tautan_sosial' => ['platform', 'url', 'status']],
];
$missingPortalSchema = [];
foreach (($migrationRequirements[$moduleKey] ?? []) as $requiredTable => $requiredColumns) {
    $tableCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $tableCheck->execute([$requiredTable]);
    if ((int)$tableCheck->fetchColumn() !== 1) {
        $missingPortalSchema[] = "Tabel {$requiredTable} belum ada";
        continue;
    }
    foreach ($requiredColumns as $requiredColumn) {
        $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $columnCheck->execute([$requiredTable, $requiredColumn]);
        if ((int)$columnCheck->fetchColumn() !== 1) $missingPortalSchema[] = "Kolom {$requiredTable}.{$requiredColumn} belum ada";
    }
}
if ($missingPortalSchema) {
    require __DIR__ . '/includes/header.php';
?>
    <div class="alert alert-warning">
        <h1 class="h4">Pembaruan database portal publik diperlukan</h1>
        <p>Halaman ini belum bisa dibuka karena skema database portal belum lengkap.</p>
        <p class="mb-2">Periksa nama migrasi yang dibutuhkan pada rincian di bawah, lalu jalankan melalui <code>php database/migrate.php</code> dari folder proyek.</p>
        <details>
            <summary>Rincian pemeriksaan</summary>
            <ul class="mt-2 mb-0"><?php foreach ($missingPortalSchema as $missing): ?><li><?= e($missing) ?></li><?php endforeach; ?></ul>
        </details>
    </div>
<?php
    require __DIR__ . '/includes/footer.php';
    exit;
}
$editorialModules = ['pengumuman', 'berita_sekolah', 'galeri_sekolah', 'kalender_akademik'];
$isEditorialContent = in_array($moduleKey, $editorialModules, true);
$isOperatorAuthor = $isEditorialContent && current_role() === 'operator';
$editorialColumns = [];
if ($isEditorialContent) {
    $editorialColumns = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
}
if (in_array($action, ['create', 'edit', 'delete'], true)) {
    require_module_access($moduleKey, true);
}
if ($moduleKey === 'profil_sekolah' && $action === 'delete') {
    http_response_code(403);
    exit('Profil sekolah tidak dapat dihapus.');
}
if ($moduleKey === 'profil_sekolah' && $action === 'create') {
    $profileId = (int)$pdo->query('SELECT id FROM profil_sekolah ORDER BY id LIMIT 1')->fetchColumn();
    if ($profileId > 0) redirect('index.php?module=profil_sekolah&action=edit&id=' . $profileId);
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

// Halaman detail baca-saja dipakai bersama oleh tabel CRUD dan tabel ringkasan dashboard.
if ($action === 'view') {
    if ($id < 1) {
        http_response_code(404);
        exit('Data yang diminta tidak ditemukan.');
    }
    // Detail memakai semua field yang dikelola modul; daftar tabel boleh tetap ringkas.
    $detailFieldKeys = array_keys($fields);
    if ($isEditorialContent && in_array('review_status', $editorialColumns, true)) $detailFieldKeys[] = 'review_status';
    $detailColumns = array_values(array_unique(array_merge([$pk], $detailFieldKeys)));
    $detailSql = 'SELECT `' . implode('`,`', $detailColumns) . '` FROM `' . $table . '` WHERE `' . $pk . '`=? LIMIT 1';
    $detailStmt = $pdo->prepare($detailSql);
    $detailStmt->execute([$id]);
    $detailRow = $detailStmt->fetch(PDO::FETCH_ASSOC);
    if (!$detailRow) {
        http_response_code(404);
        exit('Data yang diminta tidak ditemukan.');
    }
    $detailHistoryAvailable = $moduleKey === 'siswa' && db_has_column($pdo, 'student_class_history', 'id');
    $detailHistoryRows = [];
    if ($detailHistoryAvailable) {
        $historyPreview = $pdo->prepare('SELECT h.kelas_asal_id,h.tahun_ajaran_asal_id,h.kelas_tujuan_id,h.tahun_ajaran_tujuan_id,h.alasan,h.dibuat_pada,k1.nama_kelas AS kelas_asal,ta1.nama AS tahun_asal,k2.nama_kelas AS kelas_tujuan,ta2.nama AS tahun_tujuan,u.nama_lengkap AS petugas FROM student_class_history h LEFT JOIN kelas k1 ON k1.id=h.kelas_asal_id LEFT JOIN tahun_ajaran ta1 ON ta1.id=h.tahun_ajaran_asal_id JOIN kelas k2 ON k2.id=h.kelas_tujuan_id JOIN tahun_ajaran ta2 ON ta2.id=h.tahun_ajaran_tujuan_id LEFT JOIN users u ON u.id=h.actor_user_id WHERE h.siswa_id=? ORDER BY h.dibuat_pada DESC,h.id DESC LIMIT 5');
        $historyPreview->execute([$id]);
        $detailHistoryRows = $historyPreview->fetchAll();
    }
    require __DIR__ . '/includes/header.php';
    $detailRelationLabels = [];
    $detailStaticLabels = [];
    foreach ($detailFieldKeys as $detailColumn) {
        if (isset($fields[$detailColumn]['relation'])) $detailRelationLabels[$detailColumn] = relation_options($pdo, $fields[$detailColumn]['relation']);
        elseif (isset($fields[$detailColumn]['options'])) $detailStaticLabels[$detailColumn] = $fields[$detailColumn]['options'];
    }
?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <p class="text-secondary mb-1">Detail data</p>
            <h1 class="h3 mb-0"><?= e($pageTitle) ?></h1>
        </div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="index.php?module=<?= e($moduleKey) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali</a><?php if (can_manage_module($moduleKey)): ?><a class="btn btn-primary" href="index.php?module=<?= e($moduleKey) ?>&amp;action=edit&amp;id=<?= $id ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a><?php endif; ?></div>
    </div>
    <section class="card p-3 p-md-4" aria-label="Rincian <?= e($pageTitle) ?>">
        <dl class="row mb-0">
            <?php foreach ($detailFieldKeys as $detailColumn): $detailValue = $detailRow[$detailColumn] ?? null;
                if (isset($detailRelationLabels[$detailColumn])) $detailValue = $detailRelationLabels[$detailColumn][(string)$detailValue] ?? $detailValue;
                elseif (isset($detailStaticLabels[$detailColumn])) $detailValue = $detailStaticLabels[$detailColumn][(string)$detailValue] ?? $detailValue;
                $detailLabel = $fields[$detailColumn]['label'] ?? field_label($detailColumn); ?>
                <div class="col-12 col-md-6 border-bottom py-3">
                    <dt class="small text-secondary mb-1"><?= e($detailLabel) ?></dt>
                    <dd class="mb-0 text-break"><?= $detailValue === null || $detailValue === '' ? '<span class="text-secondary">—</span>' : nl2br(e((string)$detailValue)) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
    <?php if ($detailHistoryAvailable): ?><section class="card p-3 p-md-4 mt-3" aria-labelledby="studentHistoryTitle">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1" id="studentHistoryTitle">Riwayat Kelas</h2>
                    <p class="small text-secondary mb-0">Perubahan kelas yang tercatat setelah fitur riwayat diaktifkan.</p>
                </div><a class="btn btn-sm btn-outline-primary" href="student_class_history.php?siswa_id=<?= $id ?>"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Lihat semua riwayat</a>
            </div><?php if (!$detailHistoryRows): ?><p class="text-secondary mb-0">Belum ada riwayat yang tercatat. Data lama tidak dibuat ulang tanpa dokumen sumber resmi.</p><?php else: ?><div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Periode/Kelas asal</th>
                                <th>Periode/Kelas tujuan</th>
                                <th>Tanggal</th>
                                <th>Alasan</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($detailHistoryRows as $historyRow): ?><tr>
                                    <td><?= e(($historyRow['tahun_asal'] ?? '—') . ' · ' . ($historyRow['kelas_asal'] ?? 'Belum tercatat')) ?></td>
                                    <td><?= e($historyRow['tahun_tujuan'] . ' · ' . $historyRow['kelas_tujuan']) ?></td>
                                    <td class="text-nowrap"><?= e(date('d M Y H:i', strtotime($historyRow['dibuat_pada']))) ?></td>
                                    <td><?= e($historyRow['alasan'] ?: '—') ?></td>
                                    <td><?= e($historyRow['petugas'] ?: 'Akun tidak tersedia') ?></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div><?php endif; ?>
        </section><?php endif; ?>
    <?php if (can_manage_module($moduleKey) && $moduleKey !== 'profil_sekolah'): ?><form method="post" action="index.php?module=<?= e($moduleKey) ?>&amp;action=delete&amp;id=<?= $id ?>" class="mt-3" onsubmit="return confirm('Hapus data ini? Tindakan ini tidak dapat dibatalkan.');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-1" aria-hidden="true"></i>Hapus data</button></form><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
    exit;
}

function get_form_value(array $field, string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}
function portal_store_upload(array $upload): array
{
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [null, 'Ukuran berkas melewati batas unggah PHP. Batas aplikasi 5 MB; pastikan upload_max_filesize dan post_max_size pada PHP minimal 5M.'];
        if ($uploadError === UPLOAD_ERR_PARTIAL) return [null, 'Gambar hanya terunggah sebagian. Silakan coba lagi.'];
        if ($uploadError === UPLOAD_ERR_NO_TMP_DIR || $uploadError === UPLOAD_ERR_CANT_WRITE) return [null, 'PHP tidak dapat menyimpan berkas sementara. Periksa konfigurasi dan izin folder sementara server.'];
        return [null, 'Unggahan gambar tidak berhasil. Pilih berkas kembali lalu coba lagi.'];
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if (!is_uploaded_file($tmp) || $size < 1 || $size > 5 * 1024 * 1024) return [null, 'Gambar harus berukuran maksimal 5 MB.'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $image = @getimagesize($tmp);
    if (!isset($formats[$mime]) || !$image || ($image['mime'] ?? '') !== $mime) return [null, 'Format gambar harus JPG, PNG, atau WebP yang valid.'];
    if ((int)$image[0] < 120 || (int)$image[1] < 80 || (int)$image[0] > 8000 || (int)$image[1] > 8000) return [null, 'Dimensi gambar minimal 120 × 80 dan maksimal 8.000 × 8.000 piksel.'];
    $directory = __DIR__ . '/uploads/portal';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return [null, 'Folder uploads/portal gagal dibuat.'];
    $filename = bin2hex(random_bytes(20)) . '.' . $formats[$mime];
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) return [null, 'Gambar gagal disimpan. Periksa izin folder uploads/portal.'];
    return ['uploads/portal/' . $filename, null];
}
function portal_remove_stored_asset(mixed $path): void
{
    $path = (string)$path;
    if (preg_match('~^uploads/portal/([a-f0-9]{40}\.(?:jpg|png|webp))$~i', $path, $match)) {
        $root = realpath(__DIR__ . '/uploads/portal');
    } elseif (preg_match('~^storage/public_documents/([a-f0-9]{40}\.pdf)$~i', $path, $match)) {
        $root = realpath(__DIR__ . '/storage/public_documents');
    } else {
        return;
    }
    $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
    if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
}
function portal_store_pdf(array $upload): array
{
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [null, 'Ukuran dokumen melebihi batas unggah server. Batas aplikasi 10 MB.'];
        return [null, 'Unggahan PDF tidak berhasil. Pilih dokumen kembali.'];
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if (!is_uploaded_file($tmp) || $size < 8 || $size > 10 * 1024 * 1024) return [null, 'PDF harus berupa unggahan valid dengan ukuran maksimal 10 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $handle = @fopen($tmp, 'rb');
    $signature = $handle ? fread($handle, 5) : '';
    if ($handle) fclose($handle);
    if ($mime !== 'application/pdf' || $signature !== '%PDF-') return [null, 'Dokumen harus berupa file PDF yang valid.'];
    $directory = __DIR__ . '/storage/public_documents';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return [null, 'Folder dokumen publik tidak dapat dibuat.'];
    $filename = bin2hex(random_bytes(20)) . '.pdf';
    if (!move_uploaded_file($tmp, $directory . '/' . $filename)) return [null, 'PDF gagal disimpan. Periksa izin folder dokumen publik.'];
    return ['storage/public_documents/' . $filename, null];
}
function relation_options(PDO $pdo, string $relation): array
{
    $queries = [
        'kelas' => "SELECT k.id, CONCAT(k.nama_kelas, ' · ', ta.nama) AS label FROM kelas k JOIN tahun_ajaran ta ON ta.id = k.tahun_ajaran_id ORDER BY ta.nama DESC, k.tingkat, k.nama_kelas",
        'guru' => "SELECT id, nama AS label FROM guru WHERE status = 'Aktif' ORDER BY nama",
        'siswa' => 'SELECT id, CONCAT(nis, \' · \', nama) AS label FROM siswa ORDER BY nama',
        'mapel' => 'SELECT id, nama_mapel AS label FROM mapel ORDER BY nama_mapel',
        'tahun_ajaran' => 'SELECT id, nama AS label FROM tahun_ajaran ORDER BY nama DESC',
    ];
    if (!isset($queries[$relation])) return [];
    return $pdo->query($queries[$relation])->fetchAll(PDO::FETCH_KEY_PAIR);
}
function render_input(string $key, array $field, mixed $value = '', array $options = []): void
{
    $type = $field['type'] ?? 'text';
    $label = $field['label'] ?? field_label($key);
    $required = !empty($field['required']);
    if ($type === 'date' && is_string($value) && strlen($value) >= 10) $value = substr($value, 0, 10);
    echo '<div class="col-md-6 mb-3"><label class="form-label' . ($required ? ' required' : '') . '" for="' . e($key) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea class="form-control" id="' . e($key) . '" name="' . e($key) . '" rows="3"' . (isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '') . '>' . e($value) . '</textarea>';
    } elseif ($type === 'image_upload') {
        $hasStoredUpload = is_string($value) && preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $value);
        echo '<input class="form-control" id="' . e($key) . '" name="' . e($key) . '" type="file" accept="image/jpeg,image/png,image/webp"' . ($required && !$hasStoredUpload ? ' required' : '') . '>';
        echo '<div class="form-text">JPG, PNG, atau WebP; maksimal 5 MB. Gambar baru menggantikan gambar lama saat formulir disimpan.</div>';
        if (is_string($value) && preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $value) && !empty($field['media_type'])) {
            $entityId = (int)($_GET['id'] ?? 0);
            if ($entityId > 0) echo '<div class="mt-2"><img src="media/portal.php?type=' . e($field['media_type']) . '&id=' . $entityId . '" alt="Pratinjau gambar saat ini" style="max-width:240px;max-height:160px;object-fit:cover;border-radius:8px"></div>';
        }
    } elseif ($type === 'pdf_upload') {
        $hasStoredPdf = is_string($value) && preg_match('~^storage/public_documents/[a-f0-9]{40}\.pdf$~i', $value);
        echo '<input class="form-control" id="' . e($key) . '" name="' . e($key) . '" type="file" accept="application/pdf,.pdf"' . ($required && !$hasStoredPdf ? ' required' : '') . '>';
        echo '<div class="form-text">PDF resmi saja, maksimal 10 MB. Jangan unggah data pribadi pendaftar atau siswa.</div>';
        if ($hasStoredPdf) echo '<div class="form-text">Dokumen tersimpan: ' . e(basename((string)$value)) . '. Unggah berkas baru untuk menggantinya.</div>';
    } elseif ($type === 'select') {
        echo '<select class="form-select" id="' . e($key) . '" name="' . e($key) . '"' . ($required ? ' required' : '') . '><option value="">-- Pilih --</option>';
        foreach (($options ?: ($field['options'] ?? [])) as $optVal => $optLabel) {
            echo '<option value="' . e($optVal) . '"' . ((string)$value === (string)$optVal ? ' selected' : '') . '>' . e($optLabel) . '</option>';
        }
        if (isset($field['relation']) && !$options) {
            echo '<option value="" disabled>Belum ada data terkait</option>';
        }
        echo '</select>';
    } else {
        $htmlType = in_array($type, ['number', 'date', 'time', 'email'], true) ? $type : 'text';
        echo '<input class="form-control" id="' . e($key) . '" name="' . e($key) . '" type="' . $htmlType . '" value="' . e($value) . '"' . ($required ? ' required' : '') . (isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '');
        if (isset($field['min'])) echo ' min="' . e($field['min']) . '"';
        if (isset($field['max'])) echo ' max="' . e($field['max']) . '"';
        if ($htmlType === 'number') echo ' step="' . e($field['step'] ?? '0.01') . '"';
        echo '>';
    }
    echo '</div>';
}

if ($action === 'delete' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $assetsToRemove = [];
        $deleteAuditRecordStmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk`=?");
        $deleteAuditRecordStmt->execute([$id]);
        $deleteAuditRecord = $deleteAuditRecordStmt->fetch() ?: null;
        if ($isOperatorAuthor && in_array('created_by', $editorialColumns, true)) {
            $owner = $pdo->prepare("SELECT created_by FROM `$table` WHERE `$pk`=?");
            $owner->execute([$id]);
            if ((int)$owner->fetchColumn() !== (int)$_SESSION['user_id']) {
                http_response_code(403);
                exit('Anda hanya dapat menghapus konten yang dibuat oleh akun Anda.');
            }
        }
        $uploadFields = array_filter($fields, static fn(array $field): bool => in_array(($field['type'] ?? ''), ['image_upload', 'pdf_upload'], true));
        if ($uploadFields) {
            $assetColumns = array_keys($uploadFields);
            $assetLookup = $pdo->prepare("SELECT `" . implode('`,`', $assetColumns) . "` FROM `$table` WHERE `$pk`=?");
            $assetLookup->execute([$id]);
            $assetsToRemove = array_values(array_filter($assetLookup->fetch() ?: []));
        }
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
        $stmt->execute([$id]);
        foreach ($assetsToRemove as $storedAsset) portal_remove_stored_asset($storedAsset);
        record_activity($pdo, 'hapus', $table, $id, null, $deleteAuditRecord, null);
        flash('Data berhasil dihapus.');
    } catch (PDOException $ex) {
        flash('Data tidak dapat dihapus karena masih digunakan oleh data lain.', 'warning');
    }
    redirect("index.php?module=$moduleKey");
}
if (in_array($action, ['create', 'edit'], true)) {
    $record = [];
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch() ?: [];
        if (!$record) {
            flash('Data tidak ditemukan.', 'warning');
            redirect("index.php?module=$moduleKey");
        }
        if ($isOperatorAuthor && (int)($record['created_by'] ?? 0) !== (int)$_SESSION['user_id']) {
            http_response_code(403);
            exit('Anda hanya dapat mengubah konten yang dibuat oleh akun Anda.');
        }
    }
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['_form_action'] ?? ''), ['save', 'create'], true)) {
        verify_csrf();
        $values = [];
        $newUploads = [];
        $oldUploads = [];
        foreach ($fields as $key => $field) {
            if ($isOperatorAuthor && ($key === 'tampil_publik' || ($key === 'status' && $moduleKey !== 'kalender_akademik'))) continue;
            if (in_array(($field['type'] ?? ''), ['image_upload', 'pdf_upload'], true)) {
                $existing = (string)($record[$key] ?? '');
                $upload = $_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $isPdf = ($field['type'] ?? '') === 'pdf_upload';
                $hasExistingUpload = $isPdf
                    ? (bool)preg_match('~^storage/public_documents/[a-f0-9]{40}\.pdf$~i', $existing)
                    : (bool)preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $existing);
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    if (!empty($field['required']) && !$hasExistingUpload) $error = 'Silakan unggah ' . strtolower($field['label'] ?? 'gambar') . ' melalui formulir ini.';
                    $values[$key] = $existing === '' ? null : $existing;
                } else {
                    [$storedImage, $uploadError] = $isPdf ? portal_store_pdf($upload) : portal_store_upload($upload);
                    if ($uploadError !== null) {
                        $error = $uploadError;
                        $values[$key] = $existing === '' ? null : $existing;
                    } else {
                        $values[$key] = $storedImage;
                        $newUploads[] = $storedImage;
                        if ($existing !== '') $oldUploads[] = $existing;
                    }
                }
                continue;
            }
            $value = get_form_value($field, $key);
            if (isset($field['maxlength']) && mb_strlen($value, 'UTF-8') > (int)$field['maxlength']) $error = 'Kolom ' . ($field['label'] ?? $key) . ' terlalu panjang.';
            if (!empty($field['required']) && $value === '') $error = 'Kolom ' . ($field['label'] ?? $key) . ' wajib diisi.';
            if (($field['type'] ?? '') === 'number' && $value !== '' && !is_numeric($value)) $error = 'Kolom angka tidak valid.';
            if (($field['type'] ?? '') === 'date' && $value !== '') {
                $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$parsedDate || $parsedDate->format('Y-m-d') !== $value) $error = 'Kolom tanggal tidak valid.';
            }
            if (($field['type'] ?? '') === 'time' && $value !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) $error = 'Format jam tidak valid.';
            if (($field['type'] ?? '') === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $error = 'Format email tidak valid.';
            if (isset($field['min']) && $value !== '' && (float)$value < $field['min']) $error = 'Nilai tidak boleh kurang dari ' . $field['min'] . '.';
            if (isset($field['max']) && $value !== '' && (float)$value > $field['max']) $error = 'Nilai tidak boleh lebih dari ' . $field['max'] . '.';
            if (($field['type'] ?? '') === 'select' && isset($field['options']) && $value !== '' && !array_key_exists($value, $field['options'])) $error = 'Pilihan ' . ($field['label'] ?? $key) . ' tidak valid.';
            $values[$key] = $value === '' ? null : $value;
        }
        $previousReviewStatus = $record['review_status'] ?? null;
        if ($error === '' && $isEditorialContent && in_array('review_status', $editorialColumns, true)) {
            if ($isOperatorAuthor) {
                $reviewAction = (string)($_POST['review_action'] ?? 'draft');
                if (!in_array($reviewAction, ['draft', 'submit'], true)) {
                    $error = 'Pilihan pengajuan konten tidak valid.';
                } else {
                    if ($moduleKey === 'kalender_akademik') $values['tampil_publik'] = 0;
                    else $values['status'] = 'Draft';
                    $values['review_status'] = $reviewAction === 'submit' ? 'Menunggu Persetujuan' : 'Draft';
                    $values['submitted_at'] = $reviewAction === 'submit' ? date('Y-m-d H:i:s') : null;
                    $values['reviewed_at'] = null;
                    $values['reviewed_by'] = null;
                    $values['review_note'] = null;
                    if ($action === 'create' && in_array('created_by', $editorialColumns, true)) {
                        $values['created_by'] = (int)$_SESSION['user_id'];
                    }
                }
            } else {
                $isPublic = $moduleKey === 'kalender_akademik'
                    ? (string)($values['tampil_publik'] ?? '0') === '1'
                    : in_array((string)($values['status'] ?? 'Draft'), ['Terbit', 'Arsip'], true);
                $values['review_status'] = $isPublic ? 'Disetujui' : 'Draft';
                $values['reviewed_at'] = $isPublic ? date('Y-m-d H:i:s') : null;
                $values['reviewed_by'] = $isPublic ? (int)$_SESSION['user_id'] : null;
                $values['review_note'] = null;
                if ($action === 'create' && in_array('created_by', $editorialColumns, true)) {
                    $values['created_by'] = (int)$_SESSION['user_id'];
                }
            }
        }
        if ($error === '' && in_array($moduleKey, ['kalender_akademik', 'kokurikuler', 'pengumuman'], true)) {
            $startKey = $moduleKey === 'pengumuman' ? 'mulai_tayang' : 'tanggal_mulai';
            $endKey = $moduleKey === 'pengumuman' ? 'selesai_tayang' : 'tanggal_selesai';
            if (!empty($values[$startKey]) && !empty($values[$endKey]) && $values[$startKey] > $values[$endKey]) $error = 'Tanggal mulai tidak boleh melewati tanggal selesai.';
        }
        if ($error === '' && in_array($moduleKey, ['portal_fasilitas', 'portal_prestasi_publik', 'portal_dokumen_publik'], true) && ($values['status'] ?? '') === 'Terbit') {
            if ($moduleKey === 'portal_fasilitas' && empty($values['gambar_url'])) $error = 'Unggah foto aktual sebelum fasilitas diterbitkan.';
            if ($moduleKey === 'portal_prestasi_publik' && ((string)($values['bukti_terverifikasi'] ?? '0') !== '1' || (string)($values['izin_publikasi'] ?? '0') !== '1')) {
                $error = 'Prestasi hanya dapat diterbitkan setelah bukti diverifikasi dan izin publikasi diperoleh.';
            }
            if ($moduleKey === 'portal_dokumen_publik') {
                $storedPdf = (string)($values['nama_file'] ?? '');
                if (!preg_match('~^storage/public_documents/[a-f0-9]{40}\.pdf$~i', $storedPdf)) {
                    $error = 'Unggah dokumen PDF resmi sebelum menerbitkannya.';
                } elseif (empty($values['published_at'])) {
                    $values['published_at'] = date('Y-m-d 00:00:00');
                }
            }
        }
        if ($error === '' && $moduleKey === 'portal_tautan_sosial') {
            $socialUrl = (string)($values['url'] ?? '');
            $socialPlatform = (string)($values['platform'] ?? '');
            $host = strtolower((string)parse_url($socialUrl, PHP_URL_HOST));
            $hostAllowlist = [
                'facebook' => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
                'instagram' => ['instagram.com', 'www.instagram.com'],
                'youtube' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
                'tiktok' => ['tiktok.com', 'www.tiktok.com'],
                'twitter-x' => ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'],
            ];
            if (
                !filter_var($socialUrl, FILTER_VALIDATE_URL) || strtolower((string)parse_url($socialUrl, PHP_URL_SCHEME)) !== 'https'
                || !in_array($host, $hostAllowlist[$socialPlatform] ?? [], true)
            ) {
                $error = 'Gunakan URL HTTPS dari domain resmi platform media sosial yang dipilih.';
            }
        }
        if ($error === '' && $moduleKey === 'portal_jadwal_publik' && $values['jam_mulai'] >= $values['jam_selesai']) {
            $error = 'Jam mulai harus lebih awal daripada jam selesai.';
        }
        if ($error === '' && $moduleKey === 'jadwal' && $values['jam_mulai'] >= $values['jam_selesai']) $error = 'Jam mulai harus lebih awal dari jam selesai.';
        if ($moduleKey === 'nilai' && isset($values['nilai_uas'])) {
            // Simpan juga ke kolom lama agar laporan/integrasi sebelumnya tetap kompatibel.
            $values['nilai_ujian'] = $values['nilai_uas'];
        }
        if ($moduleKey === 'profil_sekolah' && $error === '') {
            $weightTotal = (float)$values['bobot_tugas'] + (float)$values['bobot_uts'] + (float)$values['bobot_uas'];
            if (abs($weightTotal - 100) > 0.001) $error = 'Jumlah bobot tugas, UTS, dan UAS harus tepat 100%.';
        }
        if ($error === '') {
            try {
                $pdo->beginTransaction();
                if ($action === 'create') {
                    $cols = array_keys($values);
                    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($values));
                    $savedId = (int)$pdo->lastInsertId();
                    if ($moduleKey === 'siswa' && db_has_column($pdo, 'student_class_history', 'id')) {
                        $classYear = $pdo->prepare('SELECT tahun_ajaran_id FROM kelas WHERE id=?');
                        $classYear->execute([(int)$values['kelas_id']]);
                        $yearId = (int)$classYear->fetchColumn();
                        if ($yearId > 0) {
                            $history = $pdo->prepare('INSERT INTO student_class_history (siswa_id,kelas_asal_id,tahun_ajaran_asal_id,kelas_tujuan_id,tahun_ajaran_tujuan_id,alasan,actor_user_id) VALUES (?,NULL,NULL,?,?,?,?)');
                            $history->execute([$savedId, (int)$values['kelas_id'], $yearId, 'Pencatatan kelas awal siswa.', (int)($_SESSION['user_id'] ?? 0) ?: null]);
                        }
                    }
                    $isPortalRecord = str_starts_with($moduleKey, 'portal_');
                    $createdStatus = (string) ($values['status'] ?? '');
                    record_activity(
                        $pdo,
                        $isPortalRecord ? ($createdStatus === 'Terbit' ? 'menerbitkan konten publik' : 'menambah konten portal') : 'tambah',
                        $table,
                        $savedId,
                        $isPortalRecord ? $createdStatus : null,
                        null,
                        $values
                    );
                    if ($isEditorialContent && isset($values['review_status'])) {
                        $reviewLog = $pdo->prepare('INSERT INTO konten_review_log (entitas,record_id,status_awal,status_akhir,catatan,user_id) VALUES (?,?,NULL,?,?,?)');
                        $reviewLog->execute([$moduleKey, $savedId, $values['review_status'], null, (int)$_SESSION['user_id']]);
                    }
                    try {
                        if ($moduleKey === 'pengumuman' && ($values['status'] ?? '') === 'Terbit') {
                            $notify = $pdo->prepare("INSERT INTO notifikasi (recipient_user_id,judul,pesan,tautan) SELECT id,?,?,? FROM users WHERE status='Aktif'");
                            $notify->execute(['Pengumuman baru: ' . (string)$values['judul'], (string)$values['isi'], 'index.php?module=pengumuman']);
                        } elseif (in_array($moduleKey, ['kalender_akademik', 'kokurikuler'], true)) {
                            $eventTitle = (string)($values['judul'] ?? $values['nama_kegiatan'] ?? 'Agenda sekolah');
                            $notify = $pdo->prepare("INSERT INTO notifikasi (recipient_user_id,judul,pesan,tautan) SELECT id,?,?,? FROM users WHERE status='Aktif'");
                            $notify->execute(['Agenda sekolah: ' . $eventTitle, (string)($values['tanggal_mulai'] ?? '') . ' · ' . $eventTitle, 'index.php?module=' . $moduleKey]);
                        }
                    } catch (PDOException $notificationError) {
                        // Kegagalan notifikasi tidak membatalkan data utama yang sudah berhasil disimpan.
                    }
                    flash('Data berhasil ditambahkan.');
                } else {
                    $sets = array_map(fn($col) => "`$col` = ?", array_keys($values));
                    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$pk` = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([...array_values($values), $id]);
                    if ($moduleKey === 'siswa' && (int)($record['kelas_id'] ?? 0) !== (int)($values['kelas_id'] ?? 0) && db_has_column($pdo, 'student_class_history', 'id')) {
                        $classYear = $pdo->prepare('SELECT id,tahun_ajaran_id FROM kelas WHERE id IN (?,?)');
                        $classYear->execute([(int)$record['kelas_id'], (int)$values['kelas_id']]);
                        $classMap = [];
                        foreach ($classYear->fetchAll() as $classRow) $classMap[(int)$classRow['id']] = (int)$classRow['tahun_ajaran_id'];
                        $fromYear = $classMap[(int)$record['kelas_id']] ?? 0;
                        $toYear = $classMap[(int)$values['kelas_id']] ?? 0;
                        if ($fromYear < 1 || $toYear < 1) throw new RuntimeException('Tahun ajaran kelas asal/tujuan tidak ditemukan.');
                        $reason = trim((string)($_POST['class_change_reason'] ?? ''));
                        $history = $pdo->prepare('INSERT INTO student_class_history (siswa_id,kelas_asal_id,tahun_ajaran_asal_id,kelas_tujuan_id,tahun_ajaran_tujuan_id,alasan,actor_user_id) VALUES (?,?,?,?,?,?,?)');
                        $reason = $reason === '' ? null : (function_exists('mb_substr') ? mb_substr($reason, 0, 500) : substr($reason, 0, 500));
                        $history->execute([$id, (int)$record['kelas_id'], $fromYear, (int)$values['kelas_id'], $toYear, $reason, (int)($_SESSION['user_id'] ?? 0) ?: null]);
                    }
                    $isPortalRecord = str_starts_with($moduleKey, 'portal_');
                    $previousStatus = (string) ($record['status'] ?? '');
                    $updatedStatus = (string) ($values['status'] ?? $previousStatus);
                    $portalAction = $updatedStatus === 'Terbit' && $previousStatus !== 'Terbit'
                        ? 'menerbitkan konten publik'
                        : ($updatedStatus === 'Arsip' && $previousStatus !== 'Arsip' ? 'mengarsipkan konten publik' : 'memperbarui konten portal');
                    record_activity(
                        $pdo,
                        $isPortalRecord ? $portalAction : 'ubah',
                        $table,
                        $id,
                        $isPortalRecord ? ($previousStatus . ' → ' . $updatedStatus) : null,
                        $record,
                        $values
                    );
                    if ($isEditorialContent && isset($values['review_status']) && $previousReviewStatus !== $values['review_status']) {
                        $reviewLog = $pdo->prepare('INSERT INTO konten_review_log (entitas,record_id,status_awal,status_akhir,catatan,user_id) VALUES (?,?,?,?,?,?)');
                        $reviewLog->execute([$moduleKey, $id, $previousReviewStatus, $values['review_status'], $values['review_note'] ?? null, (int)$_SESSION['user_id']]);
                    }
                    flash('Data berhasil diperbarui.');
                }
                $pdo->commit();
                foreach ($oldUploads as $oldUpload) portal_remove_stored_asset($oldUpload);
                redirect("index.php?module=$moduleKey");
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($newUploads as $newUpload) portal_remove_stored_asset($newUpload);
                foreach ($fields as $assetKey => $assetField) {
                    if (
                        in_array(($assetField['type'] ?? ''), ['image_upload', 'pdf_upload'], true)
                        && in_array((string) ($values[$assetKey] ?? ''), $newUploads, true)
                    ) {
                        $values[$assetKey] = $record[$assetKey] ?? null;
                    }
                }
                if (!$ex instanceof PDOException) app_log('error', 'Portal record save failed', ['module' => $moduleKey, 'type' => get_class($ex)]);
                $error = 'Data gagal disimpan. Periksa nomor yang duplikat dan pastikan ID relasi (kelas/guru/siswa/mapel/tahun ajaran) benar.';
            }
        } else {
            foreach ($newUploads as $newUpload) portal_remove_stored_asset($newUpload);
            foreach ($fields as $assetKey => $assetField) {
                if (
                    in_array(($assetField['type'] ?? ''), ['image_upload', 'pdf_upload'], true)
                    && in_array((string) ($values[$assetKey] ?? ''), $newUploads, true)
                ) {
                    $values[$assetKey] = $record[$assetKey] ?? null;
                }
            }
        }
        $record = array_merge($record, $values);
    }
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
        foreach ($fields as $restoreKey => $restoreField) if (in_array(($restoreField['type'] ?? ''), ['image_upload', 'pdf_upload'], true)) unset($values[$restoreKey]);
        $values['_review_action'] = (string)($_POST['review_action'] ?? 'draft');
        $_SESSION['_create_form_restore'][$moduleKey] = $values;
        flash($error, 'danger');
        redirect('index.php?module=' . rawurlencode($moduleKey) . '&open_add=1');
    }
    require __DIR__ . '/includes/header.php';
?>
    <div class="mb-4"><a href="index.php?module=<?= e($moduleKey) ?>" class="text-decoration-none">&larr; Kembali ke daftar</a>
        <h1 class="h3 mt-2"><?= $action === 'create' ? 'Tambah' : 'Edit' ?> <?= e($pageTitle) ?></h1>
    </div>
    <div class="card p-3 p-md-4">
        <?php if ($isOperatorAuthor && !empty($record['review_note'])): ?><div class="alert alert-warning" role="status"><strong>Catatan pemeriksa:</strong> <?= e($record['review_note']) ?></div><?php endif; ?>
        <form method="post" <?= array_filter($fields, static fn($field) => in_array(($field['type'] ?? ''), ['image_upload', 'pdf_upload'], true)) ? ' enctype="multipart/form-data"' : '' ?>><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="save">
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <div class="row"><?php foreach ($fields as $key => $field): if ($isOperatorAuthor && ($key === 'tampil_publik' || ($key === 'status' && $moduleKey !== 'kalender_akademik'))) continue;
                                    render_input($key, $field, $record[$key] ?? $field['default'] ?? '', isset($field['relation']) ? relation_options($pdo, $field['relation']) : []);
                                endforeach; ?>
                <?php if ($moduleKey === 'siswa' && $action === 'edit' && db_has_column($pdo, 'student_class_history', 'id')): ?><div class="col-12 mb-3"><label class="form-label" for="class_change_reason">Alasan perpindahan kelas (opsional)</label><textarea class="form-control" id="class_change_reason" name="class_change_reason" rows="2" maxlength="500" placeholder="Contoh: kenaikan kelas atau penyesuaian rombongan belajar"><?= e($_POST['class_change_reason'] ?? '') ?></textarea>
                        <div class="form-text">Disimpan bersama kelas asal/tujuan, tahun ajaran, akun petugas, dan waktu perubahan.</div>
                    </div><?php endif; ?>
                <?php if ($isOperatorAuthor): ?><div class="col-12 mb-3"><label class="form-label" for="review_action">Tindakan konten</label><select class="form-select" id="review_action" name="review_action">
                            <option value="draft">Simpan sebagai draf</option>
                            <option value="submit" <?= ($_POST['review_action'] ?? '') === 'submit' ? 'selected' : '' ?>>Ajukan untuk persetujuan</option>
                        </select>
                        <div class="form-text">Konten akan tampil di website setelah disetujui Super Admin.</div>
                    </div><?php endif; ?>
            </div>
            <button class="btn btn-primary">Simpan</button> <a class="btn btn-outline-secondary" href="index.php?module=<?= e($moduleKey) ?>">Batal</a>
        </form>
    </div>
<?php require __DIR__ . '/includes/footer.php';
    exit;
}
$q = trim((string)($_GET['q'] ?? ''));
$cols = $cfg['columns'];
$staffJabatanOptions = [];
$staffStatusFilter = '';
$staffJabatanFilter = '';
if ($moduleKey === 'staf') {
    $staffJabatanOptions = $pdo->query("SELECT DISTINCT jabatan FROM staff WHERE jabatan<>'' ORDER BY jabatan")->fetchAll(PDO::FETCH_COLUMN);
    $staffStatusFilter = in_array((string)($_GET['status'] ?? ''), ['aktif', 'nonaktif', 'pensiun'], true) ? (string)$_GET['status'] : '';
    $requestedJabatan = trim((string)($_GET['jabatan'] ?? ''));
    $staffJabatanFilter = in_array($requestedJabatan, $staffJabatanOptions, true) ? $requestedJabatan : '';
}
$selectColumns = array_values(array_unique(array_merge([$pk], $cols, $isEditorialContent ? ['review_status'] : [])));
$sql = "SELECT `" . implode('`,`', $selectColumns) . "` FROM `$table`";
$params = [];
$whereParts = [];
if ($isOperatorAuthor && in_array('created_by', $editorialColumns, true)) {
    $whereParts[] = 'created_by=?';
    $params[] = (int)$_SESSION['user_id'];
}
if ($moduleKey === 'notifikasi') {
    $whereParts[] = '(recipient_user_id=?' . (in_array(current_role(), ['admin', 'operator'], true) ? ' OR recipient_user_id IS NULL' : '') . ')';
    $params[] = (int)($_SESSION['user_id'] ?? 0);
}
if ($moduleKey === 'staf') {
    if ($staffStatusFilter !== '') {
        $whereParts[] = 'status=?';
        $params[] = $staffStatusFilter;
    }
    if ($staffJabatanFilter !== '') {
        $whereParts[] = 'jabatan=?';
        $params[] = $staffJabatanFilter;
    }
}
if ($q !== '') {
    $searchable = array_values(array_filter($cols, fn($c) => !in_array($c, ['id', 'kelas_id', 'wali_guru_id', 'tahun_ajaran_id', 'guru_id', 'siswa_id', 'mapel_id', 'nilai_tugas', 'nilai_ujian', 'aktif'], true)));
    if ($searchable) {
        $whereParts[] = '(' . implode(' OR ', array_map(fn($c) => "`$c` LIKE ?", $searchable)) . ')';
        array_push($params, ...array_fill(0, count($searchable), "%$q%"));
    }
}
$whereSql = $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '';
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`" . $whereSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$pageCount = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $pageCount);
$offset = ($page - 1) * $perPage;
$sql .= $whereSql . " ORDER BY `$pk` DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$relationLabels = [];
$staticLabels = [];
foreach ($cols as $col) {
    if (isset($fields[$col]['relation'])) {
        $relationLabels[$col] = relation_options($pdo, $fields[$col]['relation']);
    } elseif (isset($fields[$col]['options'])) {
        $staticLabels[$col] = $fields[$col]['options'];
    }
}
$canView = can_view_module($moduleKey);
$canManage = can_manage_module($moduleKey);
$canCreate = $canManage && $moduleKey !== 'profil_sekolah';
$canDelete = $canManage && $moduleKey !== 'profil_sekolah';
// Kelas wajib ditautkan ke satu tahun ajaran; ambil jumlahnya agar halaman dapat menjelaskan prasyarat yang belum diisi.
$academicYearCount = $moduleKey === 'kelas' ? (int)$pdo->query('SELECT COUNT(*) FROM tahun_ajaran')->fetchColumn() : null;
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-secondary mb-0"><?= $canManage ? 'Kelola data: tambah, lihat, ubah, atau hapus.' : 'Mode baca saja sesuai hak akses akun.' ?></p>
    </div><?php if ($moduleKey === 'staf' && $canView): ?><a class="btn btn-outline-primary ms-auto" href="reports.php?type=staf"><i class="bi bi-bar-chart me-1" aria-hidden="true"></i>Laporan Ringkas</a><?php endif; ?><?php if ($canManage && ($canCreate || $moduleKey === 'absensi' || $moduleKey === 'staf')): ?><div class="d-flex flex-wrap gap-2"><?php if ($moduleKey === 'absensi'): ?><a class="btn btn-outline-primary" href="bulk_absensi.php">Input Satu Kelas</a><?php endif; ?><?php if ($moduleKey === 'staf'): ?><a class="btn btn-outline-primary" href="staff_import.php"><i class="bi bi-upload me-1" aria-hidden="true"></i>Impor CSV</a><a class="btn btn-outline-secondary" href="staff_export.php<?= e('?' . http_build_query(array_filter(['q' => $q, 'status' => $staffStatusFilter, 'jabatan' => $staffJabatanFilter], static fn($value): bool => $value !== ''))) ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>Ekspor CSV</a><?php endif; ?><?php if ($canCreate): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createRecordModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Data</button><?php endif; ?></div><?php endif; ?>
</div>
<?php if ($moduleKey === 'kelas' && $academicYearCount === 0): ?><div class="alert alert-warning" role="status"><strong>Data kelas belum dapat dibuat.</strong> Belum ada Tahun Ajaran. <?php if ($canManage): ?>Tambahkan tahun ajaran terlebih dahulu, lalu kembali untuk membuat kelas. <a class="alert-link" href="index.php?module=tahun_ajaran&open_add=1">Tambah Tahun Ajaran</a><?php else: ?>Hubungi Operator untuk membuat Tahun Ajaran, lalu kelas dapat ditambahkan.<?php endif; ?></div><?php elseif ($moduleKey === 'mapel' && !$rows && $canCreate): ?><div class="alert alert-info" role="status"><strong>Belum ada data mata pelajaran.</strong> Modul siap digunakan. Pilih <a class="alert-link" href="index.php?module=mapel&open_add=1">Tambah Data</a> untuk mulai mengisi.</div><?php endif; ?>
<?php if ($canCreate): ?>
    <div class="modal fade app-entry-modal" id="createRecordModal" tabindex="-1" aria-labelledby="createRecordTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="post" action="index.php?module=<?= e($moduleKey) ?>" <?= array_filter($fields, static fn($field) => in_array(($field['type'] ?? ''), ['image_upload', 'pdf_upload'], true)) ? 'enctype="multipart/form-data"' : '' ?>>
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="createRecordTitle">Tambah <?= e($pageTitle) ?></h2>
                            <p class="small text-secondary mb-0">Isi informasi yang diperlukan, lalu simpan data.</p>
                        </div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!empty($flashMessage) && ($flashMessage['type'] ?? '') === 'danger'): ?><div class="alert alert-danger" role="alert"><?= e($flashMessage['message']) ?></div><?php endif; ?>
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="create">
                        <div class="row"><?php foreach ($fields as $key => $field): if ($isOperatorAuthor && ($key === 'tampil_publik' || ($key === 'status' && $moduleKey !== 'kalender_akademik'))) continue;
                                                $fieldOptions = isset($field['relation']) ? relation_options($pdo, $field['relation']) : [];
                                                if ($moduleKey === 'kelas' && $key === 'tahun_ajaran_id' && !$fieldOptions): ?><div class="col-12">
                                        <div class="alert alert-warning py-2 mb-3">Kelas harus terhubung ke Tahun Ajaran. <a class="alert-link" href="index.php?module=tahun_ajaran&open_add=1">Buat Tahun Ajaran terlebih dahulu</a>, lalu buka kembali formulir kelas.</div>
                                    </div><?php elseif ($moduleKey === 'mapel' && $key === 'guru_id' && !$fieldOptions): ?><div class="col-12">
                                        <div class="form-text mb-2">Belum ada data Guru. Pengampu bersifat opsional, jadi mata pelajaran tetap dapat disimpan tanpa memilih Guru. <a href="index.php?module=guru&open_add=1">Tambah Guru</a> bila diperlukan.</div>
                                    </div><?php endif;
                                                render_input($key, $field, $_createFormRestore[$key] ?? $field['default'] ?? '', $fieldOptions);
                                            endforeach; ?>
                            <?php if ($isOperatorAuthor): ?><div class="col-12 mb-3"><label class="form-label" for="create_review_action">Tindakan konten</label><select class="form-select" id="create_review_action" name="review_action">
                                        <option value="draft" <?= ($_createFormRestore['_review_action'] ?? '') === 'draft' ? 'selected' : '' ?>>Simpan sebagai draf</option>
                                        <option value="submit" <?= ($_createFormRestore['_review_action'] ?? '') === 'submit' ? 'selected' : '' ?>>Ajukan untuk persetujuan</option>
                                    </select>
                                    <div class="form-text">Konten akan tampil di website setelah disetujui Super Admin.</div>
                                </div><?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Simpan</button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
        if (<?= isset($_GET['open_add']) ? 'true' : 'false' ?>) {
            window.addEventListener('load', () => {
                const modal = document.getElementById('createRecordModal');
                if (window.bootstrap && modal) bootstrap.Modal.getOrCreateInstance(modal).show()
            })
        }
    </script>
<?php endif; ?>
<div class="card p-3">
    <form class="row g-2 mb-3 no-print" method="get"><input type="hidden" name="module" value="<?= e($moduleKey) ?>">
        <div class="col-12 col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= $moduleKey === 'staf' ? 'Cari nama, NIP, jabatan, unit kerja...' : 'Cari data...' ?>"></div>
        <?php if ($moduleKey === 'staf'): ?><div class="col-12 col-sm-6 col-md-3"><select class="form-select" name="jabatan" aria-label="Filter jabatan">
                    <option value="">Semua jabatan</option><?php foreach ($staffJabatanOptions as $position): ?><option value="<?= e($position) ?>" <?= $staffJabatanFilter === $position ? 'selected' : '' ?>><?= e($position) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-12 col-sm-6 col-md-2"><select class="form-select" name="status" aria-label="Filter status">
                    <option value="">Semua status</option>
                    <option value="aktif" <?= $staffStatusFilter === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $staffStatusFilter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    <option value="pensiun" <?= $staffStatusFilter === 'pensiun' ? 'selected' : '' ?>>Pensiun</option>
                </select></div><?php endif; ?>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-primary">Cari / Filter</button> <a class="btn btn-outline-secondary" href="index.php?module=<?= e($moduleKey) ?>">Reset</a></div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No.</th><?php foreach ($cols as $col): ?><th><?= e($fields[$col]['label'] ?? field_label($col)) ?></th><?php endforeach; ?><th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <?php // Tampilkan langkah awal yang relevan supaya daftar kosong tidak disalahartikan sebagai modul mati.
                        ?>
                        <td colspan="<?= count($cols) + 2 ?>" class="text-center text-secondary py-4"><?php if ($moduleKey === 'kelas' && $academicYearCount === 0): ?>Belum ada kelas karena Tahun Ajaran belum dibuat. <?= $canManage ? 'Tambahkan Tahun Ajaran lebih dahulu.' : 'Hubungi Operator untuk menambah Tahun Ajaran.' ?><?php elseif ($moduleKey === 'mapel' && $canCreate): ?>Belum ada mata pelajaran. Gunakan tombol Tambah Data untuk mulai mengisi.<?php elseif ($moduleKey === 'mapel'): ?>Belum ada data mata pelajaran. Silakan hubungi Operator untuk melengkapinya.<?php else: ?>Belum ada data.<?php endif; ?></td>
                    </tr>
                    <?php else: foreach ($rows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <?php foreach ($cols as $col): $cell = $row[$col] ?? null;
                                if (isset($relationLabels[$col])) $cell = $relationLabels[$col][(string)$cell] ?? $cell;
                                elseif (isset($staticLabels[$col])) $cell = $staticLabels[$col][(string)$cell] ?? $cell; ?><td><?= e($cell ?? '-') ?><?php if ($isEditorialContent && $col === 'status' && isset($row['review_status']) && $row['review_status'] !== 'Disetujui'): ?><br><span class="badge text-bg-warning mt-1"><?= e($row['review_status']) ?></span><?php endif; ?></td><?php endforeach; ?>
                            <td class="no-print text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="index.php?module=<?= e($moduleKey) ?>&amp;action=view&amp;id=<?= (int)$row[$pk] ?>" aria-label="Lihat data"><i class="bi bi-eye" aria-hidden="true"></i><span class="d-none d-lg-inline ms-1">Lihat</span></a><?php if ($canManage): ?> <a class="btn btn-sm btn-outline-primary" href="index.php?module=<?= e($moduleKey) ?>&amp;action=edit&amp;id=<?= (int)$row[$pk] ?>" aria-label="Edit data"><i class="bi bi-pencil" aria-hidden="true"></i><span class="d-none d-lg-inline ms-1">Edit</span></a><?php endif; ?>
                                <?php if ($canDelete): ?><form class="d-inline" method="post" action="index.php?module=<?= e($moduleKey) ?>&amp;action=delete&amp;id=<?= (int)$row[$pk] ?>" onsubmit="return confirm('Yakin ingin menghapus data ini?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Hapus data"><i class="bi bi-trash" aria-hidden="true"></i><span class="d-none d-lg-inline ms-1">Hapus</span></button></form><?php endif; ?></td>
                        </tr><?php endforeach;
                        endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-secondary">Menampilkan <?= $totalRows ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $totalRows) ?> dari <?= $totalRows ?> data.</div><?php if ($pageCount > 1): ?><nav aria-label="Halaman data">
                <ul class="pagination pagination-sm mb-0"><?php for ($pageNumber = max(1, $page - 2); $pageNumber <= min($pageCount, $page + 2); $pageNumber++): ?><li class="page-item<?= $pageNumber === $page ? ' active' : '' ?>"><a class="page-link" href="index.php?<?= e(http_build_query(array_filter(['module' => $moduleKey, 'q' => $q, 'status' => $staffStatusFilter, 'jabatan' => $staffJabatanFilter, 'page' => $pageNumber], static fn($value): bool => $value !== ''))) ?>"><?= $pageNumber ?></a></li><?php endfor; ?></ul>
            </nav><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
