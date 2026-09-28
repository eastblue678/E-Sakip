-- =====================================================================
-- AKSARA+ — POSISI TERBAGI (IKP pola posisi yang dapat dipecah per bagian)
--
-- Jalankan SEKALI pada basis data server (phpMyAdmin / mysql < berkas ini).
-- Aman diulang: kolom diperiksa lebih dulu.
-- Padanan `spark migrate`: app/Database/Migrations/2026-09-29-000001_AddPosisiTerbagiToIkp.php
--
-- MENGAPA
--   Posisi biasa (mis. % layanan tepat waktu) dipikul SATU pemikul angka
--   dengan target utuh. Ada posisi yang merupakan JUMLAH beberapa bagian
--   pada bulan yang sama (pengikut semua akun resmi = IG + FB + TikTok +
--   YouTube; nasabah aktif per unit). Dengan posisi_terbagi = 1, IKP itu
--   boleh diturunkan ke beberapa pemikul angka dengan porsi (Σ porsi anak =
--   target induk); realisasi bulan m = Σ posisi setiap bagian pada bulan m.
--   Bawaan 0: perilaku lama tidak berubah.
-- =====================================================================

SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ikp' AND COLUMN_NAME = 'posisi_terbagi');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ikp` ADD COLUMN `posisi_terbagi` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = posisi dapat dipecah per bagian (total = jumlah posisi bagian pada bulan yang sama)'' AFTER `pola_ditebak`',
  'SELECT ''ikp.posisi_terbagi sudah ada'' AS laporan');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT pola_ukur, posisi_terbagi, COUNT(*) AS jumlah FROM `ikp` WHERE dihapus_pada IS NULL GROUP BY pola_ukur, posisi_terbagi;
