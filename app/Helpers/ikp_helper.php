<?php

/**
 * =====================================================================
 * IKP — Indikator Kinerja Prioritas: rumus murni (tanpa DB)
 * =====================================================================
 *
 * Dipakai bersama oleh AdminOpd\IkpController, IkpRekapService, lampiran PK,
 * dasbor Kabupaten/Bupati, dan API eKin. Karena itu SEMUA fungsi di sini
 * murni: menerima angka, mengembalikan angka/array, tidak membaca sesi atau
 * basis data — sehingga bisa dikunci dengan tests/unit/IkpRekapTest.php.
 *
 * MENGAPA ADA PARSER ANGKA SENDIRI (ikp_angka_baca), BUKAN capaianToFloat()
 *   capaianToFloat("302.663") = 302,663 (titik = desimal), sedangkan target
 *   IKP warisan prototipe Prioritas menulis "302.663" untuk 302.663 orang
 *   (titik = ribuan; 48 dari 346 target berbentuk begitu). Dua tafsir yang
 *   berbeda atas teks yang sama tidak boleh hidup berdampingan, maka:
 *     - teks isian IKP dibaca SEKALI saat disimpan, dengan aturan Angka::baca
 *       Prioritas, lalu disimpan sebagai DECIMAL;
 *     - setelah itu semua perhitungan bekerja pada float. Teks IKP tidak
 *       pernah dilewatkan ke capaianToFloat().
 *
 * MENGAPA calculateCapaianTotalPercentage() DIBUNGKUS, BUKAN DIUBAH
 *   Fungsi itu hanya mengenal triwulan [1..4] dan dikunci 33 kasus di
 *   tests/unit/CapaianTotalTest.php. IKP berbasis BULAN, jadi ikp_capaian()
 *   meringkas bulan-bulan menjadi satu "periode" lalu menyerahkan
 *   persentase serta kasus tepinya (target 0 -> not_evaluable, trend_turun
 *   dengan realisasi 0, metode kosong) ke fungsi lama. Rumusnya tetap satu.
 *
 * Metode (kosakata sama dengan monev & capaian_helper):
 *   sum          nilai bulanan = TAMBAHAN; triwulan = jumlah bulan terisi;
 *                tahunan = jumlah 12 bulan.
 *   trend_naik   nilai bulanan = POSISI (makin tinggi makin baik); triwulan =
 *                bulan terisi terakhir dalam triwulan; tahunan = Desember.
 *   trend_turun  seperti trend_naik, makin rendah makin baik.
 *   trend_flat   posisi dipertahankan; rumus capaiannya = trend_naik.
 */

if (! function_exists('ikp_metode_valid')) {
    /** Metode yang dikenal (sum|trend_naik|trend_turun|trend_flat). */
    function ikp_metode_valid(?string $metode): bool
    {
        return in_array($metode, ['sum', 'trend_naik', 'trend_turun', 'trend_flat'], true);
    }
}

if (! function_exists('ikp_rapikan_teks')) {
    /** Spasi tak-putus -> spasi biasa, spasi berlebih dirapatkan, ujung dipangkas. */
    function ikp_rapikan_teks(?string $teks): string
    {
        $teks = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}", "\t"], ' ', (string) $teks);

        return trim((string) preg_replace('/\s+/u', ' ', $teks));
    }
}

if (! function_exists('ikp_angka_kosong')) {
    /** Isian yang bermakna "belum diisi": kosong atau tanda strip. */
    function ikp_angka_kosong(?string $teks): bool
    {
        return in_array(ikp_rapikan_teks($teks), ['', '-', '–', '—'], true);
    }
}

if (! function_exists('ikp_angka_sah')) {
    /**
     * Benar bila isian boleh diterima sebagai angka IKP (atau kosong/strip).
     *
     * Diterima: "400", "1.234" (ribuan), "1.234,5", "24,65", "2.5", "90%", "90 %".
     * Ditolak : huruf, kalimat, "1.2.3", angka negatif, "1 234".
     */
    function ikp_angka_sah(?string $teks): bool
    {
        if ($teks === null || ikp_angka_kosong($teks)) {
            return true;
        }
        $bersih = ikp_rapikan_teks($teks);

        return preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?\s*%?$/u', $bersih) === 1
            || preg_match('/^\d+([.,]\d+)?\s*%?$/u', $bersih) === 1;
    }
}

if (! function_exists('ikp_angka_baca')) {
    /**
     * Teks isian -> float, dengan aturan Angka::baca prototipe Prioritas.
     *
     *   "302.663"  -> 302663   (titik tanpa koma, pola ribuan = pemisah ribuan)
     *   "1.234,5"  -> 1234.5   (koma hadir: titik = ribuan, koma = desimal)
     *   "24,65"    -> 24.65
     *   "2.5"      -> 2.5      (bukan pola ribuan -> titik desimal)
     *   "90%"      -> 90       (tanda persen dibuang; satuannya di kolom satuan)
     *   "" / "-"   -> null
     *   "0"        -> null bila $nolKosong (TARGET: nol = belum ada target),
     *                 0.0 bila ! $nolKosong (REALISASI: nol adalah nilai sah)
     *
     * Mengembalikan null juga untuk teks yang tidak terbaca sebagai angka —
     * pemanggil yang perlu membedakan "kosong" dari "salah ketik" memeriksa
     * ikp_angka_sah() lebih dulu.
     */
    function ikp_angka_baca(?string $teks, bool $nolKosong = true): ?float
    {
        if ($teks === null || ikp_angka_kosong($teks)) {
            return null;
        }

        $bersih = trim(str_replace('%', '', ikp_rapikan_teks($teks)));

        if (str_contains($bersih, ',')) {
            $bersih = str_replace(',', '.', str_replace('.', '', $bersih));
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $bersih)) {
            $bersih = str_replace('.', '', $bersih);
        }

        if (! is_numeric($bersih)) {
            return null;
        }

        $angka = (float) $bersih;
        if (abs($angka) < 1e-12) {
            return $nolKosong ? null : 0.0;
        }

        return $angka;
    }
}

if (! function_exists('ikp_fmt')) {
    /**
     * Float -> teks gaya Indonesia tanpa nol berlebih; "-" untuk null.
     *   302663 -> "302.663", 1234.5 -> "1.234,5", 98.37 -> "98,37", 1.0 -> "1".
     *
     * Hasilnya aman dibaca ulang oleh ikp_angka_baca() (bolak-balik tetap sama),
     * jadi boleh dipakai sebagai nilai awal kotak isian.
     */
    function ikp_fmt(?float $v, int $maxDesimal = 2): string
    {
        if ($v === null || ! is_finite($v)) {
            return '-';
        }
        $maxDesimal = max(0, min(6, $maxDesimal));
        $v          = round($v, $maxDesimal);
        if ($v == 0.0) {
            $v = 0.0; // -0.0 -> 0.0, hindari tampilan "-0"
        }
        $teks = number_format($v, $maxDesimal, ',', '.');
        if ($maxDesimal > 0) {
            $teks = rtrim(rtrim($teks, '0'), ',');
        }

        return $teks;
    }
}

