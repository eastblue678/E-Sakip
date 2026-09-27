<?php
/**
 * AKSARA+ — Rencana Aksi Pegawai lintas OPD (Admin Kabupaten, Inspektorat, Bupati, Super Admin): ringkasan rencana
 * aksi bulanan per perangkat daerah yang pegawainya sudah dimuat di eKin; klik untuk daftar per atasan.
 *
 * @var int   $tahun
 * @var int   $bulan
 * @var list<array> $baris
 * @var bool  $ekinAda
 */
$this->setVar('shellCss', $shellCss);
$bulanPendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$namaBulan   = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$persen = static fn ($v) => $v === null ? '–' : number_format((float) $v, 1, ',', '.') . '%';
?>
<?= $this->include('templates/shell_atas') ?>
<style>.rapi-siap { color: #5b4a8b; font-weight: 700; font-size: .7rem; white-space: nowrap; }</style>

<div class="ro">
  <div class="ro-hero mb-3">
    <div class="ic"><i class="fas fa-list-check"></i></div>
    <div class="isi">
      <h2>Rencana Aksi Pegawai</h2>
      <p>Rencana aksi bulanan sampai pelaksana, disusun pegawai di eKin dari SKP-nya. Realisasi dihitung dari kegiatan harian yang sudah disetujui atasan.</p>
      <p class="mt-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="ro-lencana"><i class="fas fa-eye"></i> Hanya baca</span>
        <span class="ro-lencana"><i class="fas fa-users"></i> Sumber: eKin</span>
      </p>
    </div>
    <div class="kanan">
      <nav class="ro-tahun" aria-label="Pilih tahun">
        <?php foreach ($tahunList as $t): ?>
          <a href="<?= base_url('rencana-aksi-pegawai?tahun=' . (int) $t) ?>" class="<?= (int) $t === $tahun ? 'aktif' : '' ?>"<?= (int) $t === $tahun ? ' aria-current="true"' : '' ?>><?= (int) $t ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>

  <div class="ro-alat mb-3 d-flex flex-wrap align-items-center gap-2">
    <strong style="font-size:.85rem;">Bulan</strong>
    <?php foreach ($bulanPendek as $b => $nm): ?>
      <a class="btn btn-sm <?= $b === $bulan ? 'btn-success' : 'btn-outline-secondary' ?> py-0 px-2" href="<?= base_url('rencana-aksi-pegawai?tahun=' . (int) $tahun . '&bulan=' . $b) ?>"><?= $nm ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (! $ekinAda): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
      <h5>Data eKin belum tersedia</h5><p class="small mb-0"><?= esc($ekinPesan) ?></p></div>
  <?php elseif ($baris === []): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-users-slash"></i></div>
      <h5>Belum ada perangkat daerah di eKin</h5><p class="small mb-0">Pegawai belum dimuat di eKin untuk tahun <?= (int) $tahun ?>.</p></div>
  <?php else: ?>
    <div class="ro-gulir-x" style="border:1px solid #e3e9e5;border-radius:12px;background:#fff;">
      <table class="ro-mini" data-no-paginate style="font-size:.84rem;min-width:720px !important;">
        <thead><tr><th>Perangkat daerah</th><th class="num">Pegawai ber-SKP</th><th class="num">Rencana aksi <?= esc($namaBulan[$bulan]) ?></th>
          <th class="num">Tercapai</th><th class="num">Capaian rata-rata</th><th class="num">RA tanpa kegiatan</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($baris as $r): $url = base_url('ruang-opd/' . (int) $r['opd_id'] . '/rencana-aksi-pegawai?tahun=' . (int) $tahun . '&bulan=' . (int) $bulan); ?>
            <tr>
              <td><a href="<?= esc($url, 'attr') ?>"><strong><?= esc($r['nama']) ?></strong></a></td>
              <?php if (! $r['ada']): ?>
                <td class="num text-muted" colspan="5">Data eKin perangkat daerah ini belum terbaca.</td>
              <?php else: ?>
                <td class="num"><?= (int) $r['ber_skp'] ?>/<?= (int) $r['pegawai'] ?></td>
                <td class="num"><?= (int) $r['ra'] ?><?php if ((int) ($r['persiapan'] ?? 0) > 0): ?><br><small class="rapi-siap" title="Rencana aksi persiapan: bulan tanpa target angka (indeks/nilai resmi yang belum dirilis, atau bulan non-ukur indikator posisi). Tidak dihitung di jumlah, tercapai, maupun capaian.">+<?= (int) $r['persiapan'] ?> persiapan indeks/nilai rilis</small><?php endif; ?></td>
                <td class="num"><?= (int) $r['tercapai'] ?></td>
                <td class="num"><?= $persen($r['capaian']) ?></td>
                <td class="num"><?= (int) $r['belum'] > 0 ? '<span style="color:#9a6b00;font-weight:700;">' . (int) $r['belum'] . '</span>' : '0' ?></td>
              <?php endif; ?>
              <td class="num"><a class="btn btn-sm btn-outline-success py-0 px-2" href="<?= esc($url, 'attr') ?>">Buka <i class="fas fa-arrow-right"></i></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="ro-catatan mt-2"><i class="fas fa-circle-info me-1"></i>Hanya perangkat daerah yang pegawainya sudah dimuat di eKin. Kolom rencana aksi, tercapai, dan capaian hanya menghitung rencana aksi yang diukur bulan itu;
      rencana aksi <b>persiapan</b> (indeks/nilai resmi yang belum dirilis, bulan non-ukur indikator posisi) ditulis terpisah. Rencana aksi triwulan pada Perjanjian Kinerja tetap di menu Target &amp; Rencana Aksi.</p>
  <?php endif; ?>
</div>

<?= $this->include('templates/shell_bawah') ?>
