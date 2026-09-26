<?php
/**
 * Ruang OPD — matriks kelengkapan seluruh perangkat daerah (AKSARA+).
 * Data: RuangOpdController::index (RuangOpdService::matriks).
 *
 * Satu baris = satu OPD; satu sel = satu dokumen SAKIP dengan status warna & angka
 * hidup tahun terpilih. Klik nama OPD = buka Ruang OPD-nya; klik sel = langsung ke
 * bagian dokumen itu di Ruang OPD. Penyaringan (cari, kelompok, kolom bermasalah,
 * urutan) berjalan di peramban — datanya sudah lengkap di halaman.
 *
 * @var int   $tahun
 * @var int[] $tahunList
 * @var array $daftar   RuangOpdService::daftarOpd()
 * @var array $kepala   [opd_id => kepala]
 * @var array $matriks  [opd_id => [kolom => sel, '_skor' => ?int]]
 * @var array $ringkas  [kolom => [hijau,kuning,merah,abu]]
 */
use App\Services\RuangOpdService;

$kolom   = RuangOpdService::KOLOM;
$tahapKol = [];
foreach ($kolom as $k => [$lbl, $tahap]) {
    $tahapKol[$tahap][] = $k;
}
$kelompokLabel = RuangOpdService::KELOMPOK;
$hitungKel = array_count_values(array_column($daftar, 'kelompok'));
$warnaSkor = static fn (?int $s) => $s === null ? 's-abu' : ($s >= 85 ? 's-hijau' : ($s >= 50 ? 's-kuning' : 's-merah'));
$rataSkor  = array_values(array_filter(array_map(static fn ($m) => $m['_skor'], $matriks), static fn ($v) => $v !== null));
$rata      = $rataSkor === [] ? null : (int) round(array_sum($rataSkor) / count($rataSkor));
$this->setVar('shellCss', $shellCss);
?>
<?= $this->include('templates/shell_atas') ?>

