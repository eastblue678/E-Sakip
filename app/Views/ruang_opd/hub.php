<?php
/**
 * Ruang OPD — hub satu perangkat daerah (AKSARA+). Data: RuangOpdController::hub
 * (RuangOpdService::hub). Setiap kartu = satu dokumen SAKIP: status + ringkasan di
 * tempat + tombol "Buka" ke halaman lama yang BOLEH dibuka peran ini dengan OPD ini
 * sudah terpilih ($buka = RuangOpdTautan). Tanpa halaman yang cocok, ringkasan di
 * kartu itulah datanya (tidak ada tautan yang pasti ditolak).
 *
 * @var array    $opd
 * @var int      $tahun
 * @var array    $d        RuangOpdService::hub()
 * @var callable $buka     fn(item, ctx) => ['url','publik','baru'] | null
 * @var bool     $ekinAda
 */
use App\Services\RuangOpdService;

$sel  = $d['sel'];
$this->setVar('skor', $sel['_skor']);
$this->setVar('aktifTab', 'hub');
$this->setVar('shellCss', $shellCss);

$fmt = static fn ($v, int $des = 0) => number_format((float) $v, $des, ',', '.');
$chip = static fn (array $s) => '<span class="ro-chip s-' . esc($s['s'], 'attr') . '" title="' . esc($s['j'], 'attr') . '"><i class="ro-titik"></i>'
    . esc($s['t']) . '</span>';
/** Tombol ke halaman lama; null = tidak dirender. */
$tombol = static function (?array $t, string $label, string $ikon = 'fa-arrow-right', string $gaya = 'btn-success'): string {
    if ($t === null) {
        return '';
    }
    $atr = $t['baru'] ? ' target="_blank" rel="noopener"' : '';

    return '<a class="btn btn-sm ' . $gaya . '" href="' . esc(base_url($t['url'])) . '"' . $atr . ' data-ro-tautan>'
        . '<i class="fas ' . $ikon . ' me-1"></i>' . esc($label)
        . ($t['publik'] ? ' <span class="badge text-bg-light ms-1" title="Halaman publik e-SAKIP">publik</span>' : '') . '</a>';
};
$tanpaLayar = '<p class="ro-catatan mb-0"><i class="fas fa-circle-info me-1"></i>Tidak ada layar khusus untuk peran Anda — ringkasan di atas adalah datanya.</p>';
$jenisPk = ['bupati' => 'PK Bupati', 'jpt' => 'JPT', 'camat' => 'Camat', 'administrator' => 'Administrator', 'pengawas' => 'Pengawas'];
$rata = static function (array $keys) use ($sel): ?int {
    $n = ['hijau' => 1, 'kuning' => .5, 'merah' => 0];
    $x = [];
    foreach ($keys as $k) {
        if (isset($n[$sel[$k]['s']])) {
            $x[] = $n[$sel[$k]['s']];
        }
    }

    return $x === [] ? null : (int) round(array_sum($x) / count($x) * 100);
};
$tahapSkor = [
    'perencanaan' => $rata(['renstra', 'rkt', 'iku', 'cascading', 'pk']),
    'pengukuran'  => $rata(['renaksi', 'monev', 'ikp']),
    'pelaporan'   => $rata(['lakip']),
    'evaluasi'    => null,
    'pegawai'     => $rata(['ekin']),
];
$warnaSkor = static fn (?int $s) => $s === null ? 's-abu' : ($s >= 85 ? 's-hijau' : ($s >= 50 ? 's-kuning' : 's-merah'));
$lompat = [
    'renstra' => 'Renstra', 'rkt' => 'Renja/RKT', 'iku' => 'IKU', 'cascading' => 'Pohon Kinerja', 'pk' => 'Perjanjian Kinerja',
    'renaksi' => 'Rencana Aksi', 'monev' => 'MONEV', 'ikp' => 'IKP', 'lakip' => 'LAKIP', 'evaluasi' => 'Evaluasi', 'ekin' => 'Pegawai (eKin)',
];
?>
<?= $this->include('ruang_opd/_kepala') ?>

