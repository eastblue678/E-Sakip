<?php

namespace App\Controllers\Concerns;

use App\Services\PohonPemilikService;

/**
 * AKSARA+ — data "pemilik & pelaksana" untuk bagan Pohon Kinerja OPD (adminopd & adminkab mode OPD).
 *
 * Partial adminOpd/cascading/_pohon_opd_tree hanya menggambar pemilik bila variabel `pemilikPohon`
 * ada; halaman publik dan cetak tidak pernah mengirimnya, jadi tampilannya tidak berubah.
 */
trait PohonPemilikTrait
{
    /**
     * @param array  $tree  hasil cascOpdTree (kunci simpul = id cascading_sasaran_opd)
     * @param string $area  'adminopd' | 'adminkab' — menentukan tautan "Kelola di Pemilik Kinerja"
     */
    protected function dataPemilikPohon(array $tree, int $opdId, ?int $awal, ?int $akhir, string $area): ?array
    {
        if ($tree === [] || $opdId <= 0 || ! $awal || ! $akhir || ! function_exists('user_can') || ! user_can('pemilik_kinerja.view')) {
            return null;
        }

        try {
            $tahun = PohonPemilikService::tahunDalamPeriode($awal, $akhir, (int) $this->request->getGet('tahun'));
            $data  = (new PohonPemilikService())->untukPohon($opdId, $tahun, $tree);
        } catch (\Throwable $e) {
            // Tabel cascading_pemilik belum ada (basis data tanpa pembaruan AKSARA+): pohon tetap tampil tanpa pemilik.
            log_message('warning', 'Pemilik pohon kinerja tidak termuat: ' . $e->getMessage());

            return null;
        }

        $data['daftarTahun'] = range($awal, $akhir);
        $data['bolehUbah']   = user_can('pemilik_kinerja.update');
        $data['urlKelola']   = $area === 'adminkab'
            ? base_url('adminkab/pemilik-kinerja?' . http_build_query(['opd_id' => $opdId, 'tahun' => $data['tahun']]))
            : base_url('adminopd/pemilik-kinerja?' . http_build_query(['tahun' => $data['tahun']]));

        return $data;
    }
}
