<?php
/**
 * Turunkan IKP — pohon mini satu IKP (AdminOpd\IkpTurunController::detail).
 *
 * Kepala OPD (pemilik IKP) di puncak; di bawahnya simpul pohon kinerja aktif
 * (Eselon III → IV/Ketua Tim → pelaksana) dengan pemiliknya. Setiap simpul:
 * centang "ikut memikul", peran (pemikul angka / pendukung), indikator (yang ada
 * atau baru), porsi / target utuh / indikator proses. Pemeriksa per jenjang
 * dihitung server (tersimpan) dan diperbarui langsung oleh ikp-turun.js.
 *
 * Tanpa elemen melayang: tombol Simpan di ujung formulir (bukan bilah lekat).
 *
 * @var array $ikp, $rekap, $pola, $pohon, $perNode, $usul, $periksa, $cakupan, $es2, $proses, $ikpSederhana
 * @var ?float $target
 * @var ?array $lama   isian terakhir (setelah galat validasi)
 */
$label    = $pohon['label'];
$simpul   = $pohon['simpul'];
$ind      = $pohon['indikator'];
$polaKode = $pola['pola'];
$utuh     = $polaKode !== 'hitungan';
$satuan   = (string) ($ikp['satuan_label'] ?? '');
$ikpId    = (int) $ikp['id'];
$ubah     = $bolehUbah;
$bulanTeks = $polaKode === 'rilis'
    ? 'bulan rilis ' . implode(', ', array_map(static fn ($m) => ikp_bulan_rilis_label($pola, (int) $tahun, (int) $m), $pola['bulan_ukur']))
    : 'bulan ukur ' . ikp_bulan_ukur_label($pola['bulan_ukur']);

// ---------- Keadaan isian per simpul: isian lama (galat) → tersimpan → usulan → bawaan.
$keadaan = function (int $node) use ($lama, $perNode, $usul, $ind, $ikpSederhana, $proses): array {
    $bawaan = [
        'ikut' => false, 'peran' => 'angka', 'sumber' => '',
        'indikator' => 'baru', 'teks' => $ikpSederhana['nama'], 'satuan' => $ikpSederhana['satuan'], 'target' => '',
        'indikator_proses' => 'baru', 'teks_proses' => $proses['teks'], 'satuan_proses' => $proses['satuan'], 'target_proses' => ikp_fmt($proses['target'], 4),
    ];
    if ($lama !== null) {
        $v = $lama[$node] ?? [];

        return array_merge($bawaan, array_intersect_key(array_map(static fn ($x) => is_string($x) ? $x : (string) $x, (array) $v), $bawaan),
            ['ikut' => ! empty($v['ikut'])]);
    }
    if (isset($perNode[$node])) {
        $b = $perNode[$node];
        $s = $bawaan;
        $s['ikut']   = true;
        $s['peran']  = $b['ikp_peran'];
        $s['sumber'] = (string) ($b['sumber'] ?? '');
        if ($b['ikp_peran'] === 'pendukung') {
            $s['indikator_proses'] = (string) $b['cascading_indikator_id'];
            $s['target_proses']    = ikp_fmt($b['target'], 4);
        } else {
            $s['indikator'] = (string) $b['cascading_indikator_id'];
            $s['target']    = ikp_fmt($b['target'], 4);
        }

        return $s;
    }
    if (isset($usul[$node])) {
        $u = $usul[$node];
        $s = $bawaan;
        $s['ikut']   = true;
        $s['peran']  = $u['peran'];
        $s['sumber'] = 'usulan';
        if ($u['peran'] === 'pendukung') {
            $s['indikator_proses'] = (string) $u['indikator'];
            $s['teks_proses']      = $u['teks'];
            $s['satuan_proses']    = $u['satuan'];
            $s['target_proses']    = ikp_fmt($u['target'], 4);
        } else {
            $s['indikator'] = (string) $u['indikator'];
            if ($u['indikator'] === 'baru') {
                $s['teks']   = $u['teks'];
                $s['satuan'] = $u['satuan'];
            }
            $s['target'] = ikp_fmt($u['target'], 4);
        }

        return $s;
    }

    return $bawaan;
};

