<?php
/**
 * Grid realisasi bulanan semua IKP — AdminOpd\IkpController::realisasi.
 * Isian realisasi per bulan (target tampil kecil di bawahnya), rekap TW +
 * capaian berwarna, catatan/bukti per bulan (modal), simpan per baris ke
 * POST ikp/realisasi/save. Bulan setelah bulan berjalan (WIB) dikunci.
 *
 * @var array $rekap         IkpRekapService::rekapOpd()
 * @var int   $bulanTerbuka  0..12
 * @var string $kategori
 * @var array $saranEkin [ikp_id => [bulan => IkpTurunService::saranRealisasi()]] (kosong = eKin belum menyediakan)
 */
$saranEkin = $saranEkin ?? [];
$js = static fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$metodeSingkat = ['sum' => 'Akumulasi', 'trend_naik' => 'Posisi ↑', 'trend_turun' => 'Posisi ↓', 'trend_flat' => 'Tetap'];
$palet = \App\Models\DashboardThresholdModel::COLORS;
$fmt4 = static fn ($v) => $v === null ? '' : ikp_fmt((float) $v, 4);
$sel  = static function (array $t) {
    if (($t['diukur'] ?? true) === false) {
        // Triwulan tanpa bulan ukur: capaian TIDAK dihitung (bukan 0, bukan 100).
        return '<span class="sel-tidak-ukur" title="' . esc((string) $t['keterangan'], 'attr') . '">—<small>'
            . esc($t['status'] === 'menunggu_rilis' ? 'bukan bulan rilis' : 'tidak diukur') . '</small></span>';
    }
    if ($t['realisasi'] === null && $t['target'] === null) {
        return '<span class="bulan-kunci">–</span>';
    }
    $h = '<div class="ikp-tw"><span class="r">' . esc(ikp_fmt($t['realisasi'], 4)) . '</span><span class="t"> / ' . esc(ikp_fmt($t['target'], 4)) . '</span></div>';
    if ($t['capaian'] !== null || $t['status'] !== 'belum_ada_data') {
        $h .= ikp_lencana_status($t['warna'], $t['capaian'] !== null ? capaianFormatPersen($t['capaian']) : $t['status_label'], $t['keterangan']);
    }
    if (! empty($t['berjalan'])) {
        $h .= '<div class="t" style="font-size:.66rem;color:#7b8a80">berjalan'
            . (! empty($t['sampai_bulan']) ? ' · capaian s.d. ' . esc(ikp_nama_bulan((int) $t['sampai_bulan'], true)) : '') . '</div>';
    }

    return $h;
};
?>
<?= $this->include('ikp/_kepala') ?>

<div class="ikp-info">
    <i class="fas fa-pen-to-square"></i>
    <div>
        <p>Isi <strong>realisasi</strong> pada bulan ukur setiap IKP; target bulan itu tampil kecil di bawah kotak. Rekap triwulan dan capaian dihitung otomatis dari bulan yang sudah terisi.
            Angka <strong>0 adalah realisasi yang sah</strong> (tidak sama dengan kosong); kosongkan kotak untuk menghapus realisasi.</p>
        <?php if (! empty($saranEkin)): ?>
            <p class="small mb-1"><span class="saran-ekin d-inline-flex">eKin <b>n</b></span> = saran <strong>Dari eKin (pemikul angka)</strong>: IKP hitungan = jumlah realisasi porsi para pemikul
                IKP turunan (* = belum semua melapor); posisi/rilis = nilai pemikul angka jenjang terdekat. Tekan <em>Gunakan</em> lalu Simpan — realisasi resmi tetap diisi di sini.</p>
        <?php endif; ?>
        <p class="small mb-1">Sel <strong>"—"</strong> = bulan yang tidak diukur menurut pola ukur IKP itu (posisi triwulanan/semesteran, atau indeks resmi di luar bulan rilisnya) — tidak diisi dan tidak dihitung.
            IKP <span class="ikp-pola rilis">Rilis</span> diisi lewat tombol <em>Catat nilai resmi</em> saat nilainya keluar, wajib dengan tautan bukti publikasi.</p>
        <p class="small text-secondary mb-0">
            <?php if ($bulanTerbuka >= 12): ?>
                Seluruh bulan <?= (int) $tahun ?> sudah dapat diisi.
            <?php elseif ($bulanTerbuka <= 0): ?>
                Tahun <?= (int) $tahun ?> belum berjalan, realisasi belum dapat diisi.
            <?php else: ?>
                Bulan setelah <?= esc(ikp_nama_bulan($bulanTerbuka)) ?> <?= (int) $tahun ?> dikunci karena belum berjalan (waktu WIB).
            <?php endif; ?>
            Ikon <i class="fas fa-paperclip"></i> di bawah kotak untuk keterangan &amp; tautan bukti dukung.
        </p>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
    <form method="get" action="<?= base_url('adminopd/ikp/realisasi') ?>" class="d-flex gap-2 align-items-center">
        <?php if (! empty($scope['can_pick'])): ?><input type="hidden" name="opd_id" value="<?= (int) $scope['opd_id'] ?>"><?php endif; ?>
        <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
        <select name="kategori" class="form-select form-select-sm" data-no-select2 onchange="this.form.submit()" aria-label="Saring kategori">
            <option value="">Semua kategori</option>
            <?php foreach ($kategoriList as $k => $label): ?>
                <option value="<?= $k ?>" <?= $kategori === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="<?= esc($u('adminopd/ikp/rekap', ['tahun' => $tahun]), 'attr') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-column me-1"></i>Rekap triwulan</a>
