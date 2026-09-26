<?php

namespace App\Services;

/**
 * AKSARA+ — PETA TAUTAN "Ruang OPD": halaman lama mana yang boleh dibuka PERAN ini
 * untuk dokumen X milik OPD Y, dengan OPD itu sudah terpilih.
 *
 * MENGAPA peta tersendiri (bukan if-else di view): setiap halaman lama punya aturan
 * lingkupnya sendiri dan aturan itu berbeda per peran —
 *
 *   * grup /adminopd hanya menerima admin_opd, admin_kecamatan, admin, dan hampir
 *     semua controllernya membaca OPD dari SESI (tidak menerima ?opd_id=);
 *   * grup /adminkab menerima admin_kab, admin_inspektorat, admin, lalu
 *     ModulePermissionFilter menuntut izin modul (mis. adminkab/renaksi_pk -> pk_bupati.view);
 *   * grup /bupati hanya baca; PkRenaksiController menolak super admin sama sekali;
 *   * halaman publik (/renstra, /cascading_opd, ...) menerima ?opd_id= siapa pun.
 *
 * Tautan yang salah peran berakhir di /unauthorized atau /login — persis keluhan yang
 * ingin dihapus Ruang OPD. Karena itu satu fungsi murni memutuskan semuanya, dan
 * tests/unit/RuangOpdTest.php menjaga aturan pokoknya (Bupati tidak pernah diarahkan
 * ke /adminkab atau /adminopd, peran OPD tidak pernah ke /adminkab, dst.). Uji Playwright
 * uji/ms/cek_ruang_opd.mjs membuka SETIAP tautan hub untuk lima peran.
 *
 * null = tidak ada halaman yang cocok untuk peran ini -> hub menampilkan ringkasan
 * baca-saja di tempat (bukan tautan yang pasti ditolak).
 */
final class RuangOpdTautan
{
    /** Peran yang boleh membuka Ruang OPD SEMUA perangkat daerah (baca). */
    public const PERAN_LINTAS = ['admin', 'admin_kab', 'admin_inspektorat', 'bupati'];

    /** Peran yang hanya boleh membuka Ruang OPD miliknya sendiri. */
    public const PERAN_OPD = ['admin_opd', 'admin_kecamatan'];

    /** Segmen URL PK per jenis data (PK Camat disimpan 'camat', rutenya 'kecamatan'). */
    public const SEGMEN_PK = [
        'bupati'        => 'bupati',
        'jpt'           => 'jpt',
        'camat'         => 'kecamatan',
        'administrator' => 'administrator',
        'pengawas'      => 'pengawas',
    ];

    public static function kelompokPeran(string $peran): ?string
    {
        return match (true) {
            in_array($peran, self::PERAN_OPD, true)                  => 'opd',
            in_array($peran, ['admin_kab', 'admin_inspektorat'], true) => 'kab',
            $peran === 'admin'                                       => 'admin',
            $peran === 'bupati'                                      => 'bupati',
            default                                                  => null,
        };
    }

