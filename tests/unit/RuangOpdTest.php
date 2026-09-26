<?php

use App\Services\EkinClient;
use App\Services\RuangOpdService;
use App\Services\RuangOpdTautan;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AKSARA+ — aturan murni Ruang OPD: peta tautan per peran, sel status matriks, dan klien eKin
 * (pohon cascading pegawai + penanganan kegagalan). Tanpa basis data & tanpa jaringan.
 *
 * Contoh data di sini karangan (id & teks generik) — tidak ada nama/NIP orang sungguhan.
 *
 * @internal
 */
final class RuangOpdTest extends CIUnitTestCase
{
    private const ITEM = ['dashboard', 'renstra', 'rkt', 'iku', 'cascading', 'pohon', 'pemilik', 'pk', 'pk_lihat', 'pk_cetak',
        'pk_edit', 'renaksi', 'monev', 'ikp', 'ikp_realisasi', 'lakip', 'evaluasi'];

    private const JENIS = ['bupati', 'jpt', 'camat', 'administrator', 'pengawas'];

    protected function setUp(): void
    {
        parent::setUp();
        helper('ikp');
    }

    /** Semua tautan satu peran untuk semua item & jenis PK, izin penuh. */
    private function semuaTautan(string $peran, ?callable $boleh = null): array
    {
        $boleh ??= static fn (string $izin) => true;
        $hasil = [];
        foreach (self::ITEM as $item) {
            foreach (self::JENIS as $jenis) {
                $t = RuangOpdTautan::untuk($peran, $item, 20, 2026, ['periode' => '2025-2029', 'jenis' => $jenis, 'id' => 7, 'ikp_kab' => true], $boleh);
                if ($t !== null) {
                    $hasil[$item . ':' . $jenis] = $t['url'];
                }
            }
        }

        return $hasil;
    }

    public function testBupatiTidakPernahDiarahkanKeAreaAdmin(): void
    {
        $url = $this->semuaTautan('bupati');
        $this->assertNotEmpty($url);
        foreach ($url as $k => $u) {
            $this->assertDoesNotMatchRegularExpression('#^(adminkab|adminopd)/#', $u, $k);
        }
        $this->assertSame('bupati/renaksi_pk/es3?opd_id=20&tahun=2026', $url['renaksi:jpt']);
        $this->assertSame('bupati/ikp/opd/20?tahun=2026', $url['ikp:jpt']);
        $this->assertSame('bupati/pk/kecamatan?tahun=2026&opd_id=20', $url['pk_lihat:camat']);
        $this->assertArrayNotHasKey('pk_edit:jpt', $url, 'Bupati hanya baca');
        $this->assertArrayNotHasKey('pk_cetak:jpt', $url);
    }

    public function testPeranOpdHanyaAreaSendiriTanpaOpdId(): void
    {
        foreach (['admin_opd', 'admin_kecamatan'] as $peran) {
            foreach ($this->semuaTautan($peran) as $k => $u) {
                $this->assertDoesNotMatchRegularExpression('#^(adminkab|bupati)/#', $u, $peran . ' ' . $k);
                // Area /adminopd membaca OPD dari sesi: opd_id di alamat hanya menyesatkan.
                $this->assertStringNotContainsString('opd_id=', $u, $peran . ' ' . $k);
            }
        }
        $this->assertSame('adminopd/pk/kecamatan/cetak/7', RuangOpdTautan::untuk('admin_kecamatan', 'pk_cetak', 31, 2026, ['jenis' => 'camat', 'id' => 7], static fn () => true)['url']);
        $this->assertNull(RuangOpdTautan::untuk('admin_opd', 'pk_lihat', 20, 2026, ['jenis' => 'bupati', 'id' => 7], static fn () => true), 'PK Bupati bukan milik OPD');
    }

