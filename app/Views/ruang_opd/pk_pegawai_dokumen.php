<?php
/**
 * Ruang OPD — dokumen PK satu pegawai, baca-saja (AKSARA+). Data: eKin endpoint
 * pk-pegawai/{pegawai_id}. Susunannya mengikuti cetakan PK: halaman 1 pernyataan &
 * tanda tangan, halaman 2 lampiran dengan target bulanan. Tombol Cetak memakai
 * pencetakan peramban (gaya @media print di _gaya menyembunyikan bingkai aplikasi).
 *
 * @var array|null $dok
 * @var array      $pkAksara    PK AKSARA pegawai ini (pihak pertama), terbaru dulu — hanya bila status lewat_aksara
 * @var array      $isiPkAksara [pk_id => sasaran → indikator → target]
 *
 * MENGAPA status "PK di AKSARA" tidak memakai kertas di bawah: eKin sengaja tidak membuat PK pegawai bagi pihak
 * pertama PK jabatan, jadi jawabannya kosong (tanpa pihak kedua, tanpa baris) — kertas kosong berkesan PK-nya belum
 * ada. Yang tampil adalah PK AKSARA-nya sendiri, dengan tombol Lihat/Cetak halaman PK aslinya.
 */
$this->setVar('aktifTab', 'pk');
$this->setVar('shellCss', $shellCss);

