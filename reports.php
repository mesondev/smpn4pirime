<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
if (in_array(current_role(), ['guru', 'siswa'], true)) {
    http_response_code(403);
    exit('Akses laporan sekolah tidak tersedia untuk peran ini. Buka dashboard pribadi Anda.');
}
$pageTitle = 'Laporan';
$type = (string)($_GET['type'] ?? 'siswa');
$allowed = ['siswa', 'guru', 'kelas', 'nilai', 'absensi', 'absensi_guru', 'jadwal', 'materi', 'tugas', 'pengumpulan'];
if (!in_array($type, $allowed, true)) {
    $type = 'siswa';
}
$schoolYears = $pdo->query('SELECT id, nama FROM tahun_ajaran ORDER BY nama DESC')->fetchAll(PDO::FETCH_KEY_PAIR);
$filterError = '';
$yearId = '';
$semester = (string)($_GET['semester'] ?? '');
$statusFilter = trim((string)($_GET['status'] ?? ''));
$dateFrom = trim((string)($_GET['tanggal_mulai'] ?? ''));
$dateTo = trim((string)($_GET['tanggal_selesai'] ?? ''));

$requestedYear = trim((string)($_GET['tahun_ajaran_id'] ?? ''));
if ($requestedYear !== '') {
    if (ctype_digit($requestedYear) && isset($schoolYears[$requestedYear])) {
        $yearId = $requestedYear;
    } else {
        $filterError = 'Tahun ajaran yang dipilih tidak ditemukan.';
    }
}
$classId = trim((string)($_GET['kelas_id'] ?? ''));
$classSql = 'SELECT k.id,CONCAT(k.nama_kelas,\' · \',ta.nama) AS label FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id';
$classParams = [];
if ($yearId !== '') {
    $classSql .= ' WHERE k.tahun_ajaran_id=?';
    $classParams[] = (int)$yearId;
}
$classSql .= ' ORDER BY ta.nama DESC,k.tingkat,k.nama_kelas';
$classStmt = $pdo->prepare($classSql);
$classStmt->execute($classParams);
$classes = $classStmt->fetchAll(PDO::FETCH_KEY_PAIR);
if ($classId !== '' && (!ctype_digit($classId) || !isset($classes[$classId]))) {
    $filterError = 'Kelas yang dipilih tidak sesuai dengan tahun ajaran.';
    $classId = '';
}
if ($semester !== '' && !in_array($semester, ['1', '2'], true)) {
    $filterError = 'Semester tidak valid.';
    $semester = '';
}
$validDate = static function (string $date): bool {
    if ($date === '') return true;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
if (!$validDate($dateFrom) || !$validDate($dateTo)) {
    $filterError = 'Format tanggal tidak valid.';
    $dateFrom = $validDate($dateFrom) ? $dateFrom : '';
    $dateTo = $validDate($dateTo) ? $dateTo : '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $filterError = 'Tanggal mulai tidak boleh melewati tanggal selesai.';
}
$queries = [
    'siswa' => ['title' => 'Laporan Data Siswa', 'sql' => "SELECT s.nis,s.nisn,s.nama,s.jenis_kelamin,k.nama_kelas,s.tahun_masuk,s.status FROM siswa s LEFT JOIN kelas k ON k.id=s.kelas_id", 'headers' => ['NIS', 'NISN', 'Nama', 'Jenis Kelamin', 'Kelas', 'Tahun Masuk', 'Status']],
    'guru' => ['title' => 'Laporan Data Guru', 'sql' => "SELECT nip,nama,jenis_kelamin,telepon,email,status FROM guru", 'headers' => ['NIP', 'Nama', 'Jenis Kelamin', 'Telepon', 'Email', 'Status']],
    'kelas' => ['title' => 'Laporan Distribusi Siswa per Kelas', 'sql' => "SELECT ta.nama AS tahun_ajaran,k.nama_kelas,CASE k.tingkat WHEN '7' THEN 'VII' WHEN '8' THEN 'VIII' ELSE 'IX' END AS tingkat,COALESCE(g.nama,'Belum ditetapkan') AS wali_kelas,COUNT(CASE WHEN s.status='Aktif' THEN 1 END) AS siswa_aktif,COUNT(CASE WHEN s.status='Aktif' AND s.jenis_kelamin='L' THEN 1 END) AS laki_laki,COUNT(CASE WHEN s.status='Aktif' AND s.jenis_kelamin='P' THEN 1 END) AS perempuan FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id LEFT JOIN guru g ON g.id=k.wali_guru_id LEFT JOIN siswa s ON s.kelas_id=k.id", 'headers' => ['Tahun Ajaran', 'Kelas', 'Tingkat', 'Wali Kelas', 'Siswa Aktif', 'Laki-laki', 'Perempuan']],
    'nilai' => ['title' => 'Laporan Nilai Siswa', 'sql' => "SELECT s.nama AS siswa,m.nama_mapel,ta.nama AS tahun_ajaran,IF(n.semester='1','Ganjil','Genap') AS semester,n.nilai_tugas,n.nilai_uts,n.nilai_uas,n.nilai_ujian AS nilai_legacy,m.kkm FROM nilai n JOIN siswa s ON s.id=n.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id JOIN mapel m ON m.id=n.mapel_id JOIN tahun_ajaran ta ON ta.id=n.tahun_ajaran_id", 'headers' => ['Siswa', 'Mata Pelajaran', 'Tahun Ajaran', 'Semester', 'Tugas', 'UTS', 'UAS', 'Nilai Akhir', 'KKM', 'Hasil']],
    'absensi' => ['title' => 'Laporan Absensi Siswa', 'sql' => "SELECT a.tanggal,s.nis,s.nama,k.nama_kelas,a.status,a.keterangan FROM absensi a JOIN siswa s ON s.id=a.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id", 'headers' => ['Tanggal', 'NIS', 'Nama', 'Kelas', 'Status', 'Keterangan']],
    'absensi_guru' => ['title' => 'Laporan Kehadiran Guru', 'sql' => "SELECT ag.tanggal,g.nip,g.nama,ag.status,ag.keterangan FROM absensi_guru ag JOIN guru g ON g.id=ag.guru_id", 'headers' => ['Tanggal', 'NIP', 'Nama Guru', 'Status', 'Keterangan']],
    'jadwal' => ['title' => 'Jadwal Kelas', 'sql' => "SELECT ta.nama AS tahun_ajaran,k.nama_kelas,j.hari,j.jam_mulai,j.jam_selesai,m.nama_mapel,g.nama AS guru,j.ruang FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id LEFT JOIN guru g ON g.id=j.guru_id JOIN tahun_ajaran ta ON ta.id=j.tahun_ajaran_id", 'headers' => ['Tahun Ajaran', 'Kelas', 'Hari', 'Mulai', 'Selesai', 'Mata Pelajaran', 'Guru', 'Ruang']],
    'materi' => ['title' => 'Laporan Materi Pembelajaran', 'sql' => "SELECT ta.nama AS tahun_ajaran,k.nama_kelas,m.nama_mapel,g.nama AS guru,mp.judul,mp.status,mp.created_at FROM materi_pelajaran mp JOIN kelas k ON k.id=mp.kelas_id JOIN mapel m ON m.id=mp.mapel_id JOIN guru g ON g.id=mp.guru_id JOIN tahun_ajaran ta ON ta.id=mp.tahun_ajaran_id", 'headers' => ['Tahun Ajaran', 'Kelas', 'Mata Pelajaran', 'Guru', 'Judul Materi', 'Status', 'Dibuat']],
    'tugas' => ['title' => 'Laporan Tugas Pembelajaran', 'sql' => "SELECT ta.nama AS tahun_ajaran,k.nama_kelas,m.nama_mapel,g.nama AS guru,t.judul,t.status,t.tenggat,COUNT(p.id) AS jumlah_pengumpulan,SUM(p.nilai IS NOT NULL) AS sudah_dinilai FROM tugas_pelajaran t JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id JOIN guru g ON g.id=t.guru_id JOIN tahun_ajaran ta ON ta.id=t.tahun_ajaran_id LEFT JOIN pengumpulan_tugas p ON p.tugas_id=t.id", 'headers' => ['Tahun Ajaran', 'Kelas', 'Mata Pelajaran', 'Guru', 'Tugas', 'Status', 'Tenggat', 'Pengumpulan', 'Dinilai']],
    'pengumpulan' => ['title' => 'Laporan Pengumpulan Tugas', 'sql' => "SELECT ta.nama AS tahun_ajaran,k.nama_kelas,m.nama_mapel,t.judul AS tugas,s.nis,s.nama AS siswa,p.dikumpulkan_at,IF(t.tenggat IS NULL,'Tidak ditentukan',IF(p.dikumpulkan_at<=t.tenggat,'Tepat waktu','Terlambat')) AS ketepatan,COALESCE(CAST(p.nilai AS CHAR),'Belum dinilai') AS nilai FROM pengumpulan_tugas p JOIN tugas_pelajaran t ON t.id=p.tugas_id JOIN siswa s ON s.id=p.siswa_id JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id JOIN tahun_ajaran ta ON ta.id=t.tahun_ajaran_id", 'headers' => ['Tahun Ajaran', 'Kelas', 'Mata Pelajaran', 'Tugas', 'NIS', 'Siswa', 'Waktu Kumpul', 'Ketepatan', 'Nilai']]
];
$where = [];
$params = [];
if ($filterError === '') {
    if ($yearId !== '' && in_array($type, ['siswa', 'kelas', 'nilai', 'absensi', 'jadwal', 'materi', 'tugas', 'pengumpulan'], true)) {
        $where[] = match ($type) {
            'siswa' => 'k.tahun_ajaran_id = ?',
            'kelas' => 'k.tahun_ajaran_id = ?',
            'nilai' => 'n.tahun_ajaran_id = ?',
            'absensi' => 'k.tahun_ajaran_id = ?',
            'jadwal' => 'j.tahun_ajaran_id = ?',
            'materi' => 'mp.tahun_ajaran_id = ?',
            'tugas' => 't.tahun_ajaran_id = ?',
            'pengumpulan' => 't.tahun_ajaran_id = ?'
        };
        $params[] = (int)$yearId;
    }
    if ($classId !== '' && in_array($type, ['siswa', 'kelas', 'nilai', 'absensi', 'jadwal', 'materi', 'tugas', 'pengumpulan'], true)) {
        $where[] = $type === 'jadwal' ? 'j.kelas_id = ?' : ($type === 'materi' ? 'mp.kelas_id = ?' : (in_array($type, ['tugas', 'pengumpulan'], true) ? 't.kelas_id = ?' : 'k.id = ?'));
        $params[] = (int)$classId;
    }
    if ($type === 'nilai' && $semester !== '') {
        $where[] = 'n.semester = ?';
        $params[] = $semester;
    }
    if (in_array($type, ['absensi', 'absensi_guru'], true)) {
        if ($dateFrom !== '') {
            $where[] = ($type === 'absensi' ? 'a' : 'ag') . '.tanggal >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = ($type === 'absensi' ? 'a' : 'ag') . '.tanggal <= ?';
            $params[] = $dateTo;
        }
    }
    if ($type === 'tugas' && in_array($statusFilter, ['Draft', 'Terbit', 'Arsip'], true)) {
        $where[] = 't.status=?';
        $params[] = $statusFilter;
    }
    if ($type === 'materi' && in_array($statusFilter, ['Draft', 'Terbit', 'Arsip'], true)) {
        $where[] = 'mp.status=?';
        $params[] = $statusFilter;
    }
    if ($type === 'pengumpulan' && in_array($statusFilter, ['belum', 'dinilai'], true)) {
        $where[] = $statusFilter === 'belum' ? 'p.nilai IS NULL' : 'p.nilai IS NOT NULL';
    }
    if ($type === 'tugas' && $dateFrom !== '') {
        $where[] = 'DATE(t.tenggat)>=?';
        $params[] = $dateFrom;
    }
    if ($type === 'tugas' && $dateTo !== '') {
        $where[] = 'DATE(t.tenggat)<=?';
        $params[] = $dateTo;
    }
    if ($type === 'pengumpulan' && $dateFrom !== '') {
        $where[] = 'DATE(p.dikumpulkan_at)>=?';
        $params[] = $dateFrom;
    }
    if ($type === 'pengumpulan' && $dateTo !== '') {
        $where[] = 'DATE(p.dikumpulkan_at)<=?';
        $params[] = $dateTo;
    }
}
if ($filterError !== '') {
    $data = [];
} else {
    $sql = $queries[$type]['sql'];
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    if (in_array($type, ['materi', 'tugas', 'pengumpulan'], true)) {
        $requiredTables = $type === 'materi' ? "'materi_pelajaran'" : "'tugas_pelajaran','pengumpulan_tugas'";
        $requiredTableCount = $type === 'materi' ? 1 : 2;
        $tablesReady = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ($requiredTables)")->fetchColumn() === $requiredTableCount;
        if (!$tablesReady) {
            $filterError = 'Modul pembelajaran belum tersedia. Terapkan migrasi materi dan tugas terlebih dahulu.';
            $data = [];
            $sql = '';
        }
    }
    if ($sql !== '') $sql .= match ($type) {
        'siswa' => ' ORDER BY k.nama_kelas,s.nama',
        'kelas' => ' GROUP BY k.id,ta.nama,k.nama_kelas,k.tingkat,g.nama ORDER BY ta.nama DESC,k.tingkat,k.nama_kelas',
        'guru' => ' ORDER BY nama',
        'nilai' => ' ORDER BY s.nama,m.nama_mapel',
        'absensi' => ' ORDER BY a.tanggal DESC,s.nama',
        'absensi_guru' => ' ORDER BY ag.tanggal DESC,g.nama',
        'materi' => ' ORDER BY mp.created_at DESC,mp.judul',
        'tugas' => ' GROUP BY t.id,ta.nama,k.nama_kelas,m.nama_mapel,g.nama ORDER BY t.tenggat IS NULL,t.tenggat DESC',
        'pengumpulan' => ' ORDER BY p.dikumpulkan_at DESC,s.nama',
        default => " ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'),j.jam_mulai,k.nama_kelas",
    };
    if ($sql !== '') {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
    }
}
if ($type === 'nilai' && $data) {
    $weights = $pdo->query('SELECT bobot_tugas,bobot_uts,bobot_uas FROM profil_sekolah ORDER BY id LIMIT 1')->fetch() ?: ['bobot_tugas' => 30, 'bobot_uts' => 30, 'bobot_uas' => 40];
    $data = array_map(static function (array $row) use ($weights): array {
        $hasNewComponents = $row['nilai_uts'] !== null && $row['nilai_uas'] !== null;
        $uts = $hasNewComponents ? (float)$row['nilai_uts'] : '-';
        $uas = $hasNewComponents ? (float)$row['nilai_uas'] : (float)$row['nilai_legacy'];
        $final = $hasNewComponents
            ? round(((float)$row['nilai_tugas'] * (float)$weights['bobot_tugas'] + $uts * (float)$weights['bobot_uts'] + $uas * (float)$weights['bobot_uas']) / 100, 2)
            : round(((float)$row['nilai_tugas'] + $uas) / 2, 2);
        return [
            'siswa' => $row['siswa'],
            'nama_mapel' => $row['nama_mapel'],
            'tahun_ajaran' => $row['tahun_ajaran'],
            'semester' => $row['semester'],
            'nilai_tugas' => $row['nilai_tugas'],
            'nilai_uts' => $uts,
            'nilai_uas' => $uas,
            'nilai_akhir' => $final,
            'kkm' => $row['kkm'],
            'hasil' => $final >= (float)$row['kkm'] ? 'Tuntas' : 'Belum Tuntas',
        ];
    }, $data);
}
$schoolName = (string)$pdo->query('SELECT nama FROM profil_sekolah ORDER BY id LIMIT 1')->fetchColumn();
if ($schoolName === '') $schoolName = 'SMP Negeri 4 Pirime';
$reportPeriod = [];
if ($yearId !== '') $reportPeriod[] = 'Tahun ajaran: ' . ($schoolYears[$yearId] ?? '');
if ($classId !== '') $reportPeriod[] = 'Kelas: ' . ($classes[$classId] ?? '');
if ($semester !== '') $reportPeriod[] = 'Semester: ' . ($semester === '1' ? 'Ganjil' : 'Genap');
if ($dateFrom !== '' || $dateTo !== '') $reportPeriod[] = 'Tanggal: ' . ($dateFrom ?: 'awal') . ' s.d. ' . ($dateTo ?: 'sekarang');
if ($statusFilter !== '') $reportPeriod[] = 'Status: ' . $statusFilter;
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sims-' . $type . '-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $queries[$type]['headers']);
    foreach ($data as $row) fputcsv($output, array_values($row));
    fclose($output);
    exit;
}
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3"><?= e($queries[$type]['title']) ?></h1>
        <p class="text-secondary mb-0">Laporan administrasi sekolah · akses <?= e(role_label()) ?>.</p>
    </div>
    <div class="d-flex gap-2 no-print"><a class="btn btn-outline-primary" href="reports.php?<?= e(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Ekspor CSV</a><button onclick="window.print()" class="btn btn-primary">Cetak / Simpan PDF</button></div>