    public function testPeranKabupatenMembawaOpdId(): void
    {
        $url = $this->semuaTautan('admin_kab');
        $this->assertSame('adminkab/renaksi_pk/es3?opd_id=20&tahun=2026', $url['renaksi:jpt']);
        $this->assertSame('adminkab/cascading?mode=opd&view=pohon&periode=2025-2029&opd_id=20', $url['pohon:jpt']);
        $this->assertSame('adminkab/lakip?mode=opd&opd_id=20&tahun=2026', $url['lakip:jpt']);
        $this->assertSame('adminkab/pk/pengawas/cetak/7', $url['pk_cetak:pengawas']);
        $this->assertSame('adminkab/pk/bupati/edit/7', $url['pk_edit:bupati']);
        $this->assertArrayNotHasKey('pk_edit:jpt', $url, 'Admin Kabupaten tidak mengubah PK OPD dari sini');

        // Inspektorat: baca saja, tidak ada tombol ubah.
        $this->assertArrayNotHasKey('pk_edit:bupati', $this->semuaTautan('admin_inspektorat'));
    }

    public function testTanpaIzinJatuhKeHalamanPublikAtauTidakAda(): void
    {
        $tanpa = static fn (string $izin) => false;
        $rkt = RuangOpdTautan::untuk('admin_inspektorat', 'rkt', 20, 2026, [], $tanpa);
        $this->assertSame('rkt?opd_id=20&tahun=2026', $rkt['url']);
        $this->assertTrue($rkt['publik']);
        $this->assertNull(RuangOpdTautan::untuk('admin_inspektorat', 'pemilik', 20, 2026, [], $tanpa));
        $this->assertNull(RuangOpdTautan::untuk('admin_opd', 'renstra', 20, 2026, [], $tanpa));
    }

    public function testSuperAdminTidakDiberiTautanRencanaAksi(): void
    {
        // PkRenaksiController::ensureRole menolak super admin: tautan ke sana pasti berakhir galat.
        $this->assertNull(RuangOpdTautan::untuk('admin', 'renaksi', 20, 2026, [], static fn () => true));
        $this->assertNull(RuangOpdTautan::untuk('admin', 'monev', 20, 2026, [], static fn () => true));
        $this->assertSame('adminkab/ikp/opd/20?tahun=2026', RuangOpdTautan::untuk('admin', 'ikp', 20, 2026, ['ikp_kab' => true], static fn () => true)['url']);
    }

    public function testKelurahanDanUptTidakDiberiTautanRekapIkpKabupaten(): void
    {
        // AdminKab\IkpController::opdSah hanya menerima jenis opd/kecamatan: kelurahan/UPT = 404.
        foreach (['admin', 'admin_kab', 'admin_inspektorat', 'bupati'] as $peran) {
            $this->assertNull(RuangOpdTautan::untuk($peran, 'ikp', 42, 2026, ['ikp_kab' => false], static fn () => true), $peran);
            $this->assertNull(RuangOpdTautan::untuk($peran, 'ikp', 42, 2026, [], static fn () => true), $peran . ': bawaan = tidak');
            $this->assertNotNull(RuangOpdTautan::untuk($peran, 'ikp', 20, 2026, ['ikp_kab' => true], static fn () => true), $peran);
        }
    }

    public function testLabelJenjangKecamatanMengikutiPemilikKinerja(): void
    {
        $kec = RuangOpdService::labelJenjang(true);
        $this->assertSame('Eselon IV', $kec['es3'], 'Camat = Eselon III, jadi simpul es3 kecamatan = Eselon IV');
        $this->assertSame('Pelaksana / JF', $kec['es4']);
        $this->assertSame('Eselon III', RuangOpdService::labelJenjang(false)['es3']);
        $sel = RuangOpdService::selCascading(['es3' => ['simpul' => 2, 'berpemilik' => 2]], true, true);
        $this->assertStringContainsString('Eselon IV 2', $sel['j']);
        $this->assertStringNotContainsString('Eselon III', $sel['j']);
    }

    public function testPeranTakDikenalTidakMendapatTautan(): void
    {
        $this->assertSame([], $this->semuaTautan('pegawai'));
        $this->assertSame([], $this->semuaTautan(''));
    }

    public function testTriwulanDanBulanJatuhTempo(): void
    {
        $kini = new DateTimeImmutable('2026-09-26', new DateTimeZone('Asia/Jakarta'));
        $this->assertSame(2, RuangOpdService::triwulanWajib(2026, $kini), 'TW III baru berakhir 30 September');
        $this->assertSame(4, RuangOpdService::triwulanWajib(2025, $kini));
        $this->assertSame(0, RuangOpdService::triwulanWajib(2027, $kini));
        $this->assertSame(8, RuangOpdService::bulanLalu(2026, $kini));
        $this->assertSame(12, RuangOpdService::bulanLalu(2025, $kini));
        $this->assertSame(0, RuangOpdService::bulanLalu(2027, $kini));
    }

