<?php

use App\Services\EkinClient;
use App\Services\IkpTurunService as T;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * IKP turun sampai pelaksana — keputusan 29-09-2026 (uji coba pengguna di Diskominfo):
 *   D1 pengiriman otomatis ke eKin sesudah Simpan (ringkasan flash, eKin mati = tidak gagal);
 *   D2 status per pemilik dari eKin (chip, amplop kontrak baru, tembolok ≤ 60 detik);
 *   D3 peran EFEKTIF menurut satuan (angka bersatuan lain = pendukung) + pemeriksa rantai pendukung;
 *   D4 posisi terbagi (beberapa pemikul angka berporsi, target bulanan = posisi IKP × porsi/target IKP,
 *      saran realisasi = Σ posisi bagian per bulan).
 *
 * Pemicu nyata: IKP 381 "Pengelolaan Media Komunikasi Publik" (Media, target 48) diturunkan ke Es III porsi 50.000
 * → Es IV "Jumlah Followers Media Sosial" (Pengikut) 50.000 → pelaksana "followers Instagram" 10.000.
 *
 * @internal
 */
final class IkpTurunSatuanTerbagiTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ikp');
    }

    private function pola(string $pola, array $bulan, string $metode = 'sum', bool $terbagi = false): array
    {
        return ['pola' => $pola, 'metode' => $metode, 'bulan_ukur' => $bulan, 'periode_ukur' => 'bulanan',
            'penerbit' => null, 'rilis_tahun_berikut' => false, 'ditebak' => false, 'posisi_terbagi' => $terbagi];
    }

    /* ================================ D3 — satuan sepadan ================================ */

    public function testSatuanDirapikanDanSinonimDariSatuTempat(): void
    {
        $this->assertSame('pengikut', ikp_satuan_rapikan(' Pengikut (followers). '));
        $this->assertSame('persentase', ikp_satuan_rapikan('Persentase (%)'));
        $this->assertSame('%', ikp_satuan_rapikan('(%)'));   // kurung saja: isi kurung dipertahankan
        $this->assertTrue(ikp_satuan_sama('Followers', 'pengikut'));
        $this->assertTrue(ikp_satuan_sama('Persentase (%)', '%'));
        $this->assertTrue(ikp_satuan_sama('MEDIA', ' media '));
        $this->assertFalse(ikp_satuan_sama('Pengikut', 'Media'));
        $this->assertNull(ikp_satuan_sama('', 'Media'));   // tidak dapat dinilai
        $this->assertNull(ikp_satuan_sama('Media', null));

        // Satuan IKP sering berupa frasa penjelas: kata pertamanya = satuan satu kata; garis miring = salah satu bagian.
        $this->assertTrue(ikp_satuan_sama('Indeks Keterbukaan Informasi Publik', 'Indeks'));
        $this->assertTrue(ikp_satuan_sama('permohonan informasi', 'Permohonan'));
        $this->assertTrue(ikp_satuan_sama('aduan SP4N-LAPOR!', 'Aduan'));
        $this->assertTrue(ikp_satuan_sama('Pekon/kelurahan', 'Kelurahan'));
        $this->assertTrue(ikp_satuan_sama('Persentase penduduk', '%'));   // kata pertama lewat sinonim
        $this->assertFalse(ikp_satuan_sama('Indeks Keterbukaan Informasi Publik', 'Indeks Pembangunan Statistik'));   // dua frasa: utuh
        $this->assertFalse(ikp_satuan_sama('KM', 'Paket'));
        $this->assertFalse(ikp_satuan_sama('Dokumen', 'Usulan'));

        $peta = ikp_satuan_peta();
        $this->assertSame('pengikut', $peta['followers']);
        $this->assertSame('%', $peta['persen']);
    }

    public function testPeranEfektifMenurutSatuan(): void
    {
        $e = T::peranEfektif('angka', 'Pengikut', 'Media');
        $this->assertSame('pendukung', $e['peran']);
        $this->assertSame('angka', $e['peran_tersimpan']);
        $this->assertSame('satuan_beda', $e['alasan_peran_kode']);
        $this->assertSame('Dihitung sebagai pendukung: satuan Pengikut ≠ Media.', $e['alasan_peran']);

        $this->assertSame('angka', T::peranEfektif('angka', 'media', 'Media')['peran']);
        $this->assertSame('angka', T::peranEfektif('angka', 'Followers', 'Pengikut')['peran']);   // sinonim
        $this->assertSame('angka', T::peranEfektif('angka', '', 'Media')['peran']);               // satuan kosong: peran tersimpan
        $p = T::peranEfektif('pendukung', 'Dokumen', 'Media');
        $this->assertSame('pendukung', $p['peran']);
        $this->assertNull($p['alasan_peran']);   // pendukung pilihan sendiri, bukan karena satuan
    }

    public function testKasusDiskominfoMediaDanPengikut(): void
    {
        // IKP 381 hitungan, satuan Media, target 2026 = 48. Baris 363/364/365 percobaan pengguna.
        $baris = [
            ['id' => 363, 'induk' => null, 'peran' => 'angka', 'target' => 50000.0, 'satuan' => 'Media'],
            ['id' => 364, 'induk' => 363, 'peran' => 'angka', 'target' => 50000.0, 'satuan' => 'Pengikut'],
            ['id' => 365, 'induk' => 364, 'peran' => 'angka', 'target' => 10000.0, 'satuan' => 'Pengikut'],
        ];
        $r = T::periksaSemua('hitungan', 48.0, $baris, ['satuan_ikp' => 'Media']);

        // Es III bersatuan Media memikul angka — dan porsinya 50.000 melebihi 48 Media.
        $this->assertSame('lebih', $r['per_induk']['akar']['kode']);
        $this->assertStringContainsString('Media', $r['per_induk']['akar']['pesan']);
        // Es IV bersatuan Pengikut dihitung pendukung: angka IKP tetap di Es III.
        $this->assertSame('pendukung', $r['efektif'][364]['peran']);
        $this->assertSame('pendukung', $r['efektif'][365]['peran']);
        $this->assertSame('tetap_di_atas', $r['per_induk']['363']['kode']);
        // Rantai pendukung Pengikut diperiksa porsinya sendiri: 10.000 dari 50.000.
        $this->assertSame('kurang', $r['per_induk']['364']['kode']);
        $this->assertSame('pendukung', $r['per_induk']['364']['lingkup']);
        $this->assertStringContainsString('Rantai pendukung (Pengikut)', $r['per_induk']['364']['pesan']);
        $this->assertStringContainsString('40.000', $r['per_induk']['364']['pesan']);

        // Tanpa kunci satuan_ikp: perilaku lama (peran apa adanya), tanpa daftar efektif.
        $lama = T::periksaSemua('hitungan', 48.0, $baris);
        $this->assertSame([], $lama['efektif']);
        $this->assertSame('habis', $lama['per_induk']['363']['kode']);
    }

    public function testRantaiPendukungHanyaMenjumlahSatuanYangSama(): void
    {
        $p = T::periksa('hitungan', 40.0, [
            ['peran' => 'pendukung', 'target' => 25.0, 'satuan' => 'Dokumen'],
            ['peran' => 'pendukung', 'target' => 15.0, 'satuan' => 'dok'],       // sinonim Dokumen
            ['peran' => 'pendukung', 'target' => 12.0, 'satuan' => 'Laporan'],   // satuan lain: tidak dijumlah
        ], false, 'pendukung', ['satuan_induk' => 'Dokumen']);
        $this->assertSame('habis', $p['kode']);
        $this->assertSame('pendukung', $p['lingkup']);
        $this->assertStringContainsString('1 pendukung bersatuan lain', $p['pesan']);

        // Tanpa satuan induk: netral seperti dulu.
        $n = T::periksa('hitungan', 40.0, [['peran' => 'pendukung', 'target' => 25.0, 'satuan' => 'Dokumen']], false, 'pendukung');
        $this->assertSame('pendukung', $n['kode']);
        $this->assertSame('netral', $n['warna']);
    }

    public function testEfektifkanMenambahKunciTanpaMengubahDataTersimpan(): void
    {
        $out = T::efektifkan([['id' => 1, 'ikp_peran' => 'angka', 'satuan' => 'Pengikut', 'target' => 5.0]], 'Media');
        $this->assertSame('pendukung', $out[0]['peran']);
        $this->assertSame('angka', $out[0]['ikp_peran']);   // kolom tersimpan utuh
        $this->assertSame(5.0, $out[0]['target']);
    }

    /* ================================ D4 — posisi terbagi ================================ */

    public function testIkpPolaMengenalPosisiTerbagiHanyaUntukPosisi(): void
    {
        $p = ikp_pola(['pola_ukur' => 'posisi', 'metode' => 'trend_naik', 'bulan_ukur' => '1,2,3,4,5,6,7,8,9,10,11,12', 'posisi_terbagi' => 1]);
        $this->assertTrue($p['posisi_terbagi']);
        $this->assertStringContainsString('terbagi per bagian', ikp_pola_ringkas($p));
        $this->assertFalse(ikp_pola(['pola_ukur' => 'hitungan', 'metode' => 'sum', 'posisi_terbagi' => 1])['posisi_terbagi']);
        $this->assertFalse(ikp_pola(['pola_ukur' => 'posisi', 'metode' => 'trend_naik'])['posisi_terbagi']);
    }

    public function testPosisiTerbagiBolehBeberapaPemikulAngkaBerporsi(): void
    {
        // Pengikut akun resmi 50.000 = IG 22.000 + FB 15.000 + TikTok 9.000 + YouTube 4.000.
        $anak = [
            ['peran' => 'angka', 'target' => 22000.0, 'satuan' => 'Pengikut'], ['peran' => 'angka', 'target' => 15000.0, 'satuan' => 'Pengikut'],
            ['peran' => 'angka', 'target' => 9000.0, 'satuan' => 'Pengikut'], ['peran' => 'angka', 'target' => 4000.0, 'satuan' => 'Pengikut'],
        ];
        $p = T::periksa('posisi', 50000.0, $anak, false, 'angka', ['terbagi' => true, 'satuan' => 'Pengikut']);
        $this->assertSame('habis', $p['kode']);
        $this->assertStringContainsString('Posisi terbagi', $p['pesan']);

        $kurang = T::periksa('posisi', 50000.0, array_slice($anak, 0, 3), false, 'angka', ['terbagi' => true]);
        $this->assertSame('kurang', $kurang['kode']);

        // Tanpa bendera: tetap "satu pemikul angka" + petunjuk ke bendera.
        $g = T::periksa('posisi', 50000.0, $anak);
        $this->assertSame('ganda', $g['kode']);
        $this->assertStringContainsString('Dapat dipecah per bagian', $g['pesan']);
    }

    public function testProfilPosisiTerbagiSkalaPosisiBukanCicilan(): void
    {
        $pola = $this->pola('posisi', range(1, 12), 'trend_naik', true);
        $ikp  = [1 => 41000.0, 2 => 41800.0, 3 => 42500.0, 4 => 43300.0, 5 => 44100.0, 6 => 44900.0,
            7 => 45700.0, 8 => 46500.0, 9 => 47400.0, 10 => 48200.0, 11 => 49100.0, 12 => 50000.0];
        $ig = T::profilBulanan($pola, $ikp, 22000.0, 'angka', 50000.0);
        $this->assertSame(18040.0, $ig[1]);    // 41.000 × 22.000/50.000
        $this->assertSame(22000.0, $ig[12]);   // posisi akhir = porsi, bukan Σ cicilan
        $this->assertGreaterThan(22000.0 * 1.5, array_sum($ig));   // posisi, tidak dicicil

        // Jenjang tengah = porsi utuhnya (Es IV memikul semua 50.000) → sama dengan target IKP.
        $this->assertSame($ikp[7], T::profilBulanan($pola, $ikp, 50000.0, 'angka', 50000.0)[7]);

        // Tanpa breakdown IKP: porsi di setiap bulan ukur; bulan non-ukur null.
        $sem = T::profilBulanan($this->pola('posisi', [6, 12], 'trend_naik', true), [], 9000.0, 'angka', 50000.0);
        $this->assertSame(9000.0, $sem[6]);
        $this->assertSame(9000.0, $sem[12]);
        $this->assertNull($sem[1]);

        // Posisi biasa (tanpa bendera): target utuh IKP, tidak diskala.
        $this->assertSame(41000.0, T::profilBulanan($this->pola('posisi', range(1, 12), 'trend_naik'), $ikp, 22000.0, 'angka', 50000.0)[1]);
    }

    public function testSaranPosisiTerbagiMenjumlahPosisiBagianPerBulan(): void
    {
        $pola  = $this->pola('posisi', range(1, 12), 'trend_naik', true);
        $baris = [
            ['id' => 1, 'induk' => null, 'peran' => 'angka', 'level' => 'es3'],
            ['id' => 2, 'induk' => 1, 'peran' => 'angka', 'level' => 'es4'],
            ['id' => 3, 'induk' => 2, 'peran' => 'angka', 'level' => 'pelaksana'],   // IG
            ['id' => 4, 'induk' => 2, 'peran' => 'angka', 'level' => 'pelaksana'],   // FB
        ];
        // Jenjang tengah = sendiri (0) + Σ bawahan; `baris` = bagian baris itu sendiri.
        $lapor = [
            1 => [7 => [['pegawai_id' => 10, 'realisasi' => 33600.0, 'baris' => 0.0]], 8 => [['pegawai_id' => 10, 'realisasi' => 34500.0, 'baris' => 0.0]]],
            2 => [7 => [['pegawai_id' => 20, 'realisasi' => 33600.0, 'baris' => 0.0]], 8 => [['pegawai_id' => 20, 'realisasi' => 34500.0, 'baris' => 0.0]]],
            3 => [7 => [['pegawai_id' => 30, 'realisasi' => 20100.0, 'baris' => 20100.0]], 8 => [['pegawai_id' => 30, 'realisasi' => 20700.0, 'baris' => 20700.0]]],
            4 => [7 => [['pegawai_id' => 40, 'realisasi' => 13500.0, 'baris' => 13500.0]], 8 => [['pegawai_id' => 40, 'realisasi' => 13800.0, 'baris' => 13800.0]]],
        ];
        $s = T::saranRealisasi($pola, $baris, $lapor);
        $this->assertSame(33600.0, $s[7]['nilai']);
        $this->assertSame(34500.0, $s[8]['nilai']);   // Agustus berdiri sendiri — tidak ditambah Juli
        $this->assertSame('jumlah_posisi', $s[8]['cara']);
        $this->assertTrue($s[8]['lengkap']);

        unset($lapor[4][8]);   // FB belum melapor Agustus
        $this->assertFalse(T::saranRealisasi($pola, $baris, $lapor)[8]['lengkap']);
    }

    public function testUsulanPosisiTerbagiSepertiHitungan(): void
    {
        $pohon = [
            'simpul' => [
                10 => ['id' => 10, 'level' => 'es3', 'induk' => null, 'nama' => 'Meningkatnya jangkauan media sosial pemerintah', 'indikator' => [100]],
                20 => ['id' => 20, 'level' => 'pelaksana', 'induk' => 10, 'nama' => 'Pengelolaan akun Instagram', 'indikator' => [200]],
                30 => ['id' => 30, 'level' => 'pelaksana', 'induk' => 10, 'nama' => 'Pengelolaan akun Facebook', 'indikator' => [300]],
            ],
            'indikator' => [
                100 => ['id' => 100, 'nama' => 'Jumlah pengikut media sosial resmi', 'satuan' => 'Pengikut', 'target' => null, 'ikp_id' => null],
                200 => ['id' => 200, 'nama' => 'Jumlah pengikut media sosial resmi Instagram', 'satuan' => 'Pengikut', 'target' => 30000.0, 'ikp_id' => null],
                300 => ['id' => 300, 'nama' => 'Jumlah pengikut media sosial resmi Facebook', 'satuan' => 'Pengikut', 'target' => 20000.0, 'ikp_id' => null],
            ],
        ];
        $u = T::usulkan(['id' => 900, 'nama' => 'Jumlah pengikut media sosial resmi', 'satuan' => 'Pengikut', 'pola' => 'posisi',
            'target' => 50000.0, 'terbagi' => true], $pohon);
        $this->assertSame('angka', $u[20]['peran']);
        $this->assertSame('angka', $u[30]['peran']);
        $this->assertSame(30000.0, $u[20]['target']);
        $this->assertSame(20000.0, $u[30]['target']);
        $this->assertSame(50000.0, $u[10]['target']);
    }

    /* ========================= D1/D2 — kirim & status di eKin ========================== */

    public function testAmplopKontrakBaru(): void
    {
        $this->assertSame(['baris' => []], EkinClient::bukaAmplop(['status' => 'success', 'data' => ['baris' => []]]));
        $this->assertNull(EkinClient::bukaAmplop(['status' => 'error', 'message' => 'x']));
        $this->assertSame(['baris' => [1]], EkinClient::bukaAmplop(['baris' => [1]]));   // tanpa amplop: apa adanya
    }

    public function testSegarkanMengirimPostJsonDanMembacaAmplop(): void
    {
        $lihat = [];
        $k = new EkinClient('http://127.0.0.1:8195/', 'token-uji', static function (string $url, array $header, string $metode = 'GET', ?string $badan = null) use (&$lihat) {
            $lihat = compact('url', 'header', 'metode', 'badan');

            return [200, json_encode(['status' => 'success', 'data' => ['opd_id' => 20, 'tahun' => 2026,
                'pegawai' => [['pegawai_id' => 8239, 'dibuat' => 1]], 'ringkas' => ['dibuat' => 1]]])];
        }, false);
        $j = $k->segarkanIkpTurunan(20, 2026);
        $this->assertSame(1, $j['ringkas']['dibuat']);
        $this->assertSame('POST', $lihat['metode']);
        $this->assertSame('http://127.0.0.1:8195/api/aksara/ikp-turunan/segarkan', $lihat['url']);
        $this->assertSame(['opd_id' => 20, 'tahun' => 2026], json_decode((string) $lihat['badan'], true));
        $this->assertSame('Bearer token-uji', $lihat['header']['Authorization']);
        $this->assertSame('application/json', $lihat['header']['Content-Type']);
    }

    public function testSegarkanGagalMenjadiAlasan(): void
    {
        foreach ([503 => 'aksara_tak_terjangkau', 401 => 'ditolak', 404 => 'belum_tersedia', 400 => 'isian_ditolak', 500 => 'galat_server'] as $kode => $alasan) {
            $k = new EkinClient('http://x/', 't', static fn () => [$kode, '{"status":"error"}'], false);
            $this->assertNull($k->segarkanIkpTurunan(20, 2026));
            $this->assertSame($alasan, $k->alasanTerakhir(), 'HTTP ' . $kode);
        }
        $mati = new EkinClient('http://x/', 't', static function () { throw new RuntimeException('putus'); }, false);
        $this->assertNull($mati->segarkanIkpTurunan(20, 2026));
        $this->assertSame('tidak_terjangkau', $mati->alasanTerakhir());
        $belum = new EkinClient('', '', static fn () => [200, '{}'], false);
        $this->assertNull($belum->segarkanIkpTurunan(20, 2026));
        $this->assertSame('belum_dikonfigurasi', $belum->alasanTerakhir());
        // Amplop sukses tanpa pegawai/ringkas = tidak sesuai kontrak.
        $aneh = new EkinClient('http://x/', 't', static fn () => [200, '{"status":"success","data":{"opd_id":20}}'], false);
        $this->assertNull($aneh->segarkanIkpTurunan(20, 2026));
        $this->assertSame('format', $aneh->alasanTerakhir());
    }

    public function testStatusDitemboloki60DetikDanDihapusSesudahSimpan(): void
    {
        $cache = new class () {
            public array $isi = [];
            public array $umur = [];

            public function get(string $k) { return $this->isi[$k] ?? null; }

            public function save(string $k, $v, int $ttl = 60): bool { $this->isi[$k] = $v; $this->umur[$k] = $ttl; return true; }

            public function delete(string $k): bool { unset($this->isi[$k]); return true; }
        };
        $n = 0;
        $k = new EkinClient('http://x/', 't', static function (string $url) use (&$n) {
            $n++;

            return [200, json_encode(['status' => 'success', 'data' => ['opd_id' => 20, 'tahun' => 2026,
                'baris' => [['delegasi_id' => 365, 'pegawai_id' => 8239, 'status' => 'menunggu']]]])];
        }, $cache);
        $this->assertSame('menunggu', $k->ikpTurunanStatus(20, 2026)['baris'][0]['status']);
        $k->ikpTurunanStatus(20, 2026);
        $this->assertSame(1, $n);                                    // kedua dari tembolok
        $this->assertSame([EkinClient::UMUR_STATUS], array_values($cache->umur));
        $this->assertLessThanOrEqual(60, EkinClient::UMUR_STATUS);
        $k->lupakanStatusIkpTurunan(20, 2026);
        $k->ikpTurunanStatus(20, 2026);
        $this->assertSame(2, $n);                                    // sesudah Simpan dibaca ulang

        // Endpoint belum ada (eKin lama): null + belum_tersedia, bukan pengecualian.
        $lama = new EkinClient('http://x/', 't', static fn () => [404, '{"status":"error"}'], false);
        $this->assertNull($lama->ikpTurunanStatus(20, 2026));
        $this->assertSame('belum_tersedia', $lama->alasanTerakhir());
    }

    public function testRingkasKirimUntukFlash(): void
    {
        $ok = T::ringkasKirim([
            ['pegawai' => [['pegawai_id' => 8239], ['pegawai_id' => 304]], 'ringkas' => ['dibuat' => 1, 'dimasukkan_skp_draf' => 0, 'sudah' => 1, 'tanpa_skp' => 0]],
            ['pegawai' => [['pegawai_id' => 999]], 'ringkas' => ['tanpa_skp' => 1]],
        ], null, 2026);
        $this->assertSame('ok', $ok['jenis']);
        $this->assertSame(3, $ok['pegawai']);
        $this->assertSame(1, $ok['ringkas']['dibuat']);
        $this->assertCount(3, $ok['rincian']);
        $this->assertStringContainsString('menunggu diterima', $ok['rincian'][0]);
        $this->assertStringContainsString('SKP 2026', $ok['rincian'][2]);

        $gagal = T::ringkasKirim([], 'tidak_terjangkau', 2026, 'eKin tidak dapat dihubungi saat ini.');
        $this->assertSame('gagal', $gagal['jenis']);
        $this->assertStringContainsString('eKin tidak dapat dihubungi saat ini', $gagal['pesan']);
        $this->assertStringContainsString('setiap malam', $gagal['pesan']);

        $tenang = T::ringkasKirim([['pegawai' => [['pegawai_id' => 1]], 'ringkas' => []]], null, 2026);
        $this->assertStringContainsString('tidak ada yang perlu diubah', $tenang['pesan']);

        $sebagian = T::ringkasKirim([['pegawai' => [], 'ringkas' => ['dibuat' => 2]]], 'galat_server', 2026, 'eKin sedang mengalami gangguan.');
        $this->assertSame('sebagian', $sebagian['jenis']);
    }

    public function testChipStatusPerPemilik(): void
    {
        $indeks = T::indeksStatusEkin([
            ['delegasi_id' => 365, 'pegawai_id' => 8239, 'status' => 'menunggu', 'penugasan_id' => 77],
            ['delegasi_id' => 364, 'pegawai_id' => 304, 'status' => 'rhk', 'beda_target' => true, 'target_ekin' => 10000, 'target_aksara' => 50000],
            ['delegasi_id' => 363, 'pegawai_id' => 1012, 'status' => 'aneh'],
            ['delegasi_id' => 'x', 'pegawai_id' => 1],   // dibuang
        ]);
        $this->assertSame(77, $indeks[365][8239]['penugasan_id']);
        $this->assertSame('belum', $indeks[363][1012]['status']);

        $c = T::chipStatusEkin($indeks, 365, 8239, 2026);
        $this->assertSame('Menunggu diterima', $c['label']);
        $this->assertSame('tunggu', $c['kelas']);
        $this->assertNull($c['beda']);

        $b = T::chipStatusEkin($indeks, 364, 304, 2026);
        $this->assertSame('Masuk RHK ✓', $b['label']);
        $this->assertSame('Target di eKin berbeda', $b['beda']['label']);
        $this->assertStringContainsString('10.000', $b['beda']['judul']);

        $this->assertSame('Belum terkirim', T::chipStatusEkin($indeks, 365, 1, 2026)['label']);   // pemilik tak dikenal eKin
        $this->assertSame('Belum punya SKP 2027', T::chipStatusEkin(T::indeksStatusEkin([
            ['delegasi_id' => 1, 'pegawai_id' => 2, 'status' => 'tanpa_skp']]), 1, 2, 2027)['label']);
        $this->assertSame('SKP masih draf — sudah dimasukkan', T::chipStatusEkin(T::indeksStatusEkin([
            ['delegasi_id' => 1, 'pegawai_id' => 2, 'status' => 'skp_draf']]), 1, 2, 2026)['label']);
        $this->assertSame('Ditolak pegawai', T::chipStatusEkin(T::indeksStatusEkin([
            ['delegasi_id' => 1, 'pegawai_id' => 2, 'status' => 'ditolak']]), 1, 2, 2026)['label']);
    }
}
