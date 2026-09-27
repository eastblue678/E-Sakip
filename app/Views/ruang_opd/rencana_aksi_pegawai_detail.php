<?php
/**
 * Ruang OPD — rencana aksi setahun satu pegawai (AKSARA+). Data: eKin api/aksara/pegawai/{id}/rencana-aksi.
 * Kisi RHK → IKI kuantitas → bulan 1–12: target (T) dan realisasi terhitung (R) dari kinerja harian disetujui.
 *
 * @var array|null $data
 * @var array|null $pk        PKRINGKAS pegawai ini (status PK Pegawai)
 * @var int        $bulanKini
 */
$this->setVar('aktifTab', 'ra');
$this->setVar('shellCss', $shellCss);

$bulanPendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$angka = static function ($v): string {
    if ($v === null) {
        return '–';
    }
    $v = (float) $v;

    return abs($v - round($v)) < 0.0001 ? number_format($v, 0, ',', '.') : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
};
$skpLbl = ['disetujui' => ['s-hijau', 'SKP disetujui'], 'diajukan' => ['s-kuning', 'SKP diajukan'], 'draf' => ['s-abu', 'SKP draf'], 'dikembalikan' => ['s-merah', 'SKP dikembalikan']];
$pkLbl = [
    'ditandatangani' => ['s-hijau', 'PK ditandatangani'], 'lewat_aksara' => ['s-biru', 'PK di AKSARA'], 'diajukan' => ['s-kuning', 'PK diajukan'],
    'draf' => ['s-abu', 'PK draf'], 'dikembalikan' => ['s-merah', 'PK dikembalikan'], 'belum_ada_skp' => ['s-abu', 'Belum ada SKP'],
];
$aspekLbl = ['kuantitas' => 'Kuantitas', 'kualitas' => 'Kualitas', 'waktu' => 'Waktu', 'biaya' => 'Biaya'];
$kembali = base_url('ruang-opd/' . (int) $opd['id'] . '/rencana-aksi-pegawai?tahun=' . (int) $tahun);
$tahunIni = (int) date('Y');
/** Kelas sel: tercapai / berjalan (bulan ini) / kurang (bulan lalu belum tercapai) / rencana (bulan depan). */
$kelasSel = static function (array $ra) use ($bulanKini, $tahun, $tahunIni): string {
    if (! empty($ra['tercapai'])) {
        return 'c-capai';
    }
    // Realisasi Kepala OPD dari IKP AKSARA yang belum dilaporkan: netral, bukan "belum tercapai".
    if (($ra['sumber_realisasi'] ?? '') === 'ikp' && empty($ra['ikp_dilaporkan'])) {
        return 'c-belum';
    }
    $lalu = $tahun < $tahunIni || ($tahun === $tahunIni && $ra['bulan'] < $bulanKini);
    $kini = $tahun === $tahunIni && $ra['bulan'] === $bulanKini;

    return $lalu ? 'c-kurang' : ($kini ? 'c-jalan' : 'c-rencana');
};
?>
<?= $this->include('ruang_opd/_kepala') ?>