    public function testSkorMengabaikanSelAbu(): void
    {
        $sel = ['a' => ['s' => 'hijau'], 'b' => ['s' => 'kuning'], 'c' => ['s' => 'merah'], 'd' => ['s' => 'abu'], '_skor' => null];
        $this->assertSame(50, RuangOpdService::skor($sel));
        $this->assertNull(RuangOpdService::skor(['x' => ['s' => 'abu']]));
        // Kolom eKin tidak ikut skor dokumen SAKIP: unit yang hanya punya eKin hijau tidak menjadi 100%.
        $this->assertNull(RuangOpdService::skor(['renstra' => ['s' => 'abu'], 'ekin' => ['s' => 'hijau']]));
        $this->assertSame(0, RuangOpdService::skor(['renstra' => ['s' => 'merah'], 'ekin' => ['s' => 'hijau']]));
    }

    public function testLakipTahunBerjalanYangSedangDisusunTidakMenurunkanSkor(): void
    {
        $kini = (int) RuangOpdService::kini()->format('Y');
        $draf = RuangOpdService::selLakip([$kini => ['n' => 4, 'selesai' => 1]], $kini, true);
        $this->assertSame('abu', $draf['s'], 'belum jatuh tempo: tidak dihitung');
        $this->assertSame('hijau', RuangOpdService::selLakip([$kini => ['n' => 4, 'selesai' => 4]], $kini, true)['s']);
        $this->assertSame('abu', RuangOpdService::selLakip([], $kini, true)['s']);
        // Tahun lampau: draf tetap kuning, tidak ada tetap merah.
        $this->assertSame('kuning', RuangOpdService::selLakip([$kini - 2 => ['n' => 4, 'selesai' => 1]], $kini - 2, true)['s']);
        $this->assertSame('merah', RuangOpdService::selLakip([], $kini - 2, true)['s']);
    }

    public function testSelPerjanjianKinerja(): void
    {
        $this->assertSame('hijau', RuangOpdService::selPk(['jpt' => 1, 'administrator' => 3, 'pengawas' => 2], true, false)['s']);
        $this->assertSame('kuning', RuangOpdService::selPk(['camat' => 1], true, true)['s']);
        $this->assertSame('Camat ✓', RuangOpdService::selPk(['camat' => 1], true, true)['t']);
        $this->assertSame('merah', RuangOpdService::selPk(['administrator' => 2], true, false)['s'], 'tanpa PK puncak');
        $this->assertSame('merah', RuangOpdService::selPk([], true, false)['s']);
        $this->assertSame('abu', RuangOpdService::selPk([], false, false)['s'], 'kelurahan/UPT: tidak wajib');
    }

