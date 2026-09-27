-- =====================================================================
-- AKSARA+ — IKP TURUN SAMPAI PELAKSANA (pendelegasian IKP lewat pohon kinerja)
--
-- Jalankan SEKALI pada basis data server (phpMyAdmin / mysql < berkas ini).
-- Aman diulang: setiap kolom, indeks, dan kunci asing diperiksa lebih dulu.
-- Padanan `spark migrate`: app/Database/Migrations/2026-09-28-000002_AddIkpTurunToCascadingTarget.php
--
-- MENGAPA
--   IKP dulu hanya melekat di PK Kepala OPD (Eselon II) dan berhenti di sana.
--   Kini IKP diturunkan jenjang demi jenjang lewat INDIKATOR SIMPUL pohon
--   kinerja (jalur yang sama dengan IKU): Kepala OPD -> Eselon III -> Eselon IV
--   / Ketua Tim -> pelaksana. Pemilik simpul (menu Pemilik Kinerja) otomatis
--   menjadi pemikul IKP turunan itu, dan eKin menariknya menjadi RHK.
--   Satu baris cascading_indikator_target = "IKP X diturunkan ke indikator
--   simpul Y tahun T, porsi Z, peran P".
--
-- KOLOM
--   ikp_peran         angka (pemikul angka: porsi / target utuh) | pendukung
--                     (indikator PROSES sendiri, tidak menambah angka IKP)
--   ikp_induk_id      baris induk; NULL = langsung dari IKP (Kepala OPD)
--   dibuat_oleh       users.id yang menurunkan
--   sumber            delegasi | lama (tautan langsung sebelum fitur ini)
--   sebelum_delegasi  JSON keadaan indikator sebelum IKP diturunkan (dipulihkan
--                     saat pendelegasian dicabut)
-- =====================================================================

SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cascading_indikator_target' AND COLUMN_NAME = 'ikp_peran');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `cascading_indikator_target`
     ADD COLUMN `ikp_peran` VARCHAR(10) NOT NULL DEFAULT ''angka'' COMMENT ''angka|pendukung (berarti hanya bila ikp_id terisi)'' AFTER `ikp_id`,
     ADD COLUMN `ikp_induk_id` INT UNSIGNED NULL COMMENT ''baris induk IKP yang sama; NULL = langsung dari IKP (Kepala OPD)'' AFTER `ikp_peran`,
     ADD COLUMN `dibuat_oleh` INT UNSIGNED NULL COMMENT ''users.id yang menurunkan IKP'' AFTER `ikp_induk_id`,
     ADD COLUMN `sumber` VARCHAR(10) NULL COMMENT ''delegasi|lama (NULL = target biasa tanpa IKP)'' AFTER `dibuat_oleh`,
     ADD COLUMN `sebelum_delegasi` TEXT NULL COMMENT ''JSON keadaan sebelum IKP diturunkan (untuk dipulihkan)'' AFTER `sumber`',
  'SELECT ''cascading_indikator_target.ikp_peran sudah ada'' AS laporan');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @ada := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cascading_indikator_target' AND INDEX_NAME = 'idx_casc_target_ikp_tahun');
SET @sql := IF(@ada = 0, 'ALTER TABLE `cascading_indikator_target` ADD KEY `idx_casc_target_ikp_tahun` (`ikp_id`, `tahun`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @ada := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cascading_indikator_target' AND INDEX_NAME = 'idx_casc_target_induk');
SET @sql := IF(@ada = 0, 'ALTER TABLE `cascading_indikator_target` ADD KEY `idx_casc_target_induk` (`ikp_induk_id`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @ada := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cascading_indikator_target' AND CONSTRAINT_NAME = 'fk_casc_target_induk');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `cascading_indikator_target` ADD CONSTRAINT `fk_casc_target_induk` FOREIGN KEY (`ikp_induk_id`)
     REFERENCES `cascading_indikator_target` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Tautan lama (sebelum fitur ini) = pemikul angka bersumber "lama".
UPDATE `cascading_indikator_target` SET `sumber` = 'lama', `ikp_peran` = 'angka'
 WHERE `ikp_id` IS NOT NULL AND `sumber` IS NULL;

SELECT sumber, ikp_peran, COUNT(*) AS jumlah FROM `cascading_indikator_target` WHERE ikp_id IS NOT NULL GROUP BY sumber, ikp_peran;
