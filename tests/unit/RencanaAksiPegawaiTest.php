<?php

use App\Controllers\RuangOpdController;
use App\Services\EkinClient;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AKSARA+ — Rencana Aksi Pegawai (Ruang OPD): susunan per atasan, bulan bawaan, dan kontrak eKin §22–§23
 * (rencana aksi PERSIAPAN di luar penyebut, chip pola ukur per IKI, chip peran IKP, `jenis_bkn` usang) —
 * termasuk jawaban eKin lama tanpa kunci-kunci baru.
 *
 * @internal
 */
final class RencanaAksiPegawaiTest extends CIUnitTestCase
{
    private static function p(int $id, ?int $atasan, string $jenis, string $nama): array
    {
        return ['pegawai_id' => $id, 'atasan_pegawai_id' => $atasan, 'jenis_jabatan' => $jenis, 'nama' => $nama];
    }

    public function testSusunPerAtasanMengikutiRantaiDanJenjang(): void
    {
        $hasil = EkinClient::susunPerAtasan([
            self::p(5, 2, 'pelaksana', 'Eko'),
            self::p(2, 1, 'administrator', 'Budi'),
            self::p(1, 9999, 'jpt', 'Ani'),          // atasan (Bupati) tidak ada di daftar → akar
            self::p(3, 1, 'administrator', 'Cici'),
            self::p(4, 2, 'fungsional', 'Dedi'),
        ]);

        $this->assertSame([1, 2, 4, 5, 3], array_map(static fn ($b) => $b['p']['pegawai_id'], $hasil));
        $this->assertSame([0, 1, 2, 2, 1], array_column($hasil, 'tingkat'));
        $this->assertSame(4, $hasil[0]['bawahan'], 'Kepala punya 4 bawahan (langsung & tidak langsung)');
        $this->assertSame(2, $hasil[1]['bawahan']);
    }

    public function testSusunPerAtasanAmanDariLingkaran(): void
    {
        $hasil = EkinClient::susunPerAtasan([self::p(1, 2, 'pelaksana', 'A'), self::p(2, 1, 'pelaksana', 'B')]);

        $this->assertCount(2, $hasil, 'setiap pegawai tampil tepat sekali');
        $this->assertSame([1, 2], array_map(static fn ($b) => $b['p']['pegawai_id'], $hasil));
    }

    public function testBulanBawaan(): void
    {
        $this->assertSame(4, RuangOpdController::bulanDari('4', 2026, 2026, 9), 'bulan diminta');
        $this->assertSame(9, RuangOpdController::bulanDari(null, 2026, 2026, 9), 'tahun berjalan → bulan berjalan');
        $this->assertSame(12, RuangOpdController::bulanDari('13', 2025, 2026, 9), 'tahun lampau → Desember');
        $this->assertSame(1, RuangOpdController::bulanDari('', 2027, 2026, 9), 'tahun depan → Januari');
    }

    // -----------------------------------------------------------------
    // Persiapan (eKin README §23): tidak ikut penyebut, dihitung terpisah
    // -----------------------------------------------------------------

    /** Butir `pegawai` API opd/{id}/rencana-aksi. */
    private static function pr(int $id, ?array $ra, bool $skp = true): array
    {
        return ['pegawai_id' => $id, 'nama' => 'Pegawai ' . $id, 'jabatan' => 'Jabatan ' . $id, 'skp' => $skp ? ['id' => $id, 'status' => 'disetujui'] : null, 'ra' => $ra];
    }

    public function testAngkaRaMemisahkanPersiapan(): void
    {
        // Kontrak baru: jumlah = selesai + berjalan + belum_ada_kegiatan + belum_dilaporkan + persiapan.
        $a = EkinClient::angkaRa(['jumlah' => 11, 'tercapai' => 0, 'selesai' => 0, 'berjalan' => 0, 'belum_ada_kegiatan' => 1,
            'belum_dilaporkan' => 6, 'persiapan' => 4, 'sumber_ikp' => 6, 'capaian' => 0.0, 'pekerjaan' => ['berjalan' => 1, 'selesai' => 0]]);
        $this->assertSame(7, $a['jumlah'], 'rencana aksi yang diukur = jumlah − persiapan');
        $this->assertSame(4, $a['persiapan']);
        $this->assertSame(1, $a['belum']);
        $this->assertSame(6, $a['belum_dilaporkan']);
        $this->assertSame(0.0, $a['capaian']);
        $this->assertSame(1, $a['pekerjaan_berjalan']);

        // eKin lama: tanpa persiapan/pekerjaan/belum_dilaporkan → angka lama utuh.
        $lama = EkinClient::angkaRa(['jumlah' => 3, 'tercapai' => 1, 'selesai' => 1, 'berjalan' => 1, 'belum_ada_kegiatan' => 1, 'capaian' => 55.5]);
        $this->assertSame(['jumlah' => 3, 'persiapan' => 0, 'tercapai' => 1, 'belum' => 1, 'belum_dilaporkan' => 0, 'sumber_ikp' => 0,
            'capaian' => 55.5, 'pekerjaan_berjalan' => 0], $lama);
        $this->assertSame(0, EkinClient::angkaRa(null)['jumlah'], 'pegawai tanpa butir ra');
    }