    public function testSelEkin(): void
    {
        $this->assertSame('abu', RuangOpdService::selEkin(null, 'eKin mati')['s']);
        $this->assertSame('eKin mati', RuangOpdService::selEkin(null, 'eKin mati')['j']);
        $this->assertSame('belum di eKin', RuangOpdService::selEkin(false)['k']);
        // Contoh 26 September: bulan berjalan (September) baru draf/diajukan, Agustus sudah dinilai semua.
        $r = ['pegawai' => ['total' => 20, 'ber_skp' => 19],
              'bulanan' => ['bulan' => 9, 'dinilai' => 0, 'diajukan' => 12, 'draf' => 7, 'belum' => 0],
              'predikat' => ['sangat_baik' => 4, 'baik' => 15], 'predikat_bulan' => 8,
              'pk_pegawai' => ['ditandatangani' => 10, 'lewat_aksara' => 3]];
        $this->assertSame(['bulan' => 8, 'dinilai' => 19], RuangOpdService::ekinDinilai($r));
        $sel = RuangOpdService::selEkin($r, '', 8);
        $this->assertSame('hijau', $sel['s']);
        $this->assertSame('95% SKP', $sel['t']);
        $this->assertSame('dinilai Agu 19/19 · PK 10 +3 PK AKSARA', $sel['k'], '"lewat AKSARA" tidak dijumlahkan ke ditandatangani');
        $this->assertStringContainsString('Agustus', $sel['j']);

        // Penilaian Agustus belum ada sama sekali (terakhir Juli) -> tidak hijau walau semua ber-SKP.
        $telat = ['predikat_bulan' => 7] + $r;
        $this->assertSame('kuning', RuangOpdService::selEkin($telat, '', 8)['s']);

        // Pegawai dimuat tetapi belum ada yang ber-SKP.
        $nol = RuangOpdService::selEkin(['pegawai' => ['total' => 3, 'ber_skp' => 0], 'pk_pegawai' => ['lewat_aksara' => 1]], '', 8);
        $this->assertSame('merah', $nol['s']);
        $this->assertSame('belum ada SKP +1 PK AKSARA', $nol['k']);

        // eKin lama tanpa predikat_bulan: jatuh ke bulanan.bulan/dinilai.
        $lama = $r;
        unset($lama['predikat_bulan']);
        $this->assertSame(['bulan' => 9, 'dinilai' => 0], RuangOpdService::ekinDinilai($lama));
    }

    public function testNamaRapiDanSebutan(): void
    {
        $this->assertSame('Kecamatan Gading Rejo', RuangOpdService::namaRapi('KECAMATAN GADING REJO'));
        $this->assertSame('Badan Kepegawaian dan Pengembangan', RuangOpdService::namaRapi('BADAN KEPEGAWAIAN DAN PENGEMBANGAN'));
        $this->assertSame('Dinas Pendidikan Dan Kebudayaan', RuangOpdService::namaRapi('Dinas Pendidikan Dan Kebudayaan'), 'nama campuran dibiarkan');
        $this->assertStringContainsString('Diskominfo', RuangOpdService::sebutan('Dinas Komunikasi Dan Informatika'));
        $this->assertSame('kelurahan', RuangOpdService::kelompok('kelurahan'));
        $this->assertSame('pd', RuangOpdService::kelompok('opd'));
        $this->assertSame('lainnya', RuangOpdService::kelompok('upt'));
    }

    // ================================================================ eKin

    private function contohCascading(): array
    {
        $iki = static fn ($t) => [['aspek' => 'kualitas', 'indikator' => 'Mutu', 'target' => 100, 'satuan' => '%', 'metode' => 'trend_flat'],
            ['aspek' => 'kuantitas', 'indikator' => 'Jumlah', 'target' => $t, 'satuan' => 'dokumen', 'metode' => 'sum']];

        return [
            'pegawai' => [
                ['id' => 1, 'nama' => 'Kepala Contoh', 'jabatan' => 'Kepala', 'jenis_jabatan' => 'jpt', 'fiktif' => false, 'ppk_id' => null],
                ['id' => 2, 'nama' => 'Kabid Contoh', 'jabatan' => 'Kabid', 'jenis_jabatan' => 'administrator', 'fiktif' => false, 'ppk_id' => 1],
                ['id' => 3, 'nama' => 'Staf Contoh', 'jabatan' => 'Pelaksana', 'jenis_jabatan' => 'pelaksana', 'fiktif' => true, 'ppk_id' => 2],
            ],
            'rhk' => [
                // Sengaja acak urutannya, plus satu yatim & satu lingkaran.
                ['id' => 30, 'pegawai_id' => 3, 'jenis' => 'utama', 'rumusan' => 'RHK staf', 'rhk_atasan_id' => 20, 'iki' => $iki(4), 'porsi' => ['status' => 'tanpa_bawahan']],
                ['id' => 10, 'pegawai_id' => 1, 'jenis' => 'utama', 'rumusan' => 'RHK kepala', 'rhk_atasan_id' => null, 'rhk_atasan_teks' => 'RHK Bupati', 'iki' => $iki(10), 'porsi' => ['status' => 'kurang']],
                ['id' => 20, 'pegawai_id' => 2, 'jenis' => 'utama', 'rumusan' => 'RHK kabid', 'rhk_atasan_id' => 10, 'iki' => $iki(8), 'porsi' => ['status' => 'lengkap']],
                ['id' => 40, 'pegawai_id' => 3, 'jenis' => 'tambahan', 'rumusan' => 'RHK yatim', 'rhk_atasan_id' => 999, 'iki' => [], 'porsi' => ['status' => 'tanpa_bawahan']],
                ['id' => 50, 'pegawai_id' => 2, 'jenis' => 'utama', 'rumusan' => 'Lingkaran A', 'rhk_atasan_id' => 60, 'iki' => [], 'porsi' => ['status' => 'kurang']],
                ['id' => 60, 'pegawai_id' => 3, 'jenis' => 'utama', 'rumusan' => 'Lingkaran B', 'rhk_atasan_id' => 50, 'iki' => [], 'porsi' => ['status' => 'tanpa_bawahan']],
            ],
        ];
    }

