<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
if (current_role() !== 'guru') {
    http_response_code(403);
    exit('Halaman ini hanya untuk akun guru.');
}
$link = $pdo->prepare('SELECT role,guru_id,aktif FROM users WHERE id=?');
$link->execute([(int)$_SESSION['user_id']]);
$account = $link->fetch();
if (!$account || $account['role'] !== 'guru' || (int)$account['aktif'] !== 1) {
    http_response_code(403);
    exit('Akun guru tidak aktif atau perannya telah berubah. Silakan masuk kembali.');
}
$teacherId = (int)($account['guru_id'] ?? 0);
if ($teacherId < 1) {
    http_response_code(403);
    exit('Akun belum ditautkan ke data guru. Hubungi Super Admin sekolah.');
}
$q = $pdo->prepare('SELECT id,nama,nip,status FROM guru WHERE id=?');
$q->execute([$teacherId]);
$teacher = $q->fetch();
if (!$teacher) {
    http_response_code(404);
    exit('Data guru tertaut tidak ditemukan.');
}
$gradePublicationReady = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='nilai' AND column_name='status_publikasi'")->fetchColumn() === 1;
$assignStmt = $pdo->prepare('SELECT DISTINCT j.kelas_id,j.mapel_id,j.tahun_ajaran_id,k.nama_kelas,m.nama_mapel,ta.nama AS tahun_ajaran FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id JOIN tahun_ajaran ta ON ta.id=j.tahun_ajaran_id WHERE j.guru_id=? AND ta.aktif=1 ORDER BY k.nama_kelas,m.nama_mapel');
$assignStmt->execute([$teacherId]);
$assignments = $assignStmt->fetchAll();
$view = (string)($_GET['view'] ?? 'dashboard');
if (!in_array($view, ['dashboard', 'jadwal', 'kelas', 'rencana', 'absensi', 'nilai'], true)) $view = 'dashboard';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $view = (string)($_POST['view'] ?? 'dashboard');
    try {
        if ($view === 'absensi' && ($_POST['action'] ?? '') === 'save_absensi') {
            $classId = (int)($_POST['kelas_id'] ?? 0);
            $date = (string)($_POST['tanggal'] ?? '');
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > date('Y-m-d')) throw new InvalidArgumentException('Tanggal absensi harus valid dan tidak boleh di masa depan.');
            $assigned = array_filter($assignments, static fn($a) => (int)$a['kelas_id'] === $classId);
            if (!$assigned) throw new InvalidArgumentException('Kelas ini bukan bagian dari jadwal aktif Anda.');
            $rosterQuery = $pdo->prepare("SELECT id FROM siswa WHERE kelas_id=? AND status='Aktif' ORDER BY nama");
            $rosterQuery->execute([$classId]);
            $studentIds = array_map('intval', $rosterQuery->fetchAll(PDO::FETCH_COLUMN));
            $posted = $_POST['status_siswa'] ?? [];
            if (!$studentIds || !is_array($posted)) throw new InvalidArgumentException('Tidak ada siswa aktif atau data absensi tidak valid.');
            $pdo->beginTransaction();
            $save = $pdo->prepare('INSERT INTO absensi (siswa_id,tanggal,status,keterangan) VALUES (?,?,?,NULL) ON DUPLICATE KEY UPDATE status=VALUES(status)');
            foreach ($studentIds as $sid) {
                $status = (string)($posted[$sid] ?? '');
                if (!in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpa'], true)) throw new InvalidArgumentException('Pilih status absensi yang valid untuk setiap siswa.');
                $save->execute([$sid, $date, $status]);
            }
            $pdo->commit();
            record_activity($pdo, 'input absensi kelas', 'absensi', null, 'Guru ' . $teacherId . ' · kelas ' . $classId . ' · ' . $date);
            $success = 'Absensi kelas berhasil disimpan.';
        } elseif ($view === 'rencana' && ($_POST['action'] ?? '') === 'save_rencana') {
            $planId = (int)($_POST['id'] ?? 0);
            $planAssignmentParts = explode('|', (string)($_POST['assignment'] ?? ''));
            $classId = (int)($planAssignmentParts[0] ?? 0);
            $mapelId = (int)($planAssignmentParts[1] ?? 0);
            $yearId = (int)($planAssignmentParts[2] ?? 0);
            $semester = (string)($_POST['semester'] ?? '');
            $authorized = false;
            foreach ($assignments as $a) if ((int)$a['kelas_id'] === $classId && (int)$a['mapel_id'] === $mapelId && (int)$a['tahun_ajaran_id'] === $yearId) $authorized = true;
            $title = trim((string)($_POST['judul'] ?? ''));
            $tujuan = trim((string)($_POST['tujuan'] ?? ''));
            $materi = trim((string)($_POST['materi'] ?? ''));
            $kegiatan = trim((string)($_POST['kegiatan'] ?? ''));
            $metode = trim((string)($_POST['metode'] ?? ''));
            $asesmen = trim((string)($_POST['asesmen'] ?? ''));
            $planStatus = (string)($_POST['status'] ?? 'Draft');
            if (!$authorized || !in_array($semester, ['1', '2'], true)) throw new InvalidArgumentException('Kelas, mata pelajaran, tahun ajaran, atau semester bukan bagian dari penugasan Anda.');
            if ($title === '' || mb_strlen($title) > 180 || mb_strlen($metode) > 180 || max(mb_strlen($tujuan), mb_strlen($materi), mb_strlen($kegiatan), mb_strlen($asesmen)) > 20000 || !in_array($planStatus, ['Draft', 'Diajukan'], true)) throw new InvalidArgumentException('Periksa panjang judul, metode, atau isi rencana; status harus Draft atau Diajukan.');
            if ($planId > 0) {
                $owned = $pdo->prepare('SELECT status FROM rencana_ajar WHERE id=? AND guru_id=?');
                $owned->execute([$planId, $teacherId]);
                $ownedStatus = $owned->fetchColumn();
                if ($ownedStatus === false || !in_array($ownedStatus, ['Draft', 'Diajukan'], true)) throw new InvalidArgumentException('Rencana tidak ditemukan atau sudah dikunci setelah disetujui.');
                $save = $pdo->prepare('UPDATE rencana_ajar SET kelas_id=?,mapel_id=?,tahun_ajaran_id=?,semester=?,judul=?,tujuan=?,materi=?,kegiatan=?,metode=?,asesmen=?,status=? WHERE id=? AND guru_id=?');
                $save->execute([$classId, $mapelId, $yearId, $semester, $title, $tujuan, $materi, $kegiatan, $metode, $asesmen, $planStatus, $planId, $teacherId]);
                record_activity($pdo, 'ubah rencana ajar', 'rencana_ajar', $planId, 'Guru ' . $teacherId);
            } else {
                $save = $pdo->prepare('INSERT INTO rencana_ajar (guru_id,kelas_id,mapel_id,tahun_ajaran_id,semester,judul,tujuan,materi,kegiatan,metode,asesmen,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $save->execute([$teacherId, $classId, $mapelId, $yearId, $semester, $title, $tujuan, $materi, $kegiatan, $metode, $asesmen, $planStatus]);
                $planId = (int)$pdo->lastInsertId();
                record_activity($pdo, 'buat rencana ajar', 'rencana_ajar', $planId, 'Guru ' . $teacherId);
            }
            $success = $planStatus === 'Diajukan' ? 'Rencana ajar berhasil diajukan untuk ditinjau.' : 'Draf rencana ajar berhasil disimpan.';
        } elseif ($view === 'nilai' && ($_POST['action'] ?? '') === 'save_nilai') {
            $classId = (int)($_POST['kelas_id'] ?? 0);
            $mapelId = (int)($_POST['mapel_id'] ?? 0);
            $yearId = (int)($_POST['tahun_ajaran_id'] ?? 0);
            $semester = (string)($_POST['semester'] ?? '');
            $authorized = false;
            foreach ($assignments as $a) if ((int)$a['kelas_id'] === $classId && (int)$a['mapel_id'] === $mapelId && (int)$a['tahun_ajaran_id'] === $yearId) $authorized = true;
            if (!$authorized || !in_array($semester, ['1', '2'], true)) throw new InvalidArgumentException('Kelas, mata pelajaran, tahun ajaran, atau semester bukan bagian dari jadwal aktif Anda.');
            $rosterQuery = $pdo->prepare("SELECT id FROM siswa WHERE kelas_id=? AND status='Aktif' ORDER BY nama");
            $rosterQuery->execute([$classId]);
            $studentIds = array_map('intval', $rosterQuery->fetchAll(PDO::FETCH_COLUMN));
            $tugas = $_POST['tugas'] ?? [];
            $uts = $_POST['uts'] ?? [];
            $uas = $_POST['uas'] ?? [];
            $publicationStatuses = $_POST['status_nilai'] ?? [];
            if (!$studentIds || !is_array($tugas) || !is_array($uts) || !is_array($uas) || ($gradePublicationReady && !is_array($publicationStatuses))) throw new InvalidArgumentException('Daftar nilai tidak valid.');
            $saveSql = $gradePublicationReady
                ? "INSERT INTO nilai (siswa_id,mapel_id,tahun_ajaran_id,semester,nilai_tugas,nilai_uts,nilai_uas,nilai_ujian,status_publikasi) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nilai_tugas=VALUES(nilai_tugas),nilai_uts=VALUES(nilai_uts),nilai_uas=VALUES(nilai_uas),nilai_ujian=VALUES(nilai_ujian),status_publikasi=VALUES(status_publikasi)"
                : 'INSERT INTO nilai (siswa_id,mapel_id,tahun_ajaran_id,semester,nilai_tugas,nilai_uts,nilai_uas,nilai_ujian) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nilai_tugas=VALUES(nilai_tugas),nilai_uts=VALUES(nilai_uts),nilai_uas=VALUES(nilai_uas),nilai_ujian=VALUES(nilai_ujian)';
            $pdo->beginTransaction();
            $save = $pdo->prepare($saveSql);
            foreach ($studentIds as $sid) {
                $scores = [(string)($tugas[$sid] ?? ''), (string)($uts[$sid] ?? ''), (string)($uas[$sid] ?? '')];
                foreach ($scores as $score) if ($score === '' || !is_numeric($score) || (float)$score < 0 || (float)$score > 100) throw new InvalidArgumentException('Isi nilai Tugas, UTS, dan UAS setiap siswa dengan angka 0 sampai 100.');
                $values = [$sid, $mapelId, $yearId, $semester, (float)$scores[0], (float)$scores[1], (float)$scores[2], (float)$scores[2]];
                if ($gradePublicationReady) {
                    $publication = (string)($publicationStatuses[$sid] ?? 'Draft');
                    if (!in_array($publication, ['Draft', 'Terbit'], true)) throw new InvalidArgumentException('Status publikasi nilai tidak valid.');
                    $values[] = $publication;
                }
                $save->execute($values);
            }
            $pdo->commit();
            record_activity($pdo, 'input nilai kelas', 'nilai', null, 'Guru ' . $teacherId . ' · kelas ' . $classId . ' · mapel ' . $mapelId);
            $success = 'Nilai kelas berhasil disimpan.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Data gagal disimpan. Periksa penugasan, lalu coba kembali.';
    }
}
$view = in_array($view, ['dashboard', 'jadwal', 'kelas', 'rencana', 'absensi', 'nilai'], true) ? $view : 'dashboard';
$pageTitle = match ($view) {
    'jadwal' => 'Jadwal Mengajar',
    'kelas' => 'Kelas Saya',
    'rencana' => 'Rencana Ajar',
    'absensi' => 'Input Absensi Kelas',
    'nilai' => 'Input Nilai Kelas',
    default => 'Dashboard Guru'
};
$rows = [];
$sql = match ($view) {
    'jadwal' => "SELECT j.hari,j.jam_mulai,j.jam_selesai,k.nama_kelas,m.nama_mapel,j.ruang FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id WHERE j.guru_id=? ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'),j.jam_mulai",
    'kelas' => 'SELECT DISTINCT k.id,k.nama_kelas,ta.nama AS tahun_ajaran FROM kelas k JOIN jadwal_pelajaran j ON j.kelas_id=k.id JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id WHERE j.guru_id=? UNION SELECT k.id,k.nama_kelas,ta.nama AS tahun_ajaran FROM kelas_binaan kb JOIN kelas k ON k.id=kb.kelas_id JOIN tahun_ajaran ta ON ta.id=kb.tahun_ajaran_id WHERE kb.guru_id=? ORDER BY nama_kelas',
    'rencana' => 'SELECT ra.id,ra.judul,ra.semester,ra.status,ra.kelas_id,ra.mapel_id,ra.tahun_ajaran_id,ra.tujuan,ra.materi,ra.kegiatan,ra.metode,ra.asesmen,k.nama_kelas,m.nama_mapel,ta.nama AS tahun_ajaran FROM rencana_ajar ra JOIN kelas k ON k.id=ra.kelas_id JOIN mapel m ON m.id=ra.mapel_id JOIN tahun_ajaran ta ON ta.id=ra.tahun_ajaran_id WHERE ra.guru_id=? ORDER BY ta.nama DESC,ra.semester,ra.id DESC',
    default => ''
};
if ($sql !== '') {
    $q = $pdo->prepare($sql);
    $q->execute($view === 'kelas' ? [$teacherId, $teacherId] : [$teacherId]);
    $rows = $q->fetchAll();
}
$planForm = ['id' => 0, 'judul' => '', 'tujuan' => '', 'materi' => '', 'kegiatan' => '', 'metode' => '', 'asesmen' => '', 'semester' => '1', 'kelas_id' => 0, 'mapel_id' => 0, 'tahun_ajaran_id' => 0, 'status' => 'Draft'];
if ($view === 'rencana') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
        $planForm = array_merge($planForm, array_intersect_key($_POST, array_flip(array_keys($planForm))));
        $parts = explode('|', (string)($_POST['assignment'] ?? ''));
        $planForm['kelas_id'] = (int)($parts[0] ?? 0);
        $planForm['mapel_id'] = (int)($parts[1] ?? 0);
        $planForm['tahun_ajaran_id'] = (int)($parts[2] ?? 0);
    } elseif ((int)($_GET['edit_rencana'] ?? 0) > 0) {
        $editStmt = $pdo->prepare("SELECT id,judul,tujuan,materi,kegiatan,metode,asesmen,semester,kelas_id,mapel_id,tahun_ajaran_id,status FROM rencana_ajar WHERE id=? AND guru_id=? AND status IN ('Draft','Diajukan')");
        $editStmt->execute([(int)$_GET['edit_rencana'], $teacherId]);
        $existingPlan = $editStmt->fetch();
        if ($existingPlan) $planForm = $existingPlan;
        else $error = 'Rencana tidak ditemukan atau sudah dikunci setelah disetujui.';
    }
    if (!$planForm['kelas_id'] && $assignments) {
        $planForm['kelas_id'] = (int)$assignments[0]['kelas_id'];
        $planForm['mapel_id'] = (int)$assignments[0]['mapel_id'];
        $planForm['tahun_ajaran_id'] = (int)$assignments[0]['tahun_ajaran_id'];
    }
}
$planFormAssignment = implode('|', [(int)$planForm['kelas_id'], (int)$planForm['mapel_id'], (int)$planForm['tahun_ajaran_id']]);
$classes = [];
foreach ($assignments as $a) $classes[(int)$a['kelas_id']] = $a['nama_kelas'];
$assignmentParts = explode('|', (string)($_GET['assignment'] ?? ''));
$selectedClass = (int)($_POST['kelas_id'] ?? $_GET['kelas_id'] ?? $assignmentParts[0] ?? 0);
if (!isset($classes[$selectedClass])) $selectedClass = (int)(array_key_first($classes) ?? 0);
$date = (string)($_POST['tanggal'] ?? $_GET['tanggal'] ?? date('Y-m-d'));
$selectedMapel = (int)($_POST['mapel_id'] ?? $_GET['mapel_id'] ?? $assignmentParts[1] ?? 0);
$selectedYear = (int)($_POST['tahun_ajaran_id'] ?? $_GET['tahun_ajaran_id'] ?? $assignmentParts[2] ?? 0);
$selectedSemester = (string)($_POST['semester'] ?? $_GET['semester'] ?? '1');
$todayName = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][(int)date('N') - 1] ?? '';
$todaySchedule = [];
$pendingSubmissions = [];
$teacherStudentCount = 0;
$missingAttendanceCount = 0;
$teacherAnnouncement = [];
$teacherEvents = [];
$pendingSubmissionCount = 0;
$publishedMaterialCount = 0;
$teacherTrendPeriod = (string)($_GET['grafik'] ?? '3months');
if (!in_array($teacherTrendPeriod, ['3months', '6months', '12months'], true)) $teacherTrendPeriod = '3months';
$teacherTrendLabels = [];
$teacherTrendRates = [];
$teacherAttendanceStatus = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
$teacherTrendHasData = false;
$assignedClassCount = count(array_unique(array_map(static fn($assignment) => (int)$assignment['kelas_id'], $assignments)));
if ($view === 'dashboard') {
    $todayStmt = $pdo->prepare("SELECT j.jam_mulai,j.jam_selesai,j.ruang,k.nama_kelas,m.nama_mapel FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id JOIN tahun_ajaran ta ON ta.id=j.tahun_ajaran_id WHERE j.guru_id=? AND j.hari=? AND ta.aktif=1 ORDER BY j.jam_mulai");
    $todayStmt->execute([$teacherId, $todayName]);
    $todaySchedule = $todayStmt->fetchAll();
    $studentStmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM siswa s JOIN (SELECT DISTINCT kelas_id FROM jadwal_pelajaran WHERE guru_id=? AND tahun_ajaran_id IN (SELECT id FROM tahun_ajaran WHERE aktif=1)) assigned ON assigned.kelas_id=s.kelas_id WHERE s.status='Aktif'");
    $studentStmt->execute([$teacherId]);
    $teacherStudentCount = (int)$studentStmt->fetchColumn();
    $materialStmt = $pdo->prepare("SELECT COUNT(*) FROM materi_pelajaran WHERE guru_id=? AND status='Terbit'");
    $materialStmt->execute([$teacherId]);
    $publishedMaterialCount = (int)$materialStmt->fetchColumn();
    $pendingStmt = $pdo->prepare("SELECT p.id,p.judul,p.tenggat,k.nama_kelas,m.nama_mapel,COUNT(g.id) AS jumlah_menunggu FROM tugas_pelajaran p JOIN kelas k ON k.id=p.kelas_id JOIN mapel m ON m.id=p.mapel_id JOIN pengumpulan_tugas g ON g.tugas_id=p.id AND g.nilai IS NULL WHERE p.guru_id=? AND p.status='Terbit' GROUP BY p.id,p.judul,p.tenggat,k.nama_kelas,m.nama_mapel ORDER BY p.tenggat IS NULL,p.tenggat ASC LIMIT 5");
    $pendingStmt->execute([$teacherId]);
    $pendingSubmissions = $pendingStmt->fetchAll();
    $pendingSubmissionCount = array_sum(array_map(static fn($submission) => (int)$submission['jumlah_menunggu'], $pendingSubmissions));
    $missingStmt = $pdo->prepare("SELECT COUNT(*) FROM siswa s JOIN (SELECT DISTINCT kelas_id FROM jadwal_pelajaran WHERE guru_id=? AND hari=? AND tahun_ajaran_id IN (SELECT id FROM tahun_ajaran WHERE aktif=1)) assigned ON assigned.kelas_id=s.kelas_id LEFT JOIN absensi a ON a.siswa_id=s.id AND a.tanggal=? WHERE s.status='Aktif' AND a.id IS NULL");
    $missingStmt->execute([$teacherId, $todayName, date('Y-m-d')]);
    $missingAttendanceCount = (int)$missingStmt->fetchColumn();
    $teacherAnnouncement = $pdo->query("SELECT judul,isi,mulai_tayang FROM pengumuman WHERE status='Terbit' AND (mulai_tayang IS NULL OR mulai_tayang<=CURDATE()) AND (selesai_tayang IS NULL OR selesai_tayang>=CURDATE()) ORDER BY created_at DESC LIMIT 3")->fetchAll();
    $teacherEvents = $pdo->query("SELECT judul,tanggal_mulai AS tanggal,kategori AS jenis FROM kalender_akademik WHERE tanggal_mulai>=CURDATE() AND status<>'Dibatalkan' UNION ALL SELECT nama_kegiatan,tanggal_mulai,'Kokurikuler' FROM kegiatan_kokurikuler WHERE tanggal_mulai>=CURDATE() AND status<>'Dibatalkan' ORDER BY tanggal LIMIT 3")->fetchAll();

    $teacherMonths = ['3months' => 2, '6months' => 5, '12months' => 11][$teacherTrendPeriod];
    $teacherToday = new DateTimeImmutable('today');
    $teacherTrendStart = $teacherToday->modify('first day of this month')->modify('-' . $teacherMonths . ' months');
    $teacherTrendEnd = $teacherToday->format('Y-m-d');
    $teacherDailyMap = [];
    $teacherClassIds = array_values(array_unique(array_map(static fn(array $assignment): int => (int)$assignment['kelas_id'], $assignments)));
    if ($teacherClassIds) {
        try {
            $classPlaceholders = implode(',', array_fill(0, count($teacherClassIds), '?'));
            $attendanceSql = "SELECT a.tanggal,COUNT(*) AS total,SUM(a.status='Hadir') AS hadir FROM absensi a JOIN siswa s ON s.id=a.siswa_id WHERE s.status='Aktif' AND s.kelas_id IN ($classPlaceholders) AND a.tanggal BETWEEN ? AND ? GROUP BY a.tanggal ORDER BY a.tanggal";
            $attendanceStmt = $pdo->prepare($attendanceSql);
            $attendanceStmt->execute([...$teacherClassIds, $teacherTrendStart->format('Y-m-d'), $teacherTrendEnd]);
            foreach ($attendanceStmt->fetchAll() as $row) $teacherDailyMap[$row['tanggal']] = ['total' => (int)$row['total'], 'hadir' => (int)$row['hadir']];

            $statusSql = "SELECT a.status,COUNT(*) AS jumlah FROM absensi a JOIN siswa s ON s.id=a.siswa_id WHERE s.status='Aktif' AND s.kelas_id IN ($classPlaceholders) AND a.tanggal BETWEEN ? AND ? GROUP BY a.status";
            $statusStmt = $pdo->prepare($statusSql);
            $statusStmt->execute([...$teacherClassIds, $teacherTrendStart->format('Y-m-d'), $teacherTrendEnd]);
            foreach ($statusStmt->fetchAll() as $row) if (isset($teacherAttendanceStatus[$row['status']])) $teacherAttendanceStatus[$row['status']] = (int)$row['jumlah'];
            $teacherTrendHasData = array_sum($teacherAttendanceStatus) > 0;
        } catch (PDOException $ignored) {
            $teacherDailyMap = [];
            $teacherAttendanceStatus = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
            $teacherTrendHasData = false;
        }
    }
    for ($trendDay = $teacherTrendStart; $trendDay <= $teacherToday; $trendDay = $trendDay->modify('+1 day')) {
        $trendKey = $trendDay->format('Y-m-d');
        $teacherTrendLabels[] = $trendDay->format('d M');
        $entry = $teacherDailyMap[$trendKey] ?? ['total' => 0, 'hadir' => 0];
        $teacherTrendRates[] = $entry['total'] > 0 ? round($entry['hadir'] * 100 / $entry['total'], 1) : null;
    }
}
$validAssignment = null;
foreach ($assignments as $a) if ((int)$a['kelas_id'] === $selectedClass && (int)$a['mapel_id'] === $selectedMapel && (int)$a['tahun_ajaran_id'] === $selectedYear) $validAssignment = $a;
if (!$validAssignment && $assignments) {
    $validAssignment = $assignments[0];
    $selectedClass = (int)$validAssignment['kelas_id'];
    $selectedMapel = (int)$validAssignment['mapel_id'];
    $selectedYear = (int)$validAssignment['tahun_ajaran_id'];
}
$roster = [];
if (in_array($view, ['absensi', 'nilai'], true) && $selectedClass > 0) {
    if ($view === 'absensi') {
        $q = $pdo->prepare("SELECT s.id,s.nis,s.nama,a.status FROM siswa s LEFT JOIN absensi a ON a.siswa_id=s.id AND a.tanggal=? WHERE s.kelas_id=? AND s.status='Aktif' ORDER BY s.nama");
        $q->execute([$date, $selectedClass]);
        $roster = $q->fetchAll();
    } elseif ($validAssignment) {
        $publicationSelect = $gradePublicationReady ? 'n.status_publikasi' : "'Terbit'";
        $q = $pdo->prepare("SELECT s.id,s.nis,s.nama,n.nilai_tugas,n.nilai_uts,n.nilai_uas,$publicationSelect AS status_publikasi FROM siswa s LEFT JOIN nilai n ON n.siswa_id=s.id AND n.mapel_id=? AND n.tahun_ajaran_id=? AND n.semester=? WHERE s.kelas_id=? AND s.status='Aktif' ORDER BY s.nama");
        $q->execute([$selectedMapel, $selectedYear, $selectedSemester, $selectedClass]);
        $roster = $q->fetchAll();
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') foreach ($roster as &$studentRow) {
        $sid = (int)$studentRow['id'];
        if ($view === 'absensi') $studentRow['status'] = $_POST['status_siswa'][$sid] ?? $studentRow['status'];
        else {
            $studentRow['nilai_tugas'] = $_POST['tugas'][$sid] ?? $studentRow['nilai_tugas'];
            $studentRow['nilai_uts'] = $_POST['uts'][$sid] ?? $studentRow['nilai_uts'];
            $studentRow['nilai_uas'] = $_POST['uas'][$sid] ?? $studentRow['nilai_uas'];
            $studentRow['status_publikasi'] = $_POST['status_nilai'][$sid] ?? $studentRow['status_publikasi'];
        }
    }
    unset($studentRow);
}
require __DIR__ . '/includes/header.php'; ?>
<div class="dashboard-heading">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p>Informasi dan catatan pembelajaran untuk <?= e($teacher['nama']) ?>, dibatasi pada jadwal penugasan aktif.</p>
    </div>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($view !== 'dashboard'): ?><div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card p-3"><small class="text-secondary">Guru</small><strong><?= e($teacher['nama']) ?></strong></div>
        </div>
        <div class="col-md-6">
            <div class="card p-3"><small class="text-secondary">NIP</small><strong><?= e($teacher['nip'] ?: 'Belum diisi') ?></strong></div>
        </div>
    </div><?php endif; ?>
