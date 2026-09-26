<?php

namespace App\Services;

use App\Models\OpdModel;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * AKSARA+ — RUANG OPD: satu pintu untuk seluruh dokumen & kinerja satu perangkat daerah.
 *
 * Latar: saat Admin Kabupaten menerima audiensi satu OPD, dokumennya tersebar di
 * tujuh menu, dan setiap menu meminta OPD dipilih ulang. Pembanding (e-SAKIP publik
 * kabupaten lain) menyusun daftar berkas PDF per tahap SAKIP; AKSARA berbasis data,
 * jadi Ruang OPD menampilkan KELENGKAPAN dan ANGKA hidup, bukan sekadar berkas.
 *
 * Satu kelas ini menghitung:
 *   - matriks(): satu baris per OPD, satu sel per dokumen SAKIP (status warna + angka kecil);
 *   - hub():     rincian satu OPD per tahap siklus SAKIP.
 *
 * MENGAPA kueri berkelompok (GROUP BY opd_id), bukan memanggil controller tiap modul:
 * matriks memuat ±45 OPD × 10 dokumen. Memanggil logika halaman per OPD berarti
 * ratusan kueri berat; di sini setiap dokumen = satu kueri untuk semua OPD. Aturan
 * yang DISALIN dari modul lama (dan harus ikut bila modul itu berubah):
 *   - pohon kinerja: simpul Eselon III berjangkar indikator IKU yang belum dihentikan
 *     pada periode yang memuat tahun itu; Eselon IV & Pelaksana lewat es3_indikator_id
 *     (= PemilikKinerjaController::muatPohon / CascadingModel akar IKU);
 *   - kepala OPD: pihak pertama PK jpt/camat tahun itu (bukan opd.id_kepala_opd yang basi);
 *   - capaian IKP: ikp_capaian() apa adanya (= AdminKab\IkpController::rekapLintas).
 *
 * Warna sel: hijau = lengkap, kuning = sebagian, merah = belum ada padahal wajib,
 * abu = tidak berlaku / belum jatuh tempo / sumber tidak tersedia. Skor kelengkapan
 * = rata-rata (hijau 1, kuning ½, merah 0), abu tidak dihitung.
 */
final class RuangOpdService
{
    /** OPD kembar tanpa data (akun, pohon & PK ada di id pasangannya) — sama dengan AdminKab\IkpController. */
    public const OPD_KEMBAR = [13 => 211, 213 => 32];

    public const KELOMPOK = [
        'pd'        => 'Sekretariat, Badan & Dinas',
        'kecamatan' => 'Kecamatan',
        'kelurahan' => 'Kelurahan',
        'lainnya'   => 'Lainnya',
    ];

    /** Tahap siklus SAKIP (urutan tampil di hub). */
    public const TAHAP = [
        'perencanaan' => ['Perencanaan', 'fa-compass-drafting'],
        'pengukuran'  => ['Pengukuran', 'fa-ruler-combined'],
        'pelaporan'   => ['Pelaporan', 'fa-file-lines'],
        'evaluasi'    => ['Evaluasi', 'fa-clipboard-check'],
        'pegawai'     => ['Kinerja Pegawai', 'fa-users'],
    ];

    /** Kolom matriks => [label, tahap, ikon, penjelasan singkat]. */
    public const KOLOM = [
        'renstra'   => ['Renstra', 'perencanaan', 'fa-book', 'Rencana Strategis periode yang memuat tahun ini'],
        'rkt'       => ['Renja/RKT', 'perencanaan', 'fa-calendar-days', 'Rencana Kinerja Tahunan: baris selesai / seluruhnya'],
        'iku'       => ['IKU', 'perencanaan', 'fa-bullseye', 'Indikator Kinerja Utama berjalan & yang sudah bertarget tahun ini'],
        'cascading' => ['Pohon Kinerja', 'perencanaan', 'fa-sitemap', 'Simpul Eselon III s.d. pelaksana & persentase yang sudah berpemilik'],
        'pk'        => ['Perjanjian Kinerja', 'perencanaan', 'fa-file-signature', 'PK JPT/Camat, Administrator, Pengawas tahun ini'],
        'renaksi'   => ['Rencana Aksi', 'pengukuran', 'fa-list-check', 'Indikator PK yang sudah punya target & rencana aksi triwulan'],
        'monev'     => ['MONEV', 'pengukuran', 'fa-chart-line', 'Rencana aksi yang capaian triwulannya sudah diisi'],
        'ikp'       => ['IKP', 'pengukuran', 'fa-gauge-high', 'Kinerja Prioritas: rata-rata capaian s.d. bulan lalu'],
        'lakip'     => ['LAKIP', 'pelaporan', 'fa-file-contract', 'Laporan Kinerja tahun ini (dan tahun lalu)'],
        'ekin'      => ['Pegawai (eKin)', 'pegawai', 'fa-users', 'Pegawai ber-SKP, SKP bulanan dinilai (bulan terakhir yang sudah dinilai), PK pegawai ditandatangani di eKin (+ pejabat yang memakai PK AKSARA)'],
    ];

    /**
     * Sebutan sehari-hari (untuk pencarian "diskominfo", "dinkes", ...). Nama resmi di
     * tabel opd tidak memuat singkatan (kolom singkatan kosong seluruhnya).
     */
    private const SEBUTAN = [
        '/komunikasi dan informatika/i'           => 'Diskominfo Kominfo',
        '/^dinas kesehatan/i'                     => 'Dinkes',
        '/pendidikan dan kebudayaan/i'            => 'Disdikbud',
        '/perencanaan pembangunan/i'              => 'Bappeda Bapperida',
        '/pengelolaan keuangan/i'                 => 'BPKAD',
        '/badan pendapatan/i'                     => 'Bapenda',
        '/kepegawaian/i'                          => 'BKPSDM BKD',
        '/polisi pamong/i'                        => 'Satpol PP',
        '/^dinas sosial/i'                        => 'Dinsos',
        '/pemberdayaan perempuan/i'               => 'DP3AP2KB',
        '/kependudukan dan pencatatan/i'          => 'Disdukcapil Dukcapil',
        '/kepemudaan/i'                           => 'Disporapar Dispora',
        '/koperasi/i'                             => 'Diskoperindag Koperindag',
        '/perhubungan/i'                          => 'Dishub',
        '/pekerjaan umum/i'                       => 'DPUPR PUPR',
        '/^dinas perikanan/i'                     => 'Diskan',
        '/^dinas pertanian/i'                     => 'Distan',
        '/pemberdayaan masyarakat/i'              => 'DPMP DPMD',
        '/lingkungan hidup/i'                     => 'DLH',
        '/ketahanan pangan/i'                     => 'DKP',
        '/penanaman modal/i'                      => 'DPMPTSP Perizinan',
        '/perpustakaan/i'                         => 'Dispusip Perpustakaan',
        '/tenaga kerja/i'                         => 'Disnakertrans Nakertrans',
        '/kesatuan bangsa/i'                      => 'Kesbangpol',
        '/penanggulangan bencana/i'               => 'BPBD',
        '/rumah sakit/i'                          => 'RSUD',
        '/^sekretariat daerah/i'                  => 'Setda Setdakab',
        '/sekretariat dprd/i'                     => 'Setwan DPRD',
    ];

    private $db;
    private IkpRekapService $ikp;

    /** @var array<int, array<string,mixed>>|null */
    private ?array $opdCache = null;

    public function __construct($db = null)
    {
        $this->db  = $db ?: Database::connect();
        $this->ikp = new IkpRekapService($this->db);
        helper(['ikp']);
    }

    // =================================================================
    // DAFTAR OPD, TAHUN, KEPALA
    // =================================================================

