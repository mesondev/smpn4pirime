-- Riwayat lama ditinjau dulu sebelum ditayangkan sebagai sejarah resmi.
SET @history_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='sejarah_status')=0, 'ALTER TABLE profil_sekolah ADD COLUMN sejarah_status ENUM(''rancangan'',''perlu_verifikasi'',''resmi'') NOT NULL DEFAULT ''perlu_verifikasi''', 'SELECT 1');
PREPARE history_stmt FROM @history_ddl; EXECUTE history_stmt; DEALLOCATE PREPARE history_stmt;
UPDATE profil_sekolah SET sejarah_status=IF(NULLIF(sejarah,'') IS NULL,'rancangan','perlu_verifikasi') WHERE id=1;
