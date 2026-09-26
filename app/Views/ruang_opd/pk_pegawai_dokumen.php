<?php
/**
 * Ruang OPD — dokumen PK satu pegawai, baca-saja (AKSARA+). Data: eKin endpoint
 * pk-pegawai/{pegawai_id}. Susunannya mengikuti cetakan PK: halaman 1 pernyataan &
 * tanda tangan, halaman 2 lampiran dengan target bulanan. Tombol Cetak memakai
 * pencetakan peramban (gaya @media print di _gaya menyembunyikan bingkai aplikasi).
 *
 * @var array|null $dok
 */
$this->setVar('aktifTab', 'pk');
$this->setVar('shellCss', $shellCss);

$statusLbl = [
    'ditandatangani' => ['s-hijau', 'Ditandatangani'],
    'lewat_aksara'   => ['s-hijau', 'PK di AKSARA'],
    'diajukan'       => ['s-kuning', 'Diajukan, menunggu tanda tangan'],
    'draf'           => ['s-abu', 'Draf'],
    'dikembalikan'   => ['s-merah', 'Dikembalikan'],
    'belum_ada_skp'  => ['s-abu', 'Belum ada SKP'],
];
$angka = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
    }
    $f = (float) $v;

    return number_format($f, floor($f) == $f ? 0 : 2, ',', '.');
};
$bulanPendek = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$metodeLbl = ['sum' => 'Akumulasi', 'akhir' => 'Posisi akhir', 'rata' => 'Rata-rata', 'trend_naik' => 'Posisi akhir (naik)', 'trend_turun' => 'Posisi akhir (turun)', 'trend_flat' => 'Dipertahankan'];
?>
<?= $this->include('ruang_opd/_kepala') ?>

<?php if ($dok === null): ?>
  <div class="ro-kosong">
    <div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
    <h5>Data eKin belum tersedia</h5>
    <p class="small mb-0"><?= esc($ekinPesan) ?></p>
  </div>
