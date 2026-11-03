-- Status verifikasi untuk tujuan sekolah agar rancangan tidak disajikan sebagai ketetapan resmi.
SET @school_review_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='tujuan_status')=0, 'ALTER TABLE profil_sekolah ADD COLUMN tujuan_status ENUM(''rancangan'',''perlu_verifikasi'',''resmi'') NOT NULL DEFAULT ''perlu_verifikasi''', 'SELECT 1');
PREPARE school_review_stmt FROM @school_review_ddl; EXECUTE school_review_stmt; DEALLOCATE PREPARE school_review_stmt;
UPDATE profil_sekolah SET tujuan_status=IF(tujuan LIKE 'Meningkatkan kualitas pendidikan dan hasil belajar peserta didik.%','rancangan','perlu_verifikasi') WHERE id=1;