<!-- ================= SIKLUS SAKIP ================= -->
<div class="ro-siklus">
  <?php foreach (RuangOpdService::TAHAP as $k => [$lbl, $ikon]): ?>
    <?php $sk = $tahapSkor[$k]; ?>
    <a href="#tahap-<?= $k ?>" class="<?= $warnaSkor($sk) ?>">
      <span class="nm"><i class="fas <?= $ikon ?>"></i><?= esc($lbl) ?></span>
      <span class="pc d-block" style="color:var(--c)"><?= $sk === null ? '–' : $sk . '%' ?></span>
      <span class="ro-batang"><span class="h" style="width:<?= (int) ($sk ?? 0) ?>%;background:var(--c)"></span></span>
    </a>
  <?php endforeach; ?>
</div>

<nav class="ro-lompat ro-noprint" aria-label="Lompat ke dokumen">
  <?php foreach ($lompat as $k => $lbl): ?>
    <?php $s = $k === 'evaluasi' ? 'abu' : ($sel[$k]['s'] ?? 'abu'); ?>
    <a href="#<?= $k ?>" class="s-<?= $s ?>"><i class="ro-titik"></i><?= esc($lbl) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ======================================================= PERENCANAAN -->
<section class="ro-tahap" id="tahap-perencanaan">
  <div class="ro-tahap-kepala t-perencanaan">
    <div class="ic"><i class="fas fa-compass-drafting"></i></div>
    <div><h3>Perencanaan Kinerja</h3><p>Renstra, Renja/RKT, IKU, pohon kinerja sampai pelaksana, dan Perjanjian Kinerja <?= (int) $tahun ?>.</p></div>
  </div>
  <div class="ro-kisi">

    <!-- Renstra -->
    <article class="ro-dok" id="renstra">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-book"></i></div>
        <div><h4>Rencana Strategis (Renstra)</h4><p>Periode yang memuat tahun <?= (int) $tahun ?></p></div>
        <div class="kanan"><?= $chip($sel['renstra']) ?></div>
      </div>
      <?php $r = $d['renstra']; ?>
      <?php if ($r): ?>
        <div class="ro-angka">
          <div><b><?= (int) $r['awal'] ?>–<?= (int) $r['akhir'] ?></b><span>periode</span></div>
          <div><b><?= (int) $r['n'] ?></b><span>sasaran</span></div>
          <div><b><?= (int) $r['ind'] ?></b><span>indikator</span></div>
          <div><b><?= (int) $r['n'] - (int) $r['selesai'] ?></b><span>sasaran draf</span></div>
        </div>
        <div class="ro-gulir" style="max-height:190px;">
          <table class="ro-mini"><thead><tr><th>Sasaran</th><th class="num">Ind.</th><th class="num">Status</th></tr></thead><tbody>
            <?php foreach ($d['sasaranRenstra'] as $x): ?>
              <tr><td><?= esc($x['sasaran']) ?></td><td class="num"><?= (int) $x['ind'] ?></td>
                <td class="num"><span class="ro-chip <?= $x['status'] === 'selesai' ? 's-hijau' : 's-kuning' ?>"><?= $x['status'] === 'selesai' ? 'final' : 'draf' ?></span></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php else: ?>
        <p class="ro-catatan mb-0">Belum ada sasaran Renstra yang memuat tahun <?= (int) $tahun ?>.</p>
      <?php endif; ?>
      <div class="ro-aksi"><?= $tombol($buka('renstra'), 'Buka Renstra') ?: $tanpaLayar ?></div>
    </article>

    <!-- RKT -->
    <article class="ro-dok" id="rkt">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-calendar-days"></i></div>
        <div><h4>Renja / RKT <?= (int) $tahun ?></h4><p>Rencana Kinerja Tahunan — indikator × program</p></div>
        <div class="kanan"><?= $chip($sel['rkt']) ?></div>
      </div>
      <?php $r = $d['rkt']; ?>
      <div class="ro-angka">
        <div><b><?= (int) ($r['n'] ?? 0) ?></b><span>baris RKT</span></div>
        <div><b><?= (int) ($r['selesai'] ?? 0) ?></b><span>selesai</span></div>
        <div><b><?= (int) ($r['n'] ?? 0) - (int) ($r['selesai'] ?? 0) ?></b><span>draf</span></div>
      </div>
      <p class="ro-catatan mb-0"><?= esc($sel['rkt']['j']) ?></p>
      <div class="ro-aksi"><?= $tombol($buka('rkt'), $peran === 'admin_kab' || $peran === 'admin' ? 'Buka RKPD (turunan RKT)' : 'Buka Renja/RKT') ?: $tanpaLayar ?></div>
    </article>

    <!-- IKU -->
    <article class="ro-dok" id="iku">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-bullseye"></i></div>
        <div><h4>Indikator Kinerja Utama (IKU)</h4><p>IKU berjalan & target <?= (int) $tahun ?></p></div>
        <div class="kanan"><?= $chip($sel['iku']) ?></div>
      </div>
      <?php if ($d['iku'] !== []): ?>
        <div class="ro-gulir" style="max-height:220px;">
          <table class="ro-mini"><thead><tr><th>Indikator</th><th>Satuan</th><th class="num">Target <?= (int) $tahun ?></th></tr></thead><tbody>
            <?php foreach ($d['iku'] as $x): ?>
              <tr><td><?= esc($x['indikator']) ?></td><td><?= esc((string) $x['satuan']) ?></td>
                <td class="num"><?= ($x['target'] ?? '') !== '' ? esc((string) $x['target']) : '<span class="ro-chip s-kuning">belum</span>' ?></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php else: ?>
        <p class="ro-catatan mb-0">Belum ada IKU berjalan untuk tahun <?= (int) $tahun ?>.</p>
      <?php endif; ?>
      <div class="ro-aksi"><?= $tombol($buka('iku'), 'Buka IKU') ?: $tanpaLayar ?></div>
    </article>

    <!-- Pohon Kinerja & Cascading -->
    <article class="ro-dok" id="cascading">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-sitemap"></i></div>
        <?php
        // Label jenjang per jenis unit — sama dengan halaman Pemilik Kinerja yang dibuka tombol di bawah
        // (di kecamatan Camat = Eselon III, jadi simpul es3 = Eselon IV).
        $jj = RuangOpdService::labelJenjang(($opd['kelompok'] ?? '') === 'kecamatan');
        $lv = ['es3' => $jj['es3'], 'es4' => $jj['es4'], 'pelaksana' => $jj['pelaksana']];
        $c  = $d['cascading'];
        ?>
        <div><h4>Pohon Kinerja &amp; Cascading</h4><p><?= esc(implode(' → ', $lv)) ?>, berpemilik tahun <?= (int) $tahun ?></p></div>
        <div class="kanan"><?= $chip($sel['cascading']) ?></div>
      </div>
      <div class="ro-level">
        <?php foreach ($lv as $k => $lbl): ?>
          <?php $sp = (int) ($c[$k]['simpul'] ?? 0); $bp = (int) ($c[$k]['berpemilik'] ?? 0); $p = $sp > 0 ? round($bp / $sp * 100) : 0; ?>
          <span class="nm"><?= esc($lbl) ?></span>
          <span class="ro-batang" title="<?= $bp ?> dari <?= $sp ?> simpul berpemilik"><span class="h" style="width:<?= $p ?>%"></span></span>
          <span class="nl"><?= $bp ?>/<?= $sp ?> simpul</span>
        <?php endforeach; ?>
      </div>
      <p class="ro-catatan mb-0"><i class="fas fa-circle-info me-1"></i>Batang = simpul yang sudah punya pemilik (pegawai) tahun <?= (int) $tahun ?>.
        Indikator bertarget: <?= array_sum(array_column($c, 'bertarget')) ?> dari <?= array_sum(array_column($c, 'indikator')) ?>.</p>
      <div class="ro-aksi">
        <?= $tombol($buka('pohon'), 'Pohon Kinerja', 'fa-diagram-project') ?>
        <?= $tombol($buka('cascading'), 'Tabel Cascading', 'fa-table', 'btn-outline-success') ?>
        <?= $tombol($buka('pemilik'), 'Pemilik Kinerja', 'fa-user-check', 'btn-outline-success') ?>
      </div>
    </article>

    <!-- Perjanjian Kinerja -->
    <article class="ro-dok lebar" id="pk">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-file-signature"></i></div>
        <div><h4>Perjanjian Kinerja <?= (int) $tahun ?></h4><p>Semua jenjang: JPT/Camat, Administrator, Pengawas</p></div>
        <div class="kanan"><?= $chip($sel['pk']) ?></div>
      </div>
      <?php if ($d['pk'] !== []): ?>
        <div class="ro-gulir" style="max-height:320px;">
          <table class="ro-mini">
            <thead><tr><th>Jenis</th><th>Pihak Pertama</th><th>Tanggal</th><th class="num">Sasaran / Ind.</th><th class="num">Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($d['pk'] as $x): ?>
                <?php $ctx = ['jenis' => $x['jenis'], 'id' => $x['id']]; $tL = $buka('pk_lihat', $ctx); $tC = $buka('pk_cetak', $ctx); ?>
                <tr>
                  <td><span class="ro-chip polos"><?= esc($jenisPk[$x['jenis']] ?? $x['jenis']) ?></span></td>
                  <td>
                    <strong><?= esc((string) ($x['nama_1'] ?? '–')) ?></strong>
                    <?php if ($x['status_1'] !== ''): ?><span class="ro-chip s-kuning ms-1"><?= esc($x['status_1']) ?></span><?php endif; ?>
                    <div class="text-muted" style="font-size:.72rem;"><?= esc($x['jabatan_1']) ?></div>
                  </td>
                  <td class="text-nowrap"><?= $x['tanggal'] ? esc(date('d/m/Y', strtotime((string) $x['tanggal']))) : '–' ?></td>
                  <td class="num"><?= (int) $x['jml_sasaran'] ?> / <?= (int) $x['jml_indikator'] ?></td>
                  <td class="num text-nowrap">
                    <?php if ($tL): ?><a href="<?= esc(base_url($tL['url'])) ?>" class="btn btn-sm btn-outline-success py-0 px-2" data-ro-tautan title="Lihat"><i class="fas fa-eye"></i></a><?php endif; ?>
                    <?php if ($tC): ?><a href="<?= esc(base_url($tC['url'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary py-0 px-2" data-ro-tautan data-pdf title="Cetak PDF"><i class="fas fa-print"></i></a><?php endif; ?>
                    <?php if (! $tL && ! $tC): ?><span class="text-muted">–</span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="ro-catatan mb-0">Belum ada Perjanjian Kinerja tahun <?= (int) $tahun ?>.</p>
      <?php endif; ?>
      <div class="ro-aksi"><?= $tombol($buka('pk'), 'Daftar Perjanjian Kinerja', 'fa-list') ?: $tanpaLayar ?></div>
    </article>
  </div>