// Simpul yang dicentang (untuk membuka cabang yang memuatnya).
$dicentang = [];
foreach (array_keys($simpul) as $id) {
    if ($keadaan($id)['ikut']) {
        $dicentang[$id] = true;
    }
}
$bukaCabang = [];
foreach (array_keys($dicentang) as $id) {
    $cari = $simpul[$id]['induk'] ?? null;
    while ($cari !== null && isset($simpul[$cari])) {
        $bukaCabang[$cari] = true;
        $cari = $simpul[$cari]['induk'];
    }
}
$nUsul = count($usul);
// Cabang yang memikul IKP ini tampil lebih dulu (urutan pohon dipertahankan di dalam kelompoknya).
$urut = static function (array $ids) use ($dicentang, $bukaCabang): array {
    $pakai = array_values(array_filter($ids, static fn ($i) => isset($dicentang[$i]) || isset($bukaCabang[$i])));
    $lain  = array_values(array_filter($ids, static fn ($i) => ! isset($dicentang[$i]) && ! isset($bukaCabang[$i])));

    return array_merge($pakai, $lain);
};

$chipPemilik = static function (array $orang): string {
    if ($orang === []) {
        return '<span class="tr-orang kosong"><i class="fas fa-user-slash"></i> Belum ada pemilik — isi di Pemilik Kinerja</span>';
    }
    $h = '';
    foreach (array_slice($orang, 0, 4) as $o) {
        $nama = \App\Services\PohonPemilikService::namaPendek((string) $o['nama']);
        $h .= '<span class="tr-orang ' . esc($o['peran'], 'attr') . '" title="' . esc($o['nama'] . ($o['jabatan'] !== '' ? ' — ' . $o['jabatan'] : '') . ' · ' . (\App\Services\PohonPemilikService::PERAN[$o['peran']] ?? $o['peran']), 'attr') . '">'
            . '<span class="ava">' . esc(\App\Services\PohonPemilikService::inisial((string) $o['nama'])) . '</span>'
            . '<span class="nm">' . esc(mb_convert_case(mb_strtolower($nama), MB_CASE_TITLE)) . '</span>'
            . '<span class="pr">' . esc(\App\Services\PohonPemilikService::PERAN_SINGKAT[$o['peran']] ?? $o['peran']) . '</span></span>';
    }
    if (count($orang) > 4) {
        $h .= '<span class="tr-orang lagi">+' . (count($orang) - 4) . '</span>';
    }

    return $h;
};

$kotakPeriksa = static function (?array $p, string $kunci, string $judul): string {
    $w = $p['warna'] ?? 'netral';
    $i = $w === 'ok' ? 'fa-circle-check' : ($w === 'peringatan' ? 'fa-triangle-exclamation' : 'fa-circle-info');

    return '<div class="tr-periksa ' . esc($w, 'attr') . '" data-periksa="' . esc($kunci, 'attr') . '" role="status">'
        . '<i class="fas ' . $i . '"></i><span><b>' . esc($judul) . '</b> <span class="isi">' . esc($p['pesan'] ?? '') . '</span></span></div>';
};

