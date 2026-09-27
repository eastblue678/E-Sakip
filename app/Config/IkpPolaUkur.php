<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * =====================================================================
 * AKSARA+ — POLA UKUR indikator IKP: satu-satunya tempat konfigurasi
 * =====================================================================
 *
 * Setiap IKP punya POLA UKUR (kolom ikp.pola_ukur):
 *   hitungan  hasil dijumlahkan sepanjang periode (target bulanan = cicilan)
 *   posisi    nilai keadaan yang diukur SENDIRI dari data internal pada
 *             tanggal ukur (target = posisi yang diharapkan di bulan ukur)
 *   rilis     nilai yang DIKELUARKAN PIHAK LAIN secara berkala (indeks
 *             resmi, opini, predikat) — hanya ada angka di bulan rilis
 *
 * Berkas ini memuat dua hal yang dipakai migrasi 2026-09-28 (klasifikasi
 * otomatis data lama), form IKP (saran penerbit & bulan rilis), dan
 * pembangun simulasi:
 *   1. $polaRilis     — pola NAMA/SATUAN indikator yang ditebak "rilis";
 *   2. $rilisBawaan   — tabel bulan rilis bawaan per penerbit.
 *
 * MENGAPA DI SATU TEMPAT: jadwal rilis tiap indeks berbeda (keluhan
 * pengguna 28-09-2026: "setiap indeks itu beda-beda juga keluar
 * jadwalnya") dan bisa bergeser tiap tahun. Tabel ini hanya BAWAAN saat
 * IKP dibuat/diklasifikasikan; bulan rilis sesungguhnya disimpan PER IKP
 * (ikp.bulan_ukur, ikp.penerbit, ikp.rilis_tahun_berikut) dan boleh
 * diubah Admin OPD di form IKP tanpa menyentuh berkas ini.
 *
 * Bulan pada tabel adalah PERKIRAAN (lihat "Terbuka untuk dibahas" di
 * AKSARA_PLUS_IKP_PEMILIK_KINERJA.md), bukan jadwal resmi penerbit.
 */
class IkpPolaUkur extends BaseConfig
{
    /**
     * Nama atau satuan indikator yang berpola nilai resmi pihak lain.
     *
     * Sesuai spesifikasi 28-09-2026 + dua tambahan dari data Inspektorat
     * (kapabilitas APIP & maturitas SPIP = penilaian BPKP). Persentase yang
     * dihitung sendiri ("Persentase layanan informasi publik yang ditanggapi
     * tepat waktu") BUKAN rilis — lihat $awalInternal.
     */
    public string $polaRilis = '/\b(indeks|index|nilai (sakip|evaluasi|spip|mcp|reformasi)|opini|predikat|peringkat|skor spip|akreditasi|ipm|idm|kepatuhan standar pelayanan|survei nasional|kapabilitas apip|maturitas spip|level maturitas)\b/iu';

    /**
     * Awal nama indikator yang menandai angka yang dihitung SENDIRI walaupun
     * satuannya berbunyi "Indeks" (data lama kadang menaruh jenis indikator
     * di kolom satuan). Nama yang sendirinya memuat pola rilis tetap rilis.
     */
    public string $awalInternal = '/^(jumlah|banyaknya|persentase|presentase|prosentase|cakupan|rasio|rata-rata|luas|panjang)\b/iu';

    /**
     * Tabel rilis bawaan. Dibaca berurutan; baris pertama yang `cocok`
     * dengan nama + satuan indikator dipakai.
     *
     *   penerbit       teks bebas (disarankan di form, bisa diganti)
     *   bulan          bulan rilis perkiraan (1–12)
     *   tahun_berikut  true = nilai tahun N baru dirilis tahun N+1 (bulan
     *                  di atas = bulan rilis di tahun N+1)
     *
     * @var list<array{cocok:string, penerbit:string, bulan:int, tahun_berikut:bool, catatan:string}>
     */
    public array $rilisBawaan = [
        [
            'cocok'    => '/keterbukaan informasi/iu',
            'penerbit' => 'Komisi Informasi Provinsi Lampung', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Hasil monitoring & evaluasi keterbukaan informasi badan publik diumumkan akhir tahun.',
        ],
        [
            'cocok'    => '/\bspbe\b|pemerintahan berbasis elektronik/iu',
            'penerbit' => 'Kementerian PANRB', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Hasil evaluasi SPBE diumumkan akhir tahun (kadang awal tahun berikutnya).',
        ],
        [
            'cocok'    => '/keamanan informasi|indeks kami\b/iu',
            'penerbit' => 'Badan Siber dan Sandi Negara (BSSN)', 'bulan' => 11, 'tahun_berikut' => false,
            'catatan'  => 'Verifikasi Indeks KAMI oleh BSSN pada semester II.',
        ],
        [
            'cocok'    => '/pembangunan statistik|statistik sektoral|\bepss\b/iu',
            'penerbit' => 'Badan Pusat Statistik (BPS)', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Hasil Evaluasi Penyelenggaraan Statistik Sektoral (EPSS) diumumkan akhir tahun.',
        ],
        [
            'cocok'    => '/\bsakip\b|akuntabilitas kinerja/iu',
            'penerbit' => 'Kementerian PANRB', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Laporan Hasil Evaluasi SAKIP diumumkan akhir tahun.',
        ],
        [
            'cocok'    => '/reformasi birokrasi|indeks rb\b/iu',
            'penerbit' => 'Kementerian PANRB', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Hasil evaluasi Reformasi Birokrasi diumumkan akhir tahun.',
        ],
        [
            'cocok'    => '/\bopini\b|\bwtp\b|laporan keuangan/iu',
            'penerbit' => 'BPK RI Perwakilan Provinsi Lampung', 'bulan' => 5, 'tahun_berikut' => true,
            'catatan'  => 'Opini atas LKPD tahun N diserahkan sekitar Mei tahun N+1.',
        ],
        [
            'cocok'    => '/\bmcp\b|monitoring center for prevention/iu',
            'penerbit' => 'Komisi Pemberantasan Korupsi (KPK)', 'bulan' => 3, 'tahun_berikut' => true,
            'catatan'  => 'Skor MCP tahun N difinalkan awal tahun N+1.',
        ],
        [
            'cocok'    => '/integritas|\bspi\b/iu',
            'penerbit' => 'Komisi Pemberantasan Korupsi (KPK)', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Hasil Survei Penilaian Integritas diumumkan akhir tahun.',
        ],
        [
            'cocok'    => '/kepatuhan standar pelayanan|ombudsman|kepatuhan pelayanan publik/iu',
            'penerbit' => 'Ombudsman RI', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Penilaian kepatuhan penyelenggaraan pelayanan publik diumumkan akhir tahun.',
        ],
        [
            'cocok'    => '/\bipm\b|pembangunan manusia/iu',
            'penerbit' => 'Badan Pusat Statistik (BPS)', 'bulan' => 6, 'tahun_berikut' => true,
            'catatan'  => 'IPM kabupaten tahun N dirilis BPS pada tahun N+1.',
        ],
        [
            'cocok'    => '/\bidm\b|desa membangun/iu',
            'penerbit' => 'Kementerian Desa PDT', 'bulan' => 7, 'tahun_berikut' => false,
            'catatan'  => 'Status IDM ditetapkan pertengahan tahun.',
        ],
        [
            'cocok'    => '/\bspip\b|kapabilitas apip|maturitas/iu',
            'penerbit' => 'BPKP Perwakilan Provinsi Lampung', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Penilaian maturitas SPIP & kapabilitas APIP oleh BPKP.',
        ],
        [
            'cocok'    => '/layak anak|\bkla\b/iu',
            'penerbit' => 'Kementerian PPPA', 'bulan' => 7, 'tahun_berikut' => false,
            'catatan'  => 'Predikat Kabupaten Layak Anak diumumkan sekitar Hari Anak Nasional (Juli).',
        ],
        [
            'cocok'    => '/akreditasi/iu',
            'penerbit' => 'Lembaga akreditasi terkait', 'bulan' => 12, 'tahun_berikut' => false,
            'catatan'  => 'Jadwal akreditasi berbeda per lembaga; sesuaikan per IKP.',
        ],
    ];

    /** Bawaan bila pola rilis tetapi tidak ada baris tabel yang cocok. */
    public array $rilisLainnya = [
        'penerbit' => null, 'bulan' => 12, 'tahun_berikut' => false,
        'catatan'  => 'Penerbit & bulan rilis belum diketahui — isi di form IKP.',
    ];
}
