<?php

namespace App\Services;

use Throwable;

/**
 * AKSARA+ — klien baca ke eKin Internal Pringsewu (arah eKin -> AKSARA+).
 *
 * Kontrak (eKin yang menyediakan, AKSARA+ yang memakai), semuanya GET + JSON, header
 * "Authorization: Bearer <token>", dasar <EKIN_INTERNAL_URL>api/aksara/:
 *
 *   ringkas?tahun=                     {"tahun","diperbarui","opd":{"<opd_id>": RINGKAS}}
 *   opd/{id}/ringkas?tahun=            RINGKAS
 *   opd/{id}/cascading?tahun=          {"opd_id","tahun","pegawai":[...],"rhk":[...]}
 *   opd/{id}/pk-pegawai?tahun=         {"opd_id","tahun","pk":[PKRINGKAS]}
 *   pk-pegawai/{pegawai_id}?tahun=     PKRINGKAS + pegawai/pihak_kedua lengkap + baris (rhk_id, sumber_tipe) + catatan + teks
 *
 * MENGAPA setiap kegagalan menjadi null (bukan pengecualian): Ruang OPD adalah halaman
 * baca yang merangkum BANYAK sumber. eKin yang sedang dipasang ulang, token yang belum
 * diisi, atau jaringan lokal yang tersendat tidak boleh merobohkan seluruh halaman —
 * cukup satu bagian yang berkata "Data eKin belum tersedia". Alasannya disimpan di
 * alasanTerakhir() untuk ditampilkan dengan bahasa manusia.
 *
 * MENGAPA ada cache 5 menit: angka eKin dipakai di matriks semua OPD dan di tiap hub;
 * tanpa cache setiap klik menunggu eKin menghitung ulang ringkasan seluruh pegawai.
 * Kegagalan juga di-cache (1 menit) supaya eKin yang mati tidak membuat setiap halaman
 * menunggu batas waktu 5 detik berulang-ulang.
 *
 * Token TIDAK PERNAH dicatat ke log, tidak ikut kunci cache, dan tidak dikembalikan ke
 * pemanggil. Pesan log hanya memuat jalur, kode HTTP, dan jenis galat.
 */
class EkinClient
{
    public const BATAS_WAKTU   = 5;    // detik
    public const UMUR_CACHE    = 300;  // detik (5 menit)
    public const UMUR_GAGAL    = 60;   // detik — kegagalan di-cache lebih singkat

    /** Kode alasan -> kalimat untuk layar (tanpa detail teknis yang membingungkan). */
    public const ALASAN = [
        'belum_dikonfigurasi' => 'Sambungan ke eKin belum dikonfigurasi di server ini.',
        'tidak_terjangkau'    => 'eKin tidak dapat dihubungi saat ini.',
        'ditolak'             => 'eKin menolak permintaan AKSARA (token tidak cocok).',
        'belum_tersedia'      => 'Layanan data eKin untuk AKSARA belum dipasang.',
        'galat_server'        => 'eKin sedang mengalami gangguan.',
        'format'              => 'Jawaban eKin tidak sesuai kontrak data.',
    ];

    private string $base;
    private string $token;

    /** @var callable(string $url, array $header): array{0:int, 1:string} */
    private $pengambil;

    /** @var \CodeIgniter\Cache\CacheInterface|null */
    private $cache;

    private ?string $alasan = null;

    /**
     * @param callable|null $pengambil untuk uji: fn(string $url, array $header): [kodeHttp, badan]
     * @param mixed         $cache     CacheInterface; false = tanpa cache (uji)
     */
    public function __construct(?string $base = null, ?string $token = null, ?callable $pengambil = null, $cache = null)
    {
        $this->base      = trim((string) ($base ?? env('EKIN_INTERNAL_URL', '')));
        $this->token     = trim((string) ($token ?? env('EKIN_AKSARA_TOKEN', '')));
        $this->pengambil = $pengambil ?? [$this, 'ambilLewatCurl'];
        $this->cache     = $cache === false ? null : ($cache ?? cache());
    }