<style>
  .rad-kepala { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: flex-start; justify-content: space-between; background: #fff; border: 1px solid #e3e9e5; border-radius: 14px; padding: 14px 16px; margin-bottom: 14px; }
  .rad-kepala h3 { font-size: 1.05rem; font-weight: 800; color: #1f3a2a; margin: 0; }
  .rad-kepala .sub { font-size: .8rem; color: #5d6b62; }
  .rad-legenda { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: .74rem; color: #4d5e53; margin-bottom: 10px; }
  .rad-legenda i { display: inline-block; width: 11px; height: 11px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
  .rad-rhk { background: #fff; border: 1px solid #e3e9e5; border-radius: 14px; padding: 12px 14px; margin-bottom: 12px; }
  .rad-rhk-kepala { display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap; }
  .rad-jenis { font-size: .62rem; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; border-radius: 6px; padding: 3px 7px; color: #fff; background: #00743e; }
  .rad-jenis.tambahan { background: #b7791f; }
  .rad-rhk h4 { font-size: .92rem; font-weight: 700; color: #1f3a2a; margin: 0; flex: 1 1 300px; }
  .rad-atasan { font-size: .76rem; color: #5d6b62; margin: 4px 0 6px; }
  .rad-iki { display: flex; flex-wrap: wrap; gap: 4px 8px; margin-bottom: 8px; }
  .rad-iki span { font-size: .72rem; border: 1px solid #e3e9e5; background: #f8faf9; border-radius: 8px; padding: 2px 8px; color: #3f5247; }
  .rad-iki b { color: #1f3a2a; }
  .rad-kisi { width: 100%; border-collapse: separate; border-spacing: 3px; font-size: .72rem; min-width: 860px; }
  .rad-kisi th { font-size: .66rem; font-weight: 800; color: #5d6b62; text-transform: uppercase; text-align: center; padding: 2px; }
  .rad-kisi th.kiri, .rad-kisi td.kiri { text-align: left; width: 200px; color: #3f5247; font-weight: 600; }
  .rad-kisi th.kini { color: #00743e; }
  .rad-kisi td.sel { border-radius: 7px; padding: 4px 5px; text-align: center; vertical-align: top; background: #f6f8f7; color: #9aa7a0; }
  .rad-kisi td.sel .ra + .ra { margin-top: 3px; padding-top: 3px; border-top: 1px dashed rgba(0,0,0,.12); }
  .rad-kisi .t { display: block; font-weight: 700; }
  .rad-kisi .r { display: block; }
  .rad-kisi .jenis { font-size: .58rem; font-weight: 800; opacity: .75; }
  .c-capai { background: #e3f4ea !important; color: #0f5132 !important; }
  .c-jalan { background: #fff4d6 !important; color: #7a5300 !important; }
  .c-kurang { background: #fdecea !important; color: #9b1c1c !important; }
  .c-rencana { background: #f2f4f3 !important; color: #5d6b62 !important; }
  .c-belum { background: #fff !important; color: #7b8a80 !important; outline: 1px dashed #c9d3cc; outline-offset: -1px; }
  .rad-ikp { display: inline-flex; align-items: center; gap: 5px; font-size: .66rem; font-weight: 700; color: #3730a3; background: #eef2ff; border-radius: 6px; padding: 2px 7px; }
</style>

<p class="mb-2 ro-noprint"><a href="<?= esc($kembali, 'attr') ?>" data-ro-tautan><i class="fas fa-arrow-left me-1"></i>Kembali ke daftar rencana aksi pegawai</a></p>

<?php if ($data === null): ?>
  <div class="ro-kosong">
    <div class="ic"><i class="fas fa-plug-circle-xmark"></i></div>
    <h5>Data eKin belum tersedia</h5>
    <p class="small mb-0"><?= esc($ekinPesan) ?></p>
  </div>
<?php else:
    $p   = $data['pegawai'];
    $skp = $data['skp'];
    [$sc, $sl] = $skp === null ? ['s-merah', 'Belum ada SKP'] : ($skpLbl[$skp['status']] ?? ['s-abu', 'SKP ' . $skp['status']]);
    $pkS = (string) ($pk['status'] ?? '');
    $jml = ['ra' => 0, 'capai' => 0];
    foreach ($data['rhk'] as $r) {
        foreach ($r['rencana_aksi'] as $ra) {
            $jml['ra']++;
            $jml['capai'] += ! empty($ra['tercapai']) ? 1 : 0;
        }
    }
?>
  <div class="rad-kepala">
    <div>
      <h3><?= esc((string) $p['nama']) ?><?php if (! empty($p['fiktif'])): ?> <span class="ro-fiktif">FIKTIF</span><?php endif; ?></h3>
      <div class="sub"><?= esc((string) $p['jabatan']) ?><?= ! empty($p['unit_kerja']) ? ' · ' . esc((string) $p['unit_kerja']) : '' ?></div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <span class="ro-chip <?= $sc ?>"><i class="ro-titik"></i><?= esc($sl) ?></span>
        <?php if (isset($pkLbl[$pkS])): ?><span class="ro-chip <?= $pkLbl[$pkS][0] ?>"><i class="ro-titik"></i><?= esc($pkLbl[$pkS][1]) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="text-end">
      <div style="font-size:1.3rem;font-weight:800;color:#1f3a2a;"><?= $jml['capai'] ?>/<?= $jml['ra'] ?></div>
      <div class="sub">rencana aksi <?= (int) $tahun ?> mencapai target</div>
      <?php if ($pkS !== '' && $pkS !== 'belum_ada_skp'): ?>
        <a class="btn btn-sm btn-outline-success mt-2" href="<?= base_url('ruang-opd/' . (int) $opd['id'] . '/pk-pegawai/' . (int) $p['pegawai_id'] . '?tahun=' . (int) $tahun) ?>" data-ro-tautan>
          <i class="fas fa-file-signature me-1"></i><?= $pkS === 'lewat_aksara' ? 'PK AKSARA' : 'Dokumen PK Pegawai' ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($skp === null): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-file-circle-question"></i></div><h5>Belum ada SKP <?= (int) $tahun ?></h5>
      <p class="small mb-0">Pegawai ini belum menyusun SKP di eKin, sehingga belum punya rencana aksi.</p></div>
  <?php elseif ($data['rhk'] === []): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-list"></i></div><h5>SKP belum berisi RHK</h5></div>
  <?php else: ?>
    <div class="rad-legenda">
      <span><i class="c-capai"></i>Tercapai</span><span><i class="c-jalan"></i>Bulan berjalan</span>
      <span><i class="c-kurang"></i>Bulan lalu, belum tercapai</span><span><i class="c-rencana"></i>Direncanakan</span><span><i class="c-belum"></i>Belum dilaporkan di AKSARA (IKP)</span>
      <span>T = target, R = realisasi dari kinerja harian disetujui (Kepala Perangkat Daerah: dari IKP AKSARA, tanda "IKP") · TR/NT = Trajectory / Non-Trajectory</span>
    </div>
    <?php foreach ($data['rhk'] as $r):
        // Baris kisi: satu per IKI yang punya rencana aksi, lalu rencana aksi tanpa IKI.
        $perBaris = [];
        foreach ($r['rencana_aksi'] as $ra) {
            $kunci = $ra['iki_id'] ?? 0;
            $perBaris[$kunci][$ra['bulan']][] = $ra;
        }
        $namaIki = [];
        foreach ($r['iki'] as $i) {
            $namaIki[$i['id']] = $i;
        }
        $raIkp = array_values(array_filter($r['rencana_aksi'], static fn ($ra) => ($ra['sumber_realisasi'] ?? '') === 'ikp'));
        $sinkron = $raIkp !== [] && ! empty($raIkp[0]['ikp_sinkron_pada']) ? date('d/m/Y H.i', strtotime((string) $raIkp[0]['ikp_sinkron_pada'])) : null;
    ?>
      <section class="rad-rhk">
        <div class="rad-rhk-kepala">
          <span class="rad-jenis <?= $r['jenis'] === 'tambahan' ? 'tambahan' : '' ?>"><?= $r['jenis'] === 'tambahan' ? 'Tambahan' : 'Utama' ?></span>
          <h4><?= esc((string) $r['rumusan']) ?></h4>
          <?php if ($raIkp !== []): ?><span class="rad-ikp" title="Realisasi Kepala Perangkat Daerah untuk RHK dari IKP dibaca dari realisasi bulanan IKP di AKSARA<?= $sinkron ? ' (sinkron ' . esc($sinkron, 'attr') . ' WIB)' : '' ?>; kegiatan harian tetap dihitung sebagai jam kerja."><i class="fas fa-link"></i>Realisasi dari IKP AKSARA</span><?php endif; ?>
        </div>
        <?php if (($r['rhk_atasan'] ?? '') !== ''): ?><div class="rad-atasan"><i class="fas fa-turn-up fa-rotate-90 me-1"></i>RHK pimpinan yang diintervensi: <?= esc((string) $r['rhk_atasan']) ?></div><?php endif; ?>
        <?php if (! empty($r['penugasan_dari'])): ?><div class="rad-atasan"><i class="fas fa-user-tag me-1"></i>Penugasan dari: <?= esc((string) $r['penugasan_dari']) ?></div><?php endif; ?>
        <div class="rad-iki">
          <?php foreach ($r['iki'] as $i): ?>
            <span><b><?= esc($aspekLbl[$i['aspek']] ?? $i['aspek']) ?>:</b> <?= esc((string) $i['indikator']) ?>
              <?php if ($i['target'] !== null || ($i['target_teks'] ?? '') !== ''): ?> — <?= $i['target'] !== null ? $angka($i['target']) : esc((string) $i['target_teks']) ?> <?= esc((string) $i['satuan']) ?><?php endif; ?></span>
          <?php endforeach; ?>
        </div>
        <?php if ($perBaris === []): ?>
          <p class="small text-muted mb-0">Belum ada rencana aksi untuk RHK ini.</p>
        <?php else: ?>
          <div class="ro-gulir-x">
            <table class="rad-kisi">
              <thead><tr><th class="kiri">Indikator</th>
                <?php foreach ($bulanPendek as $b => $nm): ?><th class="<?= $tahun === $tahunIni && $b === $bulanKini ? 'kini' : '' ?>"><?= $nm ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($perBaris as $ikiId => $perBulan): ?>
                  <tr>
                    <td class="kiri"><?= $ikiId > 0 && isset($namaIki[$ikiId]) ? esc(mb_strimwidth((string) $namaIki[$ikiId]['indikator'], 0, 70, '…')) : 'Rencana aksi lain' ?></td>
                    <?php for ($b = 1; $b <= 12; $b++): $isi = $perBulan[$b] ?? []; ?>
                      <?php if ($isi === []): ?>
                        <td class="sel">·</td>
                      <?php else: ?>
                        <td class="sel <?= $kelasSel($isi[0]) ?>">
                          <?php foreach ($isi as $ra): ?>
                            <?php $ikp = ($ra['sumber_realisasi'] ?? '') === 'ikp'; ?>
                            <div class="ra" title="<?= esc($ra['uraian'] . ' · ' . ($ra['jenis_bkn'] === 'non_trajectory' ? 'Non-Trajectory' : 'Trajectory') . ($ra['status'] !== '' ? ' · ' . str_replace('_', ' ', $ra['status']) : '') . (! empty($ra['keterangan']) ? ' · ' . $ra['keterangan'] : ''), 'attr') ?>">
                              <span class="t">T <?= $angka($ra['target']) ?></span>
                              <span class="r">R <?= $ikp && ($ra['realisasi'] ?? null) === null ? '–' : $angka($ra['realisasi'] ?? 0) ?></span>
                              <span class="jenis"><?= $ra['jenis_bkn'] === 'non_trajectory' ? 'NT' : 'TR' ?><?= $ikp ? ' · IKP' : '' ?></span>
                            </div>
                          <?php endforeach; ?>
                        </td>
                      <?php endif; ?>
                    <?php endfor; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
    <p class="ro-catatan"><i class="fas fa-circle-info me-1"></i>Arahkan kursor ke sel untuk melihat uraian rencana aksinya. Data dibaca langsung dari eKin (disimpan sementara 5 menit).</p>
  <?php endif; ?>
<?php endif; ?>

</div><!-- /.ro -->
<?= $this->include('templates/shell_bawah') ?>
