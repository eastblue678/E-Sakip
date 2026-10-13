<?php
/**
 * Isian pilihan bisa dicari (Select2) + kotak saring daftar — dimuat footer
 * adminOpd & adminKabupaten SESUDAH jQuery dan Select2.
 * Aturan & API: public/assets/js/pilih-cari.js.
 */
$jsPc  = FCPATH . 'assets/js/pilih-cari.js';
$cssPc = FCPATH . 'assets/css/pilih-cari.css';
?>
<link rel="stylesheet" href="<?= base_url('assets/css/pilih-cari.css') ?>?v=<?= is_file($cssPc) ? filemtime($cssPc) : '1' ?>">
<script src="<?= base_url('assets/js/pilih-cari.js') ?>?v=<?= is_file($jsPc) ? filemtime($jsPc) : '1' ?>"></script>