// Satu simpul beserta cabangnya.
$render = function (int $id) use (&$render, $urut, $simpul, $ind, $label, $keadaan, $ubah, $utuh, $polaKode, $target, $satuan, $ikpId, $chipPemilik, $kotakPeriksa, $periksa, $perNode, $bukaCabang, $pola, $tahun): string {
    $s   = $simpul[$id];
    $st  = $keadaan($id);
    $nm  = 'turun[' . $id . ']';
    $lv  = $s['level'];
    $anak = $s['anak'];
    $buka = ! empty($bukaCabang[$id]) || $st['ikut'];
    $row  = $perNode[$id] ?? null;
    ob_start(); ?>
    <li class="tr-simpul" data-node="<?= $id ?>" data-level="<?= esc($lv, 'attr') ?>">
        <div class="tr-kartu<?= $st['ikut'] ? ' ikut' : '' ?><?= $st['sumber'] === 'usulan' ? ' usulan' : '' ?>" id="simpul-<?= $id ?>">
            <div class="tr-kepala">
                <span class="tr-level lv-<?= esc($lv, 'attr') ?>"><?= esc($label[$lv] ?? $lv) ?></span>
                <?php if ($row !== null): ?>
                    <span class="tr-bintang <?= $row['ikp_peran'] === 'pendukung' ? 'pendukung' : '' ?>" title="Tersimpan: <?= $row['ikp_peran'] === 'pendukung' ? 'mendukung IKP ini' : 'memikul angka IKP ini' ?>"><?= $row['ikp_peran'] === 'pendukung' ? '☆ Mendukung IKP' : '★ IKP' ?></span>
                    <?php if (($row['sumber'] ?? '') === 'lama'): ?><span class="tr-tanda lama" title="Tautan langsung IKP → indikator sebelum fitur Turunkan IKP. Simpan untuk menjadikannya pendelegasian.">tautan lama</span><?php endif; ?>
                <?php endif; ?>
                <?php if ($st['sumber'] === 'usulan'): ?><span class="tr-tanda usul">usulan</span><?php endif; ?>
                <?php if ($anak !== []): ?><span class="tr-turunan"><?= count($anak) ?> simpul di bawahnya</span><?php endif; ?>
            </div>
            <div class="tr-sasaran"><?= esc($s['nama']) ?></div>
            <div class="tr-pemilik"><?= $chipPemilik($s['pemilik']) ?></div>

            <?php if ($ubah): ?>
                <label class="tr-ikut">
                    <input type="checkbox" name="<?= $nm ?>[ikut]" value="1" data-ikut <?= $st['ikut'] ? 'checked' : '' ?>>
                    <span>Ikut memikul IKP ini</span>
                </label>
                <div class="tr-isi" <?= $st['ikut'] ? '' : 'hidden' ?>>
                    <div class="tr-peran" role="radiogroup" aria-label="Peran simpul ini">
                        <?php foreach (\App\Services\IkpTurunService::PERAN as $k => $lbl): ?>
                            <label class="tr-peran-pil">
                                <input type="radio" name="<?= $nm ?>[peran]" value="<?= $k ?>" data-peran <?= $st['peran'] === $k ? 'checked' : '' ?>>
                                <span><b><?= esc($lbl) ?></b><small><?= esc(\App\Services\IkpTurunService::PERAN_JELAS[$k]) ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach (['angka' => '', 'pendukung' => '_proses'] as $peran => $sfx): ?>
                        <div class="tr-set" data-set="<?= $peran ?>" <?= $st['peran'] === $peran ? '' : 'hidden' ?>>
                            <label class="form-label small fw-bold mb-1" for="ind-<?= $id . $sfx ?>"><?= $peran === 'angka' ? 'Indikator yang memikul IKP' : 'Indikator proses (milik simpul ini)' ?></label>
                            <select class="form-select form-select-sm" id="ind-<?= $id . $sfx ?>" name="<?= $nm ?>[indikator<?= $sfx ?>]" data-pilih-ind data-no-select2>
                                <?php foreach ($s['indikator'] as $iid): ?>
                                    <?php $i = $ind[$iid]; $lain = $i['ikp_id'] !== null && $i['ikp_id'] !== $ikpId; ?>
                                    <option value="<?= (int) $iid ?>" <?= $st['indikator' . $sfx] === (string) $iid ? 'selected' : '' ?> <?= $lain ? 'disabled' : '' ?>>
                                        <?= esc(mb_strimwidth($i['nama'], 0, 90, '…')) ?><?= $i['satuan'] !== '' ? ' (' . esc($i['satuan']) . ')' : '' ?><?= $lain ? ' — memikul IKP lain' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="baru" <?= $st['indikator' . $sfx] === 'baru' ? 'selected' : '' ?>>+ <?= $peran === 'angka' ? 'Buat indikator baru dari rumusan IKP' : 'Buat indikator proses baru' ?></option>
                            </select>
                            <div class="tr-baru" data-baru <?= $st['indikator' . $sfx] === 'baru' ? '' : 'hidden' ?>>
                                <input type="text" class="form-control form-control-sm" name="<?= $nm ?>[teks<?= $sfx ?>]" maxlength="500"
                                       value="<?= esc($st['teks' . $sfx], 'attr') ?>" aria-label="Teks indikator baru" placeholder="Rumusan indikator">
                                <input type="text" class="form-control form-control-sm tr-satuan" name="<?= $nm ?>[satuan<?= $sfx ?>]" maxlength="50"
                                       value="<?= esc($st['satuan' . $sfx], 'attr') ?>" aria-label="Satuan" placeholder="Satuan">
                            </div>
                            <?php if ($peran === 'angka' && $utuh): ?>
                                <div class="tr-utuh"><i class="fas fa-lock me-1"></i>Target utuh <b><?= esc(ikp_fmt($target, 4)) ?></b> <?= esc($satuan) ?>
                                    — <?= $polaKode === 'rilis' ? 'nilai resmi tidak dibagi' : 'posisi tidak dibagi' ?> (<?= esc(ikp_pola_ringkas($pola, (int) $tahun)) ?>).</div>
                            <?php else: ?>
                                <div class="tr-target">
                                    <label class="small fw-bold" for="tgt-<?= $id . $sfx ?>"><?= $peran === 'angka' ? 'Porsi target ' . (int) $tahun : 'Target proses ' . (int) $tahun ?></label>
                                    <input type="text" inputmode="decimal" class="form-control form-control-sm isian" id="tgt-<?= $id . $sfx ?>"
                                           name="<?= $nm ?>[target<?= $sfx ?>]" value="<?= esc($st['target' . $sfx], 'attr') ?>" data-nol="sah"
                                           <?= $peran === 'angka' ? 'data-porsi' : '' ?> placeholder="0">
                                    <span class="tr-porsi-persen" data-persen></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($row !== null): ?>
                <div class="tr-baca">
                    <b><?= esc(\App\Services\IkpTurunService::PERAN[$row['ikp_peran']]) ?></b> ·
                    <?= esc($row['indikator']) ?>
                    · <?= $row['ikp_peran'] === 'angka' ? ($utuh ? 'target utuh ' : 'porsi ') : 'target proses ' ?><b><?= esc(ikp_fmt($row['target'], 4)) ?></b> <?= esc((string) ($row['satuan'] ?? '')) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($anak !== []): ?>
            <?php $pi = $row !== null ? ($periksa['per_induk'][(string) $row['id']] ?? null) : null; ?>
            <?php if ($ubah || ($pi !== null && $pi['kode'] !== 'belum')): ?>
                <?= $kotakPeriksa($pi, (string) $id, 'Di bawah simpul ini:') ?>
            <?php endif; ?>
            <details class="tr-anak" <?= $buka ? 'open' : '' ?>>
                <summary><?= count($anak) ?> simpul <?= esc($label[$simpul[$anak[0]]['level']] ?? '') ?></summary>
                <ul>
                    <?php foreach ($urut($anak) as $a): ?>
                        <?= $render((int) $a) ?>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </li>
    <?php return (string) ob_get_clean();
};
?>
<?= $this->include('ikp/_turun_kepala') ?>

