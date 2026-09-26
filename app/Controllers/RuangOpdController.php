<?php

namespace App\Controllers;

use App\Services\EkinClient;
use App\Services\MasukSebagaiService;
use App\Services\RuangOpdService;
use App\Services\RuangOpdTautan;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * AKSARA+ — RUANG OPD: satu pintu untuk seluruh dokumen & kinerja satu perangkat daerah.
 *
 * Rute (blok AKSARA+ di ujung Config\Routes, filter 'auth', GET saja):
 *   GET ruang-opd                                    index              matriks kelengkapan semua OPD
 *   GET ruang-opd/(:num)                             hub                satu OPD, per tahap siklus SAKIP
 *   GET ruang-opd/(:num)/cascading-pegawai           cascadingPegawai   pohon RHK pegawai (eKin)
 *   GET ruang-opd/(:num)/pk-pegawai                  pkPegawai          daftar PK pegawai (eKin)
 *   GET ruang-opd/(:num)/pk-pegawai/(:num)           pkPegawaiDokumen   dokumen PK satu pegawai (eKin)
 *
 * AKSES — dijaga DI SINI, bukan oleh filter:
 *   * jalur ini di luar adminkab/* & adminopd/*, jadi ModulePermissionFilter tidak
 *     menyentuhnya; 'auth' hanya memastikan sudah masuk;
 *   * admin, admin_kab, admin_inspektorat, bupati -> Ruang OPD mana pun (baca);
 *   * admin_opd, admin_kecamatan -> HANYA OPD di sesinya: index dialihkan ke hubnya,
 *     id OPD lain dialihkan kembali ke hub sendiri dengan pesan (tidak pernah dibaca);
 *   * peran lain -> /unauthorized.
 * Semua rute GET: ReadOnlyRoleFilter (Bupati) tidak perlu dilonggarkan.
 *
 * Halaman ini TIDAK menulis apa pun. Setiap tombol "Buka" menuju halaman lama yang
 * memang boleh dibuka peran itu (App\Services\RuangOpdTautan).
 */
class RuangOpdController extends BaseController
{
    protected $helpers = ['ikp', 'rbac', 'format'];

    private ?RuangOpdService $svc = null;

    private function svc(): RuangOpdService
    {
        return $this->svc ??= new RuangOpdService();
    }

    // =================================================================
    // HALAMAN
    // =================================================================

    /** GET ruang-opd?tahun= */
    public function index()
    {
        $akses = $this->akses();
        if ($akses instanceof RedirectResponse) {
            return $akses;
        }
        $tahun = $this->svc()->tahunDari($this->request->getGet('tahun'));
        if ($akses['opd'] !== null) {
            return redirect()->to(base_url('ruang-opd/' . $akses['opd'] . '?tahun=' . $tahun));
        }

        $daftar  = $this->svc()->daftarOpd();
        $ekin    = new EkinClient();
        $ringkas = $ekin->ringkasSemua($tahun);
        $matriks = $this->svc()->matriks($tahun, array_column($daftar, 'id'), $ringkas, $ringkas === null ? $ekin->pesanTerakhir() : '');

        return view('ruang_opd/index', [
            'title'      => 'Ruang OPD · AKSARA+',
            'tahun'      => $tahun,
            'tahunList'  => $this->svc()->daftarTahun(),
            'daftar'     => $daftar,
            'kepala'     => $this->svc()->kepalaPerOpd($tahun),
            'matriks'    => $matriks,
            'ringkas'    => RuangOpdService::ringkasKolom($matriks),
            'ekinAda'    => $ringkas !== null,
            'ekinPesan'  => $ringkas === null ? $ekin->pesanTerakhir() : '',
            'ekinWaktu'  => $ringkas['diperbarui'] ?? null,
            'peran'      => (string) session('role'),
            'shellCss'   => view('ruang_opd/_gaya', [], ['saveData' => false, 'debug' => false]),
        ]);
    }

    /** GET ruang-opd/(:num)?tahun= */
    public function hub($id = null)
    {
        $opd = $this->opdBoleh((int) $id);
        if ($opd instanceof RedirectResponse) {
            return $opd;
        }
        $tahun = $this->svc()->tahunDari($this->request->getGet('tahun'));

        $ekin    = new EkinClient();
        $ringkas = $ekin->ringkasOpd($opd['id'], $tahun);
        $pesan   = $ringkas === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '';
        $data    = $this->svc()->hub($opd['id'], $tahun, $ringkas, $pesan);

        return view('ruang_opd/hub', $this->dataDasar($opd, $tahun) + [
            'title'     => 'Ruang ' . $opd['nama_tampil'] . ' · AKSARA+',
            'd'         => $data,
            'ekinAda'   => $ringkas !== null,
            'ekinPesan' => $pesan,
        ]);
    }

