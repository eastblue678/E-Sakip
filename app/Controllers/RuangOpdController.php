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

        // "PK di AKSARA": eKin tidak punya pihak kedua/baris untuk mereka — AKSARA pemilik dokumennya (satu kueri).
        $lewat = array_map(static fn ($pk) => (int) ($pk['pegawai']['id'] ?? 0),
            array_filter($semua, static fn ($pk) => ($pk['status'] ?? '') === 'lewat_aksara'));

        return view('ruang_opd/pk_pegawai', $this->dataDasar($opd, $tahun) + [
            'title'     => 'PK Pegawai · ' . $opd['nama_tampil'],
            'ada'       => $data !== null,
            'pkAksara'  => $this->svc()->pkAksaraPerPihakPertama($lewat, $tahun),
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

        // Pihak pertama PK jabatan: eKin sengaja tidak membuat PK pegawai (jawabannya kosong). Tampilkan PK AKSARA-nya
        // sendiri — isi sasaran/indikator/target dan tombol Lihat/Cetak — bukan kertas eKin tanpa isi.
        $pkAksara = ($dok['status'] ?? '') === 'lewat_aksara' ? ($this->svc()->pkAksaraPerPihakPertama([$pegawaiId], $tahun)[$pegawaiId] ?? []) : [];

        return view('ruang_opd/pk_pegawai_dokumen', $this->dataDasar($opd, $tahun) + [
            'title'     => 'Dokumen PK Pegawai · ' . $opd['nama_tampil'],
            'dok'       => $dok,
            'pkAksara'  => $pkAksara,
            'isiPkAksara' => $this->svc()->isiPk(array_column($pkAksara, 'id')),
            'ekinPesan' => $dok === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '',
        ]);
    }

    /**
     * GET rencana-aksi-pegawai?tahun=&bulan= — pintu dari menu Target & Rencana Aksi.
     * Peran OPD langsung ke OPD-nya; peran lintas OPD melihat ringkasan per OPD yang pegawainya sudah dimuat di eKin.
     */
    public function rencanaAksiPegawaiIndex()
    {
        $akses = $this->akses();
        if ($akses instanceof RedirectResponse) {
            return $akses;
        }
        $tahun = $this->svc()->tahunDari($this->request->getGet('tahun'));
        $bulan = self::bulanDari($this->request->getGet('bulan'), $tahun);
        if ($akses['opd'] !== null) {
            return redirect()->to(base_url('ruang-opd/' . $akses['opd'] . '/rencana-aksi-pegawai?tahun=' . $tahun . '&bulan=' . $bulan));
        }

        $ekin    = new EkinClient();
        $ringkas = $ekin->ringkasSemua($tahun);
        $nama    = [];
        foreach ($this->svc()->daftarOpd() as $o) {
            $nama[(int) $o['id']] = $o['nama_tampil'];
        }
        $baris = [];
        foreach (array_keys($ringkas['opd'] ?? []) as $id) {
            $id = (int) $id;
            if (! isset($nama[$id])) {
                continue;
            }
            $d = $ekin->rencanaAksiOpd($id, $tahun, $bulan);
            $r = ['opd_id' => $id, 'nama' => $nama[$id], 'ada' => $d !== null, 'pegawai' => 0, 'ber_skp' => 0, 'ra' => 0, 'tercapai' => 0, 'belum' => 0, 'capaian' => null];
            $cap = [];
            foreach ($d['pegawai'] ?? [] as $p) {
                $r['pegawai']++;
                $r['ber_skp'] += $p['skp'] !== null ? 1 : 0;
                $r['ra'] += (int) ($p['ra']['jumlah'] ?? 0);
                $r['tercapai'] += (int) ($p['ra']['tercapai'] ?? 0);
                $r['belum'] += (int) ($p['ra']['belum_ada_kegiatan'] ?? 0);
                if (($p['ra']['capaian'] ?? null) !== null) {
                    $cap[] = (float) $p['ra']['capaian'];
                }
            }
            $r['capaian'] = $cap === [] ? null : round(array_sum($cap) / count($cap), 1);
            $baris[] = $r;
        }
        usort($baris, static fn ($a, $b) => strcmp($a['nama'], $b['nama']));

        return view('ruang_opd/rencana_aksi_pegawai_index', [
            'title'     => 'Rencana Aksi Pegawai · AKSARA+',
            'tahun'     => $tahun,
            'tahunList' => $this->svc()->daftarTahun(),
            'bulan'     => $bulan,
            'baris'     => $baris,
            'ekinAda'   => $ringkas !== null,
            'ekinPesan' => $ringkas === null ? $ekin->pesanTerakhir() : '',
            'peran'     => (string) session('role'),
            'shellCss'  => view('ruang_opd/_gaya', [], ['saveData' => false, 'debug' => false]),
        ]);
    }

    /**
     * GET ruang-opd/(:num)/rencana-aksi-pegawai?tahun=&bulan=&status=&q=
     *
     * Rencana aksi BULANAN semua pegawai OPD sampai pelaksana, dari eKin (sumber realisasi yang sama dengan halaman
     * Rencana Aksi eKin). Disusun per atasan langsung supaya terbaca sebagai rantai: pimpinan → bawahan.
     */
    public function rencanaAksiPegawai($id = null)
    {
        $opd = $this->opdBoleh((int) $id);
        if ($opd instanceof RedirectResponse) {
            return $opd;
        }
        $tahun  = $this->svc()->tahunDari($this->request->getGet('tahun'));
        $bulan  = self::bulanDari($this->request->getGet('bulan'), $tahun);
        $status = (string) $this->request->getGet('status');
        $status = in_array($status, ['belum', 'berjalan', 'tercapai', 'tanpa_skp'], true) ? $status : '';
        $q      = mb_substr(trim((string) $this->request->getGet('q')), 0, 60);
        $ekin   = new EkinClient();
        $data   = $ekin->rencanaAksiOpd($opd['id'], $tahun, $bulan);
        $pk     = $data === null ? null : $ekin->pkPegawaiOpd($opd['id'], $tahun);

        $pkStatus = [];
        foreach ($pk['pk'] ?? [] as $r) {
            $pkStatus[(int) ($r['pegawai']['id'] ?? 0)] = (string) ($r['status'] ?? '');
        }

        $susun = $data === null ? [] : EkinClient::susunPerAtasan($data['pegawai']);
        $ringkas = ['pegawai' => count($susun), 'ber_skp' => 0, 'ra' => 0, 'tercapai' => 0, 'belum' => 0, 'capaian' => []];
        foreach ($susun as $b) {
            $ra = $b['p']['ra'] ?? [];
            $ringkas['ber_skp'] += $b['p']['skp'] !== null ? 1 : 0;
            $ringkas['ra'] += (int) ($ra['jumlah'] ?? 0);
            $ringkas['tercapai'] += (int) ($ra['tercapai'] ?? 0);
            $ringkas['belum'] += (int) ($ra['belum_ada_kegiatan'] ?? 0);
            if (($ra['capaian'] ?? null) !== null) {
                $ringkas['capaian'][] = (float) $ra['capaian'];
            }
        }
        $ringkas['capaian'] = $ringkas['capaian'] === [] ? null : round(array_sum($ringkas['capaian']) / count($ringkas['capaian']), 1);

        // Saringan: baris yang tidak cocok disembunyikan, tetapi atasannya tetap tampil sebagai konteks (redup).
        $cocok = static function (array $p) use ($status, $q): bool {
            $ra = $p['ra'] ?? [];
            $ok = match ($status) {
                'belum'     => $p['skp'] !== null && (int) ($ra['belum_ada_kegiatan'] ?? 0) > 0,
                'berjalan'  => $p['skp'] !== null && (int) ($ra['jumlah'] ?? 0) > 0 && (int) ($ra['tercapai'] ?? 0) < (int) ($ra['jumlah'] ?? 0),
                'tercapai'  => (int) ($ra['jumlah'] ?? 0) > 0 && (int) ($ra['tercapai'] ?? 0) === (int) ($ra['jumlah'] ?? 0),
                'tanpa_skp' => $p['skp'] === null,
                default     => true,
            };

            return $ok && ($q === '' || str_contains(mb_strtolower(($p['nama'] ?? '') . ' ' . ($p['jabatan'] ?? '')), mb_strtolower($q)));
        };
        $baris = [];
        foreach ($susun as $b) {
            $b['cocok'] = $cocok($b['p']);
            $baris[] = $b;
        }
        if ($status !== '' || $q !== '') {
            // Pertahankan hanya baris yang cocok + atasan-atasannya (supaya konteks rantai tetap terbaca).
            $simpan = [];
            $tumpuk = [];
            foreach ($baris as $i => $b) {
                $tumpuk = array_slice($tumpuk, 0, $b['tingkat']);
                $tumpuk[$b['tingkat']] = $i;
                if ($b['cocok']) {
                    foreach ($tumpuk as $j) {
                        $simpan[$j] = true;
                    }
                }
            }
            $baris = array_values(array_intersect_key($baris, $simpan));
        }

        return view('ruang_opd/rencana_aksi_pegawai', $this->dataDasar($opd, $tahun) + [
            'title'     => 'Rencana Aksi Pegawai · ' . $opd['nama_tampil'],
            'ada'       => $data !== null,
            'bulan'     => $bulan,
            'status'    => $status,
            'q'         => $q,
            'baris'     => $baris,
            'ringkas'   => $ringkas,
            'pkStatus'  => $pkStatus,
            'ekinPesan' => $data === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '',
        ]);
    }

    /** GET ruang-opd/(:num)/rencana-aksi-pegawai/(:num)?tahun= — rencana aksi setahun satu pegawai. */
    public function rencanaAksiPegawaiDetail($id = null, $pegawaiId = null)
    {
        $opd = $this->opdBoleh((int) $id);
        if ($opd instanceof RedirectResponse) {
            return $opd;
        }
        $tahun     = $this->svc()->tahunDari($this->request->getGet('tahun'));
        $pegawaiId = (int) $pegawaiId;
        $ekin      = new EkinClient();

        // Sama dengan dokumen PK: pegawai harus tercantum di daftar OPD ini, supaya admin OPD tidak bisa membaca
        // rencana aksi pegawai OPD lain dengan mengganti angka di alamat.
        $daftar = $ekin->rencanaAksiOpd($opd['id'], $tahun, self::bulanDari(null, $tahun));
        if ($daftar !== null && ! in_array($pegawaiId, array_map(static fn ($p) => (int) ($p['pegawai_id'] ?? 0), $daftar['pegawai']), true)) {
            throw PageNotFoundException::forPageNotFound('Pegawai tidak ditemukan di perangkat daerah ini.');
        }
        $data = $daftar === null ? null : $ekin->rencanaAksiPegawai($pegawaiId, $tahun);
        $pk   = $data === null ? null : $ekin->pkPegawaiOpd($opd['id'], $tahun);
        $pkIni = null;
        foreach ($pk['pk'] ?? [] as $r) {
            if ((int) ($r['pegawai']['id'] ?? 0) === $pegawaiId) {
                $pkIni = $r;
            }
        }

        return view('ruang_opd/rencana_aksi_pegawai_detail', $this->dataDasar($opd, $tahun) + [
            'title'     => 'Rencana Aksi Pegawai · ' . $opd['nama_tampil'],
            'data'      => $data,
            'pk'        => $pkIni,
            'bulanKini' => self::bulanDari(null, $tahun),
            'ekinPesan' => $data === null ? $this->pesanEkin($ekin, $opd['id'], $tahun) : '',
        ]);
    }

    /** Bulan dari ?bulan= (1–12); bawaan bulan berjalan untuk tahun berjalan, Desember untuk tahun lampau, Januari untuk tahun depan. */
    public static function bulanDari($isian, int $tahun, ?int $tahunIni = null, ?int $bulanIni = null): int
    {
        $b = is_numeric($isian) ? (int) $isian : 0;
        if ($b >= 1 && $b <= 12) {
            return $b;
        }
        $tahunIni ??= (int) date('Y');
        $bulanIni ??= (int) date('n');

        return $tahun === $tahunIni ? $bulanIni : ($tahun < $tahunIni ? 12 : 1);
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
