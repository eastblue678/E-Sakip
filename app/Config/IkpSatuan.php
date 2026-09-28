<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * =====================================================================
 * AKSARA+ — SATUAN SEPADAN untuk IKP turun sampai pelaksana (satu tempat)
 * =====================================================================
 *
 * Aturan satuan (keputusan 29-09-2026, "D3"): sebuah baris pendelegasian
 * boleh memikul ANGKA IKP hanya bila satuan indikatornya SAMA dengan satuan
 * IKP di puncak rantai angka. Bila berbeda, baris itu DIHITUNG SEBAGAI
 * PENDUKUNG (target sendiri, tidak ikut aritmetika IKP) — dihitung saat
 * dibaca, data tersimpan tidak diubah.
 *
 * Pemicu: uji coba pengguna di Diskominfo — IKP 381 "Pengelolaan Media
 * Komunikasi Publik" (satuan Media, target 48) diturunkan ke Es III porsi
 * 50.000, lalu ke "Jumlah Followers Media Sosial" (satuan Pengikut) —
 * 50.000 pengikut dijumlahkan seolah 50.000 media.
 *
 * Perbandingan: huruf besar/kecil & spasi diabaikan, titik di ujung dibuang,
 * keterangan dalam kurung dibuang bila masih ada teks lain ("Persentase (%)"
 * → "persentase"), lalu setiap bentuk dipetakan ke bentuk bakunya lewat
 * tabel sinonim di bawah. Dua aturan tambahan (ikp_satuan_sama): satuan
 * bergaris miring cukup berbagi satu bagian ("Pekon/kelurahan" ~ "Kelurahan"),
 * dan satuan FRASA sama dengan satuan SATU KATA bila kata pertamanya sama
 * ("Indeks Keterbukaan Informasi Publik" ~ "Indeks", "aduan SP4N-LAPOR!" ~
 * "Aduan") — satuan IKP sering ditulis sebagai frasa penjelas.
 * Tabel ini SENGAJA kecil: dua satuan yang tidak
 * dikenal dianggap berbeda bila teksnya berbeda — lebih baik Admin OPD
 * melihat "dihitung sebagai pendukung" lalu menyamakan satuannya daripada
 * angka yang berbeda makna diam-diam dijumlahkan.
 *
 * Cerminan di peramban: ikp-turun.js menerima peta yang sama lewat atribut
 * data-sinonim di formulir Turunkan IKP (tidak ada salinan kedua).
 */
class IkpSatuan extends BaseConfig
{
    /**
     * Kelompok sinonim: kata pertama = bentuk baku, sisanya bentuk lain yang
     * dianggap sama. Tulis huruf kecil.
     *
     * @var list<list<string>>
     */
    public array $sinonim = [
        ['%', 'persen', 'persentase', 'prosentase', 'percent', 'pct'],
        ['pengikut', 'followers', 'follower', 'akun pengikut', 'pengikut akun'],
        ['orang', 'jiwa', 'org'],
        ['dokumen', 'dok', 'dokumen/berkas'],
        ['laporan', 'lap', 'lap.'],
        ['kegiatan', 'keg', 'kali kegiatan'],
        ['rupiah', 'rp', 'rp.'],
        ['indeks', 'index', 'nilai indeks'],
        ['unit', 'buah unit'],
        ['konten', 'unggahan', 'postingan', 'posting'],
        ['media', 'kanal media', 'saluran media'],
    ];
}
