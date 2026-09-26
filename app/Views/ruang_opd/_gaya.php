<?php
/*
 * Gaya Ruang OPD (AKSARA+). Disisipkan ke <style> templates/shell_atas lewat $shellCss,
 * jadi berkas ini TANPA tag <style>. Semua kelas berawalan .ro- agar tidak bertabrakan
 * dengan gaya global (style.php memberi SEMUA tabel min-width 500px di ponsel — tabel
 * di sini menimpanya sendiri).
 */
?>
.ro { --ro-hijau:#0a8f50; --ro-hijau-tua:#00743e; --ro-kuning:#c98a00; --ro-merah:#c93c3c; --ro-abu:#8a968f;
      --ro-garis:#e3e9e5; --ro-teks:#1d3326; --ro-redup:#667a6e; --ro-latar:#f6f9f7; }
.ro a { text-decoration: none; }
.ro .ro-min0 { min-width: 0; }

/* ---------- Kepala halaman ---------- */
.ro-hero { background: linear-gradient(120deg, #00803f 0%, #00642f 100%); color: #fff; border-radius: 18px; padding: 20px 24px;
    display: flex; gap: 18px; align-items: center; flex-wrap: wrap; position: relative; overflow: hidden; box-shadow: 0 14px 34px rgba(0,116,62,.18); }
.ro-hero::after { content: ''; position: absolute; right: -40px; top: -50px; width: 190px; height: 190px; border-radius: 50%; background: rgba(255,255,255,.07); }
.ro-hero .ic { flex: 0 0 auto; width: 54px; height: 54px; border-radius: 16px; background: rgba(255,255,255,.16); display: grid; place-items: center; font-size: 23px; }
.ro-hero h2 { margin: 0 0 3px; font-weight: 800; font-size: clamp(1.05rem, 2.4vw, 1.4rem); line-height: 1.25; }
.ro-hero p { margin: 0; opacity: .9; font-size: .86rem; }
.ro-hero .isi { flex: 1 1 320px; min-width: 0; position: relative; z-index: 1; }
.ro-hero .kanan { position: relative; z-index: 1; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.ro-remah { font-size: .78rem; opacity: .9; margin-bottom: 4px; }
.ro-remah a { color: #d9f2b3; font-weight: 600; }
.ro-remah a:hover { color: #fff; text-decoration: underline; }
.ro-lencana { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 700; background: rgba(255,255,255,.18);
    padding: .3em .7em; border-radius: 999px; letter-spacing: .2px; }
.ro-tahun { display: inline-flex; gap: 4px; background: rgba(255,255,255,.14); padding: 4px; border-radius: 12px; flex-wrap: wrap; }
.ro-tahun a { color: #fff; font-weight: 700; font-size: .8rem; padding: .3rem .6rem; border-radius: 9px; }
.ro-tahun a:hover { background: rgba(255,255,255,.18); }
.ro-tahun a.aktif { background: #fff; color: #00642f; }
.ro-tahun.terang { background: #eef4f0; }
.ro-tahun.terang a { color: #2f4a3a; }
.ro-tahun.terang a.aktif { background: #00743e; color: #fff; }

/* Cincin skor */
.ro-cincin { --p: 0; --w: #fff; width: 64px; height: 64px; border-radius: 50%; flex: 0 0 auto;
    background: conic-gradient(var(--w) calc(var(--p) * 1%), rgba(255,255,255,.22) 0); display: grid; place-items: center; }
.ro-cincin > span { width: 50px; height: 50px; border-radius: 50%; background: #00703a; display: grid; place-items: center; font-weight: 800; font-size: .95rem; line-height: 1; text-align: center; }
.ro-cincin small { display: block; font-size: .55rem; font-weight: 600; opacity: .85; margin-top: 2px; }

/* ---------- Bilah alat ---------- */
.ro-alat { background: #fff; border: 1px solid var(--ro-garis); border-radius: 14px; padding: 12px 14px; box-shadow: 0 6px 18px rgba(16,40,24,.05);
    display: flex; gap: 10px 14px; align-items: center; flex-wrap: wrap; }
.ro-cari { position: relative; flex: 1 1 260px; min-width: 0; }
.ro-cari i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #8aa295; }
.ro-cari input { padding-left: 34px; border-radius: 10px; }
.ro-pil { display: inline-flex; gap: 6px; flex-wrap: wrap; }
.ro-pil button, .ro-pil a { border: 1px solid #dbe5de; background: #fff; color: #33483b; border-radius: 999px; font-size: .78rem; font-weight: 700;
    padding: .32rem .75rem; cursor: pointer; white-space: nowrap; }
.ro-pil button:hover, .ro-pil a:hover { border-color: #a9cf8c; color: #00743e; }
.ro-pil .aktif { background: #00743e; border-color: #00743e; color: #fff; }
.ro-pil .aktif:hover { color: #fff; }
.ro-pil .n { opacity: .7; font-weight: 600; margin-left: 3px; }

/* ---------- Status ---------- */
.s-hijau { --c: var(--ro-hijau); --bg: #e9f6ee; }
.s-kuning { --c: var(--ro-kuning); --bg: #fdf5e1; }
.s-merah { --c: var(--ro-merah); --bg: #fcebeb; }
.s-abu { --c: var(--ro-abu); --bg: #f2f4f3; }
.s-biru { --c: #1d5f8f; --bg: #e2effa; } /* "PK di AKSARA": dokumen lain, bukan status tanda tangan */
.ro-titik { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--c); flex: 0 0 auto; }
.ro-chip { display: inline-flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 700; color: var(--c); background: var(--bg);
    padding: .28em .65em; border-radius: 999px; white-space: nowrap; }
.ro-chip.polos { color: #45594c; background: #f0f3f1; }

/* ---------- Ringkasan kabupaten per kolom ---------- */
.ro-ringkas { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.ro-ringkas button { text-align: left; background: #fff; border: 1px solid var(--ro-garis); border-radius: 12px; padding: 10px 12px; cursor: pointer;
    transition: border-color .15s, box-shadow .15s; min-width: 0; }
.ro-ringkas button:hover { border-color: #b6d6a0; box-shadow: 0 6px 16px rgba(16,40,24,.07); }
.ro-ringkas button.aktif { border-color: #00743e; box-shadow: 0 0 0 2px rgba(0,116,62,.18); }
.ro-ringkas .lbl { font-size: .72rem; font-weight: 700; color: var(--ro-redup); text-transform: uppercase; letter-spacing: .3px; display: flex; gap: 6px; align-items: center; }
.ro-ringkas .lbl i { color: #6eab11; }
.ro-ringkas .ang { font-size: 1.15rem; font-weight: 800; color: var(--ro-teks); margin: 2px 0 6px; }
.ro-ringkas .ang small { font-size: .72rem; font-weight: 600; color: var(--ro-redup); }
.ro-batang { display: flex; height: 7px; border-radius: 6px; overflow: hidden; background: #eef2ef; }
.ro-batang span { display: block; height: 100%; }
.ro-batang .h { background: var(--ro-hijau); } .ro-batang .k { background: #e5b12c; } .ro-batang .m { background: #de6b6b; } .ro-batang .a { background: #cfd8d2; }

/* ---------- Matriks ---------- */
.ro-bungkus { overflow-x: auto; border: 1px solid var(--ro-garis); border-radius: 14px; background: #fff; }
table.ro-matriks { width: 100%; min-width: 1180px !important; border-collapse: separate; border-spacing: 0; font-size: .8rem; margin: 0; }
.ro-matriks th, .ro-matriks td { border-bottom: 1px solid #edf1ee; padding: 6px 6px; vertical-align: middle; background: #fff; }
.ro-matriks thead th { position: sticky; top: 0; z-index: 2; background: #f4f8f5; color: #3e5446; font-size: .68rem; text-transform: uppercase;
    letter-spacing: .3px; font-weight: 800; text-align: center; white-space: nowrap; }
.ro-matriks thead tr.tahap th { font-size: .66rem; color: #fff; background: #0b7a44; border-bottom: 0; }
.ro-matriks thead tr.tahap th.t-pengukuran { background: #2f6c93; }
.ro-matriks thead tr.tahap th.t-pelaporan { background: #8a5a1c; }
.ro-matriks thead tr.tahap th.t-pegawai { background: #5b4a8a; }
.ro-matriks thead tr.kolom th { top: 27px; }
.ro-matriks thead th i { color: #6eab11; margin-right: 3px; }
.ro-matriks .kol-opd { position: sticky; left: 0; z-index: 1; min-width: 240px; max-width: 280px; text-align: left; box-shadow: 1px 0 0 #e6ece8; }
.ro-matriks thead .kol-opd { z-index: 3; }
.ro-matriks tbody tr:hover td { background: #f8fbf9; }
.ro-matriks tbody tr:hover td.kol-opd { background: #f3f9f5; }
.ro-matriks tr.ro-grup td { background: #f7faf8 !important; font-weight: 800; font-size: .74rem; color: #2f4a3a; text-transform: uppercase; letter-spacing: .4px; padding: 8px 12px; }
.ro-opd-nama { display: block; font-weight: 700; color: #15311f; font-size: .84rem; line-height: 1.25; }
.ro-opd-nama:hover { color: #00743e; text-decoration: underline; }
.ro-opd-kepala { display: block; font-size: .7rem; color: var(--ro-redup); line-height: 1.3; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ro-skor { display: inline-flex; align-items: center; justify-content: center; min-width: 38px; font-size: .7rem; font-weight: 800; border-radius: 8px; padding: .2em .45em; color: var(--c); background: var(--bg); }
.ro-opd-baris { display: flex; gap: 8px; align-items: flex-start; }
.ro-opd-baris .ro-min0 { flex: 1 1 auto; }
a.ro-sel { display: flex; flex-direction: column; gap: 1px; min-width: 92px; padding: 5px 7px; border-radius: 9px; background: var(--bg); color: #213a2b;
    border: 1px solid transparent; transition: border-color .12s, transform .12s; line-height: 1.2; }
a.ro-sel:hover { border-color: var(--c); transform: translateY(-1px); }
a.ro-sel b { display: flex; align-items: center; gap: 5px; font-size: .78rem; font-weight: 800; color: #1b3325; white-space: nowrap; }
a.ro-sel small { font-size: .66rem; color: #5d7064; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
.ro-tw { display: inline-flex; gap: 2px; margin-top: 2px; }
.ro-tw span { width: 12px; height: 5px; border-radius: 2px; background: #dfe6e1; position: relative; overflow: hidden; }
.ro-tw span i { position: absolute; left: 0; top: 0; bottom: 0; background: var(--c); }
.ro-kosong-cari { display: none; padding: 26px; text-align: center; color: var(--ro-redup); }
.ro-legenda { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: .74rem; color: var(--ro-redup); align-items: center; }
.ro-legenda span { display: inline-flex; align-items: center; gap: 5px; }

@media (max-width: 767.98px) {
    .ro-bungkus { border: 0; background: transparent; overflow: visible; }
    table.ro-matriks, .ro-matriks tbody, .ro-matriks tr, .ro-matriks td { display: block; width: 100%; min-width: 0 !important; }
    .ro-matriks thead { display: none; }
    .ro-matriks tr.ro-baris { border: 1px solid var(--ro-garis); border-radius: 14px; margin-bottom: 10px; background: #fff; padding: 10px 10px 4px;
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
    .ro-matriks tr.ro-baris td { border: 0; padding: 0; background: transparent !important; }
    .ro-matriks tr.ro-baris td.kol-opd { grid-column: 1 / -1; position: static; max-width: none; box-shadow: none; padding-bottom: 4px; }
    .ro-matriks tr.ro-baris td[data-label]::before { content: attr(data-label); display: block; font-size: .62rem; font-weight: 800; color: #6b7a70;
        text-transform: uppercase; letter-spacing: .3px; margin: 0 0 2px 2px; }
    .ro-matriks tr.ro-grup { margin: 14px 0 8px; }
    .ro-matriks tr.ro-grup td { background: transparent !important; padding: 0 2px; border: 0; }
    a.ro-sel { min-width: 0; }
    a.ro-sel small { max-width: none; }
    .ro-opd-kepala { white-space: normal; }
}

/* ---------- Hub ---------- */
.ro-lompat { position: sticky; top: 0; z-index: 5; display: flex; gap: 6px; overflow-x: auto; background: rgba(255,255,255,.96);
    backdrop-filter: blur(4px); padding: 8px 2px; margin: 0 0 14px; border-bottom: 1px solid #eef2ef; scrollbar-width: thin; }
.ro-lompat a { flex: 0 0 auto; display: inline-flex; gap: 7px; align-items: center; padding: .42rem .8rem; border-radius: 10px; border: 1px solid #e1e8e3;
    background: #fff; color: #37493e; font-weight: 700; font-size: .8rem; white-space: nowrap; }
.ro-lompat a:hover { border-color: #b9d69a; color: #00743e; }
.ro-lompat a .ro-titik { width: 7px; height: 7px; }

.ro-siklus { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-bottom: 18px; }
.ro-siklus a { background: #fff; border: 1px solid var(--ro-garis); border-radius: 14px; padding: 12px 14px; color: var(--ro-teks); display: block; min-width: 0;
    box-shadow: 0 6px 16px rgba(16,40,24,.04); }
.ro-siklus a:hover { border-color: #b6d6a0; }
.ro-siklus .nm { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; color: var(--ro-redup); display: flex; gap: 6px; align-items: center; }
.ro-siklus .nm i { color: #6eab11; }
.ro-siklus .pc { font-size: 1.35rem; font-weight: 800; margin: 2px 0 6px; }
.ro-siklus .ro-batang { height: 6px; }
@media (max-width: 991.98px) { .ro-siklus { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 575.98px) { .ro-siklus { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.ro-tahap { margin-bottom: 26px; scroll-margin-top: 70px; }
.ro-tahap-kepala { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.ro-tahap-kepala .ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #0a8f50, #00743e); flex: 0 0 auto; }
.ro-tahap-kepala.t-pengukuran .ic { background: linear-gradient(135deg, #3f82b0, #2f6c93); }
.ro-tahap-kepala.t-pelaporan .ic { background: linear-gradient(135deg, #b07a2c, #8a5a1c); }
.ro-tahap-kepala.t-evaluasi .ic { background: linear-gradient(135deg, #7b8a80, #5d6b62); }
.ro-tahap-kepala.t-pegawai .ic { background: linear-gradient(135deg, #7a67b0, #5b4a8a); }
.ro-tahap-kepala h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #16321f; }
.ro-tahap-kepala p { margin: 0; font-size: .78rem; color: var(--ro-redup); }
.ro-kisi { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 991.98px) { .ro-kisi { grid-template-columns: minmax(0, 1fr); } }

.ro-dok { background: #fff; border: 1px solid var(--ro-garis); border-radius: 14px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px;
    min-width: 0; scroll-margin-top: 70px; box-shadow: 0 6px 16px rgba(16,40,24,.04); }
.ro-dok.lebar { grid-column: 1 / -1; }
.ro-dok:target { border-color: #00743e; box-shadow: 0 0 0 3px rgba(0,116,62,.15); }
.ro-dok-kepala { display: flex; align-items: flex-start; gap: 10px; }
.ro-dok-kepala .ic { width: 34px; height: 34px; border-radius: 10px; background: #eef6f0; color: #00743e; display: grid; place-items: center; flex: 0 0 auto; }
.ro-dok-kepala h4 { margin: 0; font-size: .95rem; font-weight: 800; color: #17301f; }
.ro-dok-kepala p { margin: 1px 0 0; font-size: .74rem; color: var(--ro-redup); }
.ro-dok-kepala .kanan { margin-left: auto; flex: 0 0 auto; }
.ro-angka { display: flex; flex-wrap: wrap; gap: 8px; }
.ro-angka > div { background: var(--ro-latar); border: 1px solid #edf2ee; border-radius: 10px; padding: 6px 10px; min-width: 88px; }
.ro-angka b { display: block; font-size: 1.05rem; font-weight: 800; color: #17301f; line-height: 1.15; }
.ro-angka span { font-size: .68rem; color: var(--ro-redup); font-weight: 600; }
.ro-aksi { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; }
.ro-aksi .btn { font-size: .78rem; font-weight: 700; border-radius: 9px; padding: .34rem .7rem; }
.ro-catatan { font-size: .76rem; color: var(--ro-redup); margin: 0; }
.ro-catatan i { color: #9aaa9f; }

table.ro-mini { width: 100%; min-width: 0 !important; font-size: .78rem; border-collapse: collapse; margin: 0; }
.ro-mini th { font-size: .66rem; text-transform: uppercase; letter-spacing: .3px; color: #5d7064; font-weight: 800; border-bottom: 1px solid #e5ebe7; padding: 5px 6px; text-align: left; }
.ro-mini td { border-bottom: 1px solid #f0f3f1; padding: 5px 6px; vertical-align: top; color: #2b4034; }
.ro-mini td.num, .ro-mini th.num { text-align: right; white-space: nowrap; }
.ro-mini tr:last-child td { border-bottom: 0; }
.ro-gulir { max-height: 300px; overflow: auto; border: 1px solid #eef2ef; border-radius: 10px; }
.ro-gulir table.ro-mini thead th { position: sticky; top: 0; background: #fff; }

.ro-level { display: grid; grid-template-columns: 110px minmax(0, 1fr) 90px; gap: 6px 10px; align-items: center; font-size: .78rem; }
.ro-level .nm { font-weight: 700; color: #2f4a3a; }
.ro-level .ro-batang { height: 8px; }
.ro-level .nl { text-align: right; color: var(--ro-redup); font-weight: 600; white-space: nowrap; }

.ro-tw-besar { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.ro-tw-besar > div { border: 1px solid #edf2ee; border-radius: 10px; padding: 8px 10px; background: var(--ro-latar); }
.ro-tw-besar .nm { font-size: .7rem; font-weight: 800; color: var(--ro-redup); }
.ro-tw-besar b { display: block; font-size: 1rem; }
.ro-tw-besar .ro-batang { margin-top: 4px; height: 5px; }

.ro-kosong { text-align: center; padding: 22px 16px; color: var(--ro-redup); border: 1px dashed #d7e2da; border-radius: 12px; background: #fbfcfb; }
.ro-kosong .ic { width: 48px; height: 48px; border-radius: 14px; background: #eef4f0; color: #00743e; display: grid; place-items: center; font-size: 20px; margin: 0 auto 10px; }
.ro-kosong h5 { font-size: .95rem; font-weight: 800; color: #2a4032; margin-bottom: 4px; }

.ro-pegawai { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
@media (max-width: 991.98px) { .ro-pegawai { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.ro-pegawai > div { border: 1px solid #ebe7f5; background: #faf9fd; border-radius: 12px; padding: 10px 12px; min-width: 0; }
.ro-pegawai .nm { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; color: #6a5f86; }
.ro-pegawai b { display: block; font-size: 1.25rem; font-weight: 800; color: #2d2447; }
.ro-pegawai small { display: block; font-size: .72rem; color: #6b6780; line-height: 1.35; }
.ro-predikat { display: flex; height: 8px; border-radius: 6px; overflow: hidden; margin-top: 6px; background: #ece9f4; }
.ro-predikat span { height: 100%; }

/* ---------- Tab halaman eKin ---------- */
.ro-tab { display: flex; gap: 6px; overflow-x: auto; margin: 14px 0 16px; padding-bottom: 2px; }
.ro-tab a { flex: 0 0 auto; padding: .5rem .9rem; border-radius: 11px; border: 1px solid #e1e8e3; background: #fff; color: #3a4a40; font-weight: 700; font-size: .84rem; white-space: nowrap; }
.ro-tab a i { color: #7a67b0; margin-right: 6px; }
.ro-tab a.aktif { background: linear-gradient(135deg, #7a67b0, #5b4a8a); color: #fff; border-color: transparent; }
.ro-tab a.aktif i { color: #e4ddf7; }

/* ---------- Pohon cascading pegawai ---------- */
.ro-pohon, .ro-pohon ul { list-style: none; margin: 0; padding: 0; }
.ro-pohon ul { margin-left: 22px; padding-left: 14px; border-left: 2px solid #e6e1f1; }
.ro-pohon li { margin: 8px 0; position: relative; }
.ro-pohon ul > li::before { content: ''; position: absolute; left: -14px; top: 22px; width: 12px; border-top: 2px solid #e6e1f1; }
.ro-rhk { border: 1px solid #e7e3f1; border-radius: 12px; background: #fff; padding: 9px 12px; display: flex; gap: 10px; align-items: flex-start; }
.ro-rhk .av { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; font-weight: 800; font-size: .8rem; color: #fff; flex: 0 0 auto; }
.ro-rhk .av.j0 { background: #00743e; } .ro-rhk .av.j1 { background: #0f766e; } .ro-rhk .av.j2 { background: #4d7c0f; }
.ro-rhk .av.j3 { background: #2f6c93; } .ro-rhk .av.j4 { background: #a16207; } .ro-rhk .av.j5 { background: #7b8a80; }
.ro-rhk .isi { flex: 1 1 auto; min-width: 0; }
.ro-rhk .org { font-size: .74rem; color: #5b5673; }
.ro-rhk .org b { color: #2d2447; font-size: .8rem; }
.ro-rhk .rum { font-size: .86rem; font-weight: 600; color: #1c2d23; margin: 2px 0 4px; line-height: 1.35; }
.ro-rhk .meta { display: flex; flex-wrap: wrap; gap: 5px 8px; align-items: center; font-size: .72rem; color: #5d7064; }
.ro-pohon details > summary { list-style: none; cursor: pointer; }
.ro-pohon details > summary::-webkit-details-marker { display: none; }
.ro-pohon details > summary .buka { font-size: .7rem; color: #6a5f86; font-weight: 700; margin-left: auto; white-space: nowrap; }
.ro-pohon details[open] > summary .buka .t { display: none; }
.ro-pohon details:not([open]) > summary .buka .s { display: none; }
.ro-pohon .sembunyi { display: none; }
.ro-fiktif { font-size: .6rem; font-weight: 800; letter-spacing: .4px; color: #8a4b00; background: #fff1d6; border-radius: 5px; padding: .1em .4em; }

/* ---------- Dokumen PK pegawai ---------- */
.ro-kertas { background: #fff; border: 1px solid #dfe5e1; border-radius: 6px; box-shadow: 0 10px 28px rgba(16,40,24,.08); padding: 36px 42px;
    font-family: 'Times New Roman', Times, serif; color: #000; font-size: 12pt; line-height: 1.5; max-width: 860px; margin: 0 auto 22px; }
.ro-kertas h3 { text-align: center; font-weight: bold; font-size: 13pt; margin: 0; text-transform: uppercase; }
.ro-kertas h4 { text-align: center; font-weight: bold; font-size: 12pt; margin: 0 0 18px; text-transform: uppercase; }
.ro-kertas p { text-align: justify; margin: 0 0 12px; }
.ro-kertas table.id { border: 0; margin: 0 0 10px; min-width: 0 !important; }
.ro-kertas table.id td { padding: 1px 8px 1px 0; vertical-align: top; border: 0; }
.ro-ttd { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; text-align: center; }
.ro-ttd .ruang { height: 70px; display: grid; place-items: center; font-family: Inter, sans-serif; font-size: .72rem; }
.ro-ttd .nama { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
.ro-kertas.lampiran { max-width: none; padding: 28px 26px; font-size: 10.5pt; }
.ro-gulir-x { overflow-x: auto; }
table.ro-lampiran { width: 100%; min-width: 980px !important; border-collapse: collapse; font-size: 9.5pt; }
.ro-lampiran th, .ro-lampiran td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
.ro-lampiran th { text-align: center; background: #f1f1f1; font-weight: bold; }
.ro-lampiran td.num { text-align: right; white-space: nowrap; }
.ro-lampiran tr.sub td { background: #fafafa; font-weight: bold; }
@media (max-width: 575.98px) { .ro-kertas { padding: 22px 16px; font-size: 11pt; } .ro-ttd { grid-template-columns: 1fr; } }
@media print {
    #main-header, #sidebar, #sidebar-overlay, .ro-noprint, .pita-tiru, .pita-simulasi, footer, #backToTop { display: none !important; }
    #main-content { margin: 0 !important; }
    main { padding: 0 !important; }
    main > div { box-shadow: none !important; padding: 0 !important; }
    .ro-kertas { border: 0; box-shadow: none; max-width: none; margin: 0; page-break-after: always; }
    .ro-gulir-x { overflow: visible; }
}

/* ---------- Ponsel: rapatkan bingkai & kepala ---------- */
@media (max-width: 575.98px) {
    main > .bg-white.p-4 { padding: 12px !important; }
    .ro-hero { padding: 16px; gap: 12px; border-radius: 14px; }
    .ro-hero .ic { display: none; }
    .ro-hero .kanan { width: 100%; justify-content: space-between; }
    .ro-cincin { width: 54px; height: 54px; } .ro-cincin > span { width: 42px; height: 42px; font-size: .82rem; }
    .ro-siklus { display: flex; overflow-x: auto; gap: 8px; padding-bottom: 4px; }
    .ro-siklus a { flex: 0 0 132px; padding: 10px 12px; }
    .ro-siklus .pc { font-size: 1.15rem; }
    .ro-ringkas { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .ro-ringkas button { padding: 8px 10px; }
    .ro-dok { padding: 12px; }
    .ro-dok-kepala { flex-wrap: wrap; }
    .ro-dok-kepala .kanan { margin-left: 44px; }
    .ro-tw-besar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ro-level { grid-template-columns: 92px minmax(0, 1fr) 76px; }
    .ro-pegawai { grid-template-columns: minmax(0, 1fr); }
}
