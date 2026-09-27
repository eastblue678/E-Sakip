<?php
/**
 * AKSARA+ — tab Kinerja Prioritas (IKP) area OPD. Menu samping kini SATU butir
 * "Kinerja Prioritas (IKP)"; halaman-halamannya dipilih lewat tab ini, yang tampil di
 * SETIAP halaman IKP OPD (lewat ikp/_kepala, dan langsung di ikp/inovasi).
 *
 * Data view: $aktif (index|turun|breakdown|realisasi|rekap|inovasi), $tahun, opsional $u
 * (pembuat URL dari IkpController yang membawa opd_id untuk super admin).
 */
$bangun = (isset($u) && is_callable($u)) ? $u : static function (string $path, array $q = []): string {
    $opd = (int) (service('request')->getGet('opd_id') ?? 0);
    if ($opd > 0 && session('role') === 'admin') {
        $q = ['opd_id' => $opd] + $q;
    }
    $q = array_filter($q, static fn ($v) => $v !== null && $v !== '');

    return base_url($path) . ($q === [] ? '' : '?' . http_build_query($q));
};
$qt = ['tahun' => $tahun ?? null];
$tabs = [];
foreach ([
    'index'     => ['adminopd/ikp', 'fa-list-check', 'Indikator & Target'],
    'turun'     => ['adminopd/ikp/turun', 'fa-sitemap', 'Turunkan IKP'],
    'breakdown' => ['adminopd/ikp/breakdown', 'fa-table-cells', 'Breakdown Target'],
    'realisasi' => ['adminopd/ikp/realisasi', 'fa-pen-to-square', 'Realisasi Bulanan'],
    'rekap'     => ['adminopd/ikp/rekap', 'fa-chart-column', 'Rekap Triwulan'],
    'inovasi'   => ['adminopd/ikp/inovasi', 'fa-lightbulb', 'Rencana Inovasi'],
] as $kunci => [$path, $ikon, $label]) {
    $tabs[] = ['url' => $bangun($path, $qt), 'label' => $label, 'ikon' => $ikon, 'aktif' => ($aktif ?? '') === $kunci];
}
$tabs[] = ['url' => $bangun('adminopd/ikp/lampiran-pk', $qt), 'label' => 'Cetak Lampiran PK', 'ikon' => 'fa-file-pdf', 'baru' => true];
?>
<?= view('templates/tab_halaman', ['tabs' => $tabs, 'label' => 'Menu Kinerja Prioritas'], ['saveData' => false]) ?>
