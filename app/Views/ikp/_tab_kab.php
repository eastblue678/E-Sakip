<?php
/**
 * AKSARA+ — tab Kinerja Prioritas (IKP) area Kabupaten (admin_kab, inspektorat, super admin).
 * Menu samping kini SATU butir; rekap per OPD, per Program Unggulan, Pemilik Kinerja
 * lintas OPD, dan cetak rekap dipilih lewat tab ini di setiap halamannya.
 * Tahun & bulan yang sedang dilihat ikut terbawa bila ada di alamat.
 */
$req  = service('request');
$jalur = trim((string) $req->getUri()->getPath(), '/');
$q    = array_filter(['tahun' => $req->getGet('tahun'), 'bulan' => $req->getGet('bulan')], static fn ($v) => $v !== null && $v !== '');
$qs   = $q === [] ? '' : '?' . http_build_query($q);
$tabs = [
    ['url' => 'adminkab/ikp' . $qs, 'label' => 'Rekap per OPD', 'ikon' => 'fa-table-list',
     'aktif' => $jalur === 'adminkab/ikp' || str_starts_with($jalur, 'adminkab/ikp/opd')],
    ['url' => 'adminkab/ikp/program-unggulan' . $qs, 'label' => 'Per Program Unggulan', 'ikon' => 'fa-shapes',
     'aktif' => str_starts_with($jalur, 'adminkab/ikp/program-unggulan')],
];
if (user_can('pemilik_kinerja.view')) {
    $tabs[] = ['url' => 'adminkab/pemilik-kinerja' . (isset($q['tahun']) ? '?tahun=' . (int) $q['tahun'] : ''), 'label' => 'Pemilik Kinerja', 'ikon' => 'fa-sitemap',
               'aktif' => str_starts_with($jalur, 'adminkab/pemilik-kinerja')];
}
$tabs[] = ['url' => 'adminkab/ikp/cetak' . $qs, 'label' => 'Cetak Rekap', 'ikon' => 'fa-file-pdf', 'baru' => true];
?>
<?= view('templates/tab_halaman', ['tabs' => $tabs, 'label' => 'Menu Kinerja Prioritas'], ['saveData' => false]) ?>
