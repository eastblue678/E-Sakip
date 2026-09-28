<?php
/**
 * Daftar IKP + cakupan "Turun sampai" — AdminOpd\IkpTurunController::index
 * (adminopd/ikp/turun; adminkab/ikp/turun?opd_id= baca saja).
 *
 * @var array $baris   IkpRekapService::rekapOpd()
 * @var array $ringkas IkpTurunService::ringkasIkp() [ikp_id => baris, cakupan, periksa, teks]
 * @var array $label   label jenjang (kecamatan bergeser)
 * @var ?array $statusEkin IkpTurunService::statusEkin() (null = tidak ada baris pendelegasian / belum dibaca)
 * @var array $hitungEkin [ikp_id => [kode status => n]] (IkpTurunService::hitungStatusEkin)
 */
$statusEkin = $statusEkin ?? null;
$hitungEkin = $hitungEkin ?? [];
$n       = count($baris);
$turun   = 0;
$sampai  = 0;
foreach ($ringkas as $r) {
    $turun  += $r['baris'] > 0 ? 1 : 0;
    $sampai += $r['cakupan']['sampai_pelaksana'] ? 1 : 0;
}
$this->setVar('subJudul', $area === 'adminopd'
    ? 'Turunkan setiap IKP lewat pohon kinerja sampai pelaksana, supaya IKP masuk ke RHK pegawai di eKin.'
    : null);
?>
<?= $this->include('ikp/_turun_kepala') ?>

<?php if ($scope['opd_id'] === null): ?>
    <div class="ikp-kosong mt-3">
        <div class="ic"><i class="fas fa-sitemap"></i></div>
        <div class="fw-bold mb-1">Pilih perangkat daerah untuk melihat pendelegasian IKP-nya.</div>
        <p class="small mb-0">Rekap per perangkat daerah ada di tab <em>Rekap per OPD</em> (kolom "Turun sampai pelaksana").</p>
    </div>
<?php else: ?>

<div class="ikp-info">
    <i class="fas fa-sitemap"></i>
    <div>
        <p><strong>IKP turun lewat pohon kinerja, sama dengan IKU.</strong> Kepala OPD memegang IKP; IKP diturunkan ke indikator simpul
            <?= esc($label['es3'] ?? 'Eselon III') ?> → <?= esc($label['es4'] ?? 'Eselon IV') ?> → <?= esc($label['pelaksana'] ?? 'Pelaksana') ?>.
            Pemilik simpul (menu Pemilik Kinerja) otomatis memikulnya dan menariknya menjadi RHK di eKin.</p>
        <p class="small mb-0"><strong>Pemikul angka</strong>: IKP hitungan dibagi <em>porsi</em> (jumlah porsi = target atasannya); IKP posisi/rilis tidak dibagi — satu pemikul angka per jenjang memegang target utuh,
            kecuali posisi yang <em>dapat dipecah per bagian</em> (mis. pengikut beberapa akun resmi) yang dibagi porsi seperti hitungan. Pemikul angka harus bersatuan sama dengan IKP; satuan lain dihitung sebagai pendukung.
            <strong>Pendukung</strong>: ikut bekerja lewat indikator prosesnya sendiri (mis. bukti dukung penilaian indeks), tidak menambah angka IKP.</p>
        <p class="small mb-0">Setiap Simpan langsung dikirim ke eKin: SKP yang masih draf langsung berisi RHK-nya, SKP yang sudah diajukan/disetujui mendapat penugasan "IKP turunan" untuk diterima pegawai.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-4">
        <div class="ikp-kartu"><div class="kh"><div class="ki" style="background:#00743e"><i class="fas fa-bullseye"></i></div><div class="kt">IKP <?= (int) $tahun ?></div></div>
            <div class="kn" data-stat="ikp"><?= $n ?></div><div class="ks">indikator kinerja prioritas aktif</div></div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="ikp-kartu"><div class="kh"><div class="ki" style="background:#1971c2"><i class="fas fa-arrow-down-short-wide"></i></div><div class="kt">Sudah diturunkan</div></div>
            <div class="kn" data-stat="turun"><?= $turun ?><small> / <?= $n ?></small></div><div class="ks">minimal satu jenjang di bawah Kepala</div></div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="ikp-kartu"><div class="kh"><div class="ki" style="background:#b45309"><i class="fas fa-people-group"></i></div><div class="kt">Sampai pelaksana</div></div>
            <div class="kn" data-stat="sampai"><?= $sampai ?><small> / <?= $n ?></small></div><div class="ks">sudah dipikul jenjang pelaksana</div></div>
    </div>
</div>

<?php if ($statusEkin !== null && empty($statusEkin['terbaca'])): ?>
    <p class="small text-secondary mb-2" data-status-ekin="tidak_terbaca"><i class="fas fa-mobile-screen-button me-1"></i>Status di eKin belum dapat dibaca — <?= esc(rtrim((string) ($statusEkin['pesan'] ?? 'Data eKin belum tersedia.'), '.')) ?>. Pendelegasian tetap dikirim eKin saat pegawai membuka eKin dan setiap malam.</p>
<?php endif; ?>

