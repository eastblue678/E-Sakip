<?php

use App\Controllers\RuangOpdController;
use App\Services\EkinClient;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AKSARA+ — Rencana Aksi Pegawai (Ruang OPD): susunan per atasan & bulan bawaan.
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
}
