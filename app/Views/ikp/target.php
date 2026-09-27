<?php
/**
 * Target satu IKP: langkah 1 target tahunan (periode RPJMD) terhadap target
 * 5 tahun, langkah 2 target 12 bulan tahun terpilih terhadap target tahunan,
 * dengan rekap TW otomatis. Simpan: POST ikp/target/{id}/save (JSON).
 *
 * @var array $ikp      baris IkpRekapService::satu()
 * @var array $rekap    IkpRekapService::rekapSatu() untuk $tahun
 * @var array $tahunan  [tahun => [target, target_teks]]
 * @var bool  $bulat    satuan tak terbagi -> Bagi Rata bilangan bulat
 */
$js      = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$id      = (int) $ikp['id'];
$pola    = $ikp['pola'] ?? ikp_pola($ikp);
$metode  = (string) $pola['metode'];
$polaM   = ikp_pola_meta()[$pola['pola']];
$nUkur   = count($pola['bulan_ukur']);
$kat     = (string) $ikp['kategori'];
$meta    = $kategoriMeta[$kat] ?? ['ikon' => 'fa-tag', 'singkat' => $kat];
$jelas   = $metodeJelas[$metode] ?? null;
$t5      = $ikp['target_5_tahun'] === null ? null : (float) $ikp['target_5_tahun'];
$baseline = $ikp['baseline'] === null ? null : (float) $ikp['baseline'];
$fmt4    = static fn ($v) => $v === null ? '' : ikp_fmt((float) $v, 4);
$aturanTahunan = [
    'sum'         => 'Jumlah target 5 tahun harus sama dengan target 5 tahun.',
    'trend_naik'  => 'Target tahun terakhir harus sama dengan target 5 tahun, dan tidak boleh turun dari tahun ke tahun.',
    'trend_turun' => 'Target tahun terakhir harus sama dengan target 5 tahun, dan tidak boleh naik dari tahun ke tahun.',
    'trend_flat'  => 'Setiap tahun bernilai sama dengan target 5 tahun.',
][$metode] ?? 'Pilih metode perhitungan dulu agar target bisa diperiksa.';
$blUkur    = ikp_bulan_ukur_label($pola['bulan_ukur']);
$blAkhir   = $pola['bulan_ukur'] === [] ? 'bulan ukur terakhir' : ikp_nama_bulan((int) end($pola['bulan_ukur']));
$aturanBulanan = match ($pola['pola']) {
    'hitungan' => 'Target diisi pada bulan ukur (' . $blUkur . ') sebagai cicilan; jumlahnya harus sama dengan target tahunan. Triwulan = jumlah bulannya.',
    'rilis'    => 'Nilai resmi dirilis ' . implode(', ', array_map(static fn ($m) => ikp_bulan_rilis_label($pola, (int) $tahun, (int) $m, false), $pola['bulan_ukur']))
        . (! empty($pola['penerbit']) ? ' oleh ' . $pola['penerbit'] : '') . '. Target hanya pada bulan rilis dan TIDAK dicicil; target ' . $blAkhir . ' = target tahunan.',
    default    => 'Target = posisi yang diharapkan pada bulan ukur (' . $blUkur . '), bukan cicilan. Target ' . $blAkhir . ' = target tahunan'
        . ($metode === 'trend_turun' ? ' dan tidak boleh naik.' : ($metode === 'trend_flat' ? '; setiap bulan ukur sama.' : ' dan tidak boleh turun.')),
};
if ($metode === '') {
    $aturanBulanan = '';
}
$awalBulanan = $tahunan[$tahun - 1]['target'] ?? $baseline;
$konfig = [
    'urlSimpan' => $u('adminopd/ikp/target/' . $id . '/save'),
    'metode'    => $metode,
    'pola'      => ['pola' => $pola['pola'], 'metode' => $metode, 'bulan_ukur' => $pola['bulan_ukur']],
    't5'        => $t5,
    'baseline'  => $baseline,
    'bulat'     => $bulat,
    'tahun'     => $tahun,
    'tahunList' => $tahunList,
];
?>
<?= $this->include('ikp/_kepala') ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <a href="<?= esc($u('adminopd/ikp', ['tahun' => $tahun]), 'attr') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Daftar IKP</a>
    <div class="d-flex gap-2">
        <?php if ($bolehUbah): ?>
            <a href="<?= esc($u('adminopd/ikp/edit/' . $id), 'attr') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>Ubah IKP</a>
        <?php endif; ?>
        <a href="<?= esc($u('adminopd/ikp/realisasi', ['tahun' => $tahun]), 'attr') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen-to-square me-1"></i>Isi realisasi</a>
    </div>