<?php if ($view === 'dashboard'): ?>
    <section class="teacher-welcome mb-4" aria-label="Sapaan dashboard guru">
        <div class="teacher-welcome-copy"><span class="teacher-welcome-kicker"><i class="bi bi-stars"></i> Ruang kerja guru</span>
            <h2>Selamat <?= (int)date('G') < 11 ? 'pagi' : ((int)date('G') < 15 ? 'siang' : 'sore') ?>, <?= e($teacher['nama']) ?> <span aria-hidden="true">👋</span></h2>
            <p>Semoga kegiatan belajar hari ini berjalan lancar. Berikut ringkasan kelas dan tugas Anda.</p>
            <div class="teacher-welcome-meta"><span><i class="bi bi-calendar3"></i><?= e(date('d/m/Y')) ?></span><span><i class="bi bi-person-badge"></i><?= e($teacher['nip'] ?: 'NIP belum diisi') ?></span></div>
        </div>
        <div class="teacher-welcome-action"><span class="teacher-welcome-icon"><i class="bi bi-journal-bookmark-fill"></i></span><strong><?= number_format($assignedClassCount, 0, ',', '.') ?> kelas aktif</strong><span><?= number_format($teacherStudentCount, 0, ',', '.') ?> siswa dalam penugasan</span><a href="teacher.php?view=kelas">Lihat kelas <i class="bi bi-arrow-right"></i></a></div>
    </section>
    <div class="row g-3 mb-4 teacher-stat-grid">
        <?php foreach ([['Jadwal Hari Ini', count($todaySchedule), 'bi-calendar-check', 'blue', 'Sesi mengajar · ' . $todayName], ['Kelas Diampu', $assignedClassCount, 'bi-people', 'green', 'Kelas pada tahun ajaran aktif'], ['Siswa Diajar', $teacherStudentCount, 'bi-person-lines-fill', 'purple', 'Siswa aktif pada kelas penugasan'], ['Perlu Dinilai', $pendingSubmissionCount, 'bi-inbox', 'orange', 'Pengumpulan tugas menunggu pemeriksaan'], ['Materi Terbit', $publishedMaterialCount, 'bi-file-earmark-check', 'green', 'Materi yang sudah dipublikasikan']] as [$metricTitle, $metricValue, $metricIcon, $metricColor, $metricNote]): ?>
            <div class="col-6 col-xl">
                <div class="card stat-card">
                    <div class="stat-card-top"><span><?= e($metricTitle) ?></span><span class="stat-icon <?= e($metricColor) ?>"><i class="bi <?= e($metricIcon) ?>"></i></span></div>
                    <div class="stat-number"><?= number_format((int)$metricValue, 0, ',', '.') ?></div>
                    <div class="stat-note"><?= e($metricNote) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3 mb-4 teacher-analytics-grid">
        <div class="col-12 col-xl-8">
            <section class="card chart-card dashboard-teacher-chart h-100" aria-labelledby="teacherAttendanceTrendTitle">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="chart-title" id="teacherAttendanceTrendTitle">Tren Kehadiran Kelas Saya</h2>
                        <p class="chart-subtitle mb-0">Persentase hadir harian · hanya kelas pada penugasan aktif Anda.</p>
                    </div>
                    <div class="chart-range-tabs" role="group" aria-label="Rentang grafik kehadiran guru">
                        <?php foreach (['12months' => '12 Bulan', '6months' => '6 Bulan', '3months' => '3 Bulan'] as $rangeKey => $rangeLabel): ?>
                            <a class="chart-range-tab<?= $teacherTrendPeriod === $rangeKey ? ' is-active' : '' ?>" href="teacher.php?view=dashboard&amp;grafik=<?= e($rangeKey) ?>" <?= $teacherTrendPeriod === $rangeKey ? ' aria-current="true"' : '' ?>><?= e($rangeLabel) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="chart-canvas teacher-trend-canvas"><?php if (!$teacherTrendHasData): ?><div class="empty-chart empty-chart-plot">
                            <div class="empty-chart-message"><i class="bi bi-activity" aria-hidden="true"></i><strong>Grafik menunggu data absensi</strong><span>Grafik hanya memuat kehadiran siswa pada kelas yang ditugaskan kepada Anda.</span></div>
                        </div><?php else: ?><canvas id="teacherAttendanceTrend" role="img" aria-label="Grafik kehadiran siswa pada kelas yang ditugaskan kepada guru"></canvas><?php endif; ?></div>
            </section>
        </div>
        <div class="col-12 col-xl-4">
            <section class="card chart-card dashboard-teacher-chart h-100" aria-labelledby="teacherAttendanceSummaryTitle">
                <h2 class="chart-title" id="teacherAttendanceSummaryTitle">Ringkasan Kehadiran</h2>
                <p class="chart-subtitle">Status absensi · <?= e($teacherTrendStart->format('d M Y')) ?> – <?= e($teacherTrendEnd) ?></p>
                <div class="donut-layout teacher-donut-layout"><?php if (!$teacherTrendHasData): ?><div class="empty-chart empty-chart-plot w-100">
                            <div class="empty-chart-message"><i class="bi bi-pie-chart" aria-hidden="true"></i><strong>Belum ada ringkasan</strong><span>Data absensi pada penugasan Anda akan mengisi diagram ini.</span></div>
                        </div><?php else: ?><div class="chart-canvas"><canvas id="teacherAttendanceDonut" role="img" aria-label="Ringkasan status kehadiran siswa pada kelas tugas"></canvas></div>
                        <ul class="chart-legend" id="teacherAttendanceLegend"></ul><?php endif; ?>
                </div>
            </section>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            if (typeof Chart === 'undefined') return;
            Chart.defaults.font.family = "'DM Sans', sans-serif";
            Chart.defaults.color = '#dce8f8';
            const labels = <?= json_encode($teacherTrendLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const rates = <?= json_encode($teacherTrendRates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const statuses = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
            const statusCounts = <?= json_encode(array_values($teacherAttendanceStatus), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const colors = ['#16c79a', '#ffad36', '#f05b68', '#91a3bd'];
            const line = document.getElementById('teacherAttendanceTrend');
            if (line) new Chart(line, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Persentase hadir',
                        data: rates,
                        borderColor: '#36a5ff',
                        backgroundColor: 'rgba(54,165,255,.16)',
                        pointBackgroundColor: '#b9e1ff',
                        pointBorderColor: '#36a5ff',
                        pointRadius: 2.5,
                        borderWidth: 3,
                        tension: .35,
                        fill: true,
                        spanGaps: false
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                color: '#b9c9e0',
                                callback: value => value + '%'
                            },
                            grid: {
                                color: 'rgba(151,177,214,.16)'
                            },
                            border: {
                                display: false
                            }
                        },
                        x: {
                            ticks: {
                                color: '#b9c9e0',
                                autoSkip: true,
                                maxTicksLimit: 12,
                                maxRotation: 0
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
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: context => context.raw === null ? 'Tidak ada catatan' : `${context.raw}% hadir`
                            }
                        }
                    }
                }
            });
            const donut = document.getElementById('teacherAttendanceDonut');
            if (donut) new Chart(donut, {
                type: 'doughnut',
                data: {
                    labels: statuses,
                    datasets: [{
                        data: statusCounts,
                        backgroundColor: colors,
                        borderColor: '#102443',
                        borderWidth: 3,
                        hoverOffset: 5
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: context => `${context.label}: ${context.raw} catatan`
                            }
                        }
                    }
                }
            });
            const legend = document.getElementById('teacherAttendanceLegend');
            if (legend) {
                const total = statusCounts.reduce((sum, value) => sum + value, 0);
                legend.innerHTML = statuses.map((status, index) => `<li><span class="legend-name"><i class="legend-dot" style="background:${colors[index]}"></i>${status}</span><span class="legend-value">${statusCounts[index]} <span class="text-secondary fw-normal">${total?Math.round(statusCounts[index]*100/total):0}%</span></span></li>`).join('');
            }
        })();
    </script>
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7">
            <section class="card chart-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h2 class="chart-title">Jadwal Mengajar Hari Ini</h2>
                        <p class="chart-subtitle mb-0"><?= e(date('d M Y')) ?> · <?= e($todayName) ?></p>
                    </div><a class="btn btn-sm btn-outline-primary" href="teacher.php?view=jadwal">Semua jadwal</a>
                </div>
                <?php if (!$todaySchedule): ?><div class="empty-state">Tidak ada jadwal mengajar untuk hari ini.</div><?php else: ?><div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Jam</th>
                                    <th>Kelas</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Ruang</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($todaySchedule as $session): ?><tr>
                                        <td class="text-nowrap"><?= e(substr($session['jam_mulai'], 0, 5) . '–' . substr($session['jam_selesai'], 0, 5)) ?></td>
                                        <td><?= e($session['nama_kelas']) ?></td>
                                        <td><?= e($session['nama_mapel']) ?></td>
                                        <td><?= e($session['ruang'] ?: '—') ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div><?php endif; ?>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card chart-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h2 class="chart-title">Absensi Kelas Hari Ini</h2>
                        <p class="chart-subtitle mb-0">Siswa di kelas dengan jadwal mengajar hari ini</p>
                    </div><a class="btn btn-sm btn-outline-primary" href="teacher.php?view=absensi">Isi absensi</a>
                </div>
                <div class="stat-number <?= $missingAttendanceCount ? 'text-warning' : 'text-success' ?>"><?= number_format($missingAttendanceCount, 0, ',', '.') ?></div>
                <p class="text-secondary small mb-0"><?= $missingAttendanceCount ? 'Siswa belum memiliki catatan kehadiran hari ini.' : 'Tidak ada absensi yang tertinggal pada kelas terjadwal, atau tidak ada jadwal hari ini.' ?></p>
            </section>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-7">
            <section class="card chart-card h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h2 class="chart-title">Pengumpulan Tugas Menunggu Nilai</h2>
                        <p class="chart-subtitle mb-0">Jawaban yang belum diberi nilai</p>
                    </div><a class="btn btn-sm btn-outline-primary" href="learning.php?view=pengumpulan">Buka pengumpulan</a>
                </div><?php if (!$pendingSubmissions): ?><div class="empty-state">Belum ada pengumpulan yang menunggu penilaian.</div><?php else: ?><div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tugas</th>
                                    <th>Kelas · Mapel</th>
                                    <th>Menunggu</th>
                                    <th>Tenggat</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($pendingSubmissions as $submission): ?><tr>
                                        <th scope="row"><?= e($submission['judul']) ?></th>
                                        <td><?= e($submission['nama_kelas'] . ' · ' . $submission['nama_mapel']) ?></td>
                                        <td><span class="badge text-bg-warning"><?= (int)$submission['jumlah_menunggu'] ?></span></td>
                                        <td><?= $submission['tenggat'] ? e(date('d M H:i', strtotime($submission['tenggat']))) : '—' ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div><?php endif; ?>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card chart-card h-100">
                <h2 class="chart-title mb-3">Akses Cepat</h2>
                <div class="d-grid gap-2"><a class="btn btn-outline-primary text-start" href="teacher.php?view=kelas"><i class="bi bi-people me-2"></i>Kelas Saya · <?= $assignedClassCount ?> kelas</a><a class="btn btn-outline-primary text-start" href="teacher.php?view=rencana"><i class="bi bi-journal-bookmark me-2"></i>Rencana Ajar</a><a class="btn btn-outline-primary text-start" href="teacher.php?view=nilai"><i class="bi bi-journal-check me-2"></i>Input Nilai</a><a class="btn btn-outline-primary text-start" href="learning.php?view=tugas"><i class="bi bi-clipboard2-check me-2"></i>Kelola Materi & Tugas</a></div>
            </section>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <section class="card chart-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="chart-title">Pengumuman Sekolah</h2><a class="small" href="public/pengumuman.php">Lihat semua</a>
                </div>
                <?php if (!$teacherAnnouncement): ?>
                    <div class="empty-state">Belum ada pengumuman aktif.</div>
                <?php else: ?>
                    <?php foreach ($teacherAnnouncement as $item): ?>
                        <article class="announcement-item">
                            <strong><?= e($item['judul']) ?></strong>
                            <small><?= e($item['mulai_tayang'] ?: 'Tayang segera') ?></small>
                            <p><?= e($item['isi']) ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-12 col-lg-6">
            <section class="card chart-card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="chart-title">Agenda Mendatang</h2><a class="small" href="public/agenda.php">Kalender agenda</a>
                </div>
                <?php if (!$teacherEvents): ?>
                    <div class="empty-state">Belum ada agenda mendatang.</div>
                <?php else: ?>
                    <?php foreach ($teacherEvents as $item): ?>
                        <article class="agenda-item">
                            <strong><?= e($item['judul']) ?></strong>
                            <small>
                                <?= e($item['jenis']) ?> ·
                                <?= e(date('d M Y', strtotime($item['tanggal']))) ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php elseif ($view === 'rencana'): ?>
    <div class="card p-3 p-md-4 mb-4">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= (int)$planForm['id'] ? 'Ubah Rencana Ajar' : 'Buat Rencana Ajar' ?></h2>
                <p class="text-secondary small mb-0">Pilih hanya kelas dan mata pelajaran yang tercantum pada penugasan aktif Anda.</p>
            </div><?php if ((int)$planForm['id']): ?><a class="btn btn-sm btn-outline-secondary" href="teacher.php?view=rencana">Batal edit</a><?php endif; ?>
        </div>
        <?php if (!$assignments): ?><div class="alert alert-info mb-0">Belum ada penugasan kelas dan mata pelajaran pada tahun ajaran aktif.</div><?php else: ?><form method="post" class="row g-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="view" value="rencana"><input type="hidden" name="action" value="save_rencana"><input type="hidden" name="id" value="<?= (int)$planForm['id'] ?>">
                <div class="col-lg-6"><label class="form-label" for="planAssignment">Kelas dan Mata Pelajaran</label><select class="form-select" id="planAssignment" name="assignment" required><?php foreach ($assignments as $assignment): $assignmentValue = (int)$assignment['kelas_id'] . '|' . (int)$assignment['mapel_id'] . '|' . (int)$assignment['tahun_ajaran_id']; ?><option value="<?= e($assignmentValue) ?>" <?= $assignmentValue === $planFormAssignment ? 'selected' : '' ?>><?= e($assignment['nama_kelas'] . ' · ' . $assignment['nama_mapel'] . ' · ' . $assignment['tahun_ajaran']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label" for="planSemester">Semester</label><select class="form-select" id="planSemester" name="semester">
                        <option value="1" <?= (string)$planForm['semester'] === '1' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="2" <?= (string)$planForm['semester'] === '2' ? 'selected' : '' ?>>Genap</option>
                    </select></div>
                <div class="col-md-3"><label class="form-label" for="planStatus">Tindakan</label><select class="form-select" id="planStatus" name="status">
                        <option value="Draft" <?= $planForm['status'] === 'Draft' ? 'selected' : '' ?>>Simpan draf</option>
                        <option value="Diajukan" <?= $planForm['status'] === 'Diajukan' ? 'selected' : '' ?>>Ajukan untuk ditinjau</option>
                    </select></div>
                <div class="col-12"><label class="form-label" for="planTitle">Judul Rencana</label><input class="form-control" id="planTitle" name="judul" maxlength="180" value="<?= e($planForm['judul']) ?>" required></div>
                <div class="col-12"><label class="form-label" for="planGoals">Tujuan Pembelajaran</label><textarea class="form-control" id="planGoals" name="tujuan" rows="2"><?= e($planForm['tujuan'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label" for="planMaterials">Materi Pokok</label><textarea class="form-control" id="planMaterials" name="materi" rows="2"><?= e($planForm['materi'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label" for="planActivities">Kegiatan Pembelajaran</label><textarea class="form-control" id="planActivities" name="kegiatan" rows="3"><?= e($planForm['kegiatan'] ?? '') ?></textarea></div>
                <div class="col-md-6"><label class="form-label" for="planMethod">Metode Pembelajaran</label><input class="form-control" id="planMethod" name="metode" maxlength="180" value="<?= e($planForm['metode'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="planAssessment">Asesmen</label><textarea class="form-control" id="planAssessment" name="asesmen" rows="2"><?= e($planForm['asesmen'] ?? '') ?></textarea></div>
                <div class="col-12"><button class="btn btn-primary"><i class="bi bi-save me-1"></i><?= (int)$planForm['id'] ? 'Simpan Perubahan' : 'Simpan Rencana' ?></button></div>
            </form><?php endif; ?>
    </div>
    <section class="card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Rencana Ajar Saya</h2><span class="badge text-bg-light"><?= count($rows) ?> rencana</span>
        </div><?php if (!$rows): ?><div class="empty-state">Belum ada rencana ajar. Rencana yang diajukan akan masuk ke proses peninjauan sekolah.</div><?php else: ?><div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Kelas · Mapel</th>
                            <th>Tahun · Semester</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($rows as $plan): ?><tr>
                                <th scope="row"><?= e($plan['judul']) ?></th>
                                <td><?= e($plan['nama_kelas'] . ' · ' . $plan['nama_mapel']) ?></td>
                                <td><?= e($plan['tahun_ajaran'] . ' · Semester ' . $plan['semester']) ?></td>
                                <td><span class="badge <?= $plan['status'] === 'Disetujui' ? 'text-bg-success' : ($plan['status'] === 'Diajukan' ? 'text-bg-info' : 'text-bg-secondary') ?>"><?= e($plan['status']) ?></span></td>
                                <td><?php if (in_array($plan['status'], ['Draft', 'Diajukan'], true)): ?><a class="btn btn-sm btn-outline-primary" href="teacher.php?view=rencana&edit_rencana=<?= (int)$plan['id'] ?>">Ubah</a><?php else: ?><span class="text-secondary small">Terkunci</span><?php endif; ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div><?php endif; ?>
    </section>
<?php elseif ($view === 'absensi'): ?><div class="card p-3 p-md-4">
        <form method="get" class="row g-3 mb-3"><input type="hidden" name="view" value="absensi">
            <div class="col-md-6"><label class="form-label">Kelas</label><select class="form-select" name="kelas_id"><?php foreach ($classes as $id => $name): ?><option value="<?= (int)$id ?>" <?= $selectedClass === (int)$id ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Tanggal</label><input class="form-control" type="date" name="tanggal" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary">Tampilkan</button></div>
        </form><?php if (!$classes): ?><div class="alert alert-info">Belum ada kelas dan jadwal aktif yang ditugaskan kepada akun Anda.</div><?php elseif ($roster): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_absensi"><input type="hidden" name="view" value="absensi"><input type="hidden" name="kelas_id" value="<?= $selectedClass ?>"><input type="hidden" name="tanggal" value="<?= e($date) ?>">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($roster as $s): ?><tr>
                                    <td><?= e($s['nis']) ?></td>
                                    <td><?= e($s['nama']) ?></td>
                                    <td><select class="form-select" name="status_siswa[<?= (int)$s['id'] ?>]" required><?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpa'] as $status): ?><option value="<?= $status ?>" <?= ($s['status'] ?? 'Hadir') === $status ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></td>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div><button class="btn btn-primary">Simpan Absensi</button>
            </form><?php else: ?><div class="empty-state">Tidak ada siswa aktif di kelas ini.</div><?php endif; ?>
    </div>
<?php elseif ($view === 'nilai'): ?><div class="card p-3 p-md-4">
        <form method="get" class="row g-3 mb-3"><input type="hidden" name="view" value="nilai">
            <div class="col-md-6"><label class="form-label">Kelas dan Mata Pelajaran</label><select class="form-select" name="assignment" required>
                    <option value="">Pilih penugasan</option><?php foreach ($assignments as $a): ?><option value="<?= (int)$a['kelas_id'] . '|' . (int)$a['mapel_id'] . '|' . (int)$a['tahun_ajaran_id'] ?>" <?= $validAssignment && (int)$a['kelas_id'] === $selectedClass && (int)$a['mapel_id'] === $selectedMapel && (int)$a['tahun_ajaran_id'] === $selectedYear ? 'selected' : '' ?>><?= e($a['nama_kelas'] . ' · ' . $a['nama_mapel'] . ' · ' . $a['tahun_ajaran']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label">Semester</label><select class="form-select" name="semester">
                    <option value="1" <?= $selectedSemester === '1' ? 'selected' : '' ?>>Ganjil</option>
                    <option value="2" <?= $selectedSemester === '2' ? 'selected' : '' ?>>Genap</option>
                </select></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary">Tampilkan</button></div>
        </form><?php if (!$validAssignment): ?><div class="alert alert-info">Belum ada penugasan kelas dan mata pelajaran pada tahun ajaran aktif.</div><?php elseif ($roster): ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_nilai"><input type="hidden" name="view" value="nilai"><input type="hidden" name="kelas_id" value="<?= $selectedClass ?>"><input type="hidden" name="mapel_id" value="<?= $selectedMapel ?>"><input type="hidden" name="tahun_ajaran_id" value="<?= $selectedYear ?>"><input type="hidden" name="semester" value="<?= e($selectedSemester) ?>">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Tugas</th>
                                <th>UTS</th>
                                <th>UAS</th><?php if ($gradePublicationReady): ?><th>Status untuk siswa</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($roster as $s): ?><tr>
                                    <td><?= e($s['nis']) ?></td>
                                    <td><?= e($s['nama']) ?></td><?php foreach (['nilai_tugas' => 'tugas', 'nilai_uts' => 'uts', 'nilai_uas' => 'uas'] as $col => $name): ?><td><input class="form-control" type="number" name="<?= $name ?>[<?= (int)$s['id'] ?>]" min="0" max="100" step="0.01" value="<?= e($s[$col] ?? '') ?>" required></td><?php endforeach; ?><?php if ($gradePublicationReady): ?><td><select class="form-select" name="status_nilai[<?= (int)$s['id'] ?>]">
                                                <option value="Draft" <?= ($s['status_publikasi'] ?? 'Draft') === 'Draft' ? 'selected' : '' ?>>Draft · belum terlihat siswa</option>
                                                <option value="Terbit" <?= ($s['status_publikasi'] ?? 'Draft') === 'Terbit' ? 'selected' : '' ?>>Terbit · terlihat siswa</option>
                                            </select></td><?php endif; ?>
                                </tr><?php endforeach; ?></tbody>
                    </table>
                </div><button class="btn btn-primary">Simpan Nilai</button>
                <p class="form-text mt-2 mb-0">Nilai hanya dapat disimpan untuk siswa aktif pada kelas dan mata pelajaran yang ditugaskan kepada Anda. <?= $gradePublicationReady ? 'Pilih Draft untuk menyimpan tanpa menampilkan nilai kepada siswa, atau Terbit untuk membuka akses nilai.' : 'Status publikasi aktif setelah Super Admin menjalankan migrasi database.' ?></p>
            </form><?php else: ?><div class="empty-state">Belum ada siswa aktif pada kelas ini.</div><?php endif; ?>
    </div>
<?php else: ?><div class="card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><?php foreach (array_keys($rows[0] ?? []) as $col): ?><th><?= e(field_label((string)$col)) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody><?php if (!$rows): ?><tr>
                            <td class="text-center text-secondary">Belum ada data yang terkait dengan akun ini.</td>
                        </tr><?php else: foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?= e($value) ?></td><?php endforeach; ?></tr><?php endforeach;
                                                                                                                                                    endif; ?></tbody>
            </table>
        </div>
    </div><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>