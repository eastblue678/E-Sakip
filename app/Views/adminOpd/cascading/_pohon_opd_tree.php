<?php
/**
 * Partial Pohon Kinerja OPD (legenda + pohon).
 * Variabel dibutuhkan dari parent view:
 *   $tree  array  hasil buildOpdTree()
 */
$tree = $tree ?? [];
// CSF disembunyikan di semua tampilan pohon OPD (admin_kab & adminOpd). Bisa di-override true.
$showCsf = $showCsf ?? false;
// Indikator diberi kode "IK" secara default (admin_kab & adminOpd).
$showKode = $showKode ?? true;
// Program & kegiatan PK di bawah kabid (Eselon III). Default FALSE = aman:
// partial ini dipakai bersama halaman PUBLIK, jadi hanya controller admin
// yang boleh menyalakannya lewat array data (bukan argumen include).
$showProgramPk = $showProgramPk ?? false;

// AKSARA+ — pemilik & pelaksana tiap simpul (App\Controllers\Concerns\PohonPemilikTrait). Hanya layar admin
// yang mengirim `pemilikPohon`; tanpa variabel ini (halaman publik, cetak) keluaran partial tidak berubah.
$pemilikPohon = (isset($pemilikPohon) && is_array($pemilikPohon)) ? $pemilikPohon : null;
$pp           = $pemilikPohon;
$kotakPemilik = static function (?array $orang, int $simpulId = 0) use ($pp): string {
    if ($pp === null || $orang === null) {
        return '';
    }
    $singkat = \App\Services\PohonPemilikService::PERAN_SINGKAT;
    $label   = \App\Services\PohonPemilikService::PERAN;
    $tautan  = $simpulId > 0 && ! empty($pp['urlKelola']) ? $pp['urlKelola'] . '#simpul-' . $simpulId : '';
    $buka    = $tautan !== ''
        ? '<a class="box-pemilik" href="' . esc($tautan, 'attr') . '" title="' . esc(($pp['bolehUbah'] ?? false) ? 'Kelola pemilik simpul ini di Pemilik Kinerja' : 'Lihat simpul ini di Pemilik Kinerja', 'attr') . '"'
        : '<div class="box-pemilik"';
    $tutup   = $tautan !== '' ? '</a>' : '</div>';
    if ($orang === []) {
        return str_replace('class="box-pemilik"', 'class="box-pemilik bp-kosong"', $buka) . ' data-bp-kosong="1">'
            . '<i class="fas fa-user-slash" aria-hidden="true"></i> Belum ada pemilik' . $tutup;
    }
    $isi = '';
    foreach (array_slice($orang, 0, 4) as $o) {
        $peran = (string) ($o['peran'] ?? 'penanggung_jawab');
        $kls   = $peran === 'penanggung_jawab' ? 'bp-pj' : ($peran === 'penugasan_tambahan' ? 'bp-tambahan' : 'bp-anggota');
        $nama  = trim(($o['plt'] ?? (! empty($o['is_plt']) ? 'Plt.' : '')) . ' ' . (string) $o['nama']);
        $pendek = \App\Services\PohonPemilikService::namaPendek($nama);
        $pendek = mb_convert_case(mb_strtolower($pendek), MB_CASE_TITLE);
        $judul = $nama . (($o['jabatan'] ?? '') !== '' ? ' — ' . $o['jabatan'] : '') . (($o['opd_lain'] ?? '') !== '' ? ' (pegawai ' . $o['opd_lain'] . ')' : '') . ' · ' . ($label[$peran] ?? $peran);
        $isi  .= '<span class="bp-orang ' . $kls . '" title="' . esc($judul, 'attr') . '">'
            . '<span class="bp-ava" aria-hidden="true">' . esc(\App\Services\PohonPemilikService::inisial($nama)) . '</span>'
            . '<span class="bp-nama">' . esc($pendek) . '</span>'
            . '<span class="bp-peran">' . esc($singkat[$peran] ?? $peran) . '</span></span>';
    }
    if (count($orang) > 4) {
        $isi .= '<span class="bp-lagi">+' . (count($orang) - 4) . ' lainnya</span>';
    }

    return $buka . '>' . $isi . $tutup;
};
?>