</section>

<!-- ======================================================= PENGUKURAN -->
<section class="ro-tahap" id="tahap-pengukuran">
  <div class="ro-tahap-kepala t-pengukuran">
    <div class="ic"><i class="fas fa-ruler-combined"></i></div>
    <div><h3>Pengukuran Kinerja</h3><p>Rencana aksi triwulan, monitoring capaiannya, dan Kinerja Prioritas bulanan.</p></div>
  </div>
  <div class="ro-kisi">
    <!-- Rencana Aksi -->
    <article class="ro-dok" id="renaksi">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-list-check"></i></div>
        <div><h4>Target &amp; Rencana Aksi</h4><p>Indikator PK <?= (int) $tahun ?> yang sudah punya rencana aksi triwulan</p></div>
        <div class="kanan"><?= $chip($sel['renaksi']) ?></div>
      </div>
      <?php $r = $d['renaksi']; ?>
      <div class="ro-angka">
        <div><b><?= (int) ($r['ind'] ?? 0) ?></b><span>indikator PK</span></div>
        <div><b><?= (int) ($r['ind_tr'] ?? 0) ?></b><span>ber-rencana aksi</span></div>
        <div><b><?= (int) ($r['tr'] ?? 0) ?></b><span>rencana aksi</span></div>
      </div>
      <?php if ($d['renaksiJenis'] !== []): ?>
        <table class="ro-mini"><thead><tr><th>Jenjang PK</th><th class="num">Ber-renaksi</th></tr></thead><tbody>
          <?php foreach (['jpt', 'camat', 'administrator', 'pengawas'] as $j): ?>
            <?php if (isset($d['renaksiJenis'][$j])): $x = $d['renaksiJenis'][$j]; ?>
              <tr><td><?= esc($jenisPk[$j]) ?></td><td class="num"><?= (int) $x['ind_tr'] ?> / <?= (int) $x['ind'] ?></td></tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody></table>
      <?php endif; ?>
      <div class="ro-aksi"><?= $tombol($buka('renaksi'), 'Buka Rencana Aksi') ?: $tanpaLayar ?></div>
    </article>

    <!-- MONEV -->
    <article class="ro-dok" id="monev">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-chart-line"></i></div>
        <div><h4>Monitoring Rencana Aksi (MONEV)</h4><p>Rencana aksi yang capaian triwulannya sudah diisi</p></div>
        <div class="kanan"><?= $chip($sel['monev']) ?></div>
      </div>
      <?php $tr = (int) ($r['tr'] ?? 0); ?>
      <div class="ro-tw-besar">
        <?php foreach ([1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'] as $k => $lbl): ?>
          <?php $isi = (int) ($r['tw' . $k] ?? 0); $p = $tr > 0 ? round($isi / $tr * 100) : 0; $wajib = $k <= $d['triwulanWajib']; ?>
          <div class="<?= ! $wajib ? 's-abu' : ($p >= 100 ? 's-hijau' : ($p > 0 ? 's-kuning' : 's-merah')) ?>">
            <span class="nm"><?= $lbl ?><?= $wajib ? '' : ' · belum jatuh tempo' ?></span>
            <b style="color:var(--c)"><?= $p ?>%</b>
            <span class="ro-batang"><span class="h" style="width:<?= $p ?>%;background:var(--c)"></span></span>
            <span class="ro-catatan"><?= $isi ?>/<?= $tr ?> renaksi</span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="ro-aksi"><?= $tombol($buka('monev'), 'Buka MONEV') ?: $tanpaLayar ?></div>
    </article>

    <!-- IKP -->
    <article class="ro-dok lebar" id="ikp">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-gauge-high"></i></div>
        <div><h4>Kinerja Prioritas (IKP)</h4>
          <p>Capaian kumulatif s.d. <?= $d['ikp'] && $d['ikp']['bulan'] > 0 ? esc(ikp_nama_bulan((int) $d['ikp']['bulan'])) : 'bulan lalu' ?> <?= (int) $tahun ?> — rumus yang sama dengan rekap IKP & Lampiran PK</p></div>
        <div class="kanan"><?= $chip($sel['ikp']) ?></div>
      </div>
      <?php $ik = $d['ikp']; ?>
      <?php if ($ik): ?>
        <div class="ro-angka">
          <div><b><?= (int) $ik['jumlah'] ?></b><span>IKP</span></div>
          <div><b style="color:<?= esc($ik['status']['color_hex'] ?? '#1b3325', 'attr') ?>"><?= $ik['rata'] === null ? '–' : esc(ikp_fmt($ik['rata'], 2)) . '%' ?></b><span>rata-rata capaian</span></div>
          <div><b><?= (int) $ik['sebaran']['hijau'] ?></b><span>tercapai</span></div>
          <div><b><?= (int) $ik['sebaran']['kuning'] ?></b><span>perlu perhatian</span></div>
          <div><b><?= (int) $ik['sebaran']['merah'] ?></b><span>kritis</span></div>
          <div><b><?= (int) $ik['sebaran']['abu'] ?></b><span>belum dinilai</span></div>
        </div>
        <?php
        $butir = $ik['butir'];
        usort($butir, static fn ($a, $b) => [$a['persen'] === null ? 1 : 0, $a['persen'] ?? 0] <=> [$b['persen'] === null ? 1 : 0, $b['persen'] ?? 0]);
        ?>
        <div class="ro-gulir" style="max-height:260px;">
          <table class="ro-mini"><thead><tr><th>IKP (capaian terendah dulu)</th><th>Program Unggulan</th><th class="num">Capaian</th></tr></thead><tbody>
            <?php foreach ($butir as $x): ?>
              <tr><td><?= esc($x['nama']) ?></td><td class="text-muted"><?= esc($x['pu'] ?: '–') ?></td>
                <td class="num"><span class="ro-chip s-<?= esc($x['status']['kelompok'], 'attr') ?>"><i class="ro-titik"></i><?= $x['persen'] === null ? esc($x['status']['name'] ?? 'belum') : esc(ikp_fmt($x['persen'], 1)) . '%' ?></span></td></tr>
            <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php else: ?>
        <p class="ro-catatan mb-0">Belum ada IKP terdaftar untuk perangkat daerah ini.</p>
      <?php endif; ?>
      <div class="ro-aksi">
        <?= $tombol($buka('ikp'), 'Buka rekap IKP') ?>
        <?= $tombol($buka('ikp_realisasi'), 'Isi realisasi bulanan', 'fa-pen-to-square', 'btn-outline-success') ?>
        <?= $buka('ikp') === null ? $tanpaLayar : '' ?>
      </div>
    </article>
  </div>
</section>

<!-- ======================================================= PELAPORAN & EVALUASI -->
<div class="ro-kisi">
  <section class="ro-tahap" id="tahap-pelaporan">
    <div class="ro-tahap-kepala t-pelaporan">
      <div class="ic"><i class="fas fa-file-lines"></i></div>
      <div><h3>Pelaporan Kinerja</h3><p>Laporan Kinerja (LAKIP) tahun ini dan tahun lalu.</p></div>
    </div>
    <article class="ro-dok" id="lakip">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-file-contract"></i></div>
        <div><h4>LAKIP</h4><p>Realisasi indikator & pengesahan</p></div>
        <div class="kanan"><?= $chip($sel['lakip']) ?></div>
      </div>
      <table class="ro-mini"><thead><tr><th>Tahun</th><th class="num">Baris selesai</th><th class="num">Pengesahan</th><th class="num"></th></tr></thead><tbody>
        <?php foreach ([$tahun, $tahun - 1] as $th): ?>
          <?php $x = $d['lakip'][$th] ?? null; $tL = $buka('lakip', ['tahun' => $th]); ?>
          <tr>
            <td><strong><?= (int) $th ?></strong></td>
            <td class="num"><?= $x ? (int) $x['selesai'] . ' / ' . (int) $x['n'] : '–' ?></td>
            <td class="num"><?= ($x['pengesahan'] ?? '') === 'disahkan' ? '<span class="ro-chip s-hijau"><i class="ro-titik"></i>disahkan</span>' : '<span class="ro-chip polos">' . esc((string) ($x['pengesahan'] ?? 'belum')) . '</span>' ?></td>
            <td class="num"><?php if ($tL): ?><a href="<?= esc(base_url($tL['url'])) ?>" class="btn btn-sm btn-outline-success py-0 px-2" data-ro-tautan>Buka</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
      <p class="ro-catatan mb-0"><?= esc($sel['lakip']['j']) ?></p>
    </article>
  </section>

  <section class="ro-tahap" id="tahap-evaluasi">
    <div class="ro-tahap-kepala t-evaluasi">
      <div class="ic"><i class="fas fa-clipboard-check"></i></div>
      <div><h3>Evaluasi</h3><p>Evaluasi akuntabilitas kinerja oleh Inspektorat.</p></div>
    </div>
    <article class="ro-dok" id="evaluasi">
      <div class="ro-dok-kepala">
        <div class="ic"><i class="fas fa-clipboard-check"></i></div>
        <div><h4>Evaluasi SAKIP (LHE)</h4><p>Nilai & rekomendasi Inspektorat</p></div>
        <div class="kanan"><span class="ro-chip s-abu"><i class="ro-titik"></i>belum ada data</span></div>
      </div>
      <p class="ro-catatan mb-0">Modul Evaluasi Inspektorat di AKSARA belum menyimpan nilai atau LHE per perangkat daerah.
        Begitu tersedia, nilai dan tindak lanjut rekomendasinya tampil di sini.</p>
      <div class="ro-aksi"><?= $tombol($buka('evaluasi'), 'Buka menu Evaluasi', 'fa-arrow-right', 'btn-outline-secondary') ?></div>
    </article>
  </section>
</div>

<!-- ======================================================= KINERJA PEGAWAI (eKin) -->
<section class="ro-tahap" id="tahap-pegawai">
  <div class="ro-tahap-kepala t-pegawai">
    <div class="ic"><i class="fas fa-users"></i></div>
    <div><h3>Kinerja Pegawai (eKin)</h3><p>SKP, kinerja harian & bulanan, dan PK pegawai — ditarik langsung dari eKin Internal Pringsewu.</p></div>
  </div>
  <article class="ro-dok lebar" id="ekin">
    <?php $e = $d['ekin']; ?>
    <?php if (! $ekinAda || ! is_array($e)): ?>
      <div class="ro-kosong">
        <div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
        <h5>Data eKin belum tersedia</h5>
        <p class="small mb-0"><?= esc($ekinPesan) ?> Dokumen SAKIP di atas tidak terpengaruh.</p>
      </div>
    <?php else: ?>
      <?php
      $pg = $e['pegawai']; $sk = $e['skp']; $bl = $e['bulanan']; $pr = $e['predikat']; $hr = $e['harian'];
      $pn = $e['penugasan']; $pp = $e['pk_pegawai']; $cs = $e['cascading'];
      $totPr = max(1, array_sum(array_map('intval', $pr)));
      // Bulan yang SUDAH dinilai (predikat_bulan), bukan bulan berjalan yang baru berisi draf — lihat RuangOpdService::ekinDinilai.
      $nl    = RuangOpdService::ekinDinilai($e);
      $blnNl = $nl['bulan'] > 0 ? ikp_nama_bulan($nl['bulan']) : '';
      $warnaPr = ['sangat_baik' => '#0a8f50', 'baik' => '#6eab11', 'butuh_perbaikan' => '#e5b12c', 'kurang' => '#e0843a', 'sangat_kurang' => '#c93c3c'];
      $labelPr = ['sangat_baik' => 'Sangat Baik', 'baik' => 'Baik', 'butuh_perbaikan' => 'Butuh Perbaikan', 'kurang' => 'Kurang', 'sangat_kurang' => 'Sangat Kurang'];
      ?>
      <?php if ((int) $pg['ber_skp'] === 0): ?>
        <?php /* MENGAPA tidak delapan kartu bernilai nol: unit yang baru dimuat sebagian ke eKin (mis. Setda: hanya Sekda)
                 terbaca seolah semua kinerjanya gagal. Cukup katakan apa adanya: pegawainya ada, SKP-nya belum. */ ?>
        <div class="ro-kosong">
          <div class="ic"><i class="fas fa-user-clock"></i></div>
          <h5>Belum ada SKP <?= (int) $tahun ?> di eKin</h5>
          <p class="small mb-0"><?= (int) $pg['total'] ?> pegawai perangkat daerah ini sudah dimuat di eKin (<?= (int) $pg['berakun'] ?> berakun), tetapi belum ada yang menyusun SKP <?= (int) $tahun ?>.
            <?= (int) $pp['lewat_aksara'] > 0 ? (int) $pp['lewat_aksara'] . ' pejabat struktural memakai dokumen PK AKSARA.' : '' ?></p>
        </div>
      <?php else: ?>
      <div class="ro-dok-kepala">
        <div class="ic" style="background:#f1eefa;color:#5b4a8a;"><i class="fas fa-id-badge"></i></div>
        <div><h4>Ringkasan pegawai <?= (int) $tahun ?></h4><p>Angka hidup dari eKin (tembolok 5 menit)</p></div>
        <div class="kanan"><?= $chip($sel['ekin']) ?></div>
      </div>
      <div class="ro-pegawai">
        <div><span class="nm">Pegawai</span><b><?= (int) $pg['total'] ?></b>
          <small><?= (int) $pg['berakun'] ?> berakun · <?= (int) $pg['ber_skp'] ?> ber-SKP<?= (int) $pg['fiktif'] > 0 ? ' · ' . (int) $pg['fiktif'] . ' fiktif' : '' ?></small></div>
        <div><span class="nm">SKP tahunan</span><b><?= (int) $sk['disetujui'] ?></b>
          <small>disetujui · <?= (int) $sk['diajukan'] ?> diajukan · <?= (int) $sk['draf'] ?> draf</small></div>
        <div><span class="nm">SKP bulanan dinilai<?= $blnNl !== '' ? ' ' . esc($blnNl) : '' ?></span><b><?= (int) $nl['dinilai'] ?>/<?= (int) $pg['ber_skp'] ?></b>
          <small>pegawai ber-SKP<?php if ((int) ($bl['bulan'] ?? 0) > 0 && (int) $bl['bulan'] !== $nl['bulan']): ?>
            · <?= esc(ikp_nama_bulan((int) $bl['bulan'])) ?> berjalan: <?= (int) ($bl['diajukan'] ?? 0) ?> diajukan · <?= (int) ($bl['draf'] ?? 0) ?> draf · <?= (int) ($bl['belum'] ?? 0) ?> belum<?php endif; ?></small></div>
        <div><span class="nm">Predikat<?= $blnNl !== '' ? ' ' . esc($blnNl) : ' bulanan' ?></span>
          <div class="ro-predikat" title="Sebaran predikat SKP bulanan<?= $blnNl !== '' ? ' ' . esc($blnNl, 'attr') : '' ?>">
            <?php foreach ($warnaPr as $k => $w): ?>
              <span style="width:<?= round((int) ($pr[$k] ?? 0) / $totPr * 100, 1) ?>%;background:<?= $w ?>" title="<?= esc($labelPr[$k]) ?>: <?= (int) ($pr[$k] ?? 0) ?>"></span>
            <?php endforeach; ?>
          </div>
          <small class="mt-1"><?php foreach ($warnaPr as $k => $w): ?><?php if ((int) ($pr[$k] ?? 0) > 0): ?><span class="me-2"><i class="ro-titik" style="background:<?= $w ?>"></i> <?= esc($labelPr[$k]) ?> <?= (int) $pr[$k] ?></span><?php endif; ?><?php endforeach; ?></small></div>
        <div><span class="nm">Kinerja harian</span><b><?= (int) $hr['bulan_ini_disetujui'] ?></b>
          <small>disetujui bulan ini · <?= (int) $hr['menunggu'] ?> menunggu</small></div>
        <div><span class="nm">Penugasan tambahan</span><b><?= (int) $pn['diterima'] ?></b>
          <small>diterima · <?= (int) $pn['menunggu'] ?> menunggu</small></div>
        <?php /* "lewat AKSARA" = pejabat struktural yang PK-nya dokumen PK AKSARA; AKSARA tidak menyimpan status tanda
                 tangan, jadi TIDAK dijumlahkan ke "ditandatangani". */ ?>
        <div><span class="nm">PK pegawai ditandatangani</span><b><?= (int) $pp['ditandatangani'] ?></b>
          <small>di eKin · <?= (int) $pp['diajukan'] ?> diajukan · <?= (int) $pp['draf'] ?> draf<?= (int) $pp['dikembalikan'] > 0 ? ' · ' . (int) $pp['dikembalikan'] . ' dikembalikan' : '' ?><?= (int) $pp['lewat_aksara'] > 0 ? ' · ' . (int) $pp['lewat_aksara'] . ' pejabat memakai PK AKSARA' : '' ?><?php
          // Kunci tambahan eKin: pegawai yang peran PK AKSARA-nya belum pernah diselaraskan (status masih dugaan dari SKP).
          $belumSelaras = (int) ($e['pk_aksara']['belum_diperiksa'] ?? 0);
          if ($belumSelaras > 0): ?> · <span class="text-warning-emphasis" title="Peran PK AKSARA pegawai ini belum pernah diselaraskan eKin (ekin:sinkron-pk-aksara); angkanya masih dugaan dari SKP"><?= $belumSelaras ?> belum diselaraskan dengan AKSARA</span><?php endif; ?></small></div>
        <div><span class="nm">Cascading pegawai</span><b><?= (int) $cs['porsi_lengkap'] ?>/<?= (int) $cs['rhk_ber_bawahan'] ?></b>
          <small>RHK berbawahan yang porsinya lengkap · <?= (int) $cs['porsi_kurang'] ?> kurang</small></div>
      </div>
      <?php endif; /* ber_skp */ ?>
    <?php endif; ?>
    <div class="ro-aksi">
      <a class="btn btn-sm" style="background:#5b4a8a;color:#fff;" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/cascading-pegawai?tahun=' . (int) $tahun) ?>" data-ro-tautan><i class="fas fa-diagram-project me-1"></i>Cascading Pegawai</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/pk-pegawai?tahun=' . (int) $tahun) ?>" data-ro-tautan><i class="fas fa-file-signature me-1"></i>PK Pegawai</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/rencana-aksi-pegawai?tahun=' . (int) $tahun) ?>" data-ro-tautan><i class="fas fa-list-check me-1"></i>Rencana Aksi Pegawai</a>
    </div>
  </article>
</section>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