</div>
<div class="card p-3">
    <div class="d-flex flex-wrap gap-2 mb-3 no-print"><?php foreach (['siswa' => 'Data Siswa', 'guru' => 'Data Guru', 'kelas' => 'Distribusi Kelas', 'nilai' => 'Nilai Siswa', 'absensi' => 'Absensi Siswa', 'absensi_guru' => 'Kehadiran Guru', 'jadwal' => 'Jadwal Kelas', 'materi' => 'Materi', 'tugas' => 'Tugas', 'pengumpulan' => 'Pengumpulan'] as $k => $label): ?><a class="btn btn-sm <?= $type === $k ? 'btn-primary' : 'btn-outline-primary' ?>" href="reports.php?type=<?= e($k) ?>"><?= e($label) ?></a><?php endforeach; ?></div>
    <?php if (in_array($type, ['siswa', 'kelas', 'nilai', 'jadwal', 'materi', 'tugas', 'pengumpulan'], true) || in_array($type, ['absensi', 'absensi_guru'], true)): ?>
        <form method="get" class="row g-2 align-items-end mb-4 no-print">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <?php if (in_array($type, ['siswa', 'kelas', 'nilai', 'jadwal', 'absensi', 'materi', 'tugas', 'pengumpulan'], true)): ?>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="tahun_ajaran_id">Tahun Ajaran</label><select class="form-select" id="tahun_ajaran_id" name="tahun_ajaran_id">
                        <option value="">Semua tahun</option><?php foreach ($schoolYears as $id => $name): ?><option value="<?= e($id) ?>" <?= (string)$yearId === (string)$id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select></div>
            <?php endif; ?>
            <?php if (in_array($type, ['siswa', 'kelas', 'nilai', 'jadwal', 'absensi', 'materi', 'tugas', 'pengumpulan'], true)): ?><div class="col-sm-6 col-lg-3"><label class="form-label" for="kelas_id">Kelas</label><select class="form-select" id="kelas_id" name="kelas_id">
                        <option value="">Semua kelas</option><?php foreach ($classes as $id => $label): ?><option value="<?= e($id) ?>" <?= (string)$classId === (string)$id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div><?php endif; ?>
            <?php if ($type === 'nilai'): ?><div class="col-sm-6 col-lg-2"><label class="form-label" for="semester">Semester</label><select class="form-select" id="semester" name="semester">
                        <option value="">Semua semester</option>
                        <option value="1" <?= $semester === '1' ? ' selected' : '' ?>>Ganjil</option>
                        <option value="2" <?= $semester === '2' ? ' selected' : '' ?>>Genap</option>
                    </select></div><?php endif; ?>
            <?php if (in_array($type, ['absensi', 'absensi_guru', 'tugas', 'pengumpulan'], true)): ?><div class="col-sm-6 col-lg-3"><label class="form-label" for="tanggal_mulai">Dari tanggal</label><input class="form-control" type="date" id="tanggal_mulai" name="tanggal_mulai" value="<?= e($dateFrom) ?>"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="tanggal_selesai">Sampai tanggal</label><input class="form-control" type="date" id="tanggal_selesai" name="tanggal_selesai" value="<?= e($dateTo) ?>"></div><?php endif; ?>
            <?php if (in_array($type, ['materi', 'tugas'], true)): ?><div class="col-sm-6 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status">
                        <option value="">Semua</option><?php foreach (['Draft', 'Terbit', 'Arsip'] as $st): ?><option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?>
                    </select></div><?php elseif ($type === 'pengumpulan'): ?><div class="col-sm-6 col-lg-2"><label class="form-label" for="status">Penilaian</label><select class="form-select" id="status" name="status">
                        <option value="">Semua</option>
                        <option value="belum" <?= $statusFilter === 'belum' ? 'selected' : '' ?>>Belum dinilai</option>
                        <option value="dinilai" <?= $statusFilter === 'dinilai' ? 'selected' : '' ?>>Sudah dinilai</option>
                    </select></div><?php endif; ?>
            <div class="col-auto"><button class="btn btn-outline-primary">Terapkan Filter</button> <a class="btn btn-outline-secondary" href="reports.php?type=<?= e($type) ?>">Reset</a></div>
        </form>
    <?php endif; ?>
    <?php if ($filterError !== ''): ?><div class="alert alert-danger no-print"><?= e($filterError) ?></div><?php endif; ?>
    <div class="report-print-heading">
        <div class="d-flex align-items-center gap-3"><img src="assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang sekolah" class="report-logo">
            <div>
                <h2 class="h5 mb-1"><?= e($schoolName) ?></h2>
                <p class="mb-0">Laporan Sistem Informasi Manajemen Sekolah</p>
            </div>
        </div>
        <hr>
        <h3 class="h5"><?= e($queries[$type]['title']) ?></h3>
        <p class="small text-secondary">Dibuat oleh <?= e(current_user()) ?> · <?= date('d-m-Y H:i') ?></p><?php if ($reportPeriod): ?><p class="small"><strong><?= e(implode(' · ', $reportPeriod)) ?></strong></p><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>No.</th><?php foreach ($queries[$type]['headers'] as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$data): ?><tr>
                        <td colspan="<?= count($queries[$type]['headers']) + 1 ?>" class="text-center">Belum ada data untuk dilaporkan.</td>
                    </tr><?php else: foreach ($data as $i => $row): ?><tr>
                            <td><?= $i + 1 ?></td><?php foreach ($row as $v): ?><td><?= e($v) ?></td><?php endforeach; ?>
                        </tr><?php endforeach;
                        endif; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-secondary">Jumlah baris: <?= count($data) ?></p>
    <div class="report-signatures">
        <div>Mengetahui,<br>Kepala Sekolah<br><br><br><strong>(........................................)</strong></div>
        <div><?= e(date('d F Y')) ?><br>Petugas<br><br><br><strong>(<?= e(current_user()) ?>)</strong></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>