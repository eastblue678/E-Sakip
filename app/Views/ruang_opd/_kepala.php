<?php
/**
 * Kepala bersama halaman satu OPD di Ruang OPD: remah, nama, jenis, kepala menurut PK,
 * pemilih tahun, skor, dan tab (Ringkasan · Cascading Pegawai · PK Pegawai).
 *
 * Dipanggil dengan $this->include('ruang_opd/_kepala') — hanya melihat DATA view:
 * $opd, $tahun, $tahunList, $kepala, $lintas, $buka, opsional $aktifTab, $skor.
 */
use App\Models\OpdModel;

$aktifTab = $aktifTab ?? 'hub';
$hubUrl   = base_url('ruang-opd/' . (int) $opd['id']);
$tDash    = $buka('dashboard');
$jenisLbl = OpdModel::JENIS_LABEL[$opd['jenis']] ?? $opd['jenis'];
$skorNil  = $skor ?? null;
?>
<?= $this->include('templates/shell_atas') ?>

<div class="ro">
  <div class="ro-hero mb-2">
    <div class="ic"><i class="fas fa-building-columns"></i></div>
    <div class="isi">
      <div class="ro-remah">
        <?php if ($lintas): ?>
          <a href="<?= base_url('ruang-opd?tahun=' . (int) $tahun) ?>"><i class="fas fa-table-cells me-1"></i>Ruang OPD</a> ›
        <?php else: ?>
          Ruang OPD Saya ›
        <?php endif; ?>
        <?= esc($jenisLbl) ?>
      </div>
      <h2><?= esc($opd['nama_tampil']) ?></h2>
      <p>
        <?php if ($kepala): ?>
          <i class="fas fa-user-tie me-1"></i><?= esc(trim($kepala['status'] . ' ' . $kepala['jabatan'])) ?>:
          <strong><?= esc($kepala['nama']) ?></strong>
          <span class="opacity-75">(menurut PK <?= $kepala['jenis'] === 'camat' ? 'Camat' : 'JPT' ?> <?= (int) $tahun ?>)</span>
        <?php else: ?>
          <i class="fas fa-user-tie me-1"></i>Kepala belum tercatat pada PK JPT/Camat <?= (int) $tahun ?>
        <?php endif; ?>
      </p>
      <p class="mt-2 d-flex flex-wrap gap-2">
        <span class="ro-lencana"><i class="fas fa-eye"></i> <?= $lintas ? 'Hanya baca' : 'Perangkat daerah Anda' ?></span>
        <?php if ($tDash): ?>
          <a class="ro-lencana text-white" href="<?= base_url($tDash['url']) ?>"><i class="fas fa-gauge-high"></i> Dashboard kinerja</a>
        <?php endif; ?>
        <?php /* Audiensi: Admin Kabupaten bisa langsung melihat aplikasi persis seperti admin OPD ini (fitur demo, .env). */ ?>
        <?php if ($lintas && ! \App\Services\MasukSebagaiService::sedangMeniru() && \App\Services\MasukSebagaiService::bolehDipakai()): ?>
          <a class="ro-lencana text-white" href="<?= base_url('masuk-sebagai?' . http_build_query(['q' => $opd['nama_opd']])) ?>"><i class="fas fa-user-secret"></i> Masuk sebagai admin OPD ini</a>
        <?php endif; ?>
      </p>
    </div>
    <div class="kanan">
      <nav class="ro-tahun" aria-label="Pilih tahun">
        <?php foreach ($tahunList as $t): ?>
          <a href="<?= esc(current_url() . '?tahun=' . (int) $t) ?>" class="<?= (int) $t === (int) $tahun ? 'aktif' : '' ?>"<?= (int) $t === (int) $tahun ? ' aria-current="true"' : '' ?>><?= (int) $t ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if ($skorNil !== null): ?>
        <div class="ro-cincin" style="--p: <?= (int) $skorNil ?>;" title="Skor kelengkapan dokumen SAKIP <?= (int) $tahun ?>">
          <span><?= (int) $skorNil ?>%<small>lengkap</small></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <nav class="ro-tab ro-noprint" aria-label="Bagian Ruang OPD">
    <a href="<?= $hubUrl . '?tahun=' . (int) $tahun ?>" class="<?= $aktifTab === 'hub' ? 'aktif' : '' ?>"><i class="fas fa-layer-group"></i>Dokumen SAKIP</a>
    <a href="<?= $hubUrl . '/cascading-pegawai?tahun=' . (int) $tahun ?>" class="<?= $aktifTab === 'cascading' ? 'aktif' : '' ?>"><i class="fas fa-diagram-project"></i>Cascading Pegawai</a>
    <a href="<?= $hubUrl . '/pk-pegawai?tahun=' . (int) $tahun ?>" class="<?= $aktifTab === 'pk' ? 'aktif' : '' ?>"><i class="fas fa-file-signature"></i>PK Pegawai</a>
  </nav>