    /**
     * OPD yang punya Ruang: bukan EXCLUDED_OPD_IDS, bukan non_opd; kembaran tanpa
     * data disembunyikan. Tiap baris diberi kelompok, urut, nama_tampil, cari.
     *
     * @return array<int, array<string,mixed>> urut kelompok lalu jenis lalu nama
     */
    public function daftarOpd(): array
    {
        if ($this->opdCache !== null) {
            return $this->opdCache;
        }

        $rows = $this->db->table('opd')->select('id, nama_opd, singkatan, jenis')
            ->whereNotIn('id', OpdModel::EXCLUDED_OPD_IDS)
            ->where('jenis !=', OpdModel::JENIS_NON_OPD)
            ->get()->getResultArray();

        $kembar    = array_keys(self::OPD_KEMBAR);
        $punyaData = [];
        foreach (['pk', 'iku_sasaran', 'cascading_sasaran_opd', 'ikp'] as $t) {
            if (! $this->db->tableExists($t)) {
                continue;
            }
            foreach ($this->db->table($t)->select('opd_id')->distinct()->whereIn('opd_id', $kembar)->get()->getResultArray() as $r) {
                $punyaData[(int) $r['opd_id']] = true;
            }
        }

        $hasil = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (isset(self::OPD_KEMBAR[$id]) && ! isset($punyaData[$id])) {
                continue;
            }
            $r['id']          = $id;
            $r['kelompok']    = self::kelompok((string) $r['jenis']);
            $r['nama_tampil'] = self::namaRapi((string) $r['nama_opd']);
            $r['sebutan']     = self::sebutan((string) $r['nama_opd']);
            $r['urut']        = [array_search($r['kelompok'], array_keys(self::KELOMPOK), true), self::urutJenisPd($r['nama_tampil']), $r['nama_tampil']];
            $hasil[] = $r;
        }
        usort($hasil, static fn ($a, $b) => $a['urut'] <=> $b['urut']);

        return $this->opdCache = $hasil;
    }

    /** Satu OPD dari daftar Ruang, atau null (id di luar daftar = tidak punya Ruang). */
    public function opd(int $id): ?array
    {
        foreach ($this->daftarOpd() as $o) {
            if ($o['id'] === $id) {
                return $o;
            }
        }

        return null;
    }

    /**
     * Apakah OPD ini tercakup rekap IKP kabupaten (adminkab/ikp/opd/{id} dan bupati/ikp/opd/{id})?
     *
     * MENGAPA disalin dari AdminKab\IkpController::daftarOpd, bukan ditebak dari warna sel: rekap itu
     * hanya menerima jenis opd/kecamatan, dan kembaran (OPD_KEMBAR) hanya bila punya IKP. Kelurahan
     * & UPT ada di Ruang OPD tetapi tidak di rekap IKP — tombol ke sana berakhir 404.
     */
    public function ikpKabBerlaku(int $opdId): bool
    {
        $o = $this->opd($opdId);
        if ($o === null || ! in_array($o['jenis'], [OpdModel::JENIS_OPD, OpdModel::JENIS_KECAMATAN], true)) {
            return false;
        }
        if (! isset(self::OPD_KEMBAR[$opdId])) {
            return true;
        }

        return $this->db->tableExists('ikp')
            && $this->db->table('ikp')->where('opd_id', $opdId)->where('dihapus_pada', null)->countAllResults() > 0;
    }

    /**
     * Label jenjang pohon kinerja menurut jenis unit. Satu sumber untuk Ruang OPD dan halaman
     * Pemilik Kinerja (PemilikKinerjaController::labelLevel memanggil ini).
     *
     * MENGAPA berbeda untuk kecamatan: Camat sendiri Eselon III (simpul es2), sehingga simpul es3 di
     * kecamatan = Eselon IV (Sekcam/Kasi) dan es4 = pelaksana/JF. Label dinas dipakai di kecamatan
     * membuat hub dan halaman yang dibuka tombolnya menyebut jenjang yang berbeda.
     *
     * @return array{es2:string, es3:string, es4:string, pelaksana:string, pk_es2:string}
     */
    public static function labelJenjang(bool $kecamatan): array
    {
        return $kecamatan
            ? ['es2' => 'Eselon III (Camat)', 'es3' => 'Eselon IV', 'es4' => 'Pelaksana / JF', 'pelaksana' => 'Staf Pelaksana',
               'pk_es2' => 'PK Camat']
            : ['es2' => 'Eselon II', 'es3' => 'Eselon III', 'es4' => 'Eselon IV / JF', 'pelaksana' => 'Pelaksana',
               'pk_es2' => 'PK JPT'];
    }

    public static function kelompok(string $jenis): string
    {
        return match ($jenis) {
            OpdModel::JENIS_OPD       => 'pd',
            OpdModel::JENIS_KECAMATAN => 'kecamatan',
            OpdModel::JENIS_KELURAHAN => 'kelurahan',
            default                   => 'lainnya',
        };
    }

    /** Urutan di dalam kelompok perangkat daerah: Sekretariat, Inspektorat, Badan, Dinas, lain-lain. */
    public static function urutJenisPd(string $nama): int
    {
        $n = mb_strtolower($nama);

        return match (true) {
            str_starts_with($n, 'sekretariat') => 0,
            str_starts_with($n, 'inspektorat') => 1,
            str_starts_with($n, 'badan')       => 2,
            str_starts_with($n, 'dinas')       => 3,
            default                            => 4,
        };
    }

    /** Nama yang tersimpan HURUF BESAR semua dirapikan jadi Huruf Judul (yang lain dibiarkan). */
    public static function namaRapi(string $nama): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama));
        if ($nama === '' || mb_strtoupper($nama) !== $nama) {
            return $nama;
        }
        $kata = explode(' ', mb_strtolower($nama));
        foreach ($kata as $i => $k) {
            $kata[$i] = ($i > 0 && in_array($k, ['dan', 'dan,', 'di', 'ke', 'dari'], true)) ? $k : mb_convert_case($k, MB_CASE_TITLE);
        }

        return implode(' ', $kata);
    }

    public static function sebutan(string $nama): string
    {
        foreach (self::SEBUTAN as $pola => $s) {
            if (preg_match($pola, $nama)) {
                return $s;
            }
        }

        return '';
    }

    /** Tahun yang bisa dipilih: tahun periode RPJMD aktif. */
    public function daftarTahun(): array
    {
        return $this->ikp->tahunPeriode();
    }

    /** Tahun bawaan: tahun kalender WIB bila ada di periode, selain itu dijepit ke periode. */
    public function tahunDari($minta): int
    {
        $daftar = $this->daftarTahun();
        $t      = (int) $minta;
        if (in_array($t, $daftar, true)) {
            return $t;
        }
        $kini = (int) self::kini()->format('Y');

        return in_array($kini, $daftar, true) ? $kini : (int) max(min($kini, max($daftar)), min($daftar));
    }

    public static function kini(): DateTimeImmutable
    {
        // MENGAPA WIB: AKSARA berjalan UTC; bulan & tahun pengguna berganti 7 jam lebih awal.
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    }

    /** Periode RPJMD (string 'awal-akhir') yang memuat tahun — parameter halaman Cascading. */
    public function periodeUntuk(int $tahun): string
    {
        $p = $this->ikp->periodeAktif();
        if ($tahun >= $p['awal'] && $tahun <= $p['akhir']) {
            return $p['awal'] . '-' . $p['akhir'];
        }
        $r = $this->db->table('rpjmd_misi')->select('tahun_mulai, tahun_akhir')
            ->where('tahun_mulai <=', $tahun)->where('tahun_akhir >=', $tahun)
            ->limit(1)->get()->getRowArray();

        return $r ? ((int) $r['tahun_mulai'] . '-' . (int) $r['tahun_akhir']) : ($p['awal'] . '-' . $p['akhir']);
    }

    /**
     * Kepala tiap OPD menurut PK jpt/camat tahun itu: pihak pertama PK terbaru, dengan
     * jabatan puncak didahulukan dari Asisten/Staf Ahli (Setda memuat banyak PK JPT).
     *
     * @return array<int, array{nama:string, jabatan:string, status:string, pk_id:int, jenis:string}>
     */
    public function kepalaPerOpd(int $tahun): array
    {
        $baris = $this->db->table('pk k')
            ->select('k.id, k.opd_id, k.jenis, k.tanggal, k.is_plt_pihak_1, k.is_plh_pihak_1, k.jabatan_pihak_1_manual, p.nama_pegawai, j.nama_jabatan')
            ->join('pegawai p', 'p.id = k.pihak_1', 'left')
            ->join('jabatan j', 'j.id = p.jabatan_id', 'left')
            ->where('k.tahun', $tahun)->whereIn('k.jenis', ['jpt', 'camat'])->where('k.pihak_1 >', 0)
            ->get()->getResultArray();

        $pilih = [];
        foreach ($baris as $r) {
            if (($r['nama_pegawai'] ?? '') === '') {
                continue;
            }
            $jab   = trim((string) (($r['jabatan_pihak_1_manual'] ?? '') !== '' ? $r['jabatan_pihak_1_manual'] : ($r['nama_jabatan'] ?? '')));
            $skor  = [preg_match('/^(asisten|staf ahli)/i', $jab) ? 0 : 1, (string) $r['tanggal'], (int) $r['id']];
            $opd   = (int) $r['opd_id'];
            if (! isset($pilih[$opd]) || $skor > $pilih[$opd]['_skor']) {
                $pilih[$opd] = [
                    '_skor'   => $skor,
                    'nama'    => (string) $r['nama_pegawai'],
                    'jabatan' => $jab,
                    'status'  => self::statusPejabat((int) $r['is_plt_pihak_1'], (int) $r['is_plh_pihak_1']),
                    'pk_id'   => (int) $r['id'],
                    'jenis'   => (string) $r['jenis'],
                ];
            }
        }
        foreach ($pilih as &$p) {
            unset($p['_skor']);
        }

        return $pilih;
    }

    public static function statusPejabat(int $plt, int $plh): string
    {
        return $plt === 1 ? 'Plt.' : ($plh === 1 ? 'Plh.' : '');
    }

    // =================================================================
    // MATRIKS
    // =================================================================

    /**
     * @param int[]                       $opdIds
     * @param array<string,mixed>|null     $ekinRingkas hasil EkinClient::ringkasSemua() (null = eKin tak tersedia)
     *
     * @return array<int, array<string, array>> [opd_id => [kolom => sel]]
     */
    public function matriks(int $tahun, array $opdIds, ?array $ekinRingkas, string $alasanEkin = ''): array
    {
        $opdIds = array_values(array_unique(array_map('intval', $opdIds)));
        if ($opdIds === []) {
            return [];
        }
        $kel = [];
        foreach ($this->daftarOpd() as $o) {
            $kel[$o['id']] = $o['kelompok'];
        }

        $renstra = $this->angkaRenstra($tahun, $opdIds);
        $rkt     = $this->angkaRkt($tahun, $opdIds);
        $iku     = $this->angkaIku($tahun, $opdIds);
        $casc    = $this->angkaCascading($tahun, $opdIds);
        $pk      = $this->angkaPk($tahun, $opdIds);
        $renaksi = $this->angkaRenaksi($tahun, $opdIds);
        $lakip   = $this->angkaLakip($tahun, $opdIds);
        $ikp     = $this->angkaIkp($tahun, $opdIds);
        $wajibTw = self::triwulanWajib($tahun);
        $bulanWajib = self::bulanLalu($tahun);

        $hasil = [];
        foreach ($opdIds as $id) {
            $wajib = in_array($kel[$id] ?? 'lainnya', ['pd', 'kecamatan'], true);
            $hasil[$id] = [
                'renstra'   => self::selRenstra($renstra[$id] ?? null, $wajib),
                'rkt'       => self::selRkt($rkt[$id] ?? null, $wajib),
                'iku'       => self::selIku($iku[$id] ?? null, $wajib),
                'cascading' => self::selCascading($casc[$id] ?? null, $wajib, ($kel[$id] ?? '') === 'kecamatan'),
                'pk'        => self::selPk($pk[$id] ?? [], $wajib, ($kel[$id] ?? '') === 'kecamatan'),
                'renaksi'   => self::selRenaksi($renaksi[$id] ?? null, $wajib),
                'monev'     => self::selMonev($renaksi[$id] ?? null, $wajibTw, $wajib),
                'ikp'       => self::selIkp($ikp[$id] ?? null, $wajib),
                'lakip'     => self::selLakip($lakip[$id] ?? [], $tahun, $wajib),
                'ekin'      => self::selEkin($ekinRingkas === null ? null : ($ekinRingkas['opd'][(string) $id] ?? $ekinRingkas['opd'][$id] ?? false), $alasanEkin, $bulanWajib),
            ];
            // Unit yang tidak wajib menyusun dokumen SAKIP sendiri (kelurahan, UPT, ...) tidak diberi skor:
            // dulu satu sel berwarna saja sudah memberi 100% dan menempatkannya di puncak urutan.
            $hasil[$id]['_skor'] = $wajib ? self::skor($hasil[$id]) : null;
        }

        return $hasil;
    }

    /**
     * Kolom yang TIDAK ikut skor kelengkapan dokumen SAKIP. MENGAPA eKin di luar: ia kinerja pegawai
     * (sistem lain) dan baru sebagian unit yang pegawainya dimuat — ikut dihitung berarti unit percontohan
     * eKin mendapat tambahan nilai yang tidak ada hubungannya dengan kelengkapan dokumen SAKIP-nya.
     */
    public const KOLOM_BUKAN_SKOR = ['ekin'];

    /** Skor kelengkapan dokumen SAKIP 0–100 dari sel berwarna (abu & KOLOM_BUKAN_SKOR tidak dihitung); null bila tak ada. */
    public static function skor(array $sel): ?int
    {
        $nilai = ['hijau' => 1.0, 'kuning' => 0.5, 'merah' => 0.0];
        $n     = [];
        foreach ($sel as $k => $s) {
            if ($k[0] !== '_' && ! in_array($k, self::KOLOM_BUKAN_SKOR, true) && isset($nilai[$s['s'] ?? ''])) {
                $n[] = $nilai[$s['s']];
            }
        }

        return $n === [] ? null : (int) round(array_sum($n) / count($n) * 100);
    }

    /** Ringkasan per kolom untuk seluruh baris: [kolom => [hijau, kuning, merah, abu]]. */
    public static function ringkasKolom(array $matriks): array
    {
        $r = [];
        foreach (array_keys(self::KOLOM) as $k) {
            $r[$k] = ['hijau' => 0, 'kuning' => 0, 'merah' => 0, 'abu' => 0];
        }
        foreach ($matriks as $baris) {
            foreach (array_keys(self::KOLOM) as $k) {
                $s = $baris[$k]['s'] ?? 'abu';
                $r[$k][$s] = ($r[$k][$s] ?? 0) + 1;
            }
        }

        return $r;
    }

    /**
     * Triwulan yang sudah LEWAT (wajib ada capaiannya) pada tahun itu menurut kalender WIB.
     * 26 September 2026 -> 2 (TW III baru berakhir 30 September).
     */
    public static function triwulanWajib(int $tahun, ?DateTimeImmutable $kini = null): int
    {
        $kini ??= self::kini();
        $th = (int) $kini->format('Y');
        if ($tahun < $th) {
            return 4;
        }
        if ($tahun > $th) {
            return 0;
        }

        return intdiv((int) $kini->format('n') - 1, 3);
    }

    /** Bulan terakhir yang sudah lewat untuk capaian IKP "s.d. bulan lalu" (0 = belum ada). */
    public static function bulanLalu(int $tahun, ?DateTimeImmutable $kini = null): int
    {
        $kini ??= self::kini();
        $th = (int) $kini->format('Y');

        return $tahun < $th ? 12 : ($tahun > $th ? 0 : (int) $kini->format('n') - 1);
    }

    // ---------------------------------------------------------------- sel

    private static function sel(string $s, string $t, string $k = '', string $j = '', ?float $nilai = null): array
    {
        return ['s' => $s, 't' => $t, 'k' => $k, 'j' => $j, 'n' => $nilai];
    }

    private static function kosong(bool $wajib, string $j = ''): array
    {
        return $wajib ? self::sel('merah', 'Belum ada', '', $j) : self::sel('abu', '–', '', $j !== '' ? $j : 'Tidak berlaku untuk unit ini');
    }

    public static function selRenstra(?array $a, bool $wajib): array
    {
        if (! $a || (int) $a['n'] === 0) {
            return self::kosong($wajib, 'Belum ada sasaran Renstra yang memuat tahun ini');
        }
        $periode = (int) $a['awal'] . '–' . (int) $a['akhir'];
        $k       = (int) $a['n'] . ' sasaran · ' . (int) $a['ind'] . ' indikator';
        if ((int) $a['selesai'] === (int) $a['n']) {
            return self::sel('hijau', $periode, $k, 'Renstra ' . $periode . ' final', 1.0);
        }

        return self::sel('kuning', $periode, $k . ' · ' . ((int) $a['n'] - (int) $a['selesai']) . ' draf', 'Sebagian sasaran masih draf', 0.5);
    }

    public static function selRkt(?array $a, bool $wajib): array
    {
        if (! $a || (int) $a['n'] === 0) {
            return self::kosong($wajib, 'Belum ada Renja/RKT tahun ini');
        }
        $n = (int) $a['n'];
        $s = (int) $a['selesai'];

        return self::sel($s === $n ? 'hijau' : 'kuning', $s . '/' . $n, 'baris selesai', $s === $n ? 'Seluruh baris RKT selesai' : ($n - $s) . ' baris RKT masih draf', $s / $n);
    }

    public static function selIku(?array $a, bool $wajib): array
    {
        if (! $a || (int) $a['ind'] === 0) {
            return self::kosong($wajib, 'Belum ada IKU berjalan untuk tahun ini');
        }
        $ind = (int) $a['ind'];
        $bt  = (int) $a['bertarget'];

        return self::sel($bt === $ind ? 'hijau' : 'kuning', $ind . ' indikator', $bt === $ind ? 'semua bertarget' : $bt . ' bertarget',
            $bt === $ind ? 'Semua indikator IKU punya target tahun ini' : ($ind - $bt) . ' indikator IKU belum bertarget tahun ini', $bt / $ind);
    }

    public static function selCascading(?array $a, bool $wajib, bool $kecamatan = false): array
    {
        $lbl    = self::labelJenjang($kecamatan);
        $simpul = 0;
        $milik  = 0;
        foreach (['es3', 'es4', 'pelaksana'] as $l) {
            $simpul += (int) ($a[$l]['simpul'] ?? 0);
            $milik  += (int) ($a[$l]['berpemilik'] ?? 0);
        }
        if ($simpul === 0) {
            return self::kosong($wajib, 'Belum ada simpul pohon kinerja di bawah IKU');
        }
        $p = $milik / $simpul;
        $pel = (int) ($a['pelaksana']['simpul'] ?? 0);

        return self::sel($p >= 0.999 ? 'hijau' : 'kuning', $simpul . ' simpul',
            (int) round($p * 100) . '% berpemilik',
            $lbl['es3'] . ' ' . (int) ($a['es3']['simpul'] ?? 0) . ', ' . $lbl['es4'] . ' ' . (int) ($a['es4']['simpul'] ?? 0)
            . ', ' . mb_strtolower($lbl['pelaksana']) . ' ' . $pel
            . ' · ' . $milik . ' simpul sudah punya pemilik', $p);
    }

    public static function selPk(array $a, bool $wajib, bool $kecamatan): array
    {
        $puncak = (int) ($a['jpt'] ?? 0) + (int) ($a['camat'] ?? 0);
        $adm    = (int) ($a['administrator'] ?? 0);
        $pgw    = (int) ($a['pengawas'] ?? 0);
        if ($puncak + $adm + $pgw === 0) {
            return self::kosong($wajib, 'Belum ada Perjanjian Kinerja tahun ini');
        }
        $label = $kecamatan ? 'Camat' : 'JPT';
        $t     = $puncak > 0 ? $label . ' ✓' : $label . ' belum';
        $k     = $adm . ' adm · ' . $pgw . ' pgw';
        $s     = $puncak > 0 && $adm > 0 && $pgw > 0 ? 'hijau' : ($puncak > 0 ? 'kuning' : 'merah');

        return self::sel($s, $t, $k, 'PK ' . $label . ': ' . $puncak . ' · Administrator: ' . $adm . ' · Pengawas: ' . $pgw,
            $s === 'hijau' ? 1.0 : ($s === 'kuning' ? 0.5 : 0.0));
    }

    public static function selRenaksi(?array $a, bool $wajib): array
    {
        $ind = (int) ($a['ind'] ?? 0);
        if ($ind === 0) {
            return $wajib ? self::sel('abu', '–', '', 'Belum ada indikator PK tahun ini') : self::kosong(false);
        }
        $ada = (int) $a['ind_tr'];
        $p   = $ada / $ind;

        return self::sel($ada === $ind ? 'hijau' : ($ada > 0 ? 'kuning' : 'merah'), (int) round($p * 100) . '%',
            $ada . '/' . $ind . ' indikator', $ada . ' dari ' . $ind . ' indikator PK sudah punya rencana aksi', $p);
    }

    public static function selMonev(?array $a, int $wajibTw, bool $wajib): array
    {
        $tr = (int) ($a['tr'] ?? 0);
        if ($tr === 0) {
            return $wajib ? self::sel('abu', '–', '', 'Belum ada rencana aksi untuk dimonitor') : self::kosong(false);
        }
        $isi = [];
        for ($k = 1; $k <= 4; $k++) {
            $isi[$k] = (int) ($a['tw' . $k] ?? 0) / $tr;
        }
        if ($wajibTw === 0) {
            return self::sel('abu', 'Belum', 'jatuh tempo', 'Belum ada triwulan yang berakhir', null) + ['tw' => $isi];
        }
        $rata = array_sum(array_slice($isi, 0, $wajibTw)) / $wajibTw;
        $romawi = ['', 'I', 'II', 'III', 'IV'][$wajibTw];

        return self::sel($rata >= 0.999 ? 'hijau' : ($rata > 0 ? 'kuning' : 'merah'), (int) round($rata * 100) . '%',
            's.d. TW ' . $romawi, 'Capaian triwulan I' . ($wajibTw > 1 ? '–' . $romawi : '') . ' terisi rata-rata ' . (int) round($rata * 100) . '%', $rata)
            + ['tw' => $isi];
    }

    public static function selIkp(?array $a, bool $wajib): array
    {
        $n = (int) ($a['jumlah'] ?? 0);
        if ($n === 0) {
            return $wajib ? self::sel('merah', 'Belum ada', '', 'Belum ada IKP terdaftar') : self::kosong(false);
        }
        $bulan = (int) ($a['bulan'] ?? 0);
        if ($a['rata'] === null) {
            return self::sel('kuning', $n . ' IKP', 'belum ada capaian', $bulan > 0 ? 'Belum ada realisasi yang bisa dinilai s.d. ' . ikp_nama_bulan($bulan) : 'Belum ada bulan yang berakhir');
        }
        $rata = (float) $a['rata'];
        $warna = (string) ($a['kelompok'] ?? 'abu');

        return self::sel(in_array($warna, ['hijau', 'kuning', 'merah'], true) ? $warna : 'abu', ikp_fmt($rata, 1) . '%',
            $n . ' IKP · s.d. ' . ikp_nama_bulan($bulan, true), 'Rata-rata capaian ' . $n . ' IKP s.d. ' . ikp_nama_bulan($bulan) . ': ' . ikp_fmt($rata, 2) . '%', min(1.0, $rata / 100));
    }

    public static function selLakip(array $a, int $tahun, bool $wajib): array
    {
        $ini  = $a[$tahun] ?? null;
        $lalu = $a[$tahun - 1] ?? null;
        $teksLalu = static function (?array $x): string {
            if (! $x) {
                return 'belum ada';
            }
            if (($x['pengesahan'] ?? '') === 'disahkan') {
                return 'disahkan';
            }

            return (int) $x['selesai'] === (int) $x['n'] ? 'selesai' : 'draf';
        };
        $k = ($tahun - 1) . ': ' . $teksLalu($lalu);

        if ($ini && ($ini['pengesahan'] ?? '') === 'disahkan') {
            return self::sel('hijau', 'Disahkan', $k, 'LAKIP ' . $tahun . ' sudah disahkan', 1.0);
        }
        $belumJatuhTempo = $tahun >= (int) self::kini()->format('Y');
        if ($ini && (int) $ini['n'] > 0) {
            $s = (int) $ini['selesai'];
            $n = (int) $ini['n'];
            // LAKIP tahun berjalan yang sudah MULAI disusun belum jatuh tempo: abu (tidak ikut skor), bukan
            // kuning. Kuning (½) justru membuat OPD yang rajin menyusun lebih awal turun skornya dibanding
            // OPD yang belum mulai sama sekali (abu). Yang sudah selesai tetap hijau — tidak pernah menurunkan skor.
            if ($belumJatuhTempo && $s < $n) {
                return self::sel('abu', 'Disusun ' . $s . '/' . $n, $k,
                    'LAKIP ' . $tahun . ' sedang disusun (' . $s . ' dari ' . $n . ' baris selesai) — jatuh tempo awal ' . ($tahun + 1));
            }

            return self::sel($s === $n ? 'hijau' : 'kuning', $s === $n ? 'Selesai' : 'Draf ' . $s . '/' . $n, $k,
                'LAKIP ' . $tahun . ': ' . $s . ' dari ' . $n . ' baris selesai', $s / $n);
        }
        // LAKIP tahun berjalan baru disusun awal tahun berikutnya: belum jatuh tempo.
        if ($belumJatuhTempo) {
            return self::sel('abu', 'Belum jatuh tempo', $k, 'LAKIP ' . $tahun . ' disusun awal ' . ($tahun + 1));
        }

        return self::kosong($wajib, 'Belum ada LAKIP ' . $tahun);
    }

    /**
     * @param array|false|null $r          RINGKAS eKin; null = eKin tidak tersedia, false = OPD tidak ada di eKin
     * @param int              $bulanWajib bulan terakhir yang SKP bulanannya sudah jatuh tempo dinilai (bulanLalu();
     *                                     0 = abaikan). Warna hijau menuntut bulan itu sudah dinilai.
     */
    public static function selEkin($r, string $alasan = '', int $bulanWajib = 0): array
    {
        if ($r === null) {
            return self::sel('abu', '–', '', $alasan !== '' ? $alasan : 'Data eKin belum tersedia');
        }
        if ($r === false || ! is_array($r)) {
            return self::sel('abu', '–', 'belum di eKin', 'Pegawai perangkat daerah ini belum dimuat di eKin');
        }
        $total = (int) ($r['pegawai']['total'] ?? 0);
        if ($total === 0) {
            return self::sel('abu', '–', 'tanpa pegawai', 'Belum ada pegawai di eKin');
        }
        $skp = (int) ($r['pegawai']['ber_skp'] ?? 0);
        $p   = $skp / $total;
        $nl  = self::ekinDinilai($r);
        $ttd = (int) ($r['pk_pegawai']['ditandatangani'] ?? 0);
        $pkA = (int) ($r['pk_pegawai']['lewat_aksara'] ?? 0);

        // Penilaian tertinggal = bulan jatuh tempo belum dinilai, atau baru sebagian kecil pegawai ber-SKP.
        $tertinggal = $bulanWajib > 0 && $skp > 0
            && ($nl['bulan'] < $bulanWajib || $nl['dinilai'] < 0.9 * $skp);
        $s = $p >= 0.9 && ! $tertinggal ? 'hijau' : ($p > 0 ? 'kuning' : 'merah');

        $bln  = $nl['bulan'] > 0 ? ikp_nama_bulan($nl['bulan'], true) . ' ' : '';
        $blnP = $nl['bulan'] > 0 ? ikp_nama_bulan($nl['bulan']) : 'bulan terakhir';
        $j    = $skp . ' dari ' . $total . ' pegawai ber-SKP'
            . ' · SKP bulanan ' . $blnP . ' dinilai ' . $nl['dinilai'] . ' dari ' . $skp
            . ($tertinggal && $nl['bulan'] < $bulanWajib ? ' (penilaian ' . ikp_nama_bulan($bulanWajib) . ' belum ada)' : '')
            . ' · PK pegawai ditandatangani di eKin ' . $ttd
            . ($pkA > 0 ? ' · ' . $pkA . ' pejabat struktural memakai dokumen PK AKSARA' : '');

        // Tanpa pegawai ber-SKP, "dinilai 0/0" tidak berarti apa-apa: sebut saja belum ada SKP.
        $k = ($skp > 0 ? 'dinilai ' . $bln . $nl['dinilai'] . '/' . $skp . ' · PK ' . $ttd : 'belum ada SKP')
            . ($pkA > 0 ? ' +' . $pkA . ' PK AKSARA' : '');

        return self::sel($s, (int) round($p * 100) . '% SKP', $k, $j, $p);
    }

    /**
     * Bulan terakhir yang SKP bulanannya sudah dinilai, dan berapa pegawai yang dinilai pada bulan itu.
     *
     * MENGAPA bukan bulanan.bulan/bulanan.dinilai begitu saja: `bulanan.bulan` = bulan terakhir yang PUNYA
     * SKP bulanan — di tengah bulan itu bulan berjalan (mis. September) yang baru berisi draf/diajukan, jadi
     * `dinilai` hampir selalu 0, padahal Agustus sudah dinilai semua. eKin mengirim `predikat_bulan` (kunci
     * tambahan) = bulan terakhir yang sudah dinilai, dan `predikat` = sebaran pegawai yang dinilai bulan itu.
     * eKin lama tanpa kunci itu: jatuh ke bulanan.bulan/dinilai.
     *
     * @return array{bulan:int, dinilai:int}
     */
    public static function ekinDinilai(array $r): array
    {
        $bulan = (int) ($r['bulanan']['bulan'] ?? 0);
        $pb    = (int) ($r['predikat_bulan'] ?? 0);
        if ($pb > 0 && $pb !== $bulan) {
            return ['bulan' => $pb, 'dinilai' => array_sum(array_map('intval', (array) ($r['predikat'] ?? [])))];
        }

        return ['bulan' => $bulan, 'dinilai' => (int) ($r['bulanan']['dinilai'] ?? 0)];
    }

    // ---------------------------------------------------------------- angka mentah

    /** @return array<int, array{n:int, selesai:int, awal:int, akhir:int, ind:int}> */
    public function angkaRenstra(int $tahun, array $opdIds): array
    {
        $rows = $this->db->query(
            'SELECT rs.opd_id, COUNT(DISTINCT rs.id) n, COUNT(DISTINCT CASE WHEN rs.status = \'selesai\' THEN rs.id END) selesai,
                    MIN(rs.tahun_mulai) awal, MAX(rs.tahun_akhir) akhir, COUNT(DISTINCT ris.id) ind
             FROM renstra_sasaran rs
             LEFT JOIN renstra_indikator_sasaran ris ON ris.renstra_sasaran_id = rs.id AND ris.dihentikan_pada IS NULL
             WHERE rs.opd_id IN ? AND rs.tahun_mulai <= ? AND rs.tahun_akhir >= ? AND rs.dihentikan_pada IS NULL
             GROUP BY rs.opd_id',
            [$opdIds, $tahun, $tahun]
        )->getResultArray();

        return array_column($rows, null, 'opd_id');
    }

    /** @return array<int, array{n:int, selesai:int}> */
    public function angkaRkt(int $tahun, array $opdIds): array
    {
        $rows = $this->db->query(
            'SELECT opd_id, COUNT(*) n, SUM(status = \'selesai\') selesai FROM rkt WHERE tahun = ? AND opd_id IN ? GROUP BY opd_id',
            [$tahun, $opdIds]
        )->getResultArray();

        return array_column($rows, null, 'opd_id');
    }

    /** @return array<int, array{ind:int, bertarget:int}> */
    public function angkaIku(int $tahun, array $opdIds): array
    {
        $rows = $this->db->query(
            'SELECT iks.opd_id, COUNT(DISTINCT iki.id) ind,
                    COUNT(DISTINCT CASE WHEN NULLIF(TRIM(it.target), \'\') IS NOT NULL THEN iki.id END) bertarget
             FROM iku_sasaran iks
             JOIN iku_indikator iki ON iki.iku_sasaran_id = iks.id AND iki.dihentikan_pada IS NULL
             LEFT JOIN iku_target it ON it.iku_indikator_id = iki.id AND it.tahun = ?
             WHERE iks.opd_id IN ? AND iks.tahun_mulai <= ? AND iks.tahun_akhir >= ?
             GROUP BY iks.opd_id',
            [$tahun, $opdIds, $tahun, $tahun]
        )->getResultArray();

        return array_column($rows, null, 'opd_id');
    }

    /**
     * Statistik pohon kinerja per OPD & jenjang: simpul, berpemilik, indikator, bertarget.
     *
     * @return array<int, array<string, array{simpul:int, berpemilik:int, indikator:int, bertarget:int}>>
     */
    public function angkaCascading(int $tahun, array $opdIds): array
    {
        $ikuOpd = [];
        foreach ($this->db->query(
            'SELECT iki.id, iks.opd_id FROM iku_indikator iki
             JOIN iku_sasaran iks ON iks.id = iki.iku_sasaran_id
             WHERE iki.dihentikan_pada IS NULL AND iks.tahun_mulai <= ? AND iks.tahun_akhir >= ? AND iks.opd_id IN ?',
            [$tahun, $tahun, $opdIds]
        )->getResultArray() as $r) {
            $ikuOpd[(int) $r['id']] = (int) $r['opd_id'];
        }
        if ($ikuOpd === []) {
            return [];
        }

        $simpul = $this->db->table('cascading_sasaran_opd')
            ->select('id, opd_id, level, iku_indikator_id, es3_indikator_id')->get()->getResultArray();
        $indBySimpul = [];
        foreach ($this->db->table('cascading_indikator_opd')->select('id, cascading_sasaran_id')->get()->getResultArray() as $r) {
            $indBySimpul[(int) $r['cascading_sasaran_id']][] = (int) $r['id'];
        }
        $bertarget = [];
        if ($this->db->tableExists('cascading_indikator_target')) {
            foreach ($this->db->table('cascading_indikator_target')->select('cascading_indikator_id')
                ->where('tahun', $tahun)
                ->groupStart()->where('target IS NOT NULL', null, false)->orWhere("NULLIF(TRIM(target_teks), '') IS NOT NULL", null, false)->groupEnd()
                ->get()->getResultArray() as $r) {
                $bertarget[(int) $r['cascading_indikator_id']] = true;
            }
        }
        $berpemilik = [];
        if ($this->db->tableExists('cascading_pemilik')) {
            foreach ($this->db->table('cascading_pemilik')->select('cascading_sasaran_id')->distinct()
                ->where('tahun', $tahun)->get()->getResultArray() as $r) {
                $berpemilik[(int) $r['cascading_sasaran_id']] = true;
            }
        }

        $perLevel = ['es3' => [], 'es4' => [], 'pelaksana' => []];
        foreach ($simpul as $s) {
            if (isset($perLevel[$s['level']])) {
                $perLevel[$s['level']][] = $s;
            }
        }

        $hasil  = [];
        $indOpd = []; // indikator jenjang di atas yang sah => opd pemilik pohon
        $catat  = function (int $opd, string $level, int $sid) use (&$hasil, $indBySimpul, $bertarget, $berpemilik, &$indOpd): void {
            $hasil[$opd][$level] ??= ['simpul' => 0, 'berpemilik' => 0, 'indikator' => 0, 'bertarget' => 0];
            $hasil[$opd][$level]['simpul']++;
            if (isset($berpemilik[$sid])) {
                $hasil[$opd][$level]['berpemilik']++;
            }
            foreach ($indBySimpul[$sid] ?? [] as $iid) {
                $hasil[$opd][$level]['indikator']++;
                if (isset($bertarget[$iid])) {
                    $hasil[$opd][$level]['bertarget']++;
                }
                $indOpd[$level][$iid] = $opd;
            }
        };

        foreach ($perLevel['es3'] as $s) {
            $iku = (int) $s['iku_indikator_id'];
            if (isset($ikuOpd[$iku]) && $ikuOpd[$iku] === (int) $s['opd_id']) {
                $catat((int) $s['opd_id'], 'es3', (int) $s['id']);
            }
        }
        foreach ($perLevel['es4'] as $s) {
            $induk = (int) $s['es3_indikator_id'];
            if (isset($indOpd['es3'][$induk])) {
                $catat($indOpd['es3'][$induk], 'es4', (int) $s['id']);
            }
        }
        foreach ($perLevel['pelaksana'] as $s) {
            $induk = (int) $s['es3_indikator_id'];
            if (isset($indOpd['es4'][$induk])) {
                $catat($indOpd['es4'][$induk], 'pelaksana', (int) $s['id']);
            }
        }

        return $hasil;
    }

    /** @return array<int, array<string,int>> [opd => [jenis => jumlah]] */
    public function angkaPk(int $tahun, array $opdIds): array
    {
        $hasil = [];
        foreach ($this->db->query(
            'SELECT opd_id, jenis, COUNT(*) n FROM pk WHERE tahun = ? AND opd_id IN ? GROUP BY opd_id, jenis',
            [$tahun, $opdIds]
        )->getResultArray() as $r) {
            $hasil[(int) $r['opd_id']][(string) $r['jenis']] = (int) $r['n'];
        }

        return $hasil;
    }

    /**
     * Indikator PK OPD (jpt/camat/administrator/pengawas), yang sudah ber-rencana aksi,
     * dan rencana aksi yang capaian triwulan k-nya terisi.
     *
     * @return array<int, array{ind:int, tr:int, ind_tr:int, tw1:int, tw2:int, tw3:int, tw4:int}>
     */
    public function angkaRenaksi(int $tahun, array $opdIds, bool $perJenis = false): array
    {
        $kolomJenis = $perJenis ? ', pk.jenis' : '';
        $rows = $this->db->query(
            'SELECT pk.opd_id' . $kolomJenis . ', COUNT(DISTINCT pi.id) ind, COUNT(DISTINCT tr.id) tr,
                    COUNT(DISTINCT CASE WHEN tr.id IS NOT NULL THEN pi.id END) ind_tr,
                    COUNT(DISTINCT CASE WHEN NULLIF(TRIM(m.capaian_triwulan_1), \'\') IS NOT NULL THEN tr.id END) tw1,
                    COUNT(DISTINCT CASE WHEN NULLIF(TRIM(m.capaian_triwulan_2), \'\') IS NOT NULL THEN tr.id END) tw2,
                    COUNT(DISTINCT CASE WHEN NULLIF(TRIM(m.capaian_triwulan_3), \'\') IS NOT NULL THEN tr.id END) tw3,
                    COUNT(DISTINCT CASE WHEN NULLIF(TRIM(m.capaian_triwulan_4), \'\') IS NOT NULL THEN tr.id END) tw4
             FROM pk
             JOIN pk_sasaran ps ON ps.pk_id = pk.id
             JOIN pk_indikator pi ON pi.pk_sasaran_id = ps.id
             LEFT JOIN target_rencana tr ON tr.pk_indikator_id = pi.id
             LEFT JOIN monev m ON m.target_rencana_id = tr.id
             WHERE pk.tahun = ? AND pk.opd_id IN ? AND pk.jenis IN (\'jpt\', \'camat\', \'administrator\', \'pengawas\')
             GROUP BY pk.opd_id' . $kolomJenis,
            [$tahun, $opdIds]
        )->getResultArray();

        $hasil = [];
        foreach ($rows as $r) {
            if ($perJenis) {
                $hasil[(int) $r['opd_id']][(string) $r['jenis']] = $r;
            } else {
                $hasil[(int) $r['opd_id']] = $r;
            }
        }

        return $hasil;
    }

    /** @return array<int, array<int, array{n:int, selesai:int, pengesahan:?string}>> [opd => [tahun => ...]] */
    public function angkaLakip(int $tahun, array $opdIds): array
    {
        $hasil = [];
        foreach ($this->db->query(
            'SELECT opd_id, tahun, COUNT(*) n, SUM(status = \'selesai\') selesai FROM lakip
             WHERE mode = \'opd\' AND tahun IN (?, ?) AND opd_id IN ? GROUP BY opd_id, tahun',
            [$tahun, $tahun - 1, $opdIds]
        )->getResultArray() as $r) {
            $hasil[(int) $r['opd_id']][(int) $r['tahun']] = ['n' => (int) $r['n'], 'selesai' => (int) $r['selesai'], 'pengesahan' => null];
        }
        if ($this->db->tableExists('lakip_pengesahan')) {
            foreach ($this->db->query(
                'SELECT opd_id, tahun, status FROM lakip_pengesahan WHERE mode = \'opd\' AND tahun IN (?, ?) AND opd_id IN ?',
                [$tahun, $tahun - 1, $opdIds]
            )->getResultArray() as $r) {
                $o = (int) $r['opd_id'];
                $t = (int) $r['tahun'];
                $hasil[$o][$t] ??= ['n' => 0, 'selesai' => 0, 'pengesahan' => null];
                $hasil[$o][$t]['pengesahan'] = (string) $r['status'];
            }
        }

        return $hasil;
    }

    /**
     * IKP per OPD: jumlah, rata-rata capaian s.d. bulan lalu (rumus ikp_capaian), kelompok
     * warna ambang, dan daftar per IKP (untuk hub).
     *
     * @return array<int, array{jumlah:int, bulan:int, rata:?float, kelompok:string, sebaran:array, butir:array}>
     */
    public function angkaIkp(int $tahun, array $opdIds): array
    {
        if (! $this->ikp->siap()) {
            return [];
        }
        $bulan = self::bulanLalu($tahun);
        $hasil = [];
        foreach ($opdIds as $id) {
            $rekap = $this->ikp->rekapOpd((int) $id, $tahun);
            if ($rekap === []) {
                continue;
            }
            $persen  = [];
            $sebaran = ['hijau' => 0, 'kuning' => 0, 'merah' => 0, 'abu' => 0];
            $butir   = [];
            foreach ($rekap as $r) {
                $t = [];
                $v = [];
                for ($m = 1; $m <= 12; $m++) {
                    $t[$m] = $r['bulan'][$m]['target'] ?? null;
                    $v[$m] = $r['bulan'][$m]['realisasi'] ?? null;
                }
                $h  = $bulan > 0 ? ikp_capaian((string) ($r['ikp']['metode'] ?? ''), $t, $v, 1, $bulan) : ['status' => 'incomplete', 'percentage' => null, 'error' => null];
                $st = ikp_status($h);
                $p  = ($h['status'] ?? '') === 'calculated' && $h['percentage'] !== null ? (float) $h['percentage'] : null;
                if ($p !== null) {
                    $persen[] = $p;
                }
                $sebaran[$st['kelompok']] = ($sebaran[$st['kelompok']] ?? 0) + 1;
                $butir[] = [
                    'id'     => (int) $r['ikp']['id'],
                    'nama'   => (string) ($r['ikp']['output_prioritas'] ?? $r['ikp']['indikator_outcome'] ?? ''),
                    'satuan' => (string) ($r['ikp']['satuan_label'] ?? ''),
                    'pu'     => (string) ($r['ikp']['pu_nama'] ?? ''),
                    'persen' => $p,
                    'status' => $st,
                ];
            }
            $rata = $persen === [] ? null : round(array_sum($persen) / count($persen), 2);
            $stR  = ikp_status($rata === null ? ['status' => 'incomplete', 'percentage' => null, 'error' => null]
                : ['status' => 'calculated', 'percentage' => $rata, 'error' => null]);
            $hasil[(int) $id] = [
                'jumlah'   => count($rekap),
                'bulan'    => $bulan,
                'rata'     => $rata,
                'kelompok' => (string) $stR['kelompok'],
                'status'   => $stR,
                'sebaran'  => $sebaran,
                'butir'    => $butir,
            ];
        }

        return $hasil;
    }

    // =================================================================
    // HUB SATU OPD
    // =================================================================

    /**
     * Rincian satu OPD untuk hub. Angka ringkas memakai fungsi yang sama dengan matriks
     * (satu sumber); daftar tambahan dibatasi supaya halaman tetap ringkas.
     */
    public function hub(int $opdId, int $tahun, ?array $ekinRingkas, string $alasanEkin = ''): array
    {
        $opd    = $this->opd($opdId);
        $ids    = [$opdId];
        $sel    = $this->matriks($tahun, $ids, $ekinRingkas === null ? null : ['opd' => [(string) $opdId => $ekinRingkas]], $alasanEkin)[$opdId];
        $kepala = $this->kepalaPerOpd($tahun)[$opdId] ?? null;

        return [
            'opd'        => $opd,
            'kepala'     => $kepala,
            'sel'        => $sel,
            'periode'    => $this->periodeUntuk($tahun),
            'renstra'    => $this->angkaRenstra($tahun, $ids)[$opdId] ?? null,
            'sasaranRenstra' => $this->sasaranRenstra($opdId, $tahun),
            'rkt'        => $this->angkaRkt($tahun, $ids)[$opdId] ?? null,
            'iku'        => $this->indikatorIku($opdId, $tahun),
            'cascading'  => $this->angkaCascading($tahun, $ids)[$opdId] ?? [],
            'pk'         => $this->daftarPk($opdId, $tahun),
            'renaksi'    => $this->angkaRenaksi($tahun, $ids)[$opdId] ?? null,
            'renaksiJenis' => $this->angkaRenaksi($tahun, $ids, true)[$opdId] ?? [],
            'triwulanWajib' => self::triwulanWajib($tahun),
            'ikp'        => $this->angkaIkp($tahun, $ids)[$opdId] ?? null,
            'lakip'      => $this->angkaLakip($tahun, $ids)[$opdId] ?? [],
            'ekin'       => $ekinRingkas,
        ];
    }

    /** @return list<array{id:int, sasaran:string, status:string, ind:int}> */
    public function sasaranRenstra(int $opdId, int $tahun): array
    {
        return $this->db->query(
            'SELECT rs.id, rs.sasaran, rs.status, COUNT(ris.id) ind
             FROM renstra_sasaran rs
             LEFT JOIN renstra_indikator_sasaran ris ON ris.renstra_sasaran_id = rs.id AND ris.dihentikan_pada IS NULL
             WHERE rs.opd_id = ? AND rs.tahun_mulai <= ? AND rs.tahun_akhir >= ? AND rs.dihentikan_pada IS NULL
             GROUP BY rs.id, rs.sasaran, rs.status ORDER BY rs.id',
            [$opdId, $tahun, $tahun]
        )->getResultArray();
    }

    /** @return list<array{sasaran:string, indikator:string, satuan:string, target:?string}> */
    public function indikatorIku(int $opdId, int $tahun): array
    {
        return $this->db->query(
            "SELECT iks.sasaran, iki.indikator, COALESCE(s.satuan, NULLIF(iki.satuan, '')) satuan,
                    (SELECT it.target FROM iku_target it WHERE it.iku_indikator_id = iki.id AND it.tahun = ? ORDER BY it.id LIMIT 1) target
             FROM iku_sasaran iks
             JOIN iku_indikator iki ON iki.iku_sasaran_id = iks.id AND iki.dihentikan_pada IS NULL
             LEFT JOIN satuan s ON s.id = iki.satuan AND iki.satuan REGEXP '^[0-9]+$'
             WHERE iks.opd_id = ? AND iks.tahun_mulai <= ? AND iks.tahun_akhir >= ?
             ORDER BY iks.urutan, iks.id, iki.urutan, iki.id",
            [$tahun, $opdId, $tahun, $tahun]
        )->getResultArray();
    }

    /**
     * Daftar PK satu OPD (atau semua OPD bila $opdId null) untuk satu tahun, dengan
     * pihak pertama/kedua dan jumlah sasaran/indikator. Dipakai hub & daftar PK terpadu.
     *
     * @param array{jenis?:string[], q?:string, pihak_1?:int|list<int>} $saring pihak_1 = id pegawai pihak pertama
     *                                                                        (satu, atau beberapa sekaligus)
     *
     * @return list<array<string,mixed>>
     */
    public function daftarPk(?int $opdId, ?int $tahun, array $saring = []): array
    {
        $b = $this->db->table('pk')
            ->select("pk.id, pk.opd_id, pk.tahun, pk.jenis, pk.tanggal, pk.pihak_1, pk.pihak_2,
                      pk.is_plt_pihak_1, pk.is_plh_pihak_1, pk.is_plt_pihak_2, pk.is_plh_pihak_2,
                      pk.jabatan_pihak_1_manual, pk.jabatan_pihak_2_manual,
                      p1.nama_pegawai AS nama_1, j1.nama_jabatan AS jabatan_1,
                      p2.nama_pegawai AS nama_2, j2.nama_jabatan AS jabatan_2,
                      o.nama_opd,
                      (SELECT COUNT(*) FROM pk_sasaran ps WHERE ps.pk_id = pk.id) AS jml_sasaran,
                      (SELECT COUNT(*) FROM pk_indikator pi JOIN pk_sasaran ps2 ON ps2.id = pi.pk_sasaran_id WHERE ps2.pk_id = pk.id) AS jml_indikator", false)
            ->join('pegawai p1', 'p1.id = pk.pihak_1', 'left')
            ->join('jabatan j1', 'j1.id = p1.jabatan_id', 'left')
            ->join('pegawai p2', 'p2.id = pk.pihak_2', 'left')
            ->join('jabatan j2', 'j2.id = p2.jabatan_id', 'left')
            ->join('opd o', 'o.id = pk.opd_id', 'left');
        if ($opdId !== null) {
            $b->where('pk.opd_id', $opdId);
        }
        if ($tahun !== null) {
            $b->where('pk.tahun', $tahun);
        }
        if (! empty($saring['jenis'])) {
            $b->whereIn('pk.jenis', $saring['jenis']);
        }
        // Dari eKin (PK Pegawai "PK di AKSARA"): id pegawai eKin = id pegawai AKSARA (SinkronPegawaiService eKin
        // menyalin id), jadi pencocokan lewat id tepat — teks jabatan tidak (Plt., jabatan pihak kedua ikut cocok).
        if (is_array($saring['pihak_1'] ?? null)) {
            // Beberapa pegawai sekaligus (daftar PK Pegawai Ruang OPD: semua "PK di AKSARA" satu OPD, satu kueri).
            $ids = array_values(array_filter(array_map('intval', $saring['pihak_1']), static fn ($i) => $i > 0));
            $b->whereIn('pk.pihak_1', $ids === [] ? [0] : $ids);
        } elseif ((int) ($saring['pihak_1'] ?? 0) > 0) {
            $b->where('pk.pihak_1', (int) $saring['pihak_1']);
        }
        $q = trim((string) ($saring['q'] ?? ''));
        if ($q !== '') {
            $b->groupStart()
                ->like('p1.nama_pegawai', $q)->orLike('j1.nama_jabatan', $q)->orLike('pk.jabatan_pihak_1_manual', $q)
                ->orLike('p2.nama_pegawai', $q)->orLike('j2.nama_jabatan', $q)->orLike('o.nama_opd', $q)
                ->groupEnd();
        }
        $urutJenis = "FIELD(pk.jenis, 'bupati', 'jpt', 'camat', 'administrator', 'pengawas')";
        $rows = $b->orderBy('o.nama_opd', 'ASC')->orderBy($urutJenis, '', false)
            ->orderBy('j1.nama_jabatan', 'ASC')->orderBy('pk.tanggal', 'DESC')->orderBy('pk.id', 'DESC')
            ->get()->getResultArray();

        foreach ($rows as &$r) {
            $r['id']        = (int) $r['id'];
            $r['opd_id']    = (int) $r['opd_id'];
            $r['jabatan_1'] = trim((string) (($r['jabatan_pihak_1_manual'] ?? '') !== '' ? $r['jabatan_pihak_1_manual'] : ($r['jabatan_1'] ?? '')));
            $r['jabatan_2'] = trim((string) (($r['jabatan_pihak_2_manual'] ?? '') !== '' ? $r['jabatan_pihak_2_manual'] : ($r['jabatan_2'] ?? '')));
            $r['status_1']  = self::statusPejabat((int) $r['is_plt_pihak_1'], (int) $r['is_plh_pihak_1']);
            $r['status_2']  = self::statusPejabat((int) $r['is_plt_pihak_2'], (int) $r['is_plh_pihak_2']);
            $r['nama_opd_tampil'] = self::namaRapi((string) ($r['nama_opd'] ?? ''));
        }
        unset($r);

        return $rows;
    }

    /**
     * PK AKSARA per pihak pertama — untuk pegawai yang di eKin berstatus "PK di AKSARA" (lewat_aksara).
     *
     * MENGAPA AKSARA mencarinya sendiri: eKin sengaja tidak membuat PK pegawai bagi pihak pertama PK jabatan (PK-nya
     * dokumen AKSARA), jadi jawaban eKin untuk mereka kosong — tanpa pihak kedua, tanpa baris. Pemiliknya AKSARA;
     * id pegawai eKin = id pegawai AKSARA (SinkronPegawaiService eKin menyalin id), sehingga cocok lewat pk.pihak_1.
     * Pegawai yang tidak punya PK di sini = eKin dan AKSARA tidak sepakat; halaman menandainya, bukan menutupinya.
     *
     * @param int[] $pegawaiIds
     *
     * @return array<int, list<array<string,mixed>>> id pegawai => PK tahun itu, terbaru dulu (PK perubahan di atas PK awal)
     */
    public function pkAksaraPerPihakPertama(array $pegawaiIds, int $tahun): array
    {
        $pegawaiIds = array_values(array_unique(array_filter(array_map('intval', $pegawaiIds), static fn ($i) => $i > 0)));
        if ($pegawaiIds === []) {
            return [];
        }
        return self::kelompokPerPihakPertama($this->daftarPk(null, $tahun, ['pihak_1' => $pegawaiIds]));
    }

    /**
     * Baris PK -> [pihak_1 => PK, terbaru dulu]. "Terbaru" = tanggal PK lalu id: PK perubahan (mis. 1 Juli sesudah
     * rotasi) berlaku di atas PK awal tahun, dan PK tanpa tanggal jatuh ke bawah.
     *
     * @param list<array<string,mixed>> $rows
     *
     * @return array<int, list<array<string,mixed>>>
     */
    public static function kelompokPerPihakPertama(array $rows): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            if ((int) ($r['pihak_1'] ?? 0) > 0) {
                $hasil[(int) $r['pihak_1']][] = $r;
            }
        }
        foreach ($hasil as &$daftar) {
            usort($daftar, static fn ($a, $b) => [(string) ($b['tanggal'] ?? ''), (int) $b['id']] <=> [(string) ($a['tanggal'] ?? ''), (int) $a['id']]);
        }
        unset($daftar);

        return $hasil;
    }

    /**
     * Isi (sasaran → indikator → target) sekumpulan PK untuk tampilan di tempat.
     *
     * @param int[] $pkIds
     *
     * @return array<int, list<array{sasaran:string, indikator:list<array{indikator:string, target:string, satuan:string}>}>>
     */
    public function isiPk(array $pkIds): array
    {
        $pkIds = array_values(array_filter(array_map('intval', $pkIds)));
        if ($pkIds === []) {
            return [];
        }
        $rows = $this->db->table('pk_sasaran ps')
            ->select('ps.pk_id, ps.id AS sid, ps.sasaran, pi.id AS iid, pi.indikator, pi.target, s.satuan')
            ->join('pk_indikator pi', 'pi.pk_sasaran_id = ps.id', 'left')
            ->join('satuan s', 's.id = pi.id_satuan', 'left')
            ->whereIn('ps.pk_id', $pkIds)
            ->orderBy('ps.pk_id')->orderBy('ps.id')->orderBy('pi.id')
            ->get()->getResultArray();

        $hasil = [];
        foreach ($rows as $r) {
            $pk  = (int) $r['pk_id'];
            $sid = (int) $r['sid'];
            $hasil[$pk][$sid] ??= ['sasaran' => (string) $r['sasaran'], 'indikator' => []];
            if ($r['iid'] !== null) {
                $hasil[$pk][$sid]['indikator'][] = [
                    'indikator' => (string) $r['indikator'],
                    'target'    => (string) ($r['target'] ?? ''),
                    'satuan'    => (string) ($r['satuan'] ?? ''),
                ];
            }
        }

        return array_map('array_values', $hasil);
    }

    /** Tahun yang punya dokumen PK (terbaru dulu). */
    public function tahunPk(): array
    {
        return array_map('intval', array_column(
            $this->db->table('pk')->select('tahun')->distinct()->orderBy('tahun', 'DESC')->get()->getResultArray(),
            'tahun'
        ));
    }
}