    /** GET ruang-opd/(:num)/cascading-pegawai?tahun= */
    public function cascadingPegawai($id = null)
    {
        $opd = $this->opdBoleh((int) $id);
        if ($opd instanceof RedirectResponse) {
            return $opd;
        }
        $tahun = $this->svc()->tahunDari($this->request->getGet('tahun'));
        $ekin  = new EkinClient();
        $data  = $ekin->cascading($opd['id'], $tahun);

        return view('ruang_opd/cascading_pegawai', $this->dataDasar($opd, $tahun) + [
            'title'     => 'Cascading Pegawai · ' . $opd['nama_tampil'],
            'pohon'     => $data === null ? null : EkinClient::bangunPohon($data),
            'ekinPesan' => $data === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '',
        ]);
    }

    /** GET ruang-opd/(:num)/pk-pegawai?tahun=&status=&q= */
    public function pkPegawai($id = null)
    {
        $opd = $this->opdBoleh((int) $id);
        if ($opd instanceof RedirectResponse) {
            return $opd;
        }
        $tahun  = $this->svc()->tahunDari($this->request->getGet('tahun'));
        $ekin   = new EkinClient();
        $data   = $ekin->pkPegawaiOpd($opd['id'], $tahun);
        $status = (string) $this->request->getGet('status');
        $q      = mb_substr(trim((string) $this->request->getGet('q')), 0, 60);

        $semua  = $data['pk'] ?? [];
        $hitung = [];
        foreach ($semua as $pk) {
            $s = (string) ($pk['status'] ?? '');
            $hitung[$s] = ($hitung[$s] ?? 0) + 1;
        }
        $baris = array_values(array_filter($semua, static function ($pk) use ($status, $q) {
            if ($status !== '' && ($pk['status'] ?? '') !== $status) {
                return false;
            }
            if ($q === '') {
                return true;
            }
            $teks = mb_strtolower(($pk['pegawai']['nama'] ?? '') . ' ' . ($pk['pegawai']['jabatan'] ?? '') . ' '
                . ($pk['pihak_kedua']['nama'] ?? '') . ' ' . ($pk['pihak_kedua']['jabatan'] ?? ''));

            return str_contains($teks, mb_strtolower($q));
        }));

        return view('ruang_opd/pk_pegawai', $this->dataDasar($opd, $tahun) + [
            'title'     => 'PK Pegawai · ' . $opd['nama_tampil'],
            'ada'       => $data !== null,
            'baris'     => $baris,
            'jumlah'    => count($semua),
            'hitung'    => $hitung,
            'status'    => $status,
            'q'         => $q,
            'ekinPesan' => $data === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '',
        ]);
    }

    /** GET ruang-opd/(:num)/pk-pegawai/(:num)?tahun= */
    public function pkPegawaiDokumen($id = null, $pegawaiId = null)
    {
        $opd = $this->opdBoleh((int) $id);
        if ($opd instanceof RedirectResponse) {
            return $opd;
        }
        $tahun     = $this->svc()->tahunDari($this->request->getGet('tahun'));
        $pegawaiId = (int) $pegawaiId;
        $ekin      = new EkinClient();

        // MENGAPA daftar OPD diperiksa dulu: endpoint dokumen menerima id pegawai saja.
        // Tanpa pemeriksaan ini Admin OPD bisa membaca PK pegawai OPD lain dengan
        // mengganti angka di alamat. Hanya pegawai yang tercantum di daftar PK OPD ini
        // (endpoint yang sama dengan halaman daftar, sudah di-cache) yang boleh dibuka.
        $daftar = $ekin->pkPegawaiOpd($opd['id'], $tahun);
        if ($daftar !== null) {
            $milikOpd = array_map(static fn ($p) => (int) ($p['pegawai']['id'] ?? 0), $daftar['pk'] ?? []);
            if (! in_array($pegawaiId, $milikOpd, true)) {
                throw PageNotFoundException::forPageNotFound('Pegawai tidak ditemukan di perangkat daerah ini.');
            }
        }
        $dok = $daftar === null ? null : $ekin->pkPegawai($pegawaiId, $tahun);

        return view('ruang_opd/pk_pegawai_dokumen', $this->dataDasar($opd, $tahun) + [
            'title'     => 'Dokumen PK Pegawai · ' . $opd['nama_tampil'],
            'dok'       => $dok,
            'ekinPesan' => $dok === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '',
        ]);
    }