<!-- LEGENDA WARNA -->
<div class="pohon-legend">
    <span class="lg-title">Keterangan:</span>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#15803d,#166534)"></span> Tujuan RPJMD</div>
    <?php // Jenjang sasaran dinomori 1–5 (RPJMD, ESS II, ESS III, ESS IV/JF, Pelaksana) ?>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#0f766e,#115e59)"></span> Sasaran 1</div>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#2563eb,#1e40af)"></span> Tujuan Renstra</div>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#c2410c,#9a3412)"></span> Sasaran 2<?= $pp !== null ? ' · ' . esc($pp['label']['es2']) : '' ?></div>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#9333ea,#7e22ce)"></span> Sasaran 3<?= $pp !== null ? ' · ' . esc($pp['label']['es3']) : '' ?></div>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#e11d48,#be123c)"></span> Sasaran 4<?= $pp !== null ? ' · ' . esc($pp['label']['es4']) : '' ?></div>
    <?php // Pelaksana: oranye tua — beda jelas dari merah Eselon IV, tetap selaras palet ?>
    <div class="lg-item"><span class="lg-swatch" style="background:linear-gradient(135deg,#b45309,#92400e)"></span> Sasaran 5<?= $pp !== null ? ' · ' . esc($pp['label']['pelaksana']) : '' ?></div>
    <div class="lg-item"><span class="lg-swatch" style="background:#eef2f5;border:1px solid #dbe4de"></span> Indikator Kinerja</div>
    <?php if ($showCsf): ?>
        <div class="lg-item"><span class="lg-swatch" style="background:#faf3e6;border:1px solid #ecdcb8"></span> CSF</div>
    <?php endif; ?>
    <?php if ($showProgramPk): ?>
        <div class="lg-item"><span class="lg-swatch" style="background:#eef4ff;border:1px solid #c7d7fb"></span> Program PK</div>
        <div class="lg-item"><span class="lg-swatch" style="background:#f2fbf5;border:1px solid #c3e9d0"></span> Kegiatan PK</div>
    <?php endif; ?>
</div>