<div class="ro">
  <div class="ro-hero mb-3">
    <div class="ic"><i class="fas fa-building-columns"></i></div>
    <div class="isi">
      <h2>Ruang Perangkat Daerah</h2>
      <p>Satu pintu untuk seluruh dokumen dan kinerja tiap perangkat daerah — pilih satu OPD, semua dokumennya ada di sana.</p>
      <p class="mt-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="ro-lencana"><i class="fas fa-eye"></i> Hanya baca</span>
        <span class="ro-lencana"><i class="fas fa-building"></i> <?= count($daftar) ?> unit</span>
        <?php if ($ekinAda && $ekinWaktu): ?>
          <span class="ro-lencana" title="Waktu data eKin diperbarui"><i class="fas fa-users"></i> eKin <?= esc(date('d/m H:i', strtotime((string) $ekinWaktu))) ?></span>
        <?php endif; ?>
      </p>
    </div>
    <div class="kanan">
      <nav class="ro-tahun" aria-label="Pilih tahun">
        <?php foreach ($tahunList as $t): ?>
          <a href="<?= base_url('ruang-opd?tahun=' . (int) $t) ?>" class="<?= (int) $t === $tahun ? 'aktif' : '' ?>"<?= (int) $t === $tahun ? ' aria-current="true"' : '' ?>><?= (int) $t ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if ($rata !== null): ?>
        <div class="ro-cincin" style="--p: <?= $rata ?>;" title="Rata-rata skor kelengkapan seluruh unit">
          <span><?= $rata ?>%<small>rata-rata</small></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (! $ekinAda): ?>
    <div class="alert alert-light border d-flex gap-2 align-items-start small mb-3" role="status">
      <i class="fas fa-circle-info text-secondary mt-1"></i>
      <div><strong>Kolom Pegawai (eKin) kosong:</strong> <?= esc($ekinPesan) ?> Dokumen SAKIP lainnya tetap lengkap.</div>
    </div>
  <?php endif; ?>

  <!-- ================= RINGKASAN KABUPATEN PER DOKUMEN ================= -->
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
    <div>
      <h3 class="h6 fw-bold mb-0" style="color:#16321f;">Kelengkapan dokumen <?= (int) $tahun ?> se-Kabupaten</h3>
      <p class="small text-muted mb-0">Klik satu dokumen untuk menampilkan hanya unit yang belum lengkap pada dokumen itu.</p>
    </div>
    <div class="ro-legenda">
      <span><i class="ro-titik s-hijau"></i>lengkap</span>
      <span><i class="ro-titik s-kuning"></i>sebagian</span>
      <span><i class="ro-titik s-merah"></i>belum ada</span>
      <span><i class="ro-titik s-abu"></i>tidak berlaku / belum jatuh tempo</span>
    </div>
  </div>
  <div class="ro-ringkas mb-3" id="ro-ringkas">
    <?php foreach ($kolom as $k => [$lbl, $tahap, $ikon, $jelas]): ?>
      <?php $r = $ringkas[$k]; $tot = max(1, array_sum($r)); ?>
      <button type="button" data-kolom="<?= esc($k, 'attr') ?>" title="<?= esc($jelas, 'attr') ?>">
        <span class="lbl"><i class="fas <?= $ikon ?>"></i><?= esc($lbl) ?></span>
        <span class="ang d-block"><?= (int) $r['hijau'] ?><small> / <?= (int) ($r['hijau'] + $r['kuning'] + $r['merah']) ?> lengkap</small></span>
        <span class="ro-batang" aria-hidden="true">
          <span class="h" style="width:<?= round($r['hijau'] / $tot * 100, 1) ?>%"></span>
          <span class="k" style="width:<?= round($r['kuning'] / $tot * 100, 1) ?>%"></span>
          <span class="m" style="width:<?= round($r['merah'] / $tot * 100, 1) ?>%"></span>
          <span class="a" style="width:<?= round($r['abu'] / $tot * 100, 1) ?>%"></span>
        </span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- ================= ALAT ================= -->
  <div class="ro-alat mb-3">
    <label class="ro-cari mb-0">
      <i class="fas fa-magnifying-glass"></i>
      <input type="search" id="ro-cari" class="form-control" placeholder="Cari OPD atau kepala… (mis. diskominfo, dinkes, camat)" autocomplete="off" aria-label="Cari perangkat daerah">
    </label>
    <div class="ro-pil" id="ro-kelompok" role="group" aria-label="Kelompok">
      <button type="button" class="aktif" data-kelompok="">Semua<span class="n"><?= count($daftar) ?></span></button>
      <?php foreach ($kelompokLabel as $k => $lbl): ?>
        <?php if (($hitungKel[$k] ?? 0) > 0): ?>
          <button type="button" data-kelompok="<?= $k ?>"><?= esc($k === 'pd' ? 'Sekretariat/Badan/Dinas' : $lbl) ?><span class="n"><?= (int) $hitungKel[$k] ?></span></button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <select id="ro-urut" class="form-select form-select-sm" style="width:auto;" data-no-select2 aria-label="Urutkan">
      <option value="asal">Urut: kelompok &amp; nama</option>
      <option value="skor-naik">Urut: skor terendah dulu</option>
      <option value="skor-turun">Urut: skor tertinggi dulu</option>
    </select>
  </div>

  <!-- ================= MATRIKS ================= -->
  <div class="ro-bungkus">
    <table class="ro-matriks" id="ro-matriks" data-no-paginate>
      <thead>
        <tr class="tahap">
          <th class="kol-opd" rowspan="1" style="background:#0b7a44;">Tahun <?= (int) $tahun ?></th>
          <?php foreach ($tahapKol as $tahap => $ks): ?>
            <th colspan="<?= count($ks) ?>" class="t-<?= $tahap ?>"><?= esc(RuangOpdService::TAHAP[$tahap][0]) ?></th>
          <?php endforeach; ?>
        </tr>
        <tr class="kolom">
          <th class="kol-opd">Perangkat Daerah</th>
          <?php foreach ($tahapKol as $ks): ?>
            <?php foreach ($ks as $k): ?>
              <th title="<?= esc($kolom[$k][3], 'attr') ?>"><i class="fas <?= $kolom[$k][2] ?>"></i><?= esc($kolom[$k][0]) ?></th>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php $kelSebelum = null; $no = 0; ?>
        <?php foreach ($daftar as $o): ?>
          <?php
          $id  = (int) $o['id'];
          $m   = $matriks[$id] ?? [];
          $kep = $kepala[$id] ?? null;
          $hub = base_url('ruang-opd/' . $id . '?tahun=' . $tahun);
          $cari = mb_strtolower($o['nama_tampil'] . ' ' . $o['nama_opd'] . ' ' . $o['sebutan'] . ' ' . ($kep['nama'] ?? '') . ' ' . ($kep['jabatan'] ?? ''));
          $status = [];
          foreach (array_keys($kolom) as $k) {
              $status[] = $k . ':' . ($m[$k]['s'] ?? 'abu');
          }
          ?>
          <?php if ($o['kelompok'] !== $kelSebelum): $kelSebelum = $o['kelompok']; ?>
            <tr class="ro-grup" data-grup="<?= esc($o['kelompok'], 'attr') ?>"><td colspan="<?= count($kolom) + 1 ?>"><?= esc($kelompokLabel[$o['kelompok']]) ?></td></tr>
          <?php endif; ?>
          <tr class="ro-baris" data-kelompok="<?= esc($o['kelompok'], 'attr') ?>" data-cari="<?= esc($cari, 'attr') ?>"
              data-skor="<?= $m['_skor'] ?? -1 ?>" data-asal="<?= ++$no ?>" data-status="<?= esc(implode(' ', $status), 'attr') ?>">
            <td class="kol-opd">
              <div class="ro-opd-baris">
                <div class="ro-min0">
                  <a class="ro-opd-nama" href="<?= $hub ?>"><?= esc($o['nama_tampil']) ?></a>
                  <?php if ($kep): ?>
                    <span class="ro-opd-kepala" title="<?= esc(trim($kep['status'] . ' ' . $kep['jabatan']), 'attr') ?>"><?= esc(trim($kep['status'] . ' ' . $kep['nama'])) ?></span>
                  <?php else: ?>
                    <span class="ro-opd-kepala">Kepala belum tercatat di PK <?= (int) $tahun ?></span>
                  <?php endif; ?>
                </div>
                <span class="ro-skor <?= $warnaSkor($m['_skor'] ?? null) ?>" title="Skor kelengkapan dokumen"><?= ($m['_skor'] ?? null) === null ? '–' : (int) $m['_skor'] . '%' ?></span>
              </div>
            </td>
            <?php foreach ($tahapKol as $ks): ?>
              <?php foreach ($ks as $k): ?>
                <?php $s = $m[$k] ?? ['s' => 'abu', 't' => '–', 'k' => '', 'j' => '']; ?>
                <td data-label="<?= esc($kolom[$k][0], 'attr') ?>">
                  <a class="ro-sel s-<?= esc($s['s'], 'attr') ?>" href="<?= $hub ?>#<?= $k ?>" title="<?= esc($kolom[$k][0] . ': ' . ($s['j'] ?: $s['t']), 'attr') ?>">
                    <b><i class="ro-titik"></i><?= esc($s['t']) ?></b>
                    <?php if ($k === 'monev' && isset($s['tw'])): ?>
                      <span class="ro-tw" aria-hidden="true">
                        <?php foreach ($s['tw'] as $p): ?><span><i style="width:<?= round($p * 100) ?>%"></i></span><?php endforeach; ?>
                      </span>
                    <?php endif; ?>
                    <?php if ($s['k'] !== ''): ?><small><?= esc($s['k']) ?></small><?php endif; ?>
                  </a>
                </td>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="ro-kosong-cari" id="ro-kosong"><i class="fas fa-magnifying-glass me-1"></i>Tidak ada unit yang cocok dengan saringan ini.</div>
  </div>
  <p class="ro-catatan mt-2"><i class="fas fa-circle-info me-1"></i>
    Skor kelengkapan = rata-rata sel berwarna (lengkap 1, sebagian ½, belum ada 0); sel abu tidak dihitung.
    Kepala menurut Perjanjian Kinerja JPT/Camat <?= (int) $tahun ?> (termasuk Plt./Plh.).</p>
