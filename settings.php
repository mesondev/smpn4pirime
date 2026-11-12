<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_login();
require_once __DIR__ . '/config/database.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$preferences = user_display_preferences($pdo, $userId);

// The header toggles theme and sidebar immediately, then persists those choices here.
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ((string) $_GET['ajax'] !== 'preferensi' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Permintaan tidak didukung.']);
        exit;
    }

    verify_csrf();

    $key = (string) ($_POST['preferensi'] ?? '');
    $value = (string) ($_POST['nilai'] ?? '');
    $allowedValues = [
        'tema' => ['sistem', 'terang', 'gelap'],
        'sidebar_ciut' => ['0', '1'],
    ];

    if (!isset($allowedValues[$key]) || !in_array($value, $allowedValues[$key], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Preferensi tidak valid.']);
        exit;
    }

    $preferences[$key] = $value;
    try {
        save_user_display_preferences($pdo, $userId, $preferences);
        echo json_encode(['success' => true]);
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Preferensi belum dapat disimpan.']);
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $submitted = [
        'tema' => (string) ($_POST['tema'] ?? ''),
        'ukuran_teks' => (string) ($_POST['ukuran_teks'] ?? ''),
        'kerapatan_tabel' => (string) ($_POST['kerapatan_tabel'] ?? ''),
        'sidebar_ciut' => isset($_POST['sidebar_ciut']) ? 1 : 0,
        'periode_grafik' => (string) ($_POST['periode_grafik'] ?? ''),
    ];

    $validPreferences = [
        'tema' => ['sistem', 'terang', 'gelap'],
        'ukuran_teks' => ['kecil', 'normal', 'besar'],
        'kerapatan_tabel' => ['ringkas', 'normal', 'nyaman'],
        'periode_grafik' => ['month', '3months', '6months', '12months'],
    ];

    foreach ($validPreferences as $key => $allowedValues) {
        if (!in_array($submitted[$key], $allowedValues, true)) {
            $error = 'Ada pilihan pengaturan yang tidak valid. Periksa kembali formulir.';
            break;
        }
    }

    if ($error === '') {
        try {
            save_user_display_preferences($pdo, $userId, $submitted);
            record_activity($pdo, 'mengubah preferensi tampilan', 'preferensi_pengguna', $userId);
            flash('Preferensi tampilan berhasil disimpan.', 'success');
            redirect('settings.php#appearance');
        } catch (Throwable) {
            $error = 'Pengaturan belum dapat disimpan. Coba lagi atau hubungi administrator.';
        }
    }

    $preferences = normalize_user_display_preferences($submitted);
}

$canSchoolSettings = can_view_module('profil_sekolah');
$canManageAcademic = can_view_module('tahun_ajaran') || can_view_module('kalender_akademik');
$canViewNotifications = can_view_module('notifikasi');
$canManageUsers = can_view_module('pengguna');
$canViewActivity = can_view_module('log_aktivitas');
$isPrincipal = current_role() === 'kepala_sekolah';
$databaseAvailable = false;
try {
    $databaseAvailable = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
} catch (PDOException) {
    $databaseAvailable = false;
}

$pageTitle = 'Pusat Pengaturan';
require __DIR__ . '/includes/header.php';
?>
<div class="settings-page">
    <header class="settings-hero">
        <div>
            <span class="settings-eyebrow">Pengaturan SIMS</span>
            <h1>Pusat Pengaturan</h1>
            <p>Kelola akun dan tampilan pribadi, lalu buka konfigurasi sekolah sesuai hak akses Anda.</p>
        </div>
        <span class="settings-role-badge">
            <i class="bi bi-person-badge" aria-hidden="true"></i>
            <?= e(role_label()) ?>
        </span>
    </header>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="settings-layout">
        <nav class="settings-nav card" aria-label="Kategori pengaturan">
            <span class="settings-nav-title">Kategori</span>
            <a href="#appearance"><i class="bi bi-palette2" aria-hidden="true"></i>Tampilan pribadi</a>
            <a href="#account"><i class="bi bi-person-vcard" aria-hidden="true"></i>Akun dan keamanan</a>
            <?php if ($canSchoolSettings || $canManageAcademic || $canViewNotifications): ?>
                <a href="#school"><i class="bi bi-building-gear" aria-hidden="true"></i>Sekolah dan akademik</a>
            <?php endif; ?>
            <?php if ($canManageUsers || $canViewActivity || current_role() === 'admin'): ?>
                <a href="#administration"><i class="bi bi-shield-check" aria-hidden="true"></i>Administrasi sistem</a>
            <?php endif; ?>
        </nav>

        <div class="settings-content">
            <section class="settings-section" id="appearance" aria-labelledby="appearanceTitle">
                <div class="settings-section-heading">
                    <span class="settings-section-icon"><i class="bi bi-sliders2" aria-hidden="true"></i></span>
                    <div>
                        <span class="settings-eyebrow">Khusus akun ini</span>
                        <h2 id="appearanceTitle">Preferensi tampilan</h2>
                        <p>Preferensi tersimpan pada akun Anda dan tidak mengubah tampilan pengguna lain.</p>
                    </div>
                </div>

                <form method="post" class="card settings-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <div class="settings-control-grid">
                        <div class="settings-control">
                            <label for="settingTheme">Tema dashboard</label>
                            <select class="form-select" id="settingTheme" name="tema">
                                <option value="sistem" <?= $preferences['tema'] === 'sistem' ? 'selected' : '' ?>>Ikuti sistem</option>
                                <option value="terang" <?= $preferences['tema'] === 'terang' ? 'selected' : '' ?>>Terang</option>
                                <option value="gelap" <?= $preferences['tema'] === 'gelap' ? 'selected' : '' ?>>Gelap</option>
                            </select>
                            <small>Ikuti sistem memakai tema perangkat; dashboard utama tetap menggunakan tema navy bawaan.</small>
                        </div>
                        <div class="settings-control">
                            <label for="settingTextSize">Ukuran teks</label>
                            <select class="form-select" id="settingTextSize" name="ukuran_teks">
                                <option value="kecil" <?= $preferences['ukuran_teks'] === 'kecil' ? 'selected' : '' ?>>Kecil</option>
                                <option value="normal" <?= $preferences['ukuran_teks'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="besar" <?= $preferences['ukuran_teks'] === 'besar' ? 'selected' : '' ?>>Besar</option>
                            </select>
                            <small>Pengaturan ini mengubah ukuran dasar teks di dashboard.</small>
                        </div>
                        <div class="settings-control">
                            <label for="settingDensity">Kerapatan tabel</label>
                            <select class="form-select" id="settingDensity" name="kerapatan_tabel">
                                <option value="ringkas" <?= $preferences['kerapatan_tabel'] === 'ringkas' ? 'selected' : '' ?>>Ringkas</option>
                                <option value="normal" <?= $preferences['kerapatan_tabel'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="nyaman" <?= $preferences['kerapatan_tabel'] === 'nyaman' ? 'selected' : '' ?>>Nyaman</option>
                            </select>
                            <small>Mengatur jarak baris tabel agar sesuai cara kerja Anda.</small>
                        </div>
                        <div class="settings-control">
                            <label for="settingPeriod">Periode grafik bawaan</label>
                            <select class="form-select" id="settingPeriod" name="periode_grafik">
                                <option value="month" <?= $preferences['periode_grafik'] === 'month' ? 'selected' : '' ?>>30 hari terakhir</option>
                                <option value="3months" <?= $preferences['periode_grafik'] === '3months' ? 'selected' : '' ?>>3 bulan</option>
                                <option value="6months" <?= $preferences['periode_grafik'] === '6months' ? 'selected' : '' ?>>6 bulan</option>
                                <option value="12months" <?= $preferences['periode_grafik'] === '12months' ? 'selected' : '' ?>>12 bulan</option>
                            </select>
                            <small>Dipakai dashboard saat tidak ada filter periode di URL.</small>
                        </div>
                    </div>
                    <label class="settings-check-row" for="settingSidebar">
                        <span>
                            <strong>Ciutkan menu samping pada layar lebar</strong>
                            <small>Menu dapat dibuka kembali dengan tombol navigasi.</small>
                        </span>
                        <input class="form-check-input" type="checkbox" id="settingSidebar" name="sidebar_ciut" value="1" <?= $preferences['sidebar_ciut'] ? 'checked' : '' ?>>
                    </label>
                    <div class="settings-form-footer">
                        <p><i class="bi bi-cloud-check" aria-hidden="true"></i>Preferensi ini tersimpan di akun dan digunakan pada perangkat yang Anda pakai.</p>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Simpan preferensi
                        </button>
                    </div>
                </form>
            </section>

            <section class="settings-section" id="account" aria-labelledby="accountSettingsTitle">
                <div class="settings-section-heading">
                    <span class="settings-section-icon"><i class="bi bi-person-lock" aria-hidden="true"></i></span>
                    <div>
                        <span class="settings-eyebrow">Akses pribadi</span>
                        <h2 id="accountSettingsTitle">Akun dan keamanan</h2>
                        <p>Informasi sensitif dapat dilihat atau diubah sesuai alur persetujuan biodata.</p>
                    </div>
                </div>
                <div class="settings-card-grid">
                    <a class="card settings-action-card" href="profile.php">
                        <span class="settings-action-icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                        <span><strong>Profil akun</strong><small>Nama tampilan, username, foto profil, dan kata sandi.</small></span>
                        <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                    </a>
                    <?php if (can_view_module('profil')): ?>
                        <a class="card settings-action-card" href="biodata.php">
                            <span class="settings-action-icon"><i class="bi bi-card-list" aria-hidden="true"></i></span>
                            <span><strong>Biodata dan kontak</strong><small>Email dan nomor kontak mengikuti biodata sesuai peran serta proses verifikasi.</small></span>
                            <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($canViewNotifications): ?>
                        <a class="card settings-action-card" href="index.php?module=notifikasi">
                            <span class="settings-action-icon"><i class="bi bi-bell" aria-hidden="true"></i></span>
                            <span><strong>Pusat notifikasi</strong><small>Lihat dan kelola pemberitahuan yang tersedia untuk akun Anda.</small></span>
                            <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($canSchoolSettings || $canManageAcademic || $isPrincipal): ?>
                <section class="settings-section" id="school" aria-labelledby="schoolSettingsTitle">
                    <div class="settings-section-heading">
                        <span class="settings-section-icon"><i class="bi bi-building-gear" aria-hidden="true"></i></span>
                        <div>
                            <span class="settings-eyebrow">Konfigurasi bersama</span>
                            <h2 id="schoolSettingsTitle">Sekolah dan akademik</h2>
                            <p>Pengaturan bersama hanya muncul untuk peran yang memiliki akses.</p>
                        </div>
                    </div>
                    <div class="settings-card-grid">
                        <?php if ($canSchoolSettings): ?>
                            <a class="card settings-action-card" href="index.php?module=profil_sekolah">
                                <span class="settings-action-icon"><i class="bi bi-bank" aria-hidden="true"></i></span>
                                <span><strong>Identitas sekolah</strong><small>Nama resmi, alamat, kontak, visi, misi, sejarah, dan informasi portal publik.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (can_view_module('tahun_ajaran')): ?>
                            <a class="card settings-action-card" href="index.php?module=tahun_ajaran">
                                <span class="settings-action-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
                                <span><strong>Tahun ajaran</strong><small>Kelola periode akademik melalui modul yang sudah tersedia.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php elseif ($isPrincipal): ?>
                            <a class="card settings-action-card" href="reports.php?type=jadwal">
                                <span class="settings-action-icon"><i class="bi bi-calendar2-range" aria-hidden="true"></i></span>
                                <span><strong>Informasi akademik</strong><small>Lihat laporan akademik sesuai hak akses Kepala Sekolah.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (can_view_module('kalender_akademik')): ?>
                            <a class="card settings-action-card" href="index.php?module=kalender_akademik">
                                <span class="settings-action-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                                <span><strong>Kalender akademik</strong><small>Atur atau tinjau agenda sekolah melalui modul kalender.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($isPrincipal): ?>
                            <a class="card settings-action-card" href="public/agenda.php">
                                <span class="settings-action-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
                                <span><strong>Agenda sekolah</strong><small>Tinjau agenda yang telah dipublikasikan.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($canManageUsers || $canViewActivity || current_role() === 'admin'): ?>
                <section class="settings-section" id="administration" aria-labelledby="adminSettingsTitle">
                    <div class="settings-section-heading">
                        <span class="settings-section-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                        <div>
                            <span class="settings-eyebrow">Hak akses terbatas</span>
                            <h2 id="adminSettingsTitle">Administrasi sistem</h2>
                            <p>Akses ditentukan oleh pemeriksaan peran pada sisi server.</p>
                        </div>
                    </div>
                    <div class="settings-card-grid">
                        <?php if ($canManageUsers): ?>
                            <a class="card settings-action-card" href="users.php">
                                <span class="settings-action-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
                                <span><strong>Pengguna dan hak akses</strong><small>Kelola akun dan peran melalui halaman manajemen pengguna.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($canViewActivity): ?>
                            <a class="card settings-action-card" href="activity.php">
                                <span class="settings-action-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
                                <span><strong>Log aktivitas</strong><small>Tinjau tindakan yang tercatat untuk audit.</small></span>
                                <i class="bi bi-arrow-up-right settings-action-arrow" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (current_role() === 'admin'): ?>
                            <article class="card settings-status-card">
                                <span class="settings-action-icon <?= $databaseAvailable ? 'is-healthy' : 'is-warning' ?>">
                                    <i class="bi <?= $databaseAvailable ? 'bi-database-check' : 'bi-database-exclamation' ?>" aria-hidden="true"></i>
                                </span>
                                <span>
                                    <strong>Status database: <?= $databaseAvailable ? 'terhubung' : 'perlu diperiksa' ?></strong>
                                    <small>Status koneksi yang digunakan oleh halaman ini.</small>
                                </span>
                            </article>
                            <article class="card settings-status-card is-unavailable">
                                <span class="settings-action-icon is-warning"><i class="bi bi-archive" aria-hidden="true"></i></span>
                                <span>
                                    <strong>Backup dan uji pemulihan melalui CLI</strong>
                                    <small>Jalankan <code>php tools/backup.php</code> dari Laragon Terminal. Verifikasi pemulihan memakai <code>php tools/restore-test.php &lt;folder-backup&gt;</code>; proses ini membuat database uji terpisah dan tidak mengganti database utama.</small>
                                </span>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
