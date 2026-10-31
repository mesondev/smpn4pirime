<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
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
if ($moduleKey === '') {
    $pageTitle = 'Dashboard';
    $today = new DateTimeImmutable('today');
    $period = (string)($_GET['periode'] ?? 'month');
    if (!in_array($period, ['today', 'week', 'month', 'custom'], true)) $period = 'month';
    $periodLabels = ['today' => 'Hari ini', 'week' => 'Minggu ini', 'month' => '30 hari terakhir', 'custom' => 'Rentang khusus'];
    $startInput = trim((string)($_GET['mulai'] ?? ''));
    $endInput = trim((string)($_GET['sampai'] ?? ''));
    $filterError = '';
    $periodStart = match ($period) {
        'today' => $today,
        'week' => $today->modify('monday this week'),
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
    if (in_array(current_role(), ['admin', 'operator'], true)) {
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
    $recentOperatorStudents = [];
    $recentOperatorTeachers = [];
    $operatorClassOverview = [];
    $operatorTodaySchedule = [];
    $todayAttendanceByClass = [];
    $operatorNotifications = [];
    if (current_role() === 'operator') {
        try {
            $todayStmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi WHERE tanggal=? GROUP BY status');
            $todayStmt->execute([$today->format('Y-m-d')]);
            foreach ($todayStmt->fetchAll() as $row) if (isset($todayStudentAttendance[$row['status']])) $todayStudentAttendance[$row['status']] = (int)$row['jumlah'];
            $todayTotal = array_sum($todayStudentAttendance);
            $todayAttendanceRate = $todayTotal > 0 ? round($todayStudentAttendance['Hadir'] * 100 / $todayTotal, 1) : null;
        } catch (PDOException $ignored) {
            $todayAttendanceRate = null;
        }
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

    $announcements = $pdo->query("SELECT judul,isi,mulai_tayang FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE()) ORDER BY created_at DESC LIMIT 4")->fetchAll();
    $upcomingEvents = $pdo->query("SELECT judul,tanggal_mulai AS tanggal,kategori AS jenis FROM kalender_akademik WHERE tanggal_mulai>=CURDATE() AND status<>'Dibatalkan' UNION ALL SELECT nama_kegiatan,tanggal_mulai,'Kokurikuler' FROM kegiatan_kokurikuler WHERE tanggal_mulai>=CURDATE() AND status<>'Dibatalkan' ORDER BY tanggal LIMIT 5")->fetchAll();
    $schoolName = (string)$pdo->query('SELECT nama FROM profil_sekolah ORDER BY id LIMIT 1')->fetchColumn();
    if ($schoolName === '') $schoolName = 'SMP Negeri 4 Pirime';
    $recentActivities = [];
    if (current_role() === 'admin') {
        try {
            $recentActivities = $pdo->query('SELECT l.aksi,l.entitas,l.detail,l.created_at,u.nama_lengkap FROM log_aktivitas l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC,l.id DESC LIMIT 6')->fetchAll();
        } catch (PDOException $ignored) {
            $recentActivities = [];
        }
    }
    require __DIR__ . '/includes/header.php';
?>
    <div class="dashboard-heading">
        <div>
            <h1><?= current_role() === 'admin' ? 'Dashboard Super Admin' : (current_role() === 'kepala_sekolah' ? 'Dashboard Kepala Sekolah' : 'Dashboard Operator') ?></h1>
            <p><?= current_role() === 'admin' ? 'Pusat kontrol akun, data, keamanan, dan aktivitas sistem.' : (current_role() === 'kepala_sekolah' ? 'Ringkasan pemantauan sekolah dan kehadiran.' : 'Ringkasan pekerjaan administrasi sekolah.') ?> · <?= e($schoolName) ?></p>
        </div>
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
            <?php if (current_role() === 'kepala_sekolah'): ?><div><label class="form-label small mb-1" for="semester">Semester Nilai</label><select class="form-select form-select-sm" id="semester" name="semester"><option value="">Semua semester</option><option value="1" <?= $academicSemester === '1' ? 'selected' : '' ?>>Ganjil</option><option value="2" <?= $academicSemester === '2' ? 'selected' : '' ?>>Genap</option></select></div><?php endif; ?>
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
                <input class="form-control form-control-sm" id="mulai" type="date" name="mulai" value="
                <?= e($periodStart->format('Y-m-d')) ?>"
                    <?= $period === 'custom' ? '' : ' disabled' ?>>
            </div>
            <div>
                <label class="form-label small mb-1" for="sampai">Sampai</label>
                <input class="form-control form-control-sm" id="sampai" type="date" name="sampai" value="
                    <?= e($periodEnd->format('Y-m-d')) ?>"
                    <?= $period === 'custom' ? '' : ' disabled' ?>>
            </div>
            <button class="btn btn-primary btn-sm">Terapkan</button>
        </form>
    </div>
    <?php if ($filterError !== ''): ?>
        <div class="alert alert-warning no-print">
            <?= e($filterError) ?>
        </div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
        <?php $dashboardStats = current_role() === 'admin' ? [
            ['Total Pengguna', $adminAccountStats['total'], 'bi-people-fill', 'blue', 'Akun yang terdaftar'],
            ['Super Admin', $adminAccountStats['admin'], 'bi-shield-lock', 'purple', 'Pengelola sistem'],
            ['Operator', $adminAccountStats['operator'], 'bi-person-gear', 'green', 'Akun operator'],
            ['Kepala Sekolah', $adminAccountStats['kepala_sekolah'], 'bi-person-vcard', 'orange', 'Akun kepala sekolah'],
            ['Guru', $adminAccountStats['guru'], 'bi-person-badge-fill', 'blue', 'Akun guru'],
            ['Siswa', $adminAccountStats['siswa'], 'bi-mortarboard', 'green', 'Akun siswa'],
            ['Menunggu Persetujuan', $pendingBiodataTotal, 'bi-person-check', 'orange', 'Biodata diajukan untuk diperiksa'],
            ['Akun Nonaktif', $adminAccountStats['nonaktif'], 'bi-person-x', 'purple', 'Akses login dinonaktifkan'],
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
        <section class="row g-3 mb-4" aria-label="Ringkasan administrasi operator">
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
    <?php if (current_role() === 'admin'): ?><div class="row g-3 mb-4">
            <div class="col-xl-4">
                <section class="card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Kontrol Sistem</h2><i class="bi bi-shield-check text-primary fs-4"></i>
                    </div>
                    <div class="d-grid gap-2"><a class="btn btn-outline-primary text-start" href="users.php"><i class="bi bi-person-gear me-2"></i>Manajemen Pengguna</a><a class="btn btn-outline-primary text-start" href="biodata.php"><i class="bi bi-person-check me-2"></i>Persetujuan Biodata (<?= number_format($pendingBiodataTotal, 0, ',', '.') ?> menunggu)</a><a class="btn btn-outline-primary text-start" href="passwords.php"><i class="bi bi-key me-2"></i>Reset Password</a><a class="btn btn-outline-primary text-start" href="index.php?module=guru"><i class="bi bi-person-badge me-2"></i>Data Guru</a><a class="btn btn-outline-primary text-start" href="activity.php"><i class="bi bi-clock-history me-2"></i>Log Aktivitas</a><a class="btn btn-outline-primary text-start" href="learning.php?view=materi"><i class="bi bi-mortarboard me-2"></i>Pemantauan Materi & Tugas</a><a class="btn btn-outline-primary text-start" href="index.php?module=profil_sekolah"><i class="bi bi-gear me-2"></i>Pengaturan Sekolah</a></div>
                    <p class="small text-secondary mt-3 mb-0">Backup dan pemulihan belum tersedia sebagai fitur aplikasi. Siapkan backup terjadwal sebelum mengelola database produksi.</p>
                </section>
            </div>
            <div class="col-xl-8">
                <section class="card p-3 p-md-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Aktivitas Terbaru</h2><a class="small" href="activity.php">Buka log lengkap</a>
                    </div><?php if (!$recentActivities): ?>
                        <div class="empty-state">Belum ada aktivitas tercatat.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Pengguna</th>
                                        <th>Tindakan</th>
                                        <th>Modul</th>
                                    </tr>
                                </thead>
                                <tbody><?php foreach ($recentActivities as $activity): ?><tr>
                                            <td class="text-nowrap"><?= e($activity['created_at']) ?></td>
                                            <td><?= e($activity['nama_lengkap'] ?: 'Akun dihapus') ?></td>
                                            <td><?= e($activity['aksi']) ?></td>
                                            <td><?= e($activity['entitas']) ?></td>
                                        </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </section>
            </div>
        </div><?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card chart-card">
                <div>
                    <h2 class="chart-title">Tren Kehadiran Siswa</h2>
                    <div class="chart-subtitle">Persentase hadir harian · <?= e($periodLabels[$period]) ?> · <?= e($rangeStart) ?> sampai <?= e($rangeEnd) ?></div>
                </div>
                <div class="chart-canvas"><?php if (!$dailyRows): ?><div class="empty-chart">Belum ada catatan absensi siswa pada periode ini.</div><?php else: ?><canvas id="attendanceTrend"></canvas><?php endif; ?></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card chart-card">
                <h2 class="chart-title">Kehadiran Siswa</h2>
                <div class="chart-subtitle">Rekap status · <?= e($rangeStart) ?> – <?= e($rangeEnd) ?></div>
                <div class="donut-layout">
                    <div class="chart-canvas"><?php if (array_sum($studentAttendance) === 0): ?><div class="empty-chart">Belum ada data.</div><?php else: ?><canvas id="studentDonut"></canvas><?php endif; ?></div>
                    <ul class="chart-legend" id="studentLegend"></ul>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-xl-4">
            <div class="card chart-card">
                <h2 class="chart-title">Kehadiran Guru</h2>
                <div class="chart-subtitle">Rekap status · <?= e($rangeStart) ?> – <?= e($rangeEnd) ?></div>
                <div class="donut-layout">
                    <div class="chart-canvas"><?php if (array_sum($teacherAttendance) === 0): ?><div class="empty-chart">Belum ada data.</div><?php else: ?><canvas id="teacherDonut"></canvas><?php endif; ?></div>
                    <ul class="chart-legend" id="teacherLegend"></ul>
                </div>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="card chart-card">
                <h2 class="chart-title">Persentase Kehadiran Bulanan</h2>
                <div class="chart-subtitle">Perbandingan per pekan dalam rentang yang dipilih</div>
                <div class="chart-canvas compact"><?php if (!$weeklyRates): ?><div class="empty-chart">Belum ada data absensi untuk dibandingkan.</div><?php else: ?><canvas id="weeklyAttendance"></canvas><?php endif; ?></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4">
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
        <section class="mb-4" aria-labelledby="principal-monitoring-title">
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
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><div><h3 class="chart-title">Identitas Sekolah</h3><p class="chart-subtitle mb-0">Data profil sekolah yang tercatat.</p></div><?php if (can_view_module('profil_sekolah')): ?><a class="small text-decoration-none" href="index.php?module=profil_sekolah">Lihat profil</a><?php endif; ?></div>
                        <dl class="principal-school-facts mb-0"><div><dt>Nama sekolah</dt><dd><?= e($principalSchoolProfile['nama'] ?? 'Belum tersedia') ?></dd></div><div><dt>NPSN</dt><dd><?= e($principalSchoolProfile['npsn'] ?: 'Belum tersedia') ?></dd></div><div><dt>Tahun ajaran</dt><dd><?= e($activeYearName !== '' ? $activeYearName : 'Belum diatur') ?></dd></div><div><dt>Alamat</dt><dd><?= e($principalSchoolProfile['alamat'] ?: 'Belum tersedia') ?></dd></div><div><dt>Email sekolah</dt><dd><?= e($principalSchoolProfile['email'] ?: 'Belum tersedia') ?></dd></div></dl>
                        <p class="small text-secondary mt-2 mb-0">Semester aktif dan data status operasional belum tersedia sebagai kolom khusus pada profil sekolah.</p>
                    </div>
                </div>
                <div class="col-12 col-xl-7">
                    <div class="card chart-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><div><h3 class="chart-title">Komposisi Warga Sekolah</h3><p class="chart-subtitle mb-0">Jumlah dari data induk aktif · tahun ajaran <?= e($activeYearName ?: 'belum dipilih') ?>.</p></div></div>
                        <div class="row g-2">
                            <div class="col-6 col-md-3"><div class="principal-mini-stat"><small>Siswa laki-laki</small><strong><?= $principalStudentGender['L'] === null ? 'Belum tersedia' : number_format($principalStudentGender['L'], 0, ',', '.') ?></strong></div></div>
                            <div class="col-6 col-md-3"><div class="principal-mini-stat"><small>Siswa perempuan</small><strong><?= $principalStudentGender['P'] === null ? 'Belum tersedia' : number_format($principalStudentGender['P'], 0, ',', '.') ?></strong></div></div>
                            <div class="col-6 col-md-3"><div class="principal-mini-stat"><small>Guru wali kelas</small><strong><?= $principalHomeroomTeachers === null ? 'Belum tersedia' : number_format($principalHomeroomTeachers, 0, ',', '.') ?></strong></div></div>
                            <div class="col-6 col-md-3"><div class="principal-mini-stat"><small>Mata pelajaran</small><strong><?= number_format($subjectTotal, 0, ',', '.') ?></strong></div></div>
                            <div class="col-6 col-md-3"><div class="principal-mini-stat"><small>Guru laki-laki</small><strong><?= $principalTeacherGender['L'] === null ? 'Belum tersedia' : number_format($principalTeacherGender['L'], 0, ',', '.') ?></strong></div></div>
                            <div class="col-6 col-md-3"><div class="principal-mini-stat"><small>Guru perempuan</small><strong><?= $principalTeacherGender['P'] === null ? 'Belum tersedia' : number_format($principalTeacherGender['P'], 0, ',', '.') ?></strong></div></div>
                        </div>
                        <p class="small text-secondary mt-3 mb-0">Jumlah tenaga kependidikan/staf belum dapat diringkas karena SIMS belum memiliki data induk staf terpisah.</p>
                        <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-sm btn-outline-primary" href="reports.php?type=siswa"><i class="bi bi-people me-1"></i>Data siswa</a><a class="btn btn-sm btn-outline-primary" href="reports.php?type=guru"><i class="bi bi-person-badge me-1"></i>Data guru</a><a class="btn btn-sm btn-outline-primary" href="reports.php?type=kelas"><i class="bi bi-door-open me-1"></i>Data kelas</a></div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-3" aria-label="Ringkasan kehadiran hari ini">
                <?php $studentMarkedToday = array_sum($principalTodayStudentAttendance); $teacherMarkedToday = array_sum($principalTodayTeacherAttendance); ?>
                <?php foreach ([['Kehadiran Siswa · hari ini', $principalTodayStudentAttendance, $totalStudents, $studentMarkedToday, 'reports.php?type=absensi'], ['Kehadiran Guru · hari ini', $principalTodayTeacherAttendance, $teacherTotal, $teacherMarkedToday, 'reports.php?type=absensi_guru']] as [$attendanceTitle, $attendanceCounts, $expectedCount, $markedCount, $attendanceLink]): ?>
                    <div class="col-12 col-xl-6"><div class="card chart-card h-100"><div class="d-flex justify-content-between align-items-start gap-2"><div><h3 class="chart-title"><?= e($attendanceTitle) ?></h3><p class="chart-subtitle">Hanya catatan absensi yang sudah dimasukkan per <?= e($today->format('d/m/Y')) ?>.</p></div><a class="small text-decoration-none" href="<?= e($attendanceLink) ?>">Lihat laporan</a></div>
                        <?php if (!$principalAttendanceDataAvailable): ?><div class="empty-state">Data kehadiran hari ini belum tersedia.</div><?php else: ?><div class="principal-attendance-summary"><?php foreach (['Hadir','Izin','Sakit','Alpa'] as $attendanceStatus): ?><div><small><?= e($attendanceStatus) ?></small><strong><?= number_format($attendanceCounts[$attendanceStatus], 0, ',', '.') ?></strong></div><?php endforeach; ?><div class="is-pending"><small>Belum tercatat*</small><strong><?= number_format(max(0, $expectedCount - $markedCount), 0, ',', '.') ?></strong></div></div><p class="small text-secondary mb-0 mt-2">*Perkiraan selisih jumlah data induk aktif dengan catatan hari ini; periksa laporan untuk tindak lanjut.</p><?php endif; ?>
                    </div></div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mb-3" aria-label="Daftar tindak lanjut kepala sekolah">
                <?php foreach ([
                    ['Nilai belum diterbitkan', $principalUnpublishedGrades, 'bi-journal-x', 'reports.php?type=nilai', 'Tahun ajaran terpilih'],
                    ['Tugas menunggu penilaian', $principalPendingTaskReviews, 'bi-clipboard2-check', 'reports.php?type=pengumpulan&status=belum', 'Tugas terbit tanpa nilai'],
                    ['Kelas tanpa jadwal', $principalClassesWithoutSchedule, 'bi-calendar-x', 'reports.php?type=jadwal', 'Tahun ajaran terpilih'],
                    ['Materi masih draf', $principalDraftMaterials, 'bi-file-earmark-text', 'reports.php?type=materi&status=Draft', 'Perlu ditinjau guru'],
                    ['Biodata perlu pemeriksaan', $principalPendingBiodata, 'bi-person-check', '', 'Perlu ditindaklanjuti Operator'],
                    ['Kelas tanpa wali', $principalUnassignedClasses, 'bi-people', 'reports.php?type=kelas', 'Tahun ajaran terpilih'],
                ] as [$actionLabel, $actionValue, $actionIcon, $actionUrl, $actionNote]): ?>
                    <div class="col-6 col-xl-4"><article class="card principal-action-card h-100"><span class="principal-action-icon"><i class="bi <?= e($actionIcon) ?>"></i></span><div><span class="principal-action-label"><?= e($actionLabel) ?></span><strong><?= $actionValue === null ? 'Belum tersedia' : number_format((int)$actionValue, 0, ',', '.') ?></strong><small><?= e($actionNote) ?></small></div><?php if ($actionUrl !== ''): ?><a href="<?= e($actionUrl) ?>" aria-label="Buka <?= e($actionLabel) ?>"><i class="bi bi-arrow-up-right"></i></a><?php endif; ?></article></div>
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
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><h3 class="chart-title">Jadwal Pelajaran Hari Ini</h3><p class="chart-subtitle mb-0"><?= e($principalWeekdays[(int)$today->format('N')] ?? '') ?> · <?= e($today->format('d/m/Y')) ?> · sumber: jadwal kelas.</p></div><a class="small text-decoration-none" href="reports.php?type=jadwal">Lihat jadwal lengkap</a></div>
                        <?php if (!$principalTodaySchedule): ?><div class="empty-state">Belum ada jadwal yang tercatat untuk hari ini.</div><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Waktu</th><th>Kelas</th><th>Mata pelajaran</th><th>Guru</th><th>Ruang</th></tr></thead><tbody><?php foreach ($principalTodaySchedule as $schedule): ?><tr><td class="text-nowrap"><?= e(substr((string)$schedule['jam_mulai'], 0, 5)) ?>–<?= e(substr((string)$schedule['jam_selesai'], 0, 5)) ?></td><td><?= e($schedule['nama_kelas']) ?></td><td><?= e($schedule['nama_mapel']) ?></td><td><?= e($schedule['guru'] ?: 'Belum ditentukan') ?></td><td><?= e($schedule['ruang'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
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
                        <div class="d-flex justify-content-between align-items-center mb-2"><div><h3 class="chart-title">Pengajuan Rencana Ajar</h3><p class="chart-subtitle mb-0">Pengajuan guru yang perlu ditinjau.</p></div><?php if (can_view_module('rencana_ajar')): ?><a class="small text-decoration-none" href="index.php?module=rencana_ajar">Buka semua</a><?php endif; ?></div>
                        <?php if (!$principalSubmittedPlans): ?><div class="empty-state">Tidak ada pengajuan rencana ajar yang menunggu tinjauan.</div><?php else: ?><div class="principal-activity-list"><?php foreach ($principalSubmittedPlans as $plan): ?><article><span class="principal-activity-dot" aria-hidden="true"></span><div><strong><?= e($plan['judul']) ?></strong><small><?= e($plan['guru']) ?> · <?= e($plan['nama_kelas']) ?> · <?= e($plan['nama_mapel']) ?></small><p>Semester <?= e($plan['semester'] === '1' ? 'Ganjil' : 'Genap') ?></p></div></article><?php endforeach; ?></div><?php endif; ?>
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
    <?php if (can_view_module('kelas_berlangsung')): ?><div class="card chart-card mb-4">
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
                if (typeof Chart === 'undefined') return;
                Chart.defaults.font.family = "'DM Sans', sans-serif";
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
                                        color: '#edf1f6'
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
                                        color: '#edf1f6'
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
    'berita_sekolah' => ['berita_sekolah' => ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at', 'review_status']],
    'galeri_sekolah' => ['galeri_sekolah' => ['id', 'judul', 'keterangan', 'gambar_url', 'tanggal_kegiatan', 'status', 'created_at', 'review_status']],
    'pengumuman' => ['pengumuman' => ['review_status']],
    'kalender_akademik' => ['kalender_akademik' => ['tampil_publik', 'review_status', 'created_by']],
    'profil_sekolah' => ['profil_sekolah' => ['sejarah', 'visi', 'misi', 'sambutan', 'jam_layanan', 'peta_url']],
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
function portal_remove_upload(mixed $path): void
{
    $path = (string)$path;
    if (!preg_match('~^uploads/portal/([a-f0-9]{40}\.(?:jpg|png|webp))$~i', $path, $match)) return;
    $root = realpath(__DIR__ . '/uploads/portal');
    $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $match[1]) : false;
    if ($root && $file && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
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
    echo '<div class="col-md-6 mb-3"><label class="form-label' . ($required ? ' required' : '') . '" for="' . e($key) . '">' . e($label) . '</label>';
    if ($type === 'textarea') {
        echo '<textarea class="form-control" id="' . e($key) . '" name="' . e($key) . '" rows="3">' . e($value) . '</textarea>';
    } elseif ($type === 'image_upload') {
        $hasStoredUpload = is_string($value) && preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $value);
        echo '<input class="form-control" id="' . e($key) . '" name="' . e($key) . '" type="file" accept="image/jpeg,image/png,image/webp"' . ($required && !$hasStoredUpload ? ' required' : '') . '>';
        echo '<div class="form-text">JPG, PNG, atau WebP; maksimal 5 MB. Gambar baru menggantikan gambar lama saat formulir disimpan.</div>';
        if (is_string($value) && preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $value) && !empty($field['media_type'])) {
            $entityId = (int)($_GET['id'] ?? 0);
            if ($entityId > 0) echo '<div class="mt-2"><img src="media/portal.php?type=' . e($field['media_type']) . '&id=' . $entityId . '" alt="Pratinjau gambar saat ini" style="max-width:240px;max-height:160px;object-fit:cover;border-radius:8px"></div>';
        }
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
        echo '<input class="form-control" id="' . e($key) . '" name="' . e($key) . '" type="' . $htmlType . '" value="' . e($value) . '"' . ($required ? ' required' : '');
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
        $imageToRemove = null;
        if ($isOperatorAuthor && in_array('created_by', $editorialColumns, true)) {
            $owner = $pdo->prepare("SELECT created_by FROM `$table` WHERE `$pk`=?");
            $owner->execute([$id]);
            if ((int)$owner->fetchColumn() !== (int)$_SESSION['user_id']) {
                http_response_code(403);
                exit('Anda hanya dapat menghapus konten yang dibuat oleh akun Anda.');
            }
        }
        if (in_array($moduleKey, ['berita_sekolah', 'galeri_sekolah'], true)) {
            $imageLookup = $pdo->prepare("SELECT gambar_url FROM `$table` WHERE `$pk`=?");
            $imageLookup->execute([$id]);
            $imageToRemove = $imageLookup->fetchColumn() ?: null;
        }
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
        $stmt->execute([$id]);
        if ($imageToRemove !== null) portal_remove_upload($imageToRemove);
        record_activity($pdo, 'hapus', $table, $id);
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
            if (($field['type'] ?? '') === 'image_upload') {
                $existing = (string)($record[$key] ?? '');
                $upload = $_FILES[$key] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $hasExistingUpload = (bool)preg_match('~^uploads/portal/[a-f0-9]{40}\.(?:jpg|png|webp)$~i', $existing);
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    if (!empty($field['required']) && !$hasExistingUpload) $error = 'Silakan unggah ' . strtolower($field['label'] ?? 'gambar') . ' melalui formulir ini.';
                    $values[$key] = $existing === '' ? null : $existing;
                } else {
                    [$storedImage, $uploadError] = portal_store_upload($upload);
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
                    record_activity($pdo, 'tambah', $table, $savedId);
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
                    record_activity($pdo, 'ubah', $table, $id);
                    if ($isEditorialContent && isset($values['review_status']) && $previousReviewStatus !== $values['review_status']) {
                        $reviewLog = $pdo->prepare('INSERT INTO konten_review_log (entitas,record_id,status_awal,status_akhir,catatan,user_id) VALUES (?,?,?,?,?,?)');
                        $reviewLog->execute([$moduleKey, $id, $previousReviewStatus, $values['review_status'], $values['review_note'] ?? null, (int)$_SESSION['user_id']]);
                    }
                    flash('Data berhasil diperbarui.');
                }
                $pdo->commit();
                foreach ($oldUploads as $oldUpload) portal_remove_upload($oldUpload);
                redirect("index.php?module=$moduleKey");
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($newUploads as $newUpload) portal_remove_upload($newUpload);
                if (!$ex instanceof PDOException) app_log('error', 'Portal record save failed', ['module' => $moduleKey, 'type' => get_class($ex)]);
                $error = 'Data gagal disimpan. Periksa nomor yang duplikat dan pastikan ID relasi (kelas/guru/siswa/mapel/tahun ajaran) benar.';
            }
        } else {
            foreach ($newUploads as $newUpload) portal_remove_upload($newUpload);
        }
        $record = array_merge($record, $values);
    }
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
        foreach ($fields as $restoreKey => $restoreField) if (($restoreField['type'] ?? '') === 'image_upload') unset($values[$restoreKey]);
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
        <form method="post" <?= array_filter($fields, static fn($field) => ($field['type'] ?? '') === 'image_upload') ? ' enctype="multipart/form-data"' : '' ?>><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="save">
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <div class="row"><?php foreach ($fields as $key => $field): if ($isOperatorAuthor && ($key === 'tampil_publik' || ($key === 'status' && $moduleKey !== 'kalender_akademik'))) continue;
                                    render_input($key, $field, $record[$key] ?? $field['default'] ?? '', isset($field['relation']) ? relation_options($pdo, $field['relation']) : []);
                                endforeach; ?>
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
    </div><?php if ($canManage && ($canCreate || $moduleKey === 'absensi')): ?><div class="d-flex gap-2"><?php if ($moduleKey === 'absensi'): ?><a class="btn btn-outline-primary" href="bulk_absensi.php">Input Satu Kelas</a><?php endif; ?><?php if ($canCreate): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createRecordModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Data</button><?php endif; ?></div><?php endif; ?>
</div>
<?php if ($moduleKey === 'kelas' && $academicYearCount === 0): ?><div class="alert alert-warning" role="status"><strong>Data kelas belum dapat dibuat.</strong> Belum ada Tahun Ajaran. <?php if ($canManage): ?>Tambahkan tahun ajaran terlebih dahulu, lalu kembali untuk membuat kelas. <a class="alert-link" href="index.php?module=tahun_ajaran&open_add=1">Tambah Tahun Ajaran</a><?php else: ?>Hubungi Operator untuk membuat Tahun Ajaran, lalu kelas dapat ditambahkan.<?php endif; ?></div><?php elseif ($moduleKey === 'mapel' && !$rows && $canCreate): ?><div class="alert alert-info" role="status"><strong>Belum ada data mata pelajaran.</strong> Modul siap digunakan. Pilih <a class="alert-link" href="index.php?module=mapel&open_add=1">Tambah Data</a> untuk mulai mengisi.</div><?php endif; ?>
<?php if ($canCreate): ?>
    <div class="modal fade app-entry-modal" id="createRecordModal" tabindex="-1" aria-labelledby="createRecordTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form method="post" action="index.php?module=<?= e($moduleKey) ?>" <?= array_filter($fields, static fn($field) => ($field['type'] ?? '') === 'image_upload') ? 'enctype="multipart/form-data"' : '' ?>>
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
        <div class="col-sm-8 col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Cari data..."></div>
        <div class="col-auto"><button class="btn btn-outline-primary">Cari</button> <a class="btn btn-outline-secondary" href="index.php?module=<?= e($moduleKey) ?>">Reset</a></div>
    </form>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No.</th><?php foreach ($cols as $col): ?><th><?= e($fields[$col]['label'] ?? field_label($col)) ?></th><?php endforeach; ?><?php if ($canManage): ?><th class="no-print">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <?php // Tampilkan langkah awal yang relevan supaya daftar kosong tidak disalahartikan sebagai modul mati. 
                        ?>
                        <td colspan="<?= count($cols) + ($canManage ? 2 : 1) ?>" class="text-center text-secondary py-4"><?php if ($moduleKey === 'kelas' && $academicYearCount === 0): ?>Belum ada kelas karena Tahun Ajaran belum dibuat. <?= $canManage ? 'Tambahkan Tahun Ajaran lebih dahulu.' : 'Hubungi Operator untuk menambah Tahun Ajaran.' ?><?php elseif ($moduleKey === 'mapel' && $canCreate): ?>Belum ada mata pelajaran. Gunakan tombol Tambah Data untuk mulai mengisi.<?php elseif ($moduleKey === 'mapel'): ?>Belum ada data mata pelajaran. Silakan hubungi Operator untuk melengkapinya.<?php else: ?>Belum ada data.<?php endif; ?></td>
                    </tr>
                    <?php else: foreach ($rows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <?php foreach ($cols as $col): $cell = $row[$col] ?? null;
                                if (isset($relationLabels[$col])) $cell = $relationLabels[$col][(string)$cell] ?? $cell;
                                elseif (isset($staticLabels[$col])) $cell = $staticLabels[$col][(string)$cell] ?? $cell; ?><td><?= e($cell ?? '-') ?><?php if ($isEditorialContent && $col === 'status' && isset($row['review_status']) && $row['review_status'] !== 'Disetujui'): ?><br><span class="badge text-bg-warning mt-1"><?= e($row['review_status']) ?></span><?php endif; ?></td><?php endforeach; ?>
                            <?php if ($canManage): ?><td class="no-print text-nowrap"><a class="btn btn-sm btn-outline-primary" href="index.php?module=<?= e($moduleKey) ?>&action=edit&id=<?= (int)$row[$pk] ?>">Edit</a>
                                    <?php if ($canDelete): ?><form class="d-inline" method="post" action="index.php?module=<?= e($moduleKey) ?>&action=delete&id=<?= (int)$row[$pk] ?>" onsubmit="return confirm('Yakin ingin menghapus data ini?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-outline-danger">Hapus</button></form><?php endif; ?></td><?php endif; ?>
                        </tr><?php endforeach;
                        endif; ?>
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="small text-secondary">Menampilkan <?= $totalRows ? $offset + 1 : 0 ?>–<?= min($offset + $perPage, $totalRows) ?> dari <?= $totalRows ?> data.</div><?php if ($pageCount > 1): ?><nav aria-label="Halaman data">
                <ul class="pagination pagination-sm mb-0"><?php for ($pageNumber = max(1, $page - 2); $pageNumber <= min($pageCount, $page + 2); $pageNumber++): ?><li class="page-item<?= $pageNumber === $page ? ' active' : '' ?>"><a class="page-link" href="index.php?<?= e(http_build_query(['module' => $moduleKey, 'q' => $q, 'page' => $pageNumber])) ?>"><?= $pageNumber ?></a></li><?php endfor; ?></ul>
            </nav><?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