</div>

<script>
  (function () {
    var tabel = document.getElementById('ro-matriks');
    if (!tabel) return;
    var tbody = tabel.tBodies[0];
    var baris = Array.prototype.slice.call(tbody.querySelectorAll('tr.ro-baris'));
    var grup = Array.prototype.slice.call(tbody.querySelectorAll('tr.ro-grup'));
    var cari = document.getElementById('ro-cari');
    var kosong = document.getElementById('ro-kosong');
    var saring = { q: '', kelompok: '', kolom: '' };

    function terapkan() {
      var q = saring.q.trim().toLowerCase().split(/\s+/).filter(Boolean);
      var tampil = 0, perGrup = {};
      baris.forEach(function (tr) {
        var ok = (!saring.kelompok || tr.dataset.kelompok === saring.kelompok)
          && q.every(function (k) { return tr.dataset.cari.indexOf(k) !== -1; })
          && (!saring.kolom || new RegExp('(^| )' + saring.kolom + ':(merah|kuning)( |$)').test(tr.dataset.status));
        tr.style.display = ok ? '' : 'none';
        if (ok) { tampil++; perGrup[tr.dataset.kelompok] = true; }
      });
      var urut = document.getElementById('ro-urut').value;
      grup.forEach(function (g) { g.style.display = (urut === 'asal' && perGrup[g.dataset.grup]) ? '' : 'none'; });
      kosong.style.display = tampil ? 'none' : 'block';
    }

    function urutkan() {
      var mode = document.getElementById('ro-urut').value;
      var susun = baris.slice();
      if (mode === 'asal') {
        // Kembalikan urutan asli beserta judul kelompoknya.
        asli.forEach(function (x) { tbody.appendChild(x); });
      } else {
        var arah = mode === 'skor-naik' ? 1 : -1;
        susun.sort(function (a, b) {
          var sa = +a.dataset.skor, sb = +b.dataset.skor;
          if (sa < 0) sa = arah > 0 ? 999 : -999;
          if (sb < 0) sb = arah > 0 ? 999 : -999;
          return (sa - sb) * arah || (+a.dataset.asal - +b.dataset.asal);
        });
        susun.forEach(function (tr) { tbody.appendChild(tr); });
      }
      terapkan();
    }

    // Susunan asal dicatat sebelum baris dipindah-pindah.
    var asli = Array.prototype.slice.call(tbody.children);

    cari.addEventListener('input', function () { saring.q = cari.value; terapkan(); });
    document.getElementById('ro-urut').addEventListener('change', urutkan);
    document.querySelectorAll('#ro-kelompok button').forEach(function (b) {
      b.addEventListener('click', function () {
        document.querySelectorAll('#ro-kelompok button').forEach(function (x) { x.classList.remove('aktif'); });
        b.classList.add('aktif');
        saring.kelompok = b.dataset.kelompok;
        terapkan();
      });
    });
    document.querySelectorAll('#ro-ringkas button').forEach(function (b) {
      b.addEventListener('click', function () {
        var aktif = b.classList.contains('aktif');
        document.querySelectorAll('#ro-ringkas button').forEach(function (x) { x.classList.remove('aktif'); });
        saring.kolom = aktif ? '' : b.dataset.kolom;
        if (!aktif) b.classList.add('aktif');
        terapkan();
      });
    });
    // "/" memfokuskan pencarian (kebiasaan aplikasi besar); Esc mengosongkan.
    document.addEventListener('keydown', function (e) {
      if (e.key === '/' && document.activeElement !== cari && !/input|textarea|select/i.test(document.activeElement.tagName)) { e.preventDefault(); cari.focus(); }
      if (e.key === 'Escape' && document.activeElement === cari) { cari.value = ''; saring.q = ''; terapkan(); }
    });
  })();
</script>

<?= $this->include('templates/shell_bawah') ?>
