<?php

declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_public_sections.php';

$portalProfile = portal_profile($pdo);
$pageTitle = 'FAQ';
$activePage = 'faq';
$faqGroups = [
    ['title' => 'Pendaftaran peserta didik', 'icon' => 'mortarboard-fill', 'items' => [
        ['q' => 'Bagaimana cara mendaftar melalui portal?', 'a' => 'Buka menu PPDB / SPMB, baca informasi periode dan persyaratan yang diterbitkan, lalu isi formulir jika pendaftaran sedang dibuka. Jika formulir belum tersedia, hubungi sekolah untuk informasi jadwal berikutnya.', 'url' => 'ppdb.php', 'link' => 'Buka informasi PPDB'],
        ['q' => 'Bagaimana cara mengetahui status pendaftaran?', 'a' => 'Gunakan menu Cek Status Pendaftaran dan masukkan nomor pendaftaran serta kunci status yang diberikan setelah formulir berhasil dikirim. Simpan kunci tersebut secara pribadi.', 'url' => 'cek_pendaftaran.php', 'link' => 'Cek status pendaftaran'],
        ['q' => 'Dokumen apa yang perlu disiapkan?', 'a' => 'Persyaratan resmi dapat berbeda pada setiap periode. Periksa bagian Persyaratan di halaman PPDB dan dokumen yang diterbitkan sekolah. Jangan mengirimkan berkas pribadi melalui saluran yang tidak ditetapkan sekolah.', 'url' => 'ppdb.php#persyaratan', 'link' => 'Lihat persyaratan'],
    ]],
    ['title' => 'Informasi sekolah', 'icon' => 'building-fill', 'items' => [
        ['q' => 'Di mana saya dapat membaca berita dan pengumuman?', 'a' => 'Berita, artikel, pengumuman, agenda, dan prestasi yang telah diterbitkan tersedia melalui menu Blog & Artikel.', 'url' => 'berita.php', 'link' => 'Buka blog dan artikel'],
        ['q' => 'Bagaimana melihat guru dan struktur organisasi?', 'a' => 'Buka menu Profil Sekolah, lalu pilih Direktori Guru atau Struktur Organisasi. Data personal hanya ditampilkan setelah disetujui untuk publikasi.', 'url' => 'struktur.php', 'link' => 'Lihat struktur organisasi'],
        ['q' => 'Di mana menemukan dokumen untuk diunduh?', 'a' => 'Dokumen publik yang telah diterbitkan dapat ditemukan melalui menu PPDB / SPMB → Unduhan & dokumen. Jika dokumen belum ada, hubungi sekolah untuk memastikan ketersediaannya.', 'url' => 'unduhan.php', 'link' => 'Buka pusat unduhan'],
    ]],
    ['title' => 'Akun, kontak, dan privasi', 'icon' => 'shield-lock-fill', 'items' => [
        ['q' => 'Siapa yang dapat masuk ke dashboard?', 'a' => 'Login portal ditujukan bagi pengguna yang telah memperoleh akun dari pengelola sekolah. Pengunjung umum dapat membaca halaman publik tanpa login.', 'url' => '../login.php', 'link' => 'Login portal'],
        ['q' => 'Bagaimana cara menghubungi sekolah?', 'a' => 'Gunakan halaman Kontak untuk melihat alamat, kanal komunikasi, lokasi, dan formulir pesan yang tersedia.', 'url' => 'kontak.php', 'link' => 'Buka halaman kontak'],
        ['q' => 'Bagaimana data saya digunakan?', 'a' => 'Portal hanya meminta data yang diperlukan untuk layanan seperti pesan kontak dan pendaftaran. Baca kebijakan privasi untuk penjelasan tentang penggunaan serta statistik kunjungan.', 'url' => 'privasi.php', 'link' => 'Baca kebijakan privasi'],
    ]],
];

require __DIR__ . '/_header.php';
public_page_hero('Bantuan pengunjung', $pageTitle . ' — Pertanyaan Umum', 'Jawaban ringkas untuk layanan portal ' . ($portalProfile['nama'] ?? 'sekolah') . '.', $activePage);
?>
<section class="content faq-page-content">
    <div class="wrap faq-page-wrap">
        <div class="faq-welcome-card"><span class="faq-welcome-icon"><i class="bi bi-chat-square-text-fill" aria-hidden="true"></i></span><div><span class="kicker">Pusat bantuan</span><h2>Temukan jawaban dengan cepat</h2><p>Pilih pertanyaan untuk membuka jawabannya. Jika informasi yang Anda cari belum tersedia, silakan hubungi sekolah melalui kanal resmi.</p></div><a class="btn-primary" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        <div class="faq-layout">
            <aside class="faq-side-card"><span class="kicker">Topik bantuan</span><nav aria-label="Kategori FAQ"><?php foreach ($faqGroups as $index => $group): ?><a href="#faqGroup<?= $index ?>"><i class="bi bi-<?= e($group['icon']) ?>" aria-hidden="true"></i><?= e($group['title']) ?></a><?php endforeach; ?></nav><div class="faq-side-note"><i class="bi bi-info-circle-fill" aria-hidden="true"></i><p>Jawaban mengikuti informasi yang saat ini tersedia pada portal. Ketentuan resmi tetap merujuk pada pengumuman sekolah.</p></div></aside>
            <div class="faq-groups">
                <?php foreach ($faqGroups as $index => $group): ?><section class="faq-group" id="faqGroup<?= $index ?>" aria-labelledby="faqHeading<?= $index ?>"><header><span class="faq-group-icon"><i class="bi bi-<?= e($group['icon']) ?>" aria-hidden="true"></i></span><div><span class="kicker">Bantuan <?= sprintf('%02d', $index + 1) ?></span><h2 id="faqHeading<?= $index ?>"><?= e($group['title']) ?></h2></div></header><div class="faq-questions"><?php foreach ($group['items'] as $item): ?><details class="faq-item"><summary><?= e($item['q']) ?><i class="bi bi-plus-lg" aria-hidden="true"></i></summary><div class="faq-answer"><p><?= e($item['a']) ?></p><a class="text-link" href="<?= e($item['url']) ?>"><?= e($item['link']) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></details><?php endforeach; ?></div></section><?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
