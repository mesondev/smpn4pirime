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
$biodataSchemaCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_pengguna'");
$biodataSchemaCheck->execute();
$biodataReady = (int)$biodataSchemaCheck->fetchColumn() === 1;
$teachers = $pdo->query("SELECT g.id,g.nama,g.nip,g.status,u.id AS linked_user_id,u.username AS linked_username FROM guru g LEFT JOIN users u ON u.guru_id=g.id WHERE g.status='Aktif' OR u.id IS NOT NULL ORDER BY g.nama")->fetchAll();
$students = $pdo->query("SELECT id,nis,nama FROM siswa WHERE status='Aktif' ORDER BY nama")->fetchAll();
$availableStudents = $pdo->query("SELECT s.id,s.nis,s.nama FROM siswa s LEFT JOIN users u ON u.siswa_id=s.id WHERE s.status='Aktif' AND u.id IS NULL ORDER BY s.nama")->fetchAll();
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
            $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');
            $role = (string)($_POST['role'] ?? '');
            $profileId = (int)($_POST[$role === 'guru' ? 'guru_id' : 'siswa_id'] ?? 0);
            if ($username === '' || strlen($username) > 60 || strlen($password) < 10 || !isset($createRoles[$role])) {
                throw new InvalidArgumentException('Username dan peran yang diizinkan wajib diisi; password minimal 10 karakter. Super Admin hanya dibuat saat instalasi.');
            }
            if ($password !== $passwordConfirmation) throw new InvalidArgumentException('Konfirmasi password tidak sama.');
            $guruId = $role === 'guru' ? $profileId : null;
            $siswaId = $role === 'siswa' ? $profileId : null;
            if ($role === 'guru' || $role === 'siswa') {
                if ($profileId < 1) throw new InvalidArgumentException('Pilih data profil yang akan ditautkan ke akun ini.');
                $check = $pdo->prepare($role === 'guru' ? "SELECT id,nama,nip FROM guru WHERE id=? AND status='Aktif'" : "SELECT id,nama,nis FROM siswa WHERE id=? AND status='Aktif'");
                $check->execute([$profileId]);
                $profile = $check->fetch();
                if (!$profile) throw new InvalidArgumentException('Profil yang dipilih tidak ditemukan atau tidak aktif.');
                if ($role === 'guru') {
                    $name = (string)$profile['nama'];
                    if (!empty($profile['nip']) && $username !== (string)$profile['nip']) throw new InvalidArgumentException('Username login akun Guru harus sama dengan NIP pada data profil.');
                } else {
                    $name = (string)$profile['nama'];
                    if ($username !== (string)$profile['nis']) throw new InvalidArgumentException('Username login akun Siswa harus sama dengan NIS pada data profil.');
                }
                $linkCheck = $pdo->prepare('SELECT id FROM users WHERE ' . ($role === 'guru' ? 'guru_id' : 'siswa_id') . '=? LIMIT 1');
                $linkCheck->execute([$profileId]);
                if ($linkCheck->fetchColumn()) throw new InvalidArgumentException('Profil ini sudah ditautkan ke akun lain.');
            }
            if ($name === '' || strlen($name) > 120) throw new InvalidArgumentException('Nama lengkap pada profil yang ditautkan tidak valid.');
            $usernameCheck = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            $usernameCheck->execute([$username]);
            if ($usernameCheck->fetchColumn()) throw new InvalidArgumentException('Username tersebut sudah digunakan. Coba username lain, misalnya tambahkan angka yang mudah Anda ingat.');
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
            if ($userId < 1 || $username === '' || strlen($username) > 60 || !isset($roles[$role]) || !in_array($active, ['0', '1'], true)) {
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
                if ($role === 'guru') {
                    $check = $pdo->prepare("SELECT id,nama,status FROM guru WHERE id=? AND (status='Aktif' OR id=(SELECT guru_id FROM users WHERE id=?))");
                    $check->execute([$profileId, $userId]);
                } else {
                    $check = $pdo->prepare("SELECT id,nama FROM siswa WHERE id=? AND status='Aktif'");
                    $check->execute([$profileId]);
                }
                $profile = $check->fetch();
                if (!$profile) throw new InvalidArgumentException('Profil yang dipilih tidak ditemukan atau tidak aktif.');
                if ($role === 'guru' && $active === '1' && $profile['status'] !== 'Aktif') throw new InvalidArgumentException('Profil guru masih nonaktif. Aktifkan data Guru terlebih dahulu sebelum mengaktifkan akun.');
                if ($role === 'guru') $name = (string)$profile['nama'];
                $linkCheck = $pdo->prepare('SELECT id FROM users WHERE ' . ($role === 'guru' ? 'guru_id' : 'siswa_id') . '=? AND id<>? LIMIT 1');
                $linkCheck->execute([$profileId, $userId]);
                if ($linkCheck->fetchColumn()) throw new InvalidArgumentException('Profil ini sudah ditautkan ke akun lain.');
            }
            if ($name === '' || strlen($name) > 120) throw new InvalidArgumentException('Nama lengkap pada profil yang ditautkan tidak valid.');
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
        } elseif ($action === 'toggle_active') {
            $userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $active = (string)($_POST['aktif'] ?? '');
            if ($userId < 1 || !in_array($active, ['0', '1'], true)) {
                throw new InvalidArgumentException('Permintaan aktivasi akun tidak valid.');
            }
            if ($userId === (int)$_SESSION['user_id'] && $active === '0') {
                throw new InvalidArgumentException('Akun Super Admin yang sedang digunakan tidak dapat dinonaktifkan sendiri.');
            }
            $pdo->beginTransaction();
            $accountStmt = $pdo->prepare('SELECT role,aktif,guru_id FROM users WHERE id=? LIMIT 1 FOR UPDATE');
            $accountStmt->execute([$userId]);
            $account = $accountStmt->fetch();
            if (!$account) throw new InvalidArgumentException('Akun yang akan diubah tidak ditemukan.');
            if ($active === '1' && $account['role'] === 'guru') {
                $teacherStatus = $pdo->prepare("SELECT status FROM guru WHERE id=?");
                $teacherStatus->execute([(int)($account['guru_id'] ?? 0)]);
                if ($teacherStatus->fetchColumn() !== 'Aktif') throw new InvalidArgumentException('Akun Guru belum dapat diaktifkan. Pastikan profil Guru tertaut ada dan berstatus Aktif.');
            }
            if ((string)$account['aktif'] === $active) {
                $success = $active === '1' ? 'Akun sudah aktif.' : 'Akun sudah nonaktif.';
                $pdo->commit();
            } else {
                if ($account['role'] === 'admin' && (int)$account['aktif'] === 1 && $active === '0') {
                    $activeAdmins = $pdo->query("SELECT id FROM users WHERE role='admin' AND aktif=1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
                    if (count($activeAdmins) <= 1) throw new InvalidArgumentException('Minimal harus ada satu akun Super Admin yang aktif.');
                }
                $activateStmt = $pdo->prepare('UPDATE users SET aktif=? WHERE id=?');
                $activateStmt->execute([(int)$active, $userId]);
                record_activity($pdo, $active === '1' ? 'aktifkan pengguna' : 'nonaktifkan pengguna', 'users', $userId, (string)$account['role']);
                $pdo->commit();
                $success = 'Akun ' . role_label((string)$account['role']) . ($active === '1' ? ' berhasil diaktifkan.' : ' berhasil dinonaktifkan.');
            }
        } else {
            throw new InvalidArgumentException('Tindakan tidak dikenal.');
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newPhoto !== null) remove_user_photo($newPhoto);
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newPhoto !== null) remove_user_photo($newPhoto);
        $error = 'Akun belum tersimpan. Username mungkin baru saja dipakai, atau profil sudah ditautkan oleh proses lain. Periksa username dan pilihan profil lalu coba kembali.';
    }
    if ($success !== '') {
        flash($success);
        $returnParams = $_GET;
        unset($returnParams['page'], $returnParams['export'], $returnParams['buat'], $returnParams['guru_id']);
        redirect('users.php' . ($returnParams ? '?' . http_build_query($returnParams) : ''));
    }
}

