<?php
/**
 * Perjanjian Kinerja terpadu (AKSARA+). Data: PerjanjianKinerjaController::index.
 * Satu daftar untuk semua jenis PK; jenis/tahun/OPD/cari = saringan. Tombol aksi tiap
 * baris dihasilkan RuangOpdTautan (rute lama yang boleh dipakai peran ini); isi PK
 * (sasaran → indikator → target) selalu bisa dibuka di tempat, untuk semua peran.
 *
 * @var string $kel   opd|kab|admin|bupati
 * @var array  $baris daftarPk() halaman ini
 * @var array  $isi   [pk_id => sasaran[]]
 */
use App\Services\RuangOpdTautan;

$this->setVar('shellCss', $shellCss . <<<'CSS'
.pk-tabel { width:100%; min-width:0 !important; border-collapse:separate; border-spacing:0; font-size:.82rem; }
.pk-tabel th { font-size:.66rem; text-transform:uppercase; letter-spacing:.3px; color:#5d7064; font-weight:800; padding:8px 8px; border-bottom:1px solid #e3e9e5; background:#f6f9f7; white-space:nowrap; }
.pk-tabel td { padding:9px 8px; border-bottom:1px solid #eef2ef; vertical-align:top; }
.pk-tabel tr.isi td { background:#fbfcfb; padding-top:0; }
.pk-jenis { font-size:.68rem; font-weight:800; border-radius:7px; padding:.25em .55em; white-space:nowrap; display:inline-block; }
.pk-j-bupati { background:#fde8e8; color:#9b1c1c; } .pk-j-jpt { background:#e3f1e8; color:#0b6b3a; } .pk-j-camat { background:#e2effa; color:#1d5f8f; }
.pk-j-administrator { background:#eef0fb; color:#43489a; } .pk-j-pengawas { background:#fbf1e1; color:#8a5a1c; }
.pk-org b { color:#15311f; } .pk-org small { display:block; color:#6b7a70; font-size:.72rem; line-height:1.3; }
.pk-aksi { white-space:nowrap; text-align:right; }
.pk-aksi .btn { padding:.18rem .5rem; font-size:.74rem; border-radius:8px; }
.pk-isi summary { cursor:pointer; font-size:.74rem; font-weight:700; color:#00743e; list-style:none; }
.pk-isi summary::-webkit-details-marker { display:none; }
.pk-isi ol { margin:6px 0 0; padding-left:18px; font-size:.78rem; }
.pk-isi li { margin-bottom:4px; }
.pk-isi ul { margin:2px 0 0; padding-left:16px; color:#4a5d51; }
.pk-halaman { display:flex; gap:4px; flex-wrap:wrap; justify-content:center; margin-top:12px; }
.pk-halaman a, .pk-halaman span { min-width:34px; height:34px; display:grid; place-items:center; border:1px solid #dbe3dd; border-radius:9px; font-size:.82rem; font-weight:700; color:#2f3d35; padding:0 8px; }
.pk-halaman .aktif { background:#00743e; border-color:#00743e; color:#fff; }
@media (max-width: 767.98px) {
  .pk-tabel thead { display:none; }
  .pk-tabel, .pk-tabel tbody, .pk-tabel tr, .pk-tabel td { display:block; width:100%; }
  .pk-tabel tr.utama { border:1px solid #e3e9e5; border-radius:12px 12px 0 0; border-bottom:0; margin-top:10px; background:#fff; padding:8px 10px 0; }
  .pk-tabel tr.isi { border:1px solid #e3e9e5; border-top:0; border-radius:0 0 12px 12px; padding:0 10px 8px; background:#fbfcfb; }
  .pk-tabel td { border:0; padding:3px 0; }
  .pk-tabel td[data-label]::before { content:attr(data-label); display:block; font-size:.62rem; font-weight:800; text-transform:uppercase; color:#6b7a70; letter-spacing:.3px; }
  .pk-tabel td.no { display:none; }
  .pk-aksi { text-align:left; }
}
CSS);
$pegawai = (int) ($pegawai ?? 0);
$qs = static function (array $ubah = []) use ($tahun, $jenis, $opdId, $q, $kel, $pegawai): string {
    $p = array_merge(['tahun' => $tahun, 'jenis' => $jenis, 'opd_id' => $kel === 'opd' ? null : $opdId, 'q' => $q,
        'pegawai' => $pegawai > 0 ? $pegawai : null], $ubah);

    return '?' . http_build_query(array_filter($p, static fn ($v) => $v !== null && $v !== ''));
};
$periode = $periode ?? '';
$tautan = static fn (string $item, array $r) => RuangOpdTautan::untuk($peran, $item, (int) $r['opd_id'], (int) $r['tahun'],
    ['jenis' => $r['jenis'], 'id' => $r['id'], 'periode' => $periode], 'user_can');
$jenisLbl = $jenisBoleh + ['bupati' => 'PK Bupati'];
$jumlahSemua = array_sum($hitung);
?>
<?= $this->include('templates/shell_atas') ?>

<div class="ro">
  <div class="ro-hero mb-3">
    <div class="ic"><i class="fas fa-file-signature"></i></div>
    <div class="isi">
      <h2>Perjanjian Kinerja</h2>
      <p><?= $kel === 'opd' ? esc((string) $opdNama) : 'Seluruh jenjang, seluruh perangkat daerah' ?> · tahun <?= (int) $tahun ?>
        — pilih jenis, tahun, atau perangkat daerah di bawah; tidak perlu menebak menu.</p>
    </div>
    <div class="kanan">
      <nav class="ro-tahun" aria-label="Pilih tahun">
        <?php foreach (array_reverse($tahunList) as $t): ?>
          <a href="<?= base_url('perjanjian-kinerja') . $qs(['tahun' => $t, 'hal' => null]) ?>" class="<?= (int) $t === $tahun ? 'aktif' : '' ?>"><?= (int) $t ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if ($tambah !== []): ?>
        <div class="dropdown">
          <button class="btn btn-light fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-plus me-1"></i>Tambah PK</button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">Pilih jenis PK</h6></li>
            <?php foreach ($tambah as $url => $lbl): ?>
              <li><a class="dropdown-item" href="<?= base_url($url) ?>"><?= esc($lbl) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <form method="get" action="<?= base_url('perjanjian-kinerja') ?>" class="ro-alat mb-3">
    <input type="hidden" name="tahun" value="<?= (int) $tahun ?>">
    <?php if ($jenis !== ''): ?><input type="hidden" name="jenis" value="<?= esc($jenis, 'attr') ?>"><?php endif; ?>
    <?php if ($pegawai > 0): ?><input type="hidden" name="pegawai" value="<?= $pegawai ?>"><?php endif; ?>
    <label class="ro-cari mb-0">
      <i class="fas fa-magnifying-glass"></i>
      <input type="search" name="q" value="<?= esc($q, 'attr') ?>" class="form-control" placeholder="Cari nama atau jabatan pihak pertama/kedua<?= $kel !== 'opd' ? ', atau nama OPD' : '' ?>…" aria-label="Cari">
    </label>
    <?php if ($kel !== 'opd'): ?>
      <select name="opd_id" class="form-select" data-no-select2 style="flex:1 1 260px;min-width:0;width:auto;" onchange="this.form.submit()" aria-label="Perangkat daerah">
        <option value="">Semua perangkat daerah</option>
        <?php foreach ($daftarOpd as $o): ?>
          <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === (int) $opdId ? 'selected' : '' ?>><?= esc($o['nama_tampil']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <button type="submit" class="btn btn-success"><i class="fas fa-filter me-1"></i>Terapkan</button>
  </form>

  <?php if ($pegawai > 0): ?>
    <p class="small mb-2"><span class="ro-lencana" style="background:#e3f1e8;color:#0b6b3a;"><i class="fas fa-user-tie"></i>
      Pihak pertama: <?= esc((string) ($pegawaiNama ?? ('pegawai #' . $pegawai))) ?></span>
      <a class="ms-2" href="<?= base_url('perjanjian-kinerja') . $qs(['pegawai' => null, 'hal' => null]) ?>"><i class="fas fa-xmark me-1"></i>tampilkan semua pihak pertama</a></p>
  <?php endif; ?>
  <div class="ro-pil mb-3" role="group" aria-label="Jenis PK">
    <a href="<?= base_url('perjanjian-kinerja') . $qs(['jenis' => null, 'hal' => null]) ?>" class="<?= $jenis === '' ? 'aktif' : '' ?>">Semua jenis<span class="n"><?= (int) $jumlahSemua ?></span></a>
    <?php foreach ($jenisBoleh as $k => $lbl): ?>
      <a href="<?= base_url('perjanjian-kinerja') . $qs(['jenis' => $k, 'hal' => null]) ?>" class="<?= $jenis === $k ? 'aktif' : '' ?>"><?= esc($lbl) ?><span class="n"><?= (int) $hitung[$k] ?></span></a>
    <?php endforeach; ?>
  </div>

  <?php if ($baris === []): ?>
    <div class="ro-kosong"><div class="ic"><i class="fas fa-file-circle-question"></i></div>
      <h5>Tidak ada Perjanjian Kinerja</h5><p class="small mb-0">Tidak ada PK untuk saringan ini pada tahun <?= (int) $tahun ?>.</p></div>
  <?php else: ?>
    <p class="ro-catatan mb-2"><?= (int) $total ?> dokumen<?= $halMax > 1 ? ' · halaman ' . $hal . ' dari ' . $halMax : '' ?>.
      <?= in_array($kel, ['kab', 'admin'], true) ? 'Klik nama perangkat daerah untuk membuka Ruang OPD-nya.' : '' ?></p>
    <div style="border:1px solid #e3e9e5;border-radius:12px;overflow:hidden;background:#fff;">
      <table class="pk-tabel" data-no-paginate>
        <thead><tr>
          <th style="width:38px;">No</th><th>Jenis</th>
          <?php if ($kel !== 'opd'): ?><th>Perangkat Daerah</th><?php endif; ?>
          <th>Pihak Pertama</th><th>Pihak Kedua</th><th>Tanggal</th><th class="text-end">Sasaran / Ind.</th><th class="text-end">Aksi</th>
        </tr></thead>
        <tbody>
          <?php foreach ($baris as $i => $r): ?>
            <?php $tL = $tautan('pk_lihat', $r); $tE = $tautan('pk_edit', $r); $tC = $tautan('pk_cetak', $r); ?>
            <tr class="utama">
              <td class="no"><?= ($hal - 1) * 50 + $i + 1 ?></td>
              <td data-label="Jenis"><span class="pk-jenis pk-j-<?= esc($r['jenis'], 'attr') ?>"><?= esc($jenisLbl[$r['jenis']] ?? $r['jenis']) ?></span></td>
              <?php if ($kel !== 'opd'): ?>
                <td data-label="Perangkat Daerah" class="pk-org">
                  <?php if (in_array((int) $r['opd_id'], $ruangIds, true)): ?>
                    <a href="<?= base_url('ruang-opd/' . (int) $r['opd_id'] . '?tahun=' . (int) $tahun) ?>#pk" class="fw-semibold text-success"><?= esc($r['nama_opd_tampil']) ?></a>
                  <?php else: ?>
                    <?= esc($r['nama_opd_tampil']) ?>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td data-label="Pihak Pertama" class="pk-org"><b><?= esc((string) ($r['nama_1'] ?? '–')) ?></b>
                <?php if ($r['status_1'] !== ''): ?><span class="ro-chip s-kuning ms-1"><?= esc($r['status_1']) ?></span><?php endif; ?>
                <small><?= esc($r['jabatan_1']) ?></small></td>
              <td data-label="Pihak Kedua" class="pk-org"><?php if ($r['jenis'] === 'bupati'): ?><small>— (PK Bupati)</small><?php else: ?><?= esc((string) ($r['nama_2'] ?? '–')) ?>
                <?php if ($r['status_2'] !== ''): ?><span class="ro-chip s-kuning ms-1"><?= esc($r['status_2']) ?></span><?php endif; ?>
                <small><?= esc($r['jabatan_2']) ?></small><?php endif; ?></td>
              <td data-label="Tanggal" class="text-nowrap"><?= $r['tanggal'] ? esc(date('d/m/Y', strtotime((string) $r['tanggal']))) : '–' ?></td>
              <td data-label="Sasaran / Indikator" class="text-end"><?= (int) $r['jml_sasaran'] ?> / <?= (int) $r['jml_indikator'] ?></td>
              <td class="pk-aksi">
                <?php if ($tL): ?><a class="btn btn-outline-success" href="<?= esc(base_url($tL['url'])) ?>" data-pk-tautan><i class="fas fa-eye me-1"></i>Lihat</a><?php endif; ?>
                <?php if ($tE): ?><a class="btn btn-outline-secondary" href="<?= esc(base_url($tE['url'])) ?>" data-pk-tautan><i class="fas fa-pen me-1"></i>Ubah</a><?php endif; ?>
                <?php if ($tC): ?><a class="btn btn-outline-secondary" href="<?= esc(base_url($tC['url'])) ?>" target="_blank" rel="noopener" data-pk-tautan data-pdf><i class="fas fa-print me-1"></i>Cetak</a><?php endif; ?>
              </td>
            </tr>
            <tr class="isi">
              <td class="no"></td>
              <td colspan="<?= $kel !== 'opd' ? 7 : 6 ?>">
                <details class="pk-isi">
                  <summary><i class="fas fa-chevron-down me-1"></i>Isi perjanjian (<?= (int) $r['jml_sasaran'] ?> sasaran, <?= (int) $r['jml_indikator'] ?> indikator)</summary>
                  <?php if (empty($isi[$r['id']])): ?>
                    <p class="ro-catatan mt-1 mb-0">Belum ada sasaran.</p>
                  <?php else: ?>
                    <ol>
                      <?php foreach ($isi[$r['id']] as $s): ?>
                        <li><?= esc($s['sasaran']) ?>
                          <?php if ($s['indikator'] !== []): ?>
                            <ul><?php foreach ($s['indikator'] as $ind): ?>
                              <li><?= esc($ind['indikator']) ?> — <strong><?= esc(trim($ind['target'] . ' ' . $ind['satuan'])) ?: '–' ?></strong></li>
                            <?php endforeach; ?></ul>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ol>
                  <?php endif; ?>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($halMax > 1): ?>
      <nav class="pk-halaman" aria-label="Halaman">
        <?php for ($h = 1; $h <= $halMax; $h++): ?>
          <?php if ($h === $hal): ?><span class="aktif"><?= $h ?></span>
          <?php else: ?><a href="<?= base_url('perjanjian-kinerja') . $qs(['hal' => $h]) ?>"><?= $h ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
  <p class="ro-catatan mt-3"><i class="fas fa-circle-info me-1"></i>Halaman lama per jenis (PK JPT, PK Administrator, PK Pengawas, PK Bupati) tetap berfungsi dan dipakai untuk melihat, mengubah, serta mencetak dokumen.</p>
</div>

<?= $this->include('templates/shell_bawah') ?>
