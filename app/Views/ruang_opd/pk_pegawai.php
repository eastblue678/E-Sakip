<?php
/**
 * Ruang OPD — daftar PK Pegawai (AKSARA+). Data: eKin endpoint opd/{id}/pk-pegawai.
 * "lewat_aksara" = pejabat struktural yang PK-nya adalah dokumen PK AKSARA (bukan
 * dibuat ulang di eKin; status tanda tangannya tidak tercatat di mana pun, jadi tidak
 * dihitung "ditandatangani"); "belum_ada_skp" = pegawai belum menyusun SKP tahun ini.
 *
 * @var bool   $ada       eKin menjawab?
 * @var array  $baris     PKRINGKAS tersaring
 * @var int    $jumlah
 * @var array  $hitung    [status => jumlah]
 * @var string $status
 * @var string $q
 * @var array  $pkAksara  [id pegawai => PK AKSARA-nya, terbaru dulu] untuk baris "PK di AKSARA"
 */
$this->setVar('aktifTab', 'pk');
$this->setVar('shellCss', $shellCss);

$statusLbl = [
    'ditandatangani' => ['s-hijau', 'Ditandatangani'],
    'lewat_aksara'   => ['s-biru', 'PK di AKSARA'],
    'diajukan'       => ['s-kuning', 'Diajukan'],
    'draf'           => ['s-abu', 'Draf'],
    'dikembalikan'   => ['s-merah', 'Dikembalikan'],
    'belum_ada_skp'  => ['s-abu', 'Belum ada SKP'],
];
$tgl = static fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '–';
$jenisPk = ['bupati' => 'PK Bupati', 'jpt' => 'PK JPT', 'camat' => 'PK Camat', 'administrator' => 'PK Administrator', 'pengawas' => 'PK Pengawas'];
$dasar = 'ruang-opd/' . (int) $opd['id'] . '/pk-pegawai';
?>
<?= $this->include('ruang_opd/_kepala') ?>

<?php if (! $ada): ?>
  <div class="ro-kosong">
    <div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
    <h5>Data eKin belum tersedia</h5>
    <p class="small mb-0"><?= esc($ekinPesan) ?> Daftar PK pegawai akan tampil begitu eKin dapat dihubungi.</p>
  </div>
