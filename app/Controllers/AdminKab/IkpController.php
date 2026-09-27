<?php

namespace App\Controllers\AdminKab;

use App\Controllers\BaseController;
use App\Models\OpdModel;
use App\Services\IkpRekapService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

/**
 * Kinerja Prioritas (IKP) LINTAS OPD — area Kabupaten, HANYA BACA.
 *
 * Rute (blok AKSARA+ di ujung Config\Routes, grup adminkab
 * auth:admin_kab,admin,admin_inspektorat):
 *   GET adminkab/ikp                     index            rekap per OPD (tahun, s.d. bulan)
 *   GET adminkab/ikp/opd/(:num)          opd              rincian satu OPD (bulanan + triwulan)
 *   GET adminkab/ikp/program-unggulan    programUnggulan  9 Program Unggulan + ?pu=slug
 *   GET adminkab/ikp/cetak               cetak            PDF rekap (A4 mendatar)
 *
 * =====================================================================
 * LINGKUP OPD — MENGAPA BUKAN canAccessOpd()
 *
 * Kedua akun admin_kab membawa opd_id (produksi & demo), sehingga
 * BaseController::canAccessOpd() menolak mereka untuk OPD lain (kritik 0.7).
 * Halaman ini melingkup lewat PERAN (grup rute) dan memvalidasi opd_id
 * terhadap daftar OPD sah di bawah (bukan EXCLUDED_OPD_IDS, jenis opd /
 * kecamatan). opd_id di luar daftar = 404, tidak pernah "dicoba saja".
 *
 * Semua angka berasal dari App\Services\IkpRekapService + ikp_helper — sama
 * persis dengan yang dilihat operator OPD, dicetak di Lampiran PK, dan ditarik
 * eKin. Tidak ada rumus capaian baru di sini; controller hanya merangkum.
 *
 * Bupati\IkpMonitoringController MEWARISI kelas ini untuk memakai rangkuman
 * yang sama (lingkup, tahun/bulan, agregat per OPD), dengan tampilan & izinnya
 * sendiri.
 */
class IkpController extends BaseController
{
    protected $helpers = ['cascading_label', 'ikp', 'format', 'dashboard_status'];

    /**
     * OPD kembar tanpa PK (riset prioritas §8; kritik 0.10): akun, pohon & PK
     * ada di id pasangannya. Ditampilkan hanya bila ternyata punya IKP — agar
     * rekap tidak menampilkan "belum ada IKP" abadi untuk entri duplikat.
     */
    protected const OPD_KEMBAR = [13 => 211, 213 => 32];

    protected IkpRekapService $rekap;
    protected $db;

    public function __construct()
    {
        $this->rekap = new IkpRekapService();
        $this->db    = \Config\Database::connect();
    }

    /** Peta izin (pola CascadingIzinTrait) — semua halaman di sini bacaan. */
    protected function petaIzin(): array
    {
        return [
            'index'           => 'ikp_kab.view',
            'opd'             => 'ikp_kab.view',
            'programUnggulan' => 'ikp_kab.view',
            'cetak'           => 'ikp_kab.view',
        ];
    }

    public function _remap(string $method, ...$params)
    {
        $peta = $this->petaIzin();
        if (str_starts_with($method, '_') || ! isset($peta[$method]) || ! method_exists($this, $method)
            || ! (new \ReflectionMethod($this, $method))->isPublic()) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! user_can($peta[$method])) {
            return redirect()->to(base_url('unauthorized'))
                ->with('error', 'Anda tidak memiliki akses ke rekap Kinerja Prioritas (IKP).');
        }
        if (! $this->rekap->siap()) {
            return redirect()->back()
                ->with('error', 'Tabel Kinerja Prioritas (IKP) belum tersedia. Hubungi administrator untuk menjalankan pembaruan basis data.');
        }