</div>

<?php if ($rekap === []): ?>
    <div class="ikp-kosong">
        <div class="ic"><i class="fas fa-pen-to-square"></i></div>
        <div class="fw-bold mb-1">Belum ada IKP untuk diisi realisasinya.</div>
        <a href="<?= esc($u('adminopd/ikp'), 'attr') ?>" class="btn btn-outline-secondary btn-sm mt-2">Ke daftar IKP</a>
    </div>
<?php else: ?>
    <div class="ikp-grid-wrap">
        <table class="ikp-grid" data-no-paginate id="grid-realisasi"
               data-tahun="<?= (int) $tahun ?>" data-terbuka="<?= (int) $bulanTerbuka ?>"
               data-url-simpan="<?= esc($u('adminopd/ikp/realisasi/save'), 'attr') ?>"
               data-palet="<?= esc($js($palet), 'attr') ?>">
            <thead>
                <tr>
                    <th class="lekat text-start">Indikator Kinerja Prioritas</th>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <th<?= $m > $bulanTerbuka ? ' style="opacity:.6"' : '' ?>><?= esc(ikp_nama_bulan($m, true)) ?><?= $m > $bulanTerbuka ? ' <i class="fas fa-lock" style="font-size:.6rem"></i>' : '' ?></th>
                    <?php endfor; ?>
                    <?php for ($q = 1; $q <= 4; $q++): ?><th class="tw">TW <?= capaianRomawi($q) ?></th><?php endfor; ?>
                    <th class="tw">s.d. Bulan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rekap as $r): ?>
                    <?php
                    $ikp    = $r['ikp'];
                    $id     = (int) $ikp['id'];
                    $pola   = $r['pola'];
                    $metode = (string) $pola['metode'];
                    $rilis  = $pola['pola'] === 'rilis';
                    $tb     = $r['tahun_berjalan'];
                    ?>
                    <tr data-ikp="<?= $id ?>" data-pola="<?= esc($pola['pola'], 'attr') ?>" data-penerbit="<?= esc((string) ($pola['penerbit'] ?? ''), 'attr') ?>">
                        <td class="lekat">
                            <div class="nama"><?= esc(mb_strimwidth((string) $ikp['output_prioritas'], 0, 120, '…')) ?></div>
                            <div class="sub"><?= esc($ikp['satuan_label'] !== '' ? $ikp['satuan_label'] : '-') ?> ·
                                <?= $metode !== '' ? '<span class="ikp-pola ' . esc($pola['pola'], 'attr') . '" title="' . esc(ikp_pola_meta()[$pola['pola']]['realisasi'], 'attr') . '">' . esc(ikp_pola_ringkas($pola, (int) $tahun)) . '</span>' : '<span class="text-danger">pola ukur belum dikonfirmasi</span>' ?>
                                · target <?= esc(ikp_fmt($r['target_tahunan'], 4)) ?>
                                <?php if (($r['kelengkapan']['abaikan'] ?? 0) > 0): ?><span class="d-block text-secondary" style="font-size:.66rem" title="Isian di bulan yang tidak diukur tetap tersimpan tetapi tidak dihitung"><i class="fas fa-eye-slash me-1"></i><?= (int) $r['kelengkapan']['abaikan'] ?> isian lama di bulan non-ukur diabaikan</span><?php endif; ?></div>
                        </td>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <?php
                            $b      = $r['bulan'][$m];
                            $adaCat = ($b['keterangan'] ?? '') !== '' || ($b['bukti_url'] ?? '') !== '';
                            ?>
                            <?php if (! $b['diukur']): ?>
                                <td data-bulan="<?= $m ?>" data-ukur="0" title="<?= esc($b['keadaan_ket'], 'attr') ?>">
                                    <span class="sel-tidak-ukur">—<small><?= $rilis ? 'bukan bulan rilis' : 'tidak diukur' ?></small></span>
                                </td>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php $kunci = ! $b['terbuka'] || ! $bolehUbah; ?>
                            <td data-bulan="<?= $m ?>" data-ket="<?= esc((string) ($b['keterangan'] ?? ''), 'attr') ?>" data-bukti="<?= esc((string) ($b['bukti_url'] ?? ''), 'attr') ?>"
                                <?= $b['keadaan'] !== 'diukur' ? 'title="' . esc($b['keadaan_ket'], 'attr') . '"' : '' ?>>
                                <div class="sel-bulan">
                                    <input type="text" class="isian" data-nol="sah" data-bulan="<?= $m ?>" <?= $rilis ? 'data-rilis="1" readonly' : '' ?>
                                           aria-label="<?= $rilis ? 'Nilai resmi ' . esc(ikp_bulan_rilis_label($pola, (int) $tahun, $m, false), 'attr') : 'Realisasi ' . esc(ikp_nama_bulan($m), 'attr') ?>"
                                           value="<?= esc($fmt4($b['realisasi']), 'attr') ?>" placeholder="<?= $b['terbuka'] ? '–' : '' ?>" <?= $kunci ? 'disabled' : '' ?>>
                                    <span class="tgt" title="Target <?= esc(ikp_nama_bulan($m), 'attr') ?>">T: <?= esc(ikp_fmt($b['target'], 4)) ?></span>
                                    <?php $sr = $saranEkin[$id][$m] ?? null; ?>
                                    <?php if ($sr !== null && ! $kunci): ?>
                                        <?php
                                        $srSama  = $b['realisasi'] !== null && abs((float) $b['realisasi'] - (float) $sr['nilai']) < 0.00005;
                                        $srJudul = 'Dari eKin (pemikul angka): ' . ($sr['cara'] === 'jumlah_porsi'
                                            ? 'jumlah hasil ' . count($sr['pegawai']) . ' pemikul (setiap hasil dihitung sekali oleh pemiliknya, termasuk porsi yang dibagi lewat Cascading)' . ($sr['lengkap'] ? '' : ' — belum semua pemikul terbawah melapor')
                                            : 'nilai yang dilaporkan pemikul angka jenjang terdekat') . '. Realisasi resmi tetap Anda yang mengisi.';
                                        ?>
                                        <span class="saran-ekin<?= $srSama ? ' sama' : '' ?>" data-saran="<?= esc((string) $sr['nilai'], 'attr') ?>" title="<?= esc($srJudul, 'attr') ?>">
                                            eKin <b><?= esc(ikp_fmt((float) $sr['nilai'], 4)) ?></b><?= $sr['lengkap'] ? '' : '*' ?>
                                            <?php if (! $srSama): ?><button type="button" data-gunakan="<?= esc(ikp_fmt((float) $sr['nilai'], 4), 'attr') ?>" aria-label="Gunakan nilai eKin <?= esc(ikp_fmt((float) $sr['nilai'], 4), 'attr') ?> untuk <?= esc(ikp_nama_bulan($m), 'attr') ?>">Gunakan</button><?php else: ?>✓<?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($rilis && $b['keadaan'] === 'belum_waktunya'): ?>
                                        <span class="tgt" style="color:#9a6b00">menunggu rilis</span>
                                    <?php elseif ($rilis && ! $kunci): ?>
                                        <button type="button" class="cat catat-rilis<?= $adaCat ? ' ada' : '' ?>" data-bulan="<?= $m ?>" title="Catat nilai resmi beserta tautan bukti publikasi"><i class="fas fa-certificate"></i> Catat nilai resmi</button>
                                    <?php else: ?>
                                        <button type="button" class="cat<?= $adaCat ? ' ada' : '' ?>" data-bulan="<?= $m ?>" <?= $b['terbuka'] ? '' : 'disabled' ?>
                                                title="<?= $adaCat ? 'Lihat/ubah keterangan & bukti' : 'Tambah keterangan & bukti' ?>"><i class="fas fa-paperclip"></i><?= $adaCat ? ' ada' : '' ?></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endfor; ?>
                        <?php for ($q = 1; $q <= 4; $q++): ?>
                            <td class="tw" data-tw="<?= $q ?>"><?= $sel($r['triwulan'][$q]) ?></td>
                        <?php endfor; ?>
                        <td class="tw" data-ytd>
                            <?php if ($tb['persen'] !== null): ?><div class="ikp-persen" style="font-size:.9rem"><?= esc(capaianFormatPersen($tb['persen'])) ?></div><?php endif; ?>
                            <?= ikp_lencana_status($tb['warna'], $tb['status_label'], $tb['keterangan']) ?>
                            <?php if ($tb['sampai_bulan']): ?><div class="t" style="font-size:.66rem;color:#7b8a80">s.d. <?= esc(ikp_nama_bulan((int) $tb['sampai_bulan'], true)) ?></div><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($bolehUbah && $bulanTerbuka > 0): ?>
                                <button type="button" class="btn btn-success btn-sm tombol-simpan" title="Simpan baris (Enter)"><i class="fas fa-floppy-disk"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-secondary mt-2 mb-0"><i class="fas fa-circle-info me-1"></i>Capaian triwulan hanya memakai bulan ukur yang realisasinya terisi (triwulan berjalan ditandai "berjalan"). Hitungan = jumlah realisasi ÷ jumlah target; posisi &amp; rilis = nilai ukur terakhir ÷ target bulan itu. Triwulan tanpa bulan ukur tampil "—" dan tidak dihitung. Warna status mengikuti ambang capaian Pengaturan Dashboard.</p>