<?php else: ?>
  <form method="get" class="ro-alat mb-3 ro-noprint" action="<?= base_url($dasar) ?>">
    <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
    <label class="ro-cari mb-0">
      <i class="fas fa-magnifying-glass"></i>
      <input type="search" name="q" value="<?= esc($q, 'attr') ?>" class="form-control" placeholder="Cari nama atau jabatan pegawai / pihak kedua…" aria-label="Cari PK pegawai">
    </label>
    <div class="ro-pil" role="group" aria-label="Saring status">
      <a href="<?= base_url($dasar . '?' . http_build_query(array_filter(['tahun' => $tahun, 'q' => $q]))) ?>" class="<?= $status === '' ? 'aktif' : '' ?>">Semua<span class="n"><?= (int) $jumlah ?></span></a>
      <?php foreach ($statusLbl as $k => [$cls, $lbl]): ?>
        <?php if (($hitung[$k] ?? 0) > 0): ?>
          <a href="<?= base_url($dasar . '?' . http_build_query(array_filter(['tahun' => $tahun, 'status' => $k, 'q' => $q]))) ?>" class="<?= $status === $k ? 'aktif' : '' ?>"><?= esc($lbl) ?><span class="n"><?= (int) $hitung[$k] ?></span></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <noscript><button class="btn btn-sm btn-success">Cari</button></noscript>
  </form>

  <?php if ($baris === []): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-file-circle-question"></i></div>
      <h5><?= $jumlah === 0 ? 'Belum ada PK pegawai' : 'Tidak ada yang cocok' ?></h5>
      <p class="small mb-0"><?= $jumlah === 0 ? 'eKin belum mencatat PK pegawai perangkat daerah ini untuk tahun ' . (int) $tahun . '.' : 'Ubah saringan atau kata kunci.' ?></p></div>
  <?php else: ?>
    <div class="ro-gulir-x" style="border:1px solid #e3e9e5;border-radius:12px;background:#fff;">
      <table class="ro-mini" data-no-paginate style="font-size:.82rem;min-width:780px !important;">
        <thead><tr><th style="width:36px;">No</th><th>Pegawai (Pihak Pertama)</th><th>Pihak Kedua</th><th>Status</th><th class="num">Diajukan</th><th class="num">Ditandatangani</th><th class="num">Baris</th><th class="num"></th></tr></thead>
        <tbody>
          <?php foreach ($baris as $i => $pk): ?>
            <?php
            [$cls, $lbl] = $statusLbl[$pk['status'] ?? ''] ?? ['s-abu', (string) ($pk['status'] ?? '–')];
            $pid   = (int) ($pk['pegawai']['id'] ?? 0);
            $lewat = ($pk['status'] ?? '') === 'lewat_aksara';
            // PK di AKSARA: eKin tidak membawa pihak kedua & baris — diambil dari PK AKSARA terbaru orang itu.
            $pa    = $lewat ? (($pkAksara[$pid] ?? [])[0] ?? null) : null;
            $p2    = $pa !== null ? ['nama' => $pa['nama_2'] ?? '', 'jabatan' => $pa['jabatan_2'] ?? ''] : ($pk['pihak_kedua'] ?? null);
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= esc((string) ($pk['pegawai']['nama'] ?? '')) ?></strong>
                <?php if (! empty($pk['pegawai']['fiktif'])): ?><span class="ro-fiktif ms-1">FIKTIF</span><?php endif; ?>
                <div class="text-muted" style="font-size:.72rem;"><?= esc((string) ($pk['pegawai']['jabatan'] ?? '')) ?></div></td>
              <td><?php if (! empty($p2) && ($p2['nama'] ?? '') !== ''): ?><?= esc((string) $p2['nama']) ?>
                <div class="text-muted" style="font-size:.72rem;"><?= esc((string) ($p2['jabatan'] ?? '')) ?></div><?php else: ?><span class="text-muted">–</span><?php endif; ?></td>
              <td><span class="ro-chip <?= $cls ?>"><i class="ro-titik"></i><?= esc($lbl) ?></span>
                <?php if ($lewat && $pa !== null): ?>
                  <div class="text-muted" style="font-size:.7rem;"><?= esc($jenisPk[$pa['jenis']] ?? 'PK') ?><?= ! empty($pa['tanggal']) ? ' · ' . esc(date('d/m/Y', strtotime((string) $pa['tanggal']))) : '' ?><?= count($pkAksara[$pid]) > 1 ? ' (+' . (count($pkAksara[$pid]) - 1) . ' PK lain)' : '' ?></div>
                <?php elseif ($lewat): ?>
                  <div class="ro-pk-hilang" title="eKin mencatatnya sebagai pihak pertama PK AKSARA, tetapi AKSARA tidak menemukan PK <?= (int) $tahun ?> dengan pegawai ini sebagai pihak pertama."><i class="fas fa-triangle-exclamation me-1"></i>PK <?= (int) $tahun ?> tidak ditemukan di AKSARA</div>
                <?php endif; ?></td>
              <td class="num"><?= esc($tgl($pk['diajukan_pada'] ?? null)) ?></td>
              <td class="num"><?= esc($tgl($pk['ditandatangani_pada'] ?? null)) ?></td>
              <td class="num" <?= $pa !== null ? 'title="Indikator di PK AKSARA"' : '' ?>><?= $pa !== null ? (int) $pa['jml_indikator'] : (int) ($pk['jumlah_baris'] ?? 0) ?></td>
              <td class="num">
                <?php if ($lewat && $pid > 0): ?>
                  <?php /* PK pejabat struktural = dokumen PK AKSARA, ditampilkan di Ruang OPD ini (isi + Lihat/Cetak),
                           dicari lewat ID pegawai (id eKin = id AKSARA) — tanpa nama di alamat (tercatat di log server). */ ?>
                  <a class="btn btn-sm btn-outline-success py-0 px-2" href="<?= base_url($dasar . '/' . $pid . '?tahun=' . (int) $tahun) ?>" data-ro-tautan><i class="fas fa-file-signature me-1"></i>PK AKSARA</a>
                <?php elseif ($pid > 0 && ($pk['status'] ?? '') !== 'belum_ada_skp'): ?>
                  <a class="btn btn-sm btn-outline-secondary py-0 px-2" href="<?= base_url($dasar . '/' . $pid . '?tahun=' . (int) $tahun) ?>" data-ro-tautan><i class="fas fa-file-lines me-1"></i>Dokumen</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="ro-catatan mt-2"><i class="fas fa-circle-info me-1"></i>"PK di AKSARA" = pejabat struktural yang Perjanjian Kinerjanya disusun sebagai dokumen PK AKSARA (menu Perjanjian Kinerja);
      eKin tidak membuatnya ulang dan tidak mencatat tanda tangannya, jadi tidak dihitung sebagai "ditandatangani". Pihak kedua dan jumlah indikatornya dibaca dari PK AKSARA terbaru.</p>
  <?php endif; ?>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
