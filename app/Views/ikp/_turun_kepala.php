<?php
/**
 * Kepala halaman Turunkan IKP untuk DUA area:
 *   adminopd  → ikp/_kepala (judul, tahun, tab IKP OPD) seperti halaman IKP lain;
 *   adminkab  → shell + tab IKP Kabupaten + pemilih OPD (baca saja).
 *
 * Data: $area, $title, $scope, $tahun, $tahunList, $periode, $aktif, $u, opsional $subJudul.
 */
$this->setVar('shellCss', (string) @file_get_contents(FCPATH . 'assets/css/ikp.css'));
?>
<?php if (($area ?? 'adminopd') === 'adminopd'): ?>
    <?= $this->include('ikp/_kepala') ?>
<?php else: ?>
    <?= $this->include('templates/shell_atas') ?>
    <?= $this->include('ikp/_tab_kab') ?>
    <div class="ikp-kepala">
        <div class="ic"><i class="fas fa-sitemap"></i></div>
        <div class="isi">
            <h2><?= esc($title ?? 'Turunkan IKP') ?></h2>
            <p><i class="fas fa-eye me-1"></i>Hanya baca · IKP diturunkan Admin OPD atas nama Kepala OPD</p>
            <?php if (! empty($subJudul)): ?><p class="mt-1"><?= esc($subJudul) ?></p><?php endif; ?>
        </div>
        <nav class="ikp-tahun" aria-label="Pilih tahun">
            <span class="lbl">Tahun</span>
            <?php $qs = service('request')->getGet(); ?>
            <?php foreach ($tahunList as $th): ?>
                <?php $q = $qs; $q['tahun'] = $th; ?>
                <a href="<?= esc(current_url() . '?' . http_build_query($q), 'attr') ?>" class="<?= (int) $th === (int) $tahun ? 'aktif' : '' ?>"><?= (int) $th ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <form method="get" action="<?= base_url('adminkab/ikp/turun') ?>" class="tr-pilih-opd">
        <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
        <label for="tr-opd" class="form-label mb-1 small fw-bold">Perangkat daerah</label>
        <?php // Bisa dicari (Select2 otomatis, footer → pilih-cari.js), dikelompokkan Perangkat Daerah / Kecamatan.
        $grupOpd = ['Perangkat Daerah' => [], 'Kecamatan' => []];
        foreach ($scope['opd_list'] as $o) {
            $grupOpd[($o['jenis'] ?? '') === \App\Models\OpdModel::JENIS_KECAMATAN ? 'Kecamatan' : 'Perangkat Daerah'][] = $o;
        }
        $grupOpd = array_filter($grupOpd);
        ?>
        <select id="tr-opd" name="opd_id" class="form-select" onchange="this.form.submit()">
            <option value="">— pilih perangkat daerah —</option>
            <?php foreach ($grupOpd as $judulGrup => $isiGrup): ?>
                <optgroup label="<?= esc($judulGrup, 'attr') ?>">
                    <?php foreach ($isiGrup as $o): ?>
                        <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === (int) ($scope['opd_id'] ?? 0) ? 'selected' : '' ?>><?= esc($o['nama_opd']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </form>
<?php endif; ?>