    public function testPohonCascadingPegawai(): void
    {
        $p = EkinClient::bangunPohon($this->contohCascading());

        $this->assertSame(6, $p['jumlah']['rhk']);
        $this->assertSame(2, $p['jumlah']['kurang']);
        $this->assertSame(10, $p['akar'][0]['id'], 'akar pertama = RHK Kepala (jenjang JPT)');
        $this->assertSame(20, $p['akar'][0]['anak'][0]['id']);
        $this->assertSame(30, $p['akar'][0]['anak'][0]['anak'][0]['id']);

        $akar = array_column($p['akar'], 'id');
        $this->assertContains(40, $akar, 'atasan tidak ada di data -> akar sendiri');
        // Lingkaran 50 <-> 60 diputus: tepat satu dari keduanya jadi akar, yang lain anaknya.
        $this->assertCount(1, array_intersect([50, 60], $akar));
        $this->assertSame(10, EkinClient::ikiKuantitas($p['akar'][0])['target'], 'target = IKI aspek kuantitas');
    }

    public function testKlienEkinGagalMenjadiNullDenganAlasan(): void
    {
        $tanpaKonfig = new EkinClient('', '', static fn () => [200, '{}'], false);
        $this->assertNull($tanpaKonfig->ringkasOpd(20, 2026));
        $this->assertSame('belum_dikonfigurasi', $tanpaKonfig->alasanTerakhir());

        $kasus = [
            'ditolak'          => static fn () => [401, '{"galat":"token"}'],
            'belum_tersedia'   => static fn () => [404, 'Not Found'],
            'galat_server'     => static fn () => [500, 'oops'],
            'format'           => static fn () => [200, '<html>bukan json</html>'],
            'tidak_terjangkau' => static function () { throw new RuntimeException('putus'); },
        ];
        foreach ($kasus as $alasan => $pengambil) {
            $k = new EkinClient('http://127.0.0.1:1/', 'rahasia-uji', $pengambil, false);
            $this->assertNull($k->ringkasSemua(2026), $alasan);
            $this->assertSame($alasan, $k->alasanTerakhir(), $alasan);
            $this->assertStringNotContainsString('rahasia-uji', $k->pesanTerakhir());
        }

        // JSON sah tetapi tidak sesuai kontrak.
        $k = new EkinClient('http://x/', 't', static fn () => [200, '{"opd_id":20}'], false);
        $this->assertNull($k->ringkasOpd(20, 2026));
        $this->assertSame('format', $k->alasanTerakhir());
    }

    public function testKlienEkinMengirimTokenLewatHeaderSaja(): void
    {
        $dilihat = [];
        $k = new EkinClient('http://127.0.0.1:8097/', 'token-uji', static function (string $url, array $header) use (&$dilihat) {
            $dilihat = [$url, $header];

            return [200, json_encode(['tahun' => 2026, 'diperbarui' => '2026-09-26T10:00:00+07:00', 'opd' => ['20' => []]])];
        }, false);

        $this->assertIsArray($k->ringkasSemua(2026));
        $this->assertNull($k->alasanTerakhir());
        $this->assertSame('http://127.0.0.1:8097/api/aksara/ringkas?tahun=2026', $dilihat[0]);
        $this->assertStringNotContainsString('token-uji', $dilihat[0]);
        $this->assertSame('Bearer token-uji', $dilihat[1]['Authorization']);
    }
}
