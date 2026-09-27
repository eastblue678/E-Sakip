<?php

namespace App\Services;

use App\Models\Concerns\TransaksiAman;
use App\Models\OpdModel;

/**
 * =====================================================================
 * AKSARA+ — IKP TURUN SAMPAI PELAKSANA (pendelegasian IKP lewat pohon kinerja)
 * =====================================================================
 *
 * Keluhan pengguna: "SKP dari IKU sudah turun temurun. Tapi IKP belum turun
 * temurun … IKP hanya berhenti di Es 2, ga sampai bawah."
 *
 * JALUR RESMI = POHON KINERJA (sama dengan IKU). IKP diturunkan dengan
 * menautkannya ke INDIKATOR SIMPUL pohon di setiap jenjang: Kepala OPD (Es II,
 * pemilik IKP) → Eselon III → Eselon IV/Ketua Tim → pelaksana. Pemilik simpul
 * (menu Pemilik Kinerja) otomatis menjadi pemikul IKP turunan itu; eKin menarik
 * baris ini menjadi RHK lewat api/ekin/pegawai/{id}/kinerja (alasan `delegasi`).
 * Tidak ada jalur kedua.
 *
 * Satu baris cascading_indikator_target = "IKP X diturunkan ke indikator simpul
 * Y tahun T, porsi Z, peran P" (kolom ikp_id, ikp_peran, ikp_induk_id, sumber,
 * dibuat_oleh, sebelum_delegasi; migrasi 2026-09-28-000002).
 *
 * PERAN
 *   angka      pemikul angka. hitungan → target baris = PORSI (Σ porsi anak =
 *              target induk; pemeriksa "terbagi habis / kurang / lebih").
 *              posisi/rilis → target UTUH (= target IKP), tepat SATU pemikul
 *              angka per jenjang di satu cabang.
 *   pendukung  ikut bekerja lewat indikator PROSES miliknya sendiri (teks &
 *              target sendiri, pola hitungan), TIDAK menambah angka IKP.
 *              Contoh IKIP: Kabid IKP = pemikul angka (indeks 99, Des); PPID =
 *              pendukung "Jumlah bukti dukung SAQ Keterbukaan Informasi yang
 *              dilengkapi" — di sinilah kerja sepanjang tahun diukur.
 *
 * Bagian ATURAN MURNI (statis, tanpa DB) diuji tests/unit/IkpTurunTest.php.
 * Bagian DB dipakai halaman Turunkan IKP, chip "★ IKP" di Pohon/Pemilik
 * Kinerja, rekap Kabupaten, saran "Dari eKin" di realisasi IKP, dan API eKin.
 */
class IkpTurunService
{
    use TransaksiAman;

    public const PERAN = [
        'angka'     => 'Pemikul angka',
        'pendukung' => 'Pendukung',
    ];

    public const PERAN_JELAS = [
        'angka'     => 'Targetnya dihitung ke IKP: porsi bila pola ukurnya Hitungan, target utuh bila Posisi atau Rilis resmi.',
        'pendukung' => 'Bekerja untuk IKP lewat indikator proses miliknya sendiri; tidak menambah angka IKP.',
    ];

    public const LEVEL = ['es3', 'es4', 'pelaksana'];

    /** Toleransi selisih pembagian porsi (sama dengan ikp_cek). */
    public const TOL = 0.005;

    /** Ambang kemiripan teks untuk "Usulkan dari pohon". */
    public const AMBANG_MIRIP = 0.6;

    /** Kata yang tidak membedakan makna indikator (dibuang sebelum dicocokkan). */
    private const KATA_UMUM = [
        'jumlah', 'persentase', 'prosentase', 'persen', 'terlaksananya', 'meningkatnya', 'tersedianya', 'terselenggaranya',
        'terwujudnya', 'tersusunnya', 'terciptanya', 'dilaksanakannya', 'menurunnya', 'yang', 'dan', 'di', 'ke', 'dari',
        'dengan', 'untuk', 'pada', 'sesuai', 'dalam', 'bagi', 'oleh', 'atau', 'serta', 'kabupaten', 'kota', 'kab',
        'pringsewu', 'daerah', 'pemerintah', 'pemda', 'kegiatan', 'pelaksanaan', 'penyelenggaraan', 'pengelolaan',
        'standar', 'kualitas', 'tingkat', 'nya', 'ini', 'itu', 'telah', 'sudah', 'akan', 'secara', 'melalui', 'tahun',
        'perangkat', 'opd', 'se', 'lingkungan', 'kerja', 'wilayah', 'the',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        helper('ikp');
    }

    /** Kolom pendelegasian sudah ada? (instalasi yang belum migrasi tidak 500). */
    public function siap(): bool
    {
        return $this->db->tableExists('cascading_indikator_target')
            && $this->db->fieldExists('ikp_peran', 'cascading_indikator_target');
    }

    // =================================================================
    // ATURAN MURNI
    // =================================================================

    /**
     * Pemeriksa SATU jenjang: anak langsung dari satu induk (akar = IKP itu
     * sendiri, dipegang Kepala OPD).
     *
     * @param string     $pola        hitungan|posisi|rilis
     * @param float|null $targetInduk target induk (akar: target tahunan IKP; anak: porsi/target baris induk)
     * @param list<array{peran:string, target:?float}> $anak
     * @param bool       $akar        true = jenjang pertama di bawah IKP
     * @param string     $peranInduk  angka|pendukung (akar dianggap angka)
     *
     * @return array{kode:string, warna:string, pesan:string, jumlah:?float, selisih:?float, n_angka:int, n_pendukung:int}
     *   warna: ok | peringatan | netral
     */
    public static function periksa(string $pola, ?float $targetInduk, array $anak, bool $akar = false, string $peranInduk = 'angka'): array
    {
        $angka = array_values(array_filter($anak, static fn ($a) => ($a['peran'] ?? 'angka') === 'angka'));
        $nA    = count($angka);
        $nP    = count($anak) - $nA;
        $hasil = static fn (string $kode, string $warna, string $pesan, ?float $jumlah = null, ?float $selisih = null): array
            => ['kode' => $kode, 'warna' => $warna, 'pesan' => $pesan, 'jumlah' => $jumlah, 'selisih' => $selisih,
                'n_angka' => $nA, 'n_pendukung' => $nP];

        if ($peranInduk === 'pendukung' && ! $akar) {
            return $nA > 0
                ? $hasil('terputus', 'peringatan', 'Pemikul angka di bawah pendukung: rantai angka terputus. Jadikan simpul di atasnya pemikul angka, atau jadikan simpul ini pendukung.')
                : $hasil($nP > 0 ? 'pendukung' : 'belum', 'netral', $nP > 0 ? $nP . ' pendukung.' : 'Tidak diturunkan lagi.');
        }

        if ($nA === 0) {
            if ($akar) {
                return $nP > 0
                    ? $hasil('tanpa_angka', 'peringatan', 'Belum ada pemikul angka; pendukung tidak menambah angka IKP.')
                    : $hasil('belum', 'peringatan', 'IKP belum diturunkan ke jenjang mana pun.');
            }

            return $nP > 0
                ? $hasil('tetap_di_atas', 'netral', 'Angka tetap dipikul jenjang ini; ' . $nP . ' pendukung di bawahnya.')
                : $hasil('belum', 'netral', 'Tidak diturunkan lagi (dipikul jenjang ini).');
        }

        if ($pola === 'hitungan') {
            if ($targetInduk === null) {
                return $hasil('tanpa_target', 'peringatan', 'Target ' . ($akar ? 'tahunan IKP' : 'induk') . ' belum berupa angka; porsi tidak dapat diperiksa.');
            }
            $kosong = 0;
            $jumlah = 0.0;
            foreach ($angka as $a) {
                if (($a['target'] ?? null) === null) {
                    $kosong++;
                } else {
                    $jumlah += (float) $a['target'];
                }
            }
            $selisih = round($jumlah - $targetInduk, 4);
            $tambah  = $kosong > 0 ? ' ' . $kosong . ' porsi belum diisi.' : '';
            if (abs($selisih) <= self::TOL && $kosong === 0) {
                return $hasil('habis', 'ok', 'Terbagi habis: ' . ikp_fmt($jumlah, 4) . ' = ' . ikp_fmt($targetInduk, 4) . '.', $jumlah, 0.0);
            }
            if ($selisih < -self::TOL || ($kosong > 0 && abs($selisih) <= self::TOL)) {
                return $hasil('kurang', 'peringatan', 'Kurang ' . ikp_fmt(abs($selisih), 4) . ': porsi ' . ikp_fmt($jumlah, 4)
                    . ' dari ' . ikp_fmt($targetInduk, 4) . ' (sisanya belum diturunkan).' . $tambah, $jumlah, $selisih);
            }

            return $hasil('lebih', 'peringatan', 'Lebih ' . ikp_fmt($selisih, 4) . ': porsi ' . ikp_fmt($jumlah, 4)
                . ' melebihi ' . ikp_fmt($targetInduk, 4) . '.' . $tambah, $jumlah, $selisih);
        }

        // posisi / rilis — target UTUH, tepat satu pemikul angka per jenjang.
        if ($nA > 1) {
            return $hasil('ganda', 'peringatan', $nA . ' pemikul angka. Nilai ' . ($pola === 'rilis' ? 'rilis' : 'posisi')
                . ' tidak dibagi: pilih SATU pemikul angka di jenjang ini, yang lain jadikan pendukung.');
        }
        $t = $angka[0]['target'] ?? null;
        if ($targetInduk !== null && $t !== null && abs((float) $t - $targetInduk) > self::TOL) {
            return $hasil('beda_target', 'peringatan', 'Target pemikul angka ' . ikp_fmt((float) $t, 4) . ' berbeda dari target utuh '
                . ikp_fmt($targetInduk, 4) . '. Simpan ulang untuk menyamakan.');
        }

        return $hasil('satu', 'ok', 'Satu pemikul angka' . ($nP > 0 ? ' + ' . $nP . ' pendukung' : '') . '.');
    }

