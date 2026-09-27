<?php

namespace App\Controllers;

use App\Services\EkinClient;
use App\Services\RuangOpdService;
use App\Services\RuangOpdTautan;

/**
 * AKSARA+ — PERJANJIAN KINERJA TERPADU: SATU menu untuk semua jenis PK.
 *
 * Sebelumnya menu memecah PK menjadi "PK JPT / PK Kecamatan / PK Administrator /
 * PK Pengawas" (dan kabupaten hanya melihat PK Bupati), sehingga pengguna harus
 * menebak jenis dulu sebelum melihat apa pun. Di sini pilihan itu pindah ke dalam
 * halaman sebagai saringan (tahun, jenis, OPD, cari nama/jabatan); tiap baris
 * menautkan ke rute LAMA yang boleh dipakai peran itu (lihat/ubah/cetak) — semua
 * alamat lama tetap berlaku, halaman ini hanya daftar dan pintu.
 *
 *   GET perjanjian-kinerja?tahun=&jenis=&opd_id=&q=&pegawai=&hal=
 *   (pegawai = id pegawai pihak pertama; dipakai tombol "PK AKSARA" di PK Pegawai Ruang OPD)
 *
 * Lingkup dijaga di sini (jalur di luar adminkab/* & adminopd/*, jadi modperm tidak
 * berlaku): admin OPD/kecamatan hanya OPD sesinya (opd_id dari query diabaikan);
 * kabupaten, inspektorat, Bupati, super admin lintas OPD, baca. Hanya GET.
 */
class PerjanjianKinerjaController extends BaseController
{
    protected $helpers = ['rbac', 'ikp'];

    public const JENIS = [
        'bupati'        => 'PK Bupati',
        'jpt'           => 'JPT (Eselon II)',
        'camat'         => 'Camat',
        'administrator' => 'Administrator (Eselon III)',
        'pengawas'      => 'Pengawas (Eselon IV)',
    ];

    private const PER_HALAMAN = 50;

    /**
     * AKSARA+ — jenjang pelaksana & JF: PK Pegawai hidup di eKin (disusun dari SKP & rencana aksi), dibaca lewat
     * EkinClient. Pejabat yang PK-nya dokumen AKSARA ("PK di AKSARA") tidak diulang di sini.
     */
    public const JENIS_PEGAWAI = 'pegawai';

    public const STATUS_PK_PEGAWAI = [
        'draf'           => ['s-abu', 'Draf'],
        'diajukan'       => ['s-kuning', 'Diajukan'],
        'dikembalikan'   => ['s-merah', 'Dikembalikan'],
        'ditandatangani' => ['s-hijau', 'Ditandatangani'],
    ];

