<?php
/**
 * Ruang OPD — Cascading Pegawai (AKSARA+). Data: eKin endpoint opd/{id}/cascading,
 * disusun EkinClient::bangunPohon(): simpul = RHK pegawai, anak = RHK bawahan yang
 * rhk_atasan_id-nya menunjuk simpul itu. Target yang tampil = IKI aspek kuantitas;
 * "porsi" = apakah target atasan sudah habis terbagi ke bawahan (dihitung eKin).
 *
 * @var array|null $pohon     ['akar','pegawai','jumlah'] atau null bila eKin tak tersedia
 * @var string     $ekinPesan
 */
use App\Services\EkinClient;

$this->setVar('aktifTab', 'cascading');
$this->setVar('shellCss', $shellCss);

$porsiLabel = [
    'lengkap'       => ['s-hijau', 'Porsi lengkap'],
    'kurang'        => ['s-kuning', 'Porsi kurang'],
    'lebih'         => ['s-merah', 'Porsi berlebih'],
    // eKin (CascadingService::statusPorsi) hanya menjumlah kontribusi yang satuan DAN metodenya sama.
    'beda_satuan'   => ['s-abu', 'Beda satuan/metode'],
    'tanpa_bawahan' => ['s-abu', 'Tanpa bawahan'],
];
$angka = static function ($v): string {
    if ($v === null || $v === '') {
        return '–';
    }
    $f = (float) $v;

    return number_format($f, floor($f) == $f ? 0 : 2, ',', '.');
};
$inisial = static function (string $nama): string {
    $nama = trim(preg_replace('/^\[FIKTIF\]\s*/i', '', $nama));
    $k    = preg_split('/\s+/', $nama) ?: [];

    return mb_strtoupper(mb_substr($k[0] ?? '?', 0, 1) . mb_substr($k[1] ?? '', 0, 1));
};
$pegawai = $pohon['pegawai'] ?? [];

$render = static function (array $n, int $dalam) use (&$render, $pegawai, $porsiLabel, $angka, $inisial): string {
    $p     = $pegawai[(int) ($n['pegawai_id'] ?? 0)] ?? ['nama' => 'Pegawai #' . ($n['pegawai_id'] ?? '?'), 'jabatan' => '', 'jenis_jabatan' => '', 'fiktif' => false];
    $iki   = EkinClient::ikiKuantitas($n);
    $porsi = $n['porsi'] ?? [];
    $st    = (string) ($porsi['status'] ?? '');
    [$cls, $lbl] = $porsiLabel[$st] ?? ['s-abu', $st !== '' ? $st : 'porsi ?'];
    $jenjang = EkinClient::peringkatJabatan((string) ($p['jenis_jabatan'] ?? ''));
    $cari  = mb_strtolower(($p['nama'] ?? '') . ' ' . ($p['jabatan'] ?? '') . ' ' . ($n['rumusan'] ?? ''));

    $kartu = '<div class="ro-rhk">'
        . '<span class="av j' . $jenjang . '" aria-hidden="true">' . esc($inisial((string) ($p['nama'] ?? ''))) . '</span>'
        . '<div class="isi">'
        . '<div class="org"><b>' . esc((string) ($p['nama'] ?? '')) . '</b>'
        . (! empty($p['fiktif']) ? ' <span class="ro-fiktif">FIKTIF</span>' : '')
        . ' · ' . esc((string) ($p['jabatan'] ?? '')) . '</div>'
        . '<div class="rum">' . esc((string) ($n['rumusan'] ?? '')) . '</div>'
        . '<div class="meta">'
        . '<span class="ro-chip polos">' . (($n['jenis'] ?? '') === 'tambahan' ? 'RHK tambahan' : 'RHK utama') . '</span>'
        . ($iki ? '<span><i class="fas fa-bullseye me-1" style="color:#7a67b0"></i>' . esc((string) ($iki['indikator'] ?? '')) . ': <strong>'
            . esc($angka($iki['target'] ?? null) . ' ' . ($iki['satuan'] ?? '')) . '</strong></span>' : '')
        // "Tanpa bawahan" di setiap daun hanya derau — cukup tampil bila simpul itu memang punya anak di pohon ini.
        . ($st !== '' && ($st !== 'tanpa_bawahan' || ($n['anak'] ?? []) !== []) ? '<span class="ro-chip ' . $cls . '" title="Target atasan yang terbagi ke bawahan"><i class="ro-titik"></i>' . esc($lbl)
            . (($porsi['target'] ?? null) !== null && in_array($st, ['lengkap', 'kurang', 'lebih'], true)
                ? ' · ' . esc($angka($porsi['terbagi'] ?? null)) . '/' . esc($angka($porsi['target'])) . ' ' . esc((string) ($porsi['satuan'] ?? '')) : '')
            . '</span>' : '')
        . (empty($n['rhk_atasan_id']) && ! empty($n['rhk_atasan_teks'])
            ? '<span title="RHK atasan yang diintervensi"><i class="fas fa-arrow-turn-up me-1"></i>' . esc((string) $n['rhk_atasan_teks']) . '</span>' : '')
        . '</div></div>';

    $anak = $n['anak'] ?? [];
    $li   = '<li data-porsi="' . esc($st, 'attr') . '" data-cari="' . esc($cari, 'attr') . '">';
    if ($anak === []) {
        return $li . $kartu . '</div></li>';
    }
    $html = $li . '<details' . ($dalam < 2 ? ' open' : '') . '><summary>' . $kartu
        . '<span class="buka"><span class="t">+ ' . count($anak) . ' bawahan</span><span class="s">tutup</span></span></div></summary><ul>';
    foreach ($anak as $a) {
        $html .= $render($a, $dalam + 1);
    }

    return $html . '</ul></details></li>';
};
?>
<?= $this->include('ruang_opd/_kepala') ?>

