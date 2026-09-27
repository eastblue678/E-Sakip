<?php

namespace App\Services;

/**
 * AKSARA+ — pemilik simpul pohon kinerja (cascading_pemilik) dalam satu tempat.
 *
 * Dipakai dua layar yang menyajikan pohon yang sama:
 *   - Pemilik Kinerja (AdminOpd\PemilikKinerjaController) — tempat MENGELOLA pemilik;
 *   - Pohon Kinerja (tab "Pohon Kinerja" di menu Pohon Kinerja & Cascading, adminopd & adminkab)
 *     — tempat MELIHAT pohon beserta pemilik dan pelaksananya, plus pegawai yang belum punya tugas.
 * Dulu logika ini hidup privat di controller Pemilik Kinerja; dipindah ke sini supaya kedua layar
 * tidak pernah berselisih soal siapa pemilik simpul dan siapa yang "belum punya peran".
 *
 * Peran (PermenPANRB 6/2022, Lampiran BAB II Tahap 5–6):
 *   - penanggung_jawab   : simpul menjadi RHK UTAMA pegawai itu (ketua/pemilik hasil);
 *   - anggota            : ikut mengerjakan simpul bersama PJ — tetap hasil kerja UTAMA;
 *   - penugasan_tambahan : pegawai ditugaskan (penunjukan atau pengajuan sukarela, sering lintas
 *                          bidang/unit) untuk mendukung simpul ini; di SKP menjadi HASIL KERJA TAMBAHAN
 *                          (prioritas rendah). Nomenklatur regulasi untuk bagian SKP-nya adalah
 *                          "hasil kerja tambahan"; "penugasan khusus" di regulasi hanya dipakai untuk
 *                          penugasan dari pejabat di luar unit/instansi, jadi terlalu sempit untuk nama peran.
 * Pegawai yang hanya memegang penugasan tambahan tetap dihitung BELUM punya tugas utama.
 */
class PohonPemilikService
{
    public const PERAN = [
        'penanggung_jawab'   => 'Penanggung Jawab',
        'anggota'            => 'Anggota',
        'penugasan_tambahan' => 'Penugasan Tambahan',
    ];

    /** Label ringkas untuk chip (pohon & daftar). */
    public const PERAN_SINGKAT = [
        'penanggung_jawab'   => 'PJ',
        'anggota'            => 'Anggota',
        'penugasan_tambahan' => 'Tambahan',
    ];

    /** Peran yang menjadi hasil kerja UTAMA (dasar metrik "belum punya peran"). */
    public const PERAN_UTAMA = ['penanggung_jawab', 'anggota'];

    /**
     * OPD yang pegawainya tercatat di id OPD lain: BKPSDM (8) → 210; Kec. Gadingrejo (32) → 213;
     * DP3AP2KB (211) → sebagian masih di 13. Tanpa peta ini daftar pegawai BKPSDM kosong.
     */
    public const ALIAS_OPD = [8 => [8, 210], 32 => [32, 213], 211 => [211, 13]];

    public const KATEGORI = [
        'struktural' => 'Struktural',
        'fungsional' => 'Fungsional',
        'pelaksana'  => 'Pelaksana',
        'lainnya'    => 'Tanpa kode jabatan',
    ];

    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /** @return int[] */
    public static function aliasOpd(int $opdId): array
    {
        return self::ALIAS_OPD[$opdId] ?? [$opdId];
    }

    public static function peranSah(string $peran): bool
    {
        return isset(self::PERAN[$peran]);
    }

    public static function peranUtama(string $peran): bool
    {
        return in_array($peran, self::PERAN_UTAMA, true);
    }

