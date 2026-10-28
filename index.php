<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
if (current_role() === 'siswa' && empty($_GET['module'])) redirect('student.php');
if (current_role() === 'guru' && empty($_GET['module'])) redirect('teacher.php');
require_once __DIR__ . '/modules/config.php';

$moduleKey = (string)($_GET['module'] ?? '');
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
    foreach ($years as $year) if ((int)$year['aktif'] === 1) {
        $activeYear = (string)$year['id'];
        break;
    }
    $schoolYearId = trim((string)($_GET['tahun_ajaran_id'] ?? $activeYear));
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

    $studentAttendance = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
    $stmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi WHERE tanggal BETWEEN ? AND ? GROUP BY status');
    $stmt->execute([$rangeStart, $rangeEnd]);
    foreach ($stmt->fetchAll() as $row) $studentAttendance[$row['status']] = (int)$row['jumlah'];
    $teacherAttendance = array_fill_keys(['Hadir', 'Izin', 'Sakit', 'Alpa'], 0);
    $stmt = $pdo->prepare('SELECT status,COUNT(*) AS jumlah FROM absensi_guru WHERE tanggal BETWEEN ? AND ? GROUP BY status');
    $stmt->execute([$rangeStart, $rangeEnd]);
    foreach ($stmt->fetchAll() as $row) $teacherAttendance[$row['status']] = (int)$row['jumlah'];

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
    $recentActivities=[];
    if(current_role()==='admin'){
        try{$recentActivities=$pdo->query('SELECT l.aksi,l.entitas,l.detail,l.created_at,u.nama_lengkap FROM log_aktivitas l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC,l.id DESC LIMIT 6')->fetchAll();}catch(PDOException $ignored){$recentActivities=[];}
    }
    require __DIR__ . '/includes/header.php';