$query = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100, 'UTF-8'));
$filterRole = (string)($_GET['role'] ?? '');
$filterStatus = (string)($_GET['status'] ?? '');
$filterProfile = (string)($_GET['profil'] ?? '');
$filterBiodata = (string)($_GET['biodata'] ?? '');
$validBiodataStatuses = ['belum_lengkap', 'menunggu_verifikasi', 'terverifikasi', 'perlu_perbaikan', 'ditolak'];
if (!$biodataReady || !in_array($filterBiodata, $validBiodataStatuses, true)) $filterBiodata = '';
$filterClass = (string)($_GET['kelas'] ?? '');
$filterPassword = (string)($_GET['password'] ?? '') === 'change' ? 'change' : '';
$filterLogin = (string)($_GET['login'] ?? '') === 'never' ? 'never' : '';
$sort = (string)($_GET['sort'] ?? 'newest');
$sortOptions = ['newest' => 'u.id DESC', 'name' => 'u.nama_lengkap ASC', 'last_login' => 'u.last_login_at DESC,u.nama_lengkap ASC', 'role' => 'u.role ASC,u.nama_lengkap ASC'];
if (!isset($sortOptions[$sort])) $sort = 'newest';
$where = [];
$params = [];
if ($query !== '') {
    $where[] = '(u.username LIKE ? OR u.nama_lengkap LIKE ? OR g.nama LIKE ? OR s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR g.nip LIKE ?)';
    for ($i = 0; $i < 7; $i++) $params[] = '%' . $query . '%';
}
if (isset($roles[$filterRole])) {
    $where[] = 'u.role=?';
    $params[] = $filterRole;
} else $filterRole = '';
if (in_array($filterStatus, ['0', '1'], true)) {
    $where[] = 'u.aktif=?';
    $params[] = (int)$filterStatus;
} else $filterStatus = '';
if ($filterProfile === 'unlinked') $where[] = "((u.role='guru' AND u.guru_id IS NULL) OR (u.role='siswa' AND u.siswa_id IS NULL))";
elseif ($filterProfile === 'linked') $where[] = "((u.role='guru' AND u.guru_id IS NOT NULL) OR (u.role='siswa' AND u.siswa_id IS NOT NULL))";
else $filterProfile = '';
if ($filterBiodata !== '') {
    $where[] = "COALESCE(b.status_verifikasi,'belum_lengkap')=?";
    $params[] = $filterBiodata;
}
if ($filterClass !== '' && ctype_digit($filterClass)) {
    $classExists = $pdo->prepare('SELECT 1 FROM kelas WHERE id=?');
    $classExists->execute([(int)$filterClass]);
    if ($classExists->fetchColumn()) $where[] = "u.role='siswa' AND s.kelas_id=?";
    else $filterClass = '';
    if ($filterClass !== '') $params[] = (int)$filterClass;
} else $filterClass = '';
if ($filterPassword === 'change') $where[] = 'u.password_must_change=1';
if ($filterLogin === 'never') $where[] = 'u.last_login_at IS NULL';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stats = $pdo->query("SELECT COUNT(*) total, SUM(aktif=1) active_count, SUM(aktif=0) inactive_count, SUM(password_must_change=1) must_change_count, SUM(last_login_at IS NULL) never_login_count, SUM((role='guru' AND guru_id IS NULL) OR (role='siswa' AND siswa_id IS NULL)) unlinked_count FROM users")->fetch() ?: [];
$roleStats = array_fill_keys(array_keys($roles), ['total' => 0, 'active_count' => 0]);
foreach ($pdo->query('SELECT role,COUNT(*) AS total,SUM(aktif=1) AS active_count FROM users GROUP BY role')->fetchAll() as $roleRow) {
    if (isset($roleStats[$roleRow['role']])) $roleStats[$roleRow['role']] = ['total' => (int)$roleRow['total'], 'active_count' => (int)$roleRow['active_count']];
}
$largestRoleTotal = max(1, ...array_column($roleStats, 'total'));
$teacherAccountStats = $pdo->query("SELECT COUNT(*) AS active_teachers,SUM(u.id IS NOT NULL) AS linked_accounts,SUM(u.id IS NULL) AS without_accounts FROM guru g LEFT JOIN users u ON u.guru_id=g.id AND u.role='guru' WHERE g.status='Aktif'")->fetch() ?: [];
$biodataJoin = $biodataReady ? ' LEFT JOIN biodata_pengguna b ON b.user_id=u.id' : '';
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id' . $biodataJoin . $whereSql);
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$pageCount = max(1, (int)ceil($filteredTotal / $perPage));
$page = min($page, $pageCount);
$offset = ($page - 1) * $perPage;
$biodataSelect = $biodataReady ? "COALESCE(b.status_verifikasi,'belum_lengkap') AS biodata_status" : "'belum_aktif' AS biodata_status";
$listSql = 'SELECT u.id,u.username,u.nama_lengkap,u.role,u.aktif,u.created_at,u.last_login_at,u.guru_id,u.siswa_id,u.foto_path,u.password_must_change,g.nama AS guru_nama,g.nip AS guru_nip,g.status AS guru_status,s.nama AS siswa_nama,s.nis,s.nisn,' . $biodataSelect . ' FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id' . $biodataJoin . $whereSql . ' ORDER BY ' . $sortOptions[$sort] . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$users = $listStmt->fetchAll();
if (($_GET['export'] ?? '') === 'csv') {
    $exportStmt = $pdo->prepare('SELECT u.nama_lengkap,u.username,u.role,u.aktif,COALESCE(g.nama,s.nama,\'\') AS profil,u.last_login_at,u.created_at FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id' . $biodataJoin . $whereSql . ' ORDER BY ' . $sortOptions[$sort]);
    $exportStmt->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sims-pengguna-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Nama', 'Username', 'Peran', 'Status', 'Profil tertaut', 'Login terakhir', 'Dibuat']);
    foreach ($exportStmt as $line) {
        $line = array_map(static function ($value) {
            $v = (string)($value ?? '');
            return preg_match('/^[=+@\\-\\t\\r]/', $v) ? "'" . $v : $v;
        }, $line);
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
$filterQuery = ['q' => $query, 'role' => $filterRole, 'status' => $filterStatus, 'profil' => $filterProfile, 'biodata' => $filterBiodata, 'kelas' => $filterClass, 'password' => $filterPassword, 'login' => $filterLogin, 'sort' => $sort];
$pageTitle = 'Manajemen Pengguna';
require __DIR__ . '/includes/header.php';
?>
<div class="dashboard-heading">
    <div>
        <h1>Manajemen Pengguna</h1>
        <p>Akun login ditautkan ke data induk Guru atau Siswa yang sudah dibuat. Profil sekolah tetap menjadi sumber data identitas.</p>
    </div>
    <a class="btn btn-outline-primary" href="index.php?module=guru"><i class="bi bi-person-badge me-2"></i>Buka Data Guru</a>
</div>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <?php foreach ([['Semua akun', (int)($stats['total'] ?? 0), '', 'bi-people'], ['Aktif', (int)($stats['active_count'] ?? 0), 'status=1', 'bi-person-check'], ['Nonaktif', (int)($stats['inactive_count'] ?? 0), 'status=0', 'bi-person-x'], ['Profil belum tertaut', (int)($stats['unlinked_count'] ?? 0), 'profil=unlinked', 'bi-link-45deg'], ['Perlu ganti password', (int)($stats['must_change_count'] ?? 0), 'password=change', 'bi-key'], ['Belum pernah login', (int)($stats['never_login_count'] ?? 0), 'login=never', 'bi-clock']] as [$label, $value, $quick, $icon]): ?>
        <div class="col-6 col-lg-4 col-xxl-2"><a class="card p-3 h-100 text-decoration-none user-stat-card" href="users.php<?= $quick !== '' ? '?' . e($quick) : '' ?>">
                <div class="d-flex justify-content-between align-items-center"><span class="small text-secondary"><?= e($label) ?></span><i class="bi <?= e($icon) ?> text-primary"></i></div><strong class="fs-3 mt-2"><?= number_format($value, 0, ',', '.') ?></strong>
            </a></div>
    <?php endforeach; ?>
</div>
<div class="row g-3 mb-4" aria-label="Ringkasan akun guru">
    <?php foreach ([['Guru aktif', (int)($teacherAccountStats['active_teachers'] ?? 0), 'bi-person-badge'], ['Sudah memiliki akun', (int)($teacherAccountStats['linked_accounts'] ?? 0), 'bi-person-check'], ['Belum memiliki akun', (int)($teacherAccountStats['without_accounts'] ?? 0), 'bi-person-plus']] as [$label, $value, $icon]): ?>
        <div class="col-12 col-md-4">
            <div class="card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between gap-2"><span class="small text-secondary"><?= e($label) ?></span><i class="bi <?= e($icon) ?> text-primary fs-5"></i></div><strong class="fs-3 mt-2"><?= number_format($value, 0, ',', '.') ?></strong><?php if ($label === 'Belum memiliki akun' && $value > 0): ?><a class="small mt-2" href="users.php?buat=guru#buat-akun">Buat akun Guru <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<section class="card p-3 p-md-4 mb-4" aria-labelledby="account-distribution-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1" id="account-distribution-title">Grafik Statistik Akun</h2>
            <p class="small text-secondary mb-0">Distribusi jumlah akun per peran; warna menunjukkan status akun.</p>
        </div>
        <div class="d-flex flex-wrap gap-3 small"><span><i class="bi bi-square-fill text-success me-1" aria-hidden="true"></i>Aktif</span><span><i class="bi bi-square-fill text-secondary me-1" aria-hidden="true"></i>Nonaktif</span></div>
    </div>
    <div class="d-grid gap-3">
        <?php foreach ($roles as $roleKey => $roleLabel): $roleTotal = $roleStats[$roleKey]['total'];
            $roleActive = $roleStats[$roleKey]['active_count'];
            $roleInactive = max(0, $roleTotal - $roleActive);
            $activeWidth = round($roleActive * 100 / $largestRoleTotal, 2);
            $inactiveWidth = round($roleInactive * 100 / $largestRoleTotal, 2); ?>
            <div class="user-role-chart-row">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1"><span class="small fw-semibold"><?= e($roleLabel) ?></span><span class="small text-secondary"><?= number_format($roleTotal, 0, ',', '.') ?> akun · <?= number_format($roleActive, 0, ',', '.') ?> aktif</span></div>
                <div class="progress user-role-progress" role="img" aria-label="<?= e($roleLabel) ?>: <?= $roleActive ?> akun aktif dan <?= $roleInactive ?> akun nonaktif">
                    <div class="progress-bar bg-success" style="width:<?= $activeWidth ?>%" aria-hidden="true"></div>
                    <div class="progress-bar bg-secondary" style="width:<?= $inactiveWidth ?>%" aria-hidden="true"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>


<details class="card user-management-guide mb-4">
    <summary><i class="bi bi-journal-text me-2" aria-hidden="true"></i>Panduan akun dan laporan pengembangan</summary>
    <div class="user-guide-content">
        <section>
            <h2 class="h6">Cara membuat akun</h2>
            <ol>
                <li>Buat atau perbarui profil terlebih dahulu melalui <strong>Data Guru</strong> atau <strong>Data Siswa</strong>. Menu ini membuat akun login, bukan membuat data induk guru baru.</li>
                <li>Isi identitas login unik: NIS untuk Siswa, NIP untuk Guru/Kepala Sekolah, dan ID Operator untuk Operator. Nilai tersebut dipakai sebagai username; password tetap rahasia dan terpisah.</li>
                <li>Untuk peran Guru atau Siswa, pilih profil yang benar. Jenis kelamin mengikuti data profil Guru/Siswa agar data tidak rangkap.</li>
                <li>Buat kata sandi awal minimal 10 karakter dan ulangi konfirmasinya, lalu sampaikan secara pribadi kepada pemilik akun.</li>
                <li>Pengguna wajib mengganti kata sandi awal setelah login pertama. Kata sandi tersimpan sebagai hash dan tidak dapat dilihat kembali.</li>
            </ol>
            <p class="mb-0"><strong>Alur yang disarankan:</strong> Data Guru → Manajemen Pengguna → buat akun dengan peran Guru → tautkan profil → serahkan kredensial awal secara pribadi.</p>
            <p class="mb-0"><strong>Peran yang tersedia:</strong> Super Admin, Kepala Sekolah, Guru, Operator, dan Siswa. Super Admin tidak dibuat melalui formulir ini.</p>
        </section>
        <section>
            <h2 class="h6">Status fitur dan rekomendasi</h2>
            <ul class="mb-0">
                <li><strong>Sudah tersedia:</strong> pembuatan akun oleh Super Admin, konfirmasi password awal, tautan profil Guru/Siswa, kewajiban ganti password, pencarian/filter akun, serta audit tindakan penting.</li>
                <li><strong>Alur Operator:</strong> dapat memeriksa biodata akun Siswa/Guru/Kepala Sekolah dan menerbitkan password sementara untuk peran tersebut. Operator tidak membuat akun, mengubah role, atau mengatur akun Operator/Super Admin.</li>
                <li><strong>Persetujuan:</strong> setelah migrasi biodata aktif, akun non-admin hanya dapat membuka dashboard setelah biodata disetujui. Akun yang menunggu atau diminta memperbaiki akan diarahkan ke halaman biodata.</li>
                <li><strong>Siklus akun Guru:</strong> perubahan nama pada Data Guru ikut memperbarui nama akun. Jika profil Guru dinonaktifkan, akun tertaut ikut dinonaktifkan. Setelah profil diaktifkan kembali, Super Admin perlu memeriksa lalu mengaktifkan akunnya secara manual.</li>
                <li><strong>Belum tersedia:</strong> kolom email akun SIMS, notifikasi email aktivasi, dan pemulihan password melalui email. Reset Operator saat ini menghasilkan password sementara yang harus disampaikan secara pribadi.</li>
                <li><strong>Peran Staf:</strong> belum menjadi peran tersendiri. Tetapkan tugas serta batas aksesnya terlebih dahulu; jangan menyamakan Staf dengan Operator tanpa keputusan sekolah.</li>
                <li><strong>Prioritas lanjutan:</strong> integrasi email terverifikasi dan tautan pemulihan sekali pakai dengan masa berlaku, serta uji hak akses tiap peran pada staging sebelum penggunaan produksi.</li>
            </ul>
        </section>
    </div>
</details>
<div class="row g-4">
    <div class="col-12">
        <div class="card p-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h5 mb-0">Daftar Akun</h2>
                <div class="small text-secondary">Menampilkan <?= $filteredTotal ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $filteredTotal) ?> dari <?= $filteredTotal ?></div>
            </div>
            <form method="get" class="row g-2 my-3"><?php if ($filterPassword): ?><input type="hidden" name="password" value="change"><?php endif; ?><?php if ($filterLogin): ?><input type="hidden" name="login" value="never"><?php endif; ?><div class="col-12 col-md-6 col-xl-4"><label class="form-label small" for="userSearch">Cari akun/profil</label><input id="userSearch" class="form-control" name="q" value="<?= e($query) ?>" placeholder="Nama, username, NIS/NISN, NIP"></div>
                <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Peran</label><select class="form-select" name="role">
                        <option value="">Semua peran</option><?php foreach ($roles as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterRole === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Status</label><select class="form-select" name="status">
                        <option value="">Semua</option>
                        <option value="1" <?= $filterStatus === '1' ? 'selected' : '' ?>>Aktif</option>
                        <option value="0" <?= $filterStatus === '0' ? 'selected' : '' ?>>Nonaktif</option>
                    </select></div>
                <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Tautan profil</label><select class="form-select" name="profil">
                        <option value="">Semua profil</option>
                        <option value="linked" <?= $filterProfile === 'linked' ? 'selected' : '' ?>>Sudah tertaut</option>
                        <option value="unlinked" <?= $filterProfile === 'unlinked' ? 'selected' : '' ?>>Belum tertaut</option>
                    </select></div>
                <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Kelas siswa</label><select class="form-select" name="kelas">
                        <option value="">Semua kelas</option><?php foreach ($classOptions as $classOption): ?><option value="<?= (int)$classOption['id'] ?>" <?= $filterClass === (string)$classOption['id'] ? 'selected' : '' ?>><?= e($classOption['nama_kelas']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-6 col-md-3 col-xl-2"><label class="form-label small">Urutkan</label><select class="form-select" name="sort">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nama A–Z</option>
                        <option value="last_login" <?= $sort === 'last_login' ? 'selected' : '' ?>>Login terakhir</option>
                        <option value="role" <?= $sort === 'role' ? 'selected' : '' ?>>Peran</option>
                    </select></div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-2"><button class="btn btn-outline-primary">Terapkan filter</button><a class="btn btn-outline-secondary" href="users.php">Reset</a>
                    <div class="d-flex flex-wrap gap-2 ms-auto"><button class="btn btn-primary" type="button" id="toggleAccountCreate" data-bs-toggle="modal" data-bs-target="#buat-akun" aria-controls="buat-akun" aria-expanded="false"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah Akun</button><a class="btn btn-outline-success" href="users.php?<?= e(http_build_query(array_merge($filterQuery, ['export' => 'csv']))) ?>"><i class="bi bi-download me-1"></i>Ekspor hasil CSV</a></div>
                </div>
            </form>
            <?php if ($biodataReady): ?><form method="get" class="row g-2 align-items-end mb-3"><input type="hidden" name="q" value="<?= e($query) ?>"><input type="hidden" name="role" value="<?= e($filterRole) ?>"><input type="hidden" name="status" value="<?= e($filterStatus) ?>"><input type="hidden" name="profil" value="<?= e($filterProfile) ?>"><input type="hidden" name="kelas" value="<?= e($filterClass) ?>"><input type="hidden" name="password" value="<?= e($filterPassword) ?>"><input type="hidden" name="login" value="<?= e($filterLogin) ?>"><input type="hidden" name="sort" value="<?= e($sort) ?>">
                    <div class="col-12 col-sm-6 col-md-4"><label class="form-label small" for="filterBiodata">Status persetujuan biodata</label><select class="form-select" id="filterBiodata" name="biodata">
                            <option value="">Semua status biodata</option><?php foreach (['belum_lengkap' => 'Belum lengkap', 'menunggu_verifikasi' => 'Menunggu verifikasi', 'terverifikasi' => 'Terverifikasi', 'perlu_perbaikan' => 'Perlu perbaikan', 'ditolak' => 'Ditolak'] as $statusKey => $statusLabel): ?><option value="<?= e($statusKey) ?>" <?= $filterBiodata === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-auto"><button class="btn btn-outline-primary">Terapkan status biodata</button></div>
                </form><?php endif; ?>
            <div class="modal fade user-create-modal" id="buat-akun" tabindex="-1" aria-labelledby="createAccountTitle" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="post" enctype="multipart/form-data" class="user-account-form" id="createUserForm">
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title fs-5" id="createAccountTitle">Tambah Akun Pengguna</h2>
                                    <p class="small text-secondary mb-0">Buat akun login dan tautkan profil sekolahnya.</p>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                                <div class="mb-3">
                                    <label class="form-label" for="newPhoto">Foto Profil <span class="text-secondary fw-normal">(opsional)</span></label>
                                    <label class="user-photo-upload" for="newPhoto"><i class="bi bi-image" aria-hidden="true"></i><span>Pilih foto</span><small>JPG, PNG, atau WebP · maksimal 2 MB</small></label>
                                    <input class="visually-hidden" id="newPhoto" type="file" name="foto" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div class="mb-3"><label class="form-label" for="newFullName">Nama Lengkap</label><input class="form-control account-name-input" id="newFullName" name="nama_lengkap" value="<?= e($_POST['nama_lengkap'] ?? '') ?>" maxlength="120" autocomplete="name" required>
                                    <div class="form-text account-name-help">Untuk akun Guru, nama mengikuti profil Guru yang ditautkan.</div>
                                </div>
                                <div class="mb-3"><label class="form-label" for="newUsername"><span id="newUsernameLabel">Username / NIS / NIP / ID Operator</span></label><input class="form-control" id="newUsername" name="username" value="<?= e($_POST['username'] ?? '') ?>" maxlength="60" autocomplete="username" placeholder="Masukkan identitas login yang unik" required>
                                    <div class="form-text" id="newUsernameHelp">Untuk Siswa gunakan NIS, Guru/Kepala Sekolah gunakan NIP, dan Operator gunakan ID Operator. Nilai ini menjadi username login; NIS/NIP tidak disimpan sebagai password.</div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6"><label class="form-label" for="newRole">Peran Pengguna</label><select class="form-select user-role-select" id="newRole" name="role" required><?php $requestedCreateRole = isset($createRoles[(string)($_POST['role'] ?? $_GET['buat'] ?? '')]) ? (string)($_POST['role'] ?? $_GET['buat']) : ''; ?><option value="" <?= $requestedCreateRole === '' ? 'selected' : '' ?> disabled>Pilih peran</option><?php foreach ($createRoles as $key => $label): ?><option value="<?= e($key) ?>" <?= $requestedCreateRole === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                                    <div class="col-sm-6"><label class="form-label">Status Akun</label>
                                        <div class="form-control bg-body-tertiary"><span class="badge text-bg-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Aktif</span><span class="small text-secondary ms-2">saat dibuat</span></div>
                                    </div>
                                </div>
                                <div class="mb-3 profile-link-guru"><label class="form-label" for="newGuruId">Profil Guru</label><select class="form-select" id="newGuruId" name="guru_id">
                                        <option value="">Pilih guru aktif yang belum memiliki akun</option><?php $requestedGuruId = (int)($_POST['guru_id'] ?? $_GET['guru_id'] ?? 0);
                                                                                                            foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" data-name="<?= e($teacher['nama']) ?>" <?= !empty($teacher['linked_user_id']) ? 'disabled' : '' ?> <?= $requestedGuruId === (int)$teacher['id'] ? 'selected' : '' ?>><?= e($teacher['nama']) ?><?= !empty($teacher['nip']) ? ' · NIP ' . $teacher['nip'] : '' ?><?= !empty($teacher['linked_user_id']) ? ' · sudah tertaut ke ' . $teacher['linked_username'] : '' ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Pilih profil aktif yang belum memiliki akun. Satu profil hanya dapat ditautkan ke satu akun.</div>
                                </div>
                                <div class="mb-3 profile-link-siswa"><label class="form-label" for="newStudentId">Profil Siswa</label><select class="form-select" id="newStudentId" name="siswa_id" aria-describedby="studentProfileHelp">
                                        <option value="">Pilih siswa aktif yang belum tertaut</option><?php foreach ($availableStudents as $student): ?><option value="<?= (int)$student['id'] ?>" <?= (int)($_POST['siswa_id'] ?? 0) === (int)$student['id'] ? 'selected' : '' ?>><?= e($student['nama']) ?> · <?= e($student['nis']) ?></option><?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="studentProfileHelp">Pilih data induk siswa yang akan dipakai untuk akun ini. Satu profil hanya dapat digunakan oleh satu akun.</div><?php if (!$availableStudents): ?><div class="alert alert-warning py-2 mt-2 mb-0" id="studentProfileEmpty" role="status"><strong>Tidak ada profil siswa aktif yang tersedia untuk ditautkan.</strong> Tambahkan atau aktifkan data siswa melalui <a href="index.php?module=siswa" target="_blank" rel="noopener">Data Siswa</a>. Jika profilnya sudah dipakai akun lain, buka akun tersebut pada daftar pengguna.</div><?php endif; ?>
                                </div>
                                <div class="mb-3"><label class="form-label" for="newPassword">Kata Sandi Awal</label><input class="form-control" id="newPassword" type="password" name="password" minlength="10" autocomplete="new-password" placeholder="Minimal 10 karakter" required>
                                    <div class="form-text">Gunakan password awal yang kuat. Pengguna diminta mengganti password setelah login pertama.</div>
                                </div>
                                <div class="mb-1"><label class="form-label" for="newPasswordConfirmation">Konfirmasi Kata Sandi</label><input class="form-control" id="newPasswordConfirmation" type="password" name="password_confirmation" minlength="10" autocomplete="new-password" placeholder="Ulangi kata sandi awal" required></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Buat Akun</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Profil Tertaut</th>
                            <th>Peran</th>
                            <th>Status Biodata</th>
                            <th>Status / Login Terakhir</th>
                            <th>Ubah Akun</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?><tr>
                                <td><?php if (!empty($user['foto_path'])): ?><img src="media/profile.php?id=<?= (int)$user['id'] ?>" alt="Pasfoto <?= e($user['nama_lengkap']) ?>" width="42" height="42" class="rounded-circle object-fit-cover me-2"><?php else: ?><span class="topbar-avatar me-2"><?= e(strtoupper(substr($user['nama_lengkap'], 0, 1))) ?></span><?php endif; ?><strong><?= e($user['nama_lengkap']) ?></strong><br><small class="text-secondary"><?= e($user['username']) ?></small></td>
                                <td><?= e($user['guru_nama'] ? 'Guru: ' . $user['guru_nama'] . ($user['guru_nip'] ? ' · NIP ' . $user['guru_nip'] : '') : ($user['siswa_nama'] ? 'Siswa: ' . $user['siswa_nama'] : '—')) ?><?php if ($user['role'] === 'guru' && !$user['guru_id'] || $user['role'] === 'siswa' && !$user['siswa_id']): ?><br><span class="badge text-bg-warning">Belum tertaut</span><?php elseif ($user['role'] === 'guru' && ($user['guru_status'] ?? '') !== 'Aktif'): ?><br><span class="badge text-bg-secondary">Profil guru nonaktif</span><?php endif; ?></td>
                                <td><?= e($roles[$user['role']] ?? $user['role']) ?></td>
                                <td><?php $biodataLabels = ['belum_lengkap' => 'Belum lengkap', 'menunggu_verifikasi' => 'Menunggu verifikasi', 'terverifikasi' => 'Disetujui', 'perlu_perbaikan' => 'Perlu perbaikan', 'ditolak' => 'Ditolak']; ?><span class="badge text-bg-<?= $user['biodata_status'] === 'terverifikasi' ? 'success' : ($user['biodata_status'] === 'menunggu_verifikasi' ? 'warning' : (in_array($user['biodata_status'], ['perlu_perbaikan', 'ditolak'], true) ? 'danger' : 'secondary')) ?>"><?= $user['biodata_status'] === 'belum_aktif' ? 'Belum aktif' : e($biodataLabels[$user['biodata_status']] ?? 'Belum lengkap') ?></span><?php if ($biodataReady): ?><br><a class="small" href="biodata.php?user=<?= (int)$user['id'] ?>">Periksa biodata</a><?php endif; ?></td>
                                <td><span class="badge text-bg-<?= (int)$user['aktif'] === 1 ? 'success' : 'secondary' ?>"><?= (int)$user['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span><?php if ((int)$user['password_must_change'] === 1): ?><br><span class="badge text-bg-warning mt-1">Ganti password</span><?php endif; ?><br><small class="text-secondary">Login: <?= e($user['last_login_at'] ?: 'Belum pernah') ?></small></td>
                                <td>
                                    <form method="post" class="d-inline-block mb-2" onsubmit="return confirm('<?= (int)$user['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> akun ini?')">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                        <input type="hidden" name="aktif" value="<?= (int)$user['aktif'] === 1 ? '0' : '1' ?>">
                                        <button type="submit" class="btn btn-sm <?= (int)$user['aktif'] === 1 ? 'btn-outline-danger' : 'btn-success' ?>">
                                            <i class="bi <?= (int)$user['aktif'] === 1 ? 'bi-person-x' : 'bi-person-check' ?> me-1" aria-hidden="true"></i><?= (int)$user['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <details>
                                        <summary class="btn btn-sm btn-outline-primary">Edit</summary>
                                        <form method="post" enctype="multipart/form-data" class="border rounded p-3 mt-2 user-account-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><label class="form-label">Nama</label><input class="form-control mb-2 account-name-input" name="nama_lengkap" value="<?= e($user['nama_lengkap']) ?>" required>
                                            <div class="form-text account-name-help mb-2">Untuk akun Guru, nama mengikuti profil Guru yang ditautkan.</div><label class="form-label">Username</label><input class="form-control mb-2" name="username" value="<?= e($user['username']) ?>" required><label class="form-label">Peran</label><select class="form-select mb-2 user-role-select" name="role"><?php foreach ($roles as $key => $label): ?><option value="<?= e($key) ?>" <?= $user['role'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                            <div class="profile-link-guru mb-2"><label class="form-label">Data Guru Tertaut</label><select class="form-select" name="guru_id">
                                                    <option value="">Pilih guru aktif</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id'] ?>" data-name="<?= e($teacher['nama']) ?>" <?= (int)$user['guru_id'] === (int)$teacher['id'] ? 'selected' : '' ?> <?= !empty($teacher['linked_user_id']) && (int)$teacher['linked_user_id'] !== (int)$user['id'] ? 'disabled' : '' ?>><?= e($teacher['nama']) ?><?= !empty($teacher['nip']) ? ' · NIP ' . $teacher['nip'] : '' ?><?= $teacher['status'] !== 'Aktif' ? ' · nonaktif' : '' ?><?= !empty($teacher['linked_username']) && (int)$teacher['linked_user_id'] !== (int)$user['id'] ? ' · sudah tertaut' : '' ?></option><?php endforeach; ?>
                                                </select>
                                                <div class="form-text">Pilih profil Guru yang sesuai. Satu profil hanya untuk satu akun.</div>
                                            </div>
                                            <div class="profile-link-siswa mb-2"><label class="form-label">Data Siswa Tertaut</label><select class="form-select" name="siswa_id">
                                                    <option value="">Pilih siswa</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>" <?= (int)$user['siswa_id'] === (int)$student['id'] ? 'selected' : '' ?>><?= e($student['nama']) ?> · <?= e($student['nis']) ?></option><?php endforeach; ?>
                                                </select></div>
                                            <?php if (!empty($user['foto_path'])): ?><img src="media/profile.php?id=<?= (int)$user['id'] ?>" alt="Pasfoto saat ini" width="64" height="64" class="rounded-circle object-fit-cover mb-2">
                                                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="hapus_foto" value="1" id="hapusFoto<?= (int)$user['id'] ?>"><label class="form-check-label" for="hapusFoto<?= (int)$user['id'] ?>">Hapus pasfoto</label></div><?php endif; ?><label class="form-label">Unggah / Ganti Pasfoto (JPG/PNG/WebP maks. 2 MB)</label><input class="form-control mb-2" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><label class="form-label">Password Baru (opsional)</label><input class="form-control mb-2" type="password" name="password" minlength="10" autocomplete="new-password">
                                            <div class="form-text mb-2">Jika direset, pengguna wajib mengganti password setelah login berikutnya.</div><label class="form-label">Status</label><select class="form-select mb-3" name="aktif">
                                                <option value="1" <?= (int)$user['aktif'] === 1 ? ' selected' : '' ?>>Aktif</option>
                                                <option value="0" <?= (int)$user['aktif'] === 0 ? ' selected' : '' ?>>Nonaktif</option>
                                            </select><button class="btn btn-sm btn-primary">Simpan</button>
                                        </form>
                                    </details>
                                </td>
                            </tr><?php endforeach; ?>
                        <?php if (!$users): ?><tr>
                                <td colspan="6" class="text-center text-secondary">Belum ada akun.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pageCount > 1): ?><nav class="mt-3 no-print" aria-label="Halaman pengguna">
                    <ul class="pagination pagination-sm flex-wrap mb-0"><?php for ($p = max(1, $page - 2); $p <= min($pageCount, $page + 2); $p++): ?><li class="page-item<?= $p === $page ? ' active' : '' ?>"><a class="page-link" href="users.php?<?= e(http_build_query(array_merge($filterQuery, ['page' => $p]))) ?>"><?= $p ?></a></li><?php endfor; ?></ul>
                </nav><?php endif; ?>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('.user-account-form').forEach(form => {
        const role = form.querySelector('.user-role-select');
        const name = form.querySelector('.account-name-input');
        const guru = form.querySelector('[name="guru_id"]');
        const student = form.querySelector('[name="siswa_id"]');
        const submit = form.querySelector('button[type="submit"]');
        const nameHelp = form.querySelector('.account-name-help');
        const sync = () => {
            form.querySelectorAll('.profile-link-guru').forEach(el => {
                el.hidden = role.value !== 'guru';
                const input = el.querySelector('select');
                if (input) input.required = role.value === 'guru'
            });
            form.querySelectorAll('.profile-link-siswa').forEach(el => {
                el.hidden = role.value !== 'siswa';
                if (student) {
                    const hasStudents = student.options.length > 1;
                    student.disabled = role.value === 'siswa' && !hasStudents;
                    student.required = role.value === 'siswa' && hasStudents;
                    student.setCustomValidity(role.value === 'siswa' && hasStudents && !student.value ? 'Pilih profil siswa aktif sebelum membuat akun.' : '');
                    if (submit && form.id === 'createUserForm') submit.disabled = role.value === 'siswa' && !hasStudents
                }
            });
            if (name) {
                const linked = role.value === 'guru';
                name.readOnly = linked;
                if (linked && guru?.selectedOptions[0]?.dataset.name) name.value = guru.selectedOptions[0].dataset.name;
                if (nameHelp) nameHelp.hidden = !linked
            }
        };
        role.addEventListener('change', sync);
        guru?.addEventListener('change', sync);
        student?.addEventListener('change', sync);
        sync()
    });
    const newUserRole = document.getElementById('newRole');
    const identityLabel = document.getElementById('newUsernameLabel');
    const identityHelp = document.getElementById('newUsernameHelp');
    const syncIdentity = () => {
        if (!newUserRole || !identityLabel) return;
        const role = newUserRole.value;
        identityLabel.textContent = role === 'siswa' ? 'NIS (Username Login)' : (role === 'guru' || role === 'kepala_sekolah' ? 'NIP (Username Login)' : (role === 'operator' ? 'ID Operator (Username Login)' : 'Username / NIS / NIP / ID Operator'));
        if (identityHelp) identityHelp.textContent = role === 'siswa' ? 'Masukkan NIS yang sesuai dengan profil siswa tertaut. NIS digunakan sebagai username; password tetap dibuat terpisah.' : (role === 'guru' || role === 'kepala_sekolah' ? 'Masukkan NIP yang sesuai dengan data kepegawaian. NIP digunakan sebagai username; password tetap dibuat terpisah.' : (role === 'operator' ? 'Masukkan ID Operator yang unik. ID ini digunakan sebagai username; password tetap dibuat terpisah.' : 'Gunakan identitas login unik sesuai peran akun.'))
    };
    newUserRole?.addEventListener('change', syncIdentity);
    syncIdentity();
    const createAccountModal = document.getElementById('buat-akun');
    const createAccountButton = document.getElementById('toggleAccountCreate');
    createAccountModal?.addEventListener('shown.bs.modal', () => {
        createAccountButton?.setAttribute('aria-expanded', 'true');
        createAccountModal.querySelector('#newFullName')?.focus({
            preventScroll: true
        })
    });
    createAccountModal?.addEventListener('hidden.bs.modal', () => createAccountButton?.setAttribute('aria-expanded', 'false'));
    if (<?= $error !== '' || isset($_GET['buat']) ? 'true' : 'false' ?>) {
        window.addEventListener('load', () => {
            if (window.bootstrap && createAccountModal) bootstrap.Modal.getOrCreateInstance(createAccountModal).show()
        })
    }
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>