if (! function_exists('ikp_nilai_triwulan')) {
    /**
     * Nilai bulanan [1..12 => ?float] -> nilai triwulan [1..4 => ?float].
     *
     *   sum     -> jumlah bulan yang terisi dalam triwulan
     *   trend_* -> nilai bulan TERISI terakhir dalam triwulan (posisi)
     *   lainnya -> null semua. MENGAPA: tanpa metode tidak ada cara jujur untuk
     *              tahu apakah bulan-bulannya dijumlah atau diambil posisinya
     *              (pelajaran data Prioritas: IKP 454/455 kumulatif tanpa
     *              penanda). Lebih baik kosong daripada angka yang salah.
     *
     * Triwulan tanpa satu pun bulan terisi = null (bukan 0).
     *
     * @param array<int, float|int|string|null> $bulan
     *
     * @return array<int, float|null>
     */
    function ikp_nilai_triwulan(array $bulan, string $metode): array
    {
        $tw = [];
        for ($q = 1; $q <= 4; $q++) {
            if (! ikp_metode_valid($metode)) {
                $tw[$q] = null;

                continue;
            }
            $isi = [];
            // Loop per nomor bulan: kunci bisa hilang/berurutan acak.
            for ($m = 3 * $q - 2; $m <= 3 * $q; $m++) {
                if (array_key_exists($m, $bulan) && $bulan[$m] !== null && $bulan[$m] !== '') {
                    $isi[$m] = (float) $bulan[$m];
                }
            }
            if ($isi === []) {
                $tw[$q] = null;
            } elseif ($metode === 'sum') {
                $tw[$q] = array_sum($isi);
            } else {
                $tw[$q] = end($isi);
            }
        }

        return $tw;
    }
}

if (! function_exists('ikp_nilai_tahunan')) {
    /**
     * Nilai setahun dari bulanan: sum -> jumlah bulan terisi; trend_* -> bulan
     * terisi terakhir. null bila tak satu pun terisi / metode tak dikenal.
     *
     * @param array<int, float|int|string|null> $bulan
     */
    function ikp_nilai_tahunan(array $bulan, string $metode): ?float
    {
        if (! ikp_metode_valid($metode)) {
            return null;
        }
        $isi = [];
        for ($m = 1; $m <= 12; $m++) {
            if (array_key_exists($m, $bulan) && $bulan[$m] !== null && $bulan[$m] !== '') {
                $isi[$m] = (float) $bulan[$m];
            }
        }
        if ($isi === []) {
            return null;
        }

        return $metode === 'sum' ? array_sum($isi) : end($isi);
    }
}

if (! function_exists('ikp_nama_bulan')) {
    /** 1 -> "Januari"; $pendek: 1 -> "Jan". */
    function ikp_nama_bulan(int $bulan, bool $pendek = false): string
    {
        $panjang = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $singkat = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return ($pendek ? $singkat : $panjang)[$bulan] ?? (string) $bulan;
    }
}

if (! function_exists('ikp_capaian')) {
    /**
     * Capaian (%) atas bulan $dari..$sampai.
     *
     * Aturan (sama dengan capaian_helper untuk triwulan):
     *  - hanya bulan yang REALISASINYA terisi yang dihitung (0 = terisi);
     *  - sum     : Σ realisasi bulan terisi / Σ target bulan yang sama;
     *  - trend_* : realisasi bulan terisi terakhir vs target bulan itu
     *              (trend_turun: target / realisasi).
     *
     * Contoh DLH-01 (trend_naik) Jul 305/310, Agu 318/330, Sep kosong ->
     * TW III berjalan = 318 / 330 = 96,36 %.
     *
     * Mengembalikan array hasil calculateCapaianTotalPercentage() (kunci
     * percentage, status calculated|not_evaluable|incomplete, reason_code,
     * target_total, actual_total, error, calculation_description, ...) dengan
     * `calculation_description`/`error` ditulis ulang dalam bahasa BULAN, plus:
     *   bulan_terakhir  bulan terisi terakhir (null bila belum ada realisasi)
     *   bulan_terisi    jumlah bulan terisi dalam rentang
     *
     * PENTING bagi pemanggil yang merata-rata: pakai hanya status 'calculated'
     * (not_evaluable menulis percentage 0 yang BUKAN hasil pengukuran).
     *
     * @param array<int, float|int|string|null> $target [bulan => nilai]
     * @param array<int, float|int|string|null> $real   [bulan => nilai]
     * @param array<int, array<string, mixed>>  $skala  baris satuan_skala (satuan predikat)
     */
    function ikp_capaian(string $metode, array $target, array $real, int $dari = 1, int $sampai = 12, array $skala = []): array
    {
        helper('capaian');
        $dari   = max(1, $dari);
        $sampai = min(12, $sampai);

        $terisi = [];
        for ($m = $dari; $m <= $sampai; $m++) {
            if (array_key_exists($m, $real) && $real[$m] !== null && $real[$m] !== '') {
                $terisi[] = $m;
            }
        }
        $akhir = $terisi === [] ? null : end($terisi);

        $rentang = $dari === $sampai
            ? ikp_nama_bulan($dari)
            : ikp_nama_bulan($dari) . '–' . ikp_nama_bulan($sampai);

        $tempel = static function (array $hasil, ?string $deskripsi, ?string $galat = null) use ($akhir, $terisi): array {
            if ($deskripsi !== null) {
                $hasil['calculation_description'] = $deskripsi;
            }
            if ($hasil['error'] !== null) {
                $hasil['error']                   = $galat ?? $hasil['error'];
                $hasil['calculation_description'] = $hasil['error'];
            }
            $hasil['bulan_terakhir'] = $akhir;
            $hasil['bulan_terisi']   = count($terisi);

            return $hasil;
        };

        if ($akhir === null) {
            // Belum ada realisasi: status incomplete TANPA error (bukan kesalahan).
            $hasil = calculateCapaianTotalPercentage($metode, [], []);

            return $tempel($hasil, 'Belum ada realisasi pada ' . $rentang . '.');
        }

        if (! ikp_metode_valid($metode)) {
            $hasil = calculateCapaianTotalPercentage($metode, [1 => 1], [1 => 1]);

            return $tempel($hasil, null, 'Metode perhitungan IKP belum dipilih.');
        }

        if ($metode === 'sum') {
            $t = 0.0;
            $r = 0.0;
            foreach ($terisi as $m) {
                $tm = $target[$m] ?? null;
                if ($tm === null || $tm === '') {
                    $hasil = calculateCapaianTotalPercentage('sum', [1 => null], [1 => $real[$m]], $skala);

                    return $tempel($hasil, null, 'Target ' . ikp_nama_bulan($m) . ' belum diisi, padahal realisasinya sudah ada.');
                }
                $t += (float) $tm;
                $r += (float) $real[$m];
            }
            $hasil = calculateCapaianTotalPercentage('sum', [1 => $t], [1 => $r], $skala);
            if ($hasil['status'] === 'calculated') {
                $awalIsi = $terisi[0];
                $jangka  = $awalIsi === $akhir ? ikp_nama_bulan($akhir) : ikp_nama_bulan($awalIsi) . '–' . ikp_nama_bulan($akhir);

                return $tempel($hasil, 'Akumulasi ' . count($terisi) . ' bulan terisi (' . $jangka . ').');
            }
            if ($hasil['status'] === 'not_evaluable') {
                return $tempel($hasil, 'Target kumulatif bulan terisi masih 0; capaian belum dapat dinilai.');
            }

            return $tempel($hasil, null);
        }

        $tAkhir = $target[$akhir] ?? null;
        if ($tAkhir === '') {
            $tAkhir = null;
        }
        $hasil = calculateCapaianTotalPercentage($metode, [1 => $tAkhir], [1 => $real[$akhir]], $skala);
        if ($hasil['error'] !== null && $tAkhir === null) {
            return $tempel($hasil, null, 'Target ' . ikp_nama_bulan($akhir) . ' belum diisi, padahal realisasinya sudah ada.');
        }
        if ($hasil['status'] === 'calculated') {
            return $tempel($hasil, 'Posisi ' . ikp_nama_bulan($akhir) . ' (bulan terisi terakhir) dengan metode '
                . capaianMetodeNama($metode) . '.');
        }
        if ($hasil['status'] === 'not_evaluable') {
            return $tempel($hasil, 'Target ' . ikp_nama_bulan($akhir) . ' masih 0; capaian belum dapat dinilai.');
        }

        return $tempel($hasil, null);
    }
}