<?php if ($baris === []): ?>
    <div class="ikp-kosong">
        <div class="ic"><i class="fas fa-bullseye"></i></div>
        <div class="fw-bold mb-1">Belum ada IKP untuk diturunkan.</div>
        <?php if ($area === 'adminopd'): ?><a href="<?= esc($u('adminopd/ikp'), 'attr') ?>" class="btn btn-outline-secondary btn-sm mt-2">Ke daftar IKP</a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="tr-daftar">
        <?php foreach ($baris as $r): ?>
            <?php
            $ikp  = $r['ikp'];
            $id   = (int) $ikp['id'];
            $rk   = $ringkas[$id] ?? null;
            $c    = $rk['cakupan'] ?? ['es3' => false, 'es4' => false, 'pelaksana' => false, 'sampai_pelaksana' => false];
            $pk   = $rk['periksa'] ?? null;
            $meta = $kategoriMeta[$ikp['kategori']] ?? null;
            ?>
            <article class="tr-kartu-ikp" data-ikp="<?= $id ?>" data-sampai="<?= $c['sampai_pelaksana'] ? 1 : 0 ?>">
                <div class="tr-atas">
                    <?php if ($meta): ?><span class="ikp-kat <?= esc($ikp['kategori'], 'attr') ?>"><i class="fas <?= esc($meta['ikon'], 'attr') ?>"></i><?= esc($meta['singkat']) ?></span><?php endif; ?>
                    <span class="ikp-pola <?= esc($r['pola']['pola'], 'attr') ?>"><?= esc(ikp_pola_ringkas($r['pola'], (int) $tahun)) ?></span>
                    <?php if ($c['sampai_pelaksana']): ?>
                        <span class="tr-lencana ok"><i class="fas fa-circle-check"></i>Sampai pelaksana</span>
                    <?php elseif (($rk['baris'] ?? 0) > 0): ?>
                        <span class="tr-lencana sebagian"><i class="fas fa-circle-half-stroke"></i>Sebagian</span>
                    <?php else: ?>
                        <span class="tr-lencana belum"><i class="fas fa-circle-minus"></i>Belum diturunkan</span>
                    <?php endif; ?>
                </div>
                <h3 class="tr-nama"><?= esc((string) $ikp['output_prioritas']) ?></h3>
                <div class="tr-sub">Target <?= (int) $tahun ?>: <strong><?= esc(ikp_fmt($r['target_tahunan'], 4)) ?></strong> <?= esc((string) ($ikp['satuan_label'] ?? '')) ?></div>
                <div class="tr-cakupan" aria-label="<?= esc($rk['teks'] ?? '', 'attr') ?>">
                    <span class="tr-cak-lbl">Turun sampai:</span>
                    <?php foreach (['es3', 'es4', 'pelaksana'] as $lv): ?>
                        <span class="tr-cak <?= $c[$lv] ? 'ya' : 'tidak' ?>"><?= esc($label[$lv] ?? $lv) ?> <?= $c[$lv] ? '✓' : '✗' ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (($rk['lama'] ?? 0) > 0): ?>
                    <div class="small text-secondary mt-1"><i class="fas fa-link me-1"></i><?= (int) $rk['lama'] ?> tautan lama ke indikator simpul (dibuat sebelum ada Turunkan IKP) — belum dihitung sebagai pendelegasian dan tidak dikirim ke eKin sebagai IKP turunan.</div>
                <?php endif; ?>
                <?php if (($rk['beda_satuan'] ?? 0) > 0): ?>
                    <div class="tr-efektif"><i class="fas fa-scale-unbalanced me-1"></i><?= (int) $rk['beda_satuan'] ?> pemikul angka bersatuan lain dari <?= esc((string) ($ikp['satuan_label'] ?? '')) ?> — dihitung sebagai pendukung.</div>
                <?php endif; ?>
                <?php if (($hitungEkin[$id] ?? []) !== []): ?>
                    <div class="tr-status-ekin m-0" data-status-ikp="<?= $id ?>">
                        <span class="lbl">Di eKin:</span>
                        <?php foreach (\App\Services\IkpTurunService::STATUS_EKIN as $k => $m): ?>
                            <?php if (! empty($hitungEkin[$id][$k])): ?>
                                <span class="tr-ekin <?= esc($m['kelas'], 'attr') ?>" title="<?= esc(strtr($m['judul'], ['{tahun}' => (string) (int) $tahun]), 'attr') ?>"><?= (int) $hitungEkin[$id][$k] ?> × <?= esc(strtr($m['label'], ['{tahun}' => (string) (int) $tahun])) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($pk !== null && ($rk['baris'] ?? 0) > 0): ?>
                    <div class="tr-periksa-ringkas <?= esc($pk['warna'], 'attr') ?>">
                        <i class="fas <?= $pk['warna'] === 'peringatan' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>
                        <span><?= esc($pk['warna'] === 'peringatan' ? $pk['pesan'] . ($pk['n_peringatan'] > 1 ? ' (+' . ($pk['n_peringatan'] - 1) . ' lainnya)' : '') : 'Pemeriksa: ' . $pk['pesan']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="tr-aksi">
                    <a class="btn btn-sm <?= $area === 'adminopd' && $bolehUbah ? 'btn-success' : 'btn-outline-success' ?>"
                       href="<?= esc($u('ikp/turun/' . $id, ['tahun' => $tahun]), 'attr') ?>">
                        <i class="fas <?= $area === 'adminopd' && $bolehUbah ? 'fa-sitemap' : 'fa-eye' ?> me-1"></i><?= $area === 'adminopd' && $bolehUbah ? (($rk['baris'] ?? 0) > 0 ? 'Atur pendelegasian' : 'Turunkan') : 'Lihat pohon' ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php endif; ?>

<?= $this->include('templates/shell_bawah') ?>