</div>

<!-- Ringkasan IKP -->
<div class="ikp-langkah">
    <div class="hd">
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap gap-1 mb-1">
                <span class="ikp-kat <?= esc($kat, 'attr') ?>"><i class="fas <?= $meta['ikon'] ?>"></i><?= esc($meta['singkat']) ?></span>
                <?php if (! empty($ikp['pu_nama'])): ?>
                    <span class="ikp-pu" style="--pu: <?= esc($ikp['pu_warna'] ?: '#00743e', 'attr') ?>"><i class="fas <?= esc($ikp['pu_ikon'] ?: 'fa-star', 'attr') ?>"></i><?= esc($ikp['pu_nama']) ?></span>
                <?php endif; ?>
            </div>
            <h3><?= esc($ikp['output_prioritas']) ?></h3>
            <?php if (! empty($ikp['program_opd'])): ?><p><i class="fas fa-folder-open me-1"></i><?= esc($ikp['program_opd']) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="bd">
        <div class="ikp-ringkas-ikp">
            <div class="it"><div class="l">Satuan</div><div class="v"><?= esc($ikp['satuan_label'] !== '' ? $ikp['satuan_label'] : '-') ?></div></div>
            <div class="it"><div class="l">Pola ukur</div><div class="v"><span class="ikp-pola <?= esc($pola['pola'], 'attr') ?>"><i class="fas <?= $polaM['ikon'] ?>"></i><?= esc(ikp_pola_ringkas($pola, (int) $tahun)) ?></span>
                <?php if (! empty($pola['ditebak'])): ?><a class="ikp-pola tebak ms-1" href="<?= esc($u('adminopd/ikp/edit/' . $id) . '#bagian-pola', 'attr') ?>" title="Pola ukur ditebak otomatis; buka form untuk memeriksa & menyimpannya">Periksa pola ukur</a><?php endif; ?></div></div>
            <?php if ($pola['pola'] === 'rilis'): ?>
                <div class="it"><div class="l">Penerbit</div><div class="v"><?= esc($pola['penerbit'] ?? '-') ?></div></div>
            <?php endif; ?>
            <div class="it"><div class="l">Baseline</div><div class="v"><?= esc(ikp_fmt($baseline, 4)) ?></div></div>
            <div class="it"><div class="l">Target 5 tahun</div><div class="v"><?= $t5 !== null ? esc(ikp_fmt($t5, 4)) : esc($ikp['target_5_tahun_teks'] ?: '-') ?></div></div>
            <div class="it"><div class="l">Penanggung jawab</div><div class="v"><?= esc($ikp['pj_nama'] ?? '-') ?></div></div>
        </div>
    </div>
</div>

<?php if ($metode !== ''): ?>
    <div class="ikp-info">
        <i class="fas <?= $polaM['ikon'] ?>"></i>
        <div>
            <p><strong>Pola <?= esc($polaM['judul']) ?>.</strong> <?= esc($polaM['isi']) ?> <?= esc($polaM['target']) ?></p>
            <p class="small text-secondary mb-0">Bulan ukur: <strong><?= esc($blUkur) ?></strong>. Bulan lain tampil "—" dan tidak punya target. Pemeriksaan di bawah hanya peringatan — target tetap tersimpan walau belum sesuai.</p>
        </div>
    </div>
<?php else: ?>
    <div class="ikp-info kuning">
        <i class="fas fa-triangle-exclamation"></i>
        <div><p><strong>Pola ukur belum dikonfirmasi dan metodenya kosong.</strong> Tanpa itu, sistem tidak tahu apakah angka bulanan dijumlah atau diambil posisinya, sehingga Bagi Rata, pemeriksaan, dan rekap triwulan tidak dapat dihitung.
            <?php if ($bolehUbah): ?><a href="<?= esc($u('adminopd/ikp/edit/' . $id) . '#bagian-pola', 'attr') ?>">Pilih pola ukur sekarang</a>.<?php endif; ?></p></div>
    </div>