if (! function_exists('ikp_status')) {
    /**
     * Hasil ikp_capaian() -> status warna memakai ambang dashboard_status_helper
     * (tabel dashboard_status_thresholds). Rentang TIDAK di-hardcode di sini.
     *
     * @return array{code:string, name:string, color:string, color_hex:string,
     *               color_soft:string, bs:string, icon:?string, numeric:bool, kelompok:string}
     */
    function ikp_status(array $hasil): array
    {
        helper('dashboard_status');

        // Pola ukur (ikp_capaian_pola): rentang tanpa bulan ukur / rilis yang
        // belum keluar = abu-abu "Tidak diukur" / "Menunggu rilis" — bukan
        // kinerja buruk, bukan kelalaian, dan tidak ikut rata-rata.
        if ((! empty($hasil['tidak_diukur']) || ! empty($hasil['menunggu_rilis'])) && ($hasil['error'] ?? null) === null
            && ($hasil['status'] ?? null) !== 'calculated') {
            $rilis = ! empty($hasil['menunggu_rilis']);
            $s     = dash_status_nonnumeric('belum_ada_data');
            $s['code']     = $rilis ? 'menunggu_rilis' : 'tidak_diukur';
            $s['name']     = $rilis ? 'Menunggu Rilis' : 'Tidak Diukur';
            $s['icon']     = $rilis ? 'fa-hourglass-half' : 'fa-calendar-minus';
            $s['kelompok'] = 'abu';

            return $s;
        }

        if (($hasil['status'] ?? null) === 'calculated' && $hasil['percentage'] !== null) {
            $s = getAchievementStatus((float) $hasil['percentage']);
        } elseif (($hasil['status'] ?? null) === 'not_evaluable') {
            $s = dash_status_nonnumeric('belum_dinilai');
        } elseif (($hasil['error'] ?? null) !== null) {
            $s = dash_status_nonnumeric('belum_valid');
        } else {
            $s = dash_status_nonnumeric('belum_ada_data');
        }
        $s['kelompok'] = ikp_kelompok_warna($s['color'] ?? null);

        return $s;
    }
}

if (! function_exists('ikp_kelompok_warna')) {
    /**
     * Slug warna ambang -> 4 kelompok ringkas untuk kartu ringkasan:
     *   hijau (hijau, biru = tercapai/melampaui), kuning (kuning, oranye),
     *   merah, abu (belum ada data / belum dapat dinilai).
     * Yang dipetakan adalah WARNA pilihan Super Admin, bukan rentang angka.
     */
    function ikp_kelompok_warna(?string $slug): string
    {
        return match ((string) $slug) {
            'hijau', 'biru'    => 'hijau',
            'kuning', 'oranye' => 'kuning',
            'merah'            => 'merah',
            default            => 'abu',
        };
    }
}

if (! function_exists('ikp_bagi_rata')) {
    /**
     * Bagi Rata deterministik: $total dipecah ke $n slot. Kunci hasil 1..$n.
     *
     *   sum          dibagi rata; sisa pembagian digeser ke slot-slot AWAL.
     *                $bulat (satuan tak terbagi: orang/unit/dokumen) & total
     *                bulat -> bilangan bulat (400/5 = 80×5; 346/5 = 70,69,69,69,69).
     *                Selain itu per 0,01 (atau 0,0001 bila 0,01 terlalu kasar):
     *                15/12 = 1,25×12; 10/3 = 3,34; 3,33; 3,33. Σ selalu = $total.
     *   trend_naik   interpolasi linear dari $awal (baseline / posisi periode
     *   trend_turun  sebelumnya) ke $total; slot terakhir = $total persis.
     *                Tanpa $awal -> semua slot = $total (tidak mengarang titik awal).
     *   trend_flat   semua slot = $total.
     *   lainnya      [] (metode belum dipilih: tidak ada pembagian yang jujur).
     *
     * @return array<int, float>
     */
    function ikp_bagi_rata(float $total, int $n, string $metode, ?float $awal = null, bool $bulat = false): array
    {
        if ($n < 1 || ! ikp_metode_valid($metode) || ! is_finite($total)) {
            return [];
        }

        if ($metode === 'trend_flat') {
            return array_fill(1, $n, $total);
        }

        if ($metode === 'sum') {
            $tanda   = $total < 0 ? -1 : 1;
            $mutlak  = abs($total);
            $desimal = ($bulat && abs($mutlak - round($mutlak)) < 1e-9) ? 0 : 2;
            if ($desimal === 2 && intdiv((int) round($mutlak * 100), $n) === 0 && $mutlak > 0) {
                $desimal = 4; // 0,1 dibagi 12: per 0,01 hampir semua slot jadi 0
            }
            $skala = 10 ** $desimal;
            $unit  = (int) round($mutlak * $skala);
            $dasar = intdiv($unit, $n);
            $sisa  = $unit - $dasar * $n;
            $out   = [];
            for ($k = 1; $k <= $n; $k++) {
                $u       = $dasar + ($k <= $sisa ? 1 : 0);
                $out[$k] = (float) ($tanda * $u / $skala);
            }

            return $out;
        }

        // trend_naik / trend_turun
        if ($awal === null || ! is_finite($awal)) {
            return array_fill(1, $n, $total);
        }
        $out = [];
        for ($k = 1; $k <= $n; $k++) {
            $v = $awal + ($total - $awal) * $k / $n;
            if ($k === $n) {
                $v = $total;
            } elseif ($bulat) {
                $v = round($v);
            } else {
                $v = round($v, 2);
            }
            $out[$k] = (float) $v;
        }

        return $out;
    }
}

