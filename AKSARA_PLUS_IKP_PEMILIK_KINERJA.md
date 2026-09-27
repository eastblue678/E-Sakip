# AKSARA+ — Kinerja Prioritas (IKP) & Pemilik Kinerja sampai Pelaksana

Catatan serah terima untuk tim pengembang AKSARA. Cabang: `fitur/ikp-kinerja-pelaksana`, di atas `63a8275`.

## Latar belakang

IKU Eselon II umumnya indikator tahunan yang terbit terlambat (PDRB, indeks, dsb.). Akibatnya Bupati tidak bisa memantau dan
menilai kinerja Kepala OPD setiap bulan, dan penilaian SKP bulanan menjadi prosedural. Perubahan ini menambah dua hal:

1. **Indikator Kinerja Prioritas (IKP)**: KPI terukur dari Program Unggulan Bupati, program prioritas, penugasan khusus dan
   penugasan tambahan, dengan target 5 tahun → tahunan → **bulanan**, realisasi bulanan, dan rekap triwulan yang dihitung
   (bukan diisi). Fitur prototipe "Prioritas" (Laravel) dipindah ke sini dengan gaya AKSARA.
2. **Pemilik Kinerja**: setiap simpul pohon kinerja (Es III, Es IV, pelaksana) diberi pemilik (pegawai, per tahun), dan
   setiap indikatornya satuan + target tahunan. Jenjang pelaksana sudah ada di AKSARA sejak migrasi 2026-07-27, tetapi belum
   terikat ke orang. Ini menjadi dasar SKP pegawai (aplikasi pendamping eKin menarik datanya lewat API).

## Pemasangan

| Lingkungan | Langkah |
|---|---|
| Dengan CLI | `php spark migrate` lalu `php spark db:seed IkpReferensiSeeder` |
| Tanpa CLI (phpMyAdmin) | jalankan `db/update_2026-09-26_ikp_kinerja.sql`, lalu `db/update_2026-09-26_ikp_referensi.sql`, `db/update_2026-09-28_ikp_pola_ukur.sql`, `db/update_2026-09-28_ikp_turun.sql` |

Keduanya idempoten. Untuk API eKin tambahkan `EKIN_API_TOKEN` di `.env` (token terpisah dari `API_TOKEN`).
Untuk Ruang OPD membaca eKin: `EKIN_INTERNAL_URL` dan `EKIN_AKSARA_TOKEN` (tanpa keduanya bagian eKin tampil "belum tersedia").
Impor data prototipe Prioritas (opsional): `php spark ikp:impor-prioritas --db=/path/database.sqlite [--kecuali=23,20,11] [--kering]`.

## Yang ditambahkan

- **Tabel** (semua baru, `utf8mb4_general_ci`): `ikp_program_unggulan`, `ikp_sasaran_pembangunan`, `ikp_buku_saku`, `ikp`,
  `ikp_target_tahunan`, `ikp_bulanan`, `ikp_inovasi`, `cascading_pemilik`, `cascading_indikator_target`.
- **Izin**: `ikp_opd.*`, `ikp_kab.*`, `ikp_bupati_monitoring.view`, `pemilik_kinerja.view|update|delete`
  (`IkpPermissionSeeder`, pola `RoleBupatiSeeder`).
- **Halaman OPD**: `adminopd/ikp` (daftar, form, target, breakdown, realisasi, rekap, cetak), `adminopd/ikp/inovasi`,
  `adminopd/ikp/lampiran-pk` (PDF Folio: PK + Lampiran I–V), `adminopd/pemilik-kinerja`.
- **Halaman Kabupaten/Bupati**: `adminkab/ikp` (+ `opd/{id}`, `program-unggulan`, `cetak`), `adminkab/pemilik-kinerja` (baca),
  `bupati/ikp` (+ `opd/{id}`), dan satu kartu pintasan di `bupati/dashboard`.
- **API** `api/ekin/{opd, pegawai, pegawai/{id}/kinerja, opd/{id}/ikp}`: GET saja, token terpisah, tanpa kolom pribadi.
  Didokumentasikan di `API_DOCUMENTATION.md` dan `public/openapi.json`.
- **Logika**: `app/Helpers/ikp_helper.php` dan `app/Services/IkpRekapService.php` (satu sumber angka untuk semua halaman, PDF
  dan API). Capaian memakai `calculateCapaianTotalPercentage()` apa adanya (helper lama tidak diubah).
- **Tes**: `tests/unit/IkpRekapTest.php` (28 kasus).

## Berkas lama yang disentuh (semua penambahan)

`app/Config/Routes.php` (satu blok di ujung berkas), `app/Filters/ModulePermissionFilter.php` (peta `ikp`, `pemilik-kinerja`),
`app/Filters/ApiTokenFilter.php` (argumen konsumen `api-token:ekin`; tanpa argumen perilaku lama tidak berubah),
`app/Views/templates/admin_menu.php` (blok menu bertanda AKSARA+), `app/Views/bupati/dashboard.php` (kartu pintasan),
`app/Commands/JagaAsap.php` (halaman IKP, Ruang OPD, Perjanjian Kinerja), `ALUR_FITUR_DAN_ROUTE.md` (§2, §7, §8.5–8.7, §8.10,
§9.6, §9.10, §9.11), `API_DOCUMENTATION.md`, `public/openapi.json`; untuk Ruang OPD & menu: tampilan `ikp/{_kepala,inovasi,kab_*}`,
`pemilik_kinerja/index`, `adminOpd/pk_renaksi/{index,monev}`, `{adminOpd,adminKabupaten}/cascading/cascading` (tab), `profile`.

## Masuk sebagai (prototipe; bawaan mati)

Admin Kabupaten dan Super Admin bisa memakai akun Admin OPD, Admin Kecamatan, Bupati, atau Inspektorat untuk menelusuri
AKSARA persis seperti pemiliknya, lalu kembali. Hanya aktif bila `.env` berisi `demo.masukSebagai = true`.

- **Berkas baru**: `app/Services/MasukSebagaiService.php`, `app/Controllers/MasukSebagaiController.php`,
  `app/Views/masuk_sebagai/index.php`, `app/Views/templates/pita_masuk_sebagai.php`, `tests/unit/MasukSebagaiTest.php`.
- **Berkas lama yang disentuh (penambahan)**: `Routes.php` (3 rute di ujung), `AuthFilter` (akun asli diperiksa ulang; ganti
  sandi/2FA akun tiruan ditolak), `ReadOnlyRoleFilter` (jalur `masuk-sebagai/` agar akun Bupati tiruan bisa kembali),
  `LoginController::logout` (saat meniru = kembali), `activity_helper` (catatan pelaku asli), `admin_chrome` (pita),
  `admin_menu` (menu).
- **Aturan**: hak dinilai dari akun ASLI; akun `admin` & `admin_kab` tidak bisa ditiru; sesi diregenerasi setiap berganti
  akun; mulai/ganti/kembali tercatat di `activity_logs` (modul `masuk_sebagai`) atas nama akun asli.
- Kolom "Kepala perangkat daerah" di halaman ini diambil dari PK jpt/camat terbaru tahun berjalan, bukan `opd.id_kepala_opd`
  (banyak yang basi).
- Saringan `GET masuk-sebagai?opd_id=` (pintasan dari Ruang OPD) menyaring akun menurut id perangkat daerah;
  `MasukSebagaiService::adaAkunUntukOpd()` memutuskan apakah pintasan itu ditampilkan.

## Ruang OPD — satu pintu per perangkat daerah