<p class="mb-2"><a href="<?= esc($u('ikp/turun', ['tahun' => $tahun]), 'attr') ?>" class="small"><i class="fas fa-arrow-left me-1"></i>Semua IKP</a></p>

<section class="tr-ikp">
    <div class="tr-atas">
        <?php $meta = $kategoriMeta[$ikp['kategori']] ?? null; ?>
        <?php if ($meta): ?><span class="ikp-kat <?= esc($ikp['kategori'], 'attr') ?>"><i class="fas <?= esc($meta['ikon'], 'attr') ?>"></i><?= esc($meta['singkat']) ?></span><?php endif; ?>
        <span class="ikp-pola <?= esc($polaKode, 'attr') ?>"><?= esc(ikp_pola_ringkas($pola, (int) $tahun)) ?></span>
    </div>
    <h3 class="tr-nama"><?= esc((string) $ikp['output_prioritas']) ?></h3>
    <div class="tr-fakta">
        <span>Target <?= (int) $tahun ?>: <b data-target-ikp="<?= esc($target === null ? '' : (string) $target, 'attr') ?>"><?= esc(ikp_fmt($target, 4)) ?></b> <?= esc($satuan) ?></span>
        <span><?= esc(ucfirst($bulanTeks)) ?></span>
        <span>Pemilik IKP: <b><?= $es2 !== [] ? esc(implode(', ', array_map(static fn ($e) => trim($e['plt'] . ' ' . \App\Services\PohonPemilikService::namaPendek($e['nama'])), $es2))) : 'Kepala OPD (PK belum ada)' ?></b> (<?= esc($label['es2']) ?>)</span>
    </div>
    <div class="tr-cakupan">
        <span class="tr-cak-lbl">Turun sampai:</span>
        <?php foreach (['es3', 'es4', 'pelaksana'] as $lv): ?>
            <span class="tr-cak <?= $cakupan[$lv] ? 'ya' : 'tidak' ?>"><?= esc($label[$lv]) ?> <?= $cakupan[$lv] ? '✓' : '✗' ?></span>
        <?php endforeach; ?>
        <span class="small text-secondary">(tersimpan)</span>
    </div>
    <?php if ($ubah): ?>
        <div class="tr-aksi">
            <a class="btn btn-sm btn-outline-success" href="<?= esc($u('ikp/turun/' . $ikpId, ['tahun' => $tahun, 'usul' => 1]), 'attr') ?>" data-aksi="usulkan">
                <i class="fas fa-wand-magic-sparkles me-1"></i>Usulkan dari pohon</a>
            <?php if ($area === 'adminopd'): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= esc($u('adminopd/ikp/target/' . $ikpId, ['tahun' => $tahun]), 'attr') ?>"><i class="fas fa-bullseye me-1"></i>Target IKP</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($minta_usul): ?>
    <div class="ikp-info kuning" role="status" data-banner="usulan">
        <i class="fas fa-wand-magic-sparkles"></i>
        <div>
            <?php if ($nUsul > 0): ?>
                <p><strong><?= $nUsul ?> simpul diusulkan</strong> (bertanda <span class="tr-tanda usul">usulan</span>) dari kemiripan teks sasaran/indikator dengan IKP
                    <?= $polaKode === 'hitungan' ? '— porsi dibagi proporsional target indikator simpul (atau rata), leluhur = jumlah porsi turunannya' : '— satu rantai pemikul angka' . ($polaKode === 'rilis' ? ' ke pejabat pemegang nilai resmi, ditambah pendukung dengan indikator proses' : '') ?>.
                    Pendelegasian yang sudah tersimpan tidak diubah. <strong>Belum ada yang tersimpan</strong> sebelum Anda menekan Simpan.</p>
            <?php else: ?>
                <p>Tidak ada simpul baru yang cukup mirip dengan IKP ini (kemiripan &lt; 60 %). Centang simpul secara manual.</p>
            <?php endif; ?>
            <p class="small mb-0"><a href="<?= esc($u('ikp/turun/' . $ikpId, ['tahun' => $tahun]), 'attr') ?>">Batalkan usulan</a></p>
        </div>
    </div>