<div class="tree-container text-center">
    <div class="tree" id="tree-container">
        <ul>
            <?php foreach ($tree as $tujuanRpjmd): ?>
                <li>
                    <!-- L1: Tujuan RPJMD -->
                    <div class="tree-node">
                        <div class="box-l1">
                            <div class="node-label">Tujuan RPJMD</div>
                            <?= nl2br(esc($tujuanRpjmd['nama'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($tujuanRpjmd['sasarans'])): ?>
                        <ul>
                            <?php foreach ($tujuanRpjmd['sasarans'] as $sasaranRpjmd): ?>
                                <li>
                                    <!-- L2: Sasaran RPJMD -->
                                    <div class="tree-node">
                                        <div class="box-l2">
                                            <div class="node-label">Sasaran 1</div>
                                            <?= nl2br(esc($sasaranRpjmd['nama'])) ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($sasaranRpjmd['tujuan_renstras'])): ?>
                                        <ul>
                                            <?php foreach ($sasaranRpjmd['tujuan_renstras'] as $tujuanRenstra): ?>
                                                <li>
                                                    <!-- L3: Tujuan Renstra -->
                                                    <div class="tree-node">
                                                        <div class="box-l3">
                                                            <div class="node-label">Tujuan Renstra</div>
                                                            <?= nl2br(esc($tujuanRenstra['nama'])) ?>
                                                        </div>
                                                        <?php foreach (($tujuanRenstra['indikator_tujuan'] ?? []) as $indikatorTujuan): ?>
                                                            <div class="box-iks"><?php if ($showKode): ?><span class="ind-kode">IK</span><?php endif; ?><?= nl2br(esc($indikatorTujuan)) ?></div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <?php if (!empty($tujuanRenstra['es2s'])): ?>
                                                        <ul>
                                                            <?php foreach ($tujuanRenstra['es2s'] as $es2): ?>
                                                                <li>
                                                                    <!-- L4: Sasaran ESS II -->
                                                                    <div class="tree-node">
                                                                        <?php if ($showCsf && !empty($es2['csf'])): ?>
                                                                            <div class="box-csf">
                                                                                <div class="node-label" style="opacity:.8">CSF</div>
                                                                                <?= nl2br(esc($es2['csf'])) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div class="box-es2">
                                                                            <div class="node-label">Sasaran 2<?= $pp !== null ? ' · ' . esc($pp['label']['es2']) : '' ?></div>
                                                                            <?= nl2br(esc($es2['nama'])) ?>
                                                                        </div>
                                                                        <?php foreach ($es2['indikators'] as $indikatorEs2): ?>
                                                                            <div class="box-iks"><?php if ($showKode): ?><span class="ind-kode">IK</span><?php endif; ?><?= nl2br(esc($indikatorEs2)) ?></div>
                                                                        <?php endforeach; ?>
                                                                        <?= $pp !== null ? $kotakPemilik(array_map(static fn ($e) => $e + ['peran' => 'penanggung_jawab'], $pp['es2']), 0) : '' ?>
                                                                    </div>

                                                                    <?php if (!empty($es2['es3s'])): ?>
                                                                        <ul>
                                                                            <?php foreach ($es2['es3s'] as $es3Id => $es3): ?>
                                                                                <li>
                                                                                    <!-- L5: Sasaran ESS III -->
                                                                                    <div class="tree-node"<?= $pp !== null ? ' data-simpul="' . (int) $es3Id . '" data-jenjang="es3"' : '' ?>>
                                                                                        <?php if ($showCsf && !empty($es3['csf'])): ?>
                                                                                            <div class="box-csf">
                                                                                                <div class="node-label" style="opacity:.8">CSF</div>
                                                                                                <?= nl2br(esc($es3['csf'])) ?>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                        <div class="box-es3">
                                                                                            <div class="node-label">Sasaran 3<?= $pp !== null ? ' · ' . esc($pp['label']['es3']) : '' ?></div>
                                                                                            <?= nl2br(esc($es3['nama'])) ?>
                                                                                        </div>
                                                                                        <?php foreach ($es3['indikators'] as $indikatorEs3): ?>
                                                                                            <div class="box-iks"><?php if ($showKode): ?><span class="ind-kode">IK</span><?php endif; ?><?= nl2br(esc($indikatorEs3)) ?></div>
                                                                                        <?php endforeach; ?>
                                                                                        <?= $pp !== null ? $kotakPemilik($pp['simpul'][(int) $es3Id] ?? [], (int) $es3Id) : '' ?>
                                                                                        <?php // Program PK + kegiatan di bawahnya. Program diturunkan DARI kegiatannya,
                                                                                             // jadi pasangan program-kegiatan selalu konsisten. Node yang teksnya tidak
                                                                                             // cocok dengan PK mana pun sengaja dibiarkan kosong. ?>
                                                                                        <?php if ($showProgramPk && !empty($es3['programs'])): ?>
                                                                                            <?php foreach ($es3['programs'] as $progEs3): ?>
                                                                                                <div class="box-prog"><?php if ($showKode): ?><span class="prog-kode">PRG</span><?php endif; ?><?= esc($progEs3['nama']) ?></div>
                                                                                                <?php foreach ($progEs3['kegiatan'] as $kegEs3): ?>
                                                                                                    <div class="box-keg"><?php if ($showKode): ?><span class="keg-kode">KEG</span><?php endif; ?><?= esc($kegEs3['nama']) ?></div>
                                                                                                <?php endforeach; ?>
                                                                                            <?php endforeach; ?>
                                                                                        <?php endif; ?>
                                                                                    </div>

                                                                                    <?php if (!empty($es3['es4s'])): ?>
                                                                                        <ul>
                                                                                            <?php foreach ($es3['es4s'] as $es4Id => $es4): ?>
                                                                                                <li>
                                                                                                    <!-- L6: Sasaran ESS IV -->
                                                                                                    <div class="tree-node"<?= $pp !== null ? ' data-simpul="' . (int) $es4Id . '" data-jenjang="es4"' : '' ?>>
                                                                                                        <?php if ($showCsf && !empty($es4['csf'])): ?>
                                                                                                            <div class="box-csf">
                                                                                                                <div class="node-label" style="opacity:.8">CSF</div>
                                                                                                                <?= nl2br(esc($es4['csf'])) ?>
                                                                                                            </div>
                                                                                                        <?php endif; ?>
                                                                                                        <div class="box-es4">
                                                                                                            <div class="node-label">Sasaran 4<?= $pp !== null ? ' · ' . esc($pp['label']['es4']) : '' ?></div>
                                                                                                            <?= nl2br(esc($es4['nama'])) ?>
                                                                                                        </div>
                                                                                                        <?php foreach ($es4['indikators'] as $indikatorEs4): ?>
                                                                                                            <div class="box-iks"><?php if ($showKode): ?><span class="ind-kode">IK</span><?php endif; ?><?= nl2br(esc($indikatorEs4)) ?></div>
                                                                                                        <?php endforeach; ?>
                                                                                                        <?= $pp !== null ? $kotakPemilik($pp['simpul'][(int) $es4Id] ?? [], (int) $es4Id) : '' ?>
                                                                                                    </div>

                                                                                                    <?php // L7: PELAKSANA — jenjang terakhir, di bawah Eselon IV / JF ?>
                                                                                                    <?php if (!empty($es4['pelaksanas'])): ?>
                                                                                                        <ul>
                                                                                                            <?php foreach ($es4['pelaksanas'] as $pelId => $pel): ?>
                                                                                                                <li>
                                                                                                                    <div class="tree-node"<?= $pp !== null ? ' data-simpul="' . (int) $pelId . '" data-jenjang="pelaksana"' : '' ?>>
                                                                                                                        <?php if ($showCsf && !empty($pel['csf'])): ?>
                                                                                                                            <div class="box-csf">
                                                                                                                                <div class="node-label" style="opacity:.8">CSF</div>
                                                                                                                                <?= nl2br(esc($pel['csf'])) ?>
                                                                                                                            </div>
                                                                                                                        <?php endif; ?>
                                                                                                                        <div class="box-pelaksana">
                                                                                                                            <div class="node-label">Sasaran 5<?= $pp !== null ? ' · ' . esc($pp['label']['pelaksana']) : '' ?></div>
                                                                                                                            <?= nl2br(esc($pel['nama'])) ?>
                                                                                                                        </div>
                                                                                                                        <?php foreach ($pel['indikators'] as $indikatorPel): ?>
                                                                                                                            <div class="box-iks"><?php if ($showKode): ?><span class="ind-kode">IK</span><?php endif; ?><?= nl2br(esc($indikatorPel)) ?></div>
                                                                                                                        <?php endforeach; ?>
                                                                                                                        <?= $pp !== null ? $kotakPemilik($pp['simpul'][(int) $pelId] ?? [], (int) $pelId) : '' ?>
                                                                                                                    </div>
                                                                                                                </li>
                                                                                                            <?php endforeach; ?>
                                                                                                        </ul>
                                                                                                    <?php endif; ?>
                                                                                                </li>
                                                                                            <?php endforeach; ?>
                                                                                        </ul>
                                                                                    <?php endif; ?>
                                                                                </li>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
