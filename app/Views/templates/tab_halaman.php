<?php
/**
 * AKSARA+ — bilah tab halaman bersama.
 *
 * Prinsip menu AKSARA+: SATU butir menu per konsep; pilihan di dalam konsep itu
 * (PK Bupati vs PK OPD, Target vs MONEV, Indikator/Breakdown/Realisasi/Rekap IKP,
 * Tabel vs Pohon) pindah ke dalam halaman sebagai tab ini — bukan dropdown bertingkat
 * di sidebar yang memaksa pengguna menebak lebih dulu.
 *
 * Pemakaian (di view mana pun, termasuk halaman lama yang merakit <html> sendiri):
 *   <?= view('templates/tab_halaman', ['tabs' => [
 *       ['url' => 'adminkab/ikp', 'label' => 'Rekap per OPD', 'ikon' => 'fa-table', 'aktif' => true],
 *       ['url' => 'adminkab/ikp/cetak', 'label' => 'Cetak', 'ikon' => 'fa-file-pdf', 'baru' => true],
 *   ], 'label' => 'Menu IKP'], ['saveData' => false]) ?>
 *
 * 'url' relatif (base_url dipasang di sini) atau sudah absolut (http…); 'grup' opsional memisahkan kelompok tab
 * dengan garis tipis (mis. jenis dokumen | lingkup PK).
 *
 * @var list<array{url:string,label:string,ikon?:string,aktif?:bool,baru?:bool,grup?:string}> $tabs
 * @var string|null $label aria-label
 */
$grupSebelum = null;
?>
<style>
  .tab-hal { display: flex; gap: 6px; flex-wrap: nowrap; overflow-x: auto; padding: 2px 0 6px; margin: 0 0 16px; scrollbar-width: thin; align-items: center; }
  .tab-hal a { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; padding: .48rem .85rem; border-radius: 11px; border: 1px solid #e1e8e3;
    background: #fff; color: #3a4a40; font-weight: 600; font-size: .84rem; text-decoration: none; white-space: nowrap; }
  .tab-hal a i { color: #6eab11; }
  .tab-hal a:hover { border-color: #b9d69a; color: #00743e; }
  .tab-hal a.aktif { background: linear-gradient(135deg, #0a8f50, #00743e); color: #fff; border-color: transparent; box-shadow: 0 6px 14px rgba(0,116,62,.22); }
  .tab-hal a.aktif i { color: #d9f2b3; }
  .tab-hal .pisah { flex: 0 0 auto; width: 1px; align-self: stretch; background: #dfe7e2; margin: 4px 4px; }
  .tab-hal .grup-lbl { flex: 0 0 auto; font-size: .66rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: #7b8c81; margin-right: 2px; }
</style>
<nav class="tab-hal" aria-label="<?= esc($label ?? 'Bagian halaman', 'attr') ?>">
  <?php foreach ($tabs as $t): ?>
    <?php if (isset($t['grup']) && $t['grup'] !== $grupSebelum): ?>
      <?php if ($grupSebelum !== null): ?><span class="pisah" aria-hidden="true"></span><?php endif; ?>
      <span class="grup-lbl"><?= esc($t['grup']) ?></span>
      <?php $grupSebelum = $t['grup']; ?>
    <?php endif; ?>
    <a href="<?= esc(preg_match('#^https?://#', $t['url']) ? $t['url'] : base_url($t['url'])) ?>" class="<?= ! empty($t['aktif']) ? 'aktif' : '' ?>"<?= ! empty($t['aktif']) ? ' aria-current="page"' : '' ?><?= ! empty($t['baru']) ? ' target="_blank" rel="noopener"' : '' ?>>
      <?php if (! empty($t['ikon'])): ?><i class="fas <?= esc($t['ikon'], 'attr') ?>"></i><?php endif; ?><?= esc($t['label']) ?>
    </a>
  <?php endforeach; ?>
</nav>
<script>
  // Di ponsel bilah tab menggulir mendatar: pastikan tab aktif terlihat.
  (function () {
    var n = document.currentScript && document.currentScript.previousElementSibling;
    var a = n && n.querySelector('a.aktif');
    if (a && n.scrollWidth > n.clientWidth) n.scrollLeft = Math.max(0, a.offsetLeft - n.offsetLeft - 16);
  })();
</script>
