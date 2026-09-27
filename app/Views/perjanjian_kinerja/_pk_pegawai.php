<?php
/**
 * AKSARA+ — Perjanjian Kinerja terpadu, pil "PK Pegawai · eKin": PK Pegawai dari eKin (PerjanjianKinerjaController::pkPegawaiEkin).
 * Pejabat yang PK-nya dokumen AKSARA tidak diulang di sini (mereka ada di jenjang JPT/Administrator/Pengawas).
 *
 * @var array $pkPegawai {ada, pesan, jumlah, baris, hitung, status}
 */


$tgl = static fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '–';
$st  = $pkPegawai['status'];
?>
<?php if (! $pkPegawai['ada']): ?>
  <div class="ro-kosong"><div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
    <h5>Data eKin belum tersedia</h5><p class="small mb-0"><?= esc($pkPegawai['pesan']) ?></p></div>
<?php else: ?>
  <div class="ro-pil mb-2" role="group" aria-label="Status PK Pegawai">
    <a href="<?= base_url('perjanjian-kinerja') . $qs(['jenis' => 'pegawai', 'status_pk' => null, 'hal' => null]) ?>" class="<?= $st === '' ? 'aktif' : '' ?>">Semua status<span class="n"><?= array_sum($pkPegawai['hitung']) ?></span></a>
    <?php foreach (\App\Controllers\PerjanjianKinerjaController::STATUS_PK_PEGAWAI as $k => [$cls, $lbl]): ?>
      <a href="<?= base_url('perjanjian-kinerja') . $qs(['jenis' => 'pegawai', 'status_pk' => $k, 'hal' => null]) ?>" class="<?= $st === $k ? 'aktif' : '' ?>"><?= esc($lbl) ?><span class="n"><?= (int) $pkPegawai['hitung'][$k] ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php if ($baris === []): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-file-circle-question"></i></div>
      <h5>Tidak ada PK Pegawai</h5><p class="small mb-0">eKin belum mencatat PK pegawai untuk saringan ini pada tahun <?= (int) $tahun ?>.</p></div>
  <?php else: ?>
    <p class="ro-catatan mb-2"><?= (int) $total ?> PK pegawai sampai pelaksana<?= $halMax > 1 ? ' · halaman ' . $hal . ' dari ' . $halMax : '' ?>, disusun di eKin dari SKP &amp; rencana aksi.
      Pejabat yang PK-nya dokumen AKSARA ada di jenjang JPT/Administrator/Pengawas.</p>
    <div class="ro-gulir-x" style="border:1px solid #e3e9e5;border-radius:12px;background:#fff;">
      <table class="pk-tabel" data-no-paginate style="min-width:760px;">
        <thead><tr><th style="width:38px;">No</th>
          <?php if ($kel !== 'opd'): ?><th>Perangkat Daerah</th><?php endif; ?>
          <th>Pegawai (Pihak Pertama)</th><th>Pihak Kedua</th><th>Status</th><th>Diajukan</th><th class="text-end">Baris</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($baris as $i => $pk):
              [$cls, $lbl] = \App\Controllers\PerjanjianKinerjaController::STATUS_PK_PEGAWAI[$pk['status']];
              $pid = (int) ($pk['pegawai']['id'] ?? 0);
              $dok = 'ruang-opd/' . (int) $pk['opd_id'] . '/pk-pegawai/' . $pid . '?tahun=' . (int) $tahun;
              $ra  = 'ruang-opd/' . (int) $pk['opd_id'] . '/rencana-aksi-pegawai/' . $pid . '?tahun=' . (int) $tahun;
          ?>
            <tr>
              <td class="no"><?= ($hal - 1) * 50 + $i + 1 ?></td>
              <?php if ($kel !== 'opd'): ?><td><a href="<?= base_url('ruang-opd/' . (int) $pk['opd_id'] . '?tahun=' . (int) $tahun) ?>"><?= esc((string) $pk['opd_nama']) ?></a></td><?php endif; ?>
              <td><strong><?= esc((string) ($pk['pegawai']['nama'] ?? '')) ?></strong>
                <?php if (! empty($pk['pegawai']['fiktif'])): ?><span class="ro-fiktif ms-1">FIKTIF</span><?php endif; ?>
                <div class="text-muted" style="font-size:.72rem;"><?= esc((string) ($pk['pegawai']['jabatan'] ?? '')) ?></div></td>
              <td><?= esc((string) ($pk['pihak_kedua']['nama'] ?? '–')) ?><div class="text-muted" style="font-size:.72rem;"><?= esc((string) ($pk['pihak_kedua']['jabatan'] ?? '')) ?></div></td>
              <td><span class="ro-chip <?= $cls ?>"><i class="ro-titik"></i><?= esc($lbl) ?></span></td>
              <td><?= esc($tgl($pk['diajukan_pada'] ?? null)) ?></td>
              <td class="text-end"><?= (int) ($pk['jumlah_baris'] ?? 0) ?></td>
              <td class="text-end" style="white-space:nowrap;">
                <a class="btn btn-sm btn-outline-secondary py-0 px-2" href="<?= base_url($dok) ?>"><i class="fas fa-file-lines me-1"></i>Dokumen</a>
                <a class="btn btn-sm btn-outline-success py-0 px-2" href="<?= base_url($ra) ?>" title="Rencana aksi bulanan pegawai ini"><i class="fas fa-list-check me-1"></i>Rencana aksi</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($halMax > 1): ?>
      <nav class="pk-halaman" aria-label="Halaman">
        <?php for ($h = 1; $h <= $halMax; $h++): ?>
          <?php if ($h === $hal): ?><span class="aktif"><?= $h ?></span>
          <?php else: ?><a href="<?= base_url('perjanjian-kinerja') . $qs(['jenis' => 'pegawai', 'status_pk' => $st !== '' ? $st : null, 'hal' => $h]) ?>"><?= $h ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