?>
    <div class="dashboard-heading">
        <div>
            <h1><?= current_role()==='admin'?'Dashboard Super Admin':(current_role()==='kepala_sekolah'?'Dashboard Kepala Sekolah':'Dashboard Operator') ?></h1>
            <p><?= current_role()==='admin'?'Pusat kontrol akun, data, keamanan, dan aktivitas sistem.':(current_role()==='kepala_sekolah'?'Ringkasan pemantauan sekolah dan kehadiran.':'Ringkasan pekerjaan administrasi sekolah.') ?> · <?= e($schoolName) ?></p>
        </div>
        <form method="get" class="dashboard-filter no-print">
            <div><label class="form-label small mb-1" for="tahun_ajaran_id">Tahun Ajaran</label><select class="form-select form-select-sm" id="tahun_ajaran_id" name="tahun_ajaran_id">
                    <option value="">Semua tahun</option><?php foreach ($years as $year): ?><option value="<?= (int)$year['id'] ?>" <?= (string)$year['id'] === $schoolYearId ? ' selected' : '' ?>><?= e($year['nama']) ?><?= (int)$year['aktif'] === 1 ? ' · Aktif' : '' ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="form-label small mb-1" for="periode">Periode Kehadiran</label><select class="form-select form-select-sm" id="periode" name="periode" onchange="this.form.submit()"><?php foreach ($periodLabels as $key => $label): ?><option value="<?= e($key) ?>" <?= $period === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label small mb-1" for="mulai">Dari</label><input class="form-control form-control-sm" id="mulai" type="date" name="mulai" value="<?= e($periodStart->format('Y-m-d')) ?>" <?= $period === 'custom' ? '' : ' disabled' ?>></div>
            <div><label class="form-label small mb-1" for="sampai">Sampai</label><input class="form-control form-control-sm" id="sampai" type="date" name="sampai" value="<?= e($periodEnd->format('Y-m-d')) ?>" <?= $period === 'custom' ? '' : ' disabled' ?>></div><button class="btn btn-primary btn-sm">Terapkan</button>
        </form>
    </div>
    <?php if ($filterError !== ''): ?><div class="alert alert-warning no-print"><?= e($filterError) ?></div><?php endif; ?>
    <div class="row g-3 mb-4">
        <?php foreach ([['Total Siswa', $totalStudents, 'bi-people-fill', 'blue', 'Aktif pada tahun terpilih'], ['Total Guru', $teacherTotal, 'bi-person-badge-fill', 'green', 'Guru aktif'], ['Rombongan Belajar', $classTotal, 'bi-door-open-fill', 'orange', $schoolYearId !== '' ? 'Tahun ajaran terpilih' : 'Semua tahun'], ['Mata Pelajaran', $subjectTotal, 'bi-book-half', 'purple', 'Terdaftar']] as [$label, $value, $icon, $color, $note]): ?><div class="col-6 col-xl-3">
                <div class="card stat-card">
                    <div class="stat-card-top"><span><?= e($label) ?></span><span class="stat-icon <?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></span></div>
                    <div class="stat-number"><?= number_format((int)$value, 0, ',', '.') ?></div>
                    <div class="stat-note"><?= e($note) ?></div>
                </div>
            </div><?php endforeach; ?>
    </div>
    <?php if(current_role()==='admin'): ?><div class="row g-3 mb-4"><div class="col-xl-4"><section class="card p-3 p-md-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Kontrol Sistem</h2><i class="bi bi-shield-check text-primary fs-4"></i></div><div class="d-grid gap-2"><a class="btn btn-outline-primary text-start" href="users.php"><i class="bi bi-person-gear me-2"></i>Manajemen Pengguna</a><a class="btn btn-outline-primary text-start" href="activity.php"><i class="bi bi-clock-history me-2"></i>Log Aktivitas</a><a class="btn btn-outline-primary text-start" href="learning.php?view=materi"><i class="bi bi-mortarboard me-2"></i>Pemantauan Materi & Tugas</a><a class="btn btn-outline-primary text-start" href="index.php?module=profil_sekolah"><i class="bi bi-gear me-2"></i>Pengaturan Sekolah</a></div><p class="small text-secondary mt-3 mb-0">Backup dan pemulihan belum tersedia sebagai fitur aplikasi. Siapkan backup terjadwal sebelum mengelola database produksi.</p></section></div><div class="col-xl-8"><section class="card p-3 p-md-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Aktivitas Terbaru</h2><a class="small" href="activity.php">Buka log lengkap</a></div><?php if(!$recentActivities): ?><div class="empty-state">Belum ada aktivitas tercatat.</div><?php else: ?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Tindakan</th><th>Modul</th></tr></thead><tbody><?php foreach($recentActivities as $activity): ?><tr><td class="text-nowrap"><?= e($activity['created_at']) ?></td><td><?= e($activity['nama_lengkap']?:'Akun dihapus') ?></td><td><?= e($activity['aksi']) ?></td><td><?= e($activity['entitas']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></div></div><?php endif; ?>
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
$migrationRequirements = [
    'berita_sekolah' => ['berita_sekolah' => ['id', 'judul', 'ringkasan', 'isi', 'kategori', 'gambar_url', 'status', 'published_at', 'created_at']],
    'galeri_sekolah' => ['galeri_sekolah' => ['id', 'judul', 'keterangan', 'gambar_url', 'tanggal_kegiatan', 'status', 'created_at']],
    'kalender_akademik' => ['kalender_akademik' => ['tampil_publik']],
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
        <p>Halaman ini belum bisa dibuka karena skema database portal belum lengkap. Penyebab paling umum adalah migrasi belum diimpor ke database <code>sims_db</code>.</p>
        <p class="mb-2">Jalankan file <code>database/migrations/2026_10_29_public_portal.sql</code> melalui phpMyAdmin (pilih database <code>sims_db</code>, buka tab <strong>Import</strong>, lalu pilih file tersebut). Setelah berhasil, muat ulang halaman ini.</p>
        <details><summary>Rincian pemeriksaan</summary><ul class="mt-2 mb-0"><?php foreach ($missingPortalSchema as $missing): ?><li><?= e($missing) ?></li><?php endforeach; ?></ul></details>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
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
    }
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_form_action'] ?? '') === 'save') {
        verify_csrf();
        $values = [];
        $newUploads = [];
        $oldUploads = [];
        foreach ($fields as $key => $field) {
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
                if ($action === 'create') {
                    $cols = array_keys($values);
                    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_values($values));
                    record_activity($pdo, 'tambah', $table, (int)$pdo->lastInsertId());
                    try {
                        if ($moduleKey === 'pengumuman' && ($values['status'] ?? '') === 'Terbit') {
                            $notify = $pdo->prepare('INSERT INTO notifikasi (judul,pesan,tautan) VALUES (?,?,?)');
                            $notify->execute(['Pengumuman baru: ' . (string)$values['judul'], (string)$values['isi'], 'index.php?module=pengumuman']);
                        } elseif (in_array($moduleKey, ['kalender_akademik', 'kokurikuler'], true)) {
                            $eventTitle = (string)($values['judul'] ?? $values['nama_kegiatan'] ?? 'Agenda sekolah');
                            $notify = $pdo->prepare('INSERT INTO notifikasi (judul,pesan,tautan) VALUES (?,?,?)');
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
                    flash('Data berhasil diperbarui.');
                }
                foreach ($oldUploads as $oldUpload) portal_remove_upload($oldUpload);
                redirect("index.php?module=$moduleKey");
            } catch (PDOException $ex) {
                foreach ($newUploads as $newUpload) portal_remove_upload($newUpload);
                $error = 'Data gagal disimpan. Periksa nomor yang duplikat dan pastikan ID relasi (kelas/guru/siswa/mapel/tahun ajaran) benar.';
            }
        } else {
            foreach ($newUploads as $newUpload) portal_remove_upload($newUpload);
        }
        $record = array_merge($record, $values);
    }
    require __DIR__ . '/includes/header.php';
