<?php
/**
 * AKSARA+ — bilah "Pemilik & pelaksana" di atas bagan Pohon Kinerja (adminopd & adminkab mode OPD).
 * Data: $pemilikPohon (PohonPemilikTrait::dataPemilikPohon), $tree (cascOpdTree).
 * Interaksi: public/assets/js/pohon_pemilik.js.
 */
$pp    = $pemilikPohon;
$label = $pp['label'];

// Cabang Eselon III untuk "Fokus cabang" — urutan sama dengan bagan.
$cabang = [];
foreach ($tree ?? [] as $t) {
    foreach ($t['sasarans'] ?? [] as $s) {
        foreach ($s['tujuan_renstras'] ?? [] as $rt) {
            foreach ($rt['es2s'] ?? [] as $e2) {
                foreach ($e2['es3s'] ?? [] as $id3 => $e3) {
                    $cabang[(int) $id3] ??= (string) $e3['nama'];
                }
            }
        }
    }
}
$cssPp = FCPATH . 'assets/css/pohon_pemilik.css';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/pohon_pemilik.css') ?>?v=<?= is_file($cssPp) ? filemtime($cssPp) : '1' ?>">

<div class="pp-alat" id="ppAlat" data-tahun="<?= (int) $pp['tahun'] ?>">
    <div class="pp-alat-baris">
        <span class="pp-alat-judul"><i class="fas fa-users" aria-hidden="true"></i> Pemilik &amp; pelaksana</span>
        <label class="pp-sakelar"><input type="checkbox" id="ppTampil" checked> Tampilkan di pohon</label>
        <label class="pp-sakelar"><input type="checkbox" id="ppSorot"> Sorot yang belum berpemilik</label>
        <label class="pp-pilih">
            <span>Fokus cabang</span>
            <select id="ppFokus" class="form-select form-select-sm">
                <option value="">Semua cabang</option>
                <?php foreach ($cabang as $id => $nama): ?>
                    <option value="<?= $id ?>"><?= esc(mb_strimwidth($nama, 0, 70, '…')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="pp-pilih pp-pilih-tahun">
            <span>Tahun</span>
            <select id="ppTahun" class="form-select form-select-sm" aria-label="Tahun pemilik">
                <?php foreach ($pp['daftarTahun'] as $t): ?>
                    <option value="<?= (int) $t ?>" <?= (int) $t === (int) $pp['tahun'] ? 'selected' : '' ?>><?= (int) $t ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="ppPaskan" title="Perkecil pohon agar selebar layar">
            <i class="fas fa-expand" aria-hidden="true"></i> Paskan layar</button>
        <a class="btn btn-sm btn-success ms-lg-auto" href="<?= esc($pp['urlKelola'], 'attr') ?>">
            <i class="fas fa-user-gear" aria-hidden="true"></i> <?= $pp['bolehUbah'] ? 'Kelola pemilik' : 'Daftar pemilik' ?></a>
    </div>
    <div class="pp-legenda">
        <span><i class="pp-titik bp-pj"></i>PJ = penanggung jawab</span>
        <span><i class="pp-titik bp-anggota"></i>Anggota</span>
        <span><i class="pp-titik bp-tambahan"></i>Tambahan = penugasan tambahan</span>
        <span><i class="pp-titik bp-kosong"></i>Belum ada pemilik</span>
        <span class="pp-legenda-cat">Pemilik <?= esc($label['es2']) ?> mengikuti <?= esc($label['pk_es2']) ?> <?= (int) $pp['tahun'] ?>. Klik kotak pemilik untuk membuka simpulnya di Pemilik Kinerja.</span>
    </div>
</div>
