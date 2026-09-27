<?php

use App\Services\PohonPemilikService as P;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * AKSARA+ — aturan murni pemilik simpul pohon kinerja (PohonPemilikService).
 *
 * @internal
 */
final class PohonPemilikTest extends CIUnitTestCase
{
    public function testTigaPeranDanPeranUtama(): void
    {
        $this->assertSame(['penanggung_jawab', 'anggota', 'penugasan_tambahan'], array_keys(P::PERAN));
        $this->assertTrue(P::peranUtama('penanggung_jawab'));
        $this->assertTrue(P::peranUtama('anggota'));
        $this->assertFalse(P::peranUtama('penugasan_tambahan'), 'penugasan tambahan = hasil kerja tambahan, bukan utama');
        $this->assertTrue(P::peranSah('penugasan_tambahan'));
        $this->assertFalse(P::peranSah('ketua'));
        $this->assertLessThanOrEqual(20, max(array_map('strlen', array_keys(P::PERAN))), 'kolom cascading_pemilik.peran VARCHAR(20)');
    }

    public function testTahunDalamPeriode(): void
    {
        $this->assertSame(2027, P::tahunDalamPeriode(2025, 2029, 2027, 2026), 'tahun diminta di dalam periode');
        $this->assertSame(2026, P::tahunDalamPeriode(2025, 2029, 0, 2026), 'tanpa permintaan → tahun berjalan');
        $this->assertSame(2026, P::tahunDalamPeriode(2025, 2029, 2031, 2026), 'di luar periode → tahun berjalan');
        $this->assertSame(2029, P::tahunDalamPeriode(2025, 2029, 0, 2032), 'periode lampau → tahun terakhir');
        $this->assertSame(2030, P::tahunDalamPeriode(2030, 2034, 0, 2026), 'periode mendatang → tahun pertama');
    }

    public function testInisialDanNamaPendek(): void
    {
        $this->assertSame('MA', P::inisial('MOUDY ARY NAZOLLA, S.STP.MH'));
        $this->assertSame('I', P::inisial('Plt. ICHSANUDDIN, S.IP'));
        $this->assertSame('SH', P::inisial('Dr. SITI HAJAR, M.Kes'));
        $this->assertSame('?', P::inisial(''));
        $this->assertSame('ANDY DENI SYAMSURYA', P::namaPendek('ANDY DENI SYAMSURYA, S.KOM'));
        $this->assertSame('RANGGA KASMITA', P::namaPendek('RANGGA KASMITA'));
    }

    public function testIdSimpulPohonMengikutiKunciCascOpdTree(): void
    {
        $tree = ['_1' => ['sasarans' => ['_2' => ['tujuan_renstras' => ['_3' => ['es2s' => ['_9' => ['es3s' => [
            11 => ['es4s' => [21 => ['pelaksanas' => [31 => [], 32 => []]], 22 => ['pelaksanas' => []]]],
            12 => ['es4s' => []],
        ]]]]]]]]];

        $this->assertSame(['es3' => [11, 12], 'es4' => [21, 22], 'pelaksana' => [31, 32]], P::idSimpulPohon($tree));
        $this->assertSame(['es3' => [], 'es4' => [], 'pelaksana' => []], P::idSimpulPohon([]));
    }
}
