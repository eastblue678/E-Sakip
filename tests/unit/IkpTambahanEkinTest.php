<?php

use App\Services\EkinClient;
use App\Services\IkpTurunService as T;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * JALUR DARURAT IKP → penugasan tambahan di eKin (eKin README §25, 12-10-2026) — sisi AKSARA+ (baca saja):
 * indeks per IKP, "tertangani lewat penugasan tambahan", saran realisasi "Dari eKin" yang ikut menjumlah (hitungan /
 * posisi terbagi) atau memilih nilai terbaru (posisi/rilis tanpa pemikul pohon), klien API, dan tautan publik eKin.
 *
 * Data uji tanpa nama/NIP orang asli.
 *
 * @internal
 */
final class IkpTambahanEkinTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ikp');
    }

    private static function baris(int $ikp, int $pegawai, string $status, array $bulan = [], array $pada = []): array
    {
        return ['ikp_id' => $ikp, 'pegawai_id' => $pegawai, 'penugasan_id' => $pegawai, 'status' => $status, 'target' => 6.0, 'satuan' => 'Media',
            'pola' => 'hitungan', 'bulan_mulai' => 10, 'bulan_selesai' => 12, 'alasan' => 'Tidak ada simpul pohon kinerja',
            'realisasi_bulan' => $bulan + array_fill_keys(array_map('strval', range(1, 12)), null), 'realisasi_total' => null, 'realisasi_pada' => $pada, 'ikp_opd_id' => 20];
    }

    public function testIndeksDanTertangani(): void
    {
        $i = T::indeksTambahan(['baris' => [self::baris(381, 2, 'ditolak'), self::baris(381, 1, 'menunggu'), self::baris(380, 1, 'dibatalkan'), 'rusak']]);
        $this->assertSame([381, 380], array_keys($i));
        $this->assertSame(['menunggu', 'ditolak'], array_column($i[381], 'status'), 'urut: diterima, menunggu, ditolak, dibatalkan');
        $this->assertTrue(T::tertanganiTambahan($i[381]));
        $this->assertFalse(T::tertanganiTambahan($i[380]), 'hanya dibatalkan/ditolak → belum tertangani');
        $this->assertSame([], T::indeksTambahan(null));
        $this->assertSame(['menunggu', 'diterima', 'ditolak', 'dibatalkan'], array_keys(T::STATUS_TAMBAHAN));
    }

    public function testSaranHitunganMenjumlahPohonDanJalurDarurat(): void
    {
        $pola  = ['pola' => 'hitungan', 'bulan_ukur' => range(1, 12)];
        $pohon = [10 => ['nilai' => 5.0, 'cara' => 'jumlah_porsi', 'baris' => [7], 'pegawai' => [11], 'lengkap' => true, 'level' => null]];
        $rows  = [self::baris(381, 1, 'diterima', ['10' => 2.0, '11' => 3.0]), self::baris(381, 2, 'diterima', ['10' => 1.0]), self::baris(381, 3, 'menunggu', ['10' => 9.0])];
        $g = T::gabungSaranTambahan($pola, $pohon, $rows);
        $this->assertSame([8.0, 'jumlah_porsi', [11, 1, 2], 2], [$g[10]['nilai'], $g[10]['cara'], $g[10]['pegawai'], $g[10]['tambahan']], 'Okt: 5 (pohon) + 2 + 1; menunggu tidak dihitung');
        $this->assertSame([3.0, 1], [$g[11]['nilai'], $g[11]['tambahan']], 'Nov: hanya jalur darurat');
        $this->assertArrayNotHasKey(12, $g);
        $this->assertSame($pohon, T::gabungSaranTambahan($pola, $pohon, [self::baris(381, 1, 'menunggu', ['10' => 2.0])]), 'tanpa yang diterima: saran pohon apa adanya');
    }

    public function testSaranPosisiDanRilisMemilihTerbaruBilaPohonKosong(): void
    {
        $pola = ['pola' => 'rilis', 'bulan_ukur' => [12]];
        $rows = [self::baris(380, 1, 'diterima', ['12' => 97.0], ['12' => '2026-12-10T10:00:00+07:00']),
            self::baris(380, 2, 'diterima', ['12' => 98.5], ['12' => '2026-12-20T10:00:00+07:00'])];
        $g = T::gabungSaranTambahan($pola, [], $rows);
        $this->assertSame([98.5, 'pemikul_angka', 'tambahan', [2]], [$g[12]['nilai'], $g[12]['cara'], $g[12]['level'], $g[12]['pegawai']]);

        $pohon = [12 => ['nilai' => 99.0, 'cara' => 'pemikul_angka', 'baris' => [5], 'pegawai' => [9], 'lengkap' => true, 'level' => 'es3']];
        $this->assertSame($pohon, T::gabungSaranTambahan($pola, $pohon, $rows), 'pemikul angka pohon kinerja didahulukan');

        $terbagi = ['pola' => 'posisi', 'posisi_terbagi' => true, 'bulan_ukur' => range(1, 12)];
        $g = T::gabungSaranTambahan($terbagi, [10 => ['nilai' => 48000.0, 'cara' => 'jumlah_posisi', 'baris' => [1], 'pegawai' => [4], 'lengkap' => true, 'level' => null]],
            [self::baris(476, 1, 'diterima', ['10' => 1934.0])]);
        $this->assertSame([49934.0, 'jumlah_posisi'], [$g[10]['nilai'], $g[10]['cara']], 'posisi terbagi: Σ bagian pada bulan yang sama');
    }

    public function testKlienApiDanTautanPublik(): void
    {
        $lihat = '';
        $k = new EkinClient('http://127.0.0.1:8197/', 'token-uji', static function (string $url) use (&$lihat) {
            $lihat = $url;

            return [200, json_encode(['opd_id' => 20, 'tahun' => 2026, 'baris' => [self::baris(381, 1, 'menunggu')]])];
        }, false);
        $this->assertCount(1, $k->ikpTambahan(20, 2026)['baris']);
        $this->assertSame('http://127.0.0.1:8197/api/aksara/opd/20/ikp-tambahan?tahun=2026', $lihat);
        $k->ikpTambahanSemua(2026);
        $this->assertSame('http://127.0.0.1:8197/api/aksara/ikp-tambahan?tahun=2026', $lihat);

        $lama = new EkinClient('http://x/', 't', static fn () => [404, '{"status":"error"}'], false);
        $this->assertNull($lama->ikpTambahan(20, 2026));
        $this->assertSame('belum_tersedia', $lama->alasanTerakhir());
        $aneh = new EkinClient('http://x/', 't', static fn () => [200, '{"opd_id":20}'], false);
        $this->assertNull($aneh->ikpTambahan(20, 2026));
        $this->assertSame('format', $aneh->alasanTerakhir());

        $this->assertStringEndsWith('/', EkinClient::urlPublik());
        $this->assertStringStartsWith('http', EkinClient::urlPublik());
        $this->assertStringEndsWith('penugasan?ikp=381&opd=20', EkinClient::tautanPenugasanIkp(381, 20));
        $this->assertSame('IKP · penugasan tambahan', EkinClient::peranIkp('tambahan')['label'], 'chip Rencana Aksi Pegawai');
    }
}