<?php endif; ?>

<?php if ($simpul === []): ?>
    <div class="ikp-kosong">
        <div class="ic"><i class="fas fa-sitemap"></i></div>
        <div class="fw-bold mb-1">Pohon kinerja <?= (int) $tahun ?> perangkat daerah ini belum ada.</div>
        <p class="small mb-0">Susun simpul Eselon III sampai pelaksana di menu Pohon Kinerja &amp; Cascading, lalu isi pemiliknya di Pemilik Kinerja.</p>
    </div>
<?php else: ?>
    <form method="post" action="<?= esc($u('ikp/turun/' . $ikpId . '/save'), 'attr') ?>" id="form-turun"
          data-pola="<?= esc($polaKode, 'attr') ?>" data-target="<?= esc($target === null ? '' : (string) $target, 'attr') ?>"
          data-satuan="<?= esc($satuan, 'attr') ?>" data-boleh="<?= $ubah ? 1 : 0 ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">

        <div class="tr-akar">
            <div class="tr-kartu akar">
                <div class="tr-kepala"><span class="tr-level lv-es2"><?= esc($label['es2']) ?></span><span class="tr-bintang">★ Pemilik IKP</span></div>
                <div class="tr-sasaran">Kepala perangkat daerah memegang IKP ini (<?= esc($label['pk_es2'] ?? 'PK JPT') ?>) dan menurunkannya ke jenjang di bawah.</div>
                <div class="tr-pemilik"><?= $chipPemilik(array_map(static fn ($e) => ['nama' => trim($e['plt'] . ' ' . $e['nama']), 'jabatan' => $e['jabatan'], 'peran' => 'penanggung_jawab'], $es2)) ?></div>
            </div>
            <?= $kotakPeriksa($periksa['per_induk']['akar'] ?? null, 'akar', 'Pembagian dari IKP:') ?>
        </div>

        <ul class="tr-pohon">
            <?php foreach ($urut($pohon['akar']) as $a): ?>
                <?= $render((int) $a) ?>
            <?php endforeach; ?>
        </ul>

        <?php if ($tersembunyi !== []): ?>
            <p class="small text-secondary mt-2"><i class="fas fa-eye-slash me-1"></i><?= count($tersembunyi) ?> pendelegasian berada di simpul yang kini tidak tampil (IKU induknya dihentikan). Pendelegasian itu tetap tersimpan dan tidak dikirim ke eKin.</p>
        <?php endif; ?>

        <?php if ($ubah): ?>
            <div class="ikp-tombol-bawah">
                <button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1"></i>Simpan pendelegasian</button>
                <a href="<?= esc($u('ikp/turun/' . $ikpId, ['tahun' => $tahun]), 'attr') ?>" class="btn btn-outline-secondary">Batal</a>
                <span class="small text-secondary">Pemeriksa hanya memberi peringatan; penyimpanan tidak diblokir.</span>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php if ($ubah): ?>
    <script defer src="<?= base_url('assets/js/adminopd/ikp/ikp-angka.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/adminopd/ikp/ikp-angka.js') ?>"></script>
    <script defer src="<?= base_url('assets/js/adminopd/ikp/ikp-turun.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/adminopd/ikp/ikp-turun.js') ?>"></script>
<?php endif; ?>

<?= $this->include('templates/shell_bawah') ?>
