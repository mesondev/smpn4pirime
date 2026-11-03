<?php

declare(strict_types=1);

function public_page_hero(string $eyebrow, string $title, string $intro, string $activePage): void
{
?><section class="page-hero">
        <div class="wrap"><span class="eyebrow"><?= e($eyebrow) ?></span>
            <h1><?= e($title) ?></h1>
            <p><?= e($intro) ?></p><?= portal_breadcrumb_markup($activePage, $title) ?>
        </div>
    </section><?php
            }

            function public_empty_panel(string $icon, string $title, string $message): void
            {
                ?>
    <div class="public-empty-state public-empty-state--wide">
        <span class="public-empty-icon"><i class="bi bi-<?= e($icon) ?>" aria-hidden="true"></i></span>
        <div class="public-empty-copy">
            <strong><?= e($title) ?></strong>
            <p><?= e($message) ?></p>
            <div class="public-empty-actions">
                <a class="text-link" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <a class="text-link" href="profil.php">Lihat profil sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
<?php
            }

            function public_service_notice(string $message): void
            {
?>
    <div class="public-service-notice" role="status">
        <span class="public-empty-icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></span>
        <div>
            <strong>Informasi sedang disiapkan</strong>
            <p><?= e($message) ?></p>
            <div class="public-empty-actions">
                <a class="text-link" href="kontak.php">Hubungi sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <a class="text-link" href="profil.php">Lihat profil sekolah <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
<?php
            }

            function public_facility_images(): array
            {
                return [
                    ['src' => '../assets/image/public-optimized/facility-building.jpg', 'title' => 'Gedung sekolah', 'alt' => 'Gedung sekolah dengan area halaman'],
                    ['src' => '../assets/image/public-optimized/facility-classroom.jpg', 'title' => 'Ruang kelas', 'alt' => 'Ruang kelas dengan meja dan papan tulis'],
                    ['src' => '../assets/image/public-optimized/facility-library.jpg', 'title' => 'Perpustakaan', 'alt' => 'Rak-rak buku di ruang perpustakaan'],
                    ['src' => '../assets/image/public-optimized/facility-computer-lab.jpg', 'title' => 'Laboratorium komputer', 'alt' => 'Komputer tersusun di ruang praktik'],
                    ['src' => '../assets/image/public-optimized/facility-science-lab.jpg', 'title' => 'Laboratorium IPA', 'alt' => 'Peralatan praktik di laboratorium sains'],
                ];
            }

            function public_gallery_images(): array
            {
                return [
                    ['src' => '../assets/image/public-optimized/gallery-classroom.jpg', 'title' => 'Pembelajaran di kelas', 'category' => 'Ilustrasi pembelajaran'],
                    ['src' => '../assets/image/public-optimized/gallery-study-group.jpg', 'title' => 'Belajar bersama', 'category' => 'Ilustrasi belajar kelompok'],
                    ['src' => '../assets/image/public-optimized/gallery-student-presentation.jpg', 'title' => 'Presentasi siswa', 'category' => 'Ilustrasi kegiatan belajar'],
                    ['src' => '../assets/image/public-optimized/gallery-sports.jpg', 'title' => 'Aktivitas olahraga', 'category' => 'Ilustrasi olahraga'],
                    ['src' => '../assets/image/public-optimized/gallery-outdoor-learning.jpg', 'title' => 'Kegiatan di luar kelas', 'category' => 'Ilustrasi pembelajaran'],
                    ['src' => '../assets/image/public-optimized/gallery-school-activity.jpg', 'title' => 'Kegiatan sekolah', 'category' => 'Ilustrasi kegiatan siswa'],
                    ['src' => '../assets/image/public-optimized/gallery-school-building.jpg', 'title' => 'Lingkungan sekolah', 'category' => 'Ilustrasi lingkungan belajar'],
                    ['src' => '../assets/image/public-optimized/gallery-creative-learning.jpg', 'title' => 'Kegiatan kreatif', 'category' => 'Ilustrasi kreativitas'],
                    ['src' => '../assets/image/public-optimized/gallery-reading.jpg', 'title' => 'Membaca dan belajar', 'category' => 'Ilustrasi literasi'],
                    ['src' => '../assets/image/public-optimized/gallery-school-yard.jpg', 'title' => 'Area sekolah', 'category' => 'Ilustrasi lingkungan belajar'],
                    ['src' => '../assets/image/public-optimized/gallery-students.jpg', 'title' => 'Kebersamaan siswa', 'category' => 'Ilustrasi kegiatan siswa'],
                    ['src' => '../assets/image/public-optimized/gallery-team-activity.jpg', 'title' => 'Aktivitas bersama', 'category' => 'Ilustrasi kerja sama'],
                    ['src' => '../assets/image/public-optimized/award-example-01.jpg', 'title' => 'Apresiasi prestasi', 'category' => 'Ilustrasi apresiasi siswa'],
                    ['src' => '../assets/image/public-optimized/award-example-02.jpg', 'title' => 'Penghargaan siswa', 'category' => 'Ilustrasi prestasi'],
                    ['src' => '../assets/image/public-optimized/award-example-03.jpg', 'title' => 'Inspirasi pencapaian', 'category' => 'Ilustrasi prestasi'],
                ];
            }

            function public_teacher_images(): array
            {
                return array_map(
                    static fn(int $number): array => [
                        'src' => sprintf('../assets/image/public-optimized/teacher-portrait-%02d.jpg', $number),
                        'alt' => 'Potret tenaga pendidik dari aset portal ' . $number,
                    ],
                    range(1, 6)
                );
            }

            function public_school_staff_images(): array
            {
                return array_map(
                    static fn(int $number): array => [
                        'src' => sprintf('../assets/image/public-optimized/school-staff-%02d.jpg', $number),
                        'alt' => 'Foto sambutan dan tenaga pendidik dari aset sekolah ' . $number,
                    ],
                    range(1, 3)
                );
            }

            function public_text_cards(array $items, string $emptyTitle, string $emptyMessage, callable $render): void
            {
                if (!$items) {
                    public_empty_panel('info-circle', $emptyTitle, $emptyMessage);
                    return;
                }
                echo '<div class="cards public-feature-grid">';
                foreach ($items as $item) $render($item);
                echo '</div>';
            }
