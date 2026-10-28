<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
$role = current_role();
if (!in_array($role, ['admin','guru','siswa'], true)) { http_response_code(403); exit('Modul pembelajaran ini tidak tersedia untuk peran Anda.'); }
$learningSchema=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('materi_pelajaran','tugas_pelajaran','pengumpulan_tugas')")->fetchColumn();
if($learningSchema!==3){http_response_code(503);exit('Modul pembelajaran belum siap. Super Admin perlu menerapkan migrasi database/migrations/2026_10_29_learning_materials_tasks.sql terlebih dahulu.');}
$userId = (int)($_SESSION['user_id'] ?? 0);
$accountStmt = $pdo->prepare('SELECT guru_id,siswa_id,aktif FROM users WHERE id=? LIMIT 1');
$accountStmt->execute([$userId]); $account = $accountStmt->fetch();
if (!$account || (int)$account['aktif'] !== 1) { http_response_code(403); exit('Akun tidak aktif.'); }
$teacherId = (int)($account['guru_id'] ?? 0); $studentId = (int)($account['siswa_id'] ?? 0);
if (($role === 'guru' && !$teacherId) || ($role === 'siswa' && !$studentId)) { http_response_code(403); exit('Akun belum terhubung ke profil sekolah yang benar.'); }
$view = (string)($_GET['view'] ?? 'materi');
$validViews = in_array($role,['guru','admin'],true) ? ['materi','tugas','pengumpulan'] : ['materi','tugas'];
if (!in_array($view, $validViews, true)) $view = 'materi';
$error = ''; $success = '';
$assignments = [];
if ($role === 'guru') {
    $a = $pdo->prepare("SELECT DISTINCT j.kelas_id,j.mapel_id,j.tahun_ajaran_id,k.nama_kelas,m.nama_mapel,ta.nama AS tahun_ajaran FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id JOIN tahun_ajaran ta ON ta.id=j.tahun_ajaran_id WHERE j.guru_id=? AND ta.aktif=1 ORDER BY k.nama_kelas,m.nama_mapel");
    $a->execute([$teacherId]); $assignments = $a->fetchAll();
}
$selected = (string)($_POST['assignment'] ?? $_GET['assignment'] ?? '');
$selectedAssignment = null;
foreach ($assignments as $a) { $key = $a['kelas_id'].'|'.$a['mapel_id'].'|'.$a['tahun_ajaran_id']; if ($selected === $key) $selectedAssignment = $a; }
if (!$selectedAssignment && $assignments) { $selectedAssignment = $assignments[0]; $selected = $selectedAssignment['kelas_id'].'|'.$selectedAssignment['mapel_id'].'|'.$selectedAssignment['tahun_ajaran_id']; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string)($_POST['action'] ?? ''); $view = (string)($_POST['view'] ?? $view);
        if ($role === 'guru' && in_array($action, ['create_material','create_task'], true)) {
            $parts = array_map('intval', explode('|', (string)($_POST['assignment'] ?? '')));
            if (count($parts)!==3) throw new InvalidArgumentException('Pilih kelas dan mata pelajaran yang ditugaskan.');
            $authorized = false; foreach ($assignments as $a) if ((int)$a['kelas_id']===$parts[0]&&(int)$a['mapel_id']===$parts[1]&&(int)$a['tahun_ajaran_id']===$parts[2]) $authorized=true;
            if (!$authorized) throw new InvalidArgumentException('Penugasan tersebut tidak aktif pada akun Anda.');
            $title = trim((string)($_POST['judul'] ?? '')); $description = trim((string)($_POST['deskripsi'] ?? ''));
            $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
            if ($title === '' || $titleLength > 180) throw new InvalidArgumentException('Judul wajib diisi dan maksimal 180 karakter.');
            if ($action === 'create_material') {
                $relative = null; $original = null; $upload = $_FILES['file_materi'] ?? null;
                if ($upload && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if ((int)$upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$upload['tmp_name']) || (int)$upload['size'] < 1 || (int)$upload['size'] > 10*1024*1024) throw new InvalidArgumentException('File materi maksimal 10 MB.');
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
                    $allowed = ['application/pdf'=>'pdf','application/msword'=>'doc','application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx','application/vnd.ms-excel'=>'xls','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx','application/vnd.ms-powerpoint'=>'ppt','application/vnd.openxmlformats-officedocument.presentationml.presentation'=>'pptx','text/plain'=>'txt','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                    if (!isset($allowed[$mime])) throw new InvalidArgumentException('Format materi yang diizinkan: PDF, Office, TXT, JPG, PNG, atau WebP.');
                    $dir = __DIR__.'/uploads/akademik'; if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new RuntimeException('Folder penyimpanan materi tidak dapat dibuat.');
                    $name = bin2hex(random_bytes(20)).'.'.$allowed[$mime]; if (!move_uploaded_file((string)$upload['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('File materi gagal disimpan.');
                    $relative='uploads/akademik/'.$name; $original=substr((string)preg_replace('/[^A-Za-z0-9._ -]/','_',basename((string)$upload['name'])),0,180);
                }
                $stmt=$pdo->prepare("INSERT INTO materi_pelajaran (guru_id,kelas_id,mapel_id,tahun_ajaran_id,judul,deskripsi,file_path,file_name,status) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$teacherId,$parts[0],$parts[1],$parts[2],$title,$description?:null,$relative,$original,($_POST['status']??'Draft')==='Terbit'?'Terbit':'Draft']);
                record_activity($pdo,'buat materi','materi_pelajaran',(int)$pdo->lastInsertId(),$title); $success='Materi berhasil disimpan.'; $view='materi';
            } else {
                $instructions = trim((string)($_POST['instruksi'] ?? '')); if ($instructions==='') throw new InvalidArgumentException('Instruksi tugas wajib diisi.');
                $deadline = trim((string)($_POST['tenggat'] ?? ''));
                if ($deadline !== '') { $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$deadline); if (!$dt) throw new InvalidArgumentException('Tanggal tenggat tidak valid.'); $deadline=$dt->format('Y-m-d H:i:s'); } else $deadline=null;
                $stmt=$pdo->prepare("INSERT INTO tugas_pelajaran (guru_id,kelas_id,mapel_id,tahun_ajaran_id,judul,instruksi,tenggat,status) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$teacherId,$parts[0],$parts[1],$parts[2],$title,$instructions,$deadline,($_POST['status']??'Draft')==='Terbit'?'Terbit':'Draft']);
                record_activity($pdo,'buat tugas','tugas_pelajaran',(int)$pdo->lastInsertId(),$title); $success='Tugas berhasil disimpan.'; $view='tugas';
            }
        } elseif ($role === 'siswa' && $action === 'submit_task') {
            $taskId=(int)($_POST['tugas_id']??0); $answer=trim((string)($_POST['jawaban']??''));
            $answerLength = function_exists('mb_strlen') ? mb_strlen($answer) : strlen($answer);
            if ($answer==='' || $answerLength>20000) throw new InvalidArgumentException('Jawaban wajib diisi dan maksimal 20.000 karakter.');
            $check=$pdo->prepare("SELECT t.id,t.tenggat FROM tugas_pelajaran t JOIN siswa s ON s.id=? AND s.kelas_id=t.kelas_id AND s.status='Aktif' WHERE t.id=? AND t.status='Terbit' LIMIT 1"); $check->execute([$studentId,$taskId]); $task=$check->fetch();
            if (!$task) throw new InvalidArgumentException('Tugas tidak tersedia untuk kelas Anda.');
            if ($task['tenggat'] && strtotime($task['tenggat']) < time()) throw new InvalidArgumentException('Tenggat pengumpulan sudah lewat.');
            $save=$pdo->prepare('INSERT INTO pengumpulan_tugas (tugas_id,siswa_id,jawaban) VALUES (?,?,?) ON DUPLICATE KEY UPDATE jawaban=VALUES(jawaban),dikumpulkan_at=CURRENT_TIMESTAMP,nilai=NULL,umpan_balik=NULL,dinilai_guru_id=NULL,dinilai_at=NULL'); $save->execute([$taskId,$studentId,$answer]);
            record_activity($pdo,'kumpulkan tugas','pengumpulan_tugas',null,'siswa '.$studentId.' · tugas '.$taskId); $success='Jawaban berhasil dikumpulkan.'; $view='tugas';
        } elseif (in_array($role,['guru','admin'],true) && $action === 'grade_submission') {
            $submissionId=(int)($_POST['submission_id']??0); $score=trim((string)($_POST['nilai']??'')); $feedback=trim((string)($_POST['umpan_balik']??''));
            if (!is_numeric($score)||(float)$score<0||(float)$score>100) throw new InvalidArgumentException('Nilai harus berada pada rentang 0 sampai 100.');
            $owner=$role==='admin'?$pdo->prepare('SELECT p.id FROM pengumpulan_tugas p WHERE p.id=?'):$pdo->prepare('SELECT p.id FROM pengumpulan_tugas p JOIN tugas_pelajaran t ON t.id=p.tugas_id WHERE p.id=? AND t.guru_id=?');
            if($role==='admin')$owner->execute([$submissionId]);else $owner->execute([$submissionId,$teacherId]);
            if (!$owner->fetchColumn()) throw new InvalidArgumentException('Pengumpulan tidak ditemukan atau bukan tugas Anda.');
            $graderId=$role==='admin'?null:$teacherId;$grade=$pdo->prepare('UPDATE pengumpulan_tugas SET nilai=?,umpan_balik=?,dinilai_guru_id=?,dinilai_at=NOW() WHERE id=?'); $grade->execute([(float)$score,$feedback?:null,$graderId,$submissionId]);
            record_activity($pdo,'nilai tugas','pengumpulan_tugas',$submissionId,$role==='admin'?'Super Admin':'Guru '.$teacherId); $success='Penilaian tugas berhasil disimpan.'; $view='pengumpulan';
        } elseif ($role === 'admin' && $action === 'moderate_status') {
            $entity=(string)($_POST['entity']??'');$recordId=(int)($_POST['record_id']??0);$status=(string)($_POST['status']??'');
            if(!in_array($entity,['materi_pelajaran','tugas_pelajaran'],true)||$recordId<1||!in_array($status,['Draft','Terbit','Arsip'],true))throw new InvalidArgumentException('Perubahan status konten tidak valid.');
            $table=$entity==='materi_pelajaran'?'materi_pelajaran':'tugas_pelajaran';$moderate=$pdo->prepare("UPDATE `$table` SET status=? WHERE id=?");$moderate->execute([$status,$recordId]);
            if(!$moderate->rowCount()){$exists=$pdo->prepare("SELECT id FROM `$table` WHERE id=?");$exists->execute([$recordId]);if(!$exists->fetchColumn())throw new InvalidArgumentException('Konten tidak ditemukan.');}
            record_activity($pdo,'ubah status konten',$entity,$recordId,$status);$success='Status konten berhasil diperbarui.';$view=$entity==='materi_pelajaran'?'materi':'tugas';
        }
    } catch (Throwable $ex) { $error=$ex instanceof InvalidArgumentException?$ex->getMessage():'Permintaan gagal disimpan. Periksa data dan penugasan Anda.'; }
}
$rows=[];
if (in_array($role,['guru','admin'],true) && $view==='pengumpulan') {
    if($role==='admin')$q=$pdo->query('SELECT p.id,p.jawaban,p.dikumpulkan_at,p.nilai,p.umpan_balik,s.nama AS siswa,s.nis,t.judul,t.id AS tugas_id,k.nama_kelas,m.nama_mapel FROM pengumpulan_tugas p JOIN siswa s ON s.id=p.siswa_id JOIN tugas_pelajaran t ON t.id=p.tugas_id JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id ORDER BY p.dikumpulkan_at DESC');
    else{$q=$pdo->prepare('SELECT p.id,p.jawaban,p.dikumpulkan_at,p.nilai,p.umpan_balik,s.nama AS siswa,s.nis,t.judul,t.id AS tugas_id,k.nama_kelas,m.nama_mapel FROM pengumpulan_tugas p JOIN siswa s ON s.id=p.siswa_id JOIN tugas_pelajaran t ON t.id=p.tugas_id JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id WHERE t.guru_id=? ORDER BY p.dikumpulkan_at DESC');$q->execute([$teacherId]);}
    $rows=$q->fetchAll();
} elseif ($view==='materi') {
    if ($role==='admin') { $q=$pdo->query('SELECT x.*,k.nama_kelas,m.nama_mapel,g.nama AS nama_guru FROM materi_pelajaran x JOIN kelas k ON k.id=x.kelas_id JOIN mapel m ON m.id=x.mapel_id JOIN guru g ON g.id=x.guru_id ORDER BY x.created_at DESC'); }
    elseif ($role==='guru') { $q=$pdo->prepare('SELECT x.*,k.nama_kelas,m.nama_mapel,g.nama AS nama_guru FROM materi_pelajaran x JOIN kelas k ON k.id=x.kelas_id JOIN mapel m ON m.id=x.mapel_id JOIN guru g ON g.id=x.guru_id WHERE x.guru_id=? ORDER BY x.created_at DESC'); $q->execute([$teacherId]); }
    else { $q=$pdo->prepare("SELECT x.*,k.nama_kelas,m.nama_mapel FROM materi_pelajaran x JOIN kelas k ON k.id=x.kelas_id JOIN mapel m ON m.id=x.mapel_id JOIN siswa s ON s.kelas_id=x.kelas_id WHERE s.id=? AND s.status='Aktif' AND x.status='Terbit' ORDER BY x.created_at DESC"); $q->execute([$studentId]); }
    $rows=$q->fetchAll();
} elseif ($view==='tugas') {
    if ($role==='admin') { $q=$pdo->query('SELECT t.*,k.nama_kelas,m.nama_mapel,g.nama AS nama_guru,(SELECT COUNT(*) FROM pengumpulan_tugas p WHERE p.tugas_id=t.id) AS jumlah_kumpul FROM tugas_pelajaran t JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id JOIN guru g ON g.id=t.guru_id ORDER BY t.created_at DESC'); }
    elseif ($role==='guru') { $q=$pdo->prepare('SELECT t.*,k.nama_kelas,m.nama_mapel,g.nama AS nama_guru,(SELECT COUNT(*) FROM pengumpulan_tugas p WHERE p.tugas_id=t.id) AS jumlah_kumpul FROM tugas_pelajaran t JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id JOIN guru g ON g.id=t.guru_id WHERE t.guru_id=? ORDER BY t.created_at DESC'); $q->execute([$teacherId]); }
    else { $q=$pdo->prepare("SELECT t.*,k.nama_kelas,m.nama_mapel,p.jawaban,p.dikumpulkan_at,p.nilai,p.umpan_balik FROM tugas_pelajaran t JOIN kelas k ON k.id=t.kelas_id JOIN mapel m ON m.id=t.mapel_id JOIN siswa s ON s.kelas_id=t.kelas_id LEFT JOIN pengumpulan_tugas p ON p.tugas_id=t.id AND p.siswa_id=s.id WHERE s.id=? AND s.status='Aktif' AND t.status='Terbit' ORDER BY t.tenggat IS NULL,t.tenggat"); $q->execute([$studentId]); }
    $rows=$q->fetchAll();
}
$pageTitle = $view==='pengumpulan'?'Pengumpulan Tugas':($view==='tugas'?'Tugas Pembelajaran':'Materi Pembelajaran');
require __DIR__.'/includes/header.php';
?>
<div class="dashboard-heading"><div><h1><?= e($pageTitle) ?></h1><p><?= $role==='admin'?'Pemantauan lintas kelas untuk konten pembelajaran dan pengumpulan siswa. Setiap perubahan status dicatat.':($role==='guru'?'Kelola materi dan tugas sesuai kelas serta mata pelajaran yang ditugaskan.':'Materi dan tugas yang tersedia khusus untuk kelas Anda.') ?></p></div></div>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<div class="d-flex flex-wrap gap-2 mb-4"><a class="btn <?= $view==='materi'?'btn-primary':'btn-outline-primary' ?>" href="learning.php?view=materi">Materi</a><a class="btn <?= $view==='tugas'?'btn-primary':'btn-outline-primary' ?>" href="learning.php?view=tugas">Tugas</a><?php if(in_array($role,['guru','admin'],true)): ?><a class="btn <?= $view==='pengumpulan'?'btn-primary':'btn-outline-primary' ?>" href="learning.php?view=pengumpulan">Pengumpulan dan Penilaian</a><?php endif; ?></div>
<?php if($role==='admin' && in_array($view,['materi','tugas'],true) && $rows): ?><div class="card p-3 mb-4"><h2 class="h6">Kontrol status Super Admin</h2><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Konten</th><th>Guru</th><th>Kelas / Mapel</th><th>Status</th><th>Ubah status</th></tr></thead><tbody><?php foreach($rows as $item): ?><tr><td><?= e($item['judul']) ?></td><td><?= e($item['nama_guru']??'—') ?></td><td><?= e($item['nama_kelas'].' · '.$item['nama_mapel']) ?></td><td><?= e($item['status']) ?></td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="moderate_status"><input type="hidden" name="view" value="<?= e($view) ?>"><input type="hidden" name="entity" value="<?= $view==='materi'?'materi_pelajaran':'tugas_pelajaran' ?>"><input type="hidden" name="record_id" value="<?= (int)$item['id'] ?>"><select class="form-select form-select-sm" name="status"><option <?= $item['status']==='Draft'?'selected':'' ?>>Draft</option><option <?= $item['status']==='Terbit'?'selected':'' ?>>Terbit</option><option <?= $item['status']==='Arsip'?'selected':'' ?>>Arsip</option></select><button class="btn btn-sm btn-outline-primary">Simpan</button></form></td></tr><?php endforeach; ?></tbody></table></div><small class="text-secondary">Setiap perubahan status dicatat ke Log Aktivitas.</small></div><?php endif; ?>
<?php if($role==='guru' && in_array($view,['materi','tugas'],true)): ?>
<div class="card p-3 p-md-4 mb-4"><h2 class="h5">Tambah <?= $view==='materi'?'materi':'tugas' ?></h2><?php if(!$assignments): ?><div class="alert alert-info mb-0">Belum ada jadwal aktif yang ditugaskan kepada akun guru ini.</div><?php else: ?><form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="view" value="<?= e($view) ?>"><input type="hidden" name="action" value="<?= $view==='materi'?'create_material':'create_task' ?>"><div class="col-12"><label class="form-label">Kelas · Mata Pelajaran · Tahun Ajaran</label><select class="form-select" name="assignment" required><?php foreach($assignments as $a): $key=$a['kelas_id'].'|'.$a['mapel_id'].'|'.$a['tahun_ajaran_id']; ?><option value="<?= e($key) ?>" <?= $key===$selected?'selected':'' ?>><?= e($a['nama_kelas'].' · '.$a['nama_mapel'].' · '.$a['tahun_ajaran']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Judul</label><input class="form-control" name="judul" maxlength="180" required></div><div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option value="Draft">Draft</option><option value="Terbit">Terbit</option></select></div><?php if($view==='materi'): ?><div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="deskripsi" rows="3"></textarea></div><div class="col-12"><label class="form-label">File Materi (opsional, maksimal 10 MB)</label><input class="form-control" type="file" name="file_materi" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.webp"></div><?php else: ?><div class="col-12"><label class="form-label">Instruksi</label><textarea class="form-control" name="instruksi" rows="4" required></textarea></div><div class="col-md-6"><label class="form-label">Tenggat</label><input class="form-control" type="datetime-local" name="tenggat"></div><?php endif; ?><div class="col-12"><button class="btn btn-primary">Simpan</button></div></form><?php endif; ?></div>
<?php endif; ?>
<?php if($view==='pengumpulan' && in_array($role,['guru','admin'],true)): ?><div class="vstack gap-3"><?php if(!$rows): ?><div class="empty-state">Belum ada tugas yang dikumpulkan.</div><?php endif; ?><?php foreach($rows as $row): ?><article class="card p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5 mb-1"><?= e($row['judul']) ?></h2><p class="text-secondary mb-2"><?= e($row['siswa'].' · '.$row['nis'].' · '.$row['nama_kelas'].' · '.$row['nama_mapel']) ?></p></div><small><?= e($row['dikumpulkan_at']) ?></small></div><div class="p-3 bg-body-tertiary rounded mb-3" style="white-space:pre-wrap"><?= e($row['jawaban']) ?></div><form method="post" class="row g-2 align-items-end"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="view" value="pengumpulan"><input type="hidden" name="action" value="grade_submission"><input type="hidden" name="submission_id" value="<?= (int)$row['id'] ?>"><div class="col-sm-3"><label class="form-label">Nilai 0–100</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="nilai" value="<?= e($row['nilai']) ?>" required></div><div class="col-sm-7"><label class="form-label">Umpan balik</label><input class="form-control" name="umpan_balik" value="<?= e($row['umpan_balik']) ?>" maxlength="500"></div><div class="col-sm-2"><button class="btn btn-primary w-100">Simpan</button></div></form></article><?php endforeach; ?></div>
<?php elseif($view==='materi'): ?><div class="row g-3"><?php if(!$rows): ?><div class="col-12"><div class="empty-state">Belum ada materi<?= $role==='siswa'?' yang diterbitkan untuk kelas Anda.':'.' ?></div></div><?php endif; ?><?php foreach($rows as $row): ?><div class="col-md-6 col-xl-4"><article class="card p-3 p-md-4 h-100"><div class="d-flex justify-content-between gap-2"><span class="badge text-bg-<?= $row['status']==='Terbit'?'success':($row['status']==='Draft'?'secondary':'dark') ?>"><?= e($row['status']) ?></span><small class="text-secondary"><?= e($row['created_at']) ?></small></div><h2 class="h5 mt-3"><?= e($row['judul']) ?></h2><p class="small text-secondary"><?= e($row['nama_kelas'].' · '.$row['nama_mapel']) ?></p><p class="flex-grow-1" style="white-space:pre-wrap"><?= e($row['deskripsi']) ?></p><?php if($row['file_path']): ?><a class="btn btn-outline-primary mt-auto" href="media/academic.php?type=materi&id=<?= (int)$row['id'] ?>"><i class="bi bi-download me-1"></i><?= e($row['file_name']?:'Unduh materi') ?></a><?php endif; ?></article></div><?php endforeach; ?></div>
<?php else: ?><div class="vstack gap-3"><?php if(!$rows): ?><div class="empty-state">Belum ada tugas<?= $role==='siswa'?' yang diterbitkan untuk kelas Anda.':'.' ?></div><?php endif; ?><?php foreach($rows as $row): ?><article class="card p-3 p-md-4"><div class="d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5 mb-1"><?= e($row['judul']) ?></h2><p class="small text-secondary mb-2"><?= e($row['nama_kelas'].' · '.$row['nama_mapel']) ?> · <?= e($row['status']) ?></p></div><span class="small text-secondary">Tenggat: <?= e($row['tenggat']?:'Tidak ditentukan') ?></span></div><p style="white-space:pre-wrap"><?= e($row['instruksi']??'') ?></p><?php if(in_array($role,['guru','admin'],true)): ?><p class="mb-0">Dikumpulkan: <?= (int)$row['jumlah_kumpul'] ?> siswa</p><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit_task"><input type="hidden" name="view" value="tugas"><input type="hidden" name="tugas_id" value="<?= (int)$row['id'] ?>"><label class="form-label">Jawaban Anda</label><textarea class="form-control mb-2" name="jawaban" rows="4" maxlength="20000" required <?= $row['tenggat']&&strtotime($row['tenggat'])<time()?'disabled':'' ?>><?= e($row['jawaban']??'') ?></textarea><?php if($row['nilai']!==null): ?><div class="alert alert-info py-2">Nilai: <strong><?= e($row['nilai']) ?></strong><?= $row['umpan_balik']?' · '.e($row['umpan_balik']):'' ?></div><?php endif; ?><button class="btn btn-primary" <?= $row['tenggat']&&strtotime($row['tenggat'])<time()?'disabled':'' ?>><?= $row['dikumpulkan_at']?'Perbarui jawaban':'Kumpulkan jawaban' ?></button></form><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
