<?php
/**
 * AKSARA+ — bagian di bawah bagan Pohon Kinerja: cakupan pemilik per jenjang, simpul yang belum
 * berpemilik, dan pegawai yang belum punya tugas di pohon ini (metrik "matriks 0" e-Kinerja).
 * Data: $pemilikPohon (PohonPemilikTrait), $tree. Interaksi: public/assets/js/pohon_pemilik.js.
 */
use App\Services\PohonPemilikService as PPS;

$pp     = $pemilikPohon;
$label  = $pp['label'];
$tahun  = (int) $pp['tahun'];
$persen = static fn (int $a, int $b): int => $b > 0 ? (int) round($a * 100 / $b) : 0;

// Simpul yang belum berpemilik, dalam urutan bagan.
$kosong = [];
foreach ($tree ?? [] as $t) {
    foreach ($t['sasarans'] ?? [] as $s) {
        foreach ($s['tujuan_renstras'] ?? [] as $rt) {
            foreach ($rt['es2s'] ?? [] as $e2) {
                foreach ($e2['es3s'] ?? [] as $id3 => $e3) {
                    if (empty($pp['simpul'][(int) $id3])) {
                        $kosong[(int) $id3] = ['es3', $e3['nama']];
                    }
                    foreach ($e3['es4s'] ?? [] as $id4 => $e4) {
                        if (empty($pp['simpul'][(int) $id4])) {
                            $kosong[(int) $id4] = ['es4', $e4['nama']];
                        }
                        foreach ($e4['pelaksanas'] ?? [] as $idp => $pl) {
                            if (empty($pp['simpul'][(int) $idp])) {
                                $kosong[(int) $idp] = ['pelaksana', $pl['nama']];
                            }
                        }
                    }
                }
            }
        }
    }
}

