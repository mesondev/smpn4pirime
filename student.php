<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
if (current_role() !== 'siswa') { http_response_code(403); exit('Halaman ini hanya untuk akun siswa.'); }
$link = $pdo->prepare('SELECT role,siswa_id,aktif FROM users WHERE id=?'); $link->execute([(int)$_SESSION['user_id']]); $account = $link->fetch();
if (!$account || $account['role'] !== 'siswa' || (int)$account['aktif'] !== 1) { http_response_code(403); exit('Akun siswa tidak aktif atau perannya telah berubah. Silakan masuk kembali.'); }
$studentId = (int)($account['siswa_id'] ?? 0);
if ($studentId < 1) { http_response_code(403); exit('Akun ini belum ditautkan ke data siswa. Hubungi Super Admin sekolah.'); }
$stmt=$pdo->prepare('SELECT s.id,s.nis,s.nisn,s.nama,s.jenis_kelamin,s.tanggal_lahir,s.alamat,s.status,k.nama_kelas,k.tingkat,ta.nama AS tahun_ajaran,g.nama AS wali_nama FROM siswa s JOIN kelas k ON k.id=s.kelas_id LEFT JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id LEFT JOIN guru g ON g.id=k.wali_guru_id WHERE s.id=? LIMIT 1');
$stmt->execute([$studentId]); $student=$stmt->fetch();
if (!$student) { http_response_code(404); exit('Data siswa tertaut tidak ditemukan. Hubungi Super Admin sekolah.'); }
$view=(string)($_GET['view']??'dashboard'); if(!in_array($view,['dashboard','jadwal','absensi','nilai'],true))$view='dashboard';
$pageTitle=match($view){'jadwal'=>'Jadwal Pelajaran','absensi'=>'Riwayat Absensi','nilai'=>'Nilai Saya',default=>'Dashboard Siswa'};
$params=[$studentId];
if($view==='jadwal'){
 $q=$pdo->prepare('SELECT j.hari,j.jam_mulai,j.jam_selesai,m.nama_mapel,g.nama AS guru,j.ruang FROM jadwal_pelajaran j JOIN mapel m ON m.id=j.mapel_id LEFT JOIN guru g ON g.id=j.guru_id WHERE j.kelas_id=(SELECT kelas_id FROM siswa WHERE id=?) ORDER BY FIELD(j.hari,\'Senin\',\'Selasa\',\'Rabu\',\'Kamis\',\'Jumat\',\'Sabtu\'),j.jam_mulai');$q->execute($params);$rows=$q->fetchAll();
}elseif($view==='absensi'){
 $q=$pdo->prepare('SELECT tanggal,status,keterangan FROM absensi WHERE siswa_id=? ORDER BY tanggal DESC,id DESC LIMIT 100');$q->execute($params);$rows=$q->fetchAll();
}elseif($view==='nilai'){
 $q=$pdo->prepare('SELECT m.nama_mapel,ta.nama AS tahun_ajaran,IF(n.semester=\'1\',\'Ganjil\',\'Genap\') AS semester,n.nilai_tugas,n.nilai_uts,n.nilai_uas,n.nilai_ujian,m.kkm FROM nilai n JOIN mapel m ON m.id=n.mapel_id JOIN tahun_ajaran ta ON ta.id=n.tahun_ajaran_id WHERE n.siswa_id=? ORDER BY ta.nama DESC,n.semester DESC,m.nama_mapel');$q->execute($params);$rows=$q->fetchAll();
}else{$rows=[];}
$pageTitle=$pageTitle; require __DIR__.'/includes/header.php';
?>
<div class="dashboard-heading"><div><h1><?= e($pageTitle) ?></h1><p>Portal akademik pribadi untuk <?= e($student['nama']) ?>.</p></div></div>
<div class="row g-3 mb-4"><div class="col-md-6 col-xl-3"><div class="card p-3 h-100"><small class="text-secondary">Nama</small><strong><?= e($student['nama']) ?></strong><small>NIS <?= e($student['nis']) ?></small></div></div><div class="col-md-6 col-xl-3"><div class="card p-3 h-100"><small class="text-secondary">Kelas</small><strong><?= e($student['nama_kelas']) ?></strong><small><?= e($student['tahun_ajaran']??'') ?></small></div></div><div class="col-md-6 col-xl-3"><div class="card p-3 h-100"><small class="text-secondary">Wali Kelas</small><strong><?= e($student['wali_nama']??'Belum ditetapkan') ?></strong></div></div><div class="col-md-6 col-xl-3"><div class="card p-3 h-100"><small class="text-secondary">Status</small><strong><?= e($student['status']) ?></strong></div></div></div>
<?php if($view==='dashboard'): ?>
<div class="row g-4"><div class="col-lg-6"><div class="card p-3 p-md-4 h-100"><h2 class="h5">Akses Cepat</h2><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="student.php?view=jadwal">Jadwal</a><a class="btn btn-outline-primary" href="student.php?view=absensi">Riwayat Absensi</a><a class="btn btn-outline-primary" href="student.php?view=nilai">Nilai Saya</a><a class="btn btn-outline-secondary" href="profile.php">Profil dan kata sandi</a></div></div></div><div class="col-lg-6"><div class="card p-3 p-md-4 h-100"><h2 class="h5">Privasi Data</h2><p class="text-secondary mb-0">Halaman ini hanya menampilkan jadwal, absensi, dan nilai milik akun yang sedang masuk. Hubungi sekolah jika data kelas atau profil belum sesuai.</p></div></div></div>
<?php else: ?><div class="card p-3"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><?php if($view==='jadwal'): ?><th>Hari</th><th>Jam</th><th>Mata Pelajaran</th><th>Guru</th><th>Ruang</th><?php elseif($view==='absensi'): ?><th>Tanggal</th><th>Status</th><th>Keterangan</th><?php else: ?><th>Mata Pelajaran</th><th>Tahun Ajaran</th><th>Semester</th><th>Tugas</th><th>UTS</th><th>UAS</th><th>Nilai</th><th>KKM</th><?php endif; ?></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-secondary py-4">Belum ada data untuk ditampilkan.</td></tr><?php else: foreach($rows as $row): ?><tr><?php if($view==='jadwal'): ?><td><?= e($row['hari']) ?></td><td><?= e(substr((string)$row['jam_mulai'],0,5)) ?>–<?= e(substr((string)$row['jam_selesai'],0,5)) ?></td><td><?= e($row['nama_mapel']) ?></td><td><?= e($row['guru']??'—') ?></td><td><?= e($row['ruang']??'—') ?></td><?php elseif($view==='absensi'): ?><td><?= e($row['tanggal']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['keterangan']??'—') ?></td><?php else: ?><td><?= e($row['nama_mapel']) ?></td><td><?= e($row['tahun_ajaran']) ?></td><td><?= e($row['semester']) ?></td><td><?= e($row['nilai_tugas']) ?></td><td><?= e($row['nilai_uts']??'—') ?></td><td><?= e($row['nilai_uas']??$row['nilai_ujian']) ?></td><td><?= e($row['nilai_uas']??$row['nilai_ujian']) ?></td><td><?= e($row['kkm']??'—') ?></td><?php endif; ?></tr><?php endforeach; endif; ?></tbody></table></div></div><?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