    // =================================================================
    // AKSES & DATA BERSAMA
    // =================================================================

    /**
     * @return array{opd:?int}|RedirectResponse opd = id OPD sesi untuk peran OPD, null untuk peran lintas OPD
     */
    private function akses()
    {
        $peran = (string) session('role');
        if (in_array($peran, RuangOpdTautan::PERAN_LINTAS, true)) {
            return ['opd' => null];
        }
        if (in_array($peran, RuangOpdTautan::PERAN_OPD, true)) {
            $sesi = (int) (session('opd_id') ?? 0);
            if ($sesi > 0) {
                return ['opd' => $sesi];
            }

            return redirect()->to(base_url('adminopd/dashboard'))
                ->with('error', 'Akun Anda belum terhubung ke perangkat daerah. Hubungi administrator.');
        }

        return redirect()->to(base_url('unauthorized'))->with('error', 'Anda tidak memiliki akses ke Ruang OPD.');
    }

    /** @return array<string,mixed>|RedirectResponse OPD yang boleh dibuka peran ini */
    private function opdBoleh(int $id)
    {
        $akses = $this->akses();
        if ($akses instanceof RedirectResponse) {
            return $akses;
        }
        if ($akses['opd'] !== null && $akses['opd'] !== $id) {
            return redirect()->to(base_url('ruang-opd/' . $akses['opd']))
                ->with('error', 'Anda hanya dapat membuka Ruang OPD perangkat daerah Anda sendiri.');
        }
        $opd = $this->svc()->opd($id);
        if ($opd === null) {
            throw PageNotFoundException::forPageNotFound('Perangkat daerah tidak ditemukan.');
        }

        return $opd;
    }

    /**
     * Kalimat untuk keadaan "Data eKin belum tersedia".
     *
     * MENGAPA memeriksa ringkasan semua OPD: endpoint per OPD membalas 404 baik ketika
     * layanan eKin untuk AKSARA belum dipasang MAUPUN ketika OPD itu memang belum punya
     * pegawai di eKin (kelurahan, UPT). Bila ringkasan kabupaten berhasil tetapi OPD ini
     * tidak ada di dalamnya, sebabnya yang kedua — dan pengguna perlu tahu bedanya.
     */
    private function pesanEkin(EkinClient $ekin, int $opdId, int $tahun): string
    {
        $pesan = $ekin->pesanTerakhir();
        if ($ekin->alasanTerakhir() === 'belum_tersedia') {
            $semua = $ekin->ringkasSemua($tahun);
            if ($semua !== null && ! isset($semua['opd'][(string) $opdId])) {
                return 'Pegawai perangkat daerah ini belum dimuat di eKin.';
            }
        }

        return $pesan;
    }

    /** Data yang dipakai semua halaman Ruang OPD satu OPD (kepala halaman & tautan). */
    private function dataDasar(array $opd, int $tahun): array
    {
        $peran   = (string) session('role');
        $periode = $this->svc()->periodeUntuk($tahun);
        $opdId   = (int) $opd['id'];
        $lintas  = in_array($peran, RuangOpdTautan::PERAN_LINTAS, true);
        $ikpKab  = $this->svc()->ikpKabBerlaku($opdId);

        // Pintasan audiensi "Masuk sebagai admin OPD ini": hanya bila memang ADA akun aktif
        // untuk OPD ini (RSUD, UPT, kelurahan tidak punya) — dulu tautannya berakhir di daftar kosong.
        $masukSebagai = $lintas
            && MasukSebagaiService::bolehDipakai()
            && ! MasukSebagaiService::sedangMeniru()
            && (new MasukSebagaiService())->adaAkunUntukOpd($opdId);

        return [
            'opd'       => $opd,
            'tahun'     => $tahun,
            'tahunList' => $this->svc()->daftarTahun(),
            'peran'     => $peran,
            'lintas'    => $lintas,
            'masukSebagai' => $masukSebagai,
            'kepala'    => $this->svc()->kepalaPerOpd($tahun)[$opdId] ?? null,
            // Pemberi tautan untuk view: fn(item, ctx) => ['url','publik','baru'] | null
            // ctx['tahun'] menimpa tahun halaman (mis. tautan LAKIP tahun lalu).
            'buka'      => static fn (string $item, array $ctx = []) => RuangOpdTautan::untuk(
                $peran, $item, $opdId, (int) ($ctx['tahun'] ?? $tahun), $ctx + ['periode' => $periode, 'ikp_kab' => $ikpKab], 'user_can'
            ),
            'shellCss'  => view('ruang_opd/_gaya', [], ['saveData' => false, 'debug' => false]),
        ];
    }
}