$pegawai  = $pp['roster']['pegawai'];
$tanpa    = array_values(array_filter($pegawai, static fn ($p) => $p['peran'] === 0));
$perKat   = array_fill_keys(array_keys(PPS::KATEGORI), 0);
foreach ($tanpa as $p) {
    $perKat[$p['kategori']]++;
}
$total    = ['simpul' => 0, 'berpemilik' => 0];
foreach ($pp['jenjang'] as $j) {
    $total['simpul'] += $j['simpul'];
    $total['berpemilik'] += $j['berpemilik'];
}
$pembatas = 24;
?>
<section class="pp-bagian" id="ppBagian" aria-labelledby="ppBagianJudul">
    <div class="pp-bagian-kepala">
        <h3 id="ppBagianJudul"><i class="fas fa-users-viewfinder" aria-hidden="true"></i> Pemilik &amp; pelaksana pohon kinerja <?= $tahun ?></h3>
        <a href="<?= esc($pp['urlKelola'], 'attr') ?>" class="pp-tautan"><?= $pp['bolehUbah'] ? 'Kelola di Pemilik Kinerja' : 'Buka Pemilik Kinerja' ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
    </div>

    <div class="pp-ringkas">
        <div class="pp-tile">
            <div class="pp-tile-judul">Simpul berpemilik</div>
            <div class="pp-besar"><?= $total['berpemilik'] ?>/<?= $total['simpul'] ?> <small><?= $persen($total['berpemilik'], $total['simpul']) ?>%</small></div>
            <?php foreach (['es3', 'es4', 'pelaksana'] as $lv): $x = $pp['jenjang'][$lv]; if ($x['simpul'] === 0) { continue; } ?>
                <div class="pp-baris-lv">
                    <span><?= esc($label[$lv]) ?></span>
                    <span class="pp-batang pp-lv-<?= $lv ?>"><span style="width:<?= $persen($x['berpemilik'], $x['simpul']) ?>%"></span></span>
                    <span class="pp-angka"><?= $x['berpemilik'] ?>/<?= $x['simpul'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pp-tile">
            <div class="pp-tile-judul">Pemilik <?= esc($label['es2']) ?></div>
            <?php if ($pp['es2'] === []): ?>
                <div class="pp-kepala text-danger">Belum ada <?= esc($label['pk_es2']) ?> <?= $tahun ?></div>
            <?php else: foreach ($pp['es2'] as $e): ?>
                <div class="pp-kepala"><?= esc(trim($e['plt'] . ' ' . $e['nama'])) ?><small><?= esc($e['jabatan']) ?> &middot; <?= esc($label['pk_es2']) ?> <?= $tahun ?></small></div>
            <?php endforeach; endif; ?>
        </div>
        <div class="pp-tile">
            <div class="pp-tile-judul">Pegawai belum punya tugas</div>
            <div class="pp-besar"><?= count($tanpa) ?> <small>dari <?= count($pegawai) ?> pegawai</small></div>
            <div class="pp-kecil">
                <?php foreach (PPS::KATEGORI as $k => $nm): if ($perKat[$k] === 0) { continue; } ?>
                    <span><?= esc($nm) ?> <b><?= $perKat[$k] ?></b></span>
                <?php endforeach; ?>
            </div>
            <div class="pp-kecil">Penugasan tambahan di pohon ini: <b><?= (int) $pp['tambahan'] ?></b></div>
        </div>
    </div>

    <div class="pp-dua">
        <div class="pp-kotak">
            <h4>Simpul belum berpemilik <span class="pp-lencana<?= $kosong === [] ? ' ok' : '' ?>"><?= count($kosong) ?></span></h4>
            <?php if ($kosong === []): ?>
                <p class="pp-kosong-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> Setiap simpul <?= esc($label['es3']) ?> sampai <?= esc($label['pelaksana']) ?> sudah punya pemilik tahun <?= $tahun ?>.</p>
            <?php else: ?>
                <p class="pp-cat">Klik untuk menunjukkan simpulnya di pohon.</p>
                <ul class="pp-daftar-simpul" id="ppDaftarSimpul">
                    <?php $i = 0; foreach ($kosong as $id => [$lv, $nama]): ?>
                        <li<?= $i++ >= 12 ? ' hidden' : '' ?>><button type="button" data-pp-ke="<?= $id ?>"><span class="pp-lv pp-lv-<?= $lv ?>"><?= esc($label[$lv]) ?></span><span><?= esc($nama) ?></span></button></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($kosong) > 12): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-pp-semua="ppDaftarSimpul">Tampilkan semua <?= count($kosong) ?> simpul</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="pp-kotak">
            <h4>Pegawai belum punya tugas di pohon ini <span class="pp-lencana<?= $tanpa === [] ? ' ok' : '' ?>"><?= count($tanpa) ?></span></h4>
            <?php if ($tanpa === []): ?>
                <p class="pp-kosong-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> Semua <?= count($pegawai) ?> pegawai sudah menjadi penanggung jawab atau anggota simpul tahun <?= $tahun ?>.</p>
            <?php else: ?>
                <p class="pp-cat">Pegawai perangkat daerah ini yang belum menjadi penanggung jawab atau anggota simpul mana pun &mdash; setara &ldquo;matriks 0&rdquo; di e-Kinerja.
                    Yang hanya memegang penugasan tambahan tetap tercantum karena belum punya hasil kerja utama.</p>
                <div class="pp-saring">
                    <input type="search" class="form-control form-control-sm" id="ppCariPegawai" placeholder="Cari nama atau jabatan…" aria-label="Cari pegawai belum punya tugas">
                    <button type="button" class="pp-kat aktif" data-pp-kat="">Semua</button>
                    <?php foreach (PPS::KATEGORI as $k => $nm): if ($perKat[$k] === 0) { continue; } ?>
                        <button type="button" class="pp-kat" data-pp-kat="<?= $k ?>"><?= esc($nm) ?> <?= $perKat[$k] ?></button>
                    <?php endforeach; ?>
                </div>
                <ul class="pp-daftar-pegawai" id="ppDaftarPegawai">
                    <?php foreach ($tanpa as $i => $p): ?>
                        <li data-kat="<?= esc($p['kategori'], 'attr') ?>" data-cari="<?= esc(mb_strtolower($p['nama'] . ' ' . $p['jabatan']), 'attr') ?>"<?= $i >= $pembatas ? ' data-pp-lebih hidden' : '' ?>>
                            <span class="pp-ava"><?= esc(PPS::inisial($p['nama'])) ?></span>
                            <span class="pp-org"><b><?= esc($p['nama']) ?></b><small><?= esc($p['jabatan'] !== '' ? $p['jabatan'] : 'Jabatan belum tercatat') ?> &middot; <?= esc(PPS::KATEGORI[$p['kategori']]) ?></small>
                                <?php if ($p['tambahan'] > 0): ?><em class="pp-hanya">hanya penugasan tambahan</em><?php endif; ?>
                                <?php if (($p['ganda'] ?? 1) > 1): ?><em class="pp-hanya pp-ganda" title="NIP yang sama tercatat <?= (int) $p['ganda'] ?> baris di data pegawai AKSARA; peran dari semua baris sudah dijumlahkan.">tercatat <?= (int) $p['ganda'] ?>&times; di data pegawai</em><?php endif; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="pp-cat d-none" id="ppTakAda">Tidak ada pegawai yang cocok dengan saringan.</p>
                <?php if (count($tanpa) > $pembatas): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="ppLebih">Tampilkan semua <?= count($tanpa) ?> pegawai</button>
                <?php endif; ?>
                <?php if ($pp['bolehUbah']): ?>
                    <p class="pp-cat mt-2 mb-0">Tetapkan mereka lewat <a href="<?= esc($pp['urlKelola'], 'attr') ?>">Pemilik Kinerja</a> &rarr; <b>Tambah pemilik</b> pada simpul yang sesuai.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php $jsPp = FCPATH . 'assets/js/pohon_pemilik.js'; ?>
    <script src="<?= base_url('assets/js/pohon_pemilik.js') ?>?v=<?= is_file($jsPp) ? filemtime($jsPp) : '1' ?>" defer></script>
</section>
