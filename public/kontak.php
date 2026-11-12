<?php require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';
$portalProfile = portal_profile($pdo);
$pageTitle = 'Kontak dan Lokasi';
$activePage = 'kontak';
$contactPhone = portal_phone_uri($portalProfile['telepon'] ?? null);
$contactEmail = portal_email_address($portalProfile['email'] ?? null);
require __DIR__ . '/_header.php';
$configuredMap = trim((string)($portalProfile['peta_url'] ?? ''));
if ($configuredMap === '') {
    $configuredMap = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3788.538844885933!2d138.5360632747575!3d-3.9588897443876467!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x683df3ba817309b9%3A0x1ba07ba15f884948!2sSMP%20NEGERI%204%20PIRIME!5e1!3m2!1sid!2sid!4v1794491671370!5m2!1sid!2sid';
}
$mapParts = $configuredMap !== '' ? parse_url($configuredMap) : false;
$safeMap = is_array($mapParts)
    && strtolower((string)($mapParts['scheme'] ?? '')) === 'https'
    && in_array(strtolower((string)($mapParts['host'] ?? '')), ['google.com', 'www.google.com', 'maps.google.com'], true)
    && !isset($mapParts['user'], $mapParts['pass'])
    && str_starts_with((string)($mapParts['path'] ?? ''), '/maps/')
    ? $configuredMap
    : '';
$mapOpenUrl = $safeMap;
$directionsAddress = portal_school_address($portalProfile);
$directionsUrl = $directionsAddress !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($directionsAddress) : ''; ?>

<section class="page-hero contact-page-hero">
    <div class="wrap">
        <span class="eyebrow">Informasi resmi sekolah</span>
        <h1>Kontak Kami</h1>
        <p>Hubungi <?= e($portalProfile['nama']) ?> untuk informasi sekolah, layanan akademik, dan bantuan lainnya.</p>
        <?= portal_breadcrumb_markup($activePage ?? '', $pageTitle ?? '') ?>
    </div>
</section>