Keluhan pengguna: saat Admin Kabupaten menerima audiensi satu OPD, dokumennya tersebar di tujuh menu dan OPD harus
dipilih ulang di tiap menu. Pembanding (e-SAKIP publik kabupaten lain) menyusun daftar berkas PDF per tahap SAKIP;
AKSARA berbasis data, jadi Ruang OPD menampilkan **kelengkapan dan angka hidup**, bukan sekadar berkas.

- **Rute** (blok AKSARA+ di ujung `Routes.php`, filter `auth`, GET saja): `ruang-opd` (matriks), `ruang-opd/{id}` (hub),
  `ruang-opd/{id}/cascading-pegawai`, `ruang-opd/{id}/pk-pegawai`, `ruang-opd/{id}/pk-pegawai/{pegawai_id}`.
- **Akses** dijaga `RuangOpdController` (jalur di luar `adminkab/*`/`adminopd/*`, jadi modperm tidak berlaku):
  `admin`, `admin_kab`, `admin_inspektorat`, `bupati` → semua OPD (baca); `admin_opd`, `admin_kecamatan` → OPD sesinya
  (index dialihkan ke hubnya; id OPD lain dikembalikan ke hub sendiri dengan pesan; dokumen PK pegawai hanya untuk pegawai
  yang tercantum di daftar PK OPD itu).
- **Matriks** (`RuangOpdService::matriks`): baris = OPD, dikelompokkan Sekretariat/Badan/Dinas, Kecamatan, Kelurahan,
  Lainnya (kembaran tanpa data 13 & 213 disembunyikan); kolom = Renstra, Renja/RKT, IKU, Pohon Kinerja (simpul Es III s.d.
  pelaksana & % berpemilik), Perjanjian Kinerja (JPT/Camat, jumlah administrator & pengawas), Rencana Aksi (indikator PK
  ber-renaksi), MONEV (capaian triwulan yang sudah jatuh tempo terisi), IKP (rata-rata capaian s.d. bulan lalu, rumus
  `ikp_capaian`), LAKIP (tahun ini & tahun lalu, pengesahan), Pegawai eKin (% ber-SKP, SKP bulanan dinilai pada bulan
  terakhir yang SUDAH dinilai — `predikat_bulan` eKin, bukan bulan berjalan yang baru berisi draf — dan PK pegawai
  ditandatangani di eKin; pejabat yang PK-nya dokumen PK AKSARA dihitung terpisah "+n PK AKSARA", tidak sebagai
  ditandatangani). Warna: hijau lengkap, kuning sebagian, merah belum ada padahal wajib, abu tidak berlaku/belum jatuh
  tempo (LAKIP tahun berjalan yang sedang disusun juga abu). Sel eKin hijau menuntut ≥90% pegawai ber-SKP DAN bulan lalu
  sudah dinilai. **Skor kelengkapan dokumen SAKIP** = rata-rata sel berwarna Perencanaan s.d. Pelaporan (kolom eKin tidak
  ikut — baru sebagian unit dimuat di eKin); unit yang tidak wajib menyusun dokumen SAKIP sendiri (kelurahan, UPT) tidak
  diberi skor dan tidak masuk rata-rata/urutan. Setiap dokumen = SATU kueri `GROUP BY opd_id` untuk semua OPD.
  Cari (termasuk sebutan sehari-hari: Diskominfo, Dinkes, BPKAD, …), saring kelompok, klik ringkasan kolom untuk menampilkan
  hanya OPD yang belum lengkap di dokumen itu, urut skor.
- **Hub**: kepala OPD menurut PK JPT/Camat tahun itu (termasuk Plt./Plh.; jabatan puncak didahulukan dari Asisten/Staf Ahli,
  bukan `opd.id_kepala_opd` yang basi), skor per tahap siklus, lalu kartu per dokumen: ringkasan di tempat + tombol
  **Buka** ke halaman lama yang BOLEH dibuka peran itu dengan OPD sudah terpilih. Peta tautannya satu fungsi murni,
  `App\Services\RuangOpdTautan::untuk()` (Bupati hanya `/bupati` & halaman publik; peran OPD hanya `/adminopd` tanpa
  `opd_id`; kabupaten `/adminkab?...opd_id=`; tanpa halaman yang cocok → tidak ada tombol, ringkasan di kartu itulah datanya).
  Rekap IKP kabupaten (`adminkab|bupati/ikp/opd/{id}`) hanya ditautkan untuk OPD jenis opd/kecamatan
  (`RuangOpdService::ikpKabBerlaku`, aturan `AdminKab\IkpController::opdSah`) — kelurahan & UPT tidak (dulu 404). Label
  jenjang pohon kinerja mengikuti jenis unit (`RuangOpdService::labelJenjang`, dipakai juga halaman Pemilik Kinerja: di
  kecamatan Camat = Eselon III, jadi simpul es3 = Eselon IV). Pintasan "Masuk sebagai admin OPD ini" hanya tampil bila ada
  akun aktif untuk OPD itu dan menaut lewat `masuk-sebagai?opd_id=` (RSUD, UPT, kelurahan tidak punya akun).
- **Kinerja Pegawai (eKin)**: angka RINGKAS, **Cascading Pegawai** (pohon RHK Kepala → … → staf lewat `rhk_atasan_id`, target
  IKI kuantitas, status porsi; lingkaran/yatim diputus jadi akar), **PK Pegawai** (daftar berstatus + dokumen baca-saja:
  pernyataan & lampiran target bulanan, bisa dicetak peramban). Status "PK di AKSARA" (`lewat_aksara`) = pihak pertama PK
  jabatan; eKin sengaja tidak membuat PK pegawai untuknya (jawaban API kosong), jadi AKSARA membaca PK-nya sendiri
  (`RuangOpdService::pkAksaraPerPihakPertama`, `pk.pihak_1` = id pegawai — id eKin = id AKSARA, eKin menyalin id saat
  sinkron): di daftar, pihak kedua & jumlah indikator diambil dari PK AKSARA terbaru dan tombol **PK AKSARA** membuka
  halaman PK itu di Ruang OPD (sasaran/indikator/target, Lihat/Cetak PDF — bagi admin OPD hanya PK yang tersimpan di
  OPD-nya — dan tautan `perjanjian-kinerja?pegawai={id}`), bukan kertas eKin tanpa isi. Bila AKSARA tidak menemukan PK
  orang itu, halaman menandainya "PK tidak ditemukan di AKSARA" (eKin dan AKSARA tidak sepakat). Unit yang pegawainya
  sudah dimuat tetapi belum ber-SKP → satu kalimat "Belum ada SKP", bukan kartu bernilai nol. eKin mati / belum dipasang /
  OPD tidak ada di eKin → "Data eKin belum tersedia" dengan alasannya; dokumen SAKIP lain tidak terpengaruh.

### EkinClient (arah eKin → AKSARA+)

`app/Services/EkinClient.php` membaca kontrak `GET <EKIN_INTERNAL_URL>api/aksara/{ringkas | opd/{id}/ringkas |
opd/{id}/cascading | opd/{id}/pk-pegawai | pk-pegawai/{pegawai_id}}?tahun=` dengan header `Authorization: Bearer
<EKIN_AKSARA_TOKEN>`. `.env` AKSARA: `EKIN_INTERNAL_URL` (mis. `http://127.0.0.1:8097/`) dan `EKIN_AKSARA_TOKEN` (token
berbeda dari `EKIN_API_TOKEN` yang dipakai arah sebaliknya). Batas waktu 5 detik, tembolok 5 menit (kegagalan 1 menit),
setiap kegagalan = `null` + kode alasan (`belum_dikonfigurasi`, `tidak_terjangkau`, `ditolak`, `belum_tersedia`,
`galat_server`, `format`). Token tidak pernah masuk log, kunci tembolok, atau layar.

