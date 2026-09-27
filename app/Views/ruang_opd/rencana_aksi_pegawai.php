<?php
/**
 * Ruang OPD — Rencana Aksi Pegawai (AKSARA+). Data: eKin api/aksara/opd/{id}/rencana-aksi (bulan terpilih) +
 * opd/{id}/pk-pegawai (status PK). Realisasi = kemajuan dari kinerja harian DISETUJUI (logika e-Kinerja BKN),
 * sumber yang sama dengan halaman Rencana Aksi eKin.
 *
 * @var bool   $ada
 * @var int    $bulan
 * @var string $status   '' | belum | berjalan | tercapai | tanpa_skp
 * @var string $q
 * @var list<array{p:array, tingkat:int, bawahan:int, cocok:bool}> $baris
 * @var array  $ringkas
 * @var array<int,string> $pkStatus
 */
$this->setVar('aktifTab', 'ra');
$this->setVar('shellCss', $shellCss);

$namaBulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanPendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$dasar = 'ruang-opd/' . (int) $opd['id'] . '/rencana-aksi-pegawai';
$qs = static fn (array $ubah) => base_url($dasar) . '?' . http_build_query(array_filter(array_merge(['tahun' => $tahun, 'bulan' => $bulan, 'status' => $status, 'q' => $q], $ubah), static fn ($v) => $v !== '' && $v !== null));
$skpLbl = ['disetujui' => ['s-hijau', 'SKP disetujui'], 'diajukan' => ['s-kuning', 'SKP diajukan'], 'draf' => ['s-abu', 'SKP draf'], 'dikembalikan' => ['s-merah', 'SKP dikembalikan']];
$pkLbl = [
    'ditandatangani' => ['s-hijau', 'PK ditandatangani'], 'lewat_aksara' => ['s-biru', 'PK di AKSARA'], 'diajukan' => ['s-kuning', 'PK diajukan'],
    'draf' => ['s-abu', 'PK draf'], 'dikembalikan' => ['s-merah', 'PK dikembalikan'],
];
$saringan = ['' => 'Semua', 'belum' => 'Ada RA tanpa kegiatan', 'berjalan' => 'Belum semua tercapai', 'tercapai' => 'Semua tercapai', 'tanpa_skp' => 'Belum ada SKP'];
?>
<?= $this->include('ruang_opd/_kepala') ?>

