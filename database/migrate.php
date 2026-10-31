<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../config/database.php';

if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sims_db') {
    fwrite(STDERR, "Migrasi ini mengasumsikan database sims_db. Tidak ada perubahan yang dilakukan.\n");
    exit(2);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(190) NOT NULL UNIQUE,
    checksum CHAR(64) NOT NULL,
    status VARCHAR(16) NOT NULL,
    note VARCHAR(500) NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL
) ENGINE=InnoDB");

function migration_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function migration_schema_ready(PDO $pdo, string $name): bool
{
    $required = match ($name) {
        '2026_10_27_guru_profile_fields.sql' => ['guru' => ['foto', 'tampil_publik']],
        '2026_10_29_public_portal.sql' => [
            'berita_sekolah' => ['judul', 'gambar_url', 'published_at'],
            'galeri_sekolah' => ['judul', 'gambar_url', 'status'],
            'kalender_akademik' => ['tampil_publik'],
            'profil_sekolah' => ['sejarah', 'visi', 'misi', 'sambutan', 'peta_url'],
        ],
        '2026_10_29_user_roles_profiles.sql' => ['users' => ['guru_id', 'siswa_id', 'foto_path', 'password_must_change', 'last_login_at']],
        '2026_10_29_learning_materials_tasks.sql' => [
            'materi_pelajaran' => ['file_path', 'status'],
            'tugas_pelajaran' => ['instruksi', 'tenggat', 'status'],
            'pengumpulan_tugas' => ['jawaban', 'nilai', 'umpan_balik'],
        ],
        '2026_10_30_calendar_moderation.sql' => [
            'kalender_akademik' => ['created_by', 'review_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'],
        ],
        '2026_10_30_content_moderation.sql' => [
            'pengumuman' => ['review_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'],
            'berita_sekolah' => ['review_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'],
            'galeri_sekolah' => ['review_status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'],
            'konten_review_log' => ['entitas', 'record_id', 'status_awal', 'status_akhir', 'user_id'],
        ],
        '2026_10_30_notification_recipients.sql' => ['notifikasi' => ['recipient_user_id']],
        '2026_10_30_notification_archive.sql' => ['notifikasi' => ['archived_at']],
        '2026_10_30_student_achievements.sql' => ['prestasi_siswa' => ['siswa_id', 'tahun_ajaran_id', 'nama_prestasi', 'tingkat', 'tanggal']],
        '2026_10_30_learning_plan_details.sql' => ['rencana_ajar' => ['kegiatan', 'metode', 'asesmen']],
        '2026_10_30_submission_attachments.sql' => ['pengumpulan_tugas' => ['file_path', 'file_name']],
        '2026_10_30_user_biodata.sql' => ['biodata_pengguna' => ['user_id', 'telepon', 'whatsapp', 'status_verifikasi', 'diverifikasi_oleh']],
        '2026_10_30_account_workflow.sql' => [
            'biodata_review_history' => ['user_id', 'reviewer_id', 'status', 'catatan', 'dibuat_pada'],
            'password_reset_history' => ['target_user_id', 'changed_by', 'target_role', 'changed_at'],
        ],
        '2026_10_31_grade_publication.sql' => ['nilai' => ['status_publikasi']],
        '2026_10_31_student_biodata_details.sql' => ['biodata_pengguna' => ['email', 'nama_ayah', 'nama_ibu', 'nama_wali', 'asal_sekolah', 'golongan_darah', 'dokumen_akta_path', 'dokumen_kip_path']],
        '2026_10_31_teacher_biodata_details.sql' => ['biodata_pengguna' => ['email_pribadi', 'gelar_depan', 'nuptk', 'nik_guru', 'perguruan_tinggi', 'status_kepegawaian', 'tmt', 'jabatan_tambahan']],
        '2026_10_31_principal_biodata_details.sql' => ['biodata_pengguna' => ['nip_kepala', 'email_dinas_kepsek', 'pendidikan_pelatihan', 'jabatan_saat_ini', 'sk_kepala_sekolah', 'tanggal_mulai_menjabat', 'bidang_sertifikasi', 'jabatan_sebelumnya', 'nomor_sk_jabatan'], 'profil_sekolah' => ['npsn']],
        default => [],
    };
    if (!$required) return false;
    foreach ($required as $table => $columns) {
        if (count(array_intersect($columns, migration_table_columns($pdo, $table))) !== count($columns)) return false;
    }
    if ($name === '2026_10_29_user_roles_profiles.sql') {
        $type = $pdo->query("SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='role'")->fetchColumn();
        if (!is_string($type) || !str_contains($type, "'siswa'")) return false;
    }
    return true;
}

