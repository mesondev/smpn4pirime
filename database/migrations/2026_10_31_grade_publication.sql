-- Aditif: mengendalikan kapan nilai dapat dilihat siswa.
-- Nilai lama tetap terlihat; nilai baru dari formulir Guru dimulai sebagai Draft.
USE sims_db;

SET @grade_publication_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='nilai' AND column_name='status_publikasi')=0,
    'ALTER TABLE nilai ADD COLUMN status_publikasi ENUM(''Draft'',''Terbit'') NOT NULL DEFAULT ''Terbit'' AFTER nilai_uas',
    'SELECT 1'
);
PREPARE grade_publication_stmt FROM @grade_publication_ddl;
EXECUTE grade_publication_stmt;
DEALLOCATE PREPARE grade_publication_stmt;