<?php if ($pohon === null): ?>
  <div class="ro-kosong">
    <div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
    <h5>Data eKin belum tersedia</h5>
    <p class="small mb-0"><?= esc($ekinPesan) ?> Pohon RHK pegawai akan tampil begitu eKin dapat dihubungi.</p>
  </div>
<?php elseif ($pohon['akar'] === []): ?>
  <div class="ro-kosong">
    <div class="ic"><i class="fas fa-diagram-project"></i></div>
    <h5>Belum ada RHK pegawai</h5>
    <p class="small mb-0">Belum ada pegawai perangkat daerah ini yang menyusun SKP <?= (int) $tahun ?> di eKin.</p>
  </div>
<?php else: ?>
  <?php $j = $pohon['jumlah']; ?>
  <div class="ro-alat mb-3 ro-noprint">
    <label class="ro-cari mb-0">
      <i class="fas fa-magnifying-glass"></i>
      <input type="search" id="cp-cari" class="form-control" placeholder="Cari pegawai, jabatan, atau RHK…" autocomplete="off" aria-label="Cari di pohon">
    </label>
    <div class="ro-pil" id="cp-porsi" role="group" aria-label="Saring porsi">
      <button type="button" class="aktif" data-porsi="">Semua RHK<span class="n"><?= (int) $j['rhk'] ?></span></button>
      <button type="button" data-porsi="kurang">Porsi kurang<span class="n"><?= (int) $j['kurang'] ?></span></button>
      <button type="button" data-porsi="lebih">Porsi berlebih<span class="n"><?= (int) $j['lebih'] ?></span></button>
      <button type="button" data-porsi="beda_satuan">Beda satuan/metode<span class="n"><?= (int) $j['beda_satuan'] ?></span></button>
      <button type="button" data-porsi="lengkap">Lengkap<span class="n"><?= (int) $j['lengkap'] ?></span></button>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="cp-buka"><i class="fas fa-plus-square me-1"></i>Buka semua</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="cp-tutup"><i class="fas fa-minus-square me-1"></i>Tutup semua</button>
    </div>
  </div>
  <p class="ro-catatan mb-2"><i class="fas fa-circle-info me-1"></i><?= (int) $j['pegawai'] ?> pegawai · <?= (int) $j['rhk'] ?> RHK.
    Tiap kotak = satu Rencana Hasil Kerja; bawahannya adalah RHK yang mengintervensi RHK itu.
    Target = IKI aspek kuantitas; "porsi" membandingkan target atasan dengan jumlah target bawahannya (dihitung eKin).</p>
  <ul class="ro-pohon" id="cp-pohon">
    <?php foreach ($pohon['akar'] as $a): ?>
      <?= $render($a, 0) ?>
    <?php endforeach; ?>
  </ul>
  <div class="ro-kosong-cari" id="cp-kosong">Tidak ada RHK yang cocok.</div>

  <script>
    (function () {
      var akar = document.getElementById('cp-pohon');
      var li = Array.prototype.slice.call(akar.querySelectorAll('li'));
      var cari = document.getElementById('cp-cari');
      var porsi = '';
      function terapkan() {
        var q = cari.value.trim().toLowerCase();
        var aktif = q !== '' || porsi !== '';
        li.forEach(function (x) { x.classList.remove('sembunyi'); x.dataset.cocok = ''; });
        if (!aktif) { document.getElementById('cp-kosong').style.display = 'none'; return; }
        var ada = 0;
        li.forEach(function (x) {
          var ok = (!q || x.dataset.cari.indexOf(q) !== -1) && (!porsi || x.dataset.porsi === porsi);
          if (ok) {
            ada++;
            // Tampilkan leluhurnya dan buka rinciannya supaya konteksnya terlihat.
            for (var e = x; e && e !== akar; e = e.parentElement) {
              if (e.tagName === 'LI') e.dataset.cocok = '1';
              if (e.tagName === 'DETAILS' && e !== x.querySelector('details')) e.open = true;
            }
          }
        });
        li.forEach(function (x) { if (!x.dataset.cocok) x.classList.add('sembunyi'); });
        document.getElementById('cp-kosong').style.display = ada ? 'none' : 'block';
      }
      cari.addEventListener('input', terapkan);
      document.querySelectorAll('#cp-porsi button').forEach(function (b) {
        b.addEventListener('click', function () {
          document.querySelectorAll('#cp-porsi button').forEach(function (x) { x.classList.remove('aktif'); });
          b.classList.add('aktif'); porsi = b.dataset.porsi; terapkan();
        });
      });
      document.getElementById('cp-buka').addEventListener('click', function () { akar.querySelectorAll('details').forEach(function (d) { d.open = true; }); });
      document.getElementById('cp-tutup').addEventListener('click', function () { akar.querySelectorAll('details').forEach(function (d) { d.open = false; }); });
    })();
  </script>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