if (! function_exists('ikp_cek')) {
    /**
     * Pemeriksa konsistensi pecahan target terhadap induknya (tidak memblokir
     * penyimpanan — hasilnya peringatan untuk operator).
     *
     *   sum          Σ anak terisi = induk (toleransi 0,005)
     *   trend_naik   anak slot TERAKHIR = induk, dan tidak pernah turun
     *   trend_turun  anak slot TERAKHIR = induk, dan tidak pernah naik
     *   trend_flat   semua anak terisi = induk, slot terakhir terisi
     *
     * Slot kosong (null) dilewati — mis. kolom 2025 yang "sudah lewat".
     *
     * @param array<int, float|int|null> $anak [urutan => nilai], diurutkan menurut kunci
     *
     * @return array{ok:bool, pesan:string, selisih:?float, status:string}
     *         status: cocok|selisih|kosong|tanpa_induk|tanpa_metode
     */
    function ikp_cek(string $metode, ?float $induk, array $anak): array
    {
        $tol = 0.005;
        ksort($anak);
        $isi = [];
        foreach ($anak as $k => $v) {
            if ($v !== null && $v !== '') {
                $isi[$k] = (float) $v;
            }
        }
        $hasil = static fn (bool $ok, string $pesan, ?float $selisih, string $status): array
            => ['ok' => $ok, 'pesan' => $pesan, 'selisih' => $selisih, 'status' => $status];

        if (! ikp_metode_valid($metode)) {
            return $hasil(false, 'Metode perhitungan belum dipilih, konsistensi tidak dapat diperiksa.', null, 'tanpa_metode');
        }
        if ($induk === null) {
            return $hasil(false, 'Target induk belum berupa angka, konsistensi tidak dapat diperiksa.', null, 'tanpa_induk');
        }
        if ($isi === []) {
            return $hasil(false, 'Belum ada rincian yang diisi.', null, 'kosong');
        }

        if ($metode === 'sum') {
            $jumlah  = array_sum($isi);
            $selisih = $jumlah - $induk;
            if (abs($selisih) <= $tol) {
                return $hasil(true, 'Jumlah ' . ikp_fmt($jumlah, 4) . ' sesuai target ' . ikp_fmt($induk, 4) . '.', 0.0, 'cocok');
            }

            return $hasil(false, 'Jumlah ' . ikp_fmt($jumlah, 4) . ($selisih < 0 ? ' kurang ' : ' lebih ')
                . ikp_fmt(abs($selisih), 4) . ' dari target ' . ikp_fmt($induk, 4) . '.', $selisih, 'selisih');
        }

        $kunciAkhir = array_key_last($anak);
        $akhir      = $anak[$kunciAkhir] ?? null;

        if ($metode === 'trend_flat') {
            foreach ($isi as $k => $v) {
                if (abs($v - $induk) > $tol) {
                    return $hasil(false, 'Nilai ' . ikp_fmt($v, 4) . ' pada periode ke-' . $k . ' berbeda dari target '
                        . ikp_fmt($induk, 4) . ' (metode dipertahankan: semua periode sama).', $v - $induk, 'selisih');
                }
            }
            if ($akhir === null || $akhir === '') {
                return $hasil(false, 'Periode terakhir belum diisi.', null, 'selisih');
            }

            return $hasil(true, 'Semua periode terisi sama dengan target ' . ikp_fmt($induk, 4) . '.', 0.0, 'cocok');
        }

        // trend_naik / trend_turun
        $naik    = $metode === 'trend_naik';
        $sebelum = null;
        foreach ($isi as $k => $v) {
            if ($sebelum !== null && ($naik ? $v < $sebelum - $tol : $v > $sebelum + $tol)) {
                return $hasil(false, 'Nilai ' . ikp_fmt($v, 4) . ' pada periode ke-' . $k . ($naik
                    ? ' turun dari periode sebelumnya (metode makin tinggi makin baik: tidak boleh turun).'
                    : ' naik dari periode sebelumnya (metode makin rendah makin baik: tidak boleh naik).'), null, 'selisih');
            }
            $sebelum = $v;
        }
        if ($akhir === null || $akhir === '') {
            return $hasil(false, 'Periode terakhir belum diisi; nilainya harus sama dengan target ' . ikp_fmt($induk, 4) . '.', null, 'selisih');
        }
        $selisih = (float) $akhir - $induk;
        if (abs($selisih) > $tol) {
            return $hasil(false, 'Nilai periode terakhir ' . ikp_fmt((float) $akhir, 4) . ' belum sama dengan target '
                . ikp_fmt($induk, 4) . '.', $selisih, 'selisih');
        }

        return $hasil(true, 'Periode terakhir sama dengan target ' . ikp_fmt($induk, 4) . ($naik ? ', naik bertahap.' : ', turun bertahap.'), 0.0, 'cocok');
    }
}

if (! function_exists('ikp_satuan_bulat')) {
    /**
     * Satuan yang tak terbagi (orang, unit, dokumen, …) -> Bagi Rata bilangan bulat.
     * Heuristik kata; operator tetap bisa menyunting hasilnya.
     *
     * MENGAPA satuan terbagi dicocokkan per KATA UTUH (\b): dengan cocok
     * potongan, "ha" menangkap perUSAHAan, pelaku usaHA, keluraHAn dan "rp"
     * menangkap peRPustakaan — sehingga 20 Perusahaan dibagi 1,67 per bulan.
     */
    function ikp_satuan_bulat(?string $satuan): bool
    {
        $s = mb_strtolower(ikp_rapikan_teks($satuan));
        if ($s === '' || str_contains($s, '%')
            || preg_match('/\b(persen|persentase|indeks|nilai|skor|rasio|predikat|kategori|ton|kg|kilogram|km|kilometer|ha|hektar|hektare|meter|m2|m3|liter|rp|rupiah|jam|hari|menit|ppm|level)\b/u', $s)) {
            return false;
        }

        return (bool) preg_match('/(orang|jiwa|unit|dokumen|laporan|paket|kegiatan|sekolah|lembaga|aplikasi|kali|buah|desa|pekon|kelurahan|kecamatan|perusahaan|usaha|pedagang|kelompok|titik|lokasi|perangkat daerah|opd|pd\b|berita acara|inovasi|layanan|kasus|keluarga|kk|rumah|peserta|siswa|anak|nasabah|bank sampah|tps|ruang|komunitas|media|konten|eksemplar|arsip|berkas|sampel|usulan|sertifikat|objek|satuan pendidikan|koperasi|ikm|ukm|umkm|pasar|trayek|event|festival)/u', $s);
    }
}

if (! function_exists('ikp_lencana_status')) {
    /**
     * HTML lencana status capaian. Warnanya diambil dari palet ambang
     * (dash_color) berdasar slug warna — tidak menerima CSS bebas.
     */
    function ikp_lencana_status(?string $warna, string $label, ?string $judul = null): string
    {
        helper('dashboard_status');
        $w     = dash_color($warna);
        $ikon  = match ($w['slug']) {
            'hijau'  => 'fa-circle-check',
            'biru'   => 'fa-arrow-trend-up',
            'kuning' => 'fa-circle-half-stroke',
            'oranye' => 'fa-triangle-exclamation',
            'merah'  => 'fa-circle-exclamation',
            default  => 'fa-minus',
        };
        $title = $judul !== null && $judul !== '' ? ' title="' . esc($judul, 'attr') . '"' : '';

        return '<span class="ikp-status" style="background:' . esc($w['soft'], 'attr') . ';color:' . esc($w['hex'], 'attr') . '"' . $title . '>'
            . '<i class="fas ' . $ikon . '"></i>' . esc($label) . '</span>';
    }
}

/*
 * =====================================================================
 * POLA UKUR (28-09-2026) — hitungan | posisi | rilis
 * =====================================================================
 *
 * Keluhan pengguna: "indeks dibuat dicicil itu ngaco banget. Masa indeks
 * keterbukaan informasi dicicil. Itu kan memang keluar setahun sekali dan
 * setiap indeks itu beda-beda juga keluar jadwalnya."
 *
 * `metode` (sum|trend_*) hanya menjawab BAGAIMANA angka diringkas; ia tidak
 * menjawab KAPAN angka itu ada. Pola ukur menjawab keduanya:
 *
 *   hitungan  hasil dijumlahkan (metode efektif = sum). Target bulan ukur =
 *             cicilan, Σ = target tahunan. Bawaan: diukur tiap bulan.
 *   posisi    nilai keadaan yang diukur sendiri pada tanggal ukur (metode
 *             efektif = trend_naik|trend_turun|trend_flat = ARAH). Target
 *             bulan ukur = posisi yang diharapkan, bukan cicilan.
 *   rilis     nilai resmi pihak lain (indeks, opini, predikat). Target &
 *             realisasi HANYA di bulan rilis; nilai tidak pernah dicicil
 *             atau dijumlah; realisasi wajib bukti publikasi.
 *
 * Bulan di luar `bulan_ukur` TIDAK DIUKUR: tidak ada target, tidak ada
 * realisasi, capaiannya tidak dihitung (bukan 0, bukan 100) dan tidak ikut
 * rata-rata mana pun. Semua fungsi di bawah murni (tanpa DB/sesi), dikunci
 * tests/unit/IkpPolaUkurTest.php. Tabel penerbit & bulan rilis bawaan ada
 * di SATU tempat: app/Config/IkpPolaUkur.php.
 */

if (! function_exists('ikp_pola_valid')) {
    /** Pola ukur yang dikenal (hitungan|posisi|rilis). */
    function ikp_pola_valid(?string $pola): bool
    {
        return in_array($pola, ['hitungan', 'posisi', 'rilis'], true);
    }
}

