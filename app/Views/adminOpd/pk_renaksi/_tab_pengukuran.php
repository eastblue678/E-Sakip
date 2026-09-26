<?php
/**
 * AKSARA+ — tab Pengukuran Kinerja di halaman Target & Rencana Aksi dan MONEV.
 *
 * MENGAPA: menu kabupaten dulu memuat empat tautan (Target Rencana Aksi: PK Bupati /
 * PK OPD; Monitoring: PK Bupati / PK OPD). Kini satu butir menu per konsep, dan
 * pilihan PK Bupati vs PK Perangkat Daerah + Target vs MONEV ada di sini sebagai tab.
 * Semua alamat yang dituju adalah rute lama pada AREA yang sama ($base dari
 * PkRenaksiController::base), jadi Bupati tetap di /bupati dan admin OPD di /adminopd.
 *
 * Data view: $jenis (bupati|es3), $base (adminkab|adminopd|bupati), $halaman (renaksi|monev), opsional $tahun.
 */
$halaman = $halaman ?? 'renaksi';
$qTahun  = (isset($tahun) && $tahun !== 'all' && $tahun !== '' && $tahun !== null) ? '?tahun=' . (int) $tahun : '';
if ($base === 'adminopd') {
    $tabs = [
        ['url' => 'adminopd/target_renaksi' . $qTahun, 'label' => 'Target & Rencana Aksi', 'ikon' => 'fa-list-check', 'aktif' => $halaman === 'renaksi'],
        ['url' => 'adminopd/monev' . $qTahun, 'label' => 'Monitoring (MONEV)', 'ikon' => 'fa-chart-line', 'aktif' => $halaman === 'monev'],
    ];
} else {
    $bupati = $jenis === 'bupati';
    $tabs = [
        ['grup' => 'Dokumen', 'url' => ($bupati ? $base . '/target_renaksi' : $base . '/renaksi_pk/es3') . $qTahun, 'label' => 'Target & Rencana Aksi', 'ikon' => 'fa-list-check', 'aktif' => $halaman === 'renaksi'],
        ['grup' => 'Dokumen', 'url' => ($bupati ? $base . '/monev' : $base . '/monev_pk/es3') . $qTahun, 'label' => 'Monitoring (MONEV)', 'ikon' => 'fa-chart-line', 'aktif' => $halaman === 'monev'],
        ['grup' => 'Lingkup', 'url' => ($halaman === 'monev' ? $base . '/monev' : $base . '/target_renaksi') . $qTahun, 'label' => 'PK Bupati', 'ikon' => 'fa-landmark', 'aktif' => $bupati],
        ['grup' => 'Lingkup', 'url' => ($halaman === 'monev' ? $base . '/monev_pk/es3' : $base . '/renaksi_pk/es3') . $qTahun, 'label' => 'PK Perangkat Daerah / Kecamatan', 'ikon' => 'fa-building', 'aktif' => ! $bupati],
    ];
}
?>
<?= view('templates/tab_halaman', ['tabs' => $tabs, 'label' => 'Pengukuran Kinerja'], ['saveData' => false]) ?>