## Menu dirampingkan — satu butir per konsep

Pilihan di dalam satu konsep pindah ke halaman sebagai tab/saringan (`app/Views/templates/tab_halaman.php`); semua alamat
lama tetap berlaku.

| Butir | Dulu | Kini |
|---|---|---|
| Ruang OPD / Ruang OPD Saya | — | baru, dekat Dashboard (juga di menu Bupati) |
| Perjanjian Kinerja | PK JPT / Kecamatan / Administrator / Pengawas (OPD); PK Bupati (kabupaten) | `perjanjian-kinerja`: saringan tahun, jenis, OPD, cari nama/jabatan, `pegawai` (id pihak pertama); aksi Lihat/Ubah/Cetak ke rute lama peran itu; Tambah PK dengan pilihan jenis; isi PK bisa dibuka di tempat. Peran OPD melihat SEMUA PK OPD-nya selain PK Bupati; PK puncak yang ditawarkan (JPT atau Camat) mengikuti `opd.jenis`, bukan nama peran (kecamatan berakun `admin_opd` dulu kehilangan PK Camat) |
| Kinerja Prioritas (IKP) | 5 sub-butir (OPD), 3 (kabupaten) | 1 butir + tab di setiap halaman IKP (`ikp/_tab_opd`, `ikp/_tab_kab`; Pemilik Kinerja lintas OPD jadi tab kabupaten) |
| Pohon Kinerja & Cascading | 2 butir ke halaman yang sama | 1 butir (periode aktif terisi) + tab Tabel/Pohon |
| Target & Rencana Aksi, MONEV (kabupaten) | 4 butir | 2 butir + tab PK Bupati / PK Perangkat Daerah (`pk_renaksi/_tab_pengukuran`) |

Perbaikan kecil yang ikut: tautan "Tentang Kami" admin kecamatan menuju `/adminkab` (ditolak) → kini `/adminopd`; nama
akun panjang di halaman Profil tidak lagi melebarkan halaman di ponsel.

**Berkas baru**: `app/Controllers/RuangOpdController.php`, `app/Controllers/PerjanjianKinerjaController.php`,
`app/Services/{RuangOpdService,RuangOpdTautan,EkinClient}.php`, `app/Views/ruang_opd/*`, `app/Views/perjanjian_kinerja/index.php`,
`app/Views/templates/tab_halaman.php`, `app/Views/ikp/_tab_{opd,kab}.php`, `app/Views/adminOpd/pk_renaksi/_tab_pengukuran.php`,
`tests/unit/RuangOpdTest.php`. Tidak ada perubahan skema. Uji peramban: `uji/ms/cek_ruang_opd.mjs` (repo demo).

## Pohon Kinerja berpemilik & peran "penugasan tambahan" (27-09-2026)

Menu **Pohon Kinerja** (tab di "Pohon Kinerja & Cascading", adminopd dan adminkab mode OPD) kini menyajikan
simpul dan pemiliknya dalam satu bagan, dengan bahasa visual yang sama dengan menu **Pemilik Kinerja**:

- **Kotak pemilik di tiap simpul** mulai Eselon II: inisial, nama, dan peran (PJ / Anggota / Tambahan);
  simpul tanpa pemilik bergaris putus merah "Belum ada pemilik". Eselon II mengikuti pihak pertama PK JPT/Camat.
  Kotak menaut ke `pemilik-kinerja#simpul-{id}` (halaman itu membuka jalur dan menyorot simpulnya).
- **Bilah alat**: tampil/sembunyikan pemilik, sorot yang belum berpemilik, *fokus cabang* (bagan digambar ulang
  hanya jalur akar → satu cabang Eselon III, supaya terbaca tanpa memperkecil semuanya), pilihan tahun,
  "Paskan layar", dan tautan kelola. Bagan kini terbuka dengan akarnya di tengah.
- **Bagian di bawah bagan**: cakupan simpul berpemilik per jenjang, daftar simpul belum berpemilik (klik →
  disorot di bagan), dan **pegawai yang belum punya tugas** di pohon ini (metrik "matriks 0"), bisa dicari
  dan disaring per struktural/fungsional/pelaksana.
- **Warna jenjang** di Pemilik Kinerja disamakan dengan kotak bagan (Eselon II oranye, III ungu, IV/JF merah
  rose, Pelaksana cokelat), dan Pemilik Kinerja punya tombol "Lihat sebagai pohon".
- Data dan aturan pemilik dipindah dari `PemilikKinerjaController` ke `App\Services\PohonPemilikService`
  (dipakai kedua layar lewat `Controllers\Concerns\PohonPemilikTrait`). Partial
  `adminOpd/cascading/_pohon_opd_tree` hanya menggambar pemilik bila menerima `pemilikPohon`; halaman
  publik dan cetak tidak berubah.

**Peran ketiga: `penugasan_tambahan`** (kolom `cascading_pemilik.peran` sudah VARCHAR(20) — tanpa migrasi).
Nomenklatur mengikuti PermenPANRB 6/2022 Lampiran BAB II: Tahap 5 menyebut *penugasan* (penunjukan atau
pengajuan sukarela, termasuk lintas unit kerja), Tahap 6 membagi rencana hasil kerja menjadi *hasil kerja
utama* dan *hasil kerja tambahan*. "Penugasan khusus" di regulasi hanya dipakai untuk penugasan dari pejabat
di luar unit/instansi, jadi tidak dipakai sebagai nama peran. Akibatnya:
- eKin menarik simpul berperan ini sebagai **RHK tambahan** (`TarikSakipService::jenisUntukSimpul`);
- pegawai yang hanya memegang penugasan tambahan tetap tercantum sebagai "belum punya tugas" (belum ada hasil
  kerja utama), dengan tanda "hanya penugasan tambahan";
- di Pemilik Kinerja, tombol peran pada chip membuka menu tiga pilihan (dulu sakelar PJ ↔ Anggota).

**Pegawai kembar.** Data pegawai AKSARA memuat NIP yang sama di dua baris (mis. PK JPT menunjuk baris lama
tanpa kode jabatan). Daftar "belum punya tugas" di kedua layar kini satu orang per NIP: peran semua barisnya
dijumlahkan dan orangnya bertanda "tercatat 2× di data pegawai" — sinyal untuk BKPSDM merapikan data.

**Tidak lagi melayang.** Bilah cari Pemilik Kinerja, navigasi lompat Ruang OPD, dan bilah tombol formulir IKP
dulu `position: sticky` dengan latar setengah tembus: saat digulir, isian di belakangnya terlihat, dan
navigasi Ruang OPD terselip di bawah kepala aplikasi. Ketiganya kini ikut mengalir bersama halaman.
Bilah simpan revisi IKU (kode upstream, sengaja lengket) tidak diubah.

Uji: `tests/unit/PohonPemilikTest.php`; peramban `uji/pohon/cek_pohon_pemilik.mjs` (repo demo, 36 pemeriksaan,
mengembalikan data pemilik seperti semula).

## PK Pegawai & Rencana Aksi sampai pelaksana dari eKin (27-09-2026)

Jenjang di bawah Pengawas hidup di eKin; AKSARA+ kini menampilkannya di tiga pintu yang biasa dipakai, tanpa
menyalin data (semua dibaca lewat `EkinClient`, tembolok 5 menit):