    /**
     * @param string                $item  dashboard|renstra|rkt|iku|cascading|pohon|pemilik|pk|pk_lihat|pk_cetak|
     *                                     pk_edit|renaksi|monev|ikp|ikp_realisasi|lakip|evaluasi
     * @param array<string,mixed>   $ctx   periode ('2025-2029'), jenis & id (PK),
     *                                     ikp_kab (bool: OPD ini tercakup rekap IKP kabupaten —
     *                                     RuangOpdService::ikpKabBerlaku; tanpa kunci ini = tidak)
     * @param callable(string):bool $boleh pemeriksa izin (user_can)
     *
     * @return array{url:string, publik:bool, baru:bool}|null url relatif (tanpa base_url)
     */
    public static function untuk(string $peran, string $item, int $opdId, int $tahun, array $ctx, callable $boleh): ?array
    {
        $kel = self::kelompokPeran($peran);
        if ($kel === null) {
            return null;
        }

        $periode = (string) ($ctx['periode'] ?? '');
        // Rekap IKP kabupaten (adminkab/ikp/opd/{id}, bupati/ikp/opd/{id}) hanya menerima
        // OPD jenis opd/kecamatan (AdminKab\IkpController::opdSah); kelurahan & UPT = 404.
        // Bawaan TIDAK: pemanggil yang lupa menyebutnya kehilangan tombol, bukan memberi tautan putus.
        $ikpKab  = ! empty($ctx['ikp_kab']);
        $jenisPk = (string) ($ctx['jenis'] ?? '');
        $idPk    = (int) ($ctx['id'] ?? 0);
        $segPk   = self::SEGMEN_PK[$jenisPk] ?? null;

        $q = static fn (array $p): string => $p === [] ? '' : '?' . http_build_query($p);
        $in = static fn (string $url): array => ['url' => $url, 'publik' => false, 'baru' => false];
        $pub = static fn (string $url): array => ['url' => $url, 'publik' => true, 'baru' => false];
        $pdf = static fn (string $url): array => ['url' => $url, 'publik' => false, 'baru' => true];

        // Halaman publik (tanpa sesi) — cadangan untuk dokumen perencanaan yang
        // tidak punya layar lintas OPD di area admin peran ini.
        $publik = [
            'renstra'   => 'renstra' . $q(['opd_id' => $opdId]),
            'rkt'       => 'rkt' . $q(['opd_id' => $opdId, 'tahun' => $tahun]),
            'iku'       => 'iku_opd' . $q(['opd_id' => $opdId]),
            'cascading' => $periode !== '' ? 'cascading_opd' . $q(['periode' => $periode, 'opd_id' => $opdId]) : null,
            'pohon'     => $periode !== '' ? 'pohon_kinerja_opd' . $q(['periode' => $periode, 'opd_id' => $opdId]) : null,
            'lakip'     => 'lakip_opd' . $q(['opd_id' => $opdId, 'tahun' => $tahun]),
        ];

        if ($kel === 'opd') {
            // Semua halaman /adminopd membaca OPD dari sesi: tautan tanpa opd_id.
            $viewCasc = static fn (string $v): string => 'adminopd/cascading' . $q(array_filter(['view' => $v, 'periode' => $periode]));

            return match ($item) {
                'dashboard'     => $boleh('dashboard.view') ? $in('adminopd/dashboard' . $q(['tahun' => $tahun])) : null,
                'renstra'       => $boleh('renstra.view') ? $in('adminopd/renstra') : null,
                'rkt'           => $boleh('rkt_opd.view') ? $in('adminopd/rkt' . $q(['tahun' => $tahun])) : null,
                'iku'           => $boleh('iku_opd.view') ? $in('adminopd/iku') : null,
                'cascading'     => $boleh('cascading_opd.view') ? $in($viewCasc('tabel')) : null,
                'pohon'         => $boleh('cascading_opd.view') ? $in($viewCasc('pohon')) : null,
                'pemilik'       => $boleh('pemilik_kinerja.view') ? $in('adminopd/pemilik-kinerja' . $q(['tahun' => $tahun])) : null,
                'pk'            => $boleh('pk_opd.view') ? $in('perjanjian-kinerja' . $q(['tahun' => $tahun])) : null,
                'pk_lihat'      => $segPk && $segPk !== 'bupati' && $boleh('pk_opd.view')
                    ? $in('adminopd/pk/' . $segPk . $q(['tahun' => $tahun, 'pk_id' => $idPk])) : null,
                'pk_cetak'      => $segPk && $segPk !== 'bupati' && $idPk > 0 && $boleh('pk_opd.view')
                    ? $pdf('adminopd/pk/' . $segPk . '/cetak/' . $idPk) : null,
                'pk_edit'       => $segPk && $segPk !== 'bupati' && $idPk > 0 && $boleh('pk_opd.update')
                    ? $in('adminopd/pk/' . $segPk . '/edit/' . $idPk) : null,
                'renaksi'       => $boleh('pk_opd.view') ? $in('adminopd/target_renaksi' . $q(['tahun' => $tahun])) : null,
                'monev'         => $boleh('pk_opd.view') ? $in('adminopd/monev' . $q(['tahun' => $tahun])) : null,
                'ikp'           => $boleh('ikp_opd.view') ? $in('adminopd/ikp/rekap' . $q(['tahun' => $tahun])) : null,
                'ikp_realisasi' => $boleh('ikp_opd.view') ? $in('adminopd/ikp/realisasi' . $q(['tahun' => $tahun])) : null,
                'lakip'         => $boleh('lakip_opd.view') ? $in('adminopd/lakip' . $q(['tahun' => $tahun])) : null,
                'evaluasi'      => $in('adminopd/evaluasi_inspektorat'),
                default         => null,
            };
        }

        if ($kel === 'bupati') {
            // Bupati: HANYA area /bupati (baca) atau halaman publik. Tidak pernah /adminkab.
            $segBupati = ['bupati' => 'bupati', 'jpt' => 'jpt', 'camat' => 'kecamatan',
                          'administrator' => 'administrator', 'pengawas' => 'pengawas'][$jenisPk] ?? null;

            return match ($item) {
                'dashboard' => $in('bupati/dashboard' . $q(['opd_id' => $opdId, 'tahun' => $tahun])),
                'renstra', 'rkt', 'iku' => $pub($publik[$item]),
                'cascading', 'pohon' => $publik[$item] !== null ? $pub($publik[$item]) : null,
                'pk'        => $in('perjanjian-kinerja' . $q(['opd_id' => $opdId, 'tahun' => $tahun])),
                'pk_lihat'  => $segBupati ? $in('bupati/pk/' . $segBupati . $q(['tahun' => $tahun] + ($segBupati === 'bupati' ? [] : ['opd_id' => $opdId]))) : null,
                'renaksi'   => $in('bupati/renaksi_pk/es3' . $q(['opd_id' => $opdId, 'tahun' => $tahun])),
                'monev'     => $in('bupati/monev_pk/es3' . $q(['opd_id' => $opdId, 'tahun' => $tahun])),
                'ikp'       => $ikpKab ? $in('bupati/ikp/opd/' . $opdId . $q(['tahun' => $tahun])) : null,
                'lakip'     => $in('bupati/lakip' . $q(['mode' => 'opd', 'opd_id' => $opdId, 'tahun' => $tahun])),
                default     => null,   // pemilik, pk_cetak, pk_edit, evaluasi, ikp_realisasi: tidak ada layar Bupati
            };
        }

        // Kabupaten (admin_kab, admin_inspektorat) & Super Admin: area /adminkab dengan ?opd_id=.
        $kab   = $kel === 'kab';
        $casc  = static fn (string $v): string => 'adminkab/cascading' . $q(array_filter([
            'mode' => 'opd', 'view' => $v, 'periode' => $periode, 'opd_id' => $opdId,
        ]));

        return match ($item) {
            'dashboard' => $boleh('dashboard.view') ? $in('adminkab/dashboard' . $q(['opd_id' => $opdId, 'tahun' => $tahun])) : null,
            'renstra'   => $pub($publik['renstra']),
            'rkt'       => $boleh('rkpd.view')
                ? $in('adminkab/rkpd' . $q(['opd_id' => $opdId, 'tahun' => $tahun])) : $pub($publik['rkt']),
            'iku'       => $boleh('iku_kab.view')
                ? $in('adminkab/iku' . $q(['mode' => 'opd', 'opd_id' => $opdId])) : $pub($publik['iku']),
            'cascading', 'pohon' => $periode === '' ? null : ($boleh('cascading_kab.view')
                ? $in($casc($item === 'pohon' ? 'pohon' : 'tabel')) : $pub((string) $publik[$item])),
            'pemilik'   => $boleh('pemilik_kinerja.view')
                ? $in('adminkab/pemilik-kinerja' . $q(['opd_id' => $opdId, 'tahun' => $tahun])) : null,
            'pk'        => $in('perjanjian-kinerja' . $q(['opd_id' => $opdId, 'tahun' => $tahun])),
            // Cetak lewat grup adminkab (modperm: pk_bupati.view). Layar "lihat" PK OPD
            // di area adminkab tidak ada (PkController membaca OPD dari sesi) — daftar
            // Perjanjian Kinerja menampilkan isinya di tempat.
            'pk_cetak'  => $segPk && $idPk > 0 && $boleh('pk_bupati.view') ? $pdf('adminkab/pk/' . $segPk . '/cetak/' . $idPk) : null,
            'pk_lihat'  => $segPk === 'bupati' && $kab && $boleh('pk_bupati.view')
                ? $in('adminkab/pk/bupati' . $q(['tahun' => $tahun, 'pk_id' => $idPk])) : null,
            'pk_edit'   => $segPk === 'bupati' && $idPk > 0 && $peran === 'admin_kab' && $boleh('pk_bupati.update')
                ? $in('adminkab/pk/bupati/edit/' . $idPk) : null,
            // PkRenaksiController::ensureRole menolak super admin -> tidak ada tautan untuknya.
            'renaksi'   => $kab && $boleh('pk_bupati.view')
                ? $in('adminkab/renaksi_pk/es3' . $q(['opd_id' => $opdId, 'tahun' => $tahun])) : null,
            'monev'     => $kab && $boleh('pk_bupati.view')
                ? $in('adminkab/monev_pk/es3' . $q(['opd_id' => $opdId, 'tahun' => $tahun])) : null,
            'ikp'       => $ikpKab && $boleh('ikp_kab.view') ? $in('adminkab/ikp/opd/' . $opdId . $q(['tahun' => $tahun])) : null,
            'lakip'     => $boleh('lakip_kab.view')
                ? $in('adminkab/lakip' . $q(['mode' => 'opd', 'opd_id' => $opdId, 'tahun' => $tahun])) : $pub($publik['lakip']),
            'evaluasi'  => $in('adminkab/evaluasi_inspektorat'),
            default     => null,
        };
    }
}