<?php endif; ?>

<!-- Modal keterangan & bukti -->
<div class="modal fade" id="modal-catatan" tabindex="-1" aria-labelledby="modal-catatan-judul" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold" id="modal-catatan-judul">Keterangan &amp; bukti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="small text-secondary mb-2" id="modal-catatan-ikp"></div>
                <div id="blok-nilai-rilis" hidden>
                    <label class="form-label wajib" for="cat-nilai">Nilai resmi yang dirilis</label>
                    <input type="text" class="form-control isian w-100 text-start mb-1" data-nol="sah" id="cat-nilai" placeholder="mis. 97,25">
                    <div class="form-text mb-3" id="cat-nilai-ket">Tulis persis nilai yang diumumkan penerbit. Nilai rilis tidak dicicil dan tidak dijumlah.</div>
                </div>
                <label class="form-label" for="cat-keterangan">Keterangan (opsional)</label>
                <textarea class="form-control mb-3" id="cat-keterangan" rows="3" maxlength="2000" placeholder="mis. Penambahan 21 nasabah dari 3 bank sampah unit baru"></textarea>
                <label class="form-label" for="cat-bukti" id="cat-bukti-label">Tautan bukti dukung (opsional)</label>
                <input type="url" class="form-control" id="cat-bukti" maxlength="500" placeholder="https://…">
                <div class="form-text">Tautan ke dokumen/foto (mis. Google Drive). Harus diawali http:// atau https://.</div>
                <div class="mt-2" id="cat-bukti-buka" hidden><a href="#" target="_blank" rel="noopener noreferrer"><i class="fas fa-up-right-from-square me-1"></i>Buka tautan bukti</a></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                <?php if ($bolehUbah): ?>
                    <button type="button" class="btn btn-success" id="cat-simpan"><i class="fas fa-floppy-disk me-1"></i>Simpan catatan</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script defer src="<?= base_url('assets/js/adminopd/ikp/ikp-angka.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/adminopd/ikp/ikp-angka.js') ?>"></script>
<script defer src="<?= base_url('assets/js/adminopd/ikp/ikp-realisasi.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/adminopd/ikp/ikp-realisasi.js') ?>"></script>

<?= $this->include('templates/shell_bawah') ?>
