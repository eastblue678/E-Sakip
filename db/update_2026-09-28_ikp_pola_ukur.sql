-- =====================================================================
-- AKSARA+ — POLA UKUR indikator IKP (hitungan | posisi | rilis)
--
-- Jalankan SEKALI pada basis data server (phpMyAdmin / mysql < berkas ini).
-- Aman diulang: setiap kolom diperiksa lebih dulu.
-- Padanan `spark migrate`: app/Database/Migrations/2026-09-28-000001_AddPolaUkurToIkp.php
--
-- MENGAPA
--   Indeks resmi (mis. Indeks Keterbukaan Informasi Publik) dulu "dicicil"
--   dan diberi realisasi setiap bulan, padahal nilainya dirilis pihak lain
--   setahun sekali pada bulan tertentu. Setiap IKP kini punya pola ukur:
--   hitungan (dijumlah), posisi (diukur sendiri pada bulan ukur), rilis
--   (nilai resmi pihak lain, hanya pada bulan rilis). Bulan di luar
--   bulan_ukur tidak diukur dan tidak dihitung capaiannya.
--
-- KLASIFIKASI DATA LAMA
--   Berkas SQL ini hanya menambah kolom. Baris yang pola_ukur-nya NULL
--   diklasifikasikan aplikasi SAAT DIBACA dengan aturan yang sama dengan
--   migrasi (ikp_pola_tebak + app/Config/IkpPolaUkur.php) dan tampil
--   bertanda "Periksa pola ukur" sampai Admin OPD menyimpan form IKP-nya.
--   `php spark migrate` sekaligus menyimpan hasil tebakan itu ke kolom.
--   Tidak ada target/realisasi yang diubah atau dihapus.
-- =====================================================================

SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ikp' AND COLUMN_NAME = 'pola_ukur');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ikp`
     ADD COLUMN `pola_ukur` VARCHAR(10) NULL COMMENT ''hitungan|posisi|rilis (NULL = ditebak saat dibaca)'' AFTER `metode`,
     ADD COLUMN `periode_ukur` VARCHAR(12) NULL COMMENT ''bulanan|triwulanan|semesteran|tahunan|khusus'' AFTER `pola_ukur`,
     ADD COLUMN `bulan_ukur` VARCHAR(40) NULL COMMENT ''bulan ukur 1..12, dipisah koma (pola rilis: bulan rilis)'' AFTER `periode_ukur`,
     ADD COLUMN `penerbit` VARCHAR(150) NULL COMMENT ''pihak yang merilis nilai (pola rilis)'' AFTER `bulan_ukur`,
     ADD COLUMN `rilis_tahun_berikut` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = nilai tahun N dirilis tahun N+1'' AFTER `penerbit`,
     ADD COLUMN `pola_ditebak` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = klasifikasi otomatis, belum dikonfirmasi admin'' AFTER `rilis_tahun_berikut`',
  'SELECT ''ikp.pola_ukur sudah ada'' AS laporan');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT pola_ukur, COUNT(*) AS jumlah FROM `ikp` WHERE dihapus_pada IS NULL GROUP BY pola_ukur;