$statusLbl = [
    'ditandatangani' => ['s-hijau', 'Ditandatangani'],
    'lewat_aksara'   => ['s-biru', 'PK di AKSARA'],
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
$jenisPk = ['bupati' => 'PK Bupati', 'jpt' => 'PK JPT', 'camat' => 'PK Camat', 'administrator' => 'PK Administrator', 'pengawas' => 'PK Pengawas'];
$lewatAksara = is_array($dok) && ($dok['status'] ?? '') === 'lewat_aksara';
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
    <?php if (! $lewatAksara): ?>
      <button type="button" class="btn btn-sm btn-success ms-auto" onclick="window.print()"><i class="fas fa-print me-1"></i>Cetak</button>
    <?php endif; ?>
  </div>
  <?php if (! empty($dok['catatan'])): ?>
    <div class="alert alert-warning small ro-noprint"><strong>Catatan:</strong> <?= esc((string) $dok['catatan']) ?></div>
  <?php endif; ?>
  <?php if ($lewatAksara): ?>
    <?php $pid = (int) ($p1['id'] ?? 0); $daftarPkUrl = base_url('perjanjian-kinerja?' . http_build_query(['tahun' => $tahun, 'pegawai' => $pid])); ?>
    <div class="alert alert-light border small"><i class="fas fa-circle-info me-1"></i>
      <strong><?= esc((string) $p1['nama']) ?></strong><?php if (! empty($p1['fiktif'])): ?> <span class="ro-fiktif">FIKTIF</span><?php endif; ?> · <?= esc((string) ($p1['jabatan'] ?? '')) ?>
      adalah pihak pertama Perjanjian Kinerja jabatan di AKSARA. eKin tidak membuat PK pegawai terpisah untuknya;
      PK yang berlaku adalah dokumen AKSARA di bawah ini<?= ! empty($dok['aksara_diperiksa_pada']) ? ' (diselaraskan eKin ' . esc(date('d/m/Y H:i', strtotime((string) $dok['aksara_diperiksa_pada']))) . ')' : '' ?>.
      <a class="ms-1" href="<?= $daftarPkUrl ?>" data-ro-tautan>Buka di daftar Perjanjian Kinerja</a></div>

    <?php if ($pkAksara === []): ?>
      <div class="ro-kosong">
        <div class="ic"><i class="fas fa-triangle-exclamation"></i></div>
        <h5>PK <?= (int) $tahun ?> tidak ditemukan di AKSARA</h5>
        <p class="small mb-0">eKin mencatat pegawai ini sebagai pihak pertama PK AKSARA, tetapi AKSARA tidak menemukan Perjanjian Kinerja <?= (int) $tahun ?>
          dengan pegawai ini sebagai pihak pertama. Periksa menu Perjanjian Kinerja, lalu minta eKin menyelaraskan ulang ("Periksa ke AKSARA").</p>
      </div>
    <?php endif; ?>
    <?php foreach ($pkAksara as $i => $pa): ?>
      <?php
      $ctx = ['jenis' => $pa['jenis'], 'id' => $pa['id']];
      // Admin OPD hanya boleh membuka PK yang tersimpan di OPD-nya sendiri (PK Lurah, mis., tersimpan di kecamatan).
      $tautanBoleh = $lintas || (int) $pa['opd_id'] === (int) $opd['id'];
      $tL = $tautanBoleh ? $buka('pk_lihat', $ctx) : null;
      $tC = $tautanBoleh ? $buka('pk_cetak', $ctx) : null;
      ?>
      <section class="ro-pka">
        <div class="ro-pka-kepala">
          <span class="ro-chip s-biru"><i class="ro-titik"></i><?= esc($jenisPk[$pa['jenis']] ?? 'PK') ?></span>
          <h5><?= $i === 0 ? 'Perjanjian Kinerja ' . (int) $tahun . ' yang berlaku' : 'PK ' . (int) $tahun . ' sebelumnya' ?></h5>
          <?php if ($pa['status_1'] !== ''): ?><span class="ro-chip s-kuning"><?= esc($pa['status_1']) ?></span><?php endif; ?>
          <div class="kanan">
            <?php if ($tL): ?><a class="btn btn-sm btn-outline-success" href="<?= esc(base_url($tL['url'])) ?>" data-ro-tautan><i class="fas fa-eye me-1"></i>Lihat</a><?php endif; ?>
            <?php if ($tC): ?><a class="btn btn-sm btn-success" href="<?= esc(base_url($tC['url'])) ?>" target="_blank" rel="noopener" data-ro-tautan data-pdf><i class="fas fa-print me-1"></i>Cetak PDF</a><?php endif; ?>
          </div>
        </div>
        <dl>
          <dt>Tanggal</dt><dd><?= ! empty($pa['tanggal']) ? esc(formatTanggal(substr((string) $pa['tanggal'], 0, 10))) : '–' ?></dd>
          <dt>Pihak pertama</dt><dd><?= esc((string) ($pa['nama_1'] ?? '')) ?> · <?= esc((string) $pa['jabatan_1']) ?></dd>
          <dt>Pihak kedua</dt><dd><?= esc((string) ($pa['nama_2'] ?? '–')) ?><?= $pa['status_2'] !== '' ? ' (' . esc($pa['status_2']) . ')' : '' ?> · <?= esc((string) $pa['jabatan_2']) ?></dd>
          <dt>Tersimpan di</dt><dd><?= esc((string) $pa['nama_opd_tampil']) ?><?= (int) $pa['opd_id'] !== (int) $opd['id'] ? ' <span class="text-muted">(perangkat daerah lain)</span>' : '' ?></dd>
        </dl>
        <?php $isi = $isiPkAksara[(int) $pa['id']] ?? []; ?>
        <?php if ($isi === []): ?>
          <p class="ro-catatan mb-0">PK ini belum berisi sasaran.</p>
        <?php else: ?>
          <div class="ro-gulir-x">
            <table class="ro-pka-isi" data-no-paginate>
              <thead><tr><th style="width:34px;">No</th><th>Sasaran</th><th>Indikator</th><th class="num">Target <?= (int) $tahun ?></th></tr></thead>
              <tbody>
                <?php $no = 0; foreach ($isi as $sas): ?>
                  <?php $ind = $sas['indikator'] ?: [['indikator' => '–', 'target' => '', 'satuan' => '']]; ?>
                  <?php foreach ($ind as $k => $x): ?>
                    <tr>
                      <?php if ($k === 0): ?><td class="no" rowspan="<?= count($ind) ?>"><?= ++$no ?></td><td class="sas" rowspan="<?= count($ind) ?>"><?= esc($sas['sasaran']) ?></td><?php endif; ?>
                      <td class="ind"><?= esc($x['indikator']) ?></td>
                      <td class="num"><?= esc(trim($x['target'] . ' ' . $x['satuan'])) ?: '–' ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
    <p class="ro-catatan"><i class="fas fa-circle-info me-1"></i>Target bulanan pejabat ini diatur di Rencana Aksi PK AKSARA dan diturunkan ke SKP-nya di eKin (RHK bersumber indikator PK).</p>
  <?php else: ?>

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
        <div class="ruang"><?php if (($dok['status'] ?? '') === 'lewat_aksara'): ?><span class="ro-chip s-abu">dokumen PK AKSARA</span><?php else: ?><?= in_array($dok['status'] ?? '', ['diajukan', 'ditandatangani'], true) ? '<span class="ro-chip s-hijau"><i class="fas fa-paper-plane me-1"></i>diajukan</span>' : '<span class="ro-chip s-abu">draf</span>' ?><?php endif; ?></div>
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
  <?php endif; /* lewatAksara */ ?>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