if (! function_exists('ikp_pola_meta')) {
    /**
     * Teks layar per pola (kartu form, lencana, legenda cetak). Satu sumber
     * agar form, daftar, rekap, dan lampiran PK berbicara dengan kalimat sama.
     *
     * @return array<string, array{judul:string, singkat:string, ikon:string, isi:string, target:string, realisasi:string, contoh:string}>
     */
    function ikp_pola_meta(): array
    {
        return [
            'hitungan' => [
                'judul'     => 'Hitungan (dijumlah)',
                'singkat'   => 'Hitungan',
                'ikon'      => 'fa-calculator',
                'isi'       => 'Hasil yang dikerjakan sendiri dan dijumlahkan sepanjang tahun.',
                'target'    => 'Target bulan ukur = cicilan; jumlahnya = target tahunan.',
                'realisasi' => 'Realisasi = hasil yang tercatat pada bulan itu (tambahan).',
                'contoh'    => 'jumlah konten, surat, pelatihan, nasabah baru',
            ],
            'posisi' => [
                'judul'     => 'Posisi (diukur sendiri)',
                'singkat'   => 'Posisi',
                'ikon'      => 'fa-location-crosshairs',
                'isi'       => 'Nilai keadaan yang diukur sendiri dari data internal pada tanggal ukur.',
                'target'    => 'Target bulan ukur = posisi yang diharapkan saat itu, bukan cicilan.',
                'realisasi' => 'Realisasi = posisi terbaru dari rekap resmi, bukan tambahan.',
                'contoh'    => '% layanan tepat waktu, % aduan ditindaklanjuti, jumlah nasabah AKTIF, cakupan',
            ],
            'rilis' => [
                'judul'     => 'Rilis resmi (pihak lain)',
                'singkat'   => 'Rilis',
                'ikon'      => 'fa-certificate',
                'isi'       => 'Nilai yang dikeluarkan pihak lain secara berkala, misalnya indeks atau opini.',
                'target'    => 'Target hanya pada bulan rilis. Nilainya tidak pernah dicicil atau dijumlah.',
                'realisasi' => 'Realisasi = nilai resmi saat dirilis, wajib disertai bukti publikasi.',
                'contoh'    => 'Indeks Keterbukaan Informasi Publik (Komisi Informasi), Indeks SPBE, opini BPK, IPM (BPS)',
            ],
        ];
    }
}

if (! function_exists('ikp_periode_ukur_bulan')) {
    /**
     * Periode ukur -> bulan ukur bawaannya.
     *
     * @return array<string, int[]>
     */
    function ikp_periode_ukur_bulan(): array
    {
        return [
            'bulanan'    => range(1, 12),
            'triwulanan' => [3, 6, 9, 12],
            'semesteran' => [6, 12],
            'tahunan'    => [12],
        ];
    }
}

if (! function_exists('ikp_periode_ukur_label')) {
    /** Label periode ukur (termasuk "khusus" = bulan pilihan yang tidak berjarak tetap). */
    function ikp_periode_ukur_label(?string $periode): string
    {
        return [
            'bulanan'    => 'Bulanan',
            'triwulanan' => 'Triwulanan',
            'semesteran' => 'Semesteran',
            'tahunan'    => 'Tahunan',
            'khusus'     => 'Bulan tertentu',
        ][(string) $periode] ?? 'Bulanan';
    }
}

if (! function_exists('ikp_bulan_ukur_baca')) {
    /**
     * "6,12" | [6, "12"] | null -> [6, 12] (bulan sah 1..12, unik, terurut).
     *
     * @param array<int|string>|string|null $nilai
     *
     * @return int[]
     */
    function ikp_bulan_ukur_baca($nilai): array
    {
        if ($nilai === null || $nilai === '') {
            return [];
        }
        $bagian = is_array($nilai) ? $nilai : preg_split('/[\s,;]+/', (string) $nilai);
        $out    = [];
        foreach ((array) $bagian as $b) {
            $b = trim((string) $b);
            if ($b !== '' && ctype_digit($b) && (int) $b >= 1 && (int) $b <= 12) {
                $out[(int) $b] = (int) $b;
            }
        }
        ksort($out);

        return array_values($out);
    }
}

if (! function_exists('ikp_bulan_ukur_teks')) {
    /** [12, 6] -> "6,12" (bentuk simpan kolom ikp.bulan_ukur). */
    function ikp_bulan_ukur_teks(array $bulan): string
    {
        return implode(',', ikp_bulan_ukur_baca($bulan));
    }
}

if (! function_exists('ikp_periode_dari_bulan')) {
    /**
     * Periode ukur dari daftar bulan: 1 bulan = tahunan; 2/4/12 bulan yang
     * berjarak sama (6/3/1 bulan) = semesteran/triwulanan/bulanan; selain
     * itu "khusus". MENGAPA diturunkan, bukan dipercaya dari isian: bulan
     * ukur-lah yang dipakai menghitung, jadi periodenya harus cocok.
     */
    function ikp_periode_dari_bulan(array $bulan): string
    {
        $b = ikp_bulan_ukur_baca($bulan);
        $n = count($b);
        if ($n === 1) {
            return 'tahunan';
        }
        $nama = [2 => 'semesteran', 4 => 'triwulanan', 12 => 'bulanan'][$n] ?? null;
        if ($nama === null) {
            return 'khusus';
        }
        $jarak = intdiv(12, $n);
        for ($i = 1; $i < $n; $i++) {
            if ($b[$i] - $b[$i - 1] !== $jarak) {
                return 'khusus';
            }
        }

        return $nama;
    }
}

if (! function_exists('ikp_bulan_ukur_label')) {
    /** [6, 12] -> "Jun, Des"; 12 bulan -> "setiap bulan". */
    function ikp_bulan_ukur_label(array $bulan): string
    {
        $b = ikp_bulan_ukur_baca($bulan);
        if (count($b) === 12) {
            return 'setiap bulan';
        }

        return implode(', ', array_map(static fn ($m) => ikp_nama_bulan($m, true), $b));
    }
}

if (! function_exists('ikp_pola_tebak')) {
    /**
     * Klasifikasi otomatis pola ukur untuk data lama (migrasi 2026-09-28,
     * IKP yang belum pernah disimpan ulang, pembangun simulasi).
     *
     *  1. Nama cocok pola rilis, ATAU satuan cocok pola rilis sedangkan nama
     *     tidak diawali kata hitungan internal (jumlah, persentase, ...)
     *     -> rilis; penerbit & bulan dari tabel rilis bawaan; periode tahunan.
     *  2. metode sum -> hitungan, bulanan.
     *  3. metode trend_* -> posisi, bulanan (arah = metode).
     *  4. metode kosong -> posisi bila satuannya persen/indeks/nilai/skor/
     *     rasio, selain itu hitungan.
     * Hasil selalu bertanda ditebak = true (chip "Periksa pola ukur").
     *
     * @param object|null $cfg Config\IkpPolaUkur (bawaan: config('IkpPolaUkur'))
     *
     * @return array{pola_ukur:string, metode:string, periode_ukur:string, bulan_ukur:int[], penerbit:?string,
     *               rilis_tahun_berikut:bool, pola_ditebak:bool, catatan:?string}
     */
    function ikp_pola_tebak(?string $metode, ?string $nama, ?string $satuan, ?object $cfg = null): array
    {
        $cfg ??= config('IkpPolaUkur');
        $nama   = ikp_rapikan_teks($nama);
        $satuan = ikp_rapikan_teks($satuan);
        $metode = (string) $metode;

        $rilis = preg_match($cfg->polaRilis, $nama) === 1
            || ($satuan !== '' && preg_match($cfg->polaRilis, $satuan) === 1 && preg_match($cfg->awalInternal, $nama) !== 1);

        if ($rilis) {
            $baris = $cfg->rilisLainnya;
            foreach ($cfg->rilisBawaan as $r) {
                if (preg_match($r['cocok'], $nama . ' ' . $satuan) === 1) {
                    $baris = $r;

                    break;
                }
            }

            return [
                'pola_ukur'           => 'rilis',
                'metode'              => in_array($metode, ['trend_naik', 'trend_turun', 'trend_flat'], true) ? $metode : 'trend_naik',
                'periode_ukur'        => 'tahunan',
                'bulan_ukur'          => [(int) $baris['bulan']],
                'penerbit'            => $baris['penerbit'] ?? null,
                'rilis_tahun_berikut' => (bool) ($baris['tahun_berikut'] ?? false),
                'pola_ditebak'        => true,
                'catatan'             => $baris['catatan'] ?? null,
            ];
        }

        if ($metode === 'sum') {
            $pola = 'hitungan';
        } elseif (in_array($metode, ['trend_naik', 'trend_turun', 'trend_flat'], true)) {
            $pola = 'posisi';
        } else {
            $pola   = (str_contains($satuan, '%') || preg_match('/\b(persen|persentase|indeks|nilai|skor|rasio)\b/iu', $satuan) === 1)
                ? 'posisi' : 'hitungan';
            $metode = $pola === 'hitungan' ? 'sum' : 'trend_naik';
        }

        return [
            'pola_ukur'           => $pola,
            'metode'              => $metode,
            'periode_ukur'        => 'bulanan',
            'bulan_ukur'          => range(1, 12),
            'penerbit'            => null,
            'rilis_tahun_berikut' => false,
            'pola_ditebak'        => true,
            'catatan'             => null,
        ];
    }
}

