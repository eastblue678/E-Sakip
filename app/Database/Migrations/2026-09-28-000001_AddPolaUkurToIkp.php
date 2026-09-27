<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * AKSARA+ — POLA UKUR indikator IKP (hitungan | posisi | rilis).
 *
 * Kembaran SQL: db/update_2026-09-28_ikp_pola_ukur.sql (hanya kolom).
 *
 * Kolom baru pada `ikp`:
 *   pola_ukur            hitungan|posisi|rilis (NULL = belum diklasifikasikan;
 *                        aplikasi menebaknya saat dibaca, ikp_pola())
 *   periode_ukur         bulanan|triwulanan|semesteran|tahunan|khusus
 *   bulan_ukur           daftar bulan "6,12" (bulan rilis untuk pola rilis)
 *   penerbit             pihak yang merilis nilai (pola rilis)
 *   rilis_tahun_berikut  1 = nilai tahun N baru dirilis tahun N+1
 *   pola_ditebak         1 = hasil klasifikasi otomatis, belum dikonfirmasi
 *                        admin (chip "Periksa pola ukur")
 *
 * Klasifikasi data lama memakai ikp_pola_tebak() + tabel rilis bawaan di
 * app/Config/IkpPolaUkur.php (satu sumber dengan form & simulasi):
 *   metode sum -> hitungan; trend_* -> posisi; nama/satuan berpola indeks/
 *   nilai resmi -> rilis (bulan rilis & penerbit dari tabel bawaan).
 *
 * MENGAPA migrasi TIDAK menyentuh target/realisasi bulanan maupun kolom
 * `metode`: di data nyata, isian di bulan non-ukur cukup DIABAIKAN rumus
 * (ikp_capaian_pola menyaringnya) — tidak ada angka produksi yang dihapus
 * oleh migrasi. Pembersihan data hanya untuk data SIMULASI, lewat pembangun
 * simulasi di luar repo. Kolom `metode` diselaraskan saat admin menyimpan
 * form IKP.
 *
 * Sifat: IDEMPOTEN (kolom diperiksa dulu; klasifikasi hanya untuk baris yang
 * pola_ukur-nya masih NULL) & ADDITIF.
 */
class AddPolaUkurToIkp extends Migration
{
    private const KOLOM = ['pola_ukur', 'periode_ukur', 'bulan_ukur', 'penerbit', 'rilis_tahun_berikut', 'pola_ditebak'];

    public function up()
    {
        if (! $this->db->tableExists('ikp')) {
            return;
        }

        $sebelum = 'metode';
        foreach ([
            'pola_ukur'           => "VARCHAR(10) NULL COMMENT 'hitungan|posisi|rilis (NULL = ditebak saat dibaca)'",
            'periode_ukur'        => "VARCHAR(12) NULL COMMENT 'bulanan|triwulanan|semesteran|tahunan|khusus'",
            'bulan_ukur'          => "VARCHAR(40) NULL COMMENT 'bulan ukur 1..12, dipisah koma (pola rilis: bulan rilis)'",
            'penerbit'            => "VARCHAR(150) NULL COMMENT 'pihak yang merilis nilai (pola rilis)'",
            'rilis_tahun_berikut' => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = nilai tahun N dirilis tahun N+1'",
            'pola_ditebak'        => "TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = klasifikasi otomatis, belum dikonfirmasi admin'",
        ] as $kolom => $def) {
            if (! $this->db->fieldExists($kolom, 'ikp')) {
                $this->db->query("ALTER TABLE `ikp` ADD COLUMN `{$kolom}` {$def} AFTER `{$sebelum}`");
            }
            $sebelum = $kolom;
        }

        $this->klasifikasi();
    }

    /** Isi pola untuk baris yang belum punya (termasuk IKP arsip: rujukan eKin tetap bermakna). */
    private function klasifikasi(): void
    {
        helper('ikp');
        $cfg  = config('IkpPolaUkur');
        $rows = $this->db->table('ikp i')
            ->select('i.id, i.metode, i.output_prioritas, i.satuan_teks, s.satuan AS satuan_nama')
            ->join('satuan s', 's.id = i.satuan_id', 'left')
            ->where('i.pola_ukur', null)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $satuan = trim((string) ($r['satuan_nama'] ?? '')) !== '' ? (string) $r['satuan_nama'] : (string) ($r['satuan_teks'] ?? '');
            $t      = ikp_pola_tebak($r['metode'] ?? null, (string) $r['output_prioritas'], $satuan, $cfg);
            $this->db->table('ikp')->where('id', (int) $r['id'])->where('pola_ukur', null)->update([
                'pola_ukur'           => $t['pola_ukur'],
                'periode_ukur'        => $t['periode_ukur'],
                'bulan_ukur'          => ikp_bulan_ukur_teks($t['bulan_ukur']),
                'penerbit'            => $t['penerbit'],
                'rilis_tahun_berikut' => $t['rilis_tahun_berikut'] ? 1 : 0,
                'pola_ditebak'        => 1,
            ]);
        }
    }

    public function down()
    {
        foreach (self::KOLOM as $k) {
            if ($this->db->fieldExists($k, 'ikp')) {
                $this->forge->dropColumn('ikp', $k);
            }
        }
    }
}