        return $this->$method(...$params);
    }

    /* =====================================================================
     * HALAMAN
     * ===================================================================*/

    /** GET adminkab/ikp?tahun=&bulan=&jenis= */
    public function index()
    {
        [$tahun, $bulan] = $this->tahunBulan();
        $jenis   = $this->jenisDiminta();
        $opdList = $this->daftarOpd($jenis);
        $baris   = $this->rekapLintas($tahun, $bulan, $opdList);
        // IKP turun sampai pelaksana: per OPD berapa IKP sudah diturunkan / sampai pelaksana.
        $turun   = (new \App\Services\IkpTurunService($this->db))->rekapKabupaten(array_map(static fn ($o) => (int) $o['id'], $opdList), $tahun);

        return view('ikp/kab_index', [
            'turun'     => $turun,
            'title'     => 'Rekap Kinerja Prioritas (IKP) Lintas Perangkat Daerah',
            'tahun'     => $tahun,
            'bulan'     => $bulan,
            'jenis'     => $jenis,
            'tahunList' => $this->rekap->tahunPeriode(),
            'baris'     => $baris,
            'total'     => $this->totalKabupaten($baris),
        ]);
    }

    /** GET adminkab/ikp/opd/(:num)?tahun=&bulan= */
    public function opd($id = null)
    {
        $opd = $this->opdSah((int) $id);
        [$tahun, $bulan] = $this->tahunBulan();
        $data = $this->rekapLintas($tahun, $bulan, [$opd])[0];

        return view('ikp/kab_opd', [
            'title'     => 'IKP ' . $opd['nama_opd'],
            'tahun'     => $tahun,
            'bulan'     => $bulan,
            'tahunList' => $this->rekap->tahunPeriode(),
            'data'      => $data,
            'baseUrl'   => 'adminkab/ikp',
        ]);
    }

    /** GET adminkab/ikp/program-unggulan?tahun=&bulan=[&pu=slug|tanpa] */
    public function programUnggulan()
    {
        [$tahun, $bulan] = $this->tahunBulan();
        $puList = $this->db->table('ikp_program_unggulan')->orderBy('urutan', 'ASC')->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $slugSah = array_column($puList, 'slug');
        $puMinta = trim((string) ($this->request->getGet('pu') ?? ''));
        if ($puMinta !== '' && $puMinta !== 'tanpa' && ! in_array($puMinta, $slugSah, true)) {
            $puMinta = '';
        }

        $baris = $this->rekapLintas($tahun, $bulan, $this->daftarOpd(''));

        // Kelompokkan IKP per PU (kunci slug; 'tanpa' = belum terpetakan).
        $grup = [];
        foreach ($baris as $b) {
            foreach ($b['ikp'] as $i) {
                $slug = (string) ($i['rekap']['ikp']['pu_slug'] ?? '') ?: 'tanpa';
                $grup[$slug][] = ['opd' => $b['opd']] + $i;
            }
        }
        $tile = [];
        foreach (array_merge($puList, [['slug' => 'tanpa', 'nama' => 'Belum terpetakan ke Program Unggulan', 'warna' => '#8a968f', 'ikon' => 'fa-circle-question']]) as $pu) {
            $isi    = $grup[$pu['slug']] ?? [];
            $persen = array_values(array_filter(array_map(static fn ($x) => $x['sd']['persen'], $isi), static fn ($v) => $v !== null));
            $rata   = $persen === [] ? null : round(array_sum($persen) / count($persen), 2);
            $tile[] = [
                'pu'      => $pu,
                'jumlah'  => count($isi),
                'opd'     => count(array_unique(array_map(static fn ($x) => (int) $x['opd']['id'], $isi))),
                'rata'    => $rata,
                'status'  => $this->statusDariPersen($rata),
                'merah'   => count(array_filter($isi, static fn ($x) => $x['sd']['status']['kelompok'] === 'merah')),
            ];
        }

        $rinci = [];
        $puAktif = null;
        if ($puMinta !== '') {
            $rinci = $grup[$puMinta] ?? [];
            usort($rinci, static fn ($a, $b) => [$a['opd']['nama_opd'], (int) $a['rekap']['ikp']['urutan'], (int) $a['rekap']['ikp']['id']]
                <=> [$b['opd']['nama_opd'], (int) $b['rekap']['ikp']['urutan'], (int) $b['rekap']['ikp']['id']]);
            foreach ($tile as $t) {
                if ($t['pu']['slug'] === $puMinta) {
                    $puAktif = $t;
                }
            }
        }

        return view('ikp/kab_pu', [
            'title'     => 'IKP per Program Unggulan',
            'tahun'     => $tahun,
            'bulan'     => $bulan,
            'tahunList' => $this->rekap->tahunPeriode(),
            'tile'      => $tile,
            'puMinta'   => $puMinta,
            'puAktif'   => $puAktif,
            'rinci'     => $rinci,
        ]);
    }

    /** GET adminkab/ikp/cetak?tahun=&bulan=&jenis= — PDF A4 mendatar. */
    public function cetak()
    {
        [$tahun, $bulan] = $this->tahunBulan();
        $jenis = $this->jenisDiminta();
        $baris = $this->rekapLintas($tahun, $bulan, $this->daftarOpd($jenis));

        try {
            helper(['setting', 'pdf']);
            $html = view('ikp/kab_cetak', [
                'judul'    => 'Rekap Kinerja Prioritas (IKP) Lintas Perangkat Daerah',
                'subjudul' => 'Tahun ' . $tahun . ' · capaian s.d. ' . ikp_nama_bulan($bulan)
                    . ($jenis !== '' ? ' · ' . (OpdModel::JENIS_LABEL[$jenis] ?? $jenis) : ''),
                'namaUnit' => '',
                'tahun'    => $tahun,
                'bulan'    => $bulan,
                'baris'    => $baris,
                'total'    => $this->totalKabupaten($baris),
            ]);
            $mpdf = new \App\Libraries\PdfMpdf([
                'mode' => 'utf-8', 'format' => 'A4-L', 'default_font_size' => 9,
                'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 14,
                'tempDir' => sys_get_temp_dir(),
            ]);
            $mpdf->SetHTMLFooter(pdf_footer_aksara());
            pdf_watermark_aksara($mpdf);
            $mpdf->WriteHTML($html);
            $mpdf->SetTitle('Rekap IKP ' . $tahun . ' s.d. ' . ikp_nama_bulan($bulan));
        } catch (Throwable $e) {
            return redirect()->to(base_url('adminkab/ikp'))->with('error', pesanGalatBerawalan($e, 'Rekap IKP gagal dicetak', 'kab.ikp_cetak'));
        }
        $this->response->setHeader('Content-Type', 'application/pdf');

        return $mpdf->Output('Rekap-IKP-' . $tahun . '-sd-' . str_pad((string) $bulan, 2, '0', STR_PAD_LEFT) . '.pdf', 'I');
    }

    /* =====================================================================
     * RANGKUMAN BERSAMA (dipakai juga Bupati\IkpMonitoringController)
     * ===================================================================*/

    /**
     * OPD yang direkap: jenis opd/kecamatan, bukan EXCLUDED_OPD_IDS; kembaran
     * tanpa PK hanya bila punya IKP.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function daftarOpd(string $jenis = ''): array
    {
        $b = $this->db->table('opd')->select('id, nama_opd, singkatan, jenis')
            ->whereNotIn('id', OpdModel::EXCLUDED_OPD_IDS);
        $b->whereIn('jenis', $jenis !== '' ? [$jenis] : [OpdModel::JENIS_OPD, OpdModel::JENIS_KECAMATAN]);
        $rows = $b->orderBy('nama_opd', 'ASC')->get()->getResultArray();

        $punyaIkp = array_map('intval', array_column(
            $this->db->table('ikp')->select('opd_id')->distinct()
                ->where('dihapus_pada', null)
                ->whereIn('opd_id', array_keys(self::OPD_KEMBAR))
                ->get()->getResultArray(),
            'opd_id'
        ));

        return array_values(array_filter($rows, static fn ($r) => ! isset(self::OPD_KEMBAR[(int) $r['id']])
            || in_array((int) $r['id'], $punyaIkp, true)));
    }

    /** Satu OPD dari daftar sah, atau 404. */
    protected function opdSah(int $id): array
    {
        foreach ($this->daftarOpd('') as $o) {
            if ((int) $o['id'] === $id) {
                return $o;
            }
        }
        throw PageNotFoundException::forPageNotFound('Perangkat daerah tidak ditemukan.');
    }

    protected function jenisDiminta(): string
    {
        $j = (string) ($this->request->getGet('jenis') ?? '');

        return in_array($j, [OpdModel::JENIS_OPD, OpdModel::JENIS_KECAMATAN], true) ? $j : '';
    }

    /**
     * Tahun (periode RPJMD aktif) & bulan dari query string.
     *
     * Bulan bawaan: bulan TERAKHIR yang sudah berisi realisasi (tidak melewati
     * bulan berjalan WIB); tahun lampau -> Desember. MENGAPA bukan date('n'):
     * AKSARA berjalan UTC dan bulan berganti 7 jam lebih lambat (kritik R11);
     * lagi pula bulan berjalan biasanya belum dilaporkan sehingga layar
     * bawaannya selalu tampak "kosong".
     *
     * @return array{0:int, 1:int}
     */
    protected function tahunBulan(): array
    {
        $tahunList = $this->rekap->tahunPeriode();
        $sekarang  = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $thIni     = (int) $sekarang->format('Y');
        $blIni     = (int) $sekarang->format('n');

        $tahun = (int) ($this->request->getGet('tahun') ?? 0);
        if (! in_array($tahun, $tahunList, true)) {
            $tahun = in_array($thIni, $tahunList, true) ? $thIni : (int) end($tahunList);
        }

        $bulan = (int) ($this->request->getGet('bulan') ?? 0);
        if ($bulan < 1 || $bulan > 12) {
            if ($tahun < $thIni) {
                $bulan = 12;
            } elseif ($tahun > $thIni) {
                $bulan = 1;
            } else {
                $row = $this->db->table('ikp_bulanan b')->selectMax('b.bulan', 'm')
                    ->join('ikp i', 'i.id = b.ikp_id')
                    ->where('i.dihapus_pada', null)
                    ->where('b.tahun', $tahun)->where('b.realisasi IS NOT NULL', null, false)
                    ->where('b.bulan <=', $blIni)
                    ->get()->getRowArray();
                $bulan = (int) ($row['m'] ?? 0) ?: $blIni;
            }
        }

        return [$tahun, $bulan];
    }

    /**
     * Rangkuman per OPD. Untuk tiap OPD:
     *   opd, ringkas (IkpRekapService::ringkasOpd), ikp[] per IKP:
     *     rekap (elemen rekapOpd), sd (capaian Jan..bulan), bln (capaian bulan itu),
     *     per_bulan [1..12 => ?persen], keadaan [1..12 => tidak_diukur|belum_waktunya|diukur],
     *     lapor (realisasi bulan itu terisi?), wajib (bulan itu bulan ukur yang sudah tiba?)
     *   agregat: jumlah, lengkap, lapor_bulan, wajib_bulan, tidak_diukur_bulan, menunggu_rilis,
     *            sel_terisi, sel_wajib (hanya bulan ukur yang sudah tiba), rata_sd, status_sd,
     *            rata_bln, status_bln, per_bulan [1..12 => ?rata], warna [hijau,kuning,merah,abu],
     *            tanpa_metode, bulan_terakhir
     *
     * @param array<int, array<string, mixed>> $opdList
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rekapLintas(int $tahun, int $bulan, array $opdList): array
    {
        // POLA UKUR: "wajib lapor" hanya bulan ukur yang sudah tiba (rilis:
        // bulan rilisnya; rilis_tahun_berikut: tahun N+1). Bulan non-ukur tidak
        // dihitung capaiannya dan tidak dianggap "belum lapor".
        $kini   = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta'));
        $thKini = (int) $kini->format('Y');
        $blKini = (int) $kini->format('n');

        $out = [];
        foreach ($opdList as $opd) {
            $rekap   = $this->rekap->rekapOpd((int) $opd['id'], $tahun);
            $ringkas = $this->rekap->ringkasOpd((int) $opd['id'], $tahun, $rekap);

            $ikp      = [];
            $persenSd = [];
            $persenBl = [];
            $perBulan = array_fill(1, 12, []);
            $warna    = ['hijau' => 0, 'kuning' => 0, 'merah' => 0, 'abu' => 0];
            $lapor    = 0;
            $wajibBln = 0;
            $selIsi   = 0;
            $selWajib = 0;
            $tanpaMetode = 0;
            $tidakDiukurBln = 0;

            foreach ($rekap as $r) {
                $pola   = $r['pola'];
                $metode = (string) $pola['metode'];
                $t = [];
                $v = [];
                for ($m = 1; $m <= 12; $m++) {
                    $t[$m] = $r['bulan'][$m]['target'] ?? null;
                    $v[$m] = $r['bulan'][$m]['realisasi'] ?? null;
                }
                if (! ikp_metode_valid($metode)) {
                    $tanpaMetode++;
                }

                $sd  = $this->capaian($pola, $t, $v, 1, $bulan, $tahun);
                $bln = $this->capaian($pola, $t, $v, $bulan, $bulan, $tahun);
                $pb  = [];
                $pbKeadaan = [];
                for ($m = 1; $m <= 12; $m++) {
                    $keadaan       = ikp_keadaan_bulan($pola, $tahun, $m, $thKini, $blKini);
                    $pbKeadaan[$m] = $keadaan['kode'];
                    $pb[$m]        = $m <= $bulan ? $this->capaian($pola, $t, $v, $m, $m, $tahun)['persen'] : null;
                    if ($pb[$m] !== null) {
                        $perBulan[$m][] = $pb[$m];
                    }
                    if ($m <= $bulan && $keadaan['kode'] === 'diukur') {
                        $selWajib++;
                        if ($v[$m] !== null) {
                            $selIsi++;
                        }
                    }
                }
                $wajib = $pbKeadaan[$bulan] === 'diukur';
                if ($wajib) {
                    $wajibBln++;
                    if ($v[$bulan] !== null) {
                        $lapor++;
                    }
                } elseif ($pbKeadaan[$bulan] === 'tidak_diukur') {
                    $tidakDiukurBln++;
                }
                if ($sd['persen'] !== null) {
                    $persenSd[] = $sd['persen'];
                }
                if ($bln['persen'] !== null) {
                    $persenBl[] = $bln['persen'];
                }
                $warna[$sd['status']['kelompok']]++;

                $ikp[] = [
                    'rekap'      => $r,
                    'sd'         => $sd,
                    'bln'        => $bln,
                    'per_bulan'  => $pb,
                    // tidak_diukur | belum_waktunya | diukur per bulan (sel "—" di kisi)
                    'keadaan'    => $pbKeadaan,
                    'lapor'      => $v[$bulan] !== null,
                    // false = bulan terpilih bukan bulan ukur / rilisnya belum tiba: bukan "belum lapor".
                    'wajib'      => $wajib,
                ];
            }

            $rataSd = $persenSd === [] ? null : round(array_sum($persenSd) / count($persenSd), 2);
            $rataBl = $persenBl === [] ? null : round(array_sum($persenBl) / count($persenBl), 2);
            $rataPerBulan = [];
            for ($m = 1; $m <= 12; $m++) {
                $rataPerBulan[$m] = $perBulan[$m] === [] ? null : round(array_sum($perBulan[$m]) / count($perBulan[$m]), 2);
            }

            $out[] = [
                'opd'     => $opd,
                'ringkas' => $ringkas,
                'ikp'     => $ikp,
                'agregat' => [
                    'jumlah'         => count($rekap),
                    'lengkap'        => (int) $ringkas['lengkap_breakdown'],
                    'lapor_bulan'    => $lapor,
                    // IKP yang WAJIB melapor bulan terpilih (bulan ukurnya & sudah tiba).
                    'wajib_bulan'    => $wajibBln,
                    'tidak_diukur_bulan' => $tidakDiukurBln,
                    'sel_terisi'     => $selIsi,
                    'sel_wajib'      => $selWajib,
                    'rata_sd'        => $rataSd,
                    'status_sd'      => $this->statusDariPersen($rataSd),
                    'rata_bln'       => $rataBl,
                    'status_bln'     => $this->statusDariPersen($rataBl),
                    'per_bulan'      => $rataPerBulan,
                    'warna'          => $warna,
                    'tanpa_metode'   => $tanpaMetode,
                    'menunggu_rilis' => (int) ($ringkas['menunggu_rilis'] ?? 0),
                    'bulan_terakhir' => $ringkas['bulan_terakhir'],
                ],
            ];
        }

        return $out;
    }

    /**
     * Capaian satu IKP atas rentang bulan (rumus ikp_capaian_pola: bulan
     * non-ukur tidak dihitung) + status warna ambang.
     *
     * @return array{persen: ?float, status: array, keterangan: ?string, bulan_terakhir: ?int, tidak_diukur: bool, menunggu_rilis: bool}
     */
    protected function capaian(array $pola, array $t, array $v, int $dari, int $sampai, ?int $tahun = null): array
    {
        $h = ikp_capaian_pola($pola, $t, $v, $dari, $sampai, [], $tahun);

        return [
            'persen'         => $h['status'] === 'calculated' && $h['percentage'] !== null ? (float) $h['percentage'] : null,
            'status'         => ikp_status($h),
            'keterangan'     => $h['calculation_description'] ?? null,
            'bulan_terakhir' => $h['bulan_terakhir'] ?? null,
            'tidak_diukur'   => ! empty($h['tidak_diukur']),
            'menunggu_rilis' => ! empty($h['menunggu_rilis']),
        ];
    }

    /** Status warna dari rata-rata persen (ambang dashboard; null = belum ada data). */
    protected function statusDariPersen(?float $persen): array
    {
        return ikp_status($persen === null
            ? ['status' => 'incomplete', 'percentage' => null, 'error' => null]
            : ['status' => 'calculated', 'percentage' => $persen, 'error' => null]);
    }

    /** Ringkasan satu kabupaten untuk kartu atas & cetak. */
    protected function totalKabupaten(array $baris): array
    {
        $opdBerIkp = array_filter($baris, static fn ($b) => $b['agregat']['jumlah'] > 0);
        $persen    = [];
        $t = ['opd' => count($baris), 'opd_ber_ikp' => count($opdBerIkp), 'ikp' => 0, 'lengkap' => 0,
              'lapor_opd' => 0, 'lapor_ikp' => 0, 'wajib_ikp' => 0, 'opd_wajib' => 0, 'menunggu_rilis' => 0,
              'hijau' => 0, 'kuning' => 0, 'merah' => 0, 'abu' => 0, 'tanpa_metode' => 0];
        foreach ($baris as $b) {
            $a = $b['agregat'];
            $t['ikp']          += $a['jumlah'];
            $t['lengkap']      += $a['lengkap'];
            $t['lapor_ikp']    += $a['lapor_bulan'];
            $t['wajib_ikp']    += $a['wajib_bulan'];
            $t['menunggu_rilis'] += $a['menunggu_rilis'];
            if ($a['wajib_bulan'] > 0) {
                $t['opd_wajib']++;
            }
            $t['tanpa_metode'] += $a['tanpa_metode'];
            if ($a['lapor_bulan'] > 0) {
                $t['lapor_opd']++;
            }
            foreach (['hijau', 'kuning', 'merah', 'abu'] as $w) {
                $t[$w] += $a['warna'][$w];
            }
            foreach ($b['ikp'] as $i) {
                if ($i['sd']['persen'] !== null) {
                    $persen[] = $i['sd']['persen'];
                }
            }
        }
        $t['rata_sd']   = $persen === [] ? null : round(array_sum($persen) / count($persen), 2);
        $t['status_sd'] = $this->statusDariPersen($t['rata_sd']);

        return $t;
    }
}