if (! function_exists('ikp_pola')) {
    /**
     * Konteks pola ukur siap pakai dari satu baris `ikp` (bisa baris
     * IkpRekapService::daftar() yang punya satuan_label).
     *
     *  pola, metode (EFEKTIF untuk rumus: hitungan -> sum; posisi/rilis ->
     *  trend_* arah), periode_ukur, bulan_ukur int[], penerbit,
     *  rilis_tahun_berikut bool, ditebak bool (belum dikonfirmasi admin),
     *  posisi_terbagi bool (posisi yang dipecah per bagian: Σ posisi bagian = total).
     *
     * MENGAPA metode efektif tidak selalu = kolom metode: nilai rilis/posisi
     * tidak boleh dijumlah walau data lama bermetode sum, dan hitungan tidak
     * boleh diambil posisinya. Kolom `metode` baru diselaraskan saat admin
     * menyimpan form (migrasi tidak menimpa kolom lama). Satu pengecualian:
     * metode KOSONG pada pola yang masih tebakan tetap kosong -> capaian
     * "metode belum dipilih" (tidak mengarang cara meringkas).
     *
     * @return array{pola:string, metode:string, periode_ukur:string, bulan_ukur:int[], penerbit:?string,
     *               rilis_tahun_berikut:bool, ditebak:bool, posisi_terbagi:bool}
     */
    function ikp_pola(array $ikp, ?object $cfg = null): array
    {
        $metode = (string) ($ikp['metode'] ?? '');
        $pola   = (string) ($ikp['pola_ukur'] ?? '');
        $arah   = in_array($metode, ['trend_naik', 'trend_turun', 'trend_flat'], true);

        if (! ikp_pola_valid($pola)) {
            $t = ikp_pola_tebak($metode, (string) ($ikp['output_prioritas'] ?? ''),
                (string) ($ikp['satuan_label'] ?? ($ikp['satuan_teks'] ?? '')), $cfg);

            return [
                'pola'                => $t['pola_ukur'],
                'metode'              => ikp_metode_valid($metode) ? $t['metode'] : '',
                'periode_ukur'        => $t['periode_ukur'],
                'bulan_ukur'          => $t['bulan_ukur'],
                'penerbit'            => $t['penerbit'],
                'rilis_tahun_berikut' => $t['rilis_tahun_berikut'],
                'ditebak'             => true,
                'posisi_terbagi'      => $t['pola_ukur'] === 'posisi' && ! empty($ikp['posisi_terbagi']),
            ];
        }

        $ditebak = ! empty($ikp['pola_ditebak']);
        $bulan   = ikp_bulan_ukur_baca($ikp['bulan_ukur'] ?? null);
        if ($bulan === []) {
            $bulan = ikp_periode_ukur_bulan()[(string) ($ikp['periode_ukur'] ?? '')]
                ?? ($pola === 'rilis' ? [12] : range(1, 12));
        }
        if ($pola === 'hitungan') {
            $efektif = 'sum';
        } else {
            $efektif = $arah ? $metode : 'trend_naik';
        }
        if (! ikp_metode_valid($metode) && $ditebak) {
            $efektif = '';
        }
        $penerbit = trim((string) ($ikp['penerbit'] ?? ''));

        return [
            'pola'                => $pola,
            'metode'              => $efektif,
            'periode_ukur'        => ikp_periode_dari_bulan($bulan),
            'bulan_ukur'          => $bulan,
            'penerbit'            => $penerbit === '' ? null : $penerbit,
            'rilis_tahun_berikut' => $pola === 'rilis' && ! empty($ikp['rilis_tahun_berikut']),
            'ditebak'             => $ditebak,
            // Posisi yang DAPAT DIPECAH per bagian (keputusan 29-09-2026, "D4"): total bulan m = Σ posisi setiap
            // bagian pada bulan m (mis. pengikut semua akun resmi = IG + FB + TikTok + YouTube). Hanya bermakna
            // untuk pola posisi; hitungan sudah terbagi, rilis tidak pernah dibagi.
            'posisi_terbagi'      => $pola === 'posisi' && ! empty($ikp['posisi_terbagi']),
        ];
    }
}

if (! function_exists('ikp_bulan_diukur')) {
    /** Apakah bulan $m termasuk bulan ukur pola ini? */
    function ikp_bulan_diukur(array $pola, int $m): bool
    {
        return in_array($m, $pola['bulan_ukur'] ?? [], true);
    }
}

if (! function_exists('ikp_saring_ukur')) {
    /**
     * Nilai bulanan -> hanya bulan ukur; bulan lain null. Dipakai sebelum
     * setiap rumus agar isian lama di bulan non-ukur (mis. indeks yang dulu
     * "dicicil" 99 tiap bulan) tidak ikut dihitung.
     *
     * @param array<int, mixed> $nilai [1..12 => nilai]
     *
     * @return array<int, mixed> [1..12 => nilai|null]
     */
    function ikp_saring_ukur(array $pola, array $nilai): array
    {
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            $out[$m] = ikp_bulan_diukur($pola, $m) ? ($nilai[$m] ?? null) : null;
        }

        return $out;
    }
}

if (! function_exists('ikp_bulan_rilis_label')) {
    /**
     * Bulan ukur -> label waktu terbitnya angka: "Des 2026"; untuk
     * rilis_tahun_berikut, nilai tahun 2026 bulan 5 -> "Mei 2027".
     */
    function ikp_bulan_rilis_label(array $pola, int $tahun, int $m, bool $pendek = true): string
    {
        return ikp_nama_bulan($m, $pendek) . ' ' . ($tahun + (! empty($pola['rilis_tahun_berikut']) ? 1 : 0));
    }
}

