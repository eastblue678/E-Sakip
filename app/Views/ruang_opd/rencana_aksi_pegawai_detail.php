<?php
/**
 * Ruang OPD — rencana aksi setahun satu pegawai (AKSARA+). Data: eKin api/aksara/pegawai/{id}/rencana-aksi.
 * Kisi RHK → IKI kuantitas → bulan 1–12: target (T) dan realisasi terhitung (R) dari kegiatan harian disetujui.
 * eKin README §22–§23: Trajectory/Non-Trajectory kini dipilih per kegiatan harian (`jenis_bkn` RA usang, tidak
 * ditampilkan); setiap IKI kuantitas punya pola ukur (hitungan/posisi/rilis) dan bulan non-ukur = rencana aksi
 * PERSIAPAN tanpa target angka; RHK/IKI bisa bergaris IKP (pemilik, pemikul angka, pendukung). Kunci baru boleh absen
 * (eKin lama) — halaman lalu tampil seperti dulu tanpa chip.
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
/** Kelas sel: persiapan / tercapai / berjalan (bulan ini) / kurang (bulan lalu belum tercapai) / rencana (bulan depan). */
$kelasSel = static fn (array $ra): string => \App\Services\EkinClient::kelasSelRa($ra, (int) $tahun, $tahunIni, (int) $bulanKini);
/** Chip pola ukur IKI (null = eKin tidak mengirim pola). */
$chipPola = static function (?array $iki): string {
    $pola = $iki === null ? null : \App\Services\EkinClient::polaIki($iki);
    if ($pola === null) {
        return '';
    }
    $ikon = ['hitungan' => 'fa-calculator', 'posisi' => 'fa-location-crosshairs', 'rilis' => 'fa-certificate'][$pola['kode']];

    return '<span class="rad-pola p-' . esc($pola['kode'], 'attr') . '" data-pola="' . esc($pola['kode'], 'attr') . '" title="' . esc($pola['judul'], 'attr') . '">'
        . '<i class="fas ' . $ikon . '" aria-hidden="true"></i>' . esc($pola['label'])
        . ($pola['ditebak'] ? '<span class="tebak" aria-label="pola tebakan">?</span>' : '') . '</span>';
};
/** Chip peran IKP (IKP / Mendukung IKP / IKP · porsi pimpinan). */
$chipPeran = static function (?string $peran): string {
    $c = \App\Services\EkinClient::peranIkp($peran);

    return $c === null ? '' : '<span class="rad-peran ' . esc($c['kelas'], 'attr') . '" data-peran-ikp="' . esc($c['kode'], 'attr') . '" title="' . esc($c['judul'], 'attr') . '">'
        . '<i class="fas ' . ($c['kelas'] === 'dukung' ? 'fa-star-half-stroke' : 'fa-star') . '" aria-hidden="true"></i>' . esc($c['label']) . '</span>';
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
  .rad-kisi .siap { display: block; font-size: .64rem; font-weight: 700; font-style: italic; }
  .rad-kisi td.kiri .rad-pola { margin-top: 3px; }
  .rad-pola, .rad-peran { display: inline-flex; align-items: center; gap: 4px; font-size: .64rem; font-weight: 700; border-radius: 6px; padding: 1px 7px; white-space: nowrap; vertical-align: 1px; }
  .rad-pola { border: 1px solid #d6dfd9; color: #33483b; background: #fff; }
  .rad-pola.p-posisi { border-color: #bfd4f2; color: #1e4f91; background: #f1f6fd; }
  .rad-pola.p-rilis { border-color: #d9cdf2; color: #5b3d99; background: #f6f2fd; }
  .rad-pola .tebak { font-weight: 800; opacity: .7; }
  .rad-peran.ikp { color: #3d5a00; background: #eef7d9; }
  .rad-peran.dukung { color: #3d5a00; background: #fff; border: 1px dashed #9cbf4a; }
  .c-siap { background: #f6f2fd !important; color: #5b3d99 !important; }
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
    $jml = \App\Services\EkinClient::hitungRaPegawai($data);
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
      <?php if ($jml['persiapan'] > 0): ?><div class="sub" style="color:#5b3d99;font-weight:700;" title="Rencana aksi persiapan: bulan tanpa target angka (indeks/nilai resmi yang belum dirilis, atau bulan non-ukur indikator posisi). Tidak dihitung di atas.">+<?= $jml['persiapan'] ?> persiapan indeks/nilai rilis</div><?php endif; ?>
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
      <span><i class="c-siap"></i>Persiapan — bulan tanpa target angka (tidak dihitung)</span>
      <span>T = target, R = realisasi dari kegiatan harian disetujui (Kepala Perangkat Daerah: dari IKP AKSARA, tanda "IKP").
        Pola ukur indikator: <b>Hitungan</b> = hasil dijumlah, target bulanan cicilan; <b>Posisi</b> = keadaan yang diukur sendiri di bulan ukur;
        <b>Rilis</b> = nilai resmi pihak lain, angka hanya di bulan rilis. <b>IKP</b> / <b>Mendukung IKP</b> = RHK atau indikator yang memikul angka / mendukung IKP perangkat daerah.
        Trajectory / Non-Trajectory dipilih per kegiatan harian di eKin.</span>
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
          <?= $chipPeran($r['peran_ikp'] ?? null) ?>
          <?php if ($raIkp !== []): ?><span class="rad-ikp" title="Realisasi Kepala Perangkat Daerah untuk RHK dari IKP dibaca dari realisasi bulanan IKP di AKSARA<?= $sinkron ? ' (sinkron ' . esc($sinkron, 'attr') . ' WIB)' : '' ?>; kegiatan harian tetap dihitung sebagai jam kerja."><i class="fas fa-link"></i>Realisasi dari IKP AKSARA</span><?php endif; ?>
        </div>
        <?php if (($r['rhk_atasan'] ?? '') !== ''): ?><div class="rad-atasan"><i class="fas fa-turn-up fa-rotate-90 me-1"></i>RHK pimpinan yang diintervensi: <?= esc((string) $r['rhk_atasan']) ?></div><?php endif; ?>
        <?php if (! empty($r['penugasan_dari'])): ?><div class="rad-atasan"><i class="fas fa-user-tag me-1"></i>Penugasan dari: <?= esc((string) $r['penugasan_dari']) ?></div><?php endif; ?>
        <div class="rad-iki">
          <?php foreach ($r['iki'] as $i): ?>
            <span><b><?= esc($aspekLbl[$i['aspek']] ?? $i['aspek']) ?>:</b> <?= esc((string) $i['indikator']) ?>
              <?php if ($i['target'] !== null || ($i['target_teks'] ?? '') !== ''): ?> — <?= $i['target'] !== null ? $angka($i['target']) : esc((string) $i['target_teks']) ?> <?= esc((string) $i['satuan']) ?><?php endif; ?>
              <?= ($i['ikp_peran'] ?? null) !== null && ($i['ikp_peran'] ?? null) !== ($r['peran_ikp'] ?? null) ? $chipPeran($i['ikp_peran']) : '' ?></span>
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
                    <td class="kiri"><?= $ikiId > 0 && isset($namaIki[$ikiId]) ? esc(mb_strimwidth((string) $namaIki[$ikiId]['indikator'], 0, 70, '…')) : 'Rencana aksi lain' ?>
                      <?php if ($ikiId > 0 && isset($namaIki[$ikiId])): ?><br><?= $chipPola($namaIki[$ikiId]) ?><?php endif; ?></td>
                    <?php for ($b = 1; $b <= 12; $b++): $isi = $perBulan[$b] ?? []; ?>
                      <?php if ($isi === []): ?>
                        <td class="sel">·</td>
                      <?php else: ?>
                        <td class="sel <?= $kelasSel($isi[0]) ?>">
                          <?php foreach ($isi as $ra): ?>
                            <?php
                              $ikp  = ($ra['sumber_realisasi'] ?? '') === 'ikp';
                              $siap = ! empty($ra['persiapan']);
                              $pkj  = $ra['pekerjaan'] ?? [];
                              $info = array_filter([
                                  (string) ($ra['uraian'] ?? ''),
                                  (string) ($ra['keadaan_label'] ?? ''),
                                  ! $siap && ($ra['status'] ?? '') !== '' ? str_replace('_', ' ', (string) $ra['status']) : '',
                                  (string) ($ra['keterangan'] ?? ''),
                                  (int) ($pkj['berjalan'] ?? 0) > 0 ? (int) $pkj['berjalan'] . ' pekerjaan bertahap berjalan' : '',
                              ], static fn ($t) => $t !== '');
                            ?>
                            <div class="ra" title="<?= esc(implode(' · ', array_unique($info)), 'attr') ?>"<?= $siap ? ' data-persiapan' : '' ?>>
                              <?php if ($siap): ?>
                                <span class="siap">persiapan</span>
                              <?php else: ?>
                                <span class="t">T <?= $angka($ra['target']) ?></span>
                                <span class="r">R <?= $ikp && ($ra['realisasi'] ?? null) === null ? '–' : $angka($ra['realisasi'] ?? 0) ?></span>
                                <?php if ($ikp): ?><span class="jenis">IKP</span><?php endif; ?>
                              <?php endif; ?>
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
