<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * AKSARA+ — IKP TURUN SAMPAI PELAKSANA (pendelegasian IKP lewat pohon kinerja).
 *
 * Kembaran SQL: db/update_2026-09-28_ikp_turun.sql.
 *
 * Satu baris `cascading_indikator_target` kini dapat berarti "IKP X diturunkan
 * ke indikator simpul Y tahun T, porsi Z, peran P". Kolom baru:
 *
 *   ikp_peran         angka | pendukung (bawaan angka). angka = pemikul angka:
 *                     target baris = PORSI IKP (hitungan) atau target UTUH
 *                     (posisi/rilis). pendukung = ikut bekerja lewat indikator
 *                     PROSES sendiri, tidak menambah angka IKP.
 *   ikp_induk_id      baris induk (jenjang di atasnya) untuk IKP yang sama;
 *                     NULL = langsung dari IKP (jangkar Eselon II / Kepala OPD).
 *                     Dirawat IkpTurunService::rapikanInduk() dari struktur pohon.
 *   dibuat_oleh       users.id yang menurunkan (NULL = migrasi/simulasi).
 *   sumber            delegasi (halaman Turunkan IKP) | lama (tautan langsung
 *                     IKP → indikator sebelum fitur ini, termasuk yang dibuat
 *                     lewat modal Pemilik Kinerja) | NULL (target indikator
 *                     biasa tanpa IKP).
 *   sebelum_delegasi  JSON keadaan indikator SEBELUM IKP diturunkan kepadanya
 *                     ({"ada_baris","target","target_teks","metode",
 *                     "indikator_dibuat"}), dipakai untuk memulihkan saat
 *                     pendelegasian dicabut.
 *
 * MENGAPA memperluas tabel ini (bukan tabel baru): eKin sudah menarik indikator
 * simpul beserta `ikp_id`-nya lewat api/ekin/pegawai/{id}/kinerja. Menambah
 * kolom membuat pendelegasian berjalan di jalur resmi yang sama dengan IKU
 * (pohon kinerja → pemilik simpul → RHK), tanpa jalur kedua.
 *
 * Baris lama yang sudah bertaut IKP (8 baris simulasi) diberi sumber `lama`,
 * peran `angka`. Sifat: IDEMPOTEN & ADDITIF (tidak ada target yang diubah).
 */
class AddIkpTurunToCascadingTarget extends Migration
{
    private const TABEL = 'cascading_indikator_target';

    private const KOLOM = ['ikp_peran', 'ikp_induk_id', 'dibuat_oleh', 'sumber', 'sebelum_delegasi'];

    public function up()
    {
        if (! $this->db->tableExists(self::TABEL)) {
            return;
        }

        $sebelum = 'ikp_id';
        foreach ([
            'ikp_peran'        => "VARCHAR(10) NOT NULL DEFAULT 'angka' COMMENT 'angka|pendukung (berarti hanya bila ikp_id terisi)'",
            'ikp_induk_id'     => "INT UNSIGNED NULL COMMENT 'baris induk IKP yang sama; NULL = langsung dari IKP (Kepala OPD)'",
            'dibuat_oleh'      => "INT UNSIGNED NULL COMMENT 'users.id yang menurunkan IKP'",
            'sumber'           => "VARCHAR(10) NULL COMMENT 'delegasi|lama (NULL = target biasa tanpa IKP)'",
            'sebelum_delegasi' => "TEXT NULL COMMENT 'JSON keadaan sebelum IKP diturunkan (untuk dipulihkan)'",
        ] as $kolom => $def) {
            if (! $this->db->fieldExists($kolom, self::TABEL)) {
                $this->db->query('ALTER TABLE `' . self::TABEL . "` ADD COLUMN `{$kolom}` {$def} AFTER `{$sebelum}`");
            }
            $sebelum = $kolom;
        }

        $indeks = array_column($this->db->query('SHOW INDEX FROM `' . self::TABEL . '`')->getResultArray(), 'Key_name');
        if (! in_array('idx_casc_target_ikp_tahun', $indeks, true)) {
            $this->db->query('ALTER TABLE `' . self::TABEL . '` ADD KEY `idx_casc_target_ikp_tahun` (`ikp_id`, `tahun`)');
        }
        if (! in_array('idx_casc_target_induk', $indeks, true)) {
            $this->db->query('ALTER TABLE `' . self::TABEL . '` ADD KEY `idx_casc_target_induk` (`ikp_induk_id`)');
        }
        $fk = $this->db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . self::TABEL . "' AND CONSTRAINT_NAME = 'fk_casc_target_induk'")->getResultArray();
        if ($fk === []) {
            $this->db->query('ALTER TABLE `' . self::TABEL . '` ADD CONSTRAINT `fk_casc_target_induk` FOREIGN KEY (`ikp_induk_id`)
                REFERENCES `' . self::TABEL . '` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        }

        // Tautan lama (sebelum fitur ini) = pemikul angka bersumber "lama".
        $this->db->query('UPDATE `' . self::TABEL . "` SET `sumber` = 'lama', `ikp_peran` = 'angka'
            WHERE `ikp_id` IS NOT NULL AND `sumber` IS NULL");
    }

    public function down()
    {
        if (! $this->db->tableExists(self::TABEL)) {
            return;
        }
        $fk = $this->db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . self::TABEL . "' AND CONSTRAINT_NAME = 'fk_casc_target_induk'")->getResultArray();
        if ($fk !== []) {
            $this->db->query('ALTER TABLE `' . self::TABEL . '` DROP FOREIGN KEY `fk_casc_target_induk`');
        }
        $indeks = array_column($this->db->query('SHOW INDEX FROM `' . self::TABEL . '`')->getResultArray(), 'Key_name');
        foreach (['idx_casc_target_induk', 'idx_casc_target_ikp_tahun'] as $k) {
            if (in_array($k, $indeks, true)) {
                $this->db->query('ALTER TABLE `' . self::TABEL . "` DROP KEY `{$k}`");
            }
        }
        foreach (self::KOLOM as $k) {
            if ($this->db->fieldExists($k, self::TABEL)) {
                $this->forge->dropColumn(self::TABEL, $k);
            }
        }
    }
}
