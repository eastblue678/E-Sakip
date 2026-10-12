<?php
/**
 * Kotak "Jalur darurat — penugasan tambahan di eKin" satu IKP (eKin README §25, AKSARA+ hanya MEMBACA & menautkan).
 * Jalur utama IKP tetap pohon kinerja (Turunkan IKP); jalur ini untuk IKP yang memang tidak bisa masuk pohon dan
 * ditugaskan di eKin atas nama Kepala OPD dengan alasan wajib.
 *
 * @var list<array<string,mixed>> $rows   IkpTurunService::indeksTambahan()[ikp_id] (boleh kosong)
 * @var int                       $ikpId
 * @var int                       $tahun
 * @var string                    $tautan EkinClient::tautanPenugasanIkp()
 * @var bool                      $terbaca kontrak eKin terbaca?
 */
$rows    = $rows ?? [];
$alasan  = array_values(array_unique(array_filter(array_map(static fn ($r) => trim((string) ($r['alasan'] ?? '')), $rows))));
$bulanP  = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
?>
<?php if ($rows !== []): ?>
    <div class="tr-darurat" data-darurat-ikp="<?= (int) $ikpId ?>">
        <div class="tr-darurat-judul"><i class="fas fa-life-ring me-1"></i>Jalur darurat — penugasan tambahan di eKin</div>
        <ul>
            <?php foreach ($rows as $r): ?>
                <?php $st = \App\Services\IkpTurunService::STATUS_TAMBAHAN[$r['status'] ?? ''] ?? ['label' => (string) ($r['status_label'] ?? $r['status'] ?? ''), 'kelas' => 'abu']; ?>
                <li data-pemikul-darurat="<?= (int) ($r['pegawai_id'] ?? 0) ?>" data-status="<?= esc((string) ($r['status'] ?? ''), 'attr') ?>">
                    <span class="tr-ekin <?= esc($st['kelas'], 'attr') ?>"><?= esc($st['label']) ?></span>
                    <b title="<?= esc((string) ($r['pegawai_jabatan'] ?? ''), 'attr') ?>"><?= esc(\App\Services\PohonPemilikService::namaPendek((string) ($r['pegawai_nama'] ?? ('Pegawai #' . (int) ($r['pegawai_id'] ?? 0))))) ?></b>
                    <span>· target <?= esc(ikp_fmt(isset($r['target']) ? (float) $r['target'] : null, 4)) ?> <?= esc((string) ($r['satuan'] ?? '')) ?></span>
                    <span class="text-secondary">· <?= esc(($bulanP[(int) ($r['bulan_mulai'] ?? 0)] ?? '?') . '–' . ($bulanP[(int) ($r['bulan_selesai'] ?? 0)] ?? '?') . ' ' . (int) $tahun) ?></span>
                    <?php if (($r['status'] ?? '') === 'diterima' && isset($r['realisasi_total']) && $r['realisasi_total'] !== null): ?>
                        <span class="text-secondary">· realisasi eKin <?= esc(ikp_fmt((float) $r['realisasi_total'], 4)) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php foreach ($alasan as $a): ?>
            <div class="alasan" data-alasan-darurat>Mengapa tidak masuk pohon kinerja: “<?= esc($a) ?>”</div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
