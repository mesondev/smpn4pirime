-- Identitas terstruktur dan tujuan sekolah. Kolom ditambahkan tanpa mengubah data profil yang telah ada.
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='alamat_jalan')=0, 'ALTER TABLE profil_sekolah ADD COLUMN alamat_jalan VARCHAR(180) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='kampung')=0, 'ALTER TABLE profil_sekolah ADD COLUMN kampung VARCHAR(100) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='distrik')=0, 'ALTER TABLE profil_sekolah ADD COLUMN distrik VARCHAR(100) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='kabupaten')=0, 'ALTER TABLE profil_sekolah ADD COLUMN kabupaten VARCHAR(120) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='provinsi')=0, 'ALTER TABLE profil_sekolah ADD COLUMN provinsi VARCHAR(120) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='kode_pos')=0, 'ALTER TABLE profil_sekolah ADD COLUMN kode_pos VARCHAR(12) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='status_sekolah')=0, 'ALTER TABLE profil_sekolah ADD COLUMN status_sekolah VARCHAR(30) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='jenjang')=0, 'ALTER TABLE profil_sekolah ADD COLUMN jenjang VARCHAR(60) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='naungan')=0, 'ALTER TABLE profil_sekolah ADD COLUMN naungan VARCHAR(180) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='akreditasi')=0, 'ALTER TABLE profil_sekolah ADD COLUMN akreditasi VARCHAR(20) NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='akreditasi_status')=0, 'ALTER TABLE profil_sekolah ADD COLUMN akreditasi_status ENUM(''perlu_verifikasi'',''terverifikasi'') NOT NULL DEFAULT ''perlu_verifikasi''', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='visi_status')=0, 'ALTER TABLE profil_sekolah ADD COLUMN visi_status ENUM(''rancangan'',''perlu_verifikasi'',''resmi'') NOT NULL DEFAULT ''perlu_verifikasi''', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='misi_status')=0, 'ALTER TABLE profil_sekolah ADD COLUMN misi_status ENUM(''rancangan'',''perlu_verifikasi'',''resmi'') NOT NULL DEFAULT ''perlu_verifikasi''', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;
SET @school_ddl = IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profil_sekolah' AND column_name='tujuan')=0, 'ALTER TABLE profil_sekolah ADD COLUMN tujuan TEXT NULL', 'SELECT 1');
PREPARE school_stmt FROM @school_ddl; EXECUTE school_stmt; DEALLOCATE PREPARE school_stmt;

UPDATE profil_sekolah
SET npsn=COALESCE(NULLIF(npsn,''),'60303637'),
    alamat_jalan=COALESCE(NULLIF(alamat_jalan,''),'Jln. Milimbo-Tiom'),
    kampung=COALESCE(NULLIF(kampung,''),'Umbanume'),
    distrik=COALESCE(NULLIF(distrik,''),'Pirime'),
    kabupaten=COALESCE(NULLIF(kabupaten,''),'Lanny Jaya'),
    provinsi=COALESCE(NULLIF(provinsi,''),'Papua Pegunungan'),
    status_sekolah=COALESCE(NULLIF(status_sekolah,''),'Negeri'),
    jenjang=COALESCE(NULLIF(jenjang,''),'SMP'),
    naungan=COALESCE(NULLIF(naungan,''),'Pemerintah Daerah'),
    akreditasi=COALESCE(NULLIF(akreditasi,''),'C'),
    tujuan=COALESCE(NULLIF(tujuan,''),'Meningkatkan kualitas pendidikan dan hasil belajar peserta didik.\nMengembangkan potensi akademik dan nonakademik siswa.\nMempersiapkan lulusan agar mampu melanjutkan pendidikan.\nMembangun karakter, kedisiplinan, dan rasa tanggung jawab.\nMeningkatkan kualitas tenaga pendidik dan tenaga kependidikan.\nMemanfaatkan teknologi dalam kegiatan pembelajaran dan administrasi sekolah.'),
    visi_status=IF(NULLIF(visi,'') IS NULL,'rancangan','perlu_verifikasi'),
    visi=COALESCE(NULLIF(visi,''),'Terwujudnya peserta didik yang beriman, berkarakter, berprestasi, berdisiplin, mandiri, serta memiliki kemampuan dan keterampilan untuk menghadapi perkembangan zaman dengan tetap menjunjung tinggi nilai-nilai budaya lokal.'),
    misi_status=IF(NULLIF(misi,'') IS NULL,'rancangan','perlu_verifikasi'),
    misi=COALESCE(NULLIF(misi,''),'Melaksanakan pembelajaran yang aktif, kreatif, efektif, dan menyenangkan.\nMembentuk peserta didik yang beriman, berakhlak mulia, dan memiliki karakter yang baik.\nMeningkatkan kemampuan literasi, numerasi, dan pengetahuan peserta didik.\nMengembangkan bakat, minat, kreativitas, dan keterampilan siswa.\nMenciptakan lingkungan sekolah yang aman, bersih, sehat, dan nyaman.\nMeningkatkan kedisiplinan serta tanggung jawab seluruh warga sekolah.\nMembangun kerja sama antara sekolah, orang tua, masyarakat, dan pemerintah.')
WHERE id=1;
