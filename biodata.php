<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/database.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$role = current_role();
$isAdmin = $role === 'admin';
$isReviewer = in_array($role, ['admin', 'operator'], true);
$targetId = $isReviewer && isset($_GET['user']) && ctype_digit((string)$_GET['user']) ? (int)$_GET['user'] : $userId;
$error = '';
$success = '';
$newPhoto = null;
$tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_pengguna'");
$tableCheck->execute();
$schemaReady = (int)$tableCheck->fetchColumn() === 1;
$rejectionSchemaCheck = $pdo->prepare("SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='biodata_pengguna' AND column_name='status_verifikasi'");
$rejectionSchemaCheck->execute();
$rejectionReady = str_contains((string)$rejectionSchemaCheck->fetchColumn(), 'ditolak');

function biodata_required_fields(string $role): array
{
    return match ($role) {
        'siswa' => ['telepon', 'whatsapp', 'nama_kontak_darurat', 'telepon_kontak_darurat'],
        'guru' => ['telepon', 'whatsapp', 'tempat_lahir', 'tanggal_lahir', 'pendidikan'],
        default => ['telepon', 'whatsapp', 'tempat_lahir', 'tanggal_lahir', 'alamat'],
    };
}
function biodata_missing_fields(array $data, string $role): array
{
    $missing = [];
    foreach (biodata_required_fields($role) as $field) {
        if (trim((string)($data[$field] ?? '')) === '') $missing[] = $field;
    }
    return $missing;
}
function biodata_history_ready(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='biodata_review_history'");
    $stmt->execute();
    return (int)$stmt->fetchColumn() === 1;
}
$labels = [
    'telepon' => 'Nomor telepon',
    'whatsapp' => 'Nomor WhatsApp',
    'tempat_lahir' => 'Tempat lahir',
    'tanggal_lahir' => 'Tanggal lahir',
    'alamat' => 'Alamat',
    'pendidikan' => 'Pendidikan terakhir',
    'nama_kontak_darurat' => 'Nama orang tua/wali atau kontak darurat',
    'telepon_kontak_darurat' => 'Nomor orang tua/wali atau kontak darurat',
    'email' => 'Email siswa',
    'agama' => 'Agama',
    'kewarganegaraan' => 'Kewarganegaraan',
    'anak_ke' => 'Anak keberapa',
    'jumlah_saudara' => 'Jumlah saudara',
    'bahasa_sehari_hari' => 'Bahasa sehari-hari',
    'provinsi' => 'Provinsi',
    'kabupaten' => 'Kabupaten',
    'kecamatan' => 'Kecamatan',
    'distrik' => 'Distrik',
    'kampung' => 'Kampung/Desa',
    'rt_rw' => 'RT/RW',
    'kode_pos' => 'Kode pos',
    'jarak_rumah' => 'Jarak rumah ke sekolah',
    'transportasi' => 'Transportasi ke sekolah',
    'nama_ayah' => 'Nama lengkap ayah',
    'nik_ayah' => 'NIK ayah',
    'tempat_lahir_ayah' => 'Tempat lahir ayah',
    'tanggal_lahir_ayah' => 'Tanggal lahir ayah',
    'pendidikan_ayah' => 'Pendidikan terakhir ayah',
    'pekerjaan_ayah' => 'Pekerjaan ayah',
    'penghasilan_ayah' => 'Penghasilan ayah (opsional)',
    'telepon_ayah' => 'Nomor telepon ayah',
    'status_ayah' => 'Status ayah',
    'nama_ibu' => 'Nama lengkap ibu',
    'nik_ibu' => 'NIK ibu',
    'tempat_lahir_ibu' => 'Tempat lahir ibu',
    'tanggal_lahir_ibu' => 'Tanggal lahir ibu',
    'pendidikan_ibu' => 'Pendidikan terakhir ibu',
    'pekerjaan_ibu' => 'Pekerjaan ibu',
    'penghasilan_ibu' => 'Penghasilan ibu (opsional)',
    'telepon_ibu' => 'Nomor telepon ibu',
    'status_ibu' => 'Status ibu',
    'nama_wali' => 'Nama lengkap wali',
    'hubungan_wali' => 'Hubungan dengan siswa',
    'nik_wali' => 'NIK wali',
    'pendidikan_wali' => 'Pendidikan terakhir wali',
    'pekerjaan_wali' => 'Pekerjaan wali',
    'telepon_wali' => 'Nomor telepon wali',
    'alamat_wali' => 'Alamat wali',
    'asal_sekolah' => 'Asal sekolah',
    'npsn_sekolah_asal' => 'NPSN sekolah asal',
    'nomor_ijazah_sd' => 'Nomor ijazah SD',
    'nomor_skhun' => 'Nomor SKHUN (jika tersedia)',
    'tahun_lulus_sd' => 'Tahun lulus SD',
    'tanggal_masuk_smp' => 'Tanggal masuk SMP',
    'semester' => 'Semester saat ini',
    'golongan_darah' => 'Golongan darah',
    'tinggi_badan' => 'Tinggi badan (cm)',
    'berat_badan' => 'Berat badan (kg)',
    'riwayat_alergi' => 'Riwayat alergi',
    'kebutuhan_khusus' => 'Kebutuhan khusus',
    'catatan_kesehatan' => 'Catatan kesehatan penting',
    'hobi' => 'Hobi',
    'cita_cita' => 'Cita-cita',
    'prestasi_akademik' => 'Prestasi akademik',
    'prestasi_nonakademik' => 'Prestasi nonakademik',
    'ekstrakurikuler' => 'Ekstrakurikuler',
    'organisasi' => 'Organisasi yang diikuti',
    'dokumen_akta_path' => 'Scan akta kelahiran',
    'dokumen_kk_path' => 'Scan Kartu Keluarga',
    'dokumen_identitas_ortu_path' => 'Scan KTP orang tua/wali (jika diperlukan)',
    'dokumen_ijazah_sd_path' => 'Scan ijazah SD',
    'dokumen_kip_path' => 'Scan Kartu Indonesia Pintar (opsional)',
    'dokumen_pendukung_path' => 'Dokumen pendukung lainnya',
    'nuptk' => 'NUPTK',
    'gelar_depan' => 'Gelar depan',
    'gelar_belakang' => 'Gelar belakang',
    'agama_guru' => 'Agama (opsional)',
    'status_perkawinan' => 'Status perkawinan',
    'kewarganegaraan_guru' => 'Kewarganegaraan',
    'nik_guru' => 'NIK / Nomor KTP',
    'nomor_kk_guru' => 'Nomor Kartu Keluarga (opsional)',
    'email_pribadi' => 'Email pribadi',
    'provinsi_guru' => 'Provinsi',
    'kabupaten_guru' => 'Kabupaten/Kota',
    'kecamatan_guru' => 'Kecamatan',
    'distrik_guru' => 'Distrik',
    'kampung_guru' => 'Kelurahan/Kampung',
    'rt_rw_guru' => 'RT/RW',
    'kode_pos_guru' => 'Kode pos',
    'perguruan_tinggi' => 'Nama perguruan tinggi',
    'fakultas' => 'Fakultas',
    'program_studi' => 'Program studi',
    'gelar_akademik' => 'Gelar akademik',
    'tahun_masuk_kuliah' => 'Tahun masuk',
    'tahun_lulus_kuliah' => 'Tahun lulus',
    'nomor_ijazah' => 'Nomor ijazah',
    'bidang_keahlian' => 'Bidang keahlian',
    'sertifikasi_pendidik' => 'Sertifikasi pendidik',
    'nomor_sertifikat_pendidik' => 'Nomor sertifikat pendidik',
    'status_kepegawaian' => 'Status kepegawaian',
    'jenis_guru' => 'Jenis pegawai/guru',
    'jabatan_guru' => 'Jabatan utama',
    'pangkat_golongan' => 'Pangkat/Golongan',
    'tmt' => 'TMT',
    'tanggal_mulai_bertugas' => 'Tanggal mulai bertugas',
    'masa_kerja' => 'Masa kerja',
    'nomor_sk_pengangkatan' => 'Nomor SK pengangkatan',
    'tanggal_sk_pengangkatan' => 'Tanggal SK pengangkatan',
    'instansi_induk' => 'Instansi induk',
    'jabatan_tambahan' => 'Jabatan tambahan',
    'pendidikan_pelatihan' => 'Pendidikan dan pelatihan yang pernah diikuti',
    'jabatan_saat_ini' => 'Jabatan saat ini',
    'sk_kepala_sekolah' => 'Nomor SK pengangkatan Kepala Sekolah',
    'tanggal_sk_kepala' => 'Tanggal SK Kepala Sekolah',
    'tanggal_mulai_menjabat' => 'Tanggal mulai menjabat',
    'periode_jabatan' => 'Periode jabatan',
    'status_jabatan' => 'Status jabatan',
    'jenjang_pendidikan' => 'Jenjang pendidikan sekolah',
    'bidang_sertifikasi' => 'Bidang sertifikasi',
    'nrg' => 'Nomor Registrasi Guru (NRG)',
    'sertifikat_kompetensi_kepsek' => 'Sertifikat kompetensi Kepala Sekolah',
    'pelatihan_kepemimpinan' => 'Pelatihan kepemimpinan',
    'pelatihan_manajemen_sekolah' => 'Pelatihan manajemen sekolah',
    'pelatihan_kurikulum' => 'Pelatihan kurikulum',
    'pengembangan_keprofesian' => 'Pengembangan keprofesian berkelanjutan',
    'jabatan_sebelumnya' => 'Jabatan sebelumnya',
    'instansi_sebelumnya' => 'Nama instansi/sekolah sebelumnya',
    'tahun_mulai_jabatan' => 'Tahun mulai jabatan sebelumnya',
    'tahun_selesai_jabatan' => 'Tahun selesai jabatan sebelumnya',
    'nomor_sk_jabatan' => 'Nomor SK jabatan sebelumnya',
    'keterangan_jabatan' => 'Keterangan riwayat jabatan',
    'nip_kepala' => 'NIP',
    'email_dinas_kepsek' => 'Email dinas',
];