    /**
     * Cakupan pendelegasian satu IKP: jenjang mana yang sudah memikul (angka
     * atau pendukung). "Sampai pelaksana" = jenjang pelaksana; di kecamatan
     * jenjang es4 SUDAH berlabel "Pelaksana / JF" (Camat = Eselon III), jadi
     * es4 juga dihitung.
     *
     * @param string[] $level jenjang setiap baris (es3|es4|pelaksana)
     *
     * @return array{es3:bool, es4:bool, pelaksana:bool, sampai_pelaksana:bool, terbawah:?string, jumlah:int}
     */
    public static function cakupan(array $level, bool $kecamatan = false): array
    {
        $ada = array_fill_keys(self::LEVEL, false);
        foreach ($level as $l) {
            if (isset($ada[$l])) {
                $ada[$l] = true;
            }
        }
        $terbawah = null;
        foreach (self::LEVEL as $l) {
            if ($ada[$l]) {
                $terbawah = $l;
            }
        }

        return $ada + [
            'sampai_pelaksana' => $ada['pelaksana'] || ($kecamatan && $ada['es4']),
            'terbawah'         => $terbawah,
            'jumlah'           => count($level),
        ];
    }

    /** "Turun sampai: Eselon III ✓ · Eselon IV / JF ✓ · Pelaksana ✗" */
    public static function cakupanTeks(array $cakupan, array $label): string
    {
        $bagian = [];
        foreach (self::LEVEL as $l) {
            $bagian[] = ($label[$l] ?? $l) . ' ' . ($cakupan[$l] ? '✓' : '✗');
        }

        return 'Turun sampai: ' . implode(' · ', $bagian);
    }

