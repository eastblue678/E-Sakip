<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * AKSARA+ — POSISI TERBAGI: IKP pola posisi yang dapat dipecah per bagian.
 *
 * Kembaran SQL: db/update_2026-09-29_ikp_posisi_terbagi.sql.
 *
 * Kolom baru `ikp.posisi_terbagi` (TINYINT, bawaan 0), hanya bermakna untuk
 * pola posisi: 1 = "Dapat dipecah per bagian — total = jumlah posisi setiap
 * bagian pada bulan yang sama" (mis. pengikut semua akun media sosial resmi =
 * IG + FB + TikTok + YouTube; nasabah aktif per unit).
 *
 * MENGAPA: posisi biasa dipikul SATU pemikul angka dengan target utuh (satu
 * persentase tidak bisa dibagi). Posisi yang merupakan jumlah dari beberapa
 * bagian justru harus dibagi: setiap pemegang akun memikul posisi akunnya.
 * Dengan bendera ini Turunkan IKP mengizinkan beberapa pemikul angka per
 * jenjang dengan porsi (Σ porsi anak = target induk), target bulanan setiap
 * pemikul = target POSISI bulanan induk × porsi/target induk (posisi, bukan
 * cicilan), dan realisasi bulan m = Σ posisi terbaru setiap bagian pada bulan m
 * (tidak pernah dijumlah lintas bulan).
 *
 * Sifat: IDEMPOTEN & ADDITIF — tidak ada IKP lama yang berubah perilakunya
 * (bawaan 0 = posisi seperti sebelumnya: satu pemikul angka, target utuh).
 */
class AddPosisiTerbagiToIkp extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('ikp') || $this->db->fieldExists('posisi_terbagi', 'ikp')) {
            return;
        }
        $sesudah = $this->db->fieldExists('pola_ditebak', 'ikp') ? ' AFTER `pola_ditebak`' : '';
        $this->db->query("ALTER TABLE `ikp` ADD COLUMN `posisi_terbagi` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = posisi dapat dipecah per bagian (total = Σ posisi bagian pada bulan yang sama)'" . $sesudah);
    }

    public function down()
    {
        if ($this->db->tableExists('ikp') && $this->db->fieldExists('posisi_terbagi', 'ikp')) {
            $this->forge->dropColumn('ikp', 'posisi_terbagi');
        }
    }
}
