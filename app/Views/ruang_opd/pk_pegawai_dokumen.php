<?php
/**
 * Ruang OPD — dokumen PK satu pegawai, baca-saja (AKSARA+). Data: eKin endpoint
 * pk-pegawai/{pegawai_id}. Kertasnya SAMA dengan dokumen eKin (ruang_opd/_pk_pegawai_kertas):
 * lembar 1 pernyataan & tanda tangan, lembar 2 lampiran target bulanan. Tombol Cetak membuka
 * halaman cetak mandiri (pk_pegawai_cetak: A4 potret + A4 lanskap, tanpa bingkai aplikasi).
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
$jenisPk = ['bupati' => 'PK Bupati', 'jpt' => 'PK JPT', 'camat' => 'PK Camat', 'administrator' => 'PK Administrator', 'pengawas' => 'PK Pengawas'];
$lewatAksara = is_array($dok) && ($dok['status'] ?? '') === 'lewat_aksara';
?>
<?= $this->include('ruang_opd/_kepala') ?>
<?php $cssPkd = FCPATH . 'assets/css/pk_pegawai_dokumen.css'; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pk_pegawai_dokumen.css') ?>?v=<?= is_file($cssPkd) ? filemtime($cssPkd) : '1' ?>">
<?php /* Ctrl+P di halaman ini: cukup kertasnya (tombol Cetak membuka halaman cetak mandiri). */ ?>
<style>@media print { .ro-hero { display: none !important; } }</style>

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
  $belumSkp = ($dok['status'] ?? '') === 'belum_ada_skp';
  ?>
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3 ro-noprint">
    <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/pk-pegawai?tahun=' . (int) $tahun) ?>"><i class="fas fa-arrow-left me-1"></i>Daftar PK pegawai</a>
    <?php if ($lewatAksara || $belumSkp): /* status PK eKin tampil di alur di bawah */ ?>
      <span class="ro-chip <?= $cls ?>"><i class="ro-titik"></i><?= esc($lbl) ?></span>
    <?php endif; ?>
    <?php if (! $lewatAksara && ! $belumSkp): ?>
      <a class="btn btn-sm btn-success ms-auto" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/pk-pegawai/' . (int) ($p1['id'] ?? 0) . '/cetak?tahun=' . (int) $tahun) ?>" target="_blank" rel="noopener"><i class="fas fa-print me-1"></i>Cetak</a>
    <?php endif; ?>
  </div>
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
  <?php elseif ($belumSkp): ?>
    <div class="ro-kosong">
      <div class="ic"><i class="fas fa-file-circle-question"></i></div>
      <h5>Belum ada SKP <?= (int) $tahun ?></h5>
      <p class="small mb-0">PK pegawai disusun eKin dari SKP dan rencana aksi. <?= esc((string) $p1['nama']) ?> belum memiliki SKP <?= (int) $tahun ?> di eKin, jadi dokumennya belum ada.</p>
    </div>
  <?php else: ?>
    <?php
    // Panel status = kartu status halaman PK pegawai eKin (pk_pegawai/lihat): alur, chip, pihak kedua & waktu, catatan.
    $st     = (string) ($dok['status'] ?? '');
    $posisi = ['draf' => 0, 'dikembalikan' => 0, 'diajukan' => 1, 'ditandatangani' => 2][$st] ?? -1;
    $beku   = in_array($st, ['diajukan', 'ditandatangani'], true);
    $chip   = ['draf' => ['c-abu', 'Draf'], 'diajukan' => ['c-kuning', 'Menunggu tanda tangan PPK'], 'ditandatangani' => ['c-hijau', 'Ditandatangani'],
        'dikembalikan' => ['c-oranye', 'Dikembalikan']][$st] ?? ['c-abu', $st];
    $bulanSingkat = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $waktu  = static function ($v) use ($bulanSingkat): ?string {
        $ts = $v ? strtotime((string) $v) : false;

        return $ts === false ? null : (int) date('j', $ts) . ' ' . $bulanSingkat[(int) date('n', $ts)] . ' ' . date('Y H.i', $ts);
    };
    ?>
    <div class="pkd-status ro-noprint">
      <ol class="pkd-alur" aria-label="Alur Perjanjian Kinerja">
        <?php foreach ([['draf', 'Draf'], ['diajukan', 'Diajukan'], ['ditandatangani', 'Ditandatangani']] as $k => [$kode, $label]): ?>
          <?php $balik = $st === 'dikembalikan' && $k === 0; ?>
          <li class="<?= $balik ? 'balik' : ($k < $posisi ? 'sudah' : ($k === $posisi ? 'kini' : '')) ?>"<?= $k === $posisi ? ' aria-current="step"' : '' ?>>
            <i class="fas <?= $k < $posisi ? 'fa-circle-check' : ($k === $posisi ? 'fa-circle-dot' : 'fa-circle') ?>" aria-hidden="true"></i><?= esc($balik ? 'Dikembalikan — perbaiki & ajukan lagi' : $label) ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="baris">
        <span class="pkd-chip <?= $chip[0] ?>"><?= esc($chip[1]) ?></span>
        <span class="ket">
          Pihak kedua (Pejabat Penilai): <strong><?= esc((string) (($p2['nama'] ?? '') ?: 'belum diatur')) ?></strong>
          <?php if ($beku && $waktu($dok['diajukan_pada'] ?? null)): ?> · diajukan <?= esc($waktu($dok['diajukan_pada'])) ?><?php endif; ?>
          <?php if ($st === 'ditandatangani' && $waktu($dok['ditandatangani_pada'] ?? null)): ?> · ditandatangani <?= esc($waktu($dok['ditandatangani_pada'])) ?><?php endif; ?>
        </span>
      </div>
      <?php if ($st === 'dikembalikan' && ! empty($dok['catatan'])): ?>
        <div class="alert alert-warning small" role="status"><i class="fas fa-rotate-left me-1"></i><strong>Catatan Pejabat Penilai:</strong> <?= esc((string) $dok['catatan']) ?></div>
      <?php endif; ?>
      <p class="ket mb-0"><i class="fas fa-circle-info me-1"></i><?= $beku
          ? 'Dokumen dari eKin, sama dengan yang dilihat pegawai dan Pejabat Penilainya. Isinya versi yang ' . ($st === 'ditandatangani' ? 'disepakati' : 'diajukan') . '.'
          : 'Pratinjau hidup dari eKin: sasaran, indikator, dan target bulanan dibaca langsung dari SKP &amp; rencana aksi. Saat diajukan, isinya dibekukan.' ?></p>
    </div>
    <?= view('ruang_opd/_pk_pegawai_kertas', ['dok' => $dok, 'cetak' => false, 'tahun' => $tahun, 'opdNama' => $opd['nama_tampil']]) ?>
  <?php endif; /* lewatAksara */ ?>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
