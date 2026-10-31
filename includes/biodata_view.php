<?php
$roleSections = match ($targetRole) {
    'siswa' => [
        'Identitas Pribadi' => ['email', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan', 'anak_ke', 'jumlah_saudara', 'bahasa_sehari_hari'],
        'Alamat Siswa' => ['alamat', 'provinsi', 'kabupaten', 'kecamatan', 'distrik', 'kampung', 'rt_rw', 'kode_pos', 'jarak_rumah', 'transportasi'],
        'Data Ayah' => ['nama_ayah', 'nik_ayah', 'tempat_lahir_ayah', 'tanggal_lahir_ayah', 'pendidikan_ayah', 'pekerjaan_ayah', 'penghasilan_ayah', 'telepon_ayah', 'status_ayah'],
        'Data Ibu' => ['nama_ibu', 'nik_ibu', 'tempat_lahir_ibu', 'tanggal_lahir_ibu', 'pendidikan_ibu', 'pekerjaan_ibu', 'penghasilan_ibu', 'telepon_ibu', 'status_ibu'],
        'Data Wali' => ['nama_wali', 'hubungan_wali', 'nik_wali', 'pendidikan_wali', 'pekerjaan_wali', 'telepon_wali', 'alamat_wali', 'nama_kontak_darurat', 'telepon_kontak_darurat'],
        'Riwayat Pendidikan' => ['asal_sekolah', 'npsn_sekolah_asal', 'nomor_ijazah_sd', 'nomor_skhun', 'tahun_lulus_sd', 'tanggal_masuk_smp', 'semester'],
        'Data Kesehatan (opsional)' => ['golongan_darah', 'tinggi_badan', 'berat_badan', 'riwayat_alergi', 'kebutuhan_khusus', 'catatan_kesehatan'],
        'Data Tambahan' => ['hobi', 'cita_cita', 'prestasi_akademik', 'prestasi_nonakademik', 'ekstrakurikuler', 'organisasi'],
        'Dokumen Pendukung' => ['dokumen_akta_path', 'dokumen_kk_path', 'dokumen_identitas_ortu_path', 'dokumen_ijazah_sd_path', 'dokumen_kip_path', 'dokumen_pendukung_path'],
    ],
    'guru' => [
        'Identitas Pribadi' => ['tempat_lahir', 'tanggal_lahir', 'nuptk', 'gelar_depan', 'gelar_belakang', 'agama_guru', 'status_perkawinan', 'kewarganegaraan_guru', 'nik_guru', 'nomor_kk_guru'],
        'Kontak & Alamat' => ['telepon', 'whatsapp', 'email_pribadi', 'alamat', 'provinsi_guru', 'kabupaten_guru', 'kecamatan_guru', 'distrik_guru', 'kampung_guru', 'rt_rw_guru', 'kode_pos_guru'],
        'Pendidikan' => ['pendidikan', 'perguruan_tinggi', 'fakultas', 'program_studi', 'gelar_akademik', 'tahun_masuk_kuliah', 'tahun_lulus_kuliah', 'nomor_ijazah', 'bidang_keahlian', 'sertifikasi_pendidik', 'nomor_sertifikat_pendidik'],
        'Data Kepegawaian' => ['status_kepegawaian', 'jenis_guru', 'jabatan_guru', 'pangkat_golongan', 'tmt', 'tanggal_mulai_bertugas', 'masa_kerja', 'nomor_sk_pengangkatan', 'tanggal_sk_pengangkatan', 'instansi_induk'],
        'Penugasan di Sekolah' => ['jabatan_tambahan', 'semester'],
    ],
    'kepala_sekolah' => [
        'Identitas Pribadi' => ['nip_kepala', 'tempat_lahir', 'tanggal_lahir', 'nuptk', 'gelar_depan', 'gelar_belakang', 'agama_guru', 'kewarganegaraan_guru', 'nik_guru', 'status_perkawinan'],
        'Kontak & Alamat' => ['telepon', 'whatsapp', 'email_dinas_kepsek', 'email_pribadi', 'alamat', 'provinsi_guru', 'kabupaten_guru', 'kecamatan_guru', 'distrik_guru', 'kampung_guru', 'rt_rw_guru', 'kode_pos_guru'],
        'Riwayat Pendidikan' => ['pendidikan', 'perguruan_tinggi', 'fakultas', 'program_studi', 'gelar_akademik', 'tahun_masuk_kuliah', 'tahun_lulus_kuliah', 'nomor_ijazah', 'bidang_keahlian', 'pendidikan_pelatihan'],
        'Data Kepegawaian' => ['status_kepegawaian', 'jenis_guru', 'pangkat_golongan', 'jabatan_guru', 'tmt', 'tanggal_mulai_bertugas', 'masa_kerja', 'nomor_sk_pengangkatan', 'tanggal_sk_pengangkatan', 'instansi_induk'],
        'Jabatan Kepala Sekolah' => ['jabatan_saat_ini', 'sk_kepala_sekolah', 'tanggal_sk_kepala', 'tanggal_mulai_menjabat', 'periode_jabatan', 'status_jabatan', 'jenjang_pendidikan'],
        'Sertifikasi & Kompetensi' => ['sertifikasi_pendidik', 'nomor_sertifikat_pendidik', 'bidang_sertifikasi', 'nrg', 'sertifikat_kompetensi_kepsek', 'pelatihan_kepemimpinan', 'pelatihan_manajemen_sekolah', 'pelatihan_kurikulum', 'pengembangan_keprofesian'],
        'Riwayat Jabatan' => ['jabatan_sebelumnya', 'instansi_sebelumnya', 'tahun_mulai_jabatan', 'tahun_selesai_jabatan', 'nomor_sk_jabatan', 'keterangan_jabatan'],
    ],
    'operator' => ['Data Pribadi' => ['telepon', 'whatsapp', 'tempat_lahir', 'tanggal_lahir'], 'Pendidikan' => ['pendidikan'], 'Kontak & Alamat' => ['alamat']],
    default => ['Data Pribadi' => ['telepon', 'whatsapp', 'tempat_lahir', 'tanggal_lahir'], 'Pendidikan' => ['pendidikan'], 'Kontak & Alamat' => ['alamat']],
};
$sectionIcons = ['Data Pribadi' => 'bi-person-lines-fill', 'Orang Tua / Wali' => 'bi-people', 'Pendidikan' => 'bi-mortarboard', 'Kontak & Alamat' => 'bi-geo-alt'];
$profileName = $target['nama_guru'] ?: ($target['nama_siswa'] ?: $target['nama_lengkap']);
if (in_array($targetRole, ['guru', 'kepala_sekolah'], true)) $profileName = trim(trim((string)($profile['gelar_depan'] ?? '') . ' ' . $profileName) . (!empty($profile['gelar_belakang']) ? ', ' . trim((string)$profile['gelar_belakang']) : ''));
$statusKey = (string)($profile['status_verifikasi'] ?? 'belum_lengkap');
$statusClass = $statusKey === 'terverifikasi' ? 'is-verified' : (in_array($statusKey, ['perlu_perbaikan', 'ditolak'], true) ? 'is-attention' : '');
$genderValue = $isLinkedStudent ? ($target['siswa_jenis_kelamin'] ?? '') : ($isLinkedTeacher ? ($target['guru_jenis_kelamin'] ?? '') : '');
$genderLabel = $genderValue === 'L' ? 'Laki-laki' : ($genderValue === 'P' ? 'Perempuan' : 'Belum dicatat');
$showBiodataForm = ($isReviewer && $targetId !== $userId) || $error !== '' || isset($_GET['menunggu']);
?>
<div class="dashboard-heading">
    <div><span class="account-eyebrow">Identitas dan kelengkapan data</span>
        <h1><?= e($pageTitle) ?></h1>
        <p>Data biodata dipisahkan dari akun login dan menyesuaikan peran Anda.</p>
    </div>
</div>
<?php if ($flashMessage): ?><div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div><?php endif; ?>
<?php if (isset($_GET['menunggu']) && $targetId === $userId): ?><div class="alert alert-info" role="status"><strong>Akses dashboard menunggu pemeriksaan biodata.</strong> Lengkapi bagian wajib lalu kirim untuk diperiksa. Status saat ini: <?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?>.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="account-profile-page biodata-profile-page">
    <section class="card account-profile-hero biodata-profile-hero <?= $targetRole === 'guru' ? 'biodata-teacher-layout' : ($targetRole === 'siswa' ? 'biodata-student-layout' : ($targetRole === 'kepala_sekolah' ? 'biodata-principal-layout' : '')) ?>">
        <div class="biodata-summary-person <?= $targetRole === 'guru' ? 'biodata-teacher-photo' : '' ?>">
            <?php if ($targetId === $userId && !empty($target['foto_path'])): ?><img class="biodata-summary-avatar account-hero-photo" src="media/profile.php?id=<?= $userId ?>" alt="Foto <?= e($profileName) ?>"><?php else: ?><span class="biodata-summary-avatar account-hero-photo account-hero-photo-fallback" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($profileName, 0, 1))) ?></span><?php endif; ?>
            <?php if (!in_array($targetRole, ['guru', 'siswa', 'kepala_sekolah'], true)): ?><div class="biodata-hero-name"><span class="account-hero-kicker"><i class="bi bi-card-text"></i> Biodata <?= e(role_label($targetRole)) ?></span>
                    <h2><?= e($profileName) ?></h2>
                    <p><?= e(role_label($targetRole)) ?> · @<?= e($target['username']) ?></p><small><?= $target['nip'] ? 'NIP ' . e($target['nip']) : ($target['nis'] ? 'NIS ' . e($target['nis']) : 'Profil biodata SIMS') ?></small>
                </div><?php endif; ?>
        </div>
        <?php if ($targetRole === 'guru'): ?><section class="biodata-teacher-summary" aria-label="Ringkasan biodata guru"><span class="account-hero-kicker"><i class="bi bi-card-text"></i> Biodata Guru</span>
                <h2><?= e($profileName) ?></h2>
                <p class="biodata-teacher-username">Guru · @<?= e($target['username']) ?></p>
                <dl>
                    <div>
                        <dt>NIP</dt>
                        <dd><?= e($target['nip'] ?: 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= e($target['email_guru'] ?: 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Alamat</dt>
                        <dd><?= e($profile['alamat'] ?: ($target['guru_alamat'] ?? 'Belum dicatat')) ?></dd>
                    </div>
                </dl><span class="biodata-status-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span><button type="button" class="btn btn-outline-primary biodata-view-button" id="biodataDetailToggle" aria-haspopup="dialog" aria-controls="biodataDetailPanel"><i class="bi bi-eye me-1"></i>Lihat biodata lengkap</button>
            </section><?php endif; ?>
        <?php if ($targetRole === 'kepala_sekolah'): ?><section class="biodata-teacher-summary biodata-principal-summary" aria-label="Ringkasan biodata Kepala Sekolah"><span class="account-hero-kicker"><i class="bi bi-card-text"></i> Biodata Kepala Sekolah</span>
                <h2><?= e($profileName) ?></h2>
                <p class="biodata-teacher-username">Kepala Sekolah · @<?= e($target['username']) ?></p>
                <dl>
                    <div>
                        <dt>NIP</dt>
                        <dd><?= e($target['nip'] ?: ($profile['nip_kepala'] ?? 'Belum dicatat')) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= e($profile['email_dinas_kepsek'] ?: ($target['email_guru'] ?: ($schoolProfile['email'] ?? ($profile['email_pribadi'] ?? 'Belum dicatat')))) ?></dd>
                    </div>
                    <div>
                        <dt>Alamat</dt>
                        <dd><?= e($profile['alamat'] ?: ($target['guru_alamat'] ?? 'Belum dicatat')) ?></dd>
                    </div>
                </dl><span class="biodata-status-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span><button type="button" class="btn btn-outline-primary biodata-view-button" id="biodataDetailToggle" aria-haspopup="dialog" aria-controls="biodataDetailPanel"><i class="bi bi-eye me-1"></i>Lihat biodata lengkap</button>
            </section><?php endif; ?>
        <?php if ($targetRole === 'siswa'): ?><section class="biodata-teacher-summary biodata-student-summary" aria-label="Ringkasan biodata siswa"><span class="account-hero-kicker"><i class="bi bi-card-text"></i> Biodata Siswa/Siswi</span>
                <h2><?= e($profileName) ?></h2>
                <p class="biodata-teacher-username">Siswa · @<?= e($target['username']) ?></p>
                <dl>
                    <div>
                        <dt>NIS</dt>
                        <dd><?= e($target['nis'] ?: 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= e($profile['email'] ?? 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Alamat</dt>
                        <dd><?= e($profile['alamat'] ?: ($target['siswa_alamat'] ?? 'Belum dicatat')) ?></dd>
                    </div>
                </dl><span class="biodata-status-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span><button type="button" class="btn btn-outline-primary biodata-view-button" id="biodataDetailToggle" aria-haspopup="dialog" aria-controls="biodataDetailPanel"><i class="bi bi-eye me-1"></i>Lihat biodata lengkap</button>
            </section><?php endif; ?>
        <div class="biodata-status-line"><span>Kelengkapan biodata</span><strong><?= $completion ?>%</strong></div>
        <div class="progress" role="progressbar" aria-label="Kelengkapan biodata" aria-valuenow="<?= $completion ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width:<?= $completion ?>%"></div>
        </div>
        <span class="biodata-status-badge <?= e($statusClass) ?>"><i class="bi <?= $statusKey === 'terverifikasi' ? 'bi-patch-check-fill' : ($statusKey === 'menunggu_verifikasi' ? 'bi-hourglass-split' : 'bi-info-circle-fill') ?>"></i><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span>
        <?php if ($missing): ?><div class="biodata-review-note"><strong>Masih perlu dilengkapi</strong><br><?= e(implode(', ', array_map(static fn(string $field): string => $labels[$field] ?? $field, $missing))) ?></div><?php endif; ?>
        <?php if (!empty($profile['catatan_verifikasi'])): ?><div class="biodata-review-note"><strong>Catatan pemeriksa</strong><br><?= nl2br(e($profile['catatan_verifikasi'])) ?></div><?php endif; ?>
        <?php if ($isLinkedStudent && $targetRole !== 'siswa'): ?>
            <div class="biodata-master-data">
                <h3>Data sekolah · dikelola Operator</h3>
                <div><span>NIS</span><strong><?= e($target['nis'] ?: '—') ?></strong></div>
                <div><span>NISN</span><strong><?= e($target['nisn'] ?: 'Belum dicatat') ?></strong></div>
                <div><span>Jenis kelamin</span><strong><?= e($genderLabel) ?></strong></div>
                <div><span>Kelas</span><strong><?= e($target['nama_kelas'] ?: 'Belum ditetapkan') ?></strong></div>
                <div><span>Tahun ajaran</span><strong><?= e($target['tahun_ajaran'] ?: 'Belum ditetapkan') ?></strong></div>
                <div><span>Status</span><strong><?= e($target['siswa_status'] ?: '—') ?></strong></div>
                <div><span>Tanggal lahir</span><strong><?= e($target['siswa_tanggal_lahir'] ?: 'Belum dicatat') ?></strong></div>
                <div><span>Alamat induk</span><strong><?= e($target['siswa_alamat'] ?: 'Belum dicatat') ?></strong></div>
            </div>
        <?php elseif ($isLinkedTeacher && $targetRole !== 'guru'): ?>
            <div class="biodata-master-data">
                <h3>Data kepegawaian · dikelola Operator</h3>
                <div><span>NIP</span><strong><?= e($target['nip'] ?: 'Belum dicatat') ?></strong></div>
                <div><span>Jenis kelamin</span><strong><?= e($genderLabel) ?></strong></div>
                <div><span>Status</span><strong><?= e($target['guru_status'] ?: '—') ?></strong></div>
                <div><span>Email kerja</span><strong><?= e($target['email_guru'] ?: 'Belum dicatat') ?></strong></div>
                <div><span>Alamat induk</span><strong><?= e($target['guru_alamat'] ?: 'Belum dicatat') ?></strong></div><?php if ($teacherAssignments): ?><div><span>Penugasan aktif</span><strong><?= e(implode(', ', array_map(static fn($item): string => $item['nama_mapel'] . ' · ' . $item['nama_kelas'], $teacherAssignments))) ?></strong></div><?php else: ?><div><span>Penugasan aktif</span><strong>Belum ada jadwal aktif</strong></div><?php endif; ?>
            </div>
        <?php elseif ($targetRole === 'operator'): ?>
            <div class="biodata-master-data">
                <h3>Informasi pekerjaan</h3>
                <div><span>Peran akun</span><strong><?= e(role_label($targetRole)) ?></strong></div>
                <div><span>Jabatan / unit</span><strong>Belum tersedia sebagai data terpisah</strong></div>
            </div>
        <?php endif; ?>
        <div class="biodata-hero-actions no-print"><button class="btn btn-outline-primary biodata-print-button" type="button" id="biodataPrintButton"><i class="bi bi-printer me-1"></i>Cetak Biodata</button><button class="btn btn-primary account-edit-toggle biodata-edit-toggle" type="button" id="biodataFormToggle" aria-expanded="<?= $showBiodataForm ? 'true' : 'false' ?>" aria-controls="biodataFormPanel"><i class="bi <?= $showBiodataForm ? 'bi-dash-lg' : 'bi-plus-lg' ?>" id="biodataFormToggleIcon"></i><span id="biodataFormToggleLabel"><?= $showBiodataForm ? 'Tutup formulir biodata' : ($statusKey === 'terverifikasi' ? 'Ubah Biodata' : 'Lengkapi Biodata') ?></span></button></div>
    </section>
    <?php if ($targetRole === 'guru'): ?>
        <div class="account-edit-modal biodata-detail-modal" id="biodataDetailPanel" hidden>
            <button class="account-modal-backdrop" type="button" data-biodata-detail-close aria-label="Tutup rincian biodata"></button>
            <section class="card biodata-detail-card" role="dialog" aria-modal="true" aria-labelledby="biodataDetailTitle" tabindex="-1">
                <header class="account-modal-heading">
                    <div><span class="account-eyebrow">Rincian biodata</span>
                        <h2 id="biodataDetailTitle">Biodata Guru</h2>
                        <p>Data biodata, kepegawaian, dan penugasan yang tercatat di SIMS.</p>
                    </div><button class="account-modal-close" type="button" data-biodata-detail-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                </header>
                <div class="biodata-detail-body">
                    <div class="biodata-detail-person"><?php if ($targetId === $userId && !empty($target['foto_path'])): ?><img src="media/profile.php?id=<?= $userId ?>" alt="Foto <?= e($profileName) ?>"><?php endif; ?><div>
                            <h3><?= e($profileName) ?></h3>
                            <p>Guru · @<?= e($target['username']) ?></p><span class="biodata-status-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span>
                        </div>
                    </div>
                    <section class="biodata-detail-group">
                        <h3>Identitas dan kontak</h3>
                        <dl>
                            <div>
                                <dt>Nama lengkap</dt>
                                <dd><?= e($profileName) ?></dd>
                            </div>
                            <div>
                                <dt>NIP</dt>
                                <dd><?= e($target['nip'] ?: 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>NUPTK</dt>
                                <dd><?= e($profile['nuptk'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Jenis kelamin</dt>
                                <dd><?= e($genderLabel) ?></dd>
                            </div>
                            <div>
                                <dt>Email kerja</dt>
                                <dd><?= e($target['email_guru'] ?: 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Email pribadi</dt>
                                <dd><?= e($profile['email_pribadi'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Alamat</dt>
                                <dd><?= nl2br(e($profile['alamat'] ?: ($target['guru_alamat'] ?? 'Belum dicatat'))) ?></dd>
                            </div>
                        </dl>
                    </section>
                    <?php foreach ($roleSections as $sectionName => $sectionFields): ?><section class="biodata-detail-group">
                            <h3><?= e($sectionName) ?></h3>
                            <dl><?php foreach ($sectionFields as $field): $detailValue = $profile[$field] ?? '';
                                    if ($field === 'nip_kepala' && $detailValue === '') $detailValue = $target['nip'] ?? '';
                                    if ($field === 'alamat' && $detailValue === '') $detailValue = $target['guru_alamat'] ?? ''; ?><div>
                                        <dt><?= e($labels[$field] ?? $field) ?></dt>
                                        <dd><?= $detailValue !== '' ? nl2br(e($detailValue)) : 'Belum diisi' ?></dd>
                                    </div><?php endforeach; ?></dl>
                        </section><?php endforeach; ?>
                    <section class="biodata-detail-group">
                        <h3>Penugasan sekolah · dikelola Operator</h3>
                        <dl>
                            <div>
                                <dt>Mata pelajaran dan kelas</dt>
                                <dd><?= e($teacherAssignments ? implode(', ', array_map(static fn($item): string => $item['nama_mapel'] . ' · ' . $item['nama_kelas'] . ' (' . $item['tahun_ajaran'] . ')', $teacherAssignments)) : 'Belum ada jadwal aktif') ?></dd>
                            </div>
                            <div>
                                <dt>Wali kelas</dt>
                                <dd><?= e($teacherHomerooms ? implode(', ', array_map(static fn($item): string => $item['nama_kelas'] . ' (' . $item['tahun_ajaran'] . ')', $teacherHomerooms)) : 'Bukan wali kelas pada tahun ajaran aktif') ?></dd>
                            </div>
                            <div>
                                <dt>Tahun ajaran aktif</dt>
                                <dd><?= e($teacherAssignments[0]['tahun_ajaran'] ?? $teacherHomerooms[0]['tahun_ajaran'] ?? 'Belum ditetapkan') ?></dd>
                            </div>
                            <div>
                                <dt>Semester</dt>
                                <dd><?= e($profile['semester'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Beban mengajar terjadwal</dt>
                                <dd><?= $teacherWorkloadJp ?> JP per minggu</dd>
                            </div>
                            <div>
                                <dt>Status penugasan</dt>
                                <dd><?= ($teacherAssignments || $teacherHomerooms) ? 'Memiliki penugasan aktif' : 'Belum ada penugasan aktif' ?></dd>
                            </div>
                            <div>
                                <dt>Status keaktifan</dt>
                                <dd><?= e($target['guru_status'] ?: 'Belum dicatat') ?></dd>
                            </div>
                        </dl>
                        <p class="biodata-detail-note">Mata pelajaran, kelas, wali kelas, beban mengajar, serta status penugasan mengikuti data jadwal yang dikelola Operator.</p>
                    </section>
                </div>
            </section>
        </div>
    <?php endif; ?>
    <?php if ($targetRole === 'kepala_sekolah'): ?>
        <div class="account-edit-modal biodata-detail-modal" id="biodataDetailPanel" hidden>
            <button class="account-modal-backdrop" type="button" data-biodata-detail-close aria-label="Tutup rincian biodata"></button>
            <section class="card biodata-detail-card" role="dialog" aria-modal="true" aria-labelledby="biodataDetailTitle" tabindex="-1">
                <header class="account-modal-heading">
                    <div><span class="account-eyebrow">Rincian biodata</span>
                        <h2 id="biodataDetailTitle">Biodata Kepala Sekolah</h2>
                        <p>Identitas, pendidikan, kepegawaian, jabatan, dan kompetensi.</p>
                    </div><button class="account-modal-close" type="button" data-biodata-detail-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                </header>
                <div class="biodata-detail-body">
                    <div class="biodata-detail-person"><?php if ($targetId === $userId && !empty($target['foto_path'])): ?><img src="media/profile.php?id=<?= $userId ?>" alt="Foto <?= e($profileName) ?>"><?php endif; ?><div>
                            <h3><?= e($profileName) ?></h3>
                            <p>Kepala Sekolah · @<?= e($target['username']) ?></p><span class="biodata-status-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span>
                        </div>
                    </div>
                    <section class="biodata-detail-group">
                        <h3>Identitas dan status akun</h3>
                        <dl>
                            <div>
                                <dt>Nama lengkap</dt>
                                <dd><?= e($profileName) ?></dd>
                            </div>
                            <div>
                                <dt>NIP</dt>
                                <dd><?= e($target['nip'] ?: ($profile['nip_kepala'] ?? 'Belum dicatat')) ?></dd>
                            </div>
                            <div>
                                <dt>NUPTK</dt>
                                <dd><?= e($profile['nuptk'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Jenis kelamin</dt>
                                <dd><?= e($genderLabel) ?></dd>
                            </div>
                            <div>
                                <dt>Status akun</dt>
                                <dd><?= !empty($target['aktif']) ? 'Aktif' : 'Tidak aktif' ?></dd>
                            </div>
                            <div>
                                <dt>ID akun</dt>
                                <dd>#<?= (int)$target['id'] ?></dd>
                            </div>
                            <div>
                                <dt>Tanggal akun dibuat</dt>
                                <dd><?= e(date('d/m/Y', strtotime((string)$target['created_at']))) ?></dd>
                            </div>
                            <div>
                                <dt>Login terakhir</dt>
                                <dd><?= !empty($target['last_login_at']) ? e(date('d/m/Y H:i', strtotime((string)$target['last_login_at']))) : 'Belum tercatat' ?></dd>
                            </div>
                        </dl>
                    </section>
                    <?php foreach ($roleSections as $sectionName => $sectionFields): ?><section class="biodata-detail-group">
                            <h3><?= e($sectionName) ?></h3>
                            <dl><?php foreach ($sectionFields as $field): ?><div>
                                        <dt><?= e($labels[$field] ?? $field) ?></dt>
                                        <dd><?= nl2br(e($profile[$field] ?? 'Belum diisi')) ?></dd>
                                    </div><?php endforeach; ?></dl>
                        </section><?php endforeach; ?>
                    <section class="biodata-detail-group">
                        <h3>Identitas Sekolah · dikelola Operator</h3>
                        <dl>
                            <div>
                                <dt>Nama sekolah</dt>
                                <dd><?= e($schoolProfile['nama'] ?? 'Belum diatur') ?></dd>
                            </div>
                            <div>
                                <dt>NPSN</dt>
                                <dd><?= e($schoolProfile['npsn'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Jenjang pendidikan</dt>
                                <dd><?= e($profile['jenjang_pendidikan'] ?: 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Alamat sekolah</dt>
                                <dd><?= nl2br(e($schoolProfile['alamat'] ?? 'Belum diatur')) ?></dd>
                            </div>
                            <div>
                                <dt>Email dinas sekolah</dt>
                                <dd><?= e($profile['email_dinas_kepsek'] ?: ($target['email_guru'] ?: ($schoolProfile['email'] ?? 'Belum dicatat'))) ?></dd>
                            </div>
                            <div>
                                <dt>Status keaktifan pegawai</dt>
                                <dd><?= !empty($target['guru_status']) ? e($target['guru_status']) : (!empty($target['aktif']) ? 'Aktif' : 'Tidak aktif') ?></dd>
                            </div>
                        </dl>
                        <p class="biodata-detail-note">Nama sekolah, NPSN, alamat, dan email umum sekolah mengikuti Pengaturan Sekolah. NIP dan jenis kelamin diambil dari Data Guru jika akun telah ditautkan ke data pegawai.</p>
                    </section>
                </div>
            </section>
        </div>
    <?php endif; ?>
    <?php if ($targetRole === 'siswa'): ?>
        <div class="account-edit-modal biodata-detail-modal" id="biodataDetailPanel" hidden>
            <button class="account-modal-backdrop" type="button" data-biodata-detail-close aria-label="Tutup rincian biodata"></button>
            <section class="card biodata-detail-card" role="dialog" aria-modal="true" aria-labelledby="biodataDetailTitle" tabindex="-1">
                <header class="account-modal-heading">
                    <div><span class="account-eyebrow">Rincian biodata</span>
                        <h2 id="biodataDetailTitle">Biodata Siswa/Siswi</h2>
                        <p>Seluruh data siswa yang saat ini tercatat di SIMS.</p>
                    </div><button class="account-modal-close" type="button" data-biodata-detail-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
                </header>
                <div class="biodata-detail-body">
                    <div class="biodata-detail-person"><?php if ($targetId === $userId && !empty($target['foto_path'])): ?><img src="media/profile.php?id=<?= $userId ?>" alt="Foto <?= e($profileName) ?>"><?php endif; ?><div>
                            <h3><?= e($profileName) ?></h3>
                            <p>Siswa · @<?= e($target['username']) ?></p><span class="biodata-status-badge <?= e($statusClass) ?>"><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></span>
                        </div>
                    </div>
                    <section class="biodata-detail-group">
                        <h3>Identitas pribadi</h3>
                        <dl>
                            <div>
                                <dt>Nama lengkap</dt>
                                <dd><?= e($profileName) ?></dd>
                            </div>
                            <div>
                                <dt>NIS</dt>
                                <dd><?= e($target['nis'] ?: 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>NISN</dt>
                                <dd><?= e($target['nisn'] ?: 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Jenis kelamin</dt>
                                <dd><?= e($genderLabel) ?></dd>
                            </div>
                            <div>
                                <dt>Tempat lahir</dt>
                                <dd><?= e($profile['tempat_lahir'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Tanggal lahir</dt>
                                <dd><?= e($target['siswa_tanggal_lahir'] ?: ($profile['tanggal_lahir'] ?? 'Belum dicatat')) ?></dd>
                            </div>
                        </dl>
                    </section>
                    <section class="biodata-detail-group">
                        <h3>Alamat dan kontak</h3>
                        <dl>
                            <div>
                                <dt>Email</dt>
                                <dd><?= e($profile['email'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Alamat lengkap</dt>
                                <dd><?= nl2br(e($profile['alamat'] ?: ($target['siswa_alamat'] ?? 'Belum dicatat'))) ?></dd>
                            </div>
                            <div>
                                <dt>Nomor telepon</dt>
                                <dd><?= e($profile['telepon'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>WhatsApp</dt>
                                <dd><?= e($profile['whatsapp'] ?? 'Belum dicatat') ?></dd>
                            </div>
                        </dl>
                    </section>
                    <section class="biodata-detail-group">
                        <h3>Data orang tua/wali</h3>
                        <dl>
                            <div>
                                <dt>Nama orang tua/wali</dt>
                                <dd><?= e($profile['nama_kontak_darurat'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Nomor kontak</dt>
                                <dd><?= e($profile['telepon_kontak_darurat'] ?? 'Belum dicatat') ?></dd>
                            </div>
                        </dl>
                    </section>
                    <section class="biodata-detail-group">
                        <h3>Data sekolah</h3>
                        <dl>
                            <div>
                                <dt>Kelas/rombel</dt>
                                <dd><?= e($target['nama_kelas'] ?: 'Belum ditetapkan') ?></dd>
                            </div>
                            <div>
                                <dt>Wali kelas</dt>
                                <dd><?= e($target['wali_kelas'] ?: 'Belum ditetapkan') ?></dd>
                            </div>
                            <div>
                                <dt>Tahun ajaran</dt>
                                <dd><?= e($target['tahun_ajaran'] ?: 'Belum ditetapkan') ?></dd>
                            </div>
                            <div>
                                <dt>Semester</dt>
                                <dd><?= e($profile['semester'] ?? 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Tahun masuk</dt>
                                <dd><?= e($target['tahun_masuk'] ?: 'Belum dicatat') ?></dd>
                            </div>
                            <div>
                                <dt>Status siswa</dt>
                                <dd><?= e($target['siswa_status'] ?: 'Belum dicatat') ?></dd>
                            </div>
                        </dl>
                    </section>
                    <section class="biodata-detail-group">
                        <h3>Informasi akun</h3>
                        <dl>
                            <div>
                                <dt>Username</dt>
                                <dd><?= e($target['username']) ?></dd>
                            </div>
                            <div>
                                <dt>Status akun</dt>
                                <dd><?= !empty($target['aktif']) ? 'Aktif' : 'Tidak aktif' ?></dd>
                            </div>
                            <div>
                                <dt>ID akun</dt>
                                <dd>#<?= (int)$target['id'] ?></dd>
                            </div>
                            <div>
                                <dt>Tanggal akun dibuat</dt>
                                <dd><?= e(date('d/m/Y', strtotime((string)$target['created_at']))) ?></dd>
                            </div>
                            <div>
                                <dt>Login terakhir</dt>
                                <dd><?= !empty($target['last_login_at']) ? e(date('d/m/Y H:i', strtotime((string)$target['last_login_at']))) : 'Belum tercatat' ?></dd>
                            </div>
                        </dl>
                    </section>
                    <?php foreach ($roleSections as $sectionName => $sectionFields): ?><section class="biodata-detail-group">
                            <h3><?= e($sectionName) ?></h3>
                            <dl><?php foreach ($sectionFields as $field): ?><div>
                                        <dt><?= e($labels[$field] ?? $field) ?></dt>
                                        <dd><?php if (str_starts_with($field, 'dokumen_')): ?><?= !empty($profile[$field]) ? '<a href="media/biodata_document.php?user=' . $targetId . '&amp;field=' . e($field) . '" target="_blank" rel="noopener">Lihat dokumen</a>' : 'Belum diunggah' ?><?php else: ?><?= nl2br(e($profile[$field] ?? 'Belum diisi')) ?><?php endif; ?></dd>
                                    </div><?php endforeach; ?></dl>
                        </section><?php endforeach; ?>
                    <p class="biodata-detail-note">NIS/NISN, kelas, tahun ajaran, status siswa, dan tanggal lahir mengikuti data induk siswa yang dikelola Operator. Email akun dapat diisi pada bagian Identitas Pribadi.</p>
                </div>
            </section>
        </div>
    <?php endif; ?>
    <section class="biodata-print-sheet" id="biodataPrintSheet" aria-label="Dokumen biodata untuk dicetak">
        <header class="biodata-print-header">
            <div>
                <div class="biodata-print-school">SIMS SMP NEGERI 4 PIRIME</div>
                <h1>BIODATA <?= e(mb_strtoupper(role_label($targetRole))) ?></h1>
                <p>Dokumen biodata pengguna</p>
            </div><?php if ($targetId === $userId && !empty($target['foto_path'])): ?><img src="media/profile.php?id=<?= $userId ?>" alt="Foto <?= e($profileName) ?>"><?php endif; ?>
        </header>
        <dl class="biodata-print-identity">
            <div>
                <dt>Nama lengkap</dt>
                <dd><?= e($profileName) ?></dd>
            </div>
            <div>
                <dt>Username</dt>
                <dd>@<?= e($target['username']) ?></dd>
            </div>
            <div>
                <dt>Peran</dt>
                <dd><?= e(role_label($targetRole)) ?></dd>
            </div><?php if ($isLinkedTeacher && $target['nip']): ?><div>
                    <dt>NIP</dt>
                    <dd><?= e($target['nip']) ?></dd>
                </div><?php elseif ($targetRole === 'kepala_sekolah'): ?><div>
                    <dt>NIP</dt>
                    <dd><?= e($target['nip'] ?: ($profile['nip_kepala'] ?? 'Belum dicatat')) ?></dd>
                </div><?php elseif ($isLinkedStudent): ?><div>
                    <dt>NIS</dt>
                    <dd><?= e($target['nis'] ?: 'Belum dicatat') ?></dd>
                </div>
                <div>
                    <dt>NISN</dt>
                    <dd><?= e($target['nisn'] ?: 'Belum dicatat') ?></dd>
                </div><?php endif; ?><div>
                <dt>Status biodata</dt>
                <dd><?= e($statusLabels[$statusKey] ?? 'Belum lengkap') ?></dd>
            </div>
        </dl>
        <?php foreach ($roleSections as $sectionName => $sectionFields): ?><section class="biodata-print-group">
                <h2><?= e($sectionName) ?></h2>
                <dl><?php foreach ($sectionFields as $field): $printValue = trim((string)($profile[$field] ?? ''));
                        if ($field === 'tanggal_lahir' && $isLinkedStudent) $printValue = (string)($profile[$field] ?: ($target['siswa_tanggal_lahir'] ?? $printValue));
                        if ($field === 'alamat' && $isLinkedStudent) $printValue = (string)($profile['alamat'] ?: ($target['siswa_alamat'] ?? $printValue));
                        if ($field === 'alamat' && $isLinkedTeacher) $printValue = (string)($profile['alamat'] ?: ($target['guru_alamat'] ?? $printValue));
                        if (str_starts_with($field, 'dokumen_')) $printValue = !empty($profile[$field]) ? 'Tersedia sebagai lampiran digital' : 'Belum diunggah'; ?><div>
                            <dt><?= e($labels[$field] ?? $field) ?></dt>
                            <dd><?= $printValue !== '' ? nl2br(e($printValue)) : 'Belum diisi' ?></dd>
                        </div><?php endforeach; ?></dl>
            </section><?php endforeach; ?>
        <?php if ($isLinkedStudent): ?><section class="biodata-print-group">
                <h2>Data Sekolah</h2>
                <dl>
                    <div>
                        <dt>Jenis kelamin</dt>
                        <dd><?= e($genderLabel) ?></dd>
                    </div>
                    <div>
                        <dt>Tanggal lahir</dt>
                        <dd><?= e($target['siswa_tanggal_lahir'] ?: 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Kelas</dt>
                        <dd><?= e($target['nama_kelas'] ?: 'Belum ditetapkan') ?></dd>
                    </div>
                    <div>
                        <dt>Tahun ajaran</dt>
                        <dd><?= e($target['tahun_ajaran'] ?: 'Belum ditetapkan') ?></dd>
                    </div>
                    <div>
                        <dt>Status siswa</dt>
                        <dd><?= e($target['siswa_status'] ?: '—') ?></dd>
                    </div>
                    <div>
                        <dt>Alamat induk</dt>
                        <dd><?= e($target['siswa_alamat'] ?: 'Belum dicatat') ?></dd>
                    </div>
                </dl>
            </section><?php elseif ($isLinkedTeacher): ?><section class="biodata-print-group">
                <h2>Data Kepegawaian dan Penugasan</h2>
                <dl>
                    <div>
                        <dt>Jenis kelamin</dt>
                        <dd><?= e($genderLabel) ?></dd>
                    </div>
                    <div>
                        <dt>Status keaktifan guru</dt>
                        <dd><?= e($target['guru_status'] ?: '—') ?></dd>
                    </div>
                    <div>
                        <dt>Email kerja</dt>
                        <dd><?= e($target['email_guru'] ?: 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Mata pelajaran dan kelas</dt>
                        <dd><?= e($teacherAssignments ? implode(', ', array_map(static fn($item): string => $item['nama_mapel'] . ' · ' . $item['nama_kelas'], $teacherAssignments)) : 'Belum ada jadwal aktif') ?></dd>
                    </div>
                    <div>
                        <dt>Wali kelas</dt>
                        <dd><?= e($teacherHomerooms ? implode(', ', array_map(static fn($item): string => $item['nama_kelas'], $teacherHomerooms)) : 'Tidak ditugaskan') ?></dd>
                    </div>
                    <div>
                        <dt>Tahun ajaran aktif</dt>
                        <dd><?= e($teacherAssignments[0]['tahun_ajaran'] ?? $teacherHomerooms[0]['tahun_ajaran'] ?? 'Belum ditetapkan') ?></dd>
                    </div>
                    <div>
                        <dt>Beban mengajar terjadwal</dt>
                        <dd><?= $teacherWorkloadJp ?> JP per minggu</dd>
                    </div>
                </dl>
            </section><?php endif; ?>
        <?php if ($targetRole === 'kepala_sekolah'): ?><section class="biodata-print-group">
                <h2>Profil Sekolah dan Status Kepegawaian</h2>
                <dl>
                    <div>
                        <dt>Email dinas</dt>
                        <dd><?= e($profile['email_dinas_kepsek'] ?: ($target['email_guru'] ?: ($schoolProfile['email'] ?? 'Belum dicatat'))) ?></dd>
                    </div>
                    <div>
                        <dt>Nama sekolah</dt>
                        <dd><?= e($schoolProfile['nama'] ?? 'Belum diatur') ?></dd>
                    </div>
                    <div>
                        <dt>NPSN</dt>
                        <dd><?= e($schoolProfile['npsn'] ?? 'Belum dicatat') ?></dd>
                    </div>
                    <div>
                        <dt>Alamat sekolah</dt>
                        <dd><?= e($schoolProfile['alamat'] ?? 'Belum diatur') ?></dd>
                    </div>
                    <div>
                        <dt>Status akun/keaktifan</dt>
                        <dd><?= !empty($target['aktif']) ? 'Aktif' : 'Tidak aktif' ?></dd>
                    </div>
                </dl>
            </section><?php endif; ?>
        <footer class="biodata-print-footer"><span>Dicetak pada <?= e(date('d/m/Y H:i')) ?></span><span>SIMS SMP Negeri 4 Pirime</span></footer>
    </section>
    <div class="account-edit-modal" id="biodataFormPanel" <?= $showBiodataForm ? '' : 'hidden' ?>><button class="account-modal-backdrop" type="button" data-biodata-modal-close aria-label="Tutup formulir biodata"></button>
        <section class="card biodata-form-card account-form-panel" role="dialog" aria-modal="true" aria-labelledby="biodataModalTitle" tabindex="-1">
            <header class="biodata-form-header account-modal-heading">
                <div><span class="account-eyebrow"><?= $isReviewer && $targetId !== $userId ? 'Pemeriksaan biodata' : 'Pengaturan biodata' ?></span>
                    <h2 id="biodataModalTitle"><?= $isReviewer && $targetId !== $userId ? 'Pemeriksaan biodata pengguna' : 'Biodata ' . e(role_label($targetRole)) ?></h2>
                    <p><?= $isReviewer && $targetId !== $userId ? 'Tinjau data yang dikirim dan berikan keputusan atau catatan koreksi.' : 'Isi data yang boleh Anda kelola. Data induk sekolah ditampilkan sebagai baca saja.' ?></p>
                </div><button class="account-modal-close" type="button" data-biodata-modal-close aria-label="Tutup"><i class="bi bi-x-lg"></i></button>
            </header>
            <?php if (!$isReviewer || $targetId === $userId): ?><nav class="biodata-tabs" aria-label="Bagian biodata"><?php $sectionIndex = 0;
                                                                                                                        foreach ($roleSections as $sectionName => $sectionFields): ?><a href="#bio-section-<?= $sectionIndex ?>"><i class="bi <?= e($sectionIcons[$sectionName] ?? 'bi-card-text') ?>"></i><?= e($sectionName) ?></a><?php $sectionIndex++;
                                                                                                                                                                                                                                                                                                                                                        endforeach; ?><?php if ($isLinkedStudent): ?><a href="#bio-school-data"><i class="bi bi-building"></i>Data Sekolah</a><?php elseif ($isLinkedTeacher): ?><a href="#bio-school-data"><i class="bi bi-journal-bookmark"></i>Penugasan</a><?php elseif ($targetRole === 'kepala_sekolah'): ?><a href="#bio-school-data"><i class="bi bi-building"></i>Data Sekolah</a><?php endif; ?></nav><?php endif; ?>
            <?php if ($isReviewer && $targetId !== $userId): ?>
                <div class="biodata-form-body">
                    <div class="row g-3"><?php foreach ($labels as $field => $label): $value = $profile[$field] ?? '';
                                                if ($field === 'tanggal_lahir' && $isLinkedStudent) $value = $target['siswa_tanggal_lahir'] ?? '';
                                                if ($field === 'alamat' && $isLinkedStudent) $value = $profile['alamat'] ?: ($target['siswa_alamat'] ?? '');
                                                if ($field === 'alamat' && $isLinkedTeacher) $value = $target['guru_alamat'] ?? ''; ?><div class="col-md-6">
                                <div class="biodata-readonly-panel"><small><?= e($label) ?></small><strong><?php if (str_starts_with($field, 'dokumen_')): ?><?= !empty($value) ? '<a href="media/biodata_document.php?user=' . $targetId . '&amp;field=' . e($field) . '" target="_blank" rel="noopener">Lihat dokumen</a>' : 'Belum diunggah' ?><?php else: ?><?= e($value !== '' ? $value : 'Belum diisi') ?><?php endif; ?></strong></div>
                            </div><?php endforeach; ?></div>
                    <?php if (!$auditReady): ?><div class="alert alert-warning mt-3">Riwayat pemeriksaan belum aktif. Keputusan belum dapat disimpan sampai migrasi riwayat tersedia.</div><?php endif; ?>
                    <form method="post" class="row g-3 mt-2"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review">
                        <div class="col-12"><label class="form-label" for="catatan_verifikasi">Catatan pemeriksa <span class="text-secondary">(wajib untuk koreksi atau penolakan)</span></label><textarea class="form-control" id="catatan_verifikasi" name="catatan_verifikasi" maxlength="500" rows="3"><?= e($profile['catatan_verifikasi'] ?? '') ?></textarea></div>
                        <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-success" name="decision" value="terverifikasi" <?= $missing || !$auditReady ? 'disabled title="Lengkapi biodata dan pastikan migrasi audit aktif"' : '' ?>><i class="bi bi-check-circle me-1"></i>Setujui Biodata</button><button class="btn btn-outline-warning" name="decision" value="perlu_perbaikan" <?= !$auditReady ? 'disabled' : '' ?>><i class="bi bi-pencil-square me-1"></i>Minta Perbaikan</button><?php if ($rejectionReady): ?><button class="btn btn-outline-danger" name="decision" value="ditolak" <?= !$auditReady ? 'disabled' : '' ?>>Tolak Pengajuan</button><?php endif; ?></div>
                    </form>
                </div>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" class="biodata-form-body"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save">
                    <section class="biodata-section">
                        <h3><span><i class="bi bi-camera"></i></span>Foto profil</h3><?php if (!empty($target['foto_path'])): ?><div class="mb-2"><img src="media/profile.php?id=<?= $userId ?>" alt="Foto profil saat ini" width="76" height="76" class="rounded-circle object-fit-cover"></div><?php endif; ?><label class="form-label" for="foto">Unggah foto (opsional)</label><input class="form-control" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG, atau WebP, maksimal 2 MB.</div>
                    </section>
                    <?php $sectionIndex = 0;
                    foreach ($roleSections as $sectionName => $sectionFields): ?>
                        <section class="biodata-section" id="bio-section-<?= $sectionIndex ?>">
                            <h3><span><i class="bi <?= e($sectionIcons[$sectionName] ?? 'bi-card-text') ?>"></i></span><?= e($sectionName) ?></h3>
                            <div class="row g-3">
                                <?php foreach ($sectionFields as $field): $label = $labels[$field] ?? $field;
                                    $required = in_array($field, biodata_required_fields($targetRole), true);
                                    $fieldValue = $profile[$field] ?? '';
                                    if ($field === 'nip_kepala' && $fieldValue === '') $fieldValue = $target['nip'] ?? '';
                                    if ($field === 'tanggal_lahir' && $isLinkedStudent && $fieldValue === '') $fieldValue = $target['siswa_tanggal_lahir'] ?? '';
                                    if ($field === 'alamat' && $fieldValue === '') $fieldValue = $isLinkedStudent ? ($target['siswa_alamat'] ?? '') : (in_array($targetRole, ['guru', 'kepala_sekolah'], true) ? ($target['guru_alamat'] ?? '') : '');
                                    $isDocument = str_starts_with($field, 'dokumen_');
                                    $isLongText = in_array($field, ['alamat', 'alamat_wali', 'riwayat_alergi', 'kebutuhan_khusus', 'catatan_kesehatan', 'prestasi_akademik', 'prestasi_nonakademik', 'pendidikan_pelatihan', 'pelatihan_kepemimpinan', 'pelatihan_manajemen_sekolah', 'pelatihan_kurikulum', 'pengembangan_keprofesian', 'keterangan_jabatan'], true);
                                    $type = in_array($field, ['tanggal_lahir', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'tanggal_masuk_smp', 'tmt', 'tanggal_mulai_bertugas', 'tanggal_sk_pengangkatan', 'tanggal_sk_kepala', 'tanggal_mulai_menjabat'], true) ? 'date' : (in_array($field, ['email', 'email_pribadi', 'email_dinas_kepsek'], true) ? 'email' : (in_array($field, ['anak_ke', 'jumlah_saudara'], true) ? 'number' : 'text'));
                                    $statusOptions = match ($field) {
                                        'status_ayah', 'status_ibu' => ['Hidup', 'Meninggal', 'Belum diketahui'],
                                        'status_perkawinan' => ['Belum kawin', 'Kawin', 'Cerai hidup', 'Cerai mati'],
                                        'status_kepegawaian' => ['PNS', 'PPPK', 'Honorer', 'Kontrak', 'Lainnya'],
                                        'sertifikasi_pendidik' => ['Ya', 'Tidak', 'Dalam proses'],
                                        'status_jabatan' => ['Aktif', 'Nonaktif'],
                                        default => []
                                    };
                                    $maxlength = ['telepon' => 30, 'whatsapp' => 30, 'tempat_lahir' => 100, 'pendidikan' => 180, 'nama_kontak_darurat' => 120, 'telepon_kontak_darurat' => 30][$field] ?? ''; ?>
                                    <div class="col-12 <?= $isLongText ? '' : 'col-md-6' ?>"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?></label>
                                        <?php if ($isDocument): ?><input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                                            <div class="form-text">PDF, JPG, atau PNG, maksimal 5 MB.<?php if (!empty($profile[$field])): ?> <a href="media/biodata_document.php?user=<?= $userId ?>&amp;field=<?= e($field) ?>" target="_blank" rel="noopener">Lihat dokumen tersimpan</a><?php endif; ?></div>
                                        <?php elseif ($isLongText): ?><textarea class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" maxlength="3000" rows="3" <?= $required ? 'required' : '' ?>><?= e($fieldValue) ?></textarea>
                                        <?php elseif ($statusOptions): ?><select class="form-select" id="<?= e($field) ?>" name="<?= e($field) ?>">
                                                <option value="">Pilih <?= e(mb_strtolower($label)) ?></option><?php if (!empty($profile[$field]) && !in_array($profile[$field], $statusOptions, true)): ?><option value="<?= e($profile[$field]) ?>" selected><?= e($profile[$field]) ?></option><?php endif; ?><?php foreach ($statusOptions as $statusOption): ?><option value="<?= e($statusOption) ?>" <?= ($profile[$field] ?? '') === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option><?php endforeach; ?>
                                            </select>
                                        <?php else: ?><input class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" <?= $type === 'date' ? 'max="' . date('Y-m-d') . '"' : '' ?> <?= $maxlength !== '' ? 'maxlength="' . (int)$maxlength . '"' : '' ?> <?= $type === 'number' ? 'min="0" max="99"' : '' ?> value="<?= e($fieldValue) ?>" <?= $required ? 'required' : '' ?>><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div><?php if ($sectionName === 'Data Kesehatan (opsional)'): ?><p class="form-text mb-0">Bagian kesehatan bersifat opsional. Isi seperlunya dan hindari informasi yang tidak perlu.</p><?php elseif ($sectionName === 'Dokumen Pendukung'): ?><p class="form-text mb-0">Dokumen disimpan privat dan hanya dapat diakses pemilik akun serta pemeriksa biodata yang berwenang.</p><?php endif; ?>
                        </section>
                    <?php $sectionIndex++;
                    endforeach; ?>
                    <?php if ($isLinkedStudent): ?><section class="biodata-section" id="bio-school-data">
                            <h3><span><i class="bi bi-building"></i></span>Data Sekolah · dikelola Operator</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>NIS / NISN</small><strong><?= e($target['nis'] ?: '—') ?> / <?= e($target['nisn'] ?: 'Belum dicatat') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Kelas / rombel</small><strong><?= e($target['nama_kelas'] ?: 'Belum ditetapkan') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Wali kelas</small><strong><?= e($target['wali_kelas'] ?: 'Belum ditetapkan') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Tahun ajaran · status</small><strong><?= e($target['tahun_ajaran'] ?: 'Belum ditetapkan') ?> · <?= e($target['siswa_status'] ?: '—') ?></strong></div>
                                </div>
                            </div>
                            <p class="small text-secondary mb-0 mt-2">Data induk dan penempatan kelas dikelola Operator. Alamat biodata dapat dilengkapi mandiri pada bagian Alamat Siswa.</p>
                        </section><?php elseif ($isLinkedTeacher): ?><section class="biodata-section" id="bio-school-data">
                            <h3><span><i class="bi bi-journal-bookmark"></i></span>Penugasan Akademik · dikelola Operator</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Mata pelajaran dan kelas</small><strong><?= e($teacherAssignments ? implode(', ', array_map(static fn($item): string => $item['nama_mapel'] . ' · ' . $item['nama_kelas'], $teacherAssignments)) : 'Belum ada jadwal aktif') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Wali kelas</small><strong><?= e($teacherHomerooms ? implode(', ', array_map(static fn($item): string => $item['nama_kelas'], $teacherHomerooms)) : 'Bukan wali kelas') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Tahun ajaran · beban mengajar</small><strong><?= e($teacherAssignments[0]['tahun_ajaran'] ?? $teacherHomerooms[0]['tahun_ajaran'] ?? 'Belum ditetapkan') ?> · <?= $teacherWorkloadJp ?> JP per minggu</strong></div>
                                </div>
                            </div>
                            <p class="small text-secondary mb-0 mt-2">NIP, jenis kelamin, email kerja, status keaktifan, dan jadwal mengajar dikelola melalui Data Guru dan Jadwal. Data kontak pribadi dan alamat pada formulir ini dapat dilengkapi sendiri.</p>
                        </section><?php elseif ($targetRole === 'kepala_sekolah'): ?><section class="biodata-section" id="bio-school-data">
                            <h3><span><i class="bi bi-building"></i></span>Identitas Sekolah · dikelola Operator</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Nama sekolah</small><strong><?= e($schoolProfile['nama'] ?? 'Belum diatur') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>NPSN</small><strong><?= e($schoolProfile['npsn'] ?? 'Belum dicatat') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Jenjang pendidikan</small><strong><?= e($profile['jenjang_pendidikan'] ?: 'Belum dicatat') ?></strong></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="biodata-readonly-panel"><small>Alamat sekolah</small><strong><?= e($schoolProfile['alamat'] ?? 'Belum diatur') ?></strong></div>
                                </div>
                            </div>
                            <p class="small text-secondary mb-0 mt-2">Nama sekolah, NPSN, dan alamat sekolah mengikuti Pengaturan Sekolah. Kepala Sekolah dapat mengisi jenjang pendidikan di formulir biodata.</p>
                        </section><?php endif; ?>
                    <div class="biodata-form-actions"><button class="btn btn-outline-secondary" type="button" data-biodata-modal-close>Batal</button><button class="btn btn-primary"><i class="bi bi-send-check me-1"></i>Simpan dan Ajukan Pemeriksaan</button></div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
<script>
    (() => {
        const printButton = document.getElementById('biodataPrintButton');
        printButton?.addEventListener('click', () => {
            document.body.classList.add('biodata-print-mode');
            window.print();
        });
        window.addEventListener('afterprint', () => document.body.classList.remove('biodata-print-mode'));
        const detailToggle = document.getElementById('biodataDetailToggle'),
            detailPanel = document.getElementById('biodataDetailPanel'),
            detailDialog = detailPanel?.querySelector('[role="dialog"]');
        const setDetailOpen = open => {
            if (!detailPanel) return;
            detailPanel.hidden = !open;
            document.body.classList.toggle('account-modal-open', open || !!document.getElementById('biodataFormPanel') && !document.getElementById('biodataFormPanel').hidden);
            if (open) detailDialog?.focus();
            else detailToggle?.focus();
        };
        detailToggle?.addEventListener('click', () => setDetailOpen(true));
        detailPanel?.querySelectorAll('[data-biodata-detail-close]').forEach(button => button.addEventListener('click', () => setDetailOpen(false)));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && detailPanel && !detailPanel.hidden) setDetailOpen(false);
        });
        const toggle = document.getElementById('biodataFormToggle'),
            panel = document.getElementById('biodataFormPanel'),
            dialog = panel?.querySelector('[role="dialog"]'),
            icon = document.getElementById('biodataFormToggleIcon'),
            label = document.getElementById('biodataFormToggleLabel');
        const setModalOpen = open => {
            if (!panel) return;
            panel.hidden = !open;
            toggle?.setAttribute('aria-expanded', String(open));
            document.body.classList.toggle('account-modal-open', open);
            if (icon) icon.className = `bi ${open?'bi-dash-lg':'bi-plus-lg'}`;
            if (label) label.textContent = open ? 'Tutup formulir biodata' : (<?= json_encode($statusKey === 'terverifikasi' ? 'Ubah Biodata' : 'Lengkapi Biodata', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            if (open) dialog?.focus();
            else toggle?.focus();
        };
        if (panel && !panel.hidden) document.body.classList.add('account-modal-open');
        toggle?.addEventListener('click', () => setModalOpen(panel.hidden));
        panel?.querySelectorAll('[data-biodata-modal-close]').forEach(button => button.addEventListener('click', () => setModalOpen(false)));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && panel && !panel.hidden) setModalOpen(false);
        });
    })();
</script>