    /** Pegawai + kategori jabatan (struktural/fungsional/pelaksana dari simpeg_id). */
    public function queryPegawai()
    {
        return $this->db->table('pegawai p')
            ->select("p.id, p.nama_pegawai, p.nip_pegawai, p.opd_id, p.is_plt, p.status,
                      j.nama_jabatan, o.nama_opd, o.singkatan,
                      CASE WHEN j.simpeg_id LIKE 'struktural-%' THEN 'struktural'
                           WHEN j.simpeg_id LIKE 'fungsional-%' THEN 'fungsional'
                           WHEN j.simpeg_id LIKE 'pelaksana-%' THEN 'pelaksana'
                           ELSE NULL END AS kategori,
                      CASE WHEN j.simpeg_id LIKE 'struktural-%' THEN 1
                           WHEN j.simpeg_id LIKE 'fungsional-%' THEN 2
                           WHEN j.simpeg_id LIKE 'pelaksana-%' THEN 3
                           ELSE 4 END AS urut_kategori", false)
            ->join('jabatan j', 'j.id = p.jabatan_id', 'left')
            ->join('opd o', 'o.id = p.opd_id', 'left');
    }

    /** @return list<array> pemilik simpul-simpul itu pada tahun itu, PJ lebih dulu. */
    public function pemilikUntukSimpul(array $nodeIds, int $tahun): array
    {
        $nodeIds = array_values(array_unique(array_filter(array_map('intval', $nodeIds))));
        if ($nodeIds === []) {
            return [];
        }

        return $this->bentukPemilik(
            $this->dasarPemilik()->where('cp.tahun', $tahun)
                ->whereIn('cp.cascading_sasaran_id', $nodeIds)
                ->get()->getResultArray()
        );
    }

    /** @return list<array> */
    public function pemilikByIds(array $ids): array
    {
        return $this->bentukPemilik(
            $this->dasarPemilik()->whereIn('cp.id', array_map('intval', $ids))->get()->getResultArray()
        );
    }

    private function dasarPemilik()
    {
        return $this->db->table('cascading_pemilik cp')
            ->select('cp.id, cp.cascading_sasaran_id, cp.opd_id, cp.pegawai_id, cp.jabatan_teks, cp.peran,
                      cp.is_plt, cp.sumber, p.nama_pegawai, p.nip_pegawai, p.opd_id AS pegawai_opd,
                      j.nama_jabatan, o.singkatan, o.nama_opd')
            ->join('pegawai p', 'p.id = cp.pegawai_id', 'left')
            ->join('jabatan j', 'j.id = p.jabatan_id', 'left')
            ->join('opd o', 'o.id = p.opd_id', 'left')
            ->orderBy("FIELD(cp.peran, 'penanggung_jawab', 'anggota', 'penugasan_tambahan')", '', false)
            ->orderBy('cp.id', 'ASC');
    }

    private function bentukPemilik(array $rows): array
    {
        $hasil = [];
        foreach ($rows as $r) {
            $alias   = self::aliasOpd((int) $r['opd_id']);
            $opdLain = $r['pegawai_opd'] !== null && ! in_array((int) $r['pegawai_opd'], $alias, true);
            $hasil[] = [
                'id'         => (int) $r['id'],
                'node_id'    => (int) $r['cascading_sasaran_id'],
                'pegawai_id' => (int) $r['pegawai_id'],
                'nama'       => (string) ($r['nama_pegawai'] ?? ('Pegawai #' . $r['pegawai_id'] . ' (tidak ditemukan)')),
                'jabatan'    => (string) (($r['jabatan_teks'] ?? '') !== '' ? $r['jabatan_teks'] : ($r['nama_jabatan'] ?? '')),
                'peran'      => (string) $r['peran'],
                'sumber'     => (string) $r['sumber'],
                'is_plt'     => (int) $r['is_plt'] === 1,
                'opd_lain'   => $opdLain ? (string) (($r['singkatan'] ?? '') !== '' ? $r['singkatan'] : ($r['nama_opd'] ?? 'OPD lain')) : '',
            ];
        }

        return $hasil;
    }

    /**
     * Pemilik Eselon II = pihak_1 PK JPT (atau PK Camat) OPD itu, tahun itu.
     * Hanya dibaca — sumber resminya tetap menu Perjanjian Kinerja.
     */
    public function pemilikEs2(int $opdId, int $tahun): array
    {
        $rows = $this->db->table('pk')
            ->select('pk.id AS pk_id, pk.jenis, pk.pihak_1, pk.is_plt_pihak_1, pk.is_plh_pihak_1,
                      pk.jabatan_pihak_1_manual, p.nama_pegawai, j.nama_jabatan')
            ->join('pegawai p', 'p.id = pk.pihak_1', 'left')
            ->join('jabatan j', 'j.id = p.jabatan_id', 'left')
            ->where('pk.opd_id', $opdId)->where('pk.tahun', $tahun)
            ->whereIn('pk.jenis', ['jpt', 'camat'])
            ->orderBy('pk.id', 'ASC')->get()->getResultArray();

        $hasil = [];
        foreach ($rows as $r) {
            if ((int) $r['pihak_1'] <= 0) {
                continue;
            }
            $jab = (string) (($r['jabatan_pihak_1_manual'] ?? '') !== '' ? $r['jabatan_pihak_1_manual'] : ($r['nama_jabatan'] ?? ''));
            $hasil[] = [
                'pk_id'      => (int) $r['pk_id'],
                'jenis'      => (string) $r['jenis'],
                'pegawai_id' => (int) $r['pihak_1'],
                'nama'       => (string) ($r['nama_pegawai'] ?? ('Pegawai #' . $r['pihak_1'])),
                'jabatan'    => $jab,
                'plt'        => (int) $r['is_plt_pihak_1'] === 1 ? 'Plt.' : ((int) $r['is_plh_pihak_1'] === 1 ? 'Plh.' : ''),
            ];
        }

        return $hasil;
    }

    /**
     * Roster pegawai OPD (termasuk id alias) + jumlah peran tiap orang tahun ini — dasar metrik
     * "matriks 0" e-Kinerja: pegawai yang belum punya satu pun simpul UTAMA di pohon kinerja.
     *
     * Peran dihitung dari SELURUH cascading_pemilik tahun itu (termasuk simpul di OPD lain),
     * ditambah pemilik Eselon II lewat PK. `jumlahPeran` = peran utama (PJ/anggota/Eselon II),
     * `jumlahTambahan` = penugasan tambahan; keduanya juga ada per pegawai (peran, tambahan).
     *
     * @return array{pegawai: list<array>, jumlahPeran: array<int,int>, jumlahTambahan: array<int,int>}
     */
    public function rosterOpd(int $opdId, int $tahun, array $es2Pemilik): array
    {
        $alias = self::aliasOpd($opdId);
        $rows  = $this->queryPegawai()->whereIn('p.opd_id', $alias)
            ->orderBy('urut_kategori', 'ASC')->orderBy('p.nama_pegawai', 'ASC')
            ->get()->getResultArray();

        $utama = $tambahan = [];
        foreach ($this->db->table('cascading_pemilik cp')
            ->select("cp.pegawai_id, SUM(cp.peran = 'penugasan_tambahan') AS tambahan, SUM(cp.peran <> 'penugasan_tambahan') AS utama", false)
            ->join('pegawai p', 'p.id = cp.pegawai_id', 'inner')
            ->whereIn('p.opd_id', $alias)->where('cp.tahun', $tahun)
            ->groupBy('cp.pegawai_id')->get()->getResultArray() as $r) {
            if ((int) $r['utama'] > 0) {
                $utama[(int) $r['pegawai_id']] = (int) $r['utama'];
            }
            if ((int) $r['tambahan'] > 0) {
                $tambahan[(int) $r['pegawai_id']] = (int) $r['tambahan'];
            }
        }
        foreach ($es2Pemilik as $e) {
            $utama[$e['pegawai_id']] = ($utama[$e['pegawai_id']] ?? 0) + 1;
        }

        // Data pegawai AKSARA memuat baris kembar (NIP sama, jabatan/status berbeda — sisa sinkron SIMPEG).
        // PK bisa menunjuk baris yang satu, pemilik simpul baris yang lain. Satu orang = satu NIP: peran
        // semua baris kembar dijumlahkan dan orangnya tampil sekali, dengan penanda `ganda`.
        $kelompok = [];
        foreach ($rows as $r) {
            $nip   = preg_replace('/\D/', '', (string) ($r['nip_pegawai'] ?? ''));
            $kunci = strlen($nip) >= 9 ? 'nip:' . $nip : 'id:' . $r['id'];
            $kelompok[$kunci][] = $r;
        }

        $pegawai = [];
        foreach ($kelompok as $baris) {
            // Wakil tampilan: yang berkode jabatan & berstatus lebih dulu.
            usort($baris, static fn ($x, $y) => [($y['kategori'] !== null) <=> ($x['kategori'] !== null), ($y['status'] !== null) <=> ($x['status'] !== null), (int) $x['id'] <=> (int) $y['id']]);
            $r   = $baris[0];
            $ids = array_map(static fn ($x) => (int) $x['id'], $baris);
            $pegawai[] = [
                'id'       => (int) $r['id'],
                'ids'      => $ids,
                'ganda'    => count($ids),
                'nama'     => (string) $r['nama_pegawai'],
                'nip'      => (string) ($r['nip_pegawai'] ?? ''),
                'jabatan'  => (string) ($r['nama_jabatan'] ?? ''),
                'kategori' => isset(self::KATEGORI[$r['kategori'] ?? '']) ? $r['kategori'] : 'lainnya',
                'status'   => (string) ($r['status'] ?? ''),
                'peran'    => array_sum(array_map(static fn ($i) => $utama[$i] ?? 0, $ids)),
                'tambahan' => array_sum(array_map(static fn ($i) => $tambahan[$i] ?? 0, $ids)),
                'urut'     => (int) $r['urut_kategori'],
            ];
        }
        usort($pegawai, static fn ($x, $y) => [$x['urut'], $x['nama']] <=> [$y['urut'], $y['nama']]);

        return ['pegawai' => $pegawai, 'jumlahPeran' => $utama, 'jumlahTambahan' => $tambahan];
    }

    /**
     * Data pemilik untuk tab Pohon Kinerja: pemilik per simpul (Eselon III, IV, Pelaksana), pemilik
     * Eselon II dari PK, dan roster pegawai. Simpul diambil dari pohon yang SUDAH dirender halaman
     * (cascOpdTree), jadi tidak ada simpul pemilik yang tampil tanpa kotaknya.
     *
     * @param array $tree hasil CascadingOpdMetaTrait::cascOpdTree
     */
    public function untukPohon(int $opdId, int $tahun, array $tree): array
    {
        $ids = self::idSimpulPohon($tree);

        $perSimpul = [];
        foreach ($this->pemilikUntukSimpul(array_merge($ids['es3'], $ids['es4'], $ids['pelaksana']), $tahun) as $p) {
            $perSimpul[$p['node_id']][] = $p;
        }

        $es2    = $this->pemilikEs2($opdId, $tahun);
        $roster = $this->rosterOpd($opdId, $tahun, $es2);

        $jenjang = [];
        foreach (['es3', 'es4', 'pelaksana'] as $lv) {
            $jenjang[$lv] = ['simpul' => count($ids[$lv]), 'berpemilik' => 0];
            foreach ($ids[$lv] as $id) {
                if (! empty($perSimpul[$id])) {
                    $jenjang[$lv]['berpemilik']++;
                }
            }
        }

        $tambahan = 0;
        foreach ($perSimpul as $list) {
            foreach ($list as $p) {
                $tambahan += $p['peran'] === 'penugasan_tambahan' ? 1 : 0;
            }
        }

        $opd = $this->db->table('opd')->select('id, jenis')->where('id', $opdId)->get()->getRowArray();
        $kecamatan = ($opd['jenis'] ?? '') === \App\Models\OpdModel::JENIS_KECAMATAN
            || $this->db->table('pk')->where('opd_id', $opdId)->where('jenis', 'camat')->countAllResults() > 0;

        return [
            'opd_id'    => $opdId,
            'tahun'     => $tahun,
            'simpul'    => $perSimpul,
            'es2'       => $es2,
            'roster'    => $roster,
            'jenjang'   => $jenjang,
            'tambahan'  => $tambahan,
            'kecamatan' => $kecamatan,
            'label'     => RuangOpdService::labelJenjang($kecamatan),
        ];
    }

    /**
     * Id simpul per jenjang di pohon cascOpdTree (kunci array es3s/es4s/pelaksanas = id simpul).
     *
     * @return array{es3: int[], es4: int[], pelaksana: int[]}
     */
    public static function idSimpulPohon(array $tree): array
    {
        $ids = ['es3' => [], 'es4' => [], 'pelaksana' => []];
        foreach ($tree as $t) {
            foreach ($t['sasarans'] ?? [] as $s) {
                foreach ($s['tujuan_renstras'] ?? [] as $rt) {
                    foreach ($rt['es2s'] ?? [] as $e2) {
                        foreach ($e2['es3s'] ?? [] as $id3 => $e3) {
                            $ids['es3'][] = (int) $id3;
                            foreach ($e3['es4s'] ?? [] as $id4 => $e4) {
                                $ids['es4'][] = (int) $id4;
                                foreach ($e4['pelaksanas'] ?? [] as $idp => $_) {
                                    $ids['pelaksana'][] = (int) $idp;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_map(static fn (array $a): array => array_values(array_unique($a)), $ids);
    }

    /**
     * Tahun pemilik untuk sebuah periode: tahun yang diminta bila ada di periode, selain itu tahun
     * berjalan bila ada di periode, selain itu tahun terakhir periode.
     */
    public static function tahunDalamPeriode(int $awal, int $akhir, int $minta, ?int $sekarang = null): int
    {
        $sekarang ??= (int) date('Y');
        if ($minta >= $awal && $minta <= $akhir) {
            return $minta;
        }
        if ($sekarang >= $awal && $sekarang <= $akhir) {
            return $sekarang;
        }

        return $sekarang < $awal ? $awal : $akhir;
    }

    /** Inisial (maks. 2 huruf) tanpa gelar — untuk lingkaran avatar di pohon. */
    public static function inisial(string $nama): string
    {
        $nama  = preg_replace('/^(plt\.|plh\.)\s*/i', '', trim($nama));
        $nama  = trim(explode(',', (string) $nama)[0]);
        $kata  = array_values(array_filter(preg_split('/\s+/', $nama) ?: [], static fn ($k) => $k !== '' && ! preg_match('/^(h|hj|dr|drs|ir|prof)\.?$/i', $k)));
        $huruf = '';
        foreach (array_slice($kata, 0, 2) as $k) {
            $huruf .= mb_strtoupper(mb_substr($k, 0, 1));
        }

        return $huruf !== '' ? $huruf : '?';
    }

    /** Nama pendek untuk kotak pohon: tanpa gelar di belakang koma. */
    public static function namaPendek(string $nama): string
    {
        $inti = trim(explode(',', $nama)[0]);

        return $inti !== '' ? $inti : $nama;
    }
}