<style>
  .rap-bulan { display: flex; flex-wrap: wrap; gap: 4px; }
  .rap-bulan a { min-width: 44px; text-align: center; border: 1px solid #dbe5de; border-radius: 8px; padding: 3px 6px; font-size: .76rem; font-weight: 700; color: #33483b; background: #fff; }
  .rap-bulan a.aktif { background: #00743e; border-color: #00743e; color: #fff; }
  .rap-ringkas { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 14px; }
  .rap-ringkas div { background: #fff; border: 1px solid #e3e9e5; border-radius: 12px; padding: 10px 12px; }
  .rap-ringkas b { display: block; font-size: 1.3rem; font-weight: 800; color: #1f3a2a; font-variant-numeric: tabular-nums; }
  .rap-ringkas span { font-size: .75rem; color: #5d6b62; }
  .rap-daftar { background: #fff; border: 1px solid #e3e9e5; border-radius: 12px; overflow: hidden; }
  .rap-baris { display: grid; grid-template-columns: minmax(0, 1fr) 150px 200px 110px; gap: 10px; align-items: center; padding: 8px 12px; border-top: 1px solid #eef2ef; color: inherit; text-decoration: none; }
  .rap-baris:first-child { border-top: 0; }
  a.rap-baris:hover { background: #f5fbf7; }
  .rap-baris.redup { opacity: .55; }
  .rap-orang { display: flex; gap: 8px; align-items: flex-start; min-width: 0; padding-left: calc(var(--t, 0) * 20px); }
  .rap-orang .garis { flex: 0 0 auto; color: #b8c7bd; font-size: .8rem; margin-top: 2px; }
  .rap-orang b { display: block; font-size: .84rem; color: #1f3a2a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rap-orang small { display: block; font-size: .72rem; color: #647269; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rap-status { display: flex; flex-wrap: wrap; gap: 4px; }
  .rap-status .ro-chip { font-size: .66rem; }
  .rap-ra { font-size: .76rem; color: #3f5247; }
  .rap-ra .batang { height: 6px; border-radius: 99px; background: #edf1ee; overflow: hidden; margin-top: 3px; }
  .rap-ra .batang span { display: block; height: 100%; background: linear-gradient(90deg, #00743e, #6eab11); }
  .rap-ra .peringatan { color: #9a6b00; font-weight: 700; }
  .rap-aksi { text-align: right; font-size: .76rem; color: #00743e; font-weight: 700; white-space: nowrap; }
  .rap-kepala { background: #f6f9f7; font-size: .68rem; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; color: #5d6b62; }
  @media (max-width: 767.98px) {
    .rap-baris { grid-template-columns: minmax(0, 1fr) auto; }
    .rap-baris .rap-status { grid-column: 1 / -1; padding-left: calc(var(--t, 0) * 12px + 20px); }
    .rap-baris .rap-ra { grid-column: 1 / -1; padding-left: calc(var(--t, 0) * 12px + 20px); }
    .rap-orang { padding-left: calc(var(--t, 0) * 12px); }
    .rap-kepala { display: none; }
  }
</style>

<?php if (! $ada): ?>
  <div class="ro-kosong">
    <div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
    <h5>Data eKin belum tersedia</h5>
    <p class="small mb-0"><?= esc($ekinPesan) ?> Rencana aksi pegawai akan tampil begitu eKin dapat dihubungi.</p>
  </div>
<?php else: ?>
  <div class="ro-alat mb-3 ro-noprint">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <strong class="me-1" style="font-size:.85rem;">Rencana aksi bulan</strong>
      <nav class="rap-bulan" aria-label="Pilih bulan">
        <?php foreach ($bulanPendek as $b => $nm): ?>
          <a href="<?= esc($qs(['bulan' => $b]), 'attr') ?>" class="<?= $b === $bulan ? 'aktif' : '' ?>"<?= $b === $bulan ? ' aria-current="true"' : '' ?>><?= $nm ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <form method="get" action="<?= base_url($dasar) ?>" class="d-flex flex-wrap gap-2 align-items-center">
      <input type="hidden" name="tahun" value="<?= (int) $tahun ?>"><input type="hidden" name="bulan" value="<?= (int) $bulan ?>">
      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
      <label class="ro-cari mb-0" style="flex:1 1 220px;">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="q" value="<?= esc($q, 'attr') ?>" class="form-control" placeholder="Cari nama atau jabatan…" aria-label="Cari pegawai">
      </label>
      <div class="ro-pil" role="group" aria-label="Saring">
        <?php foreach ($saringan as $k => $lbl): ?>
          <a href="<?= esc($qs(['status' => $k]), 'attr') ?>" class="<?= $status === $k ? 'aktif' : '' ?>"><?= esc($lbl) ?></a>
        <?php endforeach; ?>
      </div>
      <noscript><button class="btn btn-sm btn-success">Cari</button></noscript>
    </form>
  </div>

  <div class="rap-ringkas">
    <div><b><?= (int) $ringkas['ber_skp'] ?>/<?= (int) $ringkas['pegawai'] ?></b><span>pegawai ber-SKP <?= (int) $tahun ?></span></div>
    <div><b><?= (int) $ringkas['ra'] ?></b><span>rencana aksi <?= esc($namaBulan[$bulan]) ?></span></div>
    <div><b><?= (int) $ringkas['tercapai'] ?></b><span>sudah mencapai target</span></div>
    <div><b><?= $ringkas['capaian'] === null ? '–' : number_format((float) $ringkas['capaian'], 1, ',', '.') . '%' ?></b><span>capaian rata-rata pegawai</span></div>
    <div><b><?= (int) $ringkas['belum'] ?></b><span>rencana aksi belum ada kegiatan harian</span></div>
  </div>

  <?php if ($baris === []): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-list-check"></i></div>
      <h5><?= ($status === '' && $q === '') ? 'Belum ada pegawai di eKin' : 'Tidak ada yang cocok' ?></h5>
      <p class="small mb-0"><?= ($status === '' && $q === '') ? 'Perangkat daerah ini belum punya pegawai yang dimuat di eKin.' : 'Ubah saringan atau kata kunci.' ?></p></div>
  <?php else: ?>
    <div class="rap-daftar" role="list">
      <div class="rap-baris rap-kepala" aria-hidden="true"><span>Pegawai (disusun per atasan langsung)</span><span>SKP &amp; PK</span><span>Rencana aksi <?= esc($namaBulan[$bulan]) ?></span><span></span></div>
      <?php foreach ($baris as $b):
          $p   = $b['p'];
          $pid = (int) $p['pegawai_id'];
          $ra  = $p['ra'] ?? [];
          $n   = (int) ($ra['jumlah'] ?? 0);
          $skp = $p['skp'] ?? null;
          [$sc, $sl] = $skp === null ? ['s-merah', 'Belum ada SKP'] : ($skpLbl[$skp['status']] ?? ['s-abu', 'SKP ' . $skp['status']]);
          $pk  = $pkStatus[$pid] ?? '';
          $url = base_url($dasar . '/' . $pid . '?tahun=' . (int) $tahun);
          $tag = $skp !== null ? 'a' : 'div';
      ?>
        <<?= $tag ?> class="rap-baris<?= $b['cocok'] ? '' : ' redup' ?>" role="listitem" style="--t: <?= min(6, (int) $b['tingkat']) ?>;"<?= $tag === 'a' ? ' href="' . esc($url, 'attr') . '" data-ro-tautan' : '' ?>>
          <span class="rap-orang">
            <?php if ($b['tingkat'] > 0): ?><span class="garis" aria-hidden="true">└</span><?php endif; ?>
            <span style="min-width:0;"><b><?= esc((string) $p['nama']) ?><?php if (! empty($p['fiktif'])): ?> <span class="ro-fiktif">FIKTIF</span><?php endif; ?></b>
              <small><?= esc((string) $p['jabatan']) ?><?= $b['bawahan'] > 0 ? ' · ' . (int) $b['bawahan'] . ' bawahan' : '' ?></small></span>
          </span>
          <span class="rap-status">
            <span class="ro-chip <?= $sc ?>"><i class="ro-titik"></i><?= esc($sl) ?></span>
            <?php if (isset($pkLbl[$pk])): ?><span class="ro-chip <?= $pkLbl[$pk][0] ?>"><i class="ro-titik"></i><?= esc($pkLbl[$pk][1]) ?></span><?php endif; ?>
          </span>
          <span class="rap-ra">
            <?php if ($skp === null): ?>
              <span class="text-muted">–</span>
            <?php elseif ($n === 0): ?>
              <span class="text-muted">Tidak ada rencana aksi bulan ini</span>
            <?php else: ?>
              <?= $n ?> rencana aksi · <b><?= (int) ($ra['tercapai'] ?? 0) ?></b> tercapai<?= ($ra['capaian'] ?? null) !== null ? ' · ' . number_format((float) $ra['capaian'], 1, ',', '.') . '%' : '' ?>
              <?php if (($ra['capaian'] ?? null) !== null): ?><div class="batang"><span style="width:<?= min(100, (float) $ra['capaian']) ?>%"></span></div><?php endif; ?>
              <?php if ((int) ($ra['belum_ada_kegiatan'] ?? 0) > 0): ?><div class="peringatan"><?= (int) $ra['belum_ada_kegiatan'] ?> belum ada kegiatan harian</div><?php endif; ?>
              <?php if ((int) ($ra['sumber_ikp'] ?? 0) > 0): ?><div class="text-muted" style="font-size:.7rem;"><i class="fas fa-link me-1"></i><?= (int) $ra['sumber_ikp'] ?> dari IKP AKSARA<?= (int) ($ra['belum_dilaporkan'] ?? 0) > 0 ? ' · ' . (int) $ra['belum_dilaporkan'] . ' belum dilaporkan' : '' ?></div><?php endif; ?>
            <?php endif; ?>
          </span>
          <span class="rap-aksi"><?= $skp !== null ? 'Rincian <i class="fas fa-arrow-right"></i>' : '' ?></span>
        </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
    <p class="ro-catatan mt-2"><i class="fas fa-circle-info me-1"></i>Rencana aksi bulanan disusun pegawai di eKin dari SKP-nya. Realisasi dihitung dari kinerja harian yang sudah disetujui atasan
      (Trajectory: setiap kemajuan menambah realisasi; Non-Trajectory: dihitung setelah ditandai Selesai), sama dengan halaman Rencana Aksi di eKin.
      Capaian dibatasi 100% per rencana aksi. Untuk Kepala Perangkat Daerah, rencana aksi dari IKP memakai realisasi bulanan IKP di AKSARA
      (bulan yang belum dilaporkan tidak dihitung). Baris redup = atasan yang ditampilkan sebagai konteks saringan.</p>
  <?php endif; ?>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