<?php endif; ?>

<div id="ikp-target" data-konfig="<?= esc($js($konfig), 'attr') ?>">

    <!-- Langkah 1: tahunan -->
    <section class="ikp-langkah" id="langkah-tahunan">
        <div class="hd">
            <span class="no">1</span>
            <div class="flex-grow-1">
                <h3>Target tahunan <?= (int) $tahunList[0] ?>–<?= (int) end($tahunList) ?></h3>
                <p><?= esc($aturanTahunan) ?></p>
            </div>
            <div class="small text-secondary">Target 5 tahun: <strong class="text-dark"><?= $t5 !== null ? esc(ikp_fmt($t5, 4)) : 'bukan angka' ?></strong></div>
        </div>
        <div class="bd">
            <div class="ikp-sel-grid thn">
                <?php foreach ($tahunList as $th): $isi = $tahunan[$th] ?? []; ?>
                    <div class="ikp-sel<?= (int) $th === (int) $tahun ? ' pilih' : '' ?>">
                        <label for="thn-<?= (int) $th ?>"><?= (int) $th ?><?= (int) $th === (int) $tahun ? ' · dipilih' : '' ?></label>
                        <input type="text" class="isian" id="thn-<?= (int) $th ?>" data-tahun="<?= (int) $th ?>"
                               value="<?= esc($fmt4($isi['target'] ?? null), 'attr') ?>"
                               placeholder="<?= ! empty($isi['target_teks']) && ($isi['target'] ?? null) === null ? esc(mb_strimwidth((string) $isi['target_teks'], 0, 14, '…'), 'attr') : '–' ?>" <?= $bolehUbah ? '' : 'disabled' ?>
                               <?= ! empty($isi['target_teks']) && ($isi['target'] ?? null) === null ? 'title="Teks asal: ' . esc($isi['target_teks'], 'attr') . '"' : '' ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ikp-hasil-cek">
                <span id="cek-tahunan"></span>
                <span class="small text-secondary" id="cek-tahunan-pesan"></span>
                <?php if ($bolehUbah): ?>
                    <span class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" id="bagi-tahunan" <?= ($t5 === null || $metode === '') ? 'disabled title="Butuh target 5 tahun berupa angka dan metode"' : '' ?>>
                            <i class="fas fa-wand-magic-sparkles me-1"></i>Bagi rata
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="simpan-tahunan"><i class="fas fa-floppy-disk me-1"></i>Simpan target tahunan</button>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Langkah 2: bulanan -->
    <section class="ikp-langkah" id="langkah-bulanan">
        <div class="hd">
            <span class="no">2</span>
            <div class="flex-grow-1">
                <h3>Target bulanan <?= (int) $tahun ?></h3>
                <p><?= esc($aturanBulanan) ?></p>
            </div>
            <nav class="ikp-tahun" aria-label="Tahun target bulanan">
                <?php foreach ($tahunList as $th): ?>
                    <a href="<?= esc($u('adminopd/ikp/target/' . $id, ['tahun' => $th]), 'attr') ?>" class="<?= (int) $th === (int) $tahun ? 'aktif' : '' ?> pindah-tahun"><?= (int) $th ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="bd">
            <div class="small text-secondary mb-2">
                Target tahunan <?= (int) $tahun ?>: <strong class="text-dark" id="induk-bulanan"><?= esc(ikp_fmt($tahunan[$tahun]['target'] ?? null, 4)) ?></strong>
                <?php if ($pola['pola'] !== 'hitungan' && $nUkur > 1 && in_array($metode, ['trend_naik', 'trend_turun'], true)): ?>
                    · Lintasan posisi dimulai dari <?= isset($tahunan[$tahun - 1]['target']) ? 'target ' . ($tahun - 1) : 'baseline' ?>:
                    <strong class="text-dark"><?= esc(ikp_fmt($awalBulanan, 4)) ?></strong>
                <?php endif; ?>
            </div>
            <div class="ikp-sel-grid bln">
                <?php for ($m = 1; $m <= 12; $m++): $b = $rekap['bulan'][$m] ?? []; $ukur = ikp_bulan_diukur($pola, $m); ?>
                    <?php if ($ukur): ?>
                        <div class="ikp-sel">
                            <label for="bln-<?= $m ?>"><?= esc(ikp_nama_bulan($m)) ?><?= $pola['pola'] === 'rilis' ? ' · rilis' . (! empty($pola['rilis_tahun_berikut']) ? ' ' . ((int) $tahun + 1) : '') : '' ?></label>
                            <input type="text" class="isian" id="bln-<?= $m ?>" data-bulan="<?= $m ?>" data-ukur="1"
                                   value="<?= esc($fmt4($b['target'] ?? null), 'attr') ?>" placeholder="–" <?= $bolehUbah ? '' : 'disabled' ?>>
                        </div>
                    <?php else: ?>
                        <div class="ikp-sel tidak-ukur" title="<?= esc($b['keadaan_ket'] ?? 'Tidak diukur', 'attr') ?>">
                            <label for="bln-<?= $m ?>"><?= esc(ikp_nama_bulan($m)) ?></label>
                            <input type="text" class="isian" id="bln-<?= $m ?>" data-bulan="<?= $m ?>" data-ukur="0" value="" placeholder="—" disabled
                                   aria-label="<?= esc(ikp_nama_bulan($m) . ': ' . ($b['keadaan_ket'] ?? 'tidak diukur'), 'attr') ?>">
                            <span class="sel-tidak-ukur"><small><?= $pola['pola'] === 'rilis' ? 'bukan bulan rilis' : 'tidak diukur' ?></small></span>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <div class="small fw-bold text-secondary mt-3 mb-1"><i class="fas fa-calculator me-1"></i>Rekap triwulan (dihitung otomatis dari bulan)</div>
            <div class="ikp-sel-grid tw4">
                <?php for ($q = 1; $q <= 4; $q++): ?>
                    <div class="ikp-sel tw">
                        <label>TW <?= capaianRomawi($q) ?></label>
                        <div class="nilai" id="tw-<?= $q ?>"><?= esc(ikp_fmt($rekap['triwulan'][$q]['target'] ?? null, 4)) ?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="ikp-hasil-cek">
                <span id="cek-bulanan"></span>
                <span class="small text-secondary" id="cek-bulanan-pesan"></span>
                <?php if ($bolehUbah): ?>
                    <span class="ms-auto d-flex gap-2">
                        <?php /* "Bagi rata" hanya untuk hitungan; posisi/rilis mengisi POSISI bulan ukur (bukan cicilan). */ ?>
                        <button type="button" class="btn btn-outline-success btn-sm" id="bagi-bulanan" <?= $metode === '' ? 'disabled' : '' ?>
                                title="<?= $pola['pola'] === 'hitungan' ? 'Bagi target tahunan rata ke bulan ukur' : 'Bulan ukur terakhir = target tahunan' . ($nUkur > 1 ? '; bulan ukur sebelumnya lintasan bertahap' : '') ?>">
                            <i class="fas fa-wand-magic-sparkles me-1"></i><?= $pola['pola'] === 'hitungan' ? 'Bagi rata' : 'Isi target ' . ($pola['pola'] === 'rilis' ? 'bulan rilis' : 'posisi') ?>
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="simpan-bulanan"><i class="fas fa-floppy-disk me-1"></i>Simpan target bulanan</button>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($bulat && $pola['pola'] === 'hitungan'): ?>
                <div class="small text-secondary mt-2"><i class="fas fa-circle-info me-1"></i>Satuan <?= esc($ikp['satuan_label']) ?> tidak terbagi: Bagi Rata menghasilkan bilangan bulat dan sisa pembagian ditaruh di bulan/tahun awal.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script defer src="<?= base_url('assets/js/adminopd/ikp/ikp-angka.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/adminopd/ikp/ikp-angka.js') ?>"></script>
<script defer src="<?= base_url('assets/js/adminopd/ikp/ikp-target.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/adminopd/ikp/ikp-target.js') ?>"></script>

<?= $this->include('templates/shell_bawah') ?>