?>
    <div class="mb-4"><a href="index.php?module=<?= e($moduleKey) ?>" class="text-decoration-none">&larr; Kembali ke daftar</a>
        <h1 class="h3 mt-2"><?= $action === 'create' ? 'Tambah' : 'Edit' ?> <?= e($pageTitle) ?></h1>
    </div>
    <div class="card p-3 p-md-4">
        <form method="post"<?= array_filter($fields, static fn($field) => ($field['type'] ?? '') === 'image_upload') ? ' enctype="multipart/form-data"' : '' ?>><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="_form_action" value="save">
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <div class="row"><?php foreach ($fields as $key => $field) render_input($key, $field, $record[$key] ?? $field['default'] ?? '', isset($field['relation']) ? relation_options($pdo, $field['relation']) : []); ?></div>
            <button class="btn btn-primary">Simpan</button> <a class="btn btn-outline-secondary" href="index.php?module=<?= e($moduleKey) ?>">Batal</a>
        </form>
    </div>
<?php require __DIR__ . '/includes/footer.php';
    exit;
}
$q = trim((string)($_GET['q'] ?? ''));
$cols = $cfg['columns'];
$sql = "SELECT `$pk`, `" . implode('`,`', $cols) . "` FROM `$table`";
$params = [];
$whereSql = '';
if ($q !== '') {
    $searchable = array_values(array_filter($cols, fn($c) => !in_array($c, ['id', 'kelas_id', 'wali_guru_id', 'tahun_ajaran_id', 'guru_id', 'siswa_id', 'mapel_id', 'nilai_tugas', 'nilai_ujian', 'aktif'], true)));
    if ($searchable) {
        $whereSql = ' WHERE ' . implode(' OR ', array_map(fn($c) => "`$c` LIKE ?", $searchable));
        $params = array_fill(0, count($searchable), "%$q%");
    }
}
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
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
        <p class="text-secondary mb-0"><?= $canManage ? 'Kelola data: tambah, lihat, ubah, atau hapus.' : 'Mode baca saja sesuai hak akses akun.' ?></p>
    </div><?php if ($canManage && ($canCreate || $moduleKey === 'absensi')): ?><div class="d-flex gap-2"><?php if ($moduleKey === 'absensi'): ?><a class="btn btn-outline-primary" href="bulk_absensi.php">Input Satu Kelas</a><?php endif; ?><?php if ($canCreate): ?><a class="btn btn-primary" href="index.php?module=<?= e($moduleKey) ?>&action=create">+ Tambah Data</a><?php endif; ?></div><?php endif; ?>
</div>
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
                        <td colspan="<?= count($cols) + ($canManage ? 2 : 1) ?>" class="text-center text-secondary py-4">Belum ada data.</td>
                    </tr>
                    <?php else: foreach ($rows as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <?php foreach ($cols as $col): $cell = $row[$col] ?? null;
                                if (isset($relationLabels[$col])) $cell = $relationLabels[$col][(string)$cell] ?? $cell;
                                elseif (isset($staticLabels[$col])) $cell = $staticLabels[$col][(string)$cell] ?? $cell; ?><td><?= e($cell ?? '-') ?></td><?php endforeach; ?>
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
