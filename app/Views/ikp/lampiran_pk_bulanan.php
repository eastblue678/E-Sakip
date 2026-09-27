<?php
/**
 * LAMPIRAN V PK Eselon II — Target BULANAN tahun PK + rekap TRIWULAN (MENDATAR).
 *
 * Triwulan tidak diisi manual: dihitung dari target bulanan dengan rumus yang
 * sama dengan halaman OPD (ikp_nilai_triwulan): Hitungan -> jumlah bulan,
 * Posisi/Rilis -> nilai bulan ukur terakhir triwulan. IKP tanpa metode ->
 * triwulan "-" (tanpa metode tidak ada cara jujur merekapnya).
 *
 * Pola ukur: bulan di luar bulan ukur tercetak "—" (tidak diukur); indeks
 * resmi (pola rilis) hanya bertarget di bulan rilisnya.
 */
helper('pdf');
$ikpRows = $lampIkp ?? [];
$metodeSingkat = [
    'sum'         => 'Akumulasi',
    'trend_naik'  => 'Posisi (naik)',
    'trend_turun' => 'Posisi (turun)',
    'trend_flat'  => 'Dipertahankan',
];
$bulanPendek = [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGU', 'SEP', 'OKT', 'NOV', 'DES'];
$romawi      = [1 => 'I', 'II', 'III', 'IV'];
?>
<?= $this->include('ikp/lampiran_pk_gaya') ?>
<div class="lp-wrap">
  <p class="lp-label">Lampiran V :</p>
  <p class="lp-sub">Target Bulanan dan Triwulan Indikator Kinerja Prioritas (IKP) Tahun <?= (int) $lampTahun ?></p>
  <p class="lp-meta"><?= esc($lampNamaDok) ?> &middot; <?= esc($lampOpd['nama_opd']) ?></p>

  <table class="lp-t lp-kecil">
    <thead>
      <tr>
        <th rowspan="2" style="width: 3%;">NO.</th>
        <th rowspan="2" style="width: 18%;">OUTPUT PRIORITAS (INDIKATOR IKP)</th>
        <th rowspan="2" style="width: 7%;">SATUAN</th>
        <th rowspan="2" style="width: 6.5%;">POLA UKUR</th>
        <th rowspan="2" style="width: 5.5%;" class="lp-sorot">TARGET <?= (int) $lampTahun ?></th>
        <th colspan="12">TARGET BULANAN</th>
        <th colspan="4">TRIWULAN</th>
      </tr>
      <tr>
        <?php foreach ($bulanPendek as $b): ?><th style="width: 3.5%;"><?= $b ?></th><?php endforeach; ?>
        <?php foreach ($romawi as $r): ?><th style="width: 4.5%;"><?= $r ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if ($ikpRows === []): ?>
        <tr><td colspan="21" class="lp-kosong">Belum ada Indikator Kinerja Prioritas (IKP) aktif untuk perangkat daerah ini.</td></tr>
      <?php endif; ?>
      <?php foreach ($ikpRows as $i => $r): ?>
        <?php
        $id       = (int) $r['id'];
        $bulan    = $lampBulan[$id]['bulan'] ?? [];
        $tw       = $lampBulan[$id]['triwulan'] ?? [];
        $cellThn  = $lampTahunan[$id][(int) $lampTahun] ?? [];
        $thn      = ($cellThn['target'] ?? null) !== null
            ? ikp_fmt((float) $cellThn['target'])
            : (trim((string) ($cellThn['target_teks'] ?? '')) !== '' ? (string) $cellThn['target_teks'] : '-');
        ?>
        <tr>
          <td class="lp-c"><?= $i + 1 ?>.</td>
          <td><?= pdf_teks(ikp_rapikan_teks($r['output_prioritas'])) ?></td>
          <td class="lp-c"><?= pdf_teks($r['satuan_label'] !== '' ? $r['satuan_label'] : '-') ?></td>
          <?php $polaR = $lampBulan[$id]['pola'] ?? ikp_pola($r); ?>
          <td class="lp-c lp-mini"><?= esc($polaR['metode'] !== '' ? ikp_pola_ringkas($polaR, (int) $lampTahun) : 'Belum dipilih') ?><?= ! empty($polaR['penerbit']) ? '<br>' . esc($polaR['penerbit']) : '' ?></td>
          <td class="lp-c lp-sorot"><?= pdf_teks($thn) ?></td>
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <td class="lp-c"><?= in_array($m, $lampBulan[$id]['ukur'] ?? range(1, 12), true) ? esc(($bulan[$m] ?? null) !== null ? ikp_fmt((float) $bulan[$m]) : '-') : '—' ?></td>
          <?php endfor; ?>
          <?php for ($q = 1; $q <= 4; $q++): ?>
            <td class="lp-c lp-tw"><?= esc(($tw[$q] ?? null) !== null ? ikp_fmt((float) $tw[$q]) : '-') ?></td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="lp-legenda">
    Triwulan dihitung otomatis dari target bulanan: <b>Hitungan</b> = jumlah bulan ukur; <b>Posisi</b>/<b>Rilis</b> =
    nilai bulan ukur terakhir triwulan (tidak dijumlah). Tanda "—" = bulan yang tidak diukur menurut pola ukur (indeks resmi hanya
    bertarget di bulan rilisnya); tanda "-" = belum diisi, atau metode perhitungan belum dipilih perangkat daerah.
  </div>

  <?= $this->include('ikp/lampiran_pk_ttd') ?>
</div>
