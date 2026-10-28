-- Migration tambahan untuk foto dan persetujuan direktori guru publik.
-- Buat backup database sims_db sebelum menjalankan. Aman dijalankan ulang:
-- setiap kolom hanya ditambahkan bila belum tersedia.
USE sims_db;

SET @guru_foto_ddl = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guru' AND COLUMN_NAME = 'foto'
    ),
    'SELECT 1',
    'ALTER TABLE `guru` ADD COLUMN `foto` VARCHAR(255) NULL AFTER `alamat`'
);
PREPARE guru_foto_stmt FROM @guru_foto_ddl;
EXECUTE guru_foto_stmt;
DEALLOCATE PREPARE guru_foto_stmt;

SET @guru_public_ddl = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guru' AND COLUMN_NAME = 'tampil_publik'
    ),
    'SELECT 1',
    'ALTER TABLE `guru` ADD COLUMN `tampil_publik` TINYINT(1) NOT NULL DEFAULT 0 AFTER `foto`'
);
PREPARE guru_public_stmt FROM @guru_public_ddl;
EXECUTE guru_public_stmt;
DEALLOCATE PREPARE guru_public_stmt;