- **Perjanjian Kinerja** (menu terpadu): pil **PK Pegawai · eKin** di samping JPT/Administrator/Pengawas —
  daftar PK pegawai (draf, diajukan, dikembalikan, ditandatangani) dengan saringan status, 50 baris per
  halaman, tombol *Dokumen* dan *Rencana aksi*. Pejabat yang PK-nya dokumen AKSARA tidak diulang.
- **Target & Rencana Aksi**: tab "Rencana Aksi Pegawai (eKin)" (admin OPD) / "Pegawai s.d. Pelaksana (eKin)"
  (kabupaten) → `rencana-aksi-pegawai`: peran OPD langsung ke OPD-nya; peran lintas OPD melihat ringkasan per
  OPD (pegawai ber-SKP, rencana aksi bulan terpilih, tercapai, capaian rata-rata, RA tanpa kegiatan).
- **Ruang OPD**: tab & tombol **Rencana Aksi Pegawai** → `ruang-opd/{id}/rencana-aksi-pegawai?bulan=`: pegawai
  disusun per atasan langsung (Kadis → Sekdis/Kabid → Kasubag/Kasi → staf), status SKP & PK, jumlah rencana
  aksi bulan itu, tercapai, capaian, dan peringatan "belum ada kegiatan harian"; saringan & pencarian
  mempertahankan atasan sebagai konteks. Rincian `…/rencana-aksi-pegawai/{pegawai}`: kartu per RHK
  (utama/tambahan, RHK pimpinan yang diintervensi, IKI tiga aspek) dengan kisi Jan–Des target/realisasi
  berwarna (tercapai, bulan berjalan, bulan lalu belum tercapai, direncanakan).
- Lingkup: sama dengan dokumen PK Pegawai — pegawai harus tercantum di daftar OPD itu (admin OPD tidak bisa
  membuka pegawai OPD lain dengan mengganti angka di alamat → 404).
- API eKin baru: `api/aksara/opd/{id}/rencana-aksi` dan `api/aksara/pegawai/{id}/rencana-aksi` (README eKin §17).

Uji: `tests/unit/RencanaAksiPegawaiTest.php`; peramban `uji/pohon/cek_ra_pegawai.mjs` (angka = API eKin,
lingkup, 390 px).

## Pola ukur indikator IKP: hitungan · posisi · rilis (28-09-2026)

**Keluhan pengguna.** "Indeks dibuat dicicil itu ngaco banget. Masa indeks keterbukaan informasi dicicil. Itu kan
memang keluar setahun sekali dan setiap indeks itu beda-beda juga keluar jadwalnya. Intinya ini harus solid."
Bukti di data: IKP Diskominfo "Keterbukaan Informasi Publik" (satuan Indeks Keterbukaan Informasi Publik) bertarget 99
dan berrealisasi 100 SETIAP bulan Jan–Agu 2026, sehingga capaiannya 101 % sejak Januari.

**Keputusan.** `metode` (sum/trend) hanya menjawab *bagaimana* angka diringkas, tidak *kapan* angka itu ada. Setiap IKP
kini punya **pola ukur**:

| Pola | Arti | Target bulan ukur | Realisasi | Capaian |
|---|---|---|---|---|
| `hitungan` | hasil dijumlah sepanjang periode | cicilan (Σ = target tahunan) | hasil bulan itu (tambahan) | Σ realisasi ÷ Σ target bulan terisi |
| `posisi` | keadaan yang diukur SENDIRI dari data internal pada tanggal ukur (% tepat waktu, nasabah AKTIF, cakupan) | posisi yang diharapkan (bukan cicilan) | posisi terbaru dari rekap resmi | nilai ukur terakhir ÷ target bulan itu |
| `rilis` | nilai yang DIKELUARKAN pihak lain (indeks KI, SPBE, KAMI, SAKIP/RB, opini BPK, MCP/SPI, Ombudsman, IPM, IDM, SPIP, akreditasi) | HANYA bulan rilis | nilai resmi saat dirilis, **bukti publikasi wajib** | nilai resmi ÷ target bulan rilis; tidak pernah dicicil/dijumlah |

Bulan di luar `bulan_ukur` **tidak diukur**: tanpa target, tanpa realisasi, capaian tidak dihitung (bukan 0, bukan
100), tidak ikut rata-rata apa pun, dan tidak dianggap "belum lapor". `metode` kini diturunkan dari pola:
hitungan → `sum`; posisi/rilis → `trend_naik|trend_turun|trend_flat` (= *arah*: makin tinggi/rendah baik, dipertahankan).

