<?php
/**
 * AKSARA+ — cetak PK Pegawai (dari eKin), halaman mandiri tanpa bingkai aplikasi, SAMA dengan cetak eKin
 * (app/Views/pk_pegawai/cetak.php): lembar 1 A4 potret, lembar 2 A4 lanskap (`@page potret` / `@page lanskap`).
 *
 * MENGAPA mandiri: window.print() di halaman aplikasi ikut mencetak kepala Ruang OPD dan memaksa lampiran 17 kolom
 * ke kertas potret. `?cetak=1` langsung membuka dialog cetak.
 *
 * @var array  $dok
 * @var int    $tahun
 * @var array  $opd
 * @var string $kembali
 */
$cssPkd = FCPATH . 'assets/css/pk_pegawai_dokumen.css';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Perjanjian Kinerja Pegawai <?= (int) $tahun ?> · AKSARA+</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('assets/css/pk_pegawai_dokumen.css') ?>?v=<?= is_file($cssPkd) ? filemtime($cssPkd) : '1' ?>">
  <style>
    body { background: #eef2ef; margin: 0; font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; color: #1d2b23; }
    .alat-cetak { max-width: 1120px; margin: 1rem auto .5rem; padding: 0 1rem; display: flex; gap: .5rem; justify-content: flex-end; flex-wrap: wrap; }
    .alat-cetak a, .alat-cetak button { font: inherit; font-size: .9rem; padding: .45rem .9rem; border-radius: 8px; border: 1px solid #cfd9d3; background: #fff; color: #1d2b23; text-decoration: none; cursor: pointer; }
    .alat-cetak button { background: #00743e; border-color: #00743e; color: #fff; font-weight: 600; }
    .wadah-cetak { max-width: 1120px; margin: 0 auto 2rem; padding: 0 1rem; }
    @media print {
      body { background: #fff !important; }
      .alat-cetak { display: none !important; }
      .wadah-cetak { max-width: none; margin: 0; padding: 0; }
    }
  </style>
</head>
<body>
<div class="alat-cetak">
  <a href="<?= esc($kembali, 'attr') ?>">&larr; Kembali</a>
  <button type="button" onclick="window.print()">Cetak / simpan PDF</button>
</div>
<main class="wadah-cetak">
  <?= view('ruang_opd/_pk_pegawai_kertas', ['dok' => $dok, 'cetak' => true, 'tahun' => $tahun, 'opdNama' => (string) ($opd['nama_tampil'] ?? '')]) ?>
</main>
<script>
  if (new URLSearchParams(location.search).get('cetak') === '1') { window.addEventListener('load', () => window.print()); }
</script>
</body>
</html>
