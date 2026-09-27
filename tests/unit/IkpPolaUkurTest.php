<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pola ukur IKP (hitungan | posisi | rilis) — aturan murni ikp_helper.php.
 *
 * Kasus pemicu (keluhan pengguna 28-09-2026): IKP "Keterbukaan Informasi
 * Publik" bersatuan Indeks Keterbukaan Informasi Publik diberi target 99 dan
 * realisasi 100 SETIAP bulan Jan–Agu 2026, sehingga capaiannya 101 % sejak
 * Januari — padahal nilainya dirilis Komisi Informasi setahun sekali.
 *
 * @see app/Helpers/ikp_helper.php (bagian POLA UKUR)
 * @see app/Config/IkpPolaUkur.php
 *
 * @internal
 */
final class IkpPolaUkurTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ikp');
    }

    /** Pola rilis Desember seperti hasil ikp_pola() untuk IKIP Diskominfo. */
    private function polaIkip(): array
    {
        return ikp_pola([
            'metode' => 'trend_naik', 'pola_ukur' => 'rilis', 'bulan_ukur' => '12', 'periode_ukur' => 'tahunan',
            'penerbit' => 'Komisi Informasi Provinsi Lampung', 'rilis_tahun_berikut' => 0, 'pola_ditebak' => 0,
        ]);
    }

    /* =========================== klasifikasi otomatis =========================== */

    public function testTebakIndeksKeterbukaanInformasiDariSatuan(): void
    {
        $t = ikp_pola_tebak('trend_naik', 'Keterbukaan Informasi Publik', 'Indeks Keterbukaan Informasi Publik');
        $this->assertSame('rilis', $t['pola_ukur']);
        $this->assertSame([12], $t['bulan_ukur']);
        $this->assertSame('tahunan', $t['periode_ukur']);
        $this->assertSame('Komisi Informasi Provinsi Lampung', $t['penerbit']);
        $this->assertFalse($t['rilis_tahun_berikut']);
        $this->assertTrue($t['pola_ditebak']);
    }

    public function testTebakSumJadiHitunganTrendJadiPosisi(): void
    {
        $h = ikp_pola_tebak('sum', 'Penyusunan Konten', 'Konten');
        $this->assertSame('hitungan', $h['pola_ukur']);
        $this->assertSame(range(1, 12), $h['bulan_ukur']);

        $p = ikp_pola_tebak('trend_naik', 'Jumlah nasabah aktif bank sampah', 'Orang');
        $this->assertSame('posisi', $p['pola_ukur']);
        $this->assertSame('trend_naik', $p['metode']);
        $this->assertSame('bulanan', $p['periode_ukur']);
    }

    public function testPersentaseYangDihitungSendiriBukanRilis(): void
    {
        $t = ikp_pola_tebak('trend_naik', 'Persentase layanan informasi publik yang ditanggapi tepat waktu', 'Persen');
        $this->assertSame('posisi', $t['pola_ukur']);

        // Satuan "Indeks" pada nama berawalan "Jumlah" = salah isi kolom, bukan indeks resmi.
        $j = ikp_pola_tebak('trend_naik', 'Jumlah Orang yang Mengikuti Koordinasi', 'Indeks');
        $this->assertSame('posisi', $j['pola_ukur']);
    }

    public function testTebakOpiniBpkDirilisTahunBerikutnya(): void
    {
        $t = ikp_pola_tebak('trend_flat', 'Opini BPK atas LKPD', 'Predikat');
        $this->assertSame('rilis', $t['pola_ukur']);
        $this->assertSame([5], $t['bulan_ukur']);
        $this->assertTrue($t['rilis_tahun_berikut']);
        $this->assertSame('trend_flat', $t['metode']);
    }

    public function testTebakRilisBermetodeSumTidakPernahDijumlah(): void
    {
        $t = ikp_pola_tebak('sum', 'Indeks SPBE', 'Indeks');
        $this->assertSame('rilis', $t['pola_ukur']);
        $this->assertSame('trend_naik', $t['metode']);
        $this->assertSame('Kementerian PANRB', $t['penerbit']);
    }

    public function testTebakTanpaMetodeMenurutSatuan(): void
    {
        $this->assertSame('posisi', ikp_pola_tebak(null, 'Cakupan layanan', '%')['pola_ukur']);
        $this->assertSame('hitungan', ikp_pola_tebak('', 'Pelatihan UMKM', 'Kegiatan')['pola_ukur']);
    }

    /* =========================== bulan ukur =========================== */

    public function testBulanUkurBacaTeksPeriode(): void
    {
        $this->assertSame([6, 12], ikp_bulan_ukur_baca('12, 6,6'));
        $this->assertSame([3, 9], ikp_bulan_ukur_baca(['9', 3, 13, 0, 'x']));
        $this->assertSame([], ikp_bulan_ukur_baca(null));
        $this->assertSame('6,12', ikp_bulan_ukur_teks([12, 6]));

        $this->assertSame('bulanan', ikp_periode_dari_bulan(range(1, 12)));
        $this->assertSame('triwulanan', ikp_periode_dari_bulan([3, 6, 9, 12]));
        $this->assertSame('semesteran', ikp_periode_dari_bulan([6, 12]));
        $this->assertSame('tahunan', ikp_periode_dari_bulan([11]));
        $this->assertSame('khusus', ikp_periode_dari_bulan([1, 2, 3, 4]));
        $this->assertSame('khusus', ikp_periode_dari_bulan([5, 7]));
    }

    /* =========================== ikp_pola() =========================== */

    public function testPolaMetodeEfektifMengikutiPola(): void
    {
        $h = ikp_pola(['metode' => 'trend_naik', 'pola_ukur' => 'hitungan', 'bulan_ukur' => null, 'periode_ukur' => 'triwulanan']);
        $this->assertSame('sum', $h['metode']);
        $this->assertSame([3, 6, 9, 12], $h['bulan_ukur']);

        $r = ikp_pola(['metode' => 'sum', 'pola_ukur' => 'rilis', 'bulan_ukur' => '12']);
        $this->assertSame('trend_naik', $r['metode'], 'nilai rilis tidak boleh dijumlah walau kolom lama "sum"');

        $p = ikp_pola(['metode' => 'trend_turun', 'pola_ukur' => 'posisi', 'bulan_ukur' => '6,12']);
        $this->assertSame('trend_turun', $p['metode']);
        $this->assertSame('semesteran', $p['periode_ukur']);
    }

    public function testPolaTebakanTanpaMetodeTidakMengarangRumus(): void
    {
        $p = ikp_pola(['metode' => null, 'pola_ukur' => 'posisi', 'bulan_ukur' => '1,2,3,4,5,6,7,8,9,10,11,12', 'pola_ditebak' => 1]);
        $this->assertSame('', $p['metode']);
        $this->assertTrue($p['ditebak']);

        $belum = ikp_pola(['metode' => 'sum', 'output_prioritas' => 'Penyusunan Konten', 'satuan_label' => 'Konten']);
        $this->assertSame('hitungan', $belum['pola']);
        $this->assertTrue($belum['ditebak'], 'pola NULL = tebakan saat dibaca');
    }

    public function testRilisTahunBerikutHanyaUntukPolaRilis(): void
    {
        $p = ikp_pola(['metode' => 'trend_naik', 'pola_ukur' => 'posisi', 'bulan_ukur' => '12', 'rilis_tahun_berikut' => 1]);
        $this->assertFalse($p['rilis_tahun_berikut']);
    }

    /* =========================== capaian taat pola =========================== */

    public function testIndeksYangDulunyaDicicilTidakLagiDihitungTiapBulan(): void
    {
        $pola   = $this->polaIkip();
        $target = array_fill(1, 12, 99.0);
        $real   = [1 => 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, null, null, null, null];

        // Rumus lama: 101,01 % sejak Januari.
        $lama = ikp_capaian('trend_naik', $target, $real, 1, 8);
        $this->assertSame('calculated', $lama['status']);

        $sd = ikp_capaian_pola($pola, $target, $real, 1, 8, [], 2026);
        $this->assertNull($sd['percentage']);
        $this->assertNotSame('calculated', $sd['status']);
        $this->assertTrue($sd['tidak_diukur']);
        $this->assertTrue($sd['menunggu_rilis']);
        $this->assertStringContainsString('Desember 2026', $sd['calculation_description']);

        $st = ikp_status($sd);
        $this->assertSame('menunggu_rilis', $st['code']);
        $this->assertSame('abu', $st['kelompok']);

        $ytd = ikp_capaian_pola($pola, $target, $real, 1, 12, [], 2026);
        $this->assertNull($ytd['percentage']);
        $this->assertTrue($ytd['menunggu_rilis']);
        $this->assertFalse($ytd['tidak_diukur'], 'Desember termasuk rentang; nilainya saja yang belum keluar');
    }

    public function testNilaiRilisDibandingTargetBulanRilis(): void
    {
        $pola   = $this->polaIkip();
        $target = [12 => 99.0];
        $real   = [12 => 97.02];
        $h = ikp_capaian_pola($pola, $target, $real, 1, 12, [], 2026);
        $this->assertSame('calculated', $h['status']);
        $this->assertEqualsWithDelta(98.0, $h['percentage'], 0.01);
        $this->assertSame(12, $h['bulan_terakhir']);
        $this->assertStringContainsString('tidak dijumlah', $h['calculation_description']);
    }

    public function testPosisiSemesteranMemakaiNilaiUkurTerakhir(): void
    {
        $pola   = ikp_pola(['metode' => 'trend_naik', 'pola_ukur' => 'posisi', 'bulan_ukur' => '6,12']);
        $target = [6 => 80.0, 12 => 90.0];
        $real   = [3 => 50.0, 6 => 76.0];   // Maret: bukan bulan ukur -> diabaikan

        $tw1 = ikp_capaian_pola($pola, $target, $real, 1, 3);
        $this->assertTrue($tw1['tidak_diukur']);
        $this->assertFalse($tw1['menunggu_rilis']);
        $this->assertSame('tidak_diukur', ikp_status($tw1)['code']);

        $sd = ikp_capaian_pola($pola, $target, $real, 1, 8);
        $this->assertSame('calculated', $sd['status']);
        $this->assertEqualsWithDelta(95.0, $sd['percentage'], 0.001);
        $this->assertSame(6, $sd['bulan_terakhir']);
    }

    public function testHitunganBulananSamaDenganRumusLama(): void
    {
        $pola   = ikp_pola(['metode' => 'sum', 'pola_ukur' => 'hitungan', 'bulan_ukur' => '1,2,3,4,5,6,7,8,9,10,11,12']);
        $target = [1 => 1.0, 1.0, 1.1, 1.2, 1.2, 1.2, 1.3, 1.3, 1.3, 1.4, 1.4, 1.6];
        $real   = [1 => 0.9, 1.1, 1.0, 1.3, 1.1, 1.2, 1.4, 1.2];
        $baru = ikp_capaian_pola($pola, $target, $real, 1, 8);
        $lama = ikp_capaian('sum', $target, $real, 1, 8);
        $this->assertSame($lama['percentage'], $baru['percentage']);
    }

    public function testHitunganTriwulananMengabaikanBulanNonUkur(): void
    {
        $pola   = ikp_pola(['metode' => 'sum', 'pola_ukur' => 'hitungan', 'bulan_ukur' => '3,6,9,12']);
        $target = [3 => 25.0, 6 => 25.0, 9 => 25.0, 12 => 25.0];
        $real   = [2 => 99.0, 3 => 20.0, 6 => 30.0];
        $h = ikp_capaian_pola($pola, $target, $real, 1, 6);
        $this->assertEqualsWithDelta(100.0, $h['percentage'], 0.001);   // (20+30)/(25+25)
    }

    /* =========================== keadaan sel bulan =========================== */

    public function testKeadaanBulanRilis(): void
    {
        $pola = $this->polaIkip();
        $jan  = ikp_keadaan_bulan($pola, 2026, 1, 2026, 9);
        $this->assertSame('tidak_diukur', $jan['kode']);
        $this->assertFalse($jan['terbuka']);
        $this->assertStringContainsString('Des 2026', $jan['ket']);

        $des = ikp_keadaan_bulan($pola, 2026, 12, 2026, 9);
        $this->assertSame('belum_waktunya', $des['kode']);
        $this->assertStringContainsString('Menunggu rilis Desember 2026', $des['ket']);

        $this->assertTrue(ikp_keadaan_bulan($pola, 2026, 12, 2026, 12)['terbuka']);
        $this->assertTrue(ikp_keadaan_bulan($pola, 2025, 12, 2026, 9)['terbuka']);
    }

    public function testKeadaanBulanRilisTahunBerikut(): void
    {
        $pola = ikp_pola(['metode' => 'trend_flat', 'pola_ukur' => 'rilis', 'bulan_ukur' => '5', 'rilis_tahun_berikut' => 1]);
        $mei = ikp_keadaan_bulan($pola, 2026, 5, 2026, 9);
        $this->assertSame('belum_waktunya', $mei['kode'], 'opini LKPD 2026 baru keluar Mei 2027');
        $this->assertStringContainsString('Mei 2027', $mei['ket']);
        $this->assertTrue(ikp_keadaan_bulan($pola, 2026, 5, 2027, 6)['terbuka']);
        $this->assertSame('Mei 2027', ikp_bulan_rilis_label($pola, 2026, 5));
    }

    public function testKeadaanBulanPosisiNonUkur(): void
    {
        $pola = ikp_pola(['metode' => 'trend_naik', 'pola_ukur' => 'posisi', 'bulan_ukur' => '3,6,9,12']);
        $k = ikp_keadaan_bulan($pola, 2026, 4, 2026, 9);
        $this->assertSame('tidak_diukur', $k['kode']);
        $this->assertStringContainsString('Mar, Jun, Sep, Des', $k['ket']);
        $this->assertSame('belum_waktunya', ikp_keadaan_bulan($pola, 2026, 12, 2026, 9)['kode']);
        $this->assertSame('diukur', ikp_keadaan_bulan($pola, 2026, 9, 2026, 9)['kode']);
    }

    /* =========================== target bulanan =========================== */

    public function testBagiPolaHitunganHanyaKeBulanUkur(): void
    {
        $pola = ikp_pola(['metode' => 'sum', 'pola_ukur' => 'hitungan', 'bulan_ukur' => '3,6,9,12']);
        $this->assertSame([3 => 25.0, 6 => 25.0, 9 => 25.0, 12 => 25.0], ikp_bagi_pola(100, $pola));
        $this->assertSame([3 => 3.0, 6 => 2.0, 9 => 2.0, 12 => 2.0], ikp_bagi_pola(9, $pola, null, true));
    }

    public function testBagiPolaRilisBukanCicilan(): void
    {
        $this->assertSame([12 => 99.0], ikp_bagi_pola(99, $this->polaIkip(), 95.0));

        $pos = ikp_pola(['metode' => 'trend_naik', 'pola_ukur' => 'posisi', 'bulan_ukur' => '6,12']);
        $this->assertSame([6 => 92.5, 12 => 95.0], ikp_bagi_pola(95, $pos, 90.0));
        $this->assertSame([6 => 95.0, 12 => 95.0], ikp_bagi_pola(95, $pos), 'tanpa titik awal: tidak mengarang lintasan');
    }

    public function testCekBulananHanyaBulanUkur(): void
    {
        $this->assertTrue(ikp_cek_bulanan_pola($this->polaIkip(), 99.0, [12 => 99.0])['ok']);
        $this->assertFalse(ikp_cek_bulanan_pola($this->polaIkip(), 99.0, [1 => 99.0, 12 => 97.0])['ok']);

        $pos = ikp_pola(['metode' => 'trend_naik', 'pola_ukur' => 'posisi', 'bulan_ukur' => '6,12']);
        $c = ikp_cek_bulanan_pola($pos, 95.0, [6 => 92.0]);
        $this->assertFalse($c['ok']);
        $this->assertStringContainsString('terakhir belum diisi', $c['pesan']);
    }

    public function testRingkasPola(): void
    {
        $this->assertSame('Rilis ↑ · Des 2026', ikp_pola_ringkas($this->polaIkip(), 2026));
        $this->assertSame('Hitungan · bulanan', ikp_pola_ringkas(ikp_pola(['metode' => 'sum', 'pola_ukur' => 'hitungan'])));
    }
}