**Skema** (migrasi `2026-09-28-000001_AddPolaUkurToIkp`, kembaran `db/update_2026-09-28_ikp_pola_ukur.sql`, idempoten):
kolom `ikp.pola_ukur`, `periode_ukur` (bulanan|triwulanan|semesteran|tahunan|khusus), `bulan_ukur` ("6,12"),
`penerbit`, `rilis_tahun_berikut` (nilai tahun N dirilis N+1, mis. opini BPK/IPM — realisasi tahun N diisi saat rilis
di N+1), `pola_ditebak` (1 = hasil klasifikasi otomatis → chip **Periksa pola ukur** sampai Admin OPD menyimpan form).
Migrasi menebak pola data lama (sum → hitungan; trend → posisi; nama/satuan berpola indeks/nilai resmi → rilis) dan
**tidak mengubah angka apa pun**: isian lama di bulan non-ukur cukup diabaikan rumus (layar menandai "n isian lama di
bulan non-ukur diabaikan"). Tanpa CLI, berkas SQL hanya menambah kolom; baris ber-`pola_ukur` NULL ditebak dengan aturan
yang sama saat dibaca (`ikp_pola()`).

**Satu tempat konfigurasi**: `app/Config/IkpPolaUkur.php` — pola nama/satuan rilis (`$polaRilis`), kata awal yang
menandai angka internal walau satuannya "Indeks" (`$awalInternal`), dan **tabel rilis bawaan** (penerbit, bulan rilis
perkiraan, tahun berikut, catatan) untuk 15 jenis nilai resmi. Tabel ini hanya BAWAAN (saran di form & klasifikasi);
bulan rilis sesungguhnya disimpan per IKP dan diubah Admin OPD di form tanpa menyentuh berkas ini.

**Rumus (murni, `app/Helpers/ikp_helper.php`, diuji `tests/unit/IkpPolaUkurTest.php`, 22 kasus):** `ikp_pola_tebak`,
`ikp_pola` (konteks + metode efektif), `ikp_saring_ukur`, `ikp_keadaan_bulan` (tidak_diukur | belum_waktunya | diukur,
memperhitungkan rilis tahun berikut), `ikp_capaian_pola` (SATU pintu capaian semua layar), `ikp_bagi_pola`,
`ikp_cek_bulanan_pola`, `ikp_pola_ringkas`. `ikp_status()` mengenal status abu-abu `tidak_diukur` & `menunggu_rilis`.
`IkpRekapService::rakit()` menyaring bulan non-ukur SEKALI sehingga daftar, rekap, PDF, Lampiran PK, rekap Kabupaten,
monitoring Bupati, Program Unggulan, Ruang OPD, dan API eKin membaca angka yang sama.

**Layar.**
- *Form IKP* — bagian baru "Pola ukur: kapan angkanya ada?": tiga kartu berpenjelasan + contoh, arah nilai, periode ukur
  + chip bulan (rilis: "Bulan rilis"), penerbit (wajib untuk rilis, dengan saran dari tabel bawaan), "nilai tahun N
  dirilis tahun N+1". Menu "Metode perhitungan" diganti pola + arah.
- *Target & Breakdown* — bulan non-ukur "—" terkunci ("tidak diukur"/"bukan bulan rilis"); **Bagi rata hanya untuk
  hitungan** (cicilan ke bulan ukur); posisi/rilis punya "Isi target posisi/bulan rilis" (bulan ukur terakhir = target
  tahunan, bulan ukur sebelumnya lintasan dari target tahun lalu/baseline). Server menolak angka di bulan non-ukur.
- *Realisasi* — sel non-ukur "—" berketerangan, tidak bisa diisi; indeks diisi lewat "Catat nilai resmi" (nilai + tautan
  bukti publikasi wajib, ditegakkan server juga saat bukti dihapus); bulan rilis terbuka baru saat bulannya tiba.
- *Rekap Kabupaten/Bupati* — "Realisasi bulan X: n / m" memakai m = IKP yang **wajib** melapor bulan itu; kartu Bupati
  memisahkan "tidak diukur bulan ini (indeks resmi menunggu rilis / posisi di luar bulan ukur)" dari "belum dapat
  dinilai"; daftar "5 IKP perlu perhatian" tidak memuat IKP yang tidak diukur. Kisi bulanan OPD menulis "—".
- *Lampiran PK (cetak)* — kolom METODE → POLA UKUR; bulan non-ukur "—" dengan legenda.
- *API eKin* (`api/ekin/opd/{id}/ikp`, `pegawai/{id}/kinerja`) — `pola_ukur`, `periode_ukur`, `bulan_ukur`, `penerbit`,
  `rilis_tahun_berikut`, `pola_ditebak`; target bulanan hanya bulan ukur (juga `target_bulanan` indikator simpul yang
  bertaut IKP). Didokumentasikan di `API_DOCUMENTATION.md` & `public/openapi.json`.

**Data simulasi.** Pembangun di luar repo (`/root/demo-kinerja/simulasi/02_ikp.php`, `02b_selaras.php`,
`02c_lengkapi_ikp.php`) kini menerima `AKSARA_DB`, `EKIN_DB`, `AKSARA_ROOT` (bawaan demo) dan taat pola: langkah 3p
menetapkan pola (tebakan dikonfirmasi, kecuali tiga yang namanya "Jumlah …" tetapi bersatuan persen/indeks — IKP 330,
341, 344 — dibiarkan "Periksa pola ukur" sebagai contoh), 3d mengosongkan target bulan non-ukur dan menetapkan bulan ukur
terakhir = target tahunan, langkah 4 mengosongkan realisasi simulasi di bulan non-ukur / rilis yang belum keluar, dan
nilai rilis 2025 disimulasikan sebagai baseline (baris tahun 2025, berbukti). 02b memeriksa konsistensi menurut pola;
02c menghitung kelengkapan terhadap jumlah bulan ukur. Dijalankan pada `aksara_uji`: yang berubah HANYA 6 IKP rilis
(Inspektorat 23 kapabilitas APIP, 24 skor SPIP, 27 Indeks Integritas; Diskominfo 380 IKIP, 385 Indeks Pembangunan
Statistik, 386 Indeks KAMI) — 66 sel target/realisasi bulan non-ukur dikosongkan (48 realisasi), 6 baris nilai rilis 2025
ditambah; 348 IKP lain identik sel demi sel. Pembangunan ulang kedua tidak mengubah apa pun (idempoten). Peta
`02_ikp_peta.json` hanya ditulis lari `aksara_demo`.

**Uji.** Unit `tests/unit/IkpPolaUkurTest.php` (22 kasus). Peramban `uji/malam/cek_pola_ukur.mjs` (repo demo; 83
pemeriksaan pada 1366 & 390 px: form/target/realisasi IKP rilis 380, posisi 323, hitungan 384 Diskominfo; penolakan
server untuk realisasi indeks di bulan non-rilis, sebelum bulan rilis, tanpa bukti, dan target indeks yang dicicil;
rekap Kabupaten/Bupati; tanpa gulir samping; tanpa elemen melayang).

### Terbuka untuk dibahas

1. **Tabel bulan rilis bawaan** (`Config/IkpPolaUkur.php`) adalah perkiraan, bukan jadwal resmi; tiap tahun bisa
   bergeser (mis. hasil SPBE kadang keluar Januari tahun berikutnya). Perlu dicocokkan Bapperida/Diskominfo per indeks.
2. **Rilis yang terlambat**: bila bulan rilis sudah lewat tetapi nilai belum diisi, statusnya tetap "Menunggu Rilis"
   (abu-abu), bukan "belum lapor". Perlu status "rilis terlambat" (mis. > 2 bulan lewat)?
3. **Periode "khusus"** (bulan ukur tidak berjarak tetap, mis. Mei & November) ditambahkan di luar lima nilai spesifikasi
   agar chip bulan bebas; periode diturunkan dari chip, bukan dipercaya dari isian.
4. **Kolom `metode` data lama tidak diubah migrasi**; metode efektif diturunkan saat dibaca (rilis/posisi tidak pernah
   dijumlah). Kolom baru selaras saat admin menyimpan form. Perlu skrip penyelarasan massal untuk produksi?
5. **Isian lama di bulan non-ukur** (data nyata) tidak dihapus, hanya diabaikan dan ditandai. Hapus fisik lewat tombol
   "Bersihkan" per IKP?
6. **Penerbit wajib** untuk pola rilis (keputusan malam ini) — form menolak rilis tanpa penerbit.
7. **Revisi nilai resmi** (mis. BPS merevisi IPM): nilai lama ditimpa; belum ada riwayat nilai rilis selain log aktivitas.
8. **Bukti publikasi berupa tautan** (bukan unggah berkas), sama dengan bukti realisasi IKP lain.
9. **Rilis tahun berikutnya** (opini BPK, IPM): capaian tahun N baru muncul di tahun N+1, jadi monitoring bulanan tahun
   N selalu "Menunggu Rilis" untuk IKP itu. Apakah Bupati ingin melihat nilai tahun N-1 sebagai pengganti sementara?
10. **Nilai rilis tahun lalu sebagai baseline**: di simulasi disimpan sebagai baris realisasi tahun 2025 (rekap 2025 kini
    hanya berisi indeks). Alternatif: isi `ikp.baseline`.
11. **Hitungan tidak-bulanan** (triwulanan/semesteran) diizinkan: target dicicil hanya ke bulan ukur.
12. **eKin** membaca salinan `ikp_realisasi`; setelah data `aksara_uji` dibetulkan, salinan eKin harus disinkron ulang
    (`spark ekin:sinkron-ikp`) dan rencana aksi IKI rilis mengikuti `bulan_ukur` (tugas sisi eKin).
13. Klasifikasi otomatis memakai nama + satuan; "Jumlah … (satuan Indeks/Persen)" tetap posisi bertanda periksa.
    Perlu daftar putih/hitam per OPD?

## IKP turun sampai pelaksana — pendelegasian lewat pohon kinerja (28-09-2026)

**Keluhan pengguna.** "SKP dari IKU sudah turun temurun. Tapi IKP belum turun temurun … IKP hanya berhenti di
Es 2, ga sampai bawah. Pikirkan proses pendelegasiannya, termasuk bagaimana bisa sampai masuk ke RHK dsb."
Keadaan sebelumnya: IKP melekat di PK Kepala OPD; eKin menawarkan IKP lewat alasan `kepala_opd` (semua IKP OPD),
`pj` (12 IKP) dan `simpul` (8 tautan langsung) — tidak ada alur menurunkannya jenjang demi jenjang.

**Keputusan: jalur resmi = pohon kinerja AKSARA+ (sama dengan IKU), tanpa jalur kedua.** IKP diturunkan dengan
menautkannya ke **indikator simpul** di setiap jenjang: Kepala OPD (Es II, pemilik IKP) → Eselon III → Eselon IV /
Ketua Tim → pelaksana. Pemilik simpul (menu Pemilik Kinerja) otomatis menjadi pemikul IKP turunan itu dan
menariknya di eKin (Tarik dari SAKIP) menjadi RHK. Bila satu simpul pelaksana dimiliki beberapa orang, eKin membagi
porsi per orang lewat Cascading "Bagi ke bawahan" yang sudah ada.

| Peran | Target baris | Aturan pemeriksa per jenjang |
|---|---|---|
| **Pemikul angka** (`ikp_peran = angka`) — hitungan | **porsi** IKP | Σ porsi anak = porsi/target induk → *terbagi habis* / *kurang* (sisa belum diturunkan) / *lebih* |
| **Pemikul angka** — posisi/rilis | **target utuh** (= target IKP, terkunci, tidak dibagi) | tepat **satu** pemikul angka per jenjang di satu cabang (> 1 = peringatan; 0 di bawah Kabid dengan pendukung = netral "angka tetap dipikul jenjang ini"; 0 di akar = peringatan) |
| **Pendukung** (`ikp_peran = pendukung`) | target **indikator proses** miliknya sendiri (pola hitungan) | tidak menambah angka IKP; pemikul angka DI BAWAH pendukung = "rantai angka terputus" |

Contoh IKIP (rilis Des, target 99): Kabid IKP = pemikul angka (indikator "Indeks Keterbukaan Informasi Publik", 99,
hanya Desember); Pranata Humas (Es IV/JF) pendukung "Jumlah laporan layanan informasi publik PPID yang disusun";
PPID pelaksana pendukung **"Jumlah bukti dukung SAQ Keterbukaan Informasi yang dilengkapi"** (40, dicicil 12 bulan) —
di sinilah kerja sepanjang tahun diukur tanpa pernah mencicil indeks. Pemeriksa hanya **memperingatkan**; penyimpanan
tidak diblokir (porsi yang sengaja *kurang* — mis. Dikdas 1.000 siswa berprestasi, baru SMPN 1 yang ada di pohon — sah).

**Skema** (migrasi `2026-09-28-000002_AddIkpTurunToCascadingTarget`, kembaran `db/update_2026-09-28_ikp_turun.sql`,
idempoten, tanpa mengubah angka): `cascading_indikator_target` + `ikp_peran` (angka|pendukung, bawaan angka),
`ikp_induk_id` (baris induk IKP yang sama; NULL = langsung dari IKP/Kepala OPD; FK ke tabel yang sama, `SET NULL`),
`dibuat_oleh` (users.id; NULL = migrasi/simulasi), `sumber` (`delegasi` | `lama`), `sebelum_delegasi` (JSON keadaan
indikator sebelum diambil alih). Satu baris = "IKP X diturunkan ke indikator simpul Y, tahun T, porsi Z, peran P".
Delapan tautan lama diberi `sumber = lama`, peran angka. `ikp_induk_id` dirawat `IkpTurunService::rapikanInduk()`
dari struktur pohon: induk = leluhur **terdekat** yang memikul IKP yang sama (jenjang yang tidak ikut dilompati).

**Layar** (Kinerja Prioritas → tab **Turunkan IKP**, `adminopd/ikp/turun[/{id}]`; Admin Kabupaten/Inspektorat
membaca lewat tab **Turun ke Pelaksana**, `adminkab/ikp/turun`):
- *Daftar*: kartu per IKP — pola, target, lencana **"Turun sampai: Eselon III ✓ · Eselon IV / JF ✓ · Pelaksana ✗"**
  (label jenjang mengikuti jenis unit: di kecamatan Camat = Eselon III, jadi es4 sudah "Pelaksana / JF" dan dihitung
  sampai pelaksana), ringkasan pemeriksa, dan tiga angka (IKP, sudah diturunkan, sampai pelaksana).
- *Pohon mini satu IKP*: Kepala OPD (pemilik IKP, dari PK JPT/Camat) di puncak, lalu simpul aktif (aturan tampil sama
  dengan Pemilik Kinerja & API eKin) dengan pemiliknya. Setiap simpul: centang **Ikut memikul IKP ini**, peran,
  indikator (pilih yang ada — indikator yang sudah memikul IKP lain tidak bisa dipilih — atau **buat indikator baru dari
  rumusan IKP**), porsi (hitungan, dengan % terhadap atasan), target utuh terkunci (posisi/rilis), atau indikator proses
  (pendukung). Kotak **pemeriksa per jenjang** di bawah setiap induk dihitung server dan diperbarui seketika oleh
  `ikp-turun.js` (cerminan `IkpTurunService::periksa`). Cabang yang memikul tampil lebih dulu; cabang lain terlipat.
- **Usulkan dari pohon** (`?usul=1`, belum tersimpan sampai ditekan Simpan): simpul yang teks sasaran/indikatornya
  mirip IKP (kata bermakna, ≥ 60 %) atau yang sudah bertaut IKP ini. Hitungan: semua daun yang cocok memikul angka,
  porsi proporsional target indikator simpul (satuan sepadan) atau rata, leluhur = Σ porsi turunannya. Posisi: satu
  rantai angka ke simpul paling cocok (seri → paling dalam). Rilis: satu rantai angka ke simpul paling cocok (seri →
  paling **dangkal**: nilai resmi dipegang pejabat) + maks. 2 cabang pendukung dengan indikator proses bawaan
  (`IkpTurunService::teksProses`). Pendelegasian yang sudah tersimpan tidak diubah usulan.
- *Mencabut* (hilangkan centang lalu Simpan) memulihkan indikator: indikator yang dibuat pendelegasian dihapus bila
  tidak dipakai (tanpa simpul anak berjangkar padanya dan tanpa target tahun lain), indikator lama mendapat kembali
  target/metode sebelum diambil alih (`sebelum_delegasi`), tautan lama kehilangan IKP-nya tetapi targetnya tetap.
- Chip **★ IKP** (pemikul angka; ×n bila beberapa IKP) / **☆ Mendukung IKP** pada simpul di bagan **Pohon Kinerja**
  dan kartu **Pemilik Kinerja**; tag indikator menyebut perannya. Modal indikator Pemilik Kinerja **mengunci** target,
  metode, dan tautan IKP untuk baris `delegasi` (hanya satuan yang boleh diubah — porsi diatur di Turunkan IKP);
  tautan langsung yang dibuat dari modal itu tercatat `sumber = lama`.
- *Rekap Kabupaten* (`adminkab/ikp`): kolom **Turun sampai pelaksana** (n/m IKP per OPD, bertaut ke halaman baca) dan
  jumlah total di kartu IKP Terdaftar (`IkpTurunService::rekapKabupaten`).

**Masuk ke RHK (API ke eKin).** `api/ekin/pegawai/{id}/kinerja`: alasan baru **`delegasi`** (paling spesifik:
`delegasi → pj → simpul → kepala_opd`) dengan `peran` dan `delegasi[]`: `delegasi_id`, simpul & indikator, `peran`,
`porsi_target_tahunan` (+ `porsi_persen`), **profil bulanan** menurut pola (hitungan = cicilan IKP × porsi/target
dengan pembulatan kumulatif, Σ = porsi; posisi/rilis = target IKP **hanya di bulan ukur**; pendukung = proses dicicil
12 bulan), `pola_indikator`, `rantai_induk` sampai Kepala OPD (dasar "RHK pimpinan yang diintervensi" = RHK IKP milik
pemilik simpul induk). Indikator simpul membawa `ikp_peran`, `ikp_sumber`, `ikp_delegasi_id`; **indikator proses
pendukung dikirim dengan `ikp_id = null`** (IKP-nya di `ikp_didukung_id`) supaya konsumen lama tidak mengiranya
indikator IKP. Alasan `pj`/`simpul`/`kepala_opd` dan tautan `lama` tidak berubah; Kepala OPD tetap `kepala_opd`.
Kontrak lengkap: `API_DOCUMENTATION.md` §10 & `public/openapi.json` (`EkinDelegasiIkp`). Yang dilakukan eKin
(TarikSakip, keputusan spesifikasi): `angka` → RHK `sumber_tipe='ikp'` berlencana IKP dengan IKI kuantitas bertarget
porsi + pola ukur + RA sesuai profil; `pendukung` → RHK bertanda pendukung ("Mendukung IKP") dengan IKI proses; SKP
draf → RHK langsung masuk; SKP sudah diajukan/disetujui → penugasan "IKP turunan" yang harus diterima pegawai.

**Realisasi naik — saran "Dari eKin (pemikul angka)".** Halaman realisasi IKP membaca kontrak eKin
`GET api/aksara/opd/{id}/ikp-turunan?tahun=` (`EkinClient::ikpTurunan`):

```json
{ "opd_id": 20, "tahun": 2026, "diperbarui": "…",
  "baris": [ { "delegasi_id": 369, "ikp_id": 323, "pegawai_id": 307,
               "bulan": { "1": { "realisasi": 31.5, "pada": "2026-01-25" }, "…": "…" } } ] }
```

`delegasi_id` = `cascading_indikator_target.id` AKSARA (dikirim eKin dari RHK/IKI hasil tarikan baris itu),
`realisasi` = realisasi bulan itu yang sudah disetujui di eKin, per pegawai. AKSARA sendiri yang merangkum
(`IkpTurunService::saranRealisasi`): hitungan = Σ realisasi pemikul angka **terbawah** di setiap cabang (atasan dan
pendukung tidak ikut dijumlah — satu hasil dihitung satu kali oleh pemiliknya); posisi/rilis = nilai pemikul angka
jenjang **terdekat** ke IKP (Eselon III dulu; belum melapor → turun satu jenjang). Sel bulan ukur yang terbuka
menampilkan "eKin *n* **Gunakan**" (\* = belum semua pemikul melapor); *Gunakan* hanya mengisi kotak (rilis: membuka
dialog "Catat nilai resmi" berisi nilainya — bukti publikasi tetap wajib). **Realisasi resmi IKP tetap diisi/disahkan
Admin OPD**, tidak pernah ditimpa otomatis. eKin yang belum menyediakan endpoint ini (404 → `belum_tersedia`), mati,
atau belum dikonfigurasi = tidak ada saran, halaman tetap berjalan.

**Data simulasi** (`/root/demo-kinerja/simulasi/03b_ikp_turun.php`, di luar repo; `AKSARA_DB`, bawaan `aksara_demo`;
idempoten, `--kering` = ROLLBACK; menulis peta id `03b_ikp_turun[.<db>].json` tanpa nama/NIP). Meniru
`IkpTurunService::simpan`: 24 IKP diturunkan (88 baris, 61 indikator simpul baru, 27 indikator lama diambil alih
dengan `sebelum_delegasi`) memakai pohon & pemilik yang ada — **Diskominfo** (IKIP/IPS/KAMI: Kabid pemikul angka +
pendukung proses sampai pelaksana; empat IKP posisi SPBE rantai angka Kabid → Es IV → pelaksana; konten 150 = 100 +
50 dua cabang), **Kecamatan Pringsewu + Kelurahan Pringsewu Barat** (aman-tertib 15 = 14 kecamatan + 1 kelurahan
sampai petugas keamanan kelurahan sebagai pendukung; usulan kelurahan 5 = 4 + 1), **Dinkes/UPTD Puskesmas
Pringsewu** (CKG 60.000 = 52.800 Bidang P2P + 7.200 Puskesmas sampai pelaksana CKG; hipertensi & DM dengan porsi
Puskesmas 1.955 / 475; pendukung rekam medis), **Disdikbud/UPT SMPN 1** (siswa berprestasi porsi 30 dari 1.000 —
sengaja "kurang 970"; diklat guru 600 = 588 Bidang GTK + 12 SMPN 1), **DLH** (bank sampah nasabah aktif posisi +
pendukung lintas bidang penilaian bank sampah; berat sampah, adiwiyata, B3, pengaduan). Sengaja belum diturunkan:
381, 383, 239, DLH 459–470, IKP Dinkes/Disdikbud lain (rekap memperlihatkan "belum"). Dijalankan HANYA pada
`aksara_uji`; lari kedua 0 perubahan. Tahap ini tidak menyentuh realisasi, penilaian, maupun pemilik simpul.

**Uji.** Unit `tests/unit/IkpTurunTest.php` (26 kasus: pemeriksa porsi & satu pemikul angka, rantai terputus,
cakupan termasuk kecamatan, pembulatan kumulatif, profil bulanan per pola/peran, porsi sisa terbesar, kemiripan,
susun induk, usulan rilis/hitungan/tautan lama, saran realisasi hitungan & rilis). Peramban
`uji/malam/cek_ikp_turun.mjs` (repo demo; 111 pemeriksaan pada 1366 & 390 px): daftar & lencana, IKIP rilis
(target utuh terkunci, pendukung PPID), peringatan "2 pemikul angka" seketika pada posisi, usulan → simpan → cabut
IKP 381 dengan sidik jari tabel kembali identik, penolakan porsi bukan angka, lingkup OPD, chip ★ IKP di Pohon &
Pemilik Kinerja, porsi CKG & "Kurang 200" seketika, "Kurang 970" Dikdas, rekap & halaman baca kabupaten, API
`delegasi` (IKIP hanya Desember 99; PPID pendukung 12 bulan & `ikp_id` null; CKG Puskesmas 7.200 = 12 %, 600/bulan;
Kepala tetap `kepala_opd`), saran eKin + *Gunakan* lewat tiruan eKin (`uji/malam/mock_ekin_turunan.php`) dan tanpa
saran bila eKin belum menyediakan. Tanpa gulir samping, tanpa elemen melayang.

### Terbuka untuk dibahas

1. **Siapa yang menurunkan.** Kini Admin OPD atas nama Kepala OPD untuk seluruh pohon. Apakah Kabid boleh menurunkan
   sendiri IKP di cabangnya (akun per pejabat belum ada di AKSARA)?
2. **Realisasi IKP otomatis dari eKin** untuk pola hitungan? Kini hanya saran + *Gunakan*; Admin OPD tetap pengesah.
3. **Kontrak `api/aksara/opd/{id}/ikp-turunan` belum disediakan eKin** — AKSARA siap membacanya (diuji dengan tiruan).
   eKin perlu menyimpan `delegasi_id` pada RHK/IKI hasil tarikan baris `delegasi` agar realisasinya bisa dikirim balik.
4. **IKI proses bawaan untuk pendukung** kini teks bawaan per pola ("Jumlah bukti dukung penilaian … yang dilengkapi",
   "Jumlah rekap data …", "Jumlah kegiatan pendukung …") yang diketik ulang per simpul. Perlu katalog per jenis indeks
   (SAQ KIP, kuesioner SPBE, bukti Indeks KAMI, LKE SAKIP, …)?
5. **Profil bulanan pendukung** = target proses dicicil rata 12 bulan (pembulatan kumulatif). Kerja nyata sering
   menumpuk menjelang penilaian (mis. SAQ Mei–Agustus) — perlu bulan kerja pilihan?
6. **Definisi "sampai pelaksana"** = ada baris (angka ATAU pendukung) di jenjang pelaksana, walau jenjang tengah
   dilompati; di kecamatan jenjang es4 ("Pelaksana / JF") sudah dihitung. Apakah untuk hitungan harus ada PEMIKUL
   ANGKA sampai pelaksana?
7. **Satu IKP per indikator per tahun** (kunci unik lama `cascading_indikator_id + tahun`). Karena itu satu simpul yang
   memikul beberapa IKP mendapat beberapa indikator ("buat dari rumusan IKP"), dan indikator yang sudah memikul IKP lain
   tidak bisa dipilih. Alternatif: tabel penghubung IKP ↔ indikator (lebih luwes, migrasi lebih besar).
8. **Mengambil alih indikator yang sudah ada** mengganti targetnya dengan porsi/target utuh IKP (mis. indikator Kabid
   IKIP 97,5 → 99). Keadaan lama disimpan di `sebelum_delegasi` dan dipulihkan saat dicabut. Bila target Renstra simpul
   dan porsi IKP berbeda, mana yang berlaku? Kini porsi IKP.
9. **Pemeriksa tidak memblokir** (kurang/lebih/ganda boleh disimpan dengan peringatan). Perlu mode ketat sebelum PK
   ditandatangani?
10. **Porsi bawaan usulan** proporsional target indikator simpul menghasilkan angka ganjil (CKG 53.571 / 6.429);
    Admin OPD menyesuaikan. Porsi simulasi Dinkes (Puskesmas Pringsewu 12 % kabupaten) adalah asumsi.
11. **Pendukung lintas bidang** (DLH: penilaian kinerja bank sampah di Bidang Penataan mendukung IKP nasabah aktif
    Bidang Sampah) diperbolehkan; induknya langsung IKP (tidak ada leluhur yang memikul).
12. **Pendelegasian per tahun**: tahun berikutnya perlu diturunkan ulang — belum ada "salin dari tahun lalu".
13. **Modal Pemilik Kinerja** masih bisa membuat tautan langsung IKP (`sumber = lama`, porsi = target yang diketik).
    Sebaiknya dihapus dan diarahkan ke Turunkan IKP? Kini dibiarkan agar alur lama tidak putus; baris `delegasi` dikunci.
14. **Perubahan kecil kontrak API**: indikator proses pendukung dikirim `ikp_id = null` (+ `ikp_didukung_id`); eKin
    lama tidak lagi menandainya "tercakup IKP". Butir `ikp[]` beralasan `delegasi` tetap membawa angka IKP tingkat OPD
    di kunci lama — konsumen harus memakai `delegasi[]` untuk angka orang itu.
15. **Saran rilis** baru muncul saat bulan rilis terbuka (sama dengan kisi realisasi); nilai yang dilaporkan pemikul
    angka Es III didahulukan walau jenjang bawah melapor lebih baru.
16. **Simpul tersembunyi**: pendelegasian di simpul yang IKU induknya kemudian dihentikan tetap tersimpan, dicatat
    di halaman, dan tidak dikirim ke eKin (aturan sama dengan pemilik simpul).
17. **aksara_demo belum dibangun ulang**: `03b_ikp_turun.php` hanya dijalankan pada `aksara_uji` (aturan malam ini) dan
    belum masuk `jalankan.sh` (butuh migrasi cabang ini di `/root/e-sakip` lebih dulu).

## Keputusan desain penting

- IKP melekat ke **OPD × periode RPJMD** (bukan per dokumen PK). Hapus IKP = *soft delete* (`dihapus_pada`) karena aplikasi
  lain merujuk `ikp.id`.
- `ikp_bulanan` **tidak** ber-FK ke `iku_target`/tahunan: `IkuModel::updateIndikator()` menghapus-lalu-menyisipkan `iku_target`,
  sehingga FK CASCADE akan menghapus data bulanan diam-diam.
- Metode perhitungan memakai kosakata monev: `sum | trend_naik | trend_turun | trend_flat`.
- Kategori IKP selalu lewat `?kategori=` karena kata `tambah` di path dibaca modperm sebagai aksi tulis.
- Scope OPD dari sesi untuk admin OPD; admin kabupaten memakai `?opd_id=` yang divalidasi (bukan `canAccessOpd()`, karena akun
  admin_kab punya `opd_id`).
- Pemilik Es II tidak disimpan ulang; mengikuti PK JPT/Camat `pihak_1`.

## Temuan di kode/data lama (belum diubah)

- `MasukSebagaiService::kepalaPerOpd()` memilih PK JPT **terbaru** sebagai kepala; untuk Sekretariat Daerah (6 PK JPT: Sekda,
  Asisten, Staf Ahli) hasilnya bisa Staf Ahli. Ruang OPD mendahulukan jabatan puncak (`RuangOpdService::kepalaPerOpd`).
- `PkRenaksiController::ensureRole()` menolak Super Admin untuk Rencana Aksi/MONEV (Ruang OPD karena itu tidak memberi
  tautan ke sana untuk Super Admin). Sebagian halaman `adminopd/*` untuk Super Admin tanpa `opd_id` di sesi berakhir di
  /login; `adminopd/cascading?periode=` dulu HTTP 500 (`CascadingModel::programPkByEs3(int)` menerima null) — kini
  diperbaiki (`(int)`), halaman menampilkan "Akun Tidak Terikat Perangkat Daerah".

- `tests/unit/CapaianTotalTest.php`: 2 kasus sudah gagal di `63a8275` (kebijakan `not_evaluable → 0%` 16 Sep belum diselaraskan).
- `AdminOpd\PkController::cetak()`, `edit()` dan `index(?pk_id=)` dulu memuat PK hanya dari id, sehingga admin OPD bisa
  mencetak PK OPD lain (nama & NIP pejabatnya). **Diperbaiki (AKSARA+)**: `pkDiLuarLingkup()` menolak PK OPD lain untuk
  `admin_opd`/`admin_kecamatan`; jalur `/adminkab` tetap lintas OPD (sesi admin_kab membawa `opd_id` unit Kabupaten, jadi
  `canAccessOpd()` apa adanya akan memutus cetak lintas OPD mereka). Perlu diteruskan ke tim upstream.
- `PegawaiSyncService::syncJabatan()` membaca `nama_eselon`, padahal feed mengirim `eselon_id`, sehingga `jabatan.eselon` kosong
  99,6%. `pegawai.atasan_id` basi dan tidak dipakai. 42 pegawai BKPSDM tercatat di `opd_id = 210` yang tidak ada.
- mPDF memerlukan folder tmp yang bisa ditulis PHP (`adminkab/target/cetak` 500 bila tidak).
- **`server.sql` di repo publik memuat data produksi** (NIP dan hash kata sandi pegawai). Sebaiknya dihapus dari repo beserta
  riwayat git-nya.
