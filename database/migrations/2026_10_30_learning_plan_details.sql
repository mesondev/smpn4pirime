-- Lengkapi detail rencana ajar tanpa mengubah isi rencana yang sudah ada.
USE sims_db;

SET @plan_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='rencana_ajar' AND column_name='kegiatan')=0, 'ALTER TABLE rencana_ajar ADD COLUMN kegiatan TEXT NULL AFTER materi', 'SELECT 1');
PREPARE plan_stmt FROM @plan_ddl; EXECUTE plan_stmt; DEALLOCATE PREPARE plan_stmt;
SET @plan_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='rencana_ajar' AND column_name='metode')=0, 'ALTER TABLE rencana_ajar ADD COLUMN metode VARCHAR(180) NULL AFTER kegiatan', 'SELECT 1');
PREPARE plan_stmt FROM @plan_ddl; EXECUTE plan_stmt; DEALLOCATE PREPARE plan_stmt;
SET @plan_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='rencana_ajar' AND column_name='asesmen')=0, 'ALTER TABLE rencana_ajar ADD COLUMN asesmen TEXT NULL AFTER metode', 'SELECT 1');
PREPARE plan_stmt FROM @plan_ddl; EXECUTE plan_stmt; DEALLOCATE PREPARE plan_stmt;