    /** Alamat & token sudah diisi? (belum = tidak usah mencoba menghubungi). */
    public function terkonfigurasi(): bool
    {
        return $this->base !== '' && $this->token !== '';
    }

    /** Kode alasan kegagalan terakhir (kunci ALASAN), null bila berhasil. */
    public function alasanTerakhir(): ?string
    {
        return $this->alasan;
    }

    /** Kalimat alasan kegagalan terakhir untuk layar. */
    public function pesanTerakhir(): string
    {
        return self::ALASAN[$this->alasan ?? ''] ?? 'Data eKin belum tersedia.';
    }

    // =================================================================
    // ENDPOINT KONTRAK
    // =================================================================

    /** @return array<string,mixed>|null {"tahun","diperbarui","opd":{"<id>":RINGKAS}} */
    public function ringkasSemua(int $tahun): ?array
    {
        $j = $this->ambil('ringkas', $tahun);
        if ($j !== null && ! is_array($j['opd'] ?? null)) {
            return $this->gagal('format', 'ringkas');
        }

        return $j;
    }

    /** @return array<string,mixed>|null RINGKAS */
    public function ringkasOpd(int $opdId, int $tahun): ?array
    {
        $j = $this->ambil('opd/' . $opdId . '/ringkas', $tahun);
        if ($j !== null && ! self::ringkasSah($j)) {
            return $this->gagal('format', 'opd/ringkas');
        }

        return $j;
    }

    /** @return array<string,mixed>|null {"opd_id","tahun","pegawai":[],"rhk":[]} */
    public function cascading(int $opdId, int $tahun): ?array
    {
        $j = $this->ambil('opd/' . $opdId . '/cascading', $tahun);
        if ($j !== null && (! is_array($j['pegawai'] ?? null) || ! is_array($j['rhk'] ?? null))) {
            return $this->gagal('format', 'opd/cascading');
        }

        return $j;
    }

    /** @return array<string,mixed>|null {"opd_id","tahun","pk":[PKRINGKAS]} */
    public function pkPegawaiOpd(int $opdId, int $tahun): ?array
    {
        $j = $this->ambil('opd/' . $opdId . '/pk-pegawai', $tahun);
        if ($j !== null && ! is_array($j['pk'] ?? null)) {
            return $this->gagal('format', 'opd/pk-pegawai');
        }

        return $j;
    }

    /** @return array<string,mixed>|null PKRINGKAS + baris + catatan */
    public function pkPegawai(int $pegawaiId, int $tahun): ?array
    {
        $j = $this->ambil('pk-pegawai/' . $pegawaiId, $tahun);
        if ($j !== null && (! is_array($j['pegawai'] ?? null) || ! array_key_exists('status', $j))) {
            return $this->gagal('format', 'pk-pegawai');
        }

        return $j;
    }

    /**
     * Rencana aksi bulanan semua pegawai satu OPD (eKin api/aksara/opd/{id}/rencana-aksi).
     *
     * @return array<string,mixed>|null {"opd_id","tahun","bulan","pegawai":[{pegawai_id,nama,jabatan,jenis_jabatan,
     *                                  fiktif,atasan_pegawai_id,ppk_pegawai_id,skp:{id,status}|null,ra:{jumlah,tercapai,
     *                                  selesai,berjalan,belum_ada_kegiatan,capaian}}]}
     */
    public function rencanaAksiOpd(int $opdId, int $tahun, int $bulan): ?array
    {
        $j = $this->ambil('opd/' . $opdId . '/rencana-aksi', $tahun, ['bulan' => max(1, min(12, $bulan))]);
        if ($j !== null && ! is_array($j['pegawai'] ?? null)) {
            return $this->gagal('format', 'opd/rencana-aksi');
        }

        return $j;
    }

    /**
     * Rencana aksi setahun satu pegawai: SKP → RHK → IKI → rencana aksi bulan 1–12 + kemajuan.
     *
     * @return array<string,mixed>|null {"tahun","pegawai","skp","rhk":[{id,jenis,rumusan,rhk_atasan,iki[],rencana_aksi[]}]}
     */
    public function rencanaAksiPegawai(int $pegawaiId, int $tahun): ?array
    {
        $j = $this->ambil('pegawai/' . $pegawaiId . '/rencana-aksi', $tahun);
        if ($j !== null && (! is_array($j['pegawai'] ?? null) || ! is_array($j['rhk'] ?? null))) {
            return $this->gagal('format', 'pegawai/rencana-aksi');
        }

        return $j;
    }