    public function testRingkasRaTanpaPersiapanDiPenyebut(): void
    {
        $r = RuangOpdController::ringkasRa([
            self::pr(1, ['jumlah' => 6, 'tercapai' => 3, 'belum_ada_kegiatan' => 0, 'persiapan' => 1, 'capaian' => 98.3]),
            self::pr(2, ['jumlah' => 1, 'tercapai' => 0, 'belum_ada_kegiatan' => 0, 'persiapan' => 1, 'capaian' => null]),  // hanya persiapan
            self::pr(3, ['jumlah' => 2, 'tercapai' => 1, 'belum_ada_kegiatan' => 1, 'capaian' => 50.0]),                  // eKin lama
            self::pr(4, null, false),
        ]);
        $this->assertSame(4, $r['pegawai']);
        $this->assertSame(3, $r['ber_skp']);
        $this->assertSame(7, $r['ra'], '5 + 0 + 2: persiapan tidak ikut jumlah');
        $this->assertSame(2, $r['persiapan']);
        $this->assertSame(4, $r['tercapai']);
        $this->assertSame(1, $r['belum']);
        $this->assertSame(74.2, $r['capaian'], 'rata-rata capaian pegawai yang punya capaian (98,3 & 50)');
    }

    public function testSaringanMengabaikanPersiapan(): void
    {
        $hanyaSiap = self::pr(2, ['jumlah' => 1, 'tercapai' => 0, 'belum_ada_kegiatan' => 0, 'persiapan' => 1]);
        $siapCapai = self::pr(5, ['jumlah' => 3, 'tercapai' => 2, 'belum_ada_kegiatan' => 0, 'persiapan' => 1]);
        $lama      = self::pr(3, ['jumlah' => 2, 'tercapai' => 1, 'belum_ada_kegiatan' => 1]);

        $this->assertFalse(RuangOpdController::cocokSaringan($hanyaSiap, 'berjalan', ''), 'hanya persiapan ≠ belum semua tercapai');
        $this->assertFalse(RuangOpdController::cocokSaringan($hanyaSiap, 'tercapai', ''), 'hanya persiapan ≠ semua tercapai');
        $this->assertTrue(RuangOpdController::cocokSaringan($siapCapai, 'tercapai', ''), '2 dari 2 yang diukur tercapai (+1 persiapan)');
        $this->assertFalse(RuangOpdController::cocokSaringan($siapCapai, 'berjalan', ''));
        $this->assertTrue(RuangOpdController::cocokSaringan($lama, 'berjalan', ''));
        $this->assertTrue(RuangOpdController::cocokSaringan($lama, 'belum', ''));
        $this->assertTrue(RuangOpdController::cocokSaringan(self::pr(4, null, false), 'tanpa_skp', ''));
        $this->assertTrue(RuangOpdController::cocokSaringan($lama, '', 'jabatan 3'), 'cari nama/jabatan tanpa peduli huruf besar');
        $this->assertFalse(RuangOpdController::cocokSaringan($lama, '', 'tidak ada'));
    }

    public function testHitungRaPegawaiSetahun(): void
    {
        $data = ['rhk' => [
            ['rencana_aksi' => [
                ['bulan' => 1, 'persiapan' => true, 'tercapai' => false, 'target' => null],
                ['bulan' => 12, 'persiapan' => false, 'tercapai' => false, 'target' => 99.0],
            ]],
            ['rencana_aksi' => [
                ['bulan' => 8, 'tercapai' => true, 'target' => 4.0, 'jenis_bkn' => 'trajectory'],   // eKin lama
                ['bulan' => 9, 'tercapai' => false, 'target' => 4.0, 'jenis_bkn' => null],
            ]],
        ]];
        $this->assertSame(['ra' => 3, 'capai' => 1, 'persiapan' => 1], EkinClient::hitungRaPegawai($data));
    }

