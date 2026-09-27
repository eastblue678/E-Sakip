<?php

use App\Services\IkpTurunService as T;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * IKP turun sampai pelaksana — aturan murni App\Services\IkpTurunService.
 *
 * Keluhan pemicu (28-09-2026): "IKP hanya berhenti di Es 2, ga sampai bawah."
 * IKP kini diturunkan lewat indikator simpul pohon kinerja: pemikul angka
 * (porsi untuk hitungan, target utuh untuk posisi/rilis) dan pendukung
 * (indikator proses sendiri, tidak menambah angka IKP).
 *
 * @internal
 */
final class IkpTurunTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ikp');
    }

    private function pola(string $pola, array $bulan, string $metode = 'sum'): array
    {
        return ['pola' => $pola, 'metode' => $metode, 'bulan_ukur' => $bulan, 'periode_ukur' => 'bulanan',
            'penerbit' => null, 'rilis_tahun_berikut' => false, 'ditebak' => false];
    }

    /* ============================ pemeriksa porsi (hitungan) ============================ */

    public function testHitunganTerbagiHabis(): void
    {
        $p = T::periksa('hitungan', 60000.0, [['peran' => 'angka', 'target' => 52800.0], ['peran' => 'angka', 'target' => 7200.0]], true);
        $this->assertSame('habis', $p['kode']);
        $this->assertSame('ok', $p['warna']);
        $this->assertSame(2, $p['n_angka']);
    }

    public function testHitunganKurangDanLebih(): void
    {
        $k = T::periksa('hitungan', 1000.0, [['peran' => 'angka', 'target' => 30.0]]);
        $this->assertSame('kurang', $k['kode']);
        $this->assertSame('peringatan', $k['warna']);
        $this->assertEqualsWithDelta(-970.0, $k['selisih'], 0.0001);
        $this->assertStringContainsString('970', $k['pesan']);

        $l = T::periksa('hitungan', 15.0, [['peran' => 'angka', 'target' => 14.0], ['peran' => 'angka', 'target' => 2.0]]);
        $this->assertSame('lebih', $l['kode']);
        $this->assertEqualsWithDelta(1.0, $l['selisih'], 0.0001);
    }

    public function testPendukungTidakMenambahPorsi(): void
    {
        // 15 dibagi 14 + 1; pendukung bertarget 40 dokumen tidak ikut dijumlah.
        $p = T::periksa('hitungan', 15.0, [
            ['peran' => 'angka', 'target' => 14.0], ['peran' => 'angka', 'target' => 1.0], ['peran' => 'pendukung', 'target' => 40.0],
        ], true);
        $this->assertSame('habis', $p['kode']);
        $this->assertSame(1, $p['n_pendukung']);
    }

    public function testPorsiKosongDihitungKurang(): void
    {
        $p = T::periksa('hitungan', 10.0, [['peran' => 'angka', 'target' => 10.0], ['peran' => 'angka', 'target' => null]]);
        $this->assertSame('kurang', $p['kode']);
        $this->assertStringContainsString('1 porsi belum diisi', $p['pesan']);
    }

    public function testAkarTanpaTargetIkp(): void
    {
        $p = T::periksa('hitungan', null, [['peran' => 'angka', 'target' => 5.0]], true);
        $this->assertSame('tanpa_target', $p['kode']);
    }

    /* ======================== satu pemikul angka (posisi/rilis) ========================= */

    public function testRilisSatuPemikulAngkaDenganPendukung(): void
    {
        $p = T::periksa('rilis', 99.0, [['peran' => 'angka', 'target' => 99.0], ['peran' => 'pendukung', 'target' => 40.0]], true);
        $this->assertSame('satu', $p['kode']);
        $this->assertSame('ok', $p['warna']);
    }

    public function testRilisDuaPemikulAngkaDiperingatkan(): void
    {
        $p = T::periksa('rilis', 99.0, [['peran' => 'angka', 'target' => 99.0], ['peran' => 'angka', 'target' => 99.0]], true);
        $this->assertSame('ganda', $p['kode']);
        $this->assertSame('peringatan', $p['warna']);
    }

    public function testPosisiTargetBerbedaDariTargetUtuh(): void
    {
        $p = T::periksa('posisi', 35.0, [['peran' => 'angka', 'target' => 30.0]]);
        $this->assertSame('beda_target', $p['kode']);
    }

    public function testNolPemikulAngka(): void
    {
        // Di akar: peringatan. Di bawah Kabid pemikul angka dengan pendukung saja: netral (angka tetap di Kabid).
        $akar = T::periksa('rilis', 99.0, [['peran' => 'pendukung', 'target' => 12.0]], true);
        $this->assertSame('tanpa_angka', $akar['kode']);
        $this->assertSame('peringatan', $akar['warna']);

        $bawah = T::periksa('rilis', 99.0, [['peran' => 'pendukung', 'target' => 40.0]], false, 'angka');
        $this->assertSame('tetap_di_atas', $bawah['kode']);
        $this->assertSame('netral', $bawah['warna']);

        $belum = T::periksa('hitungan', 15.0, [], true);
        $this->assertSame('belum', $belum['kode']);
        $this->assertSame('peringatan', $belum['warna']);
    }

    public function testAngkaDiBawahPendukungTerputus(): void
    {
        $p = T::periksa('hitungan', 12.0, [['peran' => 'angka', 'target' => 3.0]], false, 'pendukung');
        $this->assertSame('terputus', $p['kode']);
        $this->assertSame('peringatan', $p['warna']);
    }

    public function testPeriksaSemuaPerInduk(): void
    {
        // CKG 60.000: Kabid P2P 52.800 → Kasi 52.800; Kabid Yankes 7.200 → Kepala Puskesmas 7.000 (kurang 200).
        $baris = [
            ['id' => 1, 'induk' => null, 'peran' => 'angka', 'target' => 52800.0],
            ['id' => 2, 'induk' => 1, 'peran' => 'angka', 'target' => 52800.0],
            ['id' => 3, 'induk' => null, 'peran' => 'angka', 'target' => 7200.0],
            ['id' => 4, 'induk' => 3, 'peran' => 'angka', 'target' => 7000.0],
        ];
        $r = T::periksaSemua('hitungan', 60000.0, $baris);
        $this->assertSame('habis', $r['per_induk']['akar']['kode']);
        $this->assertSame('habis', $r['per_induk']['1']['kode']);
        $this->assertSame('kurang', $r['per_induk']['3']['kode']);
        $this->assertSame('peringatan', $r['warna']);
        $this->assertSame(1, $r['n_peringatan']);
    }

    /* ================================== cakupan ======================================== */

    public function testCakupanTurunSampai(): void
    {
        $c = T::cakupan(['es3', 'es4']);
        $this->assertTrue($c['es3']);
        $this->assertTrue($c['es4']);
        $this->assertFalse($c['pelaksana']);
        $this->assertFalse($c['sampai_pelaksana']);
        $this->assertSame('es4', $c['terbawah']);
        $label = ['es3' => 'Es III', 'es4' => 'Es IV', 'pelaksana' => 'Pelaksana'];
        $this->assertSame('Turun sampai: Es III ✓ · Es IV ✓ · Pelaksana ✗', T::cakupanTeks($c, $label));

        $this->assertTrue(T::cakupan(['es3', 'pelaksana'])['sampai_pelaksana']);
    }

    public function testCakupanKecamatanEs4SudahPelaksana(): void
    {
        // Di kecamatan Camat = Eselon III, sehingga jenjang es4 berlabel "Pelaksana / JF".
        $this->assertFalse(T::cakupan(['es3', 'es4'], false)['sampai_pelaksana']);
        $this->assertTrue(T::cakupan(['es3', 'es4'], true)['sampai_pelaksana']);
    }

    /* ================================ profil bulanan =================================== */

    public function testBagiKumulatifMenyebarSisaDanTepatJumlah(): void
    {
        $h = T::bagiKumulatif(4.0, array_fill(1, 12, 1.0), 0);
        $this->assertEqualsWithDelta(4.0, array_sum($h), 1e-9);
        $this->assertSame([2, 5, 8, 11], array_keys(array_filter($h, static fn ($v) => $v > 0)));

        $s = T::bagiKumulatif(1.0, array_fill(1, 12, 1.0), 0);
        $this->assertSame(1.0, $s[6]);   // satu dokumen di pertengahan tahun, bukan Januari
        $this->assertEqualsWithDelta(1.0, array_sum($s), 1e-9);
    }

    public function testProfilHitunganProporsionalCicilanIkp(): void
    {
        // IKP 12 per tahun, cicilan 1/bulan; porsi pelaksana 6 → 0,5/bulan tidak bulat → pembulatan kumulatif bulat.
        $pola = $this->pola('hitungan', range(1, 12));
        $p    = T::profilBulanan($pola, array_fill(1, 12, 1.0), 6.0, 'angka');
        $this->assertEqualsWithDelta(6.0, array_sum($p), 1e-9);
        foreach ($p as $v) {
            $this->assertContains($v, [0.0, 1.0]);
        }

        // Porsi Puskesmas 7.200 dari CKG 60.000 dengan cicilan 5.000/bulan → 600/bulan.
        $q = T::profilBulanan($pola, array_fill(1, 12, 5000.0), 7200.0, 'angka');
        $this->assertSame(600.0, $q[1]);
        $this->assertEqualsWithDelta(7200.0, array_sum($q), 1e-9);
    }

    public function testProfilRilisHanyaBulanRilisDanTidakDicicil(): void
    {
        $pola = $this->pola('rilis', [12], 'trend_naik');
        // Data lama "dicicil" 99 setiap bulan: hanya Desember yang terbawa.
        $p = T::profilBulanan($pola, array_fill(1, 12, 99.0), 99.0, 'angka');
        $this->assertSame(99.0, $p[12]);
        $this->assertNull($p[1]);
        $this->assertSame(1, count(array_filter($p, static fn ($v) => $v !== null)));

        // Tanpa breakdown: target utuh di bulan rilis.
        $q = T::profilBulanan($pola, [], 99.0, 'angka');
        $this->assertSame(99.0, $q[12]);
    }

    public function testProfilPosisiSemesteranMengikutiTargetBulanUkur(): void
    {
        $pola = $this->pola('posisi', [6, 12], 'trend_naik');
        $p    = T::profilBulanan($pola, [6 => 30.0, 12 => 35.0], 35.0, 'angka');
        $this->assertSame(30.0, $p[6]);
        $this->assertSame(35.0, $p[12]);
        $this->assertNull($p[7]);
    }

    public function testProfilPendukungCicilanProsesSendiri(): void
    {
        // Pendukung IKIP: 40 bukti dukung SAQ setahun, dicicil sepanjang tahun (kerja sepanjang tahun terukur).
        $pola = $this->pola('rilis', [12], 'trend_naik');
        $p    = T::profilBulanan($pola, [12 => 99.0], 40.0, 'pendukung');
        $this->assertEqualsWithDelta(40.0, array_sum($p), 1e-9);
        $this->assertNotNull($p[1]);
        $this->assertCount(12, array_filter($p, static fn ($v) => $v !== null));
    }

    /* =========================== porsi bawaan & kemiripan ============================== */

    public function testBagiPorsiProporsionalDanSisaTerbesar(): void
    {
        $p = T::bagiPorsi(60000.0, ['a' => 60000, 'b' => 7200]);
        $this->assertEqualsWithDelta(60000.0, array_sum($p), 1e-9);
        $this->assertSame(53571.0, $p['a']);
        $this->assertSame(6429.0, $p['b']);

        $r = T::bagiPorsi(10.0, ['x' => null, 'y' => 5, 'z' => 5]);   // ada tanpa bobot → rata
        $this->assertSame([4.0, 3.0, 3.0], array_values($r));
    }

    public function testMiripMengabaikanKataUmum(): void
    {
        $this->assertSame(1.0, T::mirip('Pelayanan Cek Kesehatan Gratis', 'Jumlah penduduk yang mendapatkan pelayanan Cek Kesehatan Gratis'));
        $this->assertGreaterThanOrEqual(0.6, T::mirip('Keterbukaan Informasi Publik', 'Meningkatnya kualitas pelayanan informasi publik'));
        $this->assertLessThan(0.6, T::mirip('Keterbukaan Informasi Publik', 'Jumlah naskah dinas yang diagendakan dan diarsipkan'));
        $this->assertTrue(T::satuanSepadan('Indeks Keterbukaan Informasi Publik', 'Indeks'));
        $this->assertFalse(T::satuanSepadan('%', 'Orang'));
        $this->assertTrue(T::satuanSepadan(null, 'Orang'));
    }

    /* ================================= rantai induk =================================== */

    public function testSusunIndukMelompatiJenjangYangTidakIkut(): void
    {
        // es3 10 → es4 20 → pelaksana 30; hanya 10 dan 30 yang memikul: induk 30 = baris simpul 10.
        $induk = T::susunInduk([10 => null, 20 => 10, 30 => 20], [10 => 101, 30 => 103]);
        $this->assertNull($induk[101]);
        $this->assertSame(101, $induk[103]);
    }

    /* ================================== usulan ======================================= */

    private function pohonKominfo(): array
    {
        return [
            'simpul' => [
                619  => ['id' => 619, 'level' => 'es3', 'induk' => null, 'nama' => 'Meningkatnya kualitas komunikasi publik', 'indikator' => [739]],
                1722 => ['id' => 1722, 'level' => 'es4', 'induk' => 619, 'nama' => 'Meningkatnya kualitas pelayanan informasi publik', 'indikator' => [2014]],
                1724 => ['id' => 1724, 'level' => 'pelaksana', 'induk' => 1722, 'nama' => 'Terlaksananya Pelayanan Informasi Publik', 'indikator' => [2016]],
                2185 => ['id' => 2185, 'level' => 'es3', 'induk' => null, 'nama' => 'Meningkatnya Akuntabilitas Kinerja Dinas', 'indikator' => [2490]],
            ],
            'indikator' => [
                739  => ['id' => 739, 'nama' => 'Indeks Keterbukaan Informasi Publik', 'satuan' => 'Indeks', 'target' => 97.5, 'ikp_id' => null],
                2014 => ['id' => 2014, 'nama' => 'Persentase informasi publik yang tersedia sesuai ketentuan', 'satuan' => '%', 'target' => 90.0, 'ikp_id' => null],
                2016 => ['id' => 2016, 'nama' => 'Jumlah permohonan Informasi Publik yang diselesaikan', 'satuan' => 'Permohonan', 'target' => 24.0, 'ikp_id' => null],
                2490 => ['id' => 2490, 'nama' => 'Nilai SAKIP', 'satuan' => 'Nilai', 'target' => 74.0, 'ikp_id' => null],
            ],
        ];
    }

    public function testUsulanRilisAngkaDiPejabatPendukungDiBawah(): void
    {
        $u = T::usulkan(['id' => 380, 'nama' => 'Keterbukaan Informasi Publik', 'satuan' => 'Indeks Keterbukaan Informasi Publik',
            'pola' => 'rilis', 'target' => 99.0], $this->pohonKominfo());
        $this->assertSame('angka', $u[619]['peran']);
        $this->assertSame(739, $u[619]['indikator']);
        $this->assertSame(99.0, $u[619]['target']);
        $this->assertSame('pendukung', $u[1724]['peran']);
        $this->assertSame('baru', $u[1724]['indikator']);
        $this->assertStringContainsString('bukti dukung', $u[1724]['teks']);
        $this->assertArrayNotHasKey(2185, $u);
    }

    public function testUsulanHitunganLeluhurTerbagiHabis(): void
    {
        $pohon = $this->pohonKominfo();
        $u = T::usulkan(['id' => 327, 'nama' => 'Pelayanan Informasi Publik', 'satuan' => 'Permohonan', 'pola' => 'hitungan', 'target' => 4.0], $pohon);
        $this->assertSame('angka', $u[1724]['peran']);
        $this->assertSame(2016, $u[1724]['indikator']);
        $this->assertSame(4.0, $u[1724]['target']);
        $this->assertSame(4.0, $u[1722]['target']);   // leluhur = Σ porsi turunan
        $this->assertSame(4.0, $u[619]['target']);
    }

    public function testUsulanMemakaiTautanLama(): void
    {
        $pohon = $this->pohonKominfo();
        $pohon['indikator'][2490]['ikp_id'] = 999;   // tautan lama ke IKP ini walau teksnya tidak mirip
        $u = T::usulkan(['id' => 999, 'nama' => 'Sesuatu yang lain sama sekali', 'satuan' => 'Nilai', 'pola' => 'posisi', 'target' => 80.0], $pohon);
        $this->assertSame(2490, $u[2185]['indikator']);
        $this->assertSame(80.0, $u[2185]['target']);
    }

    /* ============================ saran realisasi dari eKin ============================ */

    public function testSaranHitunganMenjumlahDaunAngkaSaja(): void
    {
        $pola  = $this->pola('hitungan', range(1, 12));
        $baris = [
            ['id' => 1, 'induk' => null, 'peran' => 'angka', 'level' => 'es3'],
            ['id' => 2, 'induk' => 1, 'peran' => 'angka', 'level' => 'es4'],
            ['id' => 3, 'induk' => 1, 'peran' => 'angka', 'level' => 'es4'],
            ['id' => 4, 'induk' => 2, 'peran' => 'pendukung', 'level' => 'pelaksana'],
        ];
        $lapor = [
            1 => [3 => [['pegawai_id' => 10, 'realisasi' => 99]]],   // atasan tidak ikut dijumlah
            2 => [3 => [['pegawai_id' => 20, 'realisasi' => 400], ['pegawai_id' => 21, 'realisasi' => 100]]],
            3 => [3 => [['pegawai_id' => 30, 'realisasi' => 50]]],
            4 => [3 => [['pegawai_id' => 40, 'realisasi' => 7]]],   // pendukung tidak dijumlah
        ];
        $s = T::saranRealisasi($pola, $baris, $lapor);
        $this->assertSame(550.0, $s[3]['nilai']);
        $this->assertSame('jumlah_porsi', $s[3]['cara']);
        $this->assertTrue($s[3]['lengkap']);
        $this->assertArrayNotHasKey(4, $s);
    }

    public function testSaranRilisDariPemikulTerdekatHanyaBulanRilis(): void
    {
        $pola  = $this->pola('rilis', [12], 'trend_naik');
        $baris = [
            ['id' => 1, 'induk' => null, 'peran' => 'angka', 'level' => 'es3'],
            ['id' => 2, 'induk' => 1, 'peran' => 'angka', 'level' => 'es4'],
        ];
        $lapor = [
            2 => [12 => [['pegawai_id' => 20, 'realisasi' => 97.1]], 5 => [['pegawai_id' => 20, 'realisasi' => 50]]],
            1 => [12 => [['pegawai_id' => 10, 'realisasi' => 97.25]]],
        ];
        $s = T::saranRealisasi($pola, $baris, $lapor);
        $this->assertSame(97.25, $s[12]['nilai']);
        $this->assertSame('es3', $s[12]['level']);
        $this->assertArrayNotHasKey(5, $s);   // bukan bulan rilis: tidak pernah disarankan

        unset($lapor[1]);   // Kabid belum melapor → turun satu jenjang
        $this->assertSame(97.1, T::saranRealisasi($pola, $baris, $lapor)[12]['nilai']);
    }
}
