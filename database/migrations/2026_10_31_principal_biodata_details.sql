-- Rincian biodata Kepala Sekolah dan nomor identitas sekolah.
-- Kolom ditambahkan secara aditif, tanpa mengubah data lama.
USE sims_db;

ALTER TABLE biodata_pengguna
    ADD COLUMN nip_kepala VARCHAR(40) NULL,
    ADD COLUMN email_dinas_kepsek VARCHAR(180) NULL,
    ADD COLUMN pendidikan_pelatihan TEXT NULL,
    ADD COLUMN jabatan_saat_ini VARCHAR(120) NULL,
    ADD COLUMN sk_kepala_sekolah VARCHAR(120) NULL,
    ADD COLUMN tanggal_sk_kepala DATE NULL,
    ADD COLUMN tanggal_mulai_menjabat DATE NULL,
    ADD COLUMN periode_jabatan VARCHAR(60) NULL,
    ADD COLUMN status_jabatan VARCHAR(30) NULL,
    ADD COLUMN jenjang_pendidikan VARCHAR(80) NULL,
    ADD COLUMN bidang_sertifikasi VARCHAR(180) NULL,
    ADD COLUMN nrg VARCHAR(40) NULL,
    ADD COLUMN sertifikat_kompetensi_kepsek VARCHAR(180) NULL,
    ADD COLUMN pelatihan_kepemimpinan TEXT NULL,
    ADD COLUMN pelatihan_manajemen_sekolah TEXT NULL,
    ADD COLUMN pelatihan_kurikulum TEXT NULL,
    ADD COLUMN pengembangan_keprofesian TEXT NULL,
    ADD COLUMN jabatan_sebelumnya VARCHAR(180) NULL,
    ADD COLUMN instansi_sebelumnya VARCHAR(180) NULL,
    ADD COLUMN tahun_mulai_jabatan VARCHAR(4) NULL,
    ADD COLUMN tahun_selesai_jabatan VARCHAR(4) NULL,
    ADD COLUMN nomor_sk_jabatan VARCHAR(120) NULL,
    ADD COLUMN keterangan_jabatan TEXT NULL;

ALTER TABLE profil_sekolah ADD COLUMN npsn VARCHAR(20) NULL;