    // =================================================================
    // BENTUK DATA (murni — diuji tanpa jaringan)
    // =================================================================

    /** RINGKAS memuat semua kelompok angka yang dibaca halaman? */
    public static function ringkasSah(array $r): bool
    {
        foreach (['pegawai', 'skp', 'bulanan', 'predikat', 'harian', 'penugasan', 'pk_pegawai', 'cascading'] as $k) {
            if (! is_array($r[$k] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pohon cascading pegawai dari endpoint 3: simpul = RHK, anak = RHK yang
     * rhk_atasan_id-nya menunjuk simpul itu. Akar = RHK tanpa atasan di OPD ini
     * (mis. Kepala yang mengintervensi RHK Bupati, atau atasan di luar data).
     *
     * Urutan akar mengikuti jenjang jabatan (JPT dulu, lalu administrator,
     * pengawas, fungsional, pelaksana), supaya pohon terbaca dari Kepala ke staf.
     * RHK yang atasannya menunjuk ke dirinya sendiri / membentuk lingkaran
     * diputus di akar (tidak pernah rekursi tanpa ujung).
     *
     * @return array{akar: list<array>, pegawai: array<int,array>, jumlah: array<string,int>}
     */
    public static function bangunPohon(array $data): array
    {
        $pegawai = [];
        foreach ($data['pegawai'] ?? [] as $p) {
            if (isset($p['id'])) {
                $pegawai[(int) $p['id']] = $p;
            }
        }

        $rhk = [];
        foreach ($data['rhk'] ?? [] as $r) {
            if (isset($r['id'])) {
                $r['anak'] = [];
                $rhk[(int) $r['id']] = $r;
            }
        }

        // Tautkan anak ke induk; catat yang akarnya sendiri.
        $akarIds = [];
        $efektif = []; // induk yang BENAR-BENAR dipakai (0 = diputus jadi akar) — dasar deteksi lingkaran
        foreach ($rhk as $id => $r) {
            $induk = isset($r['rhk_atasan_id']) && $r['rhk_atasan_id'] !== null ? (int) $r['rhk_atasan_id'] : 0;
            if ($induk > 0 && $induk !== $id && isset($rhk[$induk]) && ! self::membentukLingkaran($rhk, $efektif, $id, $induk)) {
                $rhk[$induk]['anak'][] = $id;
                $efektif[$id] = $induk;
            } else {
                $akarIds[] = $id;
                $efektif[$id] = 0;
            }
        }

        $peringkat = static function (array $r) use ($pegawai): array {
            $p = $pegawai[(int) ($r['pegawai_id'] ?? 0)] ?? [];

            return [self::peringkatJabatan((string) ($p['jenis_jabatan'] ?? '')), (string) ($p['nama'] ?? ''), (int) $r['id']];
        };

        $susun = static function (int $id) use (&$susun, &$rhk, $peringkat): array {
            $n     = $rhk[$id];
            $anak  = array_map(static fn ($a) => $rhk[$a], $n['anak']);
            usort($anak, static fn ($a, $b) => $peringkat($a) <=> $peringkat($b));
            $n['anak'] = array_map(static fn ($a) => $susun((int) $a['id']), $anak);

            return $n;
        };

        $akar = array_map(static fn ($id) => $rhk[$id], $akarIds);
        usort($akar, static fn ($a, $b) => $peringkat($a) <=> $peringkat($b));
        $akar = array_map(static fn ($a) => $susun((int) $a['id']), $akar);

        $jumlah = ['rhk' => count($rhk), 'pegawai' => count($pegawai), 'lengkap' => 0, 'kurang' => 0, 'lebih' => 0,
                   'beda_satuan' => 0, 'tanpa_bawahan' => 0];
        foreach ($rhk as $r) {
            $s = (string) ($r['porsi']['status'] ?? '');
            if (isset($jumlah[$s])) {
                $jumlah[$s]++;
            }
        }

        return ['akar' => $akar, 'pegawai' => $pegawai, 'jumlah' => $jumlah];
    }

    /**
     * Apakah menautkan $id ke $induk membuat lingkaran (induk ternyata keturunan $id)?
     * Rantai ditelusuri lewat induk EFEKTIF bila simpul itu sudah diputuskan, supaya
     * lingkaran A↔B diputus sekali saja (satu jadi akar, yang lain tetap anaknya).
     */
    private static function membentukLingkaran(array $rhk, array $efektif, int $id, int $induk): bool
    {
        $lihat = [];
        $k     = $induk;
        while ($k > 0 && isset($rhk[$k]) && ! isset($lihat[$k])) {
            if ($k === $id) {
                return true;
            }
            $lihat[$k] = true;
            $k = array_key_exists($k, $efektif) ? $efektif[$k]
                : (isset($rhk[$k]['rhk_atasan_id']) ? (int) $rhk[$k]['rhk_atasan_id'] : 0);
        }

        return $k === $id;
    }

    /** Jenjang jabatan untuk urutan (kecil = lebih tinggi). */
    /**
     * Pegawai (jawaban rencanaAksiOpd) disusun per atasan langsung (atasan_pegawai_id), untuk daftar bertingkat:
     * pimpinan lebih dulu, bawahan di bawahnya. Atasan yang tidak ada di daftar (mis. Bupati) = akar. Aman dari
     * lingkaran (setiap pegawai hanya dikunjungi sekali; sisa yang tak terjangkau ditaruh sebagai akar).
     *
     * @param list<array<string,mixed>> $pegawai
     *
     * @return list<array{p: array<string,mixed>, tingkat: int, bawahan: int}>
     */
    public static function susunPerAtasan(array $pegawai): array
    {
        $per = [];
        foreach ($pegawai as $p) {
            if (isset($p['pegawai_id'])) {
                $per[(int) $p['pegawai_id']] = $p;
            }
        }
        $anak = [];
        $akar = [];
        foreach ($per as $id => $p) {
            $a = (int) ($p['atasan_pegawai_id'] ?? 0);
            if ($a > 0 && $a !== $id && isset($per[$a])) {
                $anak[$a][] = $id;
            } else {
                $akar[] = $id;
            }
        }
        $urut = static fn (int $x, int $y) => [self::peringkatJabatan((string) ($per[$x]['jenis_jabatan'] ?? '')), (string) ($per[$x]['nama'] ?? '')]
            <=> [self::peringkatJabatan((string) ($per[$y]['jenis_jabatan'] ?? '')), (string) ($per[$y]['nama'] ?? '')];

        $hasil   = [];
        $dilihat = [];
        $hitung  = static function (int $id, array $jejak = []) use (&$hitung, $anak): int {
            $n = 0;
            $jejak[$id] = true;
            foreach ($anak[$id] ?? [] as $a) {
                if (! isset($jejak[$a])) {
                    $n += 1 + $hitung($a, $jejak);
                }
            }

            return $n;
        };
        $jalan = static function (int $id, int $tingkat) use (&$jalan, &$hasil, &$dilihat, $anak, $per, $urut, $hitung): void {
            if (isset($dilihat[$id])) {
                return;
            }
            $dilihat[$id] = true;
            $hasil[]      = ['p' => $per[$id], 'tingkat' => $tingkat, 'bawahan' => $hitung($id)];
            $daftar       = $anak[$id] ?? [];
            usort($daftar, $urut);
            foreach ($daftar as $a) {
                $jalan($a, $tingkat + 1);
            }
        };
        usort($akar, $urut);
        foreach ($akar as $id) {
            $jalan($id, 0);
        }
        foreach (array_keys($per) as $id) {   // sisa lingkaran
            $jalan($id, 0);
        }

        return $hasil;
    }

    public static function peringkatJabatan(string $jenis): int
    {
        $j = strtolower($jenis);

        return match (true) {
            str_contains($j, 'jpt'), str_contains($j, 'pimpinan'), str_contains($j, 'kepala') => 0,
            str_contains($j, 'administrator')                                                   => 1,
            str_contains($j, 'pengawas')                                                        => 2,
            str_contains($j, 'fungsional'), $j === 'jf'                                         => 3,
            str_contains($j, 'pelaksana')                                                       => 4,
            default                                                                              => 5,
        };
    }

    /** IKI aspek kuantitas pertama (untuk angka target di pohon). */
    public static function ikiKuantitas(array $rhk): ?array
    {
        foreach ($rhk['iki'] ?? [] as $i) {
            if (strtolower((string) ($i['aspek'] ?? '')) === 'kuantitas') {
                return $i;
            }
        }

        return ($rhk['iki'] ?? [])[0] ?? null;
    }

    // =================================================================
    // JARINGAN
    // =================================================================

    /** @return array<string,mixed>|null */
    private function ambil(string $jalur, int $tahun, array $param = []): ?array
    {
        $this->alasan = null;

        if (! $this->terkonfigurasi()) {
            $this->alasan = 'belum_dikonfigurasi';

            return null;
        }

        $url   = rtrim($this->base, '/') . '/api/aksara/' . $jalur . '?' . http_build_query(['tahun' => $tahun] + $param);
        $kunci = 'ekin_aksara_' . md5($url);

        if ($this->cache !== null) {
            $simpan = $this->cache->get($kunci);
            if (is_array($simpan) && array_key_exists('ok', $simpan)) {
                if ($simpan['ok']) {
                    return $simpan['data'];
                }
                $this->alasan = (string) $simpan['alasan'];

                return null;
            }
        }

        try {
            [$kode, $badan] = ($this->pengambil)($url, [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
            ]);
        } catch (Throwable $e) {
            return $this->simpanGagal($kunci, 'tidak_terjangkau', $jalur, get_class($e));
        }

        if ($kode === 401 || $kode === 403) {
            return $this->simpanGagal($kunci, 'ditolak', $jalur, 'HTTP ' . $kode);
        }
        if ($kode === 404 || $kode === 405 || $kode === 503) {
            return $this->simpanGagal($kunci, 'belum_tersedia', $jalur, 'HTTP ' . $kode);
        }
        if ($kode < 200 || $kode >= 300) {
            return $this->simpanGagal($kunci, $kode >= 500 ? 'galat_server' : 'tidak_terjangkau', $jalur, 'HTTP ' . $kode);
        }

        $json = json_decode((string) $badan, true);
        if (! is_array($json)) {
            return $this->simpanGagal($kunci, 'format', $jalur, 'bukan JSON');
        }

        $this->cache?->save($kunci, ['ok' => true, 'data' => $json], self::UMUR_CACHE);

        return $json;
    }

    private function simpanGagal(string $kunci, string $alasan, string $jalur, string $detail): ?array
    {
        $this->alasan = $alasan;
        // Token sengaja tidak pernah ikut: hanya jalur kontrak & ringkas galatnya.
        log_message('warning', 'EkinClient: ' . $jalur . ' gagal (' . $alasan . ', ' . $detail . ')');
        $this->cache?->save($kunci, ['ok' => false, 'alasan' => $alasan], self::UMUR_GAGAL);

        return null;
    }

    private function gagal(string $alasan, string $jalur): ?array
    {
        $this->alasan = $alasan;
        log_message('warning', 'EkinClient: ' . $jalur . ' tidak sesuai kontrak');

        return null;
    }

    /**
     * Pengambil bawaan: CURLRequest BARU per panggilan (bukan instans bersama —
     * header Authorization tidak boleh terbawa ke permintaan lain di proses ini).
     *
     * @return array{0:int, 1:string}
     */
    private function ambilLewatCurl(string $url, array $header): array
    {
        $klien = \Config\Services::curlrequest([
            'timeout'         => self::BATAS_WAKTU,
            'connect_timeout' => 3,
            'http_errors'     => false,
            'headers'         => $header,
        ], null, null, false);

        $res = $klien->get($url);

        return [$res->getStatusCode(), (string) $res->getBody()];
    }
}