<?php else: ?>
  <?php
  $p1 = $dok['pegawai'];
  $p2 = $dok['pihak_kedua'] ?? null;
  [$cls, $lbl] = $statusLbl[$dok['status'] ?? ''] ?? ['s-abu', (string) ($dok['status'] ?? '')];
  $ttd = ! empty($dok['ditandatangani_pada']) ? formatTanggal(substr((string) $dok['ditandatangani_pada'], 0, 10)) : null;
  $unit = (string) ($p1['opd_nama'] ?? $opd['nama_tampil']);
  $baris = $dok['baris'] ?? [];
  ?>
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3 ro-noprint">
    <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/pk-pegawai?tahun=' . (int) $tahun) ?>"><i class="fas fa-arrow-left me-1"></i>Daftar PK pegawai</a>
    <span class="ro-chip <?= $cls ?>"><i class="ro-titik"></i><?= esc($lbl) ?></span>
    <?php if (! empty($dok['diajukan_pada'])): ?><span class="ro-chip polos">Diajukan <?= esc(date('d/m/Y', strtotime((string) $dok['diajukan_pada']))) ?></span><?php endif; ?>
    <button type="button" class="btn btn-sm btn-success ms-auto" onclick="window.print()"><i class="fas fa-print me-1"></i>Cetak</button>
  </div>
  <?php if (! empty($dok['catatan'])): ?>
    <div class="alert alert-warning small ro-noprint"><strong>Catatan:</strong> <?= esc((string) $dok['catatan']) ?></div>
  <?php endif; ?>
  <?php if (($dok['status'] ?? '') === 'lewat_aksara'): ?>
    <div class="alert alert-light border small ro-noprint"><i class="fas fa-circle-info me-1"></i>Pegawai ini pejabat struktural: Perjanjian Kinerjanya adalah dokumen PK di AKSARA (menu Perjanjian Kinerja). Tampilan di bawah adalah salinan yang dibaca eKin.
      <a class="ms-1" href="<?= base_url('perjanjian-kinerja?' . http_build_query(['tahun' => $tahun, 'opd_id' => $opd['id'], 'q' => (string) ($dok['pegawai']['jabatan'] ?? '')])) ?>">Buka PK di AKSARA</a></div>
  <?php endif; ?>

  <!-- ======================= HALAMAN 1: PERNYATAAN ======================= -->
  <div class="ro-kertas">
    <h3>Perjanjian Kinerja Tahun <?= (int) $tahun ?></h3>
    <h4><?= esc(mb_strtoupper($unit)) ?></h4>
    <p>Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan dan akuntabel serta berorientasi pada hasil, kami yang bertanda tangan di bawah ini:</p>
    <table class="id">
      <tr><td style="width:120px;">Nama</td><td>:</td><td><strong><?= esc(mb_strtoupper((string) $p1['nama'])) ?></strong><?php if (! empty($p1['fiktif'])): ?> <span class="ro-fiktif">FIKTIF</span><?php endif; ?></td></tr>
      <tr><td>NIP</td><td>:</td><td><?= esc((string) ($p1['nip'] ?? '–')) ?></td></tr>
      <tr><td>Pangkat/Gol.</td><td>:</td><td><?= esc((string) ($p1['pangkat_gol'] ?? '–')) ?></td></tr>
      <tr><td>Jabatan</td><td>:</td><td><?= esc((string) ($p1['jabatan'] ?? '')) ?></td></tr>
    </table>
    <p>Selanjutnya disebut PIHAK PERTAMA.</p>
    <table class="id">
      <tr><td style="width:120px;">Nama</td><td>:</td><td><strong><?= esc(mb_strtoupper((string) ($p2['nama'] ?? '…'))) ?></strong></td></tr>
      <tr><td>NIP</td><td>:</td><td><?= esc((string) ($p2['nip'] ?? '–')) ?></td></tr>
      <tr><td>Pangkat/Gol.</td><td>:</td><td><?= esc((string) ($p2['pangkat_gol'] ?? '–')) ?></td></tr>
      <tr><td>Jabatan</td><td>:</td><td><?= esc((string) ($p2['jabatan'] ?? '')) ?></td></tr>
    </table>
    <p>Selaku atasan langsung PIHAK PERTAMA, selanjutnya disebut PIHAK KEDUA.</p>
    <p>PIHAK PERTAMA berjanji akan mewujudkan target kinerja yang seharusnya sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah seperti yang telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.</p>
    <p>PIHAK KEDUA akan melakukan supervisi yang diperlukan serta akan melakukan evaluasi terhadap capaian kinerja dari perjanjian ini dan mengambil tindakan yang diperlukan dalam rangka pemberian penghargaan dan sanksi.</p>
    <p style="text-align:right;margin-top:18px;">Pringsewu, <?= $ttd !== null ? esc($ttd) : '…………………… ' . (int) $tahun ?></p>
    <div class="ro-ttd">
      <div>
        <div>PIHAK KEDUA,</div>
        <div class="ruang"><?= $ttd !== null ? '<span class="ro-chip s-hijau"><i class="fas fa-signature me-1"></i>ditandatangani ' . esc($ttd) . '</span>' : '<span class="ro-chip s-abu">belum ditandatangani</span>' ?></div>
        <div class="nama"><?= esc((string) ($p2['nama'] ?? '')) ?></div>
        <div>NIP. <?= esc((string) ($p2['nip'] ?? '')) ?></div>
      </div>
      <div>
        <div>PIHAK PERTAMA,</div>
        <div class="ruang"><?= in_array($dok['status'] ?? '', ['diajukan', 'ditandatangani', 'lewat_aksara'], true) ? '<span class="ro-chip s-hijau"><i class="fas fa-paper-plane me-1"></i>diajukan</span>' : '<span class="ro-chip s-abu">draf</span>' ?></div>
        <div class="nama"><?= esc((string) $p1['nama']) ?></div>
        <div>NIP. <?= esc((string) ($p1['nip'] ?? '')) ?></div>
      </div>
    </div>
  </div>

  <!-- ======================= HALAMAN 2: LAMPIRAN ======================= -->
  <div class="ro-kertas lampiran">
    <h3>Lampiran Perjanjian Kinerja Tahun <?= (int) $tahun ?></h3>
    <h4 style="margin-bottom:10px;"><?= esc(mb_strtoupper($unit)) ?></h4>
    <table class="id" style="font-size:10pt;">
      <tr><td style="width:120px;">Nama</td><td>:</td><td><?= esc((string) $p1['nama']) ?></td></tr>
      <tr><td>Jabatan</td><td>:</td><td><?= esc((string) ($p1['jabatan'] ?? '')) ?></td></tr>
      <tr><td>Unit kerja</td><td>:</td><td><?= esc((string) ($p1['unit_kerja'] ?? $unit)) ?></td></tr>
    </table>
    <?php if ($baris === []): ?>
      <p class="ro-catatan">Belum ada baris sasaran pada PK ini.</p>
    <?php else: ?>
      <div class="ro-gulir-x">
        <table class="ro-lampiran">
          <thead>
            <tr><th rowspan="2" style="width:30px;">No</th><th rowspan="2">Sasaran / Rencana Hasil Kerja</th><th rowspan="2">Indikator</th><th rowspan="2">Satuan</th>
              <th colspan="12">Target Bulanan</th><th rowspan="2">Target Tahunan</th><th rowspan="2">Metode</th></tr>
            <tr><?php for ($m = 1; $m <= 12; $m++): ?><th><?= $bulanPendek[$m] ?></th><?php endfor; ?></tr>
          </thead>
          <tbody>
            <?php $jenisSebelum = null; ?>
            <?php foreach ($baris as $b): ?>
              <?php if (($b['jenis'] ?? '') !== $jenisSebelum): $jenisSebelum = $b['jenis'] ?? ''; ?>
                <tr class="sub"><td colspan="18"><?= $jenisSebelum === 'tambahan' ? 'Kinerja Tambahan' : 'Kinerja Utama' ?></td></tr>
              <?php endif; ?>
              <tr>
                <td class="num"><?= esc((string) ($b['no'] ?? '')) ?></td>
                <td><?= esc((string) ($b['sasaran'] ?? '')) ?></td>
                <td><?= esc((string) ($b['indikator'] ?? '')) ?></td>
                <td><?= esc((string) ($b['satuan'] ?? '')) ?></td>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                  <td class="num"><?= esc($angka($b['target_bulan'][(string) $m] ?? $b['target_bulan'][$m] ?? null)) ?></td>
                <?php endfor; ?>
                <td class="num"><strong><?= esc($angka($b['jumlah'] ?? null)) ?></strong></td>
                <td><?= esc($metodeLbl[$b['metode'] ?? ''] ?? (string) ($b['metode'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