if (! function_exists('ikp_keadaan_bulan')) {
    /**
     * Keadaan satu sel bulan untuk kisi target/realisasi & monitoring.
     *
     *  kode  tidak_diukur    bukan bulan ukur -> "—", tidak bisa diisi
     *        belum_waktunya  bulan ukur yang belum tiba (rilis: menunggu rilis)
     *        diukur          bulan ukur yang sudah tiba -> boleh diisi & jatuh tempo
     *  label teks sel singkat, ket = kalimat penjelas (title/keterangan)
     *  terbuka  realisasi boleh diisi
     *
     * "Tiba" dihitung dari (tahun + rilis_tahun_berikut, bulan) terhadap
     * ($thKini, $blKini) WIB — pemanggil yang menentukan waktu kini, fungsi
     * ini tetap murni.
     *
     * @return array{kode:string, label:string, ket:string, terbuka:bool}
     */
    function ikp_keadaan_bulan(array $pola, int $tahun, int $m, int $thKini, int $blKini): array
    {
        $rilis = ($pola['pola'] ?? '') === 'rilis';
        if (! ikp_bulan_diukur($pola, $m)) {
            if ($rilis) {
                $ukur = $pola['bulan_ukur'] ?? [];
                $lbl  = $ukur === [] ? 'bulan rilis belum diatur'
                    : implode(', ', array_map(static fn ($b) => ikp_bulan_rilis_label($pola, $tahun, (int) $b), $ukur));

                return ['kode' => 'tidak_diukur', 'label' => '—',
                    'ket' => 'Bukan bulan rilis. Nilai resmi' . (! empty($pola['penerbit']) ? ' ' . $pola['penerbit'] : '') . ' dirilis ' . $lbl . '.',
                    'terbuka' => false];
            }

            return ['kode' => 'tidak_diukur', 'label' => '—',
                'ket' => 'Tidak diukur bulan ini (bulan ukur: ' . ikp_bulan_ukur_label($pola['bulan_ukur'] ?? []) . ').',
                'terbuka' => false];
        }
        $thBuka = $tahun + (! empty($pola['rilis_tahun_berikut']) ? 1 : 0);
        if ($thBuka * 12 + $m > $thKini * 12 + $blKini) {
            return $rilis
                ? ['kode' => 'belum_waktunya', 'label' => 'menunggu rilis',
                    'ket' => 'Menunggu rilis ' . ikp_bulan_rilis_label($pola, $tahun, $m, false) . (! empty($pola['penerbit']) ? ' (' . $pola['penerbit'] . ')' : '') . '.',
                    'terbuka' => false]
                : ['kode' => 'belum_waktunya', 'label' => '', 'ket' => 'Bulan ini belum berjalan.', 'terbuka' => false];
        }

        return ['kode' => 'diukur', 'label' => '',
            'ket' => $rilis
                ? 'Bulan rilis ' . ikp_bulan_rilis_label($pola, $tahun, $m, false) . ': isi nilai resmi beserta tautan bukti publikasi.'
                : 'Bulan ukur.',
            'terbuka' => true];
    }
}

if (! function_exists('ikp_capaian_pola')) {
    /**
     * ikp_capaian() yang taat pola ukur — SATU-SATUNYA pintu capaian IKP
     * untuk halaman OPD, rekap Kabupaten/Bupati/Program Unggulan, Ruang OPD,
     * lampiran PK, dan API eKin.
     *
     *  - target & realisasi disaring ke bulan ukur (ikp_saring_ukur);
     *  - rentang tanpa bulan ukur -> tidak_diukur = true: capaian TIDAK
     *    dihitung (percentage null, status incomplete tanpa galat), bukan 0
     *    dan bukan 100, dan keterangan menyebut bulan ukurnya;
     *  - pola rilis tanpa nilai resmi di rentang -> menunggu_rilis = true;
     *  - selebihnya rumus lama: hitungan = Σr/Σt bulan terisi; posisi/rilis =
     *    nilai ukur TERAKHIR yang terisi vs target bulan itu.
     *
     * Kunci tambahan pada hasil: tidak_diukur, menunggu_rilis, bulan_ukur_rentang.
     */
    function ikp_capaian_pola(array $pola, array $target, array $real, int $dari = 1, int $sampai = 12, array $skala = [], ?int $tahun = null): array
    {
        $dari   = max(1, $dari);
        $sampai = min(12, $sampai);
        $metode = (string) ($pola['metode'] ?? '');
        $rilis  = ($pola['pola'] ?? '') === 'rilis';
        $t      = ikp_saring_ukur($pola, $target);
        $r      = ikp_saring_ukur($pola, $real);
        $ukur   = array_values(array_filter($pola['bulan_ukur'] ?? [], static fn ($m) => $m >= $dari && $m <= $sampai));
        $label  = static fn (int $m): string => $tahun !== null ? ikp_bulan_rilis_label($pola, $tahun, $m, false) : ikp_nama_bulan($m);

        if ($ukur === []) {
            $hasil = ikp_capaian($metode !== '' ? $metode : 'sum', [], [], $dari, $sampai, $skala);
            $rentang = $dari === $sampai ? ikp_nama_bulan($dari) : ikp_nama_bulan($dari) . '–' . ikp_nama_bulan($sampai);
            $semua   = $pola['bulan_ukur'] ?? [];
            $hasil['calculation_description'] = $rilis
                ? 'Bukan bulan rilis; nilai resmi' . (! empty($pola['penerbit']) ? ' ' . $pola['penerbit'] : '') . ' dirilis '
                    . ($semua === [] ? '(bulan rilis belum diatur)' : implode(', ', array_map($label, $semua))) . '.'
                : 'Tidak diukur pada ' . $rentang . ' (bulan ukur: ' . ikp_bulan_ukur_label($semua) . ').';
            $hasil['tidak_diukur']       = true;
            $hasil['menunggu_rilis']     = $rilis;
            $hasil['bulan_ukur_rentang'] = 0;

            return $hasil;
        }

        $hasil = ikp_capaian($metode, $t, $r, $dari, $sampai, $skala);
        $hasil['tidak_diukur']       = false;
        $hasil['menunggu_rilis']     = false;
        $hasil['bulan_ukur_rentang'] = count($ukur);

        if ($rilis && $hasil['bulan_terakhir'] === null && $hasil['error'] === null) {
            $hasil['menunggu_rilis']          = true;
            $hasil['calculation_description'] = 'Menunggu rilis ' . $label($ukur[0])
                . (! empty($pola['penerbit']) ? ' (' . $pola['penerbit'] . ')' : '') . '.';
        } elseif ($rilis && $hasil['status'] === 'calculated') {
            $hasil['calculation_description'] = 'Nilai resmi rilis ' . $label((int) $hasil['bulan_terakhir'])
                . ' dibanding target bulan itu (tidak dijumlah).';
        } elseif (($pola['pola'] ?? '') === 'posisi' && $hasil['status'] === 'calculated') {
            // Posisi terbagi: realisasi bulan itu = Σ posisi setiap bagian PADA BULAN ITU (diisi Admin OPD, saran
            // "Dari eKin"); capaian tetap posisi terakhir vs target bulan itu — tidak pernah dijumlah lintas bulan.
            $hasil['calculation_description'] = 'Posisi ' . ikp_nama_bulan((int) $hasil['bulan_terakhir'])
                . (! empty($pola['posisi_terbagi']) ? ' (jumlah posisi setiap bagian pada bulan itu; bulan ukur terakhir yang terisi)' : ' (bulan ukur terakhir yang terisi)')
                . ' dibanding target posisi bulan itu.';
        }

        return $hasil;
    }
}

if (! function_exists('ikp_bagi_pola')) {
    /**
     * Isi target bulan ukur dari target tahunan menurut pola.
     *
     *   hitungan       Bagi Rata cicilan ke bulan ukur (Σ = total).
     *   posisi/rilis   BUKAN cicilan: bulan ukur terakhir = total; bulan ukur
     *                  sebelumnya lintasan dari $awal (target tahun lalu /
     *                  baseline) bila ada, selain itu = total.
     * Bulan non-ukur tidak pernah diberi nilai. Hasil [bulan => nilai].
     *
     * @return array<int, float>
     */
    function ikp_bagi_pola(float $total, array $pola, ?float $awal = null, bool $bulat = false): array
    {
        $ukur   = $pola['bulan_ukur'] ?? [];
        $metode = (string) ($pola['metode'] ?? '');
        if ($ukur === [] || ! ikp_metode_valid($metode)) {
            return [];
        }
        $bagi = ($pola['pola'] ?? '') === 'hitungan'
            ? ikp_bagi_rata($total, count($ukur), 'sum', null, $bulat)
            : ikp_bagi_rata($total, count($ukur), $metode, $awal, $bulat);
        $out = [];
        foreach (array_values($ukur) as $i => $m) {
            $out[(int) $m] = $bagi[$i + 1];
        }

        return $out;
    }
}