    public function index()
    {
        $peran = (string) session('role');
        $kel   = RuangOpdTautan::kelompokPeran($peran);
        $boleh = match ($kel) {
            'opd'    => user_can('pk_opd.view'),
            'kab'    => user_can('pk_bupati.view') || user_can('pk_opd.view'),
            'bupati' => user_can('pk_bupati_monitoring.view') || user_can('dashboard_bupati.view'),
            'admin'  => true,
            default  => false,
        };
        if (! $boleh) {
            return redirect()->to(base_url('unauthorized'))->with('error', 'Anda tidak memiliki akses ke Perjanjian Kinerja.');
        }

        $svc   = new RuangOpdService();
        $req   = $this->request;
        $opdSesi = $kel === 'opd' ? (int) (session('opd_id') ?? 0) : 0;
        if ($kel === 'opd' && $opdSesi <= 0) {
            return redirect()->to(base_url('adminopd/dashboard'))->with('error', 'Akun Anda belum terhubung ke perangkat daerah.');
        }

        // ---------- saringan
        $tahunList = $svc->tahunPk();
        $kini      = (int) RuangOpdService::kini()->format('Y');
        $tahun     = (int) $req->getGet('tahun');
        if (! in_array($tahun, $tahunList, true)) {
            $tahun = in_array($kini, $tahunList, true) ? $kini : (int) ($tahunList[0] ?? $kini);
        }

        $daftarOpd = $kel === 'opd' ? [] : $svc->daftarOpd();
        $opdId     = null;
        if ($kel === 'opd') {
            $opdId = $opdSesi;
        } else {
            $minta = (int) $req->getGet('opd_id');
            if ($minta > 0 && in_array($minta, array_column($daftarOpd, 'id'), true)) {
                $opdId = $minta;
            } elseif ($minta === 46 || $minta === 212) {
                $opdId = $minta; // unit "Bupati/Kabupaten" pemilik PK Bupati
            }
        }

        // Peran OPD melihat SEMUA PK yang tersimpan di OPD-nya (kecuali PK Bupati) — daftar tidak boleh
        // menyembunyikan dokumen. Dulu jenis disaring menurut NAMA PERAN: kecamatan yang akunnya berperan
        // admin_opd kehilangan PK Camat-nya. PK puncak yang "wajar" (JPT atau Camat) kini ditentukan JENIS
        // OPD sesi; puncak yang tidak wajar (mis. PK JPT tersimpan di kecamatan) tetap tampil bila ada.
        $opdKecamatan = $kel === 'opd' && self::opdKecamatan($opdSesi, $peran);
        $puncakLain   = $opdKecamatan ? 'jpt' : 'camat';
        $jenisBoleh = self::JENIS;
        if ($kel === 'opd') {
            unset($jenisBoleh['bupati']);
        }
        $jenis = (string) $req->getGet('jenis');
        if (! isset($jenisBoleh[$jenis]) && $jenis !== self::JENIS_PEGAWAI) {
            $jenis = '';
        }
        $q = mb_substr(trim((string) $req->getGet('q')), 0, 60);
        // Pihak pertama menurut id (bukan nama: alamat tercatat di log server). Lingkup OPD tetap berlaku
        // untuk peran OPD; peran kabupaten melihat PK orang itu di OPD mana pun (mis. PK Lurah yang
        // disimpan di kecamatan induknya).
        $pegawai = max(0, (int) $req->getGet('pegawai'));

        // ---------- data
        $semua = $svc->daftarPk($opdId, $tahun, ['jenis' => array_keys($jenisBoleh), 'q' => $q, 'pihak_1' => $pegawai]);
        $hitung = array_fill_keys(array_keys($jenisBoleh), 0);
        foreach ($semua as $r) {
            if (isset($hitung[$r['jenis']])) {
                $hitung[$r['jenis']]++;
            }
        }
        // Saringan jenis: puncak yang tidak wajar untuk jenis OPD ini hanya ditawarkan bila memang ada datanya.
        if ($kel === 'opd' && $hitung[$puncakLain] === 0 && $jenis !== $puncakLain) {
            unset($jenisBoleh[$puncakLain], $hitung[$puncakLain]);
        }
        // ---------- PK Pegawai (eKin): hitungan untuk pil jenis, baris bila pil itu dipilih
        $pkPegawai = $this->pkPegawaiEkin($opdId, $tahun, $jenis === self::JENIS_PEGAWAI, $q, (string) $req->getGet('status_pk'), $svc);

        $baris  = $jenis === '' ? $semua : array_values(array_filter($semua, static fn ($r) => $r['jenis'] === $jenis));
        if ($jenis === self::JENIS_PEGAWAI) {
            $baris = $pkPegawai['baris'];
        }
        $total  = count($baris);
        $halMax = max(1, (int) ceil($total / self::PER_HALAMAN));
        $hal    = min($halMax, max(1, (int) $req->getGet('hal')));
        $baris  = array_slice($baris, ($hal - 1) * self::PER_HALAMAN, self::PER_HALAMAN);
        $isi    = $svc->isiPk(array_column($baris, 'id'));

        // ---------- tombol tambah (hanya peran yang memang boleh menambah di rute lama)
        $tambah = [];
        if ($kel === 'opd' && user_can('pk_opd.create')) {
            $tambah = $opdKecamatan
                ? ['kecamatan' => 'PK Camat (Eselon III)', 'administrator' => self::JENIS['administrator'], 'pengawas' => self::JENIS['pengawas']]
                : ['jpt' => self::JENIS['jpt'], 'administrator' => self::JENIS['administrator'], 'pengawas' => self::JENIS['pengawas']];
            $tambah = array_combine(array_map(static fn ($s) => 'adminopd/pk/' . $s . '/tambah', array_keys($tambah)), $tambah);
        } elseif ($peran === 'admin_kab' && user_can('pk_bupati.create')) {
            $tambah = ['adminkab/pk/bupati/tambah' => self::JENIS['bupati']];
        }

        $opdNama = null;
        if ($opdId !== null) {
            $o = $svc->opd($opdId);
            $opdNama = $o['nama_tampil'] ?? RuangOpdService::namaRapi((string) (\Config\Database::connect()->table('opd')->select('nama_opd')->where('id', $opdId)->get()->getRow('nama_opd') ?? ''));
        }

        return view('perjanjian_kinerja/index', [
            'title'      => 'Perjanjian Kinerja · AKSARA+',
            'peran'      => $peran,
            'kel'        => $kel,
            'tahun'      => $tahun,
            'tahunList'  => $tahunList,
            'jenis'      => $jenis,
            'jenisBoleh' => $jenisBoleh,
            'hitung'     => $hitung,
            'opdId'      => $opdId,
            'opdNama'    => $opdNama,
            'daftarOpd'  => $daftarOpd,
            'ruangIds'   => array_column($daftarOpd, 'id'),
            'q'          => $q,
            'pegawai'    => $pegawai,
            'pegawaiNama' => $pegawai > 0 ? ($semua[0]['nama_1'] ?? null) : null,
            'baris'      => $baris,
            'isi'        => $isi,
            'total'      => $total,
            'hal'        => $hal,
            'halMax'     => $halMax,
            'tambah'     => $tambah,
            'periode'    => $svc->periodeUntuk($tahun),
            'pkPegawai'  => $pkPegawai,
            'shellCss'   => view('ruang_opd/_gaya', [], ['saveData' => false, 'debug' => false]),
        ]);
    }