<section class="content contact-page-content">
    <div class="wrap contact-page-wrap">
        <?php $messageFlash = take_flash();
        if ($messageFlash): ?><div class="form-message <?= $messageFlash['type'] === 'error' ? 'form-message-error' : '' ?>" role="status"><?= e($messageFlash['message']) ?></div><?php endif; ?>

        <article class="contact-school-card" aria-labelledby="contactSchoolTitle">
            <div class="contact-school-visual">
                <span class="contact-school-visual-label"><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Portal informasi resmi</span>
                <img src="../assets/Lambang-Sekolah-SMP-Negeri-4-Pirime.png" alt="Lambang <?= e($portalProfile['nama']) ?>">
                <span class="contact-school-visual-caption"><?= e($portalProfile['nama']) ?></span>
            </div>
            <div class="contact-school-copy">
                <span class="kicker">Tentang sekolah</span>
                <h2 id="contactSchoolTitle"><?= e($portalProfile['nama']) ?></h2>
                <p>Portal resmi untuk memperoleh informasi dan menghubungi pihak sekolah. Silakan gunakan kanal yang tersedia untuk pertanyaan umum maupun layanan sekolah.</p>
                <ul class="contact-school-facts">
                    <li><i class="bi bi-mortarboard-fill" aria-hidden="true"></i><span><?= e($portalProfile['jenjang'] ?: 'Jenjang belum diatur') ?></span></li>
                    <?php if (trim((string)$portalProfile['npsn']) !== ''): ?><li><i class="bi bi-card-text" aria-hidden="true"></i><span>NPSN <?= e($portalProfile['npsn']) ?></span></li><?php endif; ?>
                    <?php $schoolRegion = array_filter([$portalProfile['distrik'], $portalProfile['kabupaten'], $portalProfile['provinsi']], static fn($part): bool => trim((string)$part) !== ''); ?>
                    <?php if ($schoolRegion): ?><li><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><span><?= e(implode(', ', $schoolRegion)) ?></span></li><?php endif; ?>
                </ul>
            </div>
        </article>

        <div class="contact-main-grid">
            <aside class="contact-side-column" aria-label="Informasi kontak dan peta">
                <section class="contact-info-panel" aria-labelledby="contactInfoTitle">
                    <h2 id="contactInfoTitle"><i class="bi bi-card-list" aria-hidden="true"></i> Informasi Kontak</h2>
                    <ul class="contact-info-list">
                        <li><i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                            <div><strong>Alamat</strong><span><?= nl2br(e($directionsAddress ?: 'Alamat sekolah belum diatur.')) ?></span></div>
                        </li>
                        <li><i class="bi bi-envelope-fill" aria-hidden="true"></i>
                            <div><strong>Email</strong><?php if ($contactEmail !== ''): ?><a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a><?php else: ?><span>Email belum diatur.</span><?php endif; ?></div>
                        </li>
                        <li><i class="bi bi-telephone-fill" aria-hidden="true"></i>
                            <div><strong>Telepon</strong><?php if ($contactPhone !== ''): ?><a href="tel:<?= e($contactPhone) ?>"><?= e($portalProfile['telepon']) ?></a><?php else: ?><span>Nomor telepon belum diatur.</span><?php endif; ?></div>
                        </li>
                        <?php $contactWhatsApp = portal_whatsapp_number($portalProfile['telepon'] ?? null);
                        if ($contactWhatsApp !== ''): ?><li><i class="bi bi-whatsapp" aria-hidden="true"></i>
                                <div><strong>WhatsApp</strong><a href="https://wa.me/<?= e($contactWhatsApp) ?>" target="_blank" rel="noopener noreferrer">Chat WhatsApp sekolah</a></div>
                            </li><?php endif; ?>
                        <li><i class="bi bi-clock-fill" aria-hidden="true"></i>
                            <div><strong>Jam layanan</strong><span><?= nl2br(e($portalProfile['jam_layanan'] ?: 'Jam layanan belum diatur. Silakan hubungi sekolah.')) ?></span></div>
                        </li>
                    </ul>
                </section>

                <section class="contact-map-panel" aria-labelledby="contactMapTitle">
                    <div class="contact-panel-heading">
                        <h2 id="contactMapTitle"><i class="bi bi-map-fill" aria-hidden="true"></i> Lokasi Sekolah</h2><?php if ($mapOpenUrl !== ''): ?><a href="<?= e($mapOpenUrl) ?>" target="_blank" rel="noopener noreferrer">Buka peta <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a><?php endif; ?>
                    </div>
                    <?php if ($safeMap !== ''): ?><iframe class="contact-map-frame" src="<?= e($safeMap) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Peta lokasi <?= e($portalProfile['nama']) ?>" allowfullscreen></iframe><?php else: ?><p class="contact-location-empty">Peta belum tersedia karena tautan lokasi resmi belum diatur.</p><?php endif; ?>
                    <?php if ($directionsUrl !== ''): ?><a class="contact-directions-link" href="<?= e($directionsUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-signpost-2-fill" aria-hidden="true"></i> Petunjuk arah ke alamat sekolah</a><?php endif; ?>
                </section>
            </aside>

            <section class="contact-form-panel" aria-labelledby="messageFormTitle">
                <div class="contact-panel-heading contact-form-heading">
                    <h2 id="messageFormTitle"><i class="bi bi-send-fill" aria-hidden="true"></i> Kirim Pesan</h2>
                    <p>Pesan Anda akan diterima oleh petugas sekolah. Formulir ini bukan kanal untuk keadaan darurat.</p>
                </div>
                <?php if (portal_schema_ready($pdo, 'pesan_kontak', ['nama_pengirim', 'email_pengirim', 'subjek', 'status']) && portal_schema_ready($pdo, 'pesan_kontak_balasan', ['pesan_id', 'pengirim', 'isi'])): ?>
                    <form class="public-form contact-message-form" action="proses-pesan.php" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <div class="form-honeypot" aria-hidden="true"><label for="websiteMessage">Jangan isi kolom ini</label><input id="websiteMessage" name="website" tabindex="-1" autocomplete="off"></div>
                        <div class="form-grid"><label>Nama Lengkap<input name="nama" maxlength="120" required autocomplete="name"></label><label>Email<input name="email" type="email" maxlength="180" required autocomplete="email"></label><label>Nomor telepon <span>(opsional)</span><input name="telepon" type="tel" maxlength="30" autocomplete="tel"></label><label>Kategori<select name="kategori">
                                    <option>Pertanyaan umum</option>
                                    <option>Informasi akademik</option>
                                    <option>PPDB / SPMB</option>
                                    <option>Kerja sama</option>
                                    <option>Lainnya</option>
                                </select></label><label class="form-field-wide">Subjek Pesan<input name="subjek" maxlength="180" required></label><label class="form-field-wide">Isi Pesan<textarea name="isi" rows="5" maxlength="3000" required placeholder="Tuliskan pesan Anda..."></textarea></label></div><label class="privacy-consent"><input type="checkbox" name="setuju_privasi" value="1" required><span>Saya memahami penggunaan data sesuai <a href="privasi.php">Kebijakan Privasi</a>.</span></label><button type="submit" class="btn-primary"><i class="bi bi-send-fill" aria-hidden="true"></i> Kirim Pesan</button>
                    </form>
                    <?php else: ?><?php public_service_notice('Formulir pesan belum tersedia. Anda tetap dapat menghubungi sekolah melalui nomor telepon atau email pada panel informasi kontak.'); ?><?php endif; ?>
            </section>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php';