if (! function_exists('ikp_cek_bulanan_pola')) {
    /**
     * ikp_cek() atas target BULAN UKUR saja (bulan non-ukur tidak diperiksa,
     * dan "periode terakhir" = bulan ukur terakhir, bukan Desember).
     *
     * @param array<int, float|int|null> $bulan [1..12 => target]
     */
    function ikp_cek_bulanan_pola(array $pola, ?float $induk, array $bulan): array
    {
        $anak = [];
        foreach ($pola['bulan_ukur'] ?? [] as $m) {
            $anak[(int) $m] = $bulan[$m] ?? null;
        }

        return ikp_cek((string) ($pola['metode'] ?? ''), $induk, $anak);
    }
}

if (! function_exists('ikp_pola_ringkas')) {
    /** "Rilis · Des · Komisi Informasi" / "Posisi ↑ · bulanan" / "Hitungan · triwulanan" — lencana singkat. */
    function ikp_pola_ringkas(array $pola, ?int $tahun = null): string
    {
        $meta = ikp_pola_meta()[$pola['pola']] ?? ['singkat' => '-'];
        $teks = $meta['singkat'];
        if ($pola['pola'] !== 'hitungan') {
            $teks .= ['trend_naik' => ' ↑', 'trend_turun' => ' ↓', 'trend_flat' => ' ='][$pola['metode'] ?? ''] ?? '';
        }
        if ($pola['pola'] === 'rilis') {
            $b = $pola['bulan_ukur'] ?? [];
            $teks .= ' · ' . ($b === [] ? 'bulan?' : implode(', ', array_map(
                static fn ($m) => $tahun !== null ? ikp_bulan_rilis_label($pola, $tahun, (int) $m) : ikp_nama_bulan((int) $m, true) . (! empty($pola['rilis_tahun_berikut']) ? ' (th. berikut)' : ''),
                $b
            )));
        } else {
            $teks .= ' · ' . mb_strtolower(ikp_periode_ukur_label($pola['periode_ukur'] ?? 'bulanan'));
        }
        if (! empty($pola['posisi_terbagi'])) {
            $teks .= ' · terbagi per bagian';
        }

        return $teks;
    }
}

/*
 * =====================================================================
 * SATUAN SEPADAN (29-09-2026) — aturan satuan pemikul angka IKP turunan
 * =====================================================================
 *
 * Baris pendelegasian hanya memikul ANGKA IKP bila satuan indikatornya sama
 * dengan satuan IKP; selain itu dihitung sebagai pendukung (IkpTurunService::
 * peranEfektif). Tabel sinonim ada di SATU tempat: app/Config/IkpSatuan.php.
 */

if (! function_exists('ikp_satuan_peta')) {
    /**
     * Peta bentuk → bentuk baku dari Config\IkpSatuan (juga dikirim ke
     * ikp-turun.js sebagai data-sinonim, supaya peramban memakai tabel yang sama).
     *
     * @return array<string, string>
     */
    function ikp_satuan_peta(?object $cfg = null): array
    {
        $cfg ??= config('IkpSatuan');
        $peta = [];
        foreach ((array) ($cfg->sinonim ?? []) as $kelompok) {
            $kelompok = array_values((array) $kelompok);
            if ($kelompok === []) {
                continue;
            }
            $baku = ikp_satuan_rapikan((string) $kelompok[0]);
            foreach ($kelompok as $b) {
                $peta[ikp_satuan_rapikan((string) $b)] = $baku;
            }
        }

        return $peta;
    }
}

if (! function_exists('ikp_satuan_rapikan')) {
    /**
     * " Pengikut (followers). " → "pengikut": huruf kecil, spasi dirapatkan, titik
     * di ujung dibuang, keterangan dalam kurung dibuang bila masih ada teks lain
     * ("(%)" saja → "%").
     * Cerminan: ikp-turun.js rapikanSatuan().
     */
    function ikp_satuan_rapikan(?string $satuan): string
    {
        $s = mb_strtolower(trim((string) $satuan));
        $s = (string) preg_replace('/\s+/u', ' ', $s);
        $tanpaKurung = trim((string) preg_replace('/\s*\([^)]*\)\s*/u', ' ', $s));
        $s = $tanpaKurung !== ''
            ? (string) preg_replace('/\s+/u', ' ', $tanpaKurung)
            : trim(str_replace(['(', ')'], '', $s));   // "(%)" → "%"

        return trim(rtrim($s, '.'));
    }
}

if (! function_exists('ikp_satuan_kunci')) {
    /** Bentuk baku satuan untuk dibandingkan ('' = satuan kosong / tidak diketahui). */
    function ikp_satuan_kunci(?string $satuan, ?array $peta = null): string
    {
        $s = ikp_satuan_rapikan($satuan);
        if ($s === '') {
            return '';
        }
        $peta ??= ikp_satuan_peta();

        return $peta[$s] ?? $s;
    }
}

if (! function_exists('ikp_satuan_sama')) {
    /**
     * Dua satuan sama? null = tidak dapat dinilai (salah satunya kosong) —
     * pemanggil tidak menurunkan peran atas dasar ketidaktahuan.
     *
     * Sama bila (setelah dirapikan & dipetakan sinonim):
     *   1. bentuknya sama ("Media" = "media", "Followers" = "Pengikut");
     *   2. satuan bergaris miring berbagi satu bagian ("Pekon/kelurahan" ~ "Kelurahan");
     *   3. satuan FRASA dibanding satuan SATU KATA: kata pertama frasa = kata itu
     *      ("Indeks Keterbukaan Informasi Publik" ~ "Indeks", "permohonan informasi" ~
     *      "Permohonan", "aduan SP4N-LAPOR!" ~ "Aduan"). Dua frasa harus sama utuh.
     * MENGAPA aturan 2–3: satuan IKP sering ditulis sebagai frasa penjelas; tanpa itu
     * pemikul angka indeks resmi (satuan "Indeks") ikut dihitung pendukung. "Media" vs
     * "Pengikut", "KM" vs "Paket", "Dokumen" vs "Usulan" tetap berbeda.
     * Cerminan: ikp-turun.js satuanSama().
     */
    function ikp_satuan_sama(?string $a, ?string $b, ?array $peta = null): ?bool
    {
        $peta ??= ikp_satuan_peta();
        $x = ikp_satuan_kunci($a, $peta);
        $y = ikp_satuan_kunci($b, $peta);
        if ($x === '' || $y === '') {
            return null;
        }
        if ($x === $y) {
            return true;
        }
        $bagian = static function (string $k) use ($peta): array {
            $out = [$k => true];
            $pot = preg_split('~\s*/\s*~u', $k) ?: [];
            if (count($pot) > 1) {
                foreach ($pot as $p) {
                    $kk = ikp_satuan_kunci($p, $peta);
                    if ($kk !== '') {
                        $out[$kk] = true;
                    }
                }
            }

            return $out;
        };
        if (array_intersect_key($bagian($x), $bagian($y)) !== []) {
            return true;
        }
        $kepala = static fn (string $k): ?string => str_contains($k, ' ') ? ikp_satuan_kunci(explode(' ', $k)[0], $peta) : null;

        return (! str_contains($y, ' ') && $kepala($x) === $y) || (! str_contains($x, ' ') && $kepala($y) === $x);
    }
}
