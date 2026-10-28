<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();
if (current_role() !== 'admin') {
    http_response_code(403);
    exit('Hanya administrator yang dapat mengelola akun pengguna.');
}

$error = '';
$success = '';
$roles = ['admin' => 'Super Admin', 'operator' => 'Operator', 'guru' => 'Guru', 'siswa' => 'Siswa', 'kepala_sekolah' => 'Kepala Sekolah'];
$createRoles = ['operator' => 'Operator', 'guru' => 'Guru', 'siswa' => 'Siswa', 'kepala_sekolah' => 'Kepala Sekolah'];
$teachers = $pdo->query("SELECT id,nama FROM guru WHERE status='Aktif' ORDER BY nama")->fetchAll();
$students = $pdo->query("SELECT id,nis,nama FROM siswa WHERE status='Aktif' ORDER BY nama")->fetchAll();
$classOptions = $pdo->query('SELECT id,nama_kelas FROM kelas ORDER BY nama_kelas')->fetchAll();
$newPhoto = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['nama_lengkap'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? '');
            $profileId = (int)($_POST[$role === 'guru' ? 'guru_id' : 'siswa_id'] ?? 0);
            if ($name === '' || strlen($name) > 120 || $username === '' || strlen($username) > 60 || strlen($password) < 10 || !isset($createRoles[$role])) {
                throw new InvalidArgumentException('Nama, username, dan peran yang diizinkan wajib diisi; password minimal 10 karakter. Super Admin hanya dibuat saat instalasi.');
            }
            $guruId = $role === 'guru' ? $profileId : null;
            $siswaId = $role === 'siswa' ? $profileId : null;
            if ($role === 'guru' || $role === 'siswa') {
                if ($profileId < 1) throw new InvalidArgumentException('Pilih data profil yang akan ditautkan ke akun ini.');
                $table = $role === 'guru' ? 'guru' : 'siswa';
                $check = $pdo->prepare("SELECT id FROM `$table` WHERE id=? AND status='Aktif'");
                $check->execute([$profileId]);
                if (!$check->fetchColumn()) throw new InvalidArgumentException('Profil yang dipilih tidak ditemukan atau tidak aktif.');
                $linkCheck = $pdo->prepare('SELECT id FROM users WHERE ' . ($role === 'guru' ? 'guru_id' : 'siswa_id') . '=? LIMIT 1');
                $linkCheck->execute([$profileId]);
                if ($linkCheck->fetchColumn()) throw new InvalidArgumentException('Profil ini sudah ditautkan ke akun lain.');
            }
            if (isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                [$newPhoto, $photoError] = store_user_photo($_FILES['foto']);
                if ($photoError) throw new InvalidArgumentException($photoError);
            }
            $stmt = $pdo->prepare('INSERT INTO users (username,password_hash,nama_lengkap,role,aktif,guru_id,siswa_id,foto_path,password_must_change) VALUES (?,?,?,?,1,?,?,?,1)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $role, $guruId, $siswaId, $newPhoto]);
            $userId = (int)$pdo->lastInsertId();
            record_activity($pdo, 'buat pengguna', 'users', $userId, $role);
            $success = 'Akun pengguna berhasil dibuat.';
        } elseif ($action === 'update') {
            $userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $name = trim((string)($_POST['nama_lengkap'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? '');
            $active = (string)($_POST['aktif'] ?? '');
            $profileId = (int)($_POST[$role === 'guru' ? 'guru_id' : 'siswa_id'] ?? 0);
            if ($userId < 1 || $name === '' || $username === '' || !isset($roles[$role]) || !in_array($active, ['0', '1'], true)) {
                throw new InvalidArgumentException('Data akun tidak valid.');
            }
            if ($password !== '' && strlen($password) < 10) {
                throw new InvalidArgumentException('Password baru minimal 10 karakter. Kosongkan jika tidak diubah.');
            }
            $current = $pdo->prepare('SELECT role,aktif,foto_path,guru_id,siswa_id FROM users WHERE id=?');
            $current->execute([$userId]);
            $old = $current->fetch();
            if (!$old) throw new InvalidArgumentException('Akun tidak ditemukan.');
            if ($role === 'admin' && $old['role'] !== 'admin') throw new InvalidArgumentException('Akun Super Admin tidak dibuat dari formulir ini.');
            if ($userId === (int)$_SESSION['user_id'] && ($active !== '1' || $role !== 'admin')) {
                throw new InvalidArgumentException('Akun administrator yang sedang digunakan tidak dapat dinonaktifkan atau diturunkan perannya sendiri.');
            }
            if ($old['role'] === 'admin' && (int)$old['aktif'] === 1 && ($active !== '1' || $role !== 'admin')) {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND aktif=1")->fetchColumn();
                if ($admins <= 1) throw new InvalidArgumentException('Minimal harus ada satu akun administrator aktif.');
            }
            $guruId = $role === 'guru' ? $profileId : null;
            $siswaId = $role === 'siswa' ? $profileId : null;
            if ($role === 'guru' || $role === 'siswa') {
                if ($profileId < 1) throw new InvalidArgumentException('Pilih data profil yang akan ditautkan ke akun ini.');
                $table = $role === 'guru' ? 'guru' : 'siswa';
                $check = $pdo->prepare("SELECT id FROM `$table` WHERE id=? AND status='Aktif'");
                $check->execute([$profileId]);
                if (!$check->fetchColumn()) throw new InvalidArgumentException('Profil yang dipilih tidak ditemukan atau tidak aktif.');
                $linkCheck = $pdo->prepare('SELECT id FROM users WHERE ' . ($role === 'guru' ? 'guru_id' : 'siswa_id') . '=? AND id<>? LIMIT 1');
                $linkCheck->execute([$profileId, $userId]);
                if ($linkCheck->fetchColumn()) throw new InvalidArgumentException('Profil ini sudah ditautkan ke akun lain.');
            }
            $photoPath = (string)($old['foto_path'] ?? '');
            $replacePhoto = isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $removePhoto = (string)($_POST['hapus_foto'] ?? '') === '1';
            if ($replacePhoto) {
                [$newPhoto, $photoError] = store_user_photo($_FILES['foto']);
                if ($photoError) throw new InvalidArgumentException($photoError);
                $photoPath = (string)$newPhoto;
            } elseif ($removePhoto) $photoPath = '';
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET username=?,nama_lengkap=?,role=?,aktif=?,password_hash=?,password_must_change=1,guru_id=?,siswa_id=?,foto_path=? WHERE id=?');
                $stmt->execute([$username, $name, $role, (int)$active, password_hash($password, PASSWORD_DEFAULT), $guruId, $siswaId, $photoPath ?: null, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username=?,nama_lengkap=?,role=?,aktif=?,guru_id=?,siswa_id=?,foto_path=? WHERE id=?');
                $stmt->execute([$username, $name, $role, (int)$active, $guruId, $siswaId, $photoPath ?: null, $userId]);
            }
            if (($replacePhoto || $removePhoto) && !empty($old['foto_path'])) remove_user_photo($old['foto_path']);
            if ($userId === (int)$_SESSION['user_id']) {
                $_SESSION['full_name'] = $name;
                $_SESSION['profile_photo'] = $photoPath ?: null;
                if ($password !== '') $_SESSION['password_must_change'] = 1;
            }
            record_activity($pdo, 'ubah pengguna', 'users', $userId, $role);
            $success = 'Akun pengguna berhasil diperbarui.';
        } else {
            throw new InvalidArgumentException('Tindakan tidak dikenal.');
        }
    } catch (InvalidArgumentException $e) {
        if ($newPhoto !== null) remove_user_photo($newPhoto);
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($newPhoto !== null) remove_user_photo($newPhoto);
        $error = 'Akun gagal disimpan. Pastikan username belum digunakan.';
    }
    if ($success !== '') {
        flash($success);
        $returnParams=$_GET;unset($returnParams['page'],$returnParams['export']);
        redirect('users.php'.($returnParams?'?'.http_build_query($returnParams):''));
    }
}

$query = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$filterRole = (string)($_GET['role'] ?? '');
$filterStatus = (string)($_GET['status'] ?? '');
$filterProfile = (string)($_GET['profil'] ?? '');
$filterClass = (string)($_GET['kelas'] ?? '');
$filterPassword = (string)($_GET['password'] ?? '') === 'change' ? 'change' : '';
$filterLogin = (string)($_GET['login'] ?? '') === 'never' ? 'never' : '';
$sort = (string)($_GET['sort'] ?? 'newest');
$sortOptions = ['newest'=>'u.id DESC','name'=>'u.nama_lengkap ASC','last_login'=>'u.last_login_at DESC,u.nama_lengkap ASC','role'=>'u.role ASC,u.nama_lengkap ASC'];
if (!isset($sortOptions[$sort])) $sort = 'newest';
$where = []; $params = [];
if ($query !== '') { $where[] = '(u.username LIKE ? OR u.nama_lengkap LIKE ? OR g.nama LIKE ? OR s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR g.nip LIKE ?)'; for ($i=0;$i<7;$i++) $params[] = '%' . $query . '%'; }
if (isset($roles[$filterRole])) { $where[] = 'u.role=?'; $params[] = $filterRole; } else $filterRole = '';
if (in_array($filterStatus, ['0','1'], true)) { $where[] = 'u.aktif=?'; $params[] = (int)$filterStatus; } else $filterStatus = '';
if ($filterProfile === 'unlinked') $where[] = "((u.role='guru' AND u.guru_id IS NULL) OR (u.role='siswa' AND u.siswa_id IS NULL))";
elseif ($filterProfile === 'linked') $where[] = "((u.role='guru' AND u.guru_id IS NOT NULL) OR (u.role='siswa' AND u.siswa_id IS NOT NULL))";
else $filterProfile = '';
if ($filterClass !== '' && ctype_digit($filterClass)) { $classExists=$pdo->prepare('SELECT 1 FROM kelas WHERE id=?');$classExists->execute([(int)$filterClass]);if($classExists->fetchColumn())$where[]="u.role='siswa' AND s.kelas_id=?";else $filterClass='';if($filterClass!=='')$params[]=(int)$filterClass; } else $filterClass='';
if ($filterPassword === 'change') $where[] = 'u.password_must_change=1';
if ($filterLogin === 'never') $where[] = 'u.last_login_at IS NULL';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stats = $pdo->query("SELECT COUNT(*) total, SUM(aktif=1) active_count, SUM(aktif=0) inactive_count, SUM(password_must_change=1) must_change_count, SUM(last_login_at IS NULL) never_login_count, SUM((role='guru' AND guru_id IS NULL) OR (role='siswa' AND siswa_id IS NULL)) unlinked_count FROM users")->fetch() ?: [];
$countStmt=$pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id'.$whereSql);$countStmt->execute($params);$filteredTotal=(int)$countStmt->fetchColumn();
$perPage=25;$page=max(1,(int)($_GET['page']??1));$pageCount=max(1,(int)ceil($filteredTotal/$perPage));$page=min($page,$pageCount);$offset=($page-1)*$perPage;
$listSql = 'SELECT u.id,u.username,u.nama_lengkap,u.role,u.aktif,u.created_at,u.last_login_at,u.guru_id,u.siswa_id,u.foto_path,u.password_must_change,g.nama AS guru_nama,g.nip,s.nama AS siswa_nama,s.nis,s.nisn FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id' . $whereSql . ' ORDER BY '.$sortOptions[$sort].' LIMIT '.$perPage.' OFFSET '.$offset;
$listStmt = $pdo->prepare($listSql); $listStmt->execute($params); $users = $listStmt->fetchAll();
if (($_GET['export'] ?? '') === 'csv') {
    $exportStmt=$pdo->prepare('SELECT u.nama_lengkap,u.username,u.role,u.aktif,COALESCE(g.nama,s.nama,\'\') AS profil,u.last_login_at,u.created_at FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id'.$whereSql.' ORDER BY '.$sortOptions[$sort]);$exportStmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="sims-pengguna-'.date('Ymd-His').'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Nama','Username','Peran','Status','Profil tertaut','Login terakhir','Dibuat']);
    foreach($exportStmt as $line){$line=array_map(static function($value){$v=(string)($value??'');return preg_match('/^[=+@\\-\\t\\r]/',$v)?"'".$v:$v;},$line);fputcsv($out,$line);}fclose($out);exit;
}
$filterQuery=['q'=>$query,'role'=>$filterRole,'status'=>$filterStatus,'profil'=>$filterProfile,'kelas'=>$filterClass,'password'=>$filterPassword,'login'=>$filterLogin,'sort'=>$sort];
$pageTitle = 'Manajemen Pengguna';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Manajemen Pengguna</h1>
        <p>Kelola akun dan tautan profil sekolah dalam satu tempat. Password disimpan sebagai hash dan tidak dapat dilihat kembali.</p>
    </div>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <?php foreach ([['Semua akun',(int)($stats['total']??0),'','bi-people'],['Aktif',(int)($stats['active_count']??0),'status=1','bi-person-check'],['Nonaktif',(int)($stats['inactive_count']??0),'status=0','bi-person-x'],['Profil belum tertaut',(int)($stats['unlinked_count']??0),'profil=unlinked','bi-link-45deg'],['Perlu ganti password',(int)($stats['must_change_count']??0),'password=change','bi-key'],['Belum pernah login',(int)($stats['never_login_count']??0),'login=never','bi-clock'] ] as [$label,$value,$quick,$icon]): ?>
        <div class="col-6 col-lg-4 col-xxl-2"><a class="card p-3 h-100 text-decoration-none user-stat-card" href="users.php<?= $quick!==''?'?'.e($quick):'' ?>"><div class="d-flex justify-content-between align-items-center"><span class="small text-secondary"><?= e($label) ?></span><i class="bi <?= e($icon) ?> text-primary"></i></div><strong class="fs-3 mt-2"><?= number_format($value,0,',','.') ?></strong></a></div>
    <?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card p-3 p-md-4">
            <h2 class="h5 mb-3">Tambah Akun</h2>
            <form method="post" enctype="multipart/form-data" class="row g-3 user-account-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                <div class="col-12"><label class="form-label">Nama Lengkap</label><input class="form-control" name="nama_lengkap" maxlength="120" required></div>
                <div class="col-12"><label class="form-label">Username</label><input class="form-control" name="username" maxlength="60" required></div>
                <div class="col-12"><label class="form-label">Peran</label><select class="form-select user-role-select" name="role" required><?php foreach ($createRoles as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select><div class="form-text">Akun Super Admin hanya dibuat pada proses instalasi.</div></div>
                <div class="col-12 profile-link-guru"><label class="form-label">Tautkan ke Data Guru</label><select class="form-select" name="guru_id"><option value="">Pilih guru</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>"><?= e($teacher['nama']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12 profile-link-siswa"><label class="form-label">Tautkan ke Data Siswa</label><select class="form-select" name="siswa_id"><option value="">Pilih siswa</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['nama']) ?> · <?= e($student['nis']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Pasfoto (opsional, JPG/PNG/WebP maks. 2 MB)</label><input class="form-control" type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
                <div class="col-12"><label class="form-label">Password (min. 10 karakter)</label><input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" required></div>
                <div class="col-12"><button class="btn btn-primary">Buat Akun</button></div>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><h2 class="h5 mb-0">Daftar Akun</h2><div class="small text-secondary">Menampilkan <?= $filteredTotal?($offset+1):0 ?>–<?= min($offset+$perPage,$filteredTotal) ?> dari <?= $filteredTotal ?></div></div>
            <form method="get" class="row g-2 my-3"><?php if($filterPassword): ?><input type="hidden" name="password" value="change"><?php endif; ?><?php if($filterLogin): ?><input type="hidden" name="login" value="never"><?php endif; ?><div class="col-12 col-md-6 col-xl-4"><label class="form-label small" for="userSearch">Cari akun/profil</label><input id="userSearch" class="form-control" name="q" value="<?= e($query) ?>" placeholder="Nama, username, NIS/NISN, NIP"></div><div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Peran</label><select class="form-select" name="role"><option value="">Semua peran</option><?php foreach ($roles as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterRole===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Status</label><select class="form-select" name="status"><option value="">Semua</option><option value="1" <?= $filterStatus==='1'?'selected':'' ?>>Aktif</option><option value="0" <?= $filterStatus==='0'?'selected':'' ?>>Nonaktif</option></select></div><div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Tautan profil</label><select class="form-select" name="profil"><option value="">Semua profil</option><option value="linked" <?= $filterProfile==='linked'?'selected':'' ?>>Sudah tertaut</option><option value="unlinked" <?= $filterProfile==='unlinked'?'selected':'' ?>>Belum tertaut</option></select></div><div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Kelas siswa</label><select class="form-select" name="kelas"><option value="">Semua kelas</option><?php foreach($classOptions as $classOption): ?><option value="<?= (int)$classOption['id'] ?>" <?= $filterClass===(string)$classOption['id']?'selected':'' ?>><?= e($classOption['nama_kelas']) ?></option><?php endforeach; ?></select></div><div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Urutkan</label><select class="form-select" name="sort"><option value="newest" <?= $sort==='newest'?'selected':'' ?>>Terbaru</option><option value="name" <?= $sort==='name'?'selected':'' ?>>Nama A–Z</option><option value="last_login" <?= $sort==='last_login'?'selected':'' ?>>Login terakhir</option><option value="role" <?= $sort==='role'?'selected':'' ?>>Peran</option></select></div><div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-outline-primary">Terapkan filter</button><a class="btn btn-outline-secondary" href="users.php">Reset</a><a class="btn btn-outline-success ms-md-auto" href="users.php?<?= e(http_build_query(array_merge($filterQuery,['export'=>'csv']))) ?>"><i class="bi bi-download me-1"></i>Ekspor hasil CSV</a></div></form>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Profil Tertaut</th>
                            <th>Peran</th>
                            <th>Status / Login Terakhir</th>
                            <th>Ubah Akun</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?><tr>
                                <td><?php if (!empty($user['foto_path'])): ?><img src="media/profile.php?id=<?= (int)$user['id'] ?>" alt="Pasfoto <?= e($user['nama_lengkap']) ?>" width="42" height="42" class="rounded-circle object-fit-cover me-2"><?php else: ?><span class="topbar-avatar me-2"><?= e(strtoupper(substr($user['nama_lengkap'],0,1))) ?></span><?php endif; ?><strong><?= e($user['nama_lengkap']) ?></strong><br><small class="text-secondary"><?= e($user['username']) ?></small></td>
                                <td><?= e($user['guru_nama'] ? 'Guru: '.$user['guru_nama'] : ($user['siswa_nama'] ? 'Siswa: '.$user['siswa_nama'] : '—')) ?><?php if($user['role']==='guru'&&!$user['guru_id']||$user['role']==='siswa'&&!$user['siswa_id']): ?><br><span class="badge text-bg-warning">Belum tertaut</span><?php endif; ?></td>
                                <td><?= e($roles[$user['role']] ?? $user['role']) ?></td>
                                <td><span class="badge text-bg-<?= (int)$user['aktif'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$user['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span><?php if((int)$user['password_must_change']===1): ?><br><span class="badge text-bg-warning mt-1">Ganti password</span><?php endif; ?><br><small class="text-secondary">Login: <?= e($user['last_login_at'] ?: 'Belum pernah') ?></small></td>
                                <td>
                                    <details>
                                        <summary class="btn btn-sm btn-outline-primary">Edit</summary>
                                        <form method="post" enctype="multipart/form-data" class="border rounded p-3 mt-2 user-account-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><label class="form-label">Nama</label><input class="form-control mb-2" name="nama_lengkap" value="<?= e($user['nama_lengkap']) ?>" required><label class="form-label">Username</label><input class="form-control mb-2" name="username" value="<?= e($user['username']) ?>" required><label class="form-label">Peran</label><select class="form-select mb-2 user-role-select" name="role"><?php foreach ($roles as $key => $label): ?><option value="<?= e($key) ?>" <?= $user['role'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                            <div class="profile-link-guru mb-2"><label class="form-label">Data Guru Tertaut</label><select class="form-select" name="guru_id"><option value="">Pilih guru</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" <?= (int)$user['guru_id']===(int)$teacher['id']?'selected':'' ?>><?= e($teacher['nama']) ?></option><?php endforeach; ?></select></div>
                                            <div class="profile-link-siswa mb-2"><label class="form-label">Data Siswa Tertaut</label><select class="form-select" name="siswa_id"><option value="">Pilih siswa</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>" <?= (int)$user['siswa_id']===(int)$student['id']?'selected':'' ?>><?= e($student['nama']) ?> · <?= e($student['nis']) ?></option><?php endforeach; ?></select></div>
                                            <?php if (!empty($user['foto_path'])): ?><img src="media/profile.php?id=<?= (int)$user['id'] ?>" alt="Pasfoto saat ini" width="64" height="64" class="rounded-circle object-fit-cover mb-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="hapus_foto" value="1" id="hapusFoto<?= (int)$user['id'] ?>"><label class="form-check-label" for="hapusFoto<?= (int)$user['id'] ?>">Hapus pasfoto</label></div><?php endif; ?><label class="form-label">Unggah / Ganti Pasfoto (JPG/PNG/WebP maks. 2 MB)</label><input class="form-control mb-2" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><label class="form-label">Password Baru (opsional)</label><input class="form-control mb-2" type="password" name="password" minlength="10" autocomplete="new-password"><div class="form-text mb-2">Jika direset, pengguna wajib mengganti password setelah login berikutnya.</div><label class="form-label">Status</label><select class="form-select mb-3" name="aktif">
                                                <option value="1" <?= (int)$user['aktif'] === 1 ? ' selected' : '' ?>>Aktif</option>
                                                <option value="0" <?= (int)$user['aktif'] === 0 ? ' selected' : '' ?>>Nonaktif</option>
                                            </select><button class="btn btn-sm btn-primary">Simpan</button></form>
                                    </details>
                                </td>
                            </tr><?php endforeach; ?>
                        <?php if (!$users): ?><tr>
                                                <td colspan="5" class="text-center text-secondary">Belum ada akun.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if($pageCount>1): ?><nav class="mt-3 no-print" aria-label="Halaman pengguna"><ul class="pagination pagination-sm flex-wrap mb-0"><?php for($p=max(1,$page-2);$p<=min($pageCount,$page+2);$p++): ?><li class="page-item<?= $p===$page?' active':'' ?>"><a class="page-link" href="users.php?<?= e(http_build_query(array_merge($filterQuery,['page'=>$p]))) ?>"><?= $p ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.user-account-form').forEach(form=>{const role=form.querySelector('.user-role-select');const sync=()=>{form.querySelectorAll('.profile-link-guru').forEach(el=>{el.hidden=role.value!=='guru';const input=el.querySelector('select');if(input)input.required=role.value==='guru'});form.querySelectorAll('.profile-link-siswa').forEach(el=>{el.hidden=role.value!=='siswa';const input=el.querySelector('select');if(input)input.required=role.value==='siswa'})};role.addEventListener('change',sync);sync()});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