if (!$schemaReady) {
    $pageTitle = 'Biodata Saya';
    require __DIR__ . '/includes/header.php';
    echo '<div class="dashboard-heading"><div><h1>Biodata Saya</h1><p>Data pelengkap akun dan pemeriksaan profil.</p></div></div>';
    echo '<div class="alert alert-warning">Modul biodata belum aktif pada database. Setelah backup database tersedia, jalankan <code>php database/migrate.php</code> dari folder proyek.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$targetStmt = $pdo->prepare('SELECT u.id,u.username,u.nama_lengkap,u.role,u.guru_id,u.siswa_id,u.foto_path,u.aktif,u.created_at,u.last_login_at,g.nama AS nama_guru,g.nip,g.email AS email_guru,g.jenis_kelamin AS guru_jenis_kelamin,g.status AS guru_status,g.alamat AS guru_alamat,s.nama AS nama_siswa,s.nis,s.nisn,s.jenis_kelamin AS siswa_jenis_kelamin,s.tahun_masuk,s.status AS siswa_status,s.tanggal_lahir AS siswa_tanggal_lahir,s.alamat AS siswa_alamat,k.nama_kelas,ta.nama AS tahun_ajaran,wali.nama AS wali_kelas FROM users u LEFT JOIN guru g ON g.id=u.guru_id LEFT JOIN siswa s ON s.id=u.siswa_id LEFT JOIN kelas k ON k.id=s.kelas_id LEFT JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id LEFT JOIN guru wali ON wali.id=k.wali_guru_id WHERE u.id=? AND u.aktif=1');
$targetStmt->execute([$targetId]);
$target = $targetStmt->fetch();
if (!$target || ($targetId !== $userId && (!$isReviewer || (!$isAdmin && !in_array($target['role'], ['kepala_sekolah', 'guru', 'siswa'], true))))) {
    http_response_code(403);
    exit('Biodata tidak tersedia atau Anda tidak memiliki akses.');
}
$targetRole = (string)$target['role'];
$isLinkedTeacher = $targetRole === 'guru' && !empty($target['guru_id']);
$isLinkedStudent = $targetRole === 'siswa' && !empty($target['siswa_id']);
$teacherAssignments = [];
$teacherHomerooms = [];
$teacherWorkloadJp = 0;
$schoolProfile = [];
if ($target['role'] === 'kepala_sekolah') $schoolProfile = $pdo->query('SELECT nama,npsn,alamat,email FROM profil_sekolah WHERE id=1 LIMIT 1')->fetch() ?: [];
if ($isLinkedTeacher) {
    $assignmentStmt = $pdo->prepare("SELECT DISTINCT m.nama_mapel,k.nama_kelas,ta.nama AS tahun_ajaran FROM jadwal_pelajaran j JOIN kelas k ON k.id=j.kelas_id JOIN mapel m ON m.id=j.mapel_id JOIN tahun_ajaran ta ON ta.id=j.tahun_ajaran_id WHERE j.guru_id=? AND ta.aktif=1 ORDER BY m.nama_mapel,k.nama_kelas");
    $assignmentStmt->execute([(int)$target['guru_id']]);
    $teacherAssignments = $assignmentStmt->fetchAll();
    $homeroomStmt = $pdo->prepare('SELECT DISTINCT k.nama_kelas,ta.nama AS tahun_ajaran FROM kelas k JOIN tahun_ajaran ta ON ta.id=k.tahun_ajaran_id WHERE k.wali_guru_id=? AND ta.aktif=1 ORDER BY k.nama_kelas');
    $homeroomStmt->execute([(int)$target['guru_id']]);
    $teacherHomerooms = $homeroomStmt->fetchAll();
    $workloadStmt = $pdo->prepare('SELECT COALESCE(CEIL(SUM(TIME_TO_SEC(TIMEDIFF(j.jam_selesai,j.jam_mulai))/60)/40),0) FROM jadwal_pelajaran j JOIN tahun_ajaran ta ON ta.id=j.tahun_ajaran_id WHERE j.guru_id=? AND ta.aktif=1');
    $workloadStmt->execute([(int)$target['guru_id']]);
    $teacherWorkloadJp = (int)$workloadStmt->fetchColumn();
}
$profileStmt = $pdo->prepare('SELECT * FROM biodata_pengguna WHERE user_id=?');
$profileStmt->execute([$targetId]);
$profile = $profileStmt->fetch() ?: ['user_id' => $targetId, 'status_verifikasi' => 'belum_lengkap'];
$auditReady = biodata_history_ready($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    if ($isReviewer && $targetId !== $userId && ($isAdmin || in_array($targetRole, ['kepala_sekolah', 'guru', 'siswa'], true)) && $action === 'review') {
        $decision = (string)($_POST['decision'] ?? '');
        $note = trim((string)($_POST['catatan_verifikasi'] ?? ''));
        if (!$auditReady) {
            $error = 'Pemeriksaan belum dapat disimpan karena tabel riwayat belum tersedia. Jalankan migrasi database setelah membuat backup.';
        } elseif (!in_array($decision, ['terverifikasi', 'perlu_perbaikan', 'ditolak'], true) || ($decision === 'ditolak' && !$rejectionReady)) {
            $error = 'Pilihan pemeriksaan tidak valid.';
        } elseif (in_array($decision, ['perlu_perbaikan', 'ditolak'], true) && $note === '') {
            $error = 'Alasan wajib diisi untuk meminta perbaikan atau menolak pengajuan.';
        } else {
            $check = $pdo->prepare('SELECT * FROM biodata_pengguna WHERE user_id=?');
            $check->execute([$targetId]);
            $data = $check->fetch();
            if (!$data || ($decision === 'terverifikasi' && biodata_missing_fields($data, $targetRole))) {
                $error = 'Biodata belum lengkap; status belum dapat diverifikasi.';
            } else {
                $review = $pdo->prepare('UPDATE biodata_pengguna SET status_verifikasi=?,catatan_verifikasi=?,diverifikasi_oleh=?,diverifikasi_pada=NOW() WHERE user_id=?');
                $review->execute([$decision, in_array($decision, ['perlu_perbaikan', 'ditolak'], true) ? mb_substr($note, 0, 500) : null, $userId, $targetId]);
                if (biodata_history_ready($pdo)) {
                    $history = $pdo->prepare('INSERT INTO biodata_review_history (user_id,reviewer_id,status,catatan) VALUES (?,?,?,?)');
                    $history->execute([$targetId, $userId, $decision, in_array($decision, ['perlu_perbaikan', 'ditolak'], true) ? mb_substr($note, 0, 500) : null]);
                }
                record_activity($pdo, match ($decision) {
                    'terverifikasi' => 'verifikasi biodata',
                    'ditolak' => 'tolak biodata',
                    default => 'minta perbaikan biodata'
                }, 'biodata_pengguna', $targetId);
                flash(match ($decision) {
                    'terverifikasi' => 'Biodata berhasil diverifikasi.',
                    'ditolak' => 'Pengajuan biodata ditolak dan alasan tersimpan.',
                    default => 'Catatan perbaikan berhasil dikirim.'
                });
                redirect('biodata.php?user=' . $targetId);
            }
        }
    } elseif ($targetId === $userId && $action === 'save') {
        $photoUpload = isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $newDocuments = [];
        if ($photoUpload) {
            [$newPhoto, $photoError] = store_user_photo($_FILES['foto']);
            if ($photoError) $error = $photoError;
        }
        $fields = ['telepon', 'whatsapp', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'pendidikan'];
        if ($targetRole === 'siswa') $fields = array_merge($fields, ['nama_kontak_darurat', 'telepon_kontak_darurat', 'email', 'agama', 'kewarganegaraan', 'anak_ke', 'jumlah_saudara', 'bahasa_sehari_hari', 'provinsi', 'kabupaten', 'kecamatan', 'distrik', 'kampung', 'rt_rw', 'kode_pos', 'jarak_rumah', 'transportasi', 'nama_ayah', 'nik_ayah', 'tempat_lahir_ayah', 'tanggal_lahir_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah', 'telepon_ayah', 'status_ayah', 'nama_ibu', 'nik_ibu', 'tempat_lahir_ibu', 'tanggal_lahir_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu', 'telepon_ibu', 'status_ibu', 'nama_wali', 'hubungan_wali', 'nik_wali', 'pendidikan_wali', 'pekerjaan_wali', 'telepon_wali', 'alamat_wali', 'asal_sekolah', 'npsn_sekolah_asal', 'nomor_ijazah_sd', 'nomor_skhun', 'tahun_lulus_sd', 'tanggal_masuk_smp', 'semester', 'golongan_darah', 'tinggi_badan', 'berat_badan', 'riwayat_alergi', 'kebutuhan_khusus', 'catatan_kesehatan', 'hobi', 'cita_cita', 'prestasi_akademik', 'prestasi_nonakademik', 'ekstrakurikuler', 'organisasi']);
        if ($targetRole === 'guru') $fields = array_merge($fields, ['nuptk', 'gelar_depan', 'gelar_belakang', 'agama_guru', 'status_perkawinan', 'kewarganegaraan_guru', 'nik_guru', 'nomor_kk_guru', 'email_pribadi', 'provinsi_guru', 'kabupaten_guru', 'kecamatan_guru', 'distrik_guru', 'kampung_guru', 'rt_rw_guru', 'kode_pos_guru', 'perguruan_tinggi', 'fakultas', 'program_studi', 'gelar_akademik', 'tahun_masuk_kuliah', 'tahun_lulus_kuliah', 'nomor_ijazah', 'bidang_keahlian', 'sertifikasi_pendidik', 'nomor_sertifikat_pendidik', 'status_kepegawaian', 'jenis_guru', 'jabatan_guru', 'pangkat_golongan', 'tmt', 'tanggal_mulai_bertugas', 'masa_kerja', 'nomor_sk_pengangkatan', 'tanggal_sk_pengangkatan', 'instansi_induk', 'jabatan_tambahan']);
        if ($targetRole === 'kepala_sekolah') $fields = array_merge($fields, ['nip_kepala', 'nuptk', 'gelar_depan', 'gelar_belakang', 'agama_guru', 'kewarganegaraan_guru', 'nik_guru', 'status_perkawinan', 'email_dinas_kepsek', 'email_pribadi', 'provinsi_guru', 'kabupaten_guru', 'kecamatan_guru', 'distrik_guru', 'kampung_guru', 'rt_rw_guru', 'kode_pos_guru', 'pendidikan_pelatihan', 'perguruan_tinggi', 'fakultas', 'program_studi', 'gelar_akademik', 'tahun_masuk_kuliah', 'tahun_lulus_kuliah', 'nomor_ijazah', 'status_kepegawaian', 'jenis_guru', 'pangkat_golongan', 'jabatan_guru', 'tmt', 'tanggal_mulai_bertugas', 'masa_kerja', 'nomor_sk_pengangkatan', 'tanggal_sk_pengangkatan', 'instansi_induk', 'jabatan_saat_ini', 'sk_kepala_sekolah', 'tanggal_sk_kepala', 'tanggal_mulai_menjabat', 'periode_jabatan', 'status_jabatan', 'jenjang_pendidikan', 'sertifikasi_pendidik', 'nomor_sertifikat_pendidik', 'bidang_sertifikasi', 'nrg', 'sertifikat_kompetensi_kepsek', 'pelatihan_kepemimpinan', 'pelatihan_manajemen_sekolah', 'pelatihan_kurikulum', 'pengembangan_keprofesian', 'jabatan_sebelumnya', 'instansi_sebelumnya', 'tahun_mulai_jabatan', 'tahun_selesai_jabatan', 'nomor_sk_jabatan', 'keterangan_jabatan']);
        $documentFields = $targetRole === 'siswa' ? ['dokumen_akta_path', 'dokumen_kk_path', 'dokumen_identitas_ortu_path', 'dokumen_ijazah_sd_path', 'dokumen_kip_path', 'dokumen_pendukung_path'] : [];
        foreach ($documentFields as $documentField) {
            if ($error !== '' || !isset($_FILES[$documentField]) || (int)($_FILES[$documentField]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            [$storedPath, $documentError] = store_biodata_document($_FILES[$documentField]);
            if ($documentError) $error = $documentError;
            elseif ($storedPath) $newDocuments[$documentField] = $storedPath;
        }
        $data = [];
        foreach ($fields as $field) $data[$field] = trim((string)($_POST[$field] ?? ''));
        $lengthLimits = [
            'email' => 180,
            'email_pribadi' => 180,
            'agama' => 60,
            'agama_guru' => 60,
            'kewarganegaraan' => 80,
            'kewarganegaraan_guru' => 80,
            'bahasa_sehari_hari' => 100,
            'provinsi' => 100,
            'kabupaten' => 100,
            'kecamatan' => 100,
            'distrik' => 100,
            'kampung' => 120,
            'rt_rw' => 30,
            'kode_pos' => 12,
            'jarak_rumah' => 60,
            'transportasi' => 100,
            'nama_ayah' => 120,
            'nik_ayah' => 32,
            'tempat_lahir_ayah' => 100,
            'pendidikan_ayah' => 120,
            'pekerjaan_ayah' => 120,
            'penghasilan_ayah' => 80,
            'telepon_ayah' => 30,
            'status_ayah' => 30,
            'nama_ibu' => 120,
            'nik_ibu' => 32,
            'tempat_lahir_ibu' => 100,
            'pendidikan_ibu' => 120,
            'pekerjaan_ibu' => 120,
            'penghasilan_ibu' => 80,
            'telepon_ibu' => 30,
            'status_ibu' => 30,
            'nama_wali' => 120,
            'hubungan_wali' => 80,
            'nik_wali' => 32,
            'pendidikan_wali' => 120,
            'pekerjaan_wali' => 120,
            'telepon_wali' => 30,
            'asal_sekolah' => 180,
            'npsn_sekolah_asal' => 30,
            'nomor_ijazah_sd' => 100,
            'nomor_skhun' => 100,
            'tahun_lulus_sd' => 4,
            'semester' => 30,
            'golongan_darah' => 5,
            'tinggi_badan' => 10,
            'berat_badan' => 10,
            'hobi' => 180,
            'cita_cita' => 180,
            'ekstrakurikuler' => 250,
            'organisasi' => 250,
            'nuptk' => 40,
            'gelar_depan' => 60,
            'gelar_belakang' => 80,
            'status_perkawinan' => 40,
            'nik_guru' => 32,
            'nomor_kk_guru' => 32,
            'provinsi_guru' => 100,
            'kabupaten_guru' => 100,
            'kecamatan_guru' => 100,
            'distrik_guru' => 100,
            'kampung_guru' => 120,
            'rt_rw_guru' => 30,
            'kode_pos_guru' => 12,
            'perguruan_tinggi' => 180,
            'fakultas' => 150,
            'program_studi' => 150,
            'gelar_akademik' => 80,
            'tahun_masuk_kuliah' => 4,
            'tahun_lulus_kuliah' => 4,
            'nomor_ijazah' => 100,
            'bidang_keahlian' => 180,
            'sertifikasi_pendidik' => 30,
            'nomor_sertifikat_pendidik' => 100,
            'status_kepegawaian' => 60,
            'jenis_guru' => 100,
            'jabatan_guru' => 120,
            'pangkat_golongan' => 80,
            'masa_kerja' => 60,
            'nomor_sk_pengangkatan' => 120,
            'instansi_induk' => 180,
            'jabatan_tambahan' => 180,
            'nip_kepala' => 40,
            'email_dinas_kepsek' => 180,
            'jabatan_saat_ini' => 120,
            'sk_kepala_sekolah' => 120,
            'periode_jabatan' => 60,
            'status_jabatan' => 30,
            'jenjang_pendidikan' => 80,
            'bidang_sertifikasi' => 180,
            'nrg' => 40,
            'sertifikat_kompetensi_kepsek' => 180,
            'jabatan_sebelumnya' => 180,
            'instansi_sebelumnya' => 180,
            'tahun_mulai_jabatan' => 4,
            'tahun_selesai_jabatan' => 4,
            'nomor_sk_jabatan' => 120
        ];
        $lengthLimits += ['telepon' => 30, 'whatsapp' => 30, 'tempat_lahir' => 100, 'pendidikan' => 180, 'nama_kontak_darurat' => 120, 'telepon_kontak_darurat' => 30, 'alamat' => 3000, 'alamat_wali' => 3000, 'riwayat_alergi' => 3000, 'kebutuhan_khusus' => 3000, 'catatan_kesehatan' => 3000, 'prestasi_akademik' => 3000, 'prestasi_nonakademik' => 3000];
        foreach ($lengthLimits as $field => $max) if ($error === '' && isset($data[$field]) && mb_strlen($data[$field]) > $max) $error = ($labels[$field] ?? $field) . " maksimal {$max} karakter.";
        foreach (['email', 'email_pribadi', 'email_dinas_kepsek'] as $emailField) if ($error === '' && !empty($data[$emailField]) && !filter_var($data[$emailField], FILTER_VALIDATE_EMAIL)) $error = 'Format ' . strtolower($labels[$emailField] ?? 'email') . ' tidak valid.';
        foreach (['tanggal_lahir', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'tanggal_masuk_smp', 'tmt', 'tanggal_mulai_bertugas', 'tanggal_sk_pengangkatan', 'tanggal_sk_kepala', 'tanggal_mulai_menjabat'] as $dateField) if ($error === '' && !empty($data[$dateField])) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data[$dateField]);
            if (!$date || $date->format('Y-m-d') !== $data[$dateField] || $date > new DateTimeImmutable('today')) $error = ($labels[$dateField] ?? 'Tanggal') . ' tidak valid.';
        }
        foreach (['anak_ke', 'jumlah_saudara'] as $numberField) if ($error === '' && ($data[$numberField] ?? '') !== '' && (!ctype_digit($data[$numberField]) || (int)$data[$numberField] > 99)) $error = ($labels[$numberField] ?? $numberField) . ' harus angka antara 0 dan 99.';
        if ($error === '') {
            $missing = biodata_missing_fields($data, $targetRole);
            $status = $missing ? 'belum_lengkap' : 'menunggu_verifikasi';
            $data = array_merge($data, $newDocuments);
            $columns = array_values(array_filter($fields, static fn(string $field): bool => !str_starts_with($field, 'dokumen_')));
            $columns = array_merge($columns, array_keys($newDocuments));
            $insertColumns = array_merge(['user_id'], $columns, ['status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada']);
            $updates = array_map(static fn(string $field): string => "`{$field}`=VALUES(`{$field}`)", array_merge($columns, ['status_verifikasi']));
            $updates[] = 'catatan_verifikasi=NULL';
            $updates[] = 'diverifikasi_oleh=NULL';
            $updates[] = 'diverifikasi_pada=NULL';
            $placeholders = array_fill(0, count($insertColumns), '?');
            $sql = 'INSERT INTO biodata_pengguna (`' . implode('`,`', $insertColumns) . '`) VALUES (' . implode(',', $placeholders) . ') ON DUPLICATE KEY UPDATE ' . implode(',', $updates);
            $values = [$userId];
            foreach ($columns as $column) $values[] = ($data[$column] ?? '') !== '' ? $data[$column] : null;
            array_push($values, $status, null, null, null);
            $save = $pdo->prepare($sql);
            $save->execute($values);
            foreach ($newDocuments as $documentField => $storedPath) if (!empty($profile[$documentField])) remove_biodata_document($profile[$documentField]);
            if ($newPhoto !== null) {
                $photoUpdate = $pdo->prepare('UPDATE users SET foto_path=? WHERE id=?');
                $photoUpdate->execute([$newPhoto, $userId]);
                if (!empty($target['foto_path'])) remove_user_photo($target['foto_path']);
                $_SESSION['profile_photo'] = $newPhoto;
            }
            if (biodata_history_ready($pdo)) {
                $history = $pdo->prepare('INSERT INTO biodata_review_history (user_id,reviewer_id,status,catatan) VALUES (?,NULL,?,NULL)');
                $history->execute([$userId, $status]);
            }
            record_activity($pdo, 'perbarui biodata', 'biodata_pengguna', $userId);
            flash($missing ? 'Biodata disimpan. Lengkapi kolom wajib sebelum dapat diverifikasi.' : 'Biodata diajukan untuk pemeriksaan Super Admin.');
            redirect('biodata.php');
        }
        if ($newPhoto !== null && $error !== '') remove_user_photo($newPhoto);
        if ($error !== '') foreach ($newDocuments as $storedPath) remove_biodata_document($storedPath);
        $profile = array_merge($profile, $data);
    } else {
        http_response_code(403);
        exit('Tindakan ini tidak diizinkan.');
    }
}

$missing = biodata_missing_fields($profile, $targetRole);
$completion = count(biodata_required_fields($targetRole)) ? (int)round((count(biodata_required_fields($targetRole)) - count($missing)) * 100 / count(biodata_required_fields($targetRole))) : 100;
$statusLabels = ['belum_lengkap' => 'Belum lengkap', 'menunggu_verifikasi' => 'Menunggu verifikasi', 'terverifikasi' => 'Terverifikasi', 'perlu_perbaikan' => 'Perlu perbaikan', 'ditolak' => 'Ditolak'];
$reviewHistoryRows = [];
if ($isReviewer && $targetId !== $userId && biodata_history_ready($pdo)) {
    $historyStmt = $pdo->prepare("SELECT h.status,h.catatan,h.dibuat_pada,CASE WHEN h.reviewer_id IS NULL THEN 'Pengguna' WHEN u.id IS NULL THEN 'Akun dihapus' ELSE u.nama_lengkap END AS pemeriksa FROM biodata_review_history h LEFT JOIN users u ON u.id=h.reviewer_id WHERE h.user_id=? ORDER BY h.dibuat_pada DESC,h.id DESC LIMIT 30");
    $historyStmt->execute([$targetId]);
    $reviewHistoryRows = $historyStmt->fetchAll();
}
$flashMessage = take_flash();
$pageTitle = $isReviewer && $targetId !== $userId ? 'Pemeriksaan Biodata' : 'Biodata Saya';
require __DIR__ . '/includes/header.php';
?>
<?php require __DIR__ . '/includes/biodata_view.php'; ?>
<?php if ($isReviewer && $targetId !== $userId && $reviewHistoryRows): ?><section class="card p-3 p-md-4 mt-3">
        <h2 class="h5">Riwayat pengajuan dan pemeriksaan</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Pemeriksa</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($reviewHistoryRows as $historyRow): ?><tr>
                            <td><?= e($historyRow['dibuat_pada']) ?></td>
                            <td><?= e($statusLabels[$historyRow['status']] ?? $historyRow['status']) ?></td>
                            <td><?= e($historyRow['pemeriksa'] ?: 'Pengguna') ?></td>
                            <td><?= e($historyRow['catatan'] ?: '—') ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section><?php endif; ?>
<?php if ($isReviewer): $queueSql = $isAdmin ? "SELECT u.id,u.nama_lengkap,u.username,u.role,b.status_verifikasi,b.diperbarui_pada FROM users u LEFT JOIN biodata_pengguna b ON b.user_id=u.id WHERE u.aktif=1" : "SELECT u.id,u.nama_lengkap,u.username,u.role,b.status_verifikasi,b.diperbarui_pada FROM users u LEFT JOIN biodata_pengguna b ON b.user_id=u.id WHERE u.aktif=1 AND u.role IN ('kepala_sekolah','guru','siswa')";
    $queue = $pdo->query($queueSql . " ORDER BY CASE COALESCE(b.status_verifikasi,'belum_lengkap') WHEN 'menunggu_verifikasi' THEN 0 WHEN 'perlu_perbaikan' THEN 1 WHEN 'belum_lengkap' THEN 2 ELSE 3 END,b.diperbarui_pada DESC,u.nama_lengkap LIMIT 100")->fetchAll(); ?><section class="card p-3 p-md-4 mt-3">
        <h2 class="h5">Daftar pemeriksaan biodata</h2>
        <p class="text-secondary small">Data pribadi ditampilkan hanya saat Anda membuka satu pengajuan untuk diperiksa.</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Nama akun</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Terakhir diperbarui</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody><?php foreach ($queue as $row): ?><tr>
                            <td><?= e($row['nama_lengkap']) ?><div class="small text-secondary"><?= e($row['username']) ?></div>
                            </td>
                            <td><?= e(role_label($row['role'])) ?></td>
                            <td><?= e($statusLabels[$row['status_verifikasi'] ?? 'belum_lengkap'] ?? 'Belum lengkap') ?></td>
                            <td><?= e($row['diperbarui_pada'] ?? '—') ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="biodata.php?user=<?= (int)$row['id'] ?>">Periksa</a></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>