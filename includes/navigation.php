<?php
$role = current_role();
if ($role === 'siswa') return [
    'Utama' => [['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => 'student.php']],
    'Akademik' => [
        ['label' => 'Profil Saya', 'icon' => 'bi-person-vcard', 'href' => 'biodata.php'],
        ['label' => 'Jadwal Pelajaran', 'icon' => 'bi-calendar-week', 'href' => 'student.php?view=jadwal', 'activeView' => 'jadwal'],
        ['label' => 'Absensi Saya', 'icon' => 'bi-clipboard-check', 'href' => 'student.php?view=absensi', 'activeView' => 'absensi'],
        ['label' => 'Nilai Saya', 'icon' => 'bi-journal-check', 'href' => 'student.php?view=nilai', 'activeView' => 'nilai'],
    ],
    'Pembelajaran' => [
        ['label' => 'Materi Belajar', 'icon' => 'bi-file-earmark-text', 'href' => 'learning.php?view=materi', 'activeView' => 'materi'],
        ['label' => 'Tugas', 'icon' => 'bi-clipboard2-check', 'href' => 'learning.php?view=tugas', 'activeView' => 'tugas'],
        ['label' => 'Pengumpulan Tugas', 'icon' => 'bi-inbox', 'href' => 'learning.php?view=pengumpulan', 'activeView' => 'pengumpulan'],
    ],
    'Informasi Sekolah' => [
        ['label' => 'Pengumuman', 'icon' => 'bi-megaphone', 'href' => 'public/pengumuman.php'],
        ['label' => 'Agenda Sekolah', 'icon' => 'bi-calendar-event', 'href' => 'public/agenda.php'],
        ['label' => 'Notifikasi', 'icon' => 'bi-bell', 'module' => 'notifikasi'],
    ],
    'Pengaturan Akun' => [
        ['label' => 'Keamanan Akun', 'icon' => 'bi-shield-lock', 'href' => 'profile.php'],
    ],
];
if ($role === 'guru') return [
    'Utama' => [['label' => 'Dashboard Guru', 'icon' => 'bi-grid-1x2-fill', 'href' => 'teacher.php']],
    'Pembelajaran' => [
        ['label' => 'Jadwal Mengajar', 'icon' => 'bi-calendar-week', 'href' => 'teacher.php?view=jadwal', 'activeView' => 'jadwal'],
        ['label' => 'Kelas Saya', 'icon' => 'bi-people', 'href' => 'teacher.php?view=kelas', 'activeView' => 'kelas'],
        ['label' => 'Rencana Ajar', 'icon' => 'bi-journal-bookmark', 'href' => 'teacher.php?view=rencana', 'activeView' => 'rencana'],
        ['label' => 'Input Absensi', 'icon' => 'bi-clipboard-check', 'href' => 'teacher.php?view=absensi', 'activeView' => 'absensi'],
        ['label' => 'Input Nilai', 'icon' => 'bi-journal-check', 'href' => 'teacher.php?view=nilai', 'activeView' => 'nilai'],
        ['label' => 'Materi Pembelajaran', 'icon' => 'bi-file-earmark-text', 'href' => 'learning.php?view=materi'],
        ['label' => 'Tugas & Penilaian', 'icon' => 'bi-clipboard2-check', 'href' => 'learning.php?view=tugas'],
        ['label' => 'Pengumpulan Tugas', 'icon' => 'bi-inbox', 'href' => 'learning.php?view=pengumpulan'],
    ],
    'Akun & Informasi' => [
        ['label' => 'Profil Saya', 'icon' => 'bi-person-circle', 'href' => 'profile.php'],
        ['label' => 'Biodata Saya', 'icon' => 'bi-card-text', 'href' => 'biodata.php'],
        ['label' => 'Pengumuman', 'icon' => 'bi-megaphone', 'href' => 'public/pengumuman.php'],
        ['label' => 'Agenda Sekolah', 'icon' => 'bi-calendar-event', 'href' => 'public/agenda.php'],
        ['label' => 'Website Publik', 'icon' => 'bi-globe2', 'href' => 'public/index.php'],
    ],
];
if ($role === 'operator') return [
    'Utama' => [['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => 'index.php']],
    'Administrasi' => [
        ['label' => 'Data Siswa', 'icon' => 'bi-person-vcard-fill', 'module' => 'siswa'],
        ['label' => 'Data Guru', 'icon' => 'bi-person-badge-fill', 'module' => 'guru'],
        ['label' => 'Kelas / Rombel', 'icon' => 'bi-door-open-fill', 'module' => 'kelas'],
        ['label' => 'Mata Pelajaran', 'icon' => 'bi-book-half', 'module' => 'mapel'],
        ['label' => 'Tahun Ajaran', 'icon' => 'bi-calendar-range', 'module' => 'tahun_ajaran'],
        ['label' => 'Jadwal', 'icon' => 'bi-calendar-week-fill', 'module' => 'jadwal'],
        ['label' => 'Absensi Siswa', 'icon' => 'bi-clipboard2-check-fill', 'module' => 'absensi'],
        ['label' => 'Absensi Guru', 'icon' => 'bi-person-check-fill', 'module' => 'absensi_guru'],
        ['label' => 'Nilai Siswa', 'icon' => 'bi-journal-check', 'module' => 'nilai'],
        ['label' => 'Pengumuman', 'icon' => 'bi-megaphone-fill', 'module' => 'pengumuman'],
        ['label' => 'Berita Sekolah', 'icon' => 'bi-newspaper', 'module' => 'berita_sekolah'],
        ['label' => 'Galeri Sekolah', 'icon' => 'bi-images', 'module' => 'galeri_sekolah'],
        ['label' => 'Kalender Akademik', 'icon' => 'bi-calendar3', 'module' => 'kalender_akademik'],
        ['label' => 'Kelas Berlangsung', 'icon' => 'bi-easel2-fill', 'module' => 'kelas_berlangsung'],
        ['label' => 'Kelas Binaan', 'icon' => 'bi-people-fill', 'module' => 'kelas_binaan'],
        ['label' => 'Rencana Ajar', 'icon' => 'bi-journal-bookmark-fill', 'module' => 'rencana_ajar'],
        ['label' => 'Kokurikuler (P5)', 'icon' => 'bi-stars', 'module' => 'kokurikuler'],
        ['label' => 'Notifikasi', 'icon' => 'bi-bell-fill', 'module' => 'notifikasi'],
        ['label' => 'Laporan', 'icon' => 'bi-file-earmark-bar-graph-fill', 'href' => 'reports.php'],
        ['label' => 'Prestasi Siswa', 'icon' => 'bi-award', 'module' => 'prestasi'],
    ],
    'Akun' => [
        ['label' => 'Profil Saya', 'icon' => 'bi-person-circle', 'href' => 'profile.php'],
        ['label' => 'Biodata Saya', 'icon' => 'bi-card-text', 'href' => 'biodata.php'],
        ['label' => 'Verifikasi Biodata', 'icon' => 'bi-person-check', 'href' => 'biodata.php'],
        ['label' => 'Bantu Reset Password', 'icon' => 'bi-key', 'href' => 'passwords.php'],
        ['label' => 'Website Publik', 'icon' => 'bi-globe2', 'href' => 'public/index.php'],
    ],
];
if ($role === 'kepala_sekolah') return [
    'Utama' => [['label' => 'Dashboard Pemantauan', 'icon' => 'bi-grid-1x2-fill', 'href' => 'index.php']],
    'Pemantauan & Laporan' => [
        ['label' => 'Laporan Siswa', 'icon' => 'bi-people', 'href' => 'reports.php?type=siswa'],
        ['label' => 'Laporan Guru', 'icon' => 'bi-person-badge', 'href' => 'reports.php?type=guru'],
        ['label' => 'Distribusi Kelas', 'icon' => 'bi-door-open', 'href' => 'reports.php?type=kelas'],
        ['label' => 'Rekap Nilai', 'icon' => 'bi-journal-check', 'href' => 'reports.php?type=nilai'],
        ['label' => 'Rekap Absensi Siswa', 'icon' => 'bi-clipboard-check', 'href' => 'reports.php?type=absensi'],
        ['label' => 'Tinjau Rencana Ajar', 'icon' => 'bi-journal-bookmark-fill', 'module' => 'rencana_ajar'],
        ['label' => 'Prestasi Siswa', 'icon' => 'bi-award', 'module' => 'prestasi'],
        ['label' => 'Kehadiran Guru', 'icon' => 'bi-person-check', 'href' => 'reports.php?type=absensi_guru'],
        ['label' => 'Jadwal Kelas', 'icon' => 'bi-calendar-week', 'href' => 'reports.php?type=jadwal'],
        ['label' => 'Agenda Sekolah', 'icon' => 'bi-calendar-event', 'href' => 'public/agenda.php'],
        ['label' => 'Notifikasi', 'icon' => 'bi-bell-fill', 'module' => 'notifikasi'],
    ],
    'Akun' => [
        ['label' => 'Profil dan Keamanan', 'icon' => 'bi-person-circle', 'href' => 'profile.php'],
        ['label' => 'Biodata Saya', 'icon' => 'bi-card-text', 'href' => 'biodata.php'],
        ['label' => 'Website Publik', 'icon' => 'bi-globe2', 'href' => 'public/index.php'],
    ],
];
return [
    'Utama' => [
        ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'href' => 'index.php'],
    ],
    'Operasional' => [
        ['label' => 'Kelas Berlangsung', 'icon' => 'bi-easel2-fill', 'module' => 'kelas_berlangsung'],
        ['label' => 'Absensi Guru', 'icon' => 'bi-person-check-fill', 'module' => 'absensi_guru'],
        ['label' => 'Pengumuman', 'icon' => 'bi-megaphone-fill', 'module' => 'pengumuman'],
        ['label' => 'Jadwal', 'icon' => 'bi-calendar-week-fill', 'module' => 'jadwal'],
        ['label' => 'Kehadiran Guru', 'icon' => 'bi-clipboard-data-fill', 'href' => 'reports.php?type=absensi_guru', 'report' => 'absensi_guru'],
        ['label' => 'Jadwal Kelas', 'icon' => 'bi-calendar2-check-fill', 'href' => 'reports.php?type=jadwal', 'report' => 'jadwal'],
    ],
    'Portal Publik' => [
        ['label' => 'Lihat Website Publik', 'icon' => 'bi-globe2', 'href' => 'public/index.php'],
        ['label' => 'Berita Sekolah', 'icon' => 'bi-newspaper', 'module' => 'berita_sekolah'],
        ['label' => 'Galeri Sekolah', 'icon' => 'bi-images', 'module' => 'galeri_sekolah'],
    ],
    'Akademik' => [
        ['label' => 'Absensi Siswa', 'icon' => 'bi-clipboard2-check-fill', 'module' => 'absensi'],
        ['label' => 'Nilai Siswa', 'icon' => 'bi-journal-check', 'module' => 'nilai'],
        ['label' => 'Kelas Binaan', 'icon' => 'bi-people-fill', 'module' => 'kelas_binaan'],
        ['label' => 'Rencana Ajar', 'icon' => 'bi-journal-bookmark-fill', 'module' => 'rencana_ajar'],
        ['label' => 'Kalender Akademik', 'icon' => 'bi-calendar3', 'module' => 'kalender_akademik'],
        ['label' => 'Kokurikuler (P5)', 'icon' => 'bi-stars', 'module' => 'kokurikuler'],
    ],
    'Data Master' => [
        ['label' => 'Siswa', 'icon' => 'bi-person-vcard-fill', 'module' => 'siswa'],
        ['label' => 'Guru', 'icon' => 'bi-person-badge-fill', 'module' => 'guru'],
        ['label' => 'Kelas / Rombel', 'icon' => 'bi-door-open-fill', 'module' => 'kelas'],
        ['label' => 'Mata Pelajaran', 'icon' => 'bi-book-half', 'module' => 'mapel'],
        ['label' => 'Tahun Ajaran', 'icon' => 'bi-calendar2-range-fill', 'module' => 'tahun_ajaran'],
    ],
    'Laporan' => [
        ['label' => 'Laporan Sekolah', 'icon' => 'bi-file-earmark-bar-graph-fill', 'href' => 'reports.php'],
        ['label' => 'Notifikasi', 'icon' => 'bi-bell-fill', 'module' => 'notifikasi'],
    ],
    'Kontrol Sistem' => [
        ['label' => 'Persetujuan Konten', 'icon' => 'bi-shield-check', 'href' => 'moderation.php', 'module' => 'moderasi_konten'],
        ['label' => 'Manajemen Pengguna', 'icon' => 'bi-person-gear', 'href' => 'users.php', 'module' => 'pengguna'],
        ['label' => 'Log Aktivitas', 'icon' => 'bi-clock-history', 'href' => 'activity.php'],
        ['label' => 'Pemantauan Materi & Tugas', 'icon' => 'bi-mortarboard', 'href' => 'learning.php?view=materi'],
        ['label' => 'Pengaturan Sekolah', 'icon' => 'bi-gear-fill', 'module' => 'profil_sekolah'],
        ['label' => 'Profil Admin', 'icon' => 'bi-person-circle', 'href' => 'profile.php', 'module' => 'profil'],
        ['label' => 'Pemeriksaan Biodata', 'icon' => 'bi-person-vcard', 'href' => 'biodata.php'],
        ['label' => 'Reset Password', 'icon' => 'bi-key', 'href' => 'passwords.php'],
    ],
];
