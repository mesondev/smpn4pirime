<?php
$studentBiodataStatusLabels = [
    'belum_lengkap' => 'Belum lengkap',
    'menunggu_verifikasi' => 'Menunggu persetujuan admin',
    'terverifikasi' => 'Disetujui',
    'perlu_perbaikan' => 'Perlu diperbaiki',
    'ditolak' => 'Perlu diperbarui',
];
$studentBiodataStatusClass = match ($biodataStatus) {
    'terverifikasi' => 'verified',
    'menunggu_verifikasi' => 'pending',
    'perlu_perbaikan', 'ditolak' => 'needs-update',
    default => 'needs-update',
};
$studentBiodataExcludedFields = [
    'user_id', 'status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada',
    'dibuat_pada', 'diperbarui_pada', 'created_at', 'updated_at', 'foto_path',
];
$studentBiodataLabelOverrides = [
    'telepon' => 'Nomor telepon',
    'whatsapp' => 'Nomor WhatsApp',
    'nama_kontak_darurat' => 'Nama orang tua/wali atau kontak darurat',
    'telepon_kontak_darurat' => 'Nomor orang tua/wali atau kontak darurat',
    'alamat' => 'Alamat rumah',
    'alamat_wali' => 'Alamat wali',
    'email' => 'Email siswa',
    'anak_ke' => 'Anak keberapa',
    'jumlah_saudara' => 'Jumlah saudara',
    'rt_rw' => 'RT/RW',
    'kampung' => 'Kampung/Desa',
];
$studentBiodataAcronyms = ['nisn' => 'NISN', 'nis' => 'NIS', 'nik' => 'NIK', 'npsn' => 'NPSN', 'nuptk' => 'NUPTK', 'nrg' => 'NRG', 'rt' => 'RT', 'rw' => 'RW', 'sd' => 'SD', 'smp' => 'SMP', 'kk' => 'KK', 'kip' => 'KIP', 'skhun' => 'SKHUN'];
$studentBiodataEntries = [];
foreach ($studentBiodata as $field => $value) {
    if (in_array($field, $studentBiodataExcludedFields, true) || $value === null || trim((string)$value) === '') continue;
    $isDocument = str_starts_with((string)$field, 'dokumen_') && str_ends_with((string)$field, '_path');
    $label = $studentBiodataLabelOverrides[$field] ?? null;
    if ($label === null) {
        $labelField = preg_replace('/_path$/', '', (string)$field) ?? (string)$field;
        $label = implode(' ', array_map(static function (string $part) use ($studentBiodataAcronyms): string {
            return $studentBiodataAcronyms[strtolower($part)] ?? (function_exists('mb_convert_case') ? mb_convert_case($part, MB_CASE_TITLE, 'UTF-8') : ucwords($part));
        }, explode('_', $labelField)));
    }
    $studentBiodataEntries[] = ['field' => (string)$field, 'label' => $label, 'value' => $isDocument ? 'Berkas telah diunggah' : (string)$value, 'document' => $isDocument];
}
?>
<section class="student-section-card student-biodata-panel" aria-labelledby="studentBiodataTitle">
    <div class="student-biodata-heading">
        <div class="student-biodata-title-group">
            <span class="student-section-icon blue" aria-hidden="true"><i class="bi bi-person-vcard-fill"></i></span>
            <div>
                <h2 id="studentBiodataTitle">Biodata Saya</h2>
                <p><?= $biodataRequiredFilled ?> dari <?= $biodataRequiredTotal ?> data wajib sudah terisi. Data yang Anda simpan tetap dapat dilihat sambil menunggu pemeriksaan admin.</p>
            </div>
        </div>
        <span class="student-biodata-status <?= e($studentBiodataStatusClass) ?>"><i class="bi <?= $biodataStatus === 'terverifikasi' ? 'bi-patch-check-fill' : ($biodataStatus === 'menunggu_verifikasi' ? 'bi-hourglass-split' : 'bi-exclamation-circle-fill') ?>" aria-hidden="true"></i><?= e($studentBiodataStatusLabels[$biodataStatus] ?? 'Belum diperiksa') ?></span>
    </div>
    <div class="student-biodata-progress" role="progressbar" aria-label="Kelengkapan data wajib" aria-valuemin="0" aria-valuemax="<?= $biodataRequiredTotal ?>" aria-valuenow="<?= $biodataRequiredFilled ?>">
        <span style="width:<?= $biodataRequiredTotal ? (int)round($biodataRequiredFilled * 100 / $biodataRequiredTotal) : 0 ?>%"></span>
    </div>
    <div class="student-biodata-actions">
        <span><?= count($studentBiodataEntries) ?> data biodata tersimpan</span>
        <a href="biodata.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i><?= $biodataStatus === 'terverifikasi' ? 'Lihat atau perbarui' : 'Periksa dan lengkapi' ?></a>
    </div>
    <?php if ($studentBiodataEntries): ?>
        <details class="student-biodata-details" open>
            <summary><span>Rincian biodata yang sudah diisi</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
            <dl class="student-biodata-grid">
                <?php foreach ($studentBiodataEntries as $entry): ?>
                    <div class="student-biodata-item">
                        <dt><?= e($entry['label']) ?></dt>
                        <dd><?php if ($entry['document']): ?><a href="media/biodata_document.php?user=<?= (int)$_SESSION['user_id'] ?>&amp;field=<?= e(rawurlencode($entry['field'])) ?>"><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i><?= e($entry['value']) ?></a><?php else: ?><?= nl2br(e($entry['value'])) ?><?php endif; ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </details>
    <?php else: ?>
        <div class="student-biodata-empty"><i class="bi bi-card-checklist" aria-hidden="true"></i><span>Belum ada data biodata yang tersimpan. Mulai isi biodata agar admin dapat memeriksa akun Anda.</span></div>
    <?php endif; ?>
    <?php if ($biodataStatus === 'menunggu_verifikasi'): ?>
        <p class="student-biodata-review-note"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Jika semua data wajib telah lengkap, admin akan meninjau dan menyetujui biodata melalui Manajemen Pengguna.</p>
    <?php elseif (in_array($biodataStatus, ['perlu_perbaikan', 'ditolak'], true) && !empty($studentBiodata['catatan_verifikasi'])): ?>
        <p class="student-biodata-review-note needs-update"><strong>Catatan admin:</strong> <?= nl2br(e($studentBiodata['catatan_verifikasi'])) ?></p>
    <?php endif; ?>
</section>