    /**
     * PK Pegawai dari eKin untuk pil "PK Pegawai · eKin". Hitungan dari ringkasan eKin (satu panggilan, tersimpan
     * 5 menit); baris hanya diambil bila pil itu dipilih — per OPD yang punya pegawai di eKin.
     *
     * @return array{ada: bool, pesan: string, jumlah: int, baris: list<array>, hitung: array<string,int>, status: string}
     */
    private function pkPegawaiEkin(?int $opdId, int $tahun, bool $ambilBaris, string $q, string $status, RuangOpdService $svc): array
    {
        $status = isset(self::STATUS_PK_PEGAWAI[$status]) ? $status : '';
        $hasil  = ['ada' => false, 'pesan' => '', 'jumlah' => 0, 'baris' => [], 'hitung' => array_fill_keys(array_keys(self::STATUS_PK_PEGAWAI), 0), 'status' => $status];
        $ekin   = new EkinClient();
        if (! $ekin->terkonfigurasi()) {
            $hasil['pesan'] = 'Sambungan ke eKin belum dikonfigurasi.';

            return $hasil;
        }

        $ringkas = $opdId !== null ? ['opd' => [(string) $opdId => $ekin->ringkasOpd($opdId, $tahun)]] : $ekin->ringkasSemua($tahun);
        if ($ringkas === null || ($opdId !== null && $ringkas['opd'][(string) $opdId] === null && $ekin->alasanTerakhir() !== 'belum_tersedia')) {
            $hasil['pesan'] = $ekin->pesanTerakhir();

            return $hasil;
        }
        $hasil['ada'] = true;
        $opdEkin = [];
        foreach ($ringkas['opd'] ?? [] as $id => $r) {
            $n = 0;
            foreach (array_keys(self::STATUS_PK_PEGAWAI) as $k) {
                $n += (int) ($r['pk_pegawai'][$k] ?? 0);
            }
            if ($n > 0) {
                $opdEkin[] = (int) $id;
            }
            $hasil['jumlah'] += $n;
        }
        if (! $ambilBaris) {
            return $hasil;
        }

        $nama = [];
        foreach ($svc->daftarOpd() as $o) {
            $nama[(int) $o['id']] = $o['nama_tampil'] ?? ($o['nama_opd'] ?? '');
        }
        $qKecil = mb_strtolower($q);
        foreach ($opdEkin as $id) {
            foreach (($ekin->pkPegawaiOpd($id, $tahun)['pk'] ?? []) as $pk) {
                $st = (string) ($pk['status'] ?? '');
                if (! isset(self::STATUS_PK_PEGAWAI[$st])) {
                    continue;   // lewat_aksara (sudah ada di jenjang JPT/Administrator/Pengawas) & belum_ada_skp
                }
                $teks = mb_strtolower(($pk['pegawai']['nama'] ?? '') . ' ' . ($pk['pegawai']['jabatan'] ?? '') . ' ' . ($pk['pihak_kedua']['nama'] ?? ''));
                if ($qKecil !== '' && ! str_contains($teks, $qKecil)) {
                    continue;
                }
                $hasil['hitung'][$st]++;
                if ($status !== '' && $st !== $status) {
                    continue;
                }
                $hasil['baris'][] = $pk + ['opd_id' => $id, 'opd_nama' => $nama[$id] ?? ('OPD #' . $id)];
            }
        }

        return $hasil;
    }

    /**
     * OPD sesi berjenis kecamatan? Dibaca dari opd.jenis; bila kolom/barisnya tidak ada, jatuh ke
     * nama peran (perilaku lama) supaya basis data tanpa kolom jenis tetap bekerja.
     */
    private static function opdKecamatan(int $opdId, string $peran): bool
    {
        $db = \Config\Database::connect();
        if ($opdId > 0 && $db->fieldExists('jenis', 'opd')) {
            $jenis = $db->table('opd')->select('jenis')->where('id', $opdId)->get()->getRow('jenis');
            if ($jenis !== null && $jenis !== '') {
                return $jenis === \App\Models\OpdModel::JENIS_KECAMATAN;
            }
        }

        return $peran === 'admin_kecamatan';
    }
}
