<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * AKSARA+ — kertas PK Pegawai (ruang_opd/_pk_pegawai_kertas) harus sama susunannya dengan dokumen eKin.
 *
 * @internal
 */
final class PkPegawaiKertasTest extends CIUnitTestCase
{
    private static function baris(int $no, int $rhk, string $indikator, string $jenis = 'utama', string $metode = 'sum'): array
    {
        return [
            'no' => $no, 'jenis' => $jenis, 'rhk_id' => $rhk, 'sasaran' => 'Sasaran ' . $rhk, 'indikator' => $indikator,
            'satuan' => 'Dokumen', 'target_bulan' => ['1' => 1, '2' => null, '3' => 2.5] + array_fill_keys(array_map('strval', range(4, 12)), 1),
            'jumlah' => 12.5, 'metode' => $metode, 'sumber_tipe' => 'cascading',
        ];
    }

    private static function dok(array $ubah = []): array
    {
        return $ubah + [
            'status'              => 'ditandatangani',
            'diajukan_pada'       => '2026-01-23 13:46:00',
            'ditandatangani_pada' => '2026-01-23 15:35:00',
            'pegawai'             => ['id' => 7, 'nama' => 'Pihak <Satu>', 'jabatan' => 'Analis', 'nip' => '1990', 'pangkat_gol' => 'Penata (III/c)', 'unit_kerja' => 'Bidang A', 'opd_nama' => 'Dinas Uji', 'fiktif' => false],
            'pihak_kedua'         => ['id' => 8, 'nama' => 'Pihak Dua', 'jabatan' => 'Kepala Bidang A', 'nip' => '1980', 'pangkat_gol' => 'Pembina (IV/a)', 'fiktif' => false, 'kepala_daerah' => false],
            'baris'               => [self::baris(1, 10, 'Indikator 1a'), self::baris(1, 10, 'Indikator 1b', 'utama', 'akhir'), self::baris(2, 11, 'Indikator 2')],
            'teks'                => ['pembuka' => 'Kalimat pembuka dari eKin.'],
        ];
    }

    private static function render(array $dok, bool $cetak = false): string
    {
        return view('ruang_opd/_pk_pegawai_kertas', ['dok' => $dok, 'cetak' => $cetak, 'tahun' => 2026, 'opdNama' => 'Cadangan'], ['saveData' => false]);
    }

    public function testDuaLembarKopDanSasaranSatuRhkDigabung(): void
    {
        $html = self::render(self::dok());

        $this->assertSame(2, substr_count($html, 'class="pkd-lembar '), 'lembar pernyataan + lampiran');
        $this->assertStringContainsString('PEMERINTAH KABUPATEN PRINGSEWU', $html);
        $this->assertStringContainsString('DINAS UJI', $html, 'kop memakai OPD pihak pertama');
        $this->assertStringContainsString('rowspan="2">Sasaran 10', str_replace(["\n", '  '], '', $html), 'dua indikator satu RHK = satu sel sasaran');
        $this->assertSame(1, substr_count($html, 'Sasaran 10'));
        $this->assertStringContainsString('A. UTAMA', $html);
        $this->assertStringContainsString('B. TAMBAHAN', $html);
        $this->assertStringContainsString('Tidak ada', $html, 'bagian tambahan kosong tetap tampil seperti eKin');
        $this->assertStringContainsString('Kalimat pembuka dari eKin.', $html, 'kalimat baku datang dari eKin');
        $this->assertStringContainsString('selaku atasan pihak pertama', $html, 'kalimat yang tidak dikirim eKin memakai cadangan yang sama');
        $this->assertStringContainsString('12,5', $html);
        $this->assertStringContainsString('2,5', $html);
        $this->assertStringContainsString('title="Nilai akhir, bukan jumlah">*', $html);
        $this->assertStringContainsString('Disetujui elektronik', $html);
        $this->assertStringContainsString('23 Jan 2026 15.35', $html);
        $this->assertStringContainsString('Pringsewu, 23 Januari 2026', $html);
        $this->assertStringNotContainsString('pkd-watermark', $html, 'dokumen ditandatangani tanpa tanda air');
        $this->assertStringContainsString('Pihak &lt;Satu&gt;', $html, 'nama di-escape');
        $this->assertStringNotContainsString('Pihak <Satu>', $html);
        $this->assertStringContainsString('Pohon Kinerja', $html, 'lencana asal RHK di layar');

        $dok = self::dok();
        $dok['baris'][2]['sumber_tipe'] = 'manual';
        $this->assertStringContainsString('Manual', self::render($dok), 'RHK manual juga berlencana, seperti eKin');
    }

    public function testCetakTanpaLencanaDanStatusBelumFinalBertandaAir(): void
    {
        $this->assertStringNotContainsString('Pohon Kinerja', self::render(self::dok(), true), 'lencana hanya di layar');

        $diajukan = self::render(self::dok(['status' => 'diajukan', 'ditandatangani_pada' => null]));
        $this->assertStringContainsString('MENUNGGU TANDA TANGAN', $diajukan);
        $this->assertStringContainsString('Diajukan elektronik', $diajukan);
        $this->assertStringNotContainsString('Disetujui elektronik', $diajukan);

        $draf = self::render(self::dok(['status' => 'draf', 'diajukan_pada' => null, 'ditandatangani_pada' => null]));
        $this->assertStringContainsString('>DRAF<', $draf);
        $this->assertStringNotContainsString('pkd-cap"', $draf, 'draf tanpa cap elektronik');
        $this->assertStringContainsString('>DIKEMBALIKAN<', self::render(self::dok(['status' => 'dikembalikan'])));
    }

    public function testKepalaDaerahTanpaNipDanPegawaiFiktifBertanda(): void
    {
        $dok = self::dok();
        $dok['pihak_kedua']['kepala_daerah'] = true;
        $dok['pegawai']['fiktif'] = true;
        $html = self::render($dok);

        $this->assertStringNotContainsString('NIP. 1980', $html, 'Kepala Daerah sebagai pihak kedua: tanpa NIP');
        $this->assertStringNotContainsString('Pembina (IV/a)', $html);
        $this->assertStringContainsString('NIP. 1990', $html);
        $this->assertStringContainsString('pkd-fiktif', $html);
    }
}