    public function testKelasSelPersiapanNetral(): void
    {
        // Bulan lalu, sumber IKP belum dilaporkan, tidak tercapai — tetapi persiapan: bukan merah, bukan "belum dilaporkan".
        $siap = ['bulan' => 3, 'persiapan' => true, 'tercapai' => false, 'sumber_realisasi' => 'ikp', 'ikp_dilaporkan' => false];
        $this->assertSame('c-siap', EkinClient::kelasSelRa($siap, 2026, 2026, 9));
        $this->assertSame('c-kurang', EkinClient::kelasSelRa(['bulan' => 3, 'tercapai' => false], 2026, 2026, 9), 'eKin lama: bulan lalu belum tercapai');
        $this->assertSame('c-jalan', EkinClient::kelasSelRa(['bulan' => 9, 'tercapai' => false, 'persiapan' => false], 2026, 2026, 9));
        $this->assertSame('c-rencana', EkinClient::kelasSelRa(['bulan' => 12, 'tercapai' => false], 2026, 2026, 9), 'bulan rilis yang belum tiba');
        $this->assertSame('c-capai', EkinClient::kelasSelRa(['bulan' => 2, 'tercapai' => true], 2026, 2026, 9));
        $this->assertSame('c-belum', EkinClient::kelasSelRa(['bulan' => 2, 'tercapai' => false, 'sumber_realisasi' => 'ikp', 'ikp_dilaporkan' => false], 2026, 2026, 9));
    }

    // -----------------------------------------------------------------
    // Chip pola ukur per IKI & peran IKP (menggantikan TR/NT per RA)
    // -----------------------------------------------------------------

    public function testChipPolaUkur(): void
    {
        $bulanan = range(1, 12);
        $this->assertSame('Hitungan · bulanan', EkinClient::polaIki(['pola_ukur' => 'hitungan', 'periode_ukur' => 'bulanan', 'bulan_ukur' => $bulanan])['label']);
        $this->assertSame('Posisi · bulanan', EkinClient::polaIki(['pola_ukur' => 'posisi', 'periode_ukur' => 'bulanan', 'bulan_ukur' => $bulanan])['label']);
        $sem = EkinClient::polaIki(['pola_ukur' => 'posisi', 'periode_ukur' => 'semesteran', 'bulan_ukur' => [12, 6]]);
        $this->assertSame('Posisi · semesteran', $sem['label']);
        $this->assertStringContainsString('Diukur Jun, Des', $sem['judul']);
        $this->assertSame('Posisi · Mar, Agu', EkinClient::polaIki(['pola_ukur' => 'posisi', 'periode_ukur' => 'khusus', 'bulan_ukur' => [3, 8]])['label']);

        $kip = EkinClient::polaIki(['pola_ukur' => 'rilis', 'periode_ukur' => 'tahunan', 'bulan_ukur' => [12], 'penerbit' => 'Komisi Informasi Provinsi Lampung',
            'rilis_tahun_berikut' => false, 'pola_ditebak' => false]);
        $this->assertSame('rilis', $kip['kode']);
        $this->assertSame('Rilis · Des', $kip['label'], 'rilis: bulan rilis di chip');
        $this->assertStringStartsWith('Dirilis Komisi Informasi Provinsi Lampung.', $kip['judul']);
        $this->assertFalse($kip['ditebak']);

        $bpk = EkinClient::polaIki(['pola_ukur' => 'rilis', 'bulan_ukur' => [6], 'rilis_tahun_berikut' => true, 'pola_ditebak' => true]);
        $this->assertSame('Rilis · Jun th. berikut', $bpk['label']);
        $this->assertTrue($bpk['ditebak']);
        $this->assertStringContainsString('ditebak', $bpk['judul']);

        $this->assertNull(EkinClient::polaIki(['aspek' => 'kualitas', 'indikator' => 'Persentase kesesuaian']), 'IKI tanpa pola (kualitas/eKin lama) → tanpa chip');
        $this->assertNull(EkinClient::polaIki(['pola_ukur' => 'ngawur']));
    }

    public function testChipPeranIkp(): void
    {
        $this->assertSame('IKP', EkinClient::peranIkp('angka')['label']);
        $this->assertSame('IKP', EkinClient::peranIkp('pemilik')['label']);
        $this->assertSame('Mendukung IKP', EkinClient::peranIkp('pendukung')['label']);
        $this->assertSame('dukung', EkinClient::peranIkp('pendukung')['kelas']);
        $this->assertSame('IKP · porsi pimpinan', EkinClient::peranIkp('turunan')['label']);
        $this->assertNull(EkinClient::peranIkp(null));
        $this->assertNull(EkinClient::peranIkp(''));
    }
}