function migration_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';
        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= "\n";
            }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote === null && $char === '-' && $next === '-' && (($sql[$i + 2] ?? '') === ' ' || ($sql[$i + 2] ?? '') === "\t")) {
            $lineComment = true;
            $i++;
            continue;
        }
        if ($quote === null && $char === '#') {
            $lineComment = true;
            continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $blockComment = true;
            $i++;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $quote !== '`' && $i + 1 < $length) $buffer .= $sql[++$i];
            elseif ($char === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) $buffer .= $sql[++$i];
                else $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
        } elseif ($char === ';') {
            if (trim($buffer) !== '') $statements[] = trim($buffer);
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }
    if (trim($buffer) !== '') $statements[] = trim($buffer);
    return $statements;
}

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$dependencyOrder = [
    '2026_10_30_account_workflow.sql' => '2026_10_30_user_biodata.sql',
    '2026_10_30_user_biodata.sql' => '2026_10_30_account_workflow.sql',
];
usort($files, static fn(string $left, string $right): int => strcmp(
    $dependencyOrder[basename($left)] ?? basename($left),
    $dependencyOrder[basename($right)] ?? basename($right),
));
$schemaValidatedMigrations = [
    '2026_10_27_guru_profile_fields.sql',
    '2026_10_29_public_portal.sql',
    '2026_10_29_user_roles_profiles.sql',
    '2026_10_29_learning_materials_tasks.sql',
    '2026_10_30_calendar_moderation.sql',
    '2026_10_30_content_moderation.sql',
    '2026_10_30_notification_recipients.sql',
    '2026_10_30_notification_archive.sql',
    '2026_10_30_student_achievements.sql',
    '2026_10_30_learning_plan_details.sql',
    '2026_10_30_submission_attachments.sql',
    '2026_10_30_user_biodata.sql',
    '2026_10_30_account_workflow.sql',
    '2026_10_31_grade_publication.sql',
    '2026_10_31_student_biodata_details.sql',
    '2026_10_31_teacher_biodata_details.sql',
    '2026_10_31_principal_biodata_details.sql',
];
foreach ($files as $file) {
    $name = basename($file);
    $checksum = hash_file('sha256', $file);
    $existing = $pdo->prepare('SELECT checksum,status FROM schema_migrations WHERE migration_name=?');
    $existing->execute([$name]);
    $row = $existing->fetch();
    if ($row && in_array($row['status'], ['applied', 'reconciled'], true)) {
        if (!hash_equals((string)$row['checksum'], $checksum)) {
            fwrite(STDERR, "Checksum migrasi berubah setelah dicatat: {$name}. Pulihkan file migrasi asli atau buat migrasi baru.\n");
            exit(3);
        }
        if (in_array($name, $schemaValidatedMigrations, true) && !migration_schema_ready($pdo, $name)) {
            fwrite(STDERR, "Skema tidak lagi cocok dengan migrasi yang tercatat: {$name}. Periksa database sebelum melanjutkan.\n");
            exit(4);
        }
        echo "Lewati {$name} ({$row['status']})\n";
        continue;
    }
    if (!$row && migration_schema_ready($pdo, $name)) {
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration_name,checksum,status,note,started_at,finished_at) VALUES (?,?,'reconciled','Skema telah ada sebelum pencatatan migrasi.',NOW(),NOW())");
        $stmt->execute([$name, $checksum]);
        echo "Catat skema lama {$name}\n";
        continue;
    }
    $stmt = $pdo->prepare("INSERT INTO schema_migrations (migration_name,checksum,status,started_at) VALUES (?,?,'running',NOW()) ON DUPLICATE KEY UPDATE checksum=VALUES(checksum),status='running',note=NULL,started_at=NOW(),finished_at=NULL");
    $stmt->execute([$name, $checksum]);
    try {
        foreach (migration_statements((string)file_get_contents($file)) as $statement) $pdo->exec($statement);
        if (in_array($name, $schemaValidatedMigrations, true) && !migration_schema_ready($pdo, $name)) {
            throw new RuntimeException('Skema pascamigrasi tidak sesuai dengan persyaratan.');
        }
        $finish = $pdo->prepare("UPDATE schema_migrations SET status='applied',note=NULL,finished_at=NOW() WHERE migration_name=?");
        $finish->execute([$name]);
        echo "Berhasil {$name}\n";
    } catch (Throwable $exception) {
        $fail = $pdo->prepare("UPDATE schema_migrations SET status='failed',note=?,finished_at=NOW() WHERE migration_name=?");
        $fail->execute([substr($exception->getMessage(), 0, 500), $name]);
        fwrite(STDERR, "Gagal {$name}: {$exception->getMessage()}\nPeriksa status migrasi sebelum mencoba kembali.\n");
        exit(1);
    }
}
