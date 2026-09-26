<?php
/**
 * Kepala bersama halaman IKP (admin OPD): cangkang shell_atas + judul +
 * pemilih tahun + navigasi antarhalaman IKP.
 *
 * Dipanggil: <?= $this->include('ikp/_kepala') ?> — partial ini hanya melihat
 * DATA view (dari controller / setVar), bukan variabel lokal berkas pemanggil.
 *
 * Data yang dibaca: $title, $scope, $tahun, $tahunList, $periode, $aktif, $u,
 * opsional $tanpaTahun (form), $subJudul.
 */
$this->setVar('shellCss', (string) @file_get_contents(FCPATH . 'assets/css/ikp.css'));

$req  = service('request');
$qs   = $req->getGet();
$periodeTeks = 'Periode RPJMD ' . (int) $periode['awal'] . '–' . (int) $periode['akhir'];
?>
<?= $this->include('templates/shell_atas') ?>

<div class="ikp-kepala">
    <div class="ic"><i class="fas fa-bullseye"></i></div>
    <div class="isi">
        <h2><?= esc($title ?? 'Kinerja Prioritas (IKP)') ?></h2>
        <p>
            <i class="fas fa-building me-1"></i><?= esc($scope['opd_nama'] ?? '-') ?>
            <span class="mx-1">·</span><?= esc($periodeTeks) ?>
            <?php if (! empty($scope['can_pick'])): ?>
                <span class="mx-1">·</span><a href="<?= base_url('adminopd/ikp') ?>" class="small">ganti OPD</a>
            <?php endif; ?>
        </p>
        <?php if (! empty($subJudul)): ?><p class="mt-1"><?= esc($subJudul) ?></p><?php endif; ?>
    </div>
    <?php if (empty($tanpaTahun)): ?>
        <nav class="ikp-tahun" aria-label="Pilih tahun">
            <span class="lbl">Tahun</span>
            <?php foreach ($tahunList as $th): ?>
                <?php $q = $qs; $q['tahun'] = $th; ?>
                <a href="<?= esc(current_url() . '?' . http_build_query($q), 'attr') ?>"
                   class="<?= (int) $th === (int) $tahun ? 'aktif' : '' ?>"
                   <?= (int) $th === (int) $tahun ? 'aria-current="true"' : '' ?>><?= (int) $th ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</div>

<?php /* AKSARA+ — tab bersama semua halaman IKP OPD (menu samping kini satu butir) */ ?>
<?= $this->include('ikp/_tab_opd') ?>