    /**
     * Bagi $total ke bulan menurut bobot dengan PEMBULATAN KUMULATIF: nilai
     * bulan m = bulat(total × Σbobot[1..m] / Σbobot) − bulat(… m−1). Σ hasil =
     * total persis (pada presisi $desimal), tidak pernah negatif untuk bobot ≥ 0,
     * dan sisa pembulatan tersebar (4 dibagi 12 → Feb, Mei, Agu, Nov).
     * Bobot null = bulan tanpa nilai (hasil null).
     *
     * @param array<int, float|int|null> $bobot [1..12 => bobot]
     *
     * @return array<int, float|null> [1..12]
     */
    public static function bagiKumulatif(float $total, array $bobot, int $desimal = 0): array
    {
        $out  = array_fill(1, 12, null);
        $sigm = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $b = $bobot[$m] ?? null;
            if ($b !== null && (float) $b > 0) {
                $sigm += (float) $b;
            }
        }
        if ($sigm <= 0) {
            return $out;
        }
        $kum = 0.0;
        $seb = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $b = $bobot[$m] ?? null;
            if ($b === null) {
                continue;
            }
            $kum += max(0.0, (float) $b);
            $kini    = round($total * $kum / $sigm, $desimal);
            $out[$m] = round($kini - $seb, $desimal);
            $seb     = $kini;
        }

        return $out;
    }

    /**
     * Profil target bulanan satu baris pendelegasian (dikirim ke eKin sebagai
     * dasar rencana aksi):
     *   angka + hitungan       cicilan IKP × porsi/target (proporsional ke target
     *                          bulanan IKP; tanpa breakdown → cicilan rata ke bulan ukur)
     *   angka + posisi/rilis   target IKP di bulan ukur (bukan cicilan); tanpa
     *                          breakdown → target utuh di setiap bulan ukur
     *   pendukung              target proses sendiri dicicil rata 12 bulan
     * Bulan non-ukur selalu null untuk pemikul angka.
     *
     * @param array<int, float|null> $bulananIkp target bulanan IKP [1..12] (sudah atau belum disaring)
     *
     * @return array<int, float|null> [1..12]
     */
    public static function profilBulanan(array $pola, array $bulananIkp, ?float $target, string $peran): array
    {
        $kosong = array_fill(1, 12, null);
        if ($target === null) {
            return $kosong;
        }
        $bulat = abs($target - round($target)) < 1e-9;

        if ($peran === 'pendukung') {
            return self::bagiKumulatif($target, array_fill(1, 12, 1.0), $bulat ? 0 : 2);
        }

        $ikp = ikp_saring_ukur($pola, $bulananIkp);
        $ada = array_filter($ikp, static fn ($v) => $v !== null);

        if (($pola['pola'] ?? '') === 'hitungan') {
            foreach ($ada as $v) {
                $bulat = $bulat && abs((float) $v - round((float) $v)) < 1e-9;
            }
            if ($ada !== [] && array_sum(array_map('floatval', $ada)) > 0) {
                $bobot = [];
                foreach ($pola['bulan_ukur'] ?? [] as $m) {
                    $bobot[(int) $m] = $ikp[(int) $m] ?? 0.0;
                }

                return array_replace($kosong, self::bagiKumulatif($target, $bobot, $bulat ? 0 : 2));
            }
            $bagi = ikp_bagi_rata($target, count($pola['bulan_ukur'] ?? []), 'sum', null, $bulat);
            $out  = $kosong;
            foreach (array_values($pola['bulan_ukur'] ?? []) as $i => $m) {
                $out[(int) $m] = $bagi[$i + 1] ?? null;
            }

            return $out;
        }

        // posisi / rilis: nilai di bulan ukur, bukan cicilan.
        $out = $kosong;
        foreach ($pola['bulan_ukur'] ?? [] as $m) {
            $out[(int) $m] = $ada !== [] ? ($ikp[(int) $m] ?? null) : $target;
        }

        return $out;
    }

    /**
     * Porsi bawaan untuk pemikul angka pola hitungan: $total dibagi ke daun
     * menurut bobot (target indikator simpul bila ada, selain itu rata),
     * dengan metode sisa terbesar sehingga Σ = total persis.
     *
     * @param array<int|string, float|int|null> $bobot [kunci => bobot]
     *
     * @return array<int|string, float>
     */
    public static function bagiPorsi(float $total, array $bobot, bool $bulat = true): array
    {
        if ($bobot === []) {
            return [];
        }
        $b = [];
        foreach ($bobot as $k => $v) {
            $b[$k] = ($v !== null && (float) $v > 0) ? (float) $v : null;
        }
        if (array_filter($b, static fn ($v) => $v === null) !== []) {
            $b = array_fill_keys(array_keys($b), 1.0);   // ada yang tanpa bobot → bagi rata
        }
        $sigm  = array_sum($b);
        $skala = $bulat ? 1 : 100;
        $unit  = (int) round($total * $skala);
        $out   = [];
        $sisa  = [];
        $pakai = 0;
        foreach ($b as $k => $v) {
            $tepat   = $unit * $v / $sigm;
            $out[$k] = (int) floor($tepat);
            $sisa[$k] = $tepat - $out[$k];
            $pakai  += $out[$k];
        }
        arsort($sisa);
        foreach (array_keys($sisa) as $k) {
            if ($pakai >= $unit) {
                break;
            }
            $out[$k]++;
            $pakai++;
        }

        return array_map(static fn ($v) => (float) ($v / $skala), $out);
    }

    /** Kata bermakna dari teks (huruf kecil, tanpa kata umum). @return string[] */
    public static function kata(string $teks): array
    {
        $teks = mb_strtolower($teks);
        $teks = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks);
        $out  = [];
        foreach (preg_split('/\s+/', trim($teks)) ?: [] as $k) {
            if (mb_strlen($k) >= 3 && ! in_array($k, self::KATA_UMUM, true) && ! ctype_digit($k)) {
                $out[$k] = $k;
            }
        }

        return array_values($out);
    }

    /**
     * Kemiripan: bagian kata bermakna $acuan (teks IKP) yang muncul di $teks
     * (0..1). Kata ≥ 5 huruf dianggap sama bila yang satu awalan yang lain
     * (sekolah ~ sekolahnya).
     */
    public static function mirip(string $acuan, string $teks): float
    {
        $a = self::kata($acuan);
        if ($a === []) {
            return 0.0;
        }
        $b   = self::kata($teks);
        $cok = 0;
        foreach ($a as $x) {
            foreach ($b as $y) {
                if ($x === $y || (mb_strlen($x) >= 5 && mb_strlen($y) >= 5 && (str_starts_with($x, $y) || str_starts_with($y, $x)))) {
                    $cok++;
                    break;
                }
            }
        }

        return round($cok / count($a), 4);
    }

    /** Dua satuan sepadan? (kosong = tidak diketahui → sepadan). */
    public static function satuanSepadan(?string $a, ?string $b): bool
    {
        $n = static function (?string $s): string {
            $s = mb_strtolower(trim((string) $s));
            if ($s === '' ) {
                return '';
            }
            if (str_contains($s, '%') || str_contains($s, 'persen')) {
                return '%';
            }
            if (str_contains($s, 'indeks') || str_contains($s, 'index')) {
                return 'indeks';
            }
            if (str_contains($s, 'nilai') || str_contains($s, 'skor')) {
                return 'nilai';
            }

            return self::kata($s)[0] ?? $s;
        };
        $x = $n($a);
        $y = $n($b);

        return $x === '' || $y === '' || $x === $y
            || (mb_strlen($x) >= 5 && mb_strlen($y) >= 5 && (str_starts_with($x, $y) || str_starts_with($y, $x)));
    }

    /**
     * Rantai induk tiap baris: induk = baris IKP yang sama pada simpul leluhur
     * TERDEKAT (bukan harus induk langsung: Kepala boleh menurunkan langsung
     * ke pelaksana bila jenjang tengah tidak ikut).
     *
     * @param array<int, int|null> $indukSimpul [node_id => node_id induk | null]
     * @param array<int, int>      $barisPerSimpul [node_id => id baris]
     *
     * @return array<int, int|null> [id baris => id baris induk | null]
     */
    public static function susunInduk(array $indukSimpul, array $barisPerSimpul): array
    {
        $out = [];
        foreach ($barisPerSimpul as $node => $id) {
            $induk = null;
            $cari  = $indukSimpul[$node] ?? null;
            $jaga  = 0;
            while ($cari !== null && $jaga++ < 10) {
                if (isset($barisPerSimpul[$cari])) {
                    $induk = $barisPerSimpul[$cari];
                    break;
                }
                $cari = $indukSimpul[$cari] ?? null;
            }
            $out[$id] = $induk;
        }

        return $out;
    }

    /**
     * Saran realisasi IKP dari laporan eKin para pemikul (halaman realisasi,
     * tombol "Gunakan" — realisasi resmi tetap diisi/disahkan Admin OPD):
     *   hitungan      Σ realisasi pemikul angka TERBAWAH di setiap cabang (baris
     *                 angka tanpa anak angka) — satu hasil dihitung satu kali
     *                 oleh pemiliknya; atasan tidak ikut dijumlah.
     *   posisi/rilis  nilai yang dilaporkan pemikul angka jenjang TERDEKAT ke
     *                 IKP (Eselon III dulu; bila belum melapor, turun satu jenjang).
     * Hanya bulan ukur.
     *
     * @param list<array{id:int, induk:?int, peran:string, level:string}> $baris
     * @param array<int, array<int, list<array{pegawai_id:int, realisasi:float|int|null, pada?:?string}>>> $lapor
     *        [id baris => [bulan => [laporan per pegawai]]]
     *
     * @return array<int, array{nilai:float, cara:string, baris:int[], pegawai:int[], lengkap:bool, level:?string}>
     */
    public static function saranRealisasi(array $pola, array $baris, array $lapor): array
    {
        $angka = array_values(array_filter($baris, static fn ($b) => ($b['peran'] ?? 'angka') === 'angka'));
        if ($angka === []) {
            return [];
        }
        $punyaAnakAngka = [];
        foreach ($angka as $b) {
            if (($b['induk'] ?? null) !== null) {
                $punyaAnakAngka[(int) $b['induk']] = true;
            }
        }
        $urutLevel = array_flip(self::LEVEL);
        usort($angka, static fn ($x, $y) => [$urutLevel[$x['level']] ?? 9, $x['id']] <=> [$urutLevel[$y['level']] ?? 9, $y['id']]);

        $out = [];
        foreach ($pola['bulan_ukur'] ?? [] as $m) {
            $m = (int) $m;
            if (($pola['pola'] ?? '') === 'hitungan') {
                $daun   = array_values(array_filter($angka, static fn ($b) => empty($punyaAnakAngka[(int) $b['id']])));
                $jumlah = 0.0;
                $ada    = false;
                $lengkap = true;
                $ids = $peg = [];
                foreach ($daun as $b) {
                    $isi = array_values(array_filter($lapor[(int) $b['id']][$m] ?? [], static fn ($l) => ($l['realisasi'] ?? null) !== null));
                    if ($isi === []) {
                        $lengkap = false;

                        continue;
                    }
                    $ada   = true;
                    $ids[] = (int) $b['id'];
                    foreach ($isi as $l) {
                        $jumlah += (float) $l['realisasi'];
                        $peg[]   = (int) $l['pegawai_id'];
                    }
                }
                if ($ada) {
                    $out[$m] = ['nilai' => round($jumlah, 4), 'cara' => 'jumlah_porsi', 'baris' => $ids,
                        'pegawai' => array_values(array_unique($peg)), 'lengkap' => $lengkap, 'level' => null];
                }

                continue;
            }
            foreach ($angka as $b) {
                $isi = array_values(array_filter($lapor[(int) $b['id']][$m] ?? [], static fn ($l) => ($l['realisasi'] ?? null) !== null));
                if ($isi === []) {
                    continue;
                }
                usort($isi, static fn ($x, $y) => strcmp((string) ($y['pada'] ?? ''), (string) ($x['pada'] ?? '')));
                $out[$m] = ['nilai' => round((float) $isi[0]['realisasi'], 4), 'cara' => 'pemikul_angka', 'baris' => [(int) $b['id']],
                    'pegawai' => [(int) $isi[0]['pegawai_id']], 'lengkap' => true, 'level' => (string) $b['level']];
                break;
            }
        }

        return $out;
    }

    /**
     * Usulan pendelegasian dari pohon ("Usulkan dari pohon"): simpul dengan
     * teks mirip IKP (atau yang sudah bertaut IKP ini) beserta leluhurnya.
     *   hitungan      semua daun yang cocok memikul angka; porsi dibagi
     *                 proporsional target indikator simpul (satuan sepadan),
     *                 selain itu rata; leluhur = Σ porsi turunannya (terbagi habis).
     *   posisi        satu rantai angka ke simpul paling cocok (seri → paling dalam).
     *   rilis         satu rantai angka ke simpul paling cocok (seri → paling
     *                 DANGKAL: nilai resmi dipegang pejabat, bukan staf) +
     *                 maks. 2 cabang pendukung di bawahnya dengan indikator proses.
     *
     * @param array $ikp   ['nama','satuan','pola'(hitungan|posisi|rilis),'target'(?float),'id']
     * @param array $pohon ['simpul' => [id => ['id','level','induk'(?id),'nama','indikator'=>[ind ids]]],
     *                      'indikator' => [id => ['id','nama','satuan','target'(?float),'ikp_id'(?int)]]]
     *
     * @return array<int, array{peran:string, indikator:int|string, teks:string, satuan:string, target:?float, skor:float}>
     *         [node_id => usulan]; indikator = id atau 'baru'
     */
    public static function usulkan(array $ikp, array $pohon): array
    {
        $ikpId  = (int) ($ikp['id'] ?? 0);
        $acuan  = trim((string) ($ikp['nama'] ?? '') . ' ' . (string) ($ikp['satuan'] ?? ''));
        $acuanN = (string) ($ikp['nama'] ?? '');
        $simpul = $pohon['simpul'] ?? [];
        $ind    = $pohon['indikator'] ?? [];
        $pola   = (string) ($ikp['pola'] ?? 'hitungan');

        // 1) Skor & indikator terbaik per simpul.
        $skor  = [];
        $pilih = [];
        foreach ($simpul as $id => $s) {
            $best  = self::mirip($acuanN, (string) $s['nama']);
            $bInd  = null;
            $bSkor = -1.0;
            foreach ($s['indikator'] ?? [] as $iid) {
                $i = $ind[$iid] ?? null;
                if ($i === null) {
                    continue;
                }
                if ($ikpId > 0 && (int) ($i['ikp_id'] ?? 0) === $ikpId) {
                    $best = 1.0;
                    $bInd = (int) $iid;
                    $bSkor = 2.0;
                    break;
                }
                if (($i['ikp_id'] ?? null) !== null) {
                    continue;   // sudah memikul IKP lain
                }
                $k = self::mirip($acuanN, (string) $i['nama']);
                $best = max($best, $k);
                if ($k > $bSkor && self::satuanSepadan($ikp['satuan'] ?? null, $i['satuan'] ?? null)) {
                    $bSkor = $k;
                    $bInd  = (int) $iid;
                }
            }
            $skor[$id]  = $best;
            $pilih[$id] = ($bInd !== null && $bSkor >= self::AMBANG_MIRIP) ? $bInd : null;
        }
        $calon = array_keys(array_filter($skor, static fn ($v) => $v >= self::AMBANG_MIRIP));
        if ($calon === []) {
            return [];
        }

        $leluhur = static function (int $id) use ($simpul): array {
            $out  = [];
            $cari = $simpul[$id]['induk'] ?? null;
            $jaga = 0;
            while ($cari !== null && isset($simpul[$cari]) && $jaga++ < 10) {
                $out[] = (int) $cari;
                $cari  = $simpul[$cari]['induk'] ?? null;
            }

            return $out;
        };
        $dalam = array_flip(self::LEVEL);
        $angkaBaris = static function (int $id, ?float $target) use ($pilih, $ind, $ikp): array {
            $iid = $pilih[$id] ?? null;

            return [
                'peran'     => 'angka',
                'indikator' => $iid ?? 'baru',
                'teks'      => $iid !== null ? (string) $ind[$iid]['nama'] : (string) ($ikp['nama'] ?? ''),
                'satuan'    => $iid !== null ? (string) ($ind[$iid]['satuan'] ?? '') : (string) ($ikp['satuan'] ?? ''),
                'target'    => $target,
            ];
        };

        $hasil = [];
        if ($pola === 'hitungan') {
            // Daun = calon tanpa calon di bawahnya.
            $adaTurunan = [];
            foreach ($calon as $c) {
                foreach ($leluhur($c) as $l) {
                    $adaTurunan[$l] = true;
                }
            }
            $daun  = array_values(array_filter($calon, static fn ($c) => empty($adaTurunan[$c])));
            $bobot = [];
            foreach ($daun as $d) {
                $iid = $pilih[$d] ?? null;
                $bobot[$d] = $iid !== null ? ($ind[$iid]['target'] ?? null) : null;
            }
            $total = $ikp['target'] ?? null;
            $porsi = $total !== null ? self::bagiPorsi((float) $total, $bobot, abs($total - round($total)) < 1e-9) : array_fill_keys($daun, null);
            foreach ($daun as $d) {
                $hasil[$d] = $angkaBaris($d, $porsi[$d] ?? null) + ['skor' => $skor[$d]];
                foreach ($leluhur($d) as $l) {
                    if (! isset($hasil[$l])) {
                        $hasil[$l] = $angkaBaris($l, 0.0) + ['skor' => $skor[$l] ?? 0.0];
                    }
                    if ($porsi[$d] !== null) {
                        $hasil[$l]['target'] = (float) $hasil[$l]['target'] + (float) $porsi[$d];
                    } else {
                        $hasil[$l]['target'] = null;
                    }
                }
            }

            return $hasil;
        }

        // posisi / rilis: satu rantai angka.
        usort($calon, static function ($x, $y) use ($skor, $simpul, $dalam, $pola) {
            $dx = $dalam[$simpul[$x]['level']] ?? 0;
            $dy = $dalam[$simpul[$y]['level']] ?? 0;
            // skor tinggi dulu; seri → rilis dangkal dulu, posisi dalam dulu.
            return [-$skor[$x], $pola === 'rilis' ? $dx : -$dx, $x] <=> [-$skor[$y], $pola === 'rilis' ? $dy : -$dy, $y];
        });
        $ujung  = (int) $calon[0];
        $target = isset($ikp['target']) ? (float) $ikp['target'] : null;
        $hasil[$ujung] = $angkaBaris($ujung, $target) + ['skor' => $skor[$ujung]];
        foreach ($leluhur($ujung) as $l) {
            $hasil[$l] = $angkaBaris($l, $target) + ['skor' => $skor[$l] ?? 0.0];
        }

        if ($pola === 'rilis') {
            $turunan = array_values(array_filter($calon, static fn ($c) => $c !== $ujung && in_array($ujung, $leluhur((int) $c), true)));
            usort($turunan, static fn ($x, $y) => [-$skor[$x], -($dalam[$simpul[$x]['level']] ?? 0), $x] <=> [-$skor[$y], -($dalam[$simpul[$y]['level']] ?? 0), $y]);
            $proses = self::teksProses($ikp);
            $n = 0;
            foreach ($turunan as $t) {
                if ($n >= 2) {
                    break;
                }
                if (isset($hasil[$t])) {
                    continue;
                }
                $n++;
                foreach (array_merge([(int) $t], $leluhur((int) $t)) as $p) {
                    if (isset($hasil[$p])) {
                        break;
                    }
                    $hasil[$p] = ['peran' => 'pendukung', 'indikator' => 'baru', 'skor' => $skor[$p] ?? 0.0] + $proses;
                }
            }
        }

        return $hasil;
    }

    /**
     * Indikator proses bawaan untuk pendukung (bisa diubah per simpul).
     *
     * @return array{teks:string, satuan:string, target:float}
     */
    public static function teksProses(array $ikp): array
    {
        $nama = trim((string) ($ikp['nama'] ?? 'IKP'));

        return match ((string) ($ikp['pola'] ?? 'hitungan')) {
            'rilis'  => ['teks' => 'Jumlah bukti dukung penilaian ' . $nama . ' yang dilengkapi', 'satuan' => 'Dokumen', 'target' => 12.0],
            'posisi' => ['teks' => 'Jumlah rekap data ' . $nama . ' yang dilaporkan', 'satuan' => 'Laporan', 'target' => 12.0],
            default  => ['teks' => 'Jumlah kegiatan pendukung ' . $nama . ' yang dilaksanakan', 'satuan' => 'Kegiatan', 'target' => 12.0],
        };
    }

    // =================================================================
    // DB — POHON & BARIS
    // =================================================================

    /** Periode IKU yang memuat tahun itu untuk OPD ini (null = tidak ada). */
    public function periodeIku(int $opdId, int $tahun): ?array
    {
        $r = $this->db->table('iku_sasaran')->select('tahun_mulai, tahun_akhir')
            ->where('opd_id', $opdId)->where('tahun_mulai <=', $tahun)->where('tahun_akhir >=', $tahun)
            ->orderBy('tahun_mulai', 'DESC')->limit(1)->get()->getRowArray();

        return $r ? ['awal' => (int) $r['tahun_mulai'], 'akhir' => (int) $r['tahun_akhir']] : null;
    }

    /** Kecamatan menggeser label satu jenjang (aturan PemilikKinerjaController::modusKecamatan). */
    public function kecamatan(int $opdId): bool
    {
        $o = $this->db->table('opd')->select('jenis')->where('id', $opdId)->get()->getRowArray();
        if (($o['jenis'] ?? '') === OpdModel::JENIS_KECAMATAN) {
            return true;
        }

        return $this->db->table('pk')->where('opd_id', $opdId)->where('jenis', 'camat')->countAllResults() > 0;
    }

    /**
     * Pohon AKTIF satu OPD untuk tahun itu — aturan simpul tampil sama dengan
     * Pemilik Kinerja & API eKin: Eselon III berjangkar indikator IKU yang belum
     * dihentikan pada periode yang memuat tahun itu; Eselon IV & pelaksana lewat
     * es3_indikator_id (indikator induk).
     *
     * @return array{simpul: array<int, array>, indikator: array<int, array>, akar: int[], kecamatan: bool, label: array}
     */
    public function pohon(int $opdId, int $tahun, bool $denganPemilik = true): array
    {
        $kec   = $this->kecamatan($opdId);
        $hasil = ['simpul' => [], 'indikator' => [], 'akar' => [], 'kecamatan' => $kec, 'label' => RuangOpdService::labelJenjang($kec)];
        $per   = $this->periodeIku($opdId, $tahun);
        if ($per === null) {
            return $hasil;
        }
        $iku = array_map('intval', array_column($this->db->table('iku_indikator iki')->select('iki.id')
            ->join('iku_sasaran iks', 'iks.id = iki.iku_sasaran_id', 'inner')
            ->where('iks.opd_id', $opdId)->where('iks.tahun_mulai', $per['awal'])->where('iks.tahun_akhir', $per['akhir'])
            ->where('iki.dihentikan_pada', null)->get()->getResultArray(), 'id'));
        if ($iku === []) {
            return $hasil;
        }

        $simpul = [];
        $ind    = [];
        $muat   = function (array $rows) use (&$simpul): array {
            $ids = [];
            foreach ($rows as $r) {
                $id          = (int) $r['id'];
                $ids[]       = $id;
                $simpul[$id] = [
                    'id'        => $id,
                    'level'     => (string) $r['level'],
                    'induk'     => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                    'nama'      => (string) $r['nama_sasaran'],
                    'indikator' => [],
                    'anak'      => [],
                    'pemilik'   => [],
                ];
            }

            return $ids;
        };
        $muatInd = function (array $simpulIds) use (&$ind, &$simpul): array {
            if ($simpulIds === []) {
                return [];
            }
            $ids = [];
            foreach ($this->db->table('cascading_indikator_opd')->select('id, cascading_sasaran_id, indikator, satuan')
                ->whereIn('cascading_sasaran_id', $simpulIds)->orderBy('id', 'ASC')->get()->getResultArray() as $r) {
                $iid = (int) $r['id'];
                $sid = (int) $r['cascading_sasaran_id'];
                $ids[] = $iid;
                $simpul[$sid]['indikator'][] = $iid;
                $ind[$iid] = [
                    'id' => $iid, 'simpul_id' => $sid, 'nama' => (string) $r['indikator'], 'satuan' => (string) ($r['satuan'] ?? ''),
                    'baris' => null, 'target' => null, 'ikp_id' => null,
                ];
            }

            return $ids;
        };
        $dasar = fn () => $this->db->table('cascading_sasaran_opd')->select('id, level, parent_id, es3_indikator_id, nama_sasaran')->orderBy('id', 'ASC');

        $es3  = $muat($dasar()->where('level', 'es3')->where('opd_id', $opdId)->whereIn('iku_indikator_id', $iku)->get()->getResultArray());
        $i3   = $muatInd($es3);
        $es4  = $i3 === [] ? [] : $muat($dasar()->where('level', 'es4')->whereIn('es3_indikator_id', $i3)->get()->getResultArray());
        $i4   = $muatInd($es4);
        $pel  = $i4 === [] ? [] : $muat($dasar()->where('level', 'pelaksana')->whereIn('es3_indikator_id', $i4)->get()->getResultArray());
        $muatInd($pel);

        foreach ($simpul as $id => $s) {
            if ($s['induk'] !== null && isset($simpul[$s['induk']])) {
                $simpul[$s['induk']]['anak'][] = $id;
            } elseif ($s['level'] === 'es3') {
                $hasil['akar'][] = $id;
            }
        }

        if ($ind !== []) {
            foreach ($this->db->table('cascading_indikator_target')->whereIn('cascading_indikator_id', array_keys($ind))
                ->where('tahun', $tahun)->get()->getResultArray() as $r) {
                $iid = (int) $r['cascading_indikator_id'];
                $ind[$iid]['baris']  = $r;
                $ind[$iid]['target'] = $r['target'] !== null ? (float) $r['target'] : null;
                $ind[$iid]['ikp_id'] = $r['ikp_id'] !== null ? (int) $r['ikp_id'] : null;
            }
        }
        if ($denganPemilik && $simpul !== []) {
            foreach ((new PohonPemilikService($this->db))->pemilikUntukSimpul(array_keys($simpul), $tahun) as $p) {
                $simpul[$p['node_id']]['pemilik'][] = $p;
            }
        }

        $hasil['simpul']    = $simpul;
        $hasil['indikator'] = $ind;

        return $hasil;
    }

    /**
     * Baris pendelegasian satu IKP pada satu tahun, lengkap dengan simpul & indikatornya.
     *
     * @return list<array>
     */
    public function baris(int $ikpId, int $tahun): array
    {
        if (! $this->siap()) {
            return [];
        }

        return array_map(static function (array $r): array {
            foreach (['id', 'cascading_indikator_id', 'ikp_id', 'node_id', 'opd_id'] as $k) {
                $r[$k] = (int) $r[$k];
            }
            $r['ikp_induk_id'] = $r['ikp_induk_id'] !== null ? (int) $r['ikp_induk_id'] : null;
            $r['target']       = $r['target'] !== null ? (float) $r['target'] : null;
            $r['ikp_peran']    = $r['ikp_peran'] === 'pendukung' ? 'pendukung' : 'angka';

            return $r;
        }, $this->db->table('cascading_indikator_target cit')
            ->select('cit.*, ci.cascading_sasaran_id AS node_id, ci.indikator, ci.satuan, cs.level, cs.opd_id, cs.parent_id')
            ->join('cascading_indikator_opd ci', 'ci.id = cit.cascading_indikator_id', 'inner')
            ->join('cascading_sasaran_opd cs', 'cs.id = ci.cascading_sasaran_id', 'inner')
            ->where('cit.ikp_id', $ikpId)->where('cit.tahun', $tahun)
            ->orderBy('cit.id', 'ASC')->get()->getResultArray());
    }

    /**
     * Rawat ikp_induk_id semua baris satu IKP menurut pohon (leluhur terdekat
     * yang juga memikul IKP ini). Dipanggil setiap selesai menyimpan.
     */
    public function rapikanInduk(int $ikpId, int $tahun): int
    {
        $baris = $this->baris($ikpId, $tahun);
        if ($baris === []) {
            return 0;
        }
        $opdIds = array_values(array_unique(array_column($baris, 'opd_id')));
        $indukSimpul = [];
        foreach ($this->db->table('cascading_sasaran_opd')->select('id, parent_id')->whereIn('opd_id', $opdIds)->get()->getResultArray() as $s) {
            $indukSimpul[(int) $s['id']] = $s['parent_id'] !== null ? (int) $s['parent_id'] : null;
        }
        $perSimpul = [];
        foreach ($baris as $b) {
            $perSimpul[$b['node_id']] ??= $b['id'];   // satu baris per simpul per IKP
        }
        $induk = self::susunInduk($indukSimpul, $perSimpul);
        $ubah  = 0;
        foreach ($baris as $b) {
            $baru = $induk[$b['id']] ?? null;
            if ($b['ikp_induk_id'] !== $baru) {
                $this->db->table('cascading_indikator_target')->where('id', $b['id'])->update(['ikp_induk_id' => $baru]);
                $ubah++;
            }
        }

        return $ubah;
    }

    /**
     * Ringkasan pendelegasian untuk sekumpulan IKP: cakupan jenjang + pemeriksa
     * terburuk. [ikp_id => ['baris' => n, 'cakupan' => …, 'periksa' => [kode, warna, pesan], 'teks' => …]]
     *
     * @param array<int, array{pola:string, target:?float}> $ikpInfo [ikp_id => pola & target tahunan]
     */
    public function ringkasIkp(array $ikpInfo, int $tahun, bool $kecamatan, array $label): array
    {
        $ids = array_map('intval', array_keys($ikpInfo));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['baris' => 0, 'lama' => 0, 'cakupan' => self::cakupan([], $kecamatan), 'periksa' => null, 'teks' => ''];
        }
        if ($ids === [] || ! $this->siap()) {
            return $out;
        }
        $semua = $this->db->table('cascading_indikator_target cit')
            ->select('cit.id, cit.ikp_id, cit.ikp_peran, cit.ikp_induk_id, cit.target, cit.sumber, cs.level')
            ->join('cascading_indikator_opd ci', 'ci.id = cit.cascading_indikator_id', 'inner')
            ->join('cascading_sasaran_opd cs', 'cs.id = ci.cascading_sasaran_id', 'inner')
            ->whereIn('cit.ikp_id', $ids)->where('cit.tahun', $tahun)->get()->getResultArray();
        $per  = [];
        $lama = [];
        foreach ($semua as $r) {
            // Cakupan & pemeriksa hanya dari baris PENDELEGASIAN (sumber delegasi) — sama dengan yang dikirim ke eKin.
            // Tautan lama (sumber lama, sebelum fitur ini) dihitung terpisah: "tautan lama, belum diturunkan".
            if (($r['sumber'] ?? null) !== 'delegasi') {
                $lama[(int) $r['ikp_id']] = ($lama[(int) $r['ikp_id']] ?? 0) + 1;

                continue;
            }
            $per[(int) $r['ikp_id']][] = [
                'id' => (int) $r['id'], 'induk' => $r['ikp_induk_id'] !== null ? (int) $r['ikp_induk_id'] : null,
                'peran' => $r['ikp_peran'] === 'pendukung' ? 'pendukung' : 'angka',
                'target' => $r['target'] !== null ? (float) $r['target'] : null, 'level' => (string) $r['level'],
            ];
        }
        foreach ($ids as $id) {
            $b = $per[$id] ?? [];
            $c = self::cakupan(array_column($b, 'level'), $kecamatan);
            $out[$id] = [
                'baris'   => count($b),
                'lama'    => $lama[$id] ?? 0,
                'cakupan' => $c,
                'periksa' => self::periksaSemua((string) ($ikpInfo[$id]['pola'] ?? 'hitungan'), $ikpInfo[$id]['target'] ?? null, $b),
                'teks'    => self::cakupanTeks($c, $label),
            ];
        }

        return $out;
    }

    /**
     * Pemeriksa seluruh pohon pendelegasian satu IKP: per induk (akar = IKP).
     * Hasil: daftar per induk + ringkasan terburuk.
     *
     * @param list<array{id:int, induk:?int, peran:string, target:?float, level?:string}> $baris
     *
     * @return array{per_induk: array<string, array>, warna: string, kode: string, pesan: string, n_peringatan: int}
     */
    public static function periksaSemua(string $pola, ?float $targetIkp, array $baris): array
    {
        $byId = [];
        $anak = ['akar' => []];
        foreach ($baris as $b) {
            $byId[$b['id']] = $b;
        }
        foreach ($baris as $b) {
            $k = ($b['induk'] !== null && isset($byId[$b['induk']])) ? (string) $b['induk'] : 'akar';
            $anak[$k][] = $b;
        }
        $per = [];
        $per['akar'] = self::periksa($pola, $targetIkp, $anak['akar'], true);
        foreach ($anak as $k => $list) {
            if ($k === 'akar') {
                continue;
            }
            $ind = $byId[(int) $k];
            $per[$k] = self::periksa($pola, $ind['peran'] === 'angka' ? $ind['target'] : null, $list, false, $ind['peran']);
        }
        $nPer = 0;
        foreach ($per as $p) {
            $nPer += $p['warna'] === 'peringatan' ? 1 : 0;
        }
        $utama = $per['akar'];
        if ($utama['warna'] !== 'peringatan') {
            foreach ($per as $p) {
                if ($p['warna'] === 'peringatan') {
                    $utama = $p;
                    break;
                }
            }
        }

        return ['per_induk' => $per, 'warna' => $nPer > 0 ? 'peringatan' : $per['akar']['warna'], 'kode' => $utama['kode'],
            'pesan' => $utama['pesan'], 'n_peringatan' => $nPer];
    }

    /**
     * Simpul yang memikul IKP (chip "★ IKP" di Pohon Kinerja & Pemilik Kinerja).
     *
     * @return array<int, list<array{ikp_id:int, nama:string, peran:string}>> [node_id => …]
     */
    public function perSimpul(int $opdId, int $tahun): array
    {
        if (! $this->siap()) {
            return [];
        }
        $out = [];
        foreach ($this->db->table('cascading_indikator_target cit')
            ->select('ci.cascading_sasaran_id AS node_id, cit.ikp_id, cit.ikp_peran, i.output_prioritas')
            ->join('cascading_indikator_opd ci', 'ci.id = cit.cascading_indikator_id', 'inner')
            ->join('cascading_sasaran_opd cs', 'cs.id = ci.cascading_sasaran_id', 'inner')
            ->join('ikp i', 'i.id = cit.ikp_id AND i.dihapus_pada IS NULL', 'inner', false)
            ->where('cs.opd_id', $opdId)->where('cit.tahun', $tahun)
            ->orderBy('cit.ikp_id', 'ASC')->get()->getResultArray() as $r) {
            $out[(int) $r['node_id']][] = [
                'ikp_id' => (int) $r['ikp_id'],
                'nama'   => (string) $r['output_prioritas'],
                'peran'  => $r['ikp_peran'] === 'pendukung' ? 'pendukung' : 'angka',
            ];
        }

        return $out;
    }

    /**
     * Rekap Kabupaten: per OPD jumlah IKP aktif, yang sudah diturunkan, dan yang
     * sudah sampai pelaksana. [opd_id => ['ikp','turun','sampai_pelaksana']]
     *
     * @param int[] $opdIds
     */
    public function rekapKabupaten(array $opdIds, int $tahun): array
    {
        $opdIds = array_values(array_unique(array_map('intval', $opdIds)));
        $out    = [];
        foreach ($opdIds as $o) {
            $out[$o] = ['ikp' => 0, 'turun' => 0, 'sampai_pelaksana' => 0];
        }
        if ($opdIds === []) {
            return $out;
        }
        foreach ($this->db->table('ikp')->select('opd_id, COUNT(*) AS n')->whereIn('opd_id', $opdIds)
            ->where('dihapus_pada', null)->groupBy('opd_id')->get()->getResultArray() as $r) {
            $out[(int) $r['opd_id']]['ikp'] = (int) $r['n'];
        }
        if (! $this->siap()) {
            return $out;
        }
        $level = [];
        foreach ($this->db->table('cascading_indikator_target cit')
            ->select('i.opd_id, cit.ikp_id, cs.level')
            ->join('ikp i', 'i.id = cit.ikp_id AND i.dihapus_pada IS NULL', 'inner', false)
            ->join('cascading_indikator_opd ci', 'ci.id = cit.cascading_indikator_id', 'inner')
            ->join('cascading_sasaran_opd cs', 'cs.id = ci.cascading_sasaran_id', 'inner')
            ->whereIn('i.opd_id', $opdIds)->where('cit.tahun', $tahun)->where('cit.sumber', 'delegasi')->get()->getResultArray() as $r) {
            // Hanya pendelegasian (bukan tautan lama) — sama dengan cakupan Turunkan IKP & API eKin.
            $level[(int) $r['opd_id']][(int) $r['ikp_id']][] = (string) $r['level'];
        }
        foreach ($level as $opd => $perIkp) {
            $kec = $this->kecamatan((int) $opd);
            foreach ($perIkp as $lv) {
                $out[$opd]['turun']++;
                $out[$opd]['sampai_pelaksana'] += self::cakupan($lv, $kec)['sampai_pelaksana'] ? 1 : 0;
            }
        }

        return $out;
    }

    // =================================================================
    // DB — SIMPAN
    // =================================================================

    /**
     * Simpan pendelegasian satu IKP (satu tahun) dari isian halaman Turunkan IKP.
     *
     * @param array $ikp    baris IKP (id, opd_id, output_prioritas, satuan_label) + 'pola' (ikp_pola) + 'target' (?float tahunan)
     * @param array $pohon  hasil pohon() OPD IKP itu
     * @param array<int|string, array> $isian [node_id => ['ikut','peran','indikator','teks','satuan','target']]
     *
     * @return array{tambah:int, ubah:int, cabut:int, galat:string[]}
     */
    public function simpan(array $ikp, int $tahun, array $pohon, array $isian, ?int $userId): array
    {
        $ikpId = (int) $ikp['id'];
        $pola  = $ikp['pola'];
        $galat = [];
        $siap  = [];   // node_id => rencana

        foreach ($isian as $node => $v) {
            $node = (int) $node;
            if (! is_array($v) || empty($v['ikut'])) {
                continue;
            }
            $s = $pohon['simpul'][$node] ?? null;
            if ($s === null) {
                $galat[] = 'Simpul #' . $node . ' tidak ada di pohon kinerja aktif perangkat daerah ini.';

                continue;
            }
            $nama  = mb_strimwidth($s['nama'], 0, 60, '…');
            $peran = (string) ($v['peran'] ?? 'angka');
            if (! isset(self::PERAN[$peran])) {
                $galat[] = $nama . ': peran tidak dikenal.';

                continue;
            }
            $pilih = (string) ($v['indikator'] ?? '');
            $indId = null;
            $teks  = '';
            $sat   = '';
            if ($pilih === 'baru') {
                $teks = ikp_rapikan_teks((string) ($v['teks'] ?? ''));
                $sat  = ikp_rapikan_teks((string) ($v['satuan'] ?? ''));
                if (mb_strlen($teks) < 5 || mb_strlen($teks) > 500 || preg_match('/[<>]/', $teks)) {
                    $galat[] = $nama . ': teks indikator baru wajib 5–500 karakter tanpa tanda < >.';

                    continue;
                }
                if (mb_strlen($sat) > 50 || preg_match('/[<>]/', $sat)) {
                    $galat[] = $nama . ': satuan maksimal 50 karakter tanpa tanda < >.';

                    continue;
                }
            } else {
                $indId = ctype_digit($pilih) ? (int) $pilih : 0;
                if (! in_array($indId, $s['indikator'], true)) {
                    $galat[] = $nama . ': pilih indikator simpul ini atau "buat indikator baru".';

                    continue;
                }
                $lain = $pohon['indikator'][$indId]['ikp_id'] ?? null;
                if ($lain !== null && $lain !== $ikpId) {
                    $galat[] = $nama . ': indikator «' . mb_strimwidth($pohon['indikator'][$indId]['nama'], 0, 50, '…')
                        . '» sudah memikul IKP lain (#' . $lain . '). Pilih indikator lain atau buat indikator baru.';

                    continue;
                }
            }

            // Target baris.
            if ($peran === 'angka' && $pola['pola'] !== 'hitungan') {
                if ($ikp['target'] === null) {
                    $galat[] = 'Isi target tahunan IKP tahun ' . $tahun . ' lebih dulu: pemikul angka pola ' . (ikp_pola_meta()[$pola['pola']]['singkat'] ?? $pola['pola']) . ' memakai target utuh.';

                    continue;
                }
                $target = (float) $ikp['target'];
                $metode = in_array($pola['metode'], ['trend_naik', 'trend_turun', 'trend_flat'], true) ? $pola['metode'] : 'trend_naik';
            } else {
                $t = trim((string) ($v['target'] ?? ''));
                if (ikp_angka_kosong($t) || ! ikp_angka_sah($t)) {
                    $galat[] = $nama . ': ' . ($peran === 'angka' ? 'porsi target' : 'target indikator proses') . ' wajib berupa angka (contoh: 1.250 atau 12,5).';

                    continue;
                }
                $target = (float) ikp_angka_baca($t, false);
                if ($target < 0) {
                    $galat[] = $nama . ': target tidak boleh negatif.';

                    continue;
                }
                $metode = 'sum';
            }
            $siap[$node] = compact('peran', 'indId', 'teks', 'sat', 'target', 'metode');
        }
        if ($galat !== []) {
            return ['tambah' => 0, 'ubah' => 0, 'cabut' => 0, 'galat' => array_values(array_unique($galat))];
        }

        $lama     = $this->baris($ikpId, $tahun);
        $lamaNode = [];
        foreach ($lama as $b) {
            $lamaNode[$b['node_id']][] = $b;
        }
        $hitung = ['tambah' => 0, 'ubah' => 0, 'cabut' => 0, 'galat' => []];
        $tbl    = $this->db->table('cascading_indikator_target');
        $kini   = date('Y-m-d H:i:s');

        $dicabut = self::barisDicabut($lama, $siap, array_map('intval', array_keys($pohon['simpul'] ?? [])));

        $this->dalamTransaksi(function () use ($dicabut, $siap, $tbl, $kini, $ikpId, $tahun, $userId, &$hitung): void {
            // 1) Cabut baris yang simpulnya tidak dicentang / indikatornya diganti (hanya simpul yang TAMPIL di formulir).
            foreach ($dicabut as $b) {
                $this->cabut($b);
                $hitung['cabut']++;
            }

            // 2) Terapkan setiap simpul yang dicentang.
            foreach ($siap as $node => $r) {
                $indId  = $r['indId'];
                $dibuat = false;
                if ($indId === null) {
                    $this->db->table('cascading_indikator_opd')->insert([
                        'cascading_sasaran_id' => $node,
                        'indikator'            => $r['teks'],
                        'satuan'               => $r['sat'] !== '' ? $r['sat'] : null,
                        'created_at'           => $kini,
                        'updated_at'           => $kini,
                    ]);
                    $indId  = (int) $this->db->insertID();
                    $dibuat = true;
                }
                $isi = [
                    'target'      => round($r['target'], 4),
                    'target_teks' => ikp_fmt($r['target'], 4),
                    'metode'      => $r['metode'],
                    'ikp_id'      => $ikpId,
                    'ikp_peran'   => $r['peran'],
                    'sumber'      => 'delegasi',
                ];
                $ada = $tbl->where('cascading_indikator_id', $indId)->where('tahun', $tahun)->get()->getRowArray();
                if ($ada) {
                    $jejak = self::jejakSebelum($ada, $ikpId);
                    if ($jejak !== null) {
                        $isi['sebelum_delegasi'] = json_encode($jejak);
                        $isi['dibuat_oleh']      = $userId;
                    } elseif ($ada['dibuat_oleh'] === null) {
                        $isi['dibuat_oleh'] = $userId;
                    }
                    $berubah = false;
                    foreach ($isi as $k => $v) {
                        $a = $ada[$k] ?? null;
                        if ($k === 'target' ? ($a === null || abs((float) $a - (float) $v) > 1e-9) : (string) $a !== (string) $v) {
                            $berubah = true;
                        }
                    }
                    if ($berubah) {
                        $isi['updated_at'] = $kini;
                        $this->db->table('cascading_indikator_target')->where('id', (int) $ada['id'])->update($isi);
                        $hitung['ubah']++;
                    }
                } else {
                    $this->db->table('cascading_indikator_target')->insert($isi + [
                        'cascading_indikator_id' => $indId,
                        'tahun'                  => $tahun,
                        'dibuat_oleh'            => $userId,
                        'sebelum_delegasi'       => json_encode(['ada_baris' => false, 'indikator_dibuat' => $dibuat]),
                        'created_at'             => $kini,
                        'updated_at'             => $kini,
                    ]);
                    $hitung['tambah']++;
                }
            }

            $this->rapikanInduk($ikpId, $tahun);
        }, 'simpan pendelegasian IKP');

        return $hitung;
    }

    /**
     * Baris lama yang dicabut oleh satu kali simpan (murni, diuji): baris yang simpulnya ADA di pohon aktif (tampil di
     * formulir) tetapi tidak dicentang lagi, atau indikatornya diganti.
     *
     * MENGAPA simpul tersembunyi dilewati: formulir hanya merender simpul pohon AKTIF; simpul di bawah IKU yang
     * dihentikan tidak tampil sehingga tidak mungkin "dicentang lagi", dan kiriman untuknya ditolak. Tanpa aturan ini
     * setiap simpan mencabut diam-diam pendelegasian di simpul itu (beserta indikator yang dibuatnya, FK CASCADE) —
     * padahal layar menulis "Pendelegasian itu tetap tersimpan". Pencabutannya hanya lewat aksi yang eksplisit.
     *
     * @param list<array>       $lama        baris() — setiap baris membawa node_id & cascading_indikator_id
     * @param array<int, array> $siap        [node_id => rencana ['indId' => ?int, …]] simpul yang dicentang
     * @param int[]             $simpulAktif node_id pohon aktif (yang tampil di formulir)
     *
     * @return list<array> baris yang dicabut
     */
    public static function barisDicabut(array $lama, array $siap, array $simpulAktif): array
    {
        $aktif = array_flip(array_map('intval', $simpulAktif));
        $out   = [];
        foreach ($lama as $b) {
            $node = (int) $b['node_id'];
            if (! isset($aktif[$node])) {
                continue;   // simpul tersembunyi: tetap tersimpan
            }
            $rencana = $siap[$node] ?? null;
            if ($rencana === null || $rencana['indId'] !== (int) $b['cascading_indikator_id']) {
                $out[] = $b;
            }
        }

        return $out;
    }

    /**
     * Jejak keadaan sebuah baris target SEBELUM pendelegasian IKP ini menimpanya (murni, diuji) — null bila baris
     * itu sudah baris pendelegasian IKP ini (tidak ada yang perlu diingat).
     *   - baris milik IKP lain / tanpa IKP → target, target_teks, metode aslinya;
     *   - TAUTAN LAMA IKP yang sama (sumber `lama`, 8 baris sebelum fitur ini) → juga `sumber = lama` + `ikp_id`,
     *     sehingga cabut() mengembalikannya menjadi tautan lama, bukan membuangnya.
     * MENGAPA: dulu jejak hanya ditulis bila ikp_id berbeda — tautan lama ber-ikp_id sama ditimpa porsi tanpa jejak,
     * dan pencabutan mengosongkan tautannya sambil membiarkan target porsi: tautan & target aslinya hilang permanen.
     *
     * @return array<string, mixed>|null
     */
    public static function jejakSebelum(array $ada, int $ikpId): ?array
    {
        $milik = (int) ($ada['ikp_id'] ?? 0);
        if ($milik === $ikpId && ($ada['sumber'] ?? null) === 'delegasi') {
            return null;
        }
        if ($milik === $ikpId && ! empty($ada['sebelum_delegasi'])) {
            return null;   // jejak sudah ada (tidak ditimpa)
        }
        $jejak = ['ada_baris' => true, 'target' => $ada['target'] ?? null, 'target_teks' => $ada['target_teks'] ?? null,
            'metode' => $ada['metode'] ?? null, 'indikator_dibuat' => false];
        if ($milik === $ikpId) {
            $jejak['sumber'] = 'lama';
            $jejak['ikp_id'] = $ikpId;
        }

        return $jejak;
    }

    /**
     * Cabut satu baris pendelegasian: pulihkan keadaan indikator sebelum IKP
     * diturunkan kepadanya (sebelum_delegasi). Indikator yang DIBUAT oleh
     * pendelegasian dihapus bila tidak dipakai lagi (tidak ada simpul anak
     * yang berjangkar padanya dan tidak ada target tahun lain).
     */
    public function cabut(array $b): void
    {
        $id  = (int) $b['id'];
        $ind = (int) $b['cascading_indikator_id'];
        $sb  = json_decode((string) ($b['sebelum_delegasi'] ?? ''), true);
        $tbl = fn () => $this->db->table('cascading_indikator_target');

        if (is_array($sb) && ! empty($sb['indikator_dibuat'])) {
            $dipakai = $this->db->table('cascading_sasaran_opd')->where('es3_indikator_id', $ind)->countAllResults() > 0
                || $tbl()->where('cascading_indikator_id', $ind)->where('id !=', $id)->countAllResults() > 0
                || $this->db->table('ikp')->where('cascading_indikator_id', $ind)->countAllResults() > 0;
            if (! $dipakai) {
                $this->db->table('cascading_indikator_opd')->where('id', $ind)->delete();   // baris target ikut terhapus (FK CASCADE)

                return;
            }
            $tbl()->where('id', $id)->delete();

            return;
        }
        if (is_array($sb) && empty($sb['ada_baris'])) {
            $tbl()->where('id', $id)->delete();

            return;
        }
        $pulih = ['ikp_id' => null, 'ikp_peran' => 'angka', 'ikp_induk_id' => null, 'sumber' => null,
            'sebelum_delegasi' => null, 'dibuat_oleh' => null, 'updated_at' => date('Y-m-d H:i:s')];
        // Tautan LAMA yang diambil alih pendelegasian: kembali menjadi tautan lama (IKP & target aslinya).
        if (is_array($sb) && ($sb['sumber'] ?? null) === 'lama' && ! empty($sb['ikp_id'])) {
            $pulih['ikp_id'] = (int) $sb['ikp_id'];
            $pulih['sumber'] = 'lama';
        }
        if (is_array($sb)) {
            $pulih += ['target' => $sb['target'] ?? null, 'target_teks' => $sb['target_teks'] ?? null, 'metode' => $sb['metode'] ?? 'sum'];
        }
        $tbl()->where('id', $id)->update($pulih);
    }

    /**
     * Saran "Dari eKin (pemikul angka)" untuk semua IKP satu OPD pada halaman realisasi.
     *
     * @param list<array> $rekap    IkpRekapService::rekapOpd()
     * @param array|null  $dataEkin EkinClient::ikpTurunan() (null = eKin belum menyediakan → tanpa saran)
     *
     * @return array<int, array<int, array>> [ikp_id => [bulan => saranRealisasi()]]
     */
    public function saranUntukOpd(array $rekap, int $tahun, ?array $dataEkin): array
    {
        if ($dataEkin === null || ! $this->siap()) {
            return [];
        }
        $lapor = [];   // [delegasi_id => [bulan => [laporan]]]
        foreach ($dataEkin['baris'] ?? [] as $b) {
            if (! is_array($b) || ! isset($b['delegasi_id'])) {
                continue;
            }
            foreach ((array) ($b['bulan'] ?? []) as $m => $v) {
                if (! is_array($v) || ! array_key_exists('realisasi', $v) || $v['realisasi'] === null || ! is_numeric($v['realisasi'])) {
                    continue;
                }
                $lapor[(int) $b['delegasi_id']][(int) $m][] = [
                    'pegawai_id' => (int) ($b['pegawai_id'] ?? 0), 'realisasi' => (float) $v['realisasi'], 'pada' => (string) ($v['pada'] ?? ''),
                ];
            }
        }
        if ($lapor === []) {
            return [];
        }
        $out = [];
        foreach ($rekap as $r) {
            $id    = (int) $r['ikp']['id'];
            $baris = array_map(static fn ($b) => ['id' => $b['id'], 'induk' => $b['ikp_induk_id'], 'peran' => $b['ikp_peran'], 'level' => $b['level']],
                array_values(array_filter($this->baris($id, $tahun), static fn ($b) => ($b['sumber'] ?? '') === 'delegasi')));
            if ($baris === []) {
                continue;
            }
            $s = self::saranRealisasi($r['pola'], $baris, $lapor);
            if ($s !== []) {
                $out[$id] = $s;
            }
        }

        return $out;
    }

    // =================================================================
    // DB — UNTUK eKin (api/ekin/pegawai/{id}/kinerja, alasan "delegasi")
    // =================================================================

    /**
     * Baris pendelegasian yang dipikul seorang pegawai (pemilik simpulnya) pada
     * tahun itu, dikelompokkan per IKP. Setiap baris: peran, porsi, profil
     * bulanan menurut pola, indikator simpul, rantai induk (untuk "RHK pimpinan
     * yang diintervensi"). Hanya sumber `delegasi` (tautan `lama` tetap lewat
     * alasan `simpul` seperti sebelumnya).
     *
     * @param int[] $simpulMilik node_id yang dimiliki pegawai (sudah tersaring aktif)
     * @param array<int, array> $polaPerIkp [ikp_id => ikp_pola()]
     * @param array<int, array<int, float|null>> $bulananIkp [ikp_id => [1..12 => target bulanan IKP]]
     * @param array<int, ?float> $targetIkp [ikp_id => target tahunan]
     *
     * @return array<int, list<array>> [ikp_id => [baris delegasi]]
     */
    public function untukPegawai(array $simpulMilik, int $tahun, array $polaPerIkp, array $bulananIkp, array $targetIkp, array $label): array
    {
        if ($simpulMilik === [] || ! $this->siap()) {
            return [];
        }
        $rows = $this->db->table('cascading_indikator_target cit')
            ->select('cit.*, ci.cascading_sasaran_id AS node_id, ci.indikator, ci.satuan, cs.level, cs.nama_sasaran, cs.opd_id')
            ->join('cascading_indikator_opd ci', 'ci.id = cit.cascading_indikator_id', 'inner')
            ->join('cascading_sasaran_opd cs', 'cs.id = ci.cascading_sasaran_id', 'inner')
            ->join('ikp i', 'i.id = cit.ikp_id AND i.dihapus_pada IS NULL', 'inner', false)
            ->whereIn('ci.cascading_sasaran_id', $simpulMilik)->where('cit.tahun', $tahun)
            ->where('cit.sumber', 'delegasi')->orderBy('cit.ikp_id', 'ASC')->orderBy('cit.id', 'ASC')
            ->get()->getResultArray();
        if ($rows === []) {
            return [];
        }

        $rantai = $this->rantaiInduk(array_map(static fn ($r) => (int) $r['id'], $rows), $tahun, $label);
        // Pemilik simpul baris ini (penanggung jawab lebih dulu): eKin membagi porsi HITUNGAN simpul yang dimiliki
        // beberapa orang menjadi porsi per orang — tanpa ini setiap pemilik menarik porsi simpul utuh (terhitung ganda).
        $pemilikSimpul = [];
        foreach ((new PohonPemilikService($this->db))->pemilikUntukSimpul(array_map(static fn ($r) => (int) $r['node_id'], $rows), $tahun) as $p) {
            $pemilikSimpul[(int) $p['node_id']][] = (int) $p['pegawai_id'];
        }
        $out    = [];
        foreach ($rows as $r) {
            $ikpId  = (int) $r['ikp_id'];
            $pola   = $polaPerIkp[$ikpId] ?? null;
            if ($pola === null) {
                continue;
            }
            $peran  = $r['ikp_peran'] === 'pendukung' ? 'pendukung' : 'angka';
            $target = $r['target'] !== null ? (float) $r['target'] : null;
            $profil = self::profilBulanan($pola, $bulananIkp[$ikpId] ?? [], $target, $peran);
            $out[$ikpId][] = [
                'delegasi_id'     => (int) $r['id'],
                'node_id'         => (int) $r['node_id'],
                'level'           => (string) $r['level'],
                'level_label'     => $label[(int) $r['opd_id']][$r['level']] ?? (string) $r['level'],
                'sasaran'         => (string) $r['nama_sasaran'],
                'peran'           => $peran,
                'indikator_id'    => (int) $r['cascading_indikator_id'],
                'indikator'       => (string) $r['indikator'],
                'satuan'          => ($r['satuan'] ?? '') !== '' ? (string) $r['satuan'] : null,
                // angka: porsi (hitungan) atau target utuh (posisi/rilis); pendukung: target indikator proses.
                'porsi_target_tahunan' => $target,
                'target_teks'     => $r['target_teks'] ?? null,
                'porsi_persen'    => $peran === 'angka' && $pola['pola'] === 'hitungan' && ($targetIkp[$ikpId] ?? 0) > 0 && $target !== null
                    ? round($target / (float) $targetIkp[$ikpId] * 100, 2) : null,
                'metode'          => (string) $r['metode'],
                // pola indikator baris ini: angka mengikuti IKP; pendukung = indikator proses (hitungan bulanan).
                'pola_indikator'  => $peran === 'angka' ? $pola['pola'] : 'hitungan',
                'bulan_ukur_indikator' => $peran === 'angka' ? array_values(array_map('intval', $pola['bulan_ukur'])) : range(1, 12),
                'target_bulanan'  => $profil,
                'induk_delegasi_id' => $r['ikp_induk_id'] !== null ? (int) $r['ikp_induk_id'] : null,
                'rantai_induk'    => $rantai[(int) $r['id']] ?? [],
                'pemilik_pegawai_ids' => array_values(array_unique($pemilikSimpul[(int) $r['node_id']] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * Rantai induk tiap baris sampai IKP (akar = Kepala OPD, pemilik IKP).
     *
     * @param int[] $ids
     *
     * @return array<int, list<array>> [id => [induk langsung, induk berikutnya, …, akar Kepala OPD]]
     */
    public function rantaiInduk(array $ids, int $tahun, array $label = []): array
    {
        if ($ids === []) {
            return [];
        }
        // Muat semua baris IKP yang sama (maks. beberapa ratus) sekali jalan.
        $ikpIds = array_map('intval', array_column($this->db->table('cascading_indikator_target')->select('ikp_id')
            ->whereIn('id', $ids)->get()->getResultArray(), 'ikp_id'));
        $semua = $this->db->table('cascading_indikator_target cit')
            ->select('cit.id, cit.ikp_id, cit.ikp_induk_id, cit.ikp_peran, cit.target, cit.cascading_indikator_id,
                      ci.indikator, ci.cascading_sasaran_id AS node_id, cs.level, cs.nama_sasaran, cs.opd_id')
            ->join('cascading_indikator_opd ci', 'ci.id = cit.cascading_indikator_id', 'inner')
            ->join('cascading_sasaran_opd cs', 'cs.id = ci.cascading_sasaran_id', 'inner')
            ->whereIn('cit.ikp_id', array_values(array_unique($ikpIds)))->where('cit.tahun', $tahun)
            ->get()->getResultArray();
        $byId = [];
        foreach ($semua as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $nodeIds = array_values(array_unique(array_map(static fn ($r) => (int) $r['node_id'], $semua)));
        $pemilik = [];
        foreach ((new PohonPemilikService($this->db))->pemilikUntukSimpul($nodeIds, $tahun) as $p) {
            $pemilik[$p['node_id']][] = $p['pegawai_id'];
        }
        $kepala = [];
        $opdIkp = [];
        foreach ($this->db->table('ikp')->select('id, opd_id')->whereIn('id', array_values(array_unique($ikpIds)))->get()->getResultArray() as $i) {
            $opdIkp[(int) $i['id']] = (int) $i['opd_id'];
        }
        $pps = new PohonPemilikService($this->db);
        foreach (array_unique($opdIkp) as $o) {
            $es2 = $pps->pemilikEs2($o, $tahun);
            $kepala[$o] = $es2 !== [] ? [(int) end($es2)['pegawai_id']] : [];
        }

        $out = [];
        foreach ($ids as $id) {
            $r = $byId[$id] ?? null;
            if ($r === null) {
                continue;
            }
            $list = [];
            $cari = $r['ikp_induk_id'] !== null ? (int) $r['ikp_induk_id'] : null;
            $jaga = 0;
            while ($cari !== null && isset($byId[$cari]) && $jaga++ < 10) {
                $p = $byId[$cari];
                $list[] = [
                    'delegasi_id'         => (int) $p['id'],
                    'node_id'             => (int) $p['node_id'],
                    'level'               => (string) $p['level'],
                    'level_label'         => $label[(int) $p['opd_id']][$p['level']] ?? (string) $p['level'],
                    'sasaran'             => (string) $p['nama_sasaran'],
                    'indikator_id'        => (int) $p['cascading_indikator_id'],
                    'indikator'           => (string) $p['indikator'],
                    'peran'               => $p['ikp_peran'] === 'pendukung' ? 'pendukung' : 'angka',
                    'target'              => $p['target'] !== null ? (float) $p['target'] : null,
                    'pemilik_pegawai_ids' => $pemilik[(int) $p['node_id']] ?? [],
                ];
                $cari = $p['ikp_induk_id'] !== null ? (int) $p['ikp_induk_id'] : null;
            }
            $opd    = $opdIkp[(int) $r['ikp_id']] ?? (int) $r['opd_id'];
            $list[] = [
                'delegasi_id'         => null,
                'node_id'             => null,
                'level'               => 'es2',
                'level_label'         => $label[$opd]['es2'] ?? 'Eselon II',
                'sasaran'             => null,
                'indikator_id'        => null,
                'indikator'           => null,
                'peran'               => 'pemilik_ikp',
                'target'              => null,
                'pemilik_pegawai_ids' => $kepala[$opd] ?? [],
            ];
            $out[$id] = $list;
        }

        return $out;
    }
}
