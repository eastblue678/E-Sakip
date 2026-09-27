<?php
/**
 * AKSARA+ — kertas Perjanjian Kinerja Pegawai dari eKin, SAMA dengan dokumen eKin (app/Views/pk_pegawai/_dokumen.php):
 *   Lembar 1 (potret)  : kop, pernyataan baku + tanda tangan (kiri pihak kedua, kanan pihak pertama)
 *   Lembar 2 (lanskap) : PERJANJIAN KINERJA PEGAWAI — identitas + tabel sasaran/indikator/target output bulan 1..12
 * Dipakai halaman dokumen (pratinjau) dan halaman cetak. Gaya: public/assets/css/pk_pegawai_dokumen.css.
 *
 * MENGAPA menyalin susunan eKin, bukan menyusun sendiri: dokumen yang sama harus tampak sama di dua aplikasi.
 * Dulu AKSARA+ punya susunan sendiri (tanpa kop, tanpa cap elektronik, indikator satu RHK tidak digabung, dan
 * cetaknya ikut membawa bingkai aplikasi), sehingga PK yang rapi di eKin tampak berantakan di sini.
 * Kalimat baku datang dari eKin (`teks`); bila jawaban eKin belum memuatnya (cache lama) dipakai kalimat yang sama.
 *
 * "Tanda tangan" = catatan persetujuan elektronik eKin (siapa & kapan), bukan tanda tangan elektronik tersertifikasi.
 *
 *   view('ruang_opd/_pk_pegawai_kertas', ['dok' => $dok, 'cetak' => false, 'tahun' => $tahun, 'opdNama' => ...])
 *
 * @var array  $dok     jawaban eKin api/aksara/pk-pegawai/{id} (status bukan lewat_aksara/belum_ada_skp)
 * @var bool   $cetak
 * @var int    $tahun
 * @var string $opdNama cadangan nama OPD bila eKin tidak mengirimnya
 */
$teks = ($dok['teks'] ?? []) + [
    'pembuka'   => 'Dalam rangka mewujudkan manajemen pemerintahan yang efektif, transparan dan akuntabel serta berorientasi pada hasil, kami yang bertanda tangan di bawah ini:',
    'pihak_1'   => 'selanjutnya disebut pihak pertama;',
    'pihak_2'   => 'selaku atasan pihak pertama, selanjutnya disebut pihak kedua.',
    'janji'     => 'Pihak pertama berjanji akan mewujudkan target kinerja yang seharusnya sesuai lampiran perjanjian ini, dalam rangka mencapai target kinerja jangka menengah seperti yang telah ditetapkan dalam dokumen perencanaan. Keberhasilan dan kegagalan pencapaian target kinerja tersebut menjadi tanggung jawab kami.',
    'supervisi' => 'Pihak kedua akan melaksanakan supervisi yang diperlukan serta akan melakukan evaluasi terhadap capaian kinerja dari perjanjian ini dan mengambil tindakan yang diperlukan dalam rangka pemberian penghargaan dan sanksi.',
];
$status = (string) ($dok['status'] ?? '');
$p1     = $dok['pegawai'] ?? [];
$p2     = $dok['pihak_kedua'] ?? null;
$ttd    = $status === 'ditandatangani';
// Draf/dikembalikan: tanggal hari ini (seperti eKin) dalam WIB — AKSARA berjalan UTC, jadi date() mundur sehari sebelum 07.00 WIB.
$tgl    = $ttd ? ($dok['ditandatangani_pada'] ?? null) : ($status === 'diajukan' ? ($dok['diajukan_pada'] ?? null) : \App\Services\RuangOpdService::kini()->format('Y-m-d'));
$opdP1  = trim((string) ($p1['opd_nama'] ?? '')) ?: (string) ($opdNama ?? '');
$unit   = trim((string) ($p1['unit_kerja'] ?? '')) ?: $opdP1;

$bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bulanSingkat = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$tanggal = static function (?string $v) use ($bulanNama): string {
    $ts = $v !== null && trim($v) !== '' ? strtotime($v) : false;

    return $ts === false ? '–' : (int) date('j', $ts) . ' ' . $bulanNama[(int) date('n', $ts)] . ' ' . date('Y', $ts);
};
$waktu = static function (?string $v) use ($bulanSingkat): string {
    $ts = $v !== null && trim($v) !== '' ? strtotime($v) : false;

    return $ts === false ? '–' : (int) date('j', $ts) . ' ' . $bulanSingkat[(int) date('n', $ts)] . ' ' . date('Y H.i', $ts);
};
$angka = static function ($v): string {
    if ($v === null || $v === '' || ! is_numeric($v)) {
        return $v === null || $v === '' ? '–' : (string) $v;
    }
    $t = number_format((float) $v, 2, ',', '.');

    return rtrim(rtrim($t, '0'), ',');
};
$titik  = '....................................';
$orang  = static function (?array $o) use ($titik): string {
    $nama = trim((string) ($o['nama'] ?? ''));
    if ($o === null || $nama === '') {
        return esc($titik);
    }

    return esc($nama) . (! empty($o['fiktif'])
        ? ' <span class="pkd-fiktif" title="Pegawai rekaan untuk simulasi — bukan ASN sungguhan"><i class="fas fa-user-secret" aria-hidden="true"></i>Pegawai fiktif</span>' : '');
};
$nip     = static fn (?array $o): string => $o !== null && empty($o['kepala_daerah']) && ! empty($o['nip']) ? 'NIP. ' . esc((string) $o['nip']) : '';
$pangkat = static fn (?array $o): string => $o !== null && empty($o['kepala_daerah']) ? esc((string) ($o['pangkat_gol'] ?? '')) : '';
$cap     = static fn (string $label, ?string $pada): string => '<div class="pkd-cap" title="Catatan persetujuan elektronik eKin — bukan tanda tangan elektronik tersertifikasi">'
    . '<span class="centang" aria-hidden="true">&#10003;</span> ' . esc($label) . '<br><small>' . esc($waktu($pada)) . ' WIB</small></div>';
$capP1   = in_array($status, ['diajukan', 'ditandatangani'], true) && ! empty($dok['diajukan_pada']) ? $cap('Diajukan elektronik', $dok['diajukan_pada']) : '';
$capP2   = $ttd ? $cap('Disetujui elektronik', $dok['ditandatangani_pada'] ?? null) : '';
$tanda   = match ($status) {
    'ditandatangani' => '',
    'diajukan'       => 'MENUNGGU TANDA TANGAN',
    'dikembalikan'   => 'DIKEMBALIKAN',
    default          => 'DRAF',
};
// Asal RHK (layar saja) = lencanaSumberRhk eKin (app/Config/Ekin.php): label, ikon, warna chip.
$sumberLbl = [
    'cascading'          => ['Pohon Kinerja', 'fa-sitemap', 'c-hijau'],
    'ikp'                => ['IKP', 'fa-star', 'c-lime'],
    'pk_indikator'       => ['PK', 'fa-file-signature', 'c-biru'],
    'penugasan_khusus'   => ['Penugasan Khusus', 'fa-user-tie', 'c-oranye'],
    'penugasan_tambahan' => ['Penugasan Tambahan', 'fa-plus', 'c-kuning'],
    'cascading_pimpinan' => ['Cascading', 'fa-diagram-project', 'c-hijau'],
    'manual'             => ['Manual', 'fa-pen', 'c-abu'],
];
$bagian = ['utama' => [], 'tambahan' => []];
foreach ($dok['baris'] ?? [] as $b) {
    $bagian[($b['jenis'] ?? '') === 'tambahan' ? 'tambahan' : 'utama'][] = $b;
}
$pita = $cetak ? view('templates/pita_simulasi') : '';
?>
<div class="pkd-dok<?= $cetak ? ' pkd-cetak' : '' ?>">
  <!-- ============================ LEMBAR 1 ============================ -->
  <section class="pkd-lembar hal-1" aria-label="Lembar pernyataan">
    <?php if ($tanda !== ''): ?><div class="pkd-watermark" aria-hidden="true"><?= esc($tanda) ?></div><?php endif; ?>
    <div class="pkd-kop">
      <img src="<?= base_url('assets/images/logo.png') ?>" alt="Lambang Kabupaten Pringsewu">
      <div>
        <div class="instansi">PEMERINTAH KABUPATEN PRINGSEWU</div>
        <?php if ($opdP1 !== ''): ?><div class="sub"><?= esc(mb_strtoupper($opdP1)) ?></div><?php endif; ?>
      </div>
    </div>
    <?= $pita ?>
    <h2 class="pkd-judul">PERJANJIAN KINERJA TAHUN <?= (int) $tahun ?></h2>
    <p class="pkd-par"><?= esc($teks['pembuka']) ?></p>
    <table class="pkd-pihak">
      <tr><td class="lbl">Nama</td><td>:</td><td><?= $orang($p1) ?></td></tr>
      <tr><td class="lbl">Jabatan</td><td>:</td><td><?= esc((string) ($p1['jabatan'] ?? '')) ?></td></tr>
      <tr><td colspan="3" class="ket"><?= esc($teks['pihak_1']) ?></td></tr>
      <tr><td class="lbl">Nama</td><td>:</td><td><?= $orang($p2) ?></td></tr>
      <tr><td class="lbl">Jabatan</td><td>:</td><td><?= esc((string) ($p2['jabatan'] ?? $titik)) ?></td></tr>
      <tr><td colspan="3" class="ket"><?= esc($teks['pihak_2']) ?></td></tr>
    </table>
    <p class="pkd-par"><?= esc($teks['janji']) ?></p>
    <p class="pkd-par"><?= esc($teks['supervisi']) ?></p>
    <div class="pkd-ttd">
      <div class="kolom">
        <div class="tempat">&nbsp;</div>
        <div>PIHAK KEDUA,</div>
        <div class="ruang"><?= $capP2 ?></div>
        <div class="nama"><?= $orang($p2) ?></div>
        <div><?= $pangkat($p2) ?></div>
        <div><?= $nip($p2) ?></div>
      </div>
      <div class="kolom">
        <div class="tempat">Pringsewu, <?= esc($tanggal($tgl)) ?></div>
        <div>PIHAK PERTAMA,</div>
        <div class="ruang"><?= $capP1 ?></div>
        <div class="nama"><?= $orang($p1) ?></div>
        <div><?= $pangkat($p1) ?></div>
        <div><?= $nip($p1) ?></div>
      </div>
    </div>
  </section>

  <!-- ============================ LEMBAR 2 ============================ -->
  <section class="pkd-lembar hal-2" aria-label="Lampiran target kinerja">
    <?php if ($tanda !== ''): ?><div class="pkd-watermark" aria-hidden="true"><?= esc($tanda) ?></div><?php endif; ?>
    <?= $pita ?>
    <h2 class="pkd-judul">PERJANJIAN KINERJA PEGAWAI<br><small>TAHUN <?= (int) $tahun ?></small></h2>
    <div class="pkd-identitas">
      <table>
        <tr><td class="lbl">Nama</td><td>:</td><td><?= $orang($p1) ?></td></tr>
        <tr><td class="lbl">NIP</td><td>:</td><td><?= esc((string) (($p1['nip'] ?? '') ?: '–')) ?></td></tr>
        <tr><td class="lbl">Pangkat/Gol.</td><td>:</td><td><?= esc((string) (($p1['pangkat_gol'] ?? '') ?: '–')) ?></td></tr>
      </table>
      <table>
        <tr><td class="lbl">Jabatan</td><td>:</td><td><?= esc((string) ($p1['jabatan'] ?? '')) ?></td></tr>
        <tr><td class="lbl">Unit kerja / OPD</td><td>:</td><td><?= esc($unit !== '' ? $unit : '–') ?><?= $opdP1 !== '' && $unit !== $opdP1 ? ' / ' . esc($opdP1) : '' ?></td></tr>
        <tr><td class="lbl">Tahun</td><td>:</td><td><?= (int) $tahun ?></td></tr>
      </table>
    </div>
    <div class="pkd-gulir">
      <table class="pkd-tabel" data-no-paginate>
        <thead>
          <tr>
            <th rowspan="2" class="no">NO</th>
            <th rowspan="2" class="sasaran">SASARAN</th>
            <th rowspan="2" class="indikator">INDIKATOR KINERJA</th>
            <th rowspan="2">SATUAN</th>
            <th colspan="12">TARGET OUTPUT</th>
            <th rowspan="2">JUMLAH</th>
          </tr>
          <tr><?php for ($m = 1; $m <= 12; $m++): ?><th class="bln"><?= $m ?></th><?php endfor; ?></tr>
        </thead>
        <tbody>
          <?php foreach (['utama' => 'A. UTAMA', 'tambahan' => 'B. TAMBAHAN'] as $jenis => $judulBagian): ?>
            <tr class="bagian"><td colspan="17"><?= $judulBagian ?></td></tr>
            <?php if ($bagian[$jenis] === []): ?>
              <tr><td class="no">–</td><td colspan="16" class="redup">Tidak ada</td></tr>
            <?php endif; ?>
            <?php
            // Beberapa indikator satu RHK = satu sasaran bergabung (rowspan), seperti dokumen eKin.
            $grup = [];
            foreach ($bagian[$jenis] as $b) {
                $grup[(int) ($b['rhk_id'] ?? 0) . '-' . (int) ($b['no'] ?? 0)][] = $b;
            }
            ?>
            <?php foreach ($grup as $barisRhk): $rs = count($barisRhk); ?>
              <?php foreach ($barisRhk as $i => $b): ?>
                <tr>
                  <?php if ($i === 0): ?>
                    <td class="no" rowspan="<?= $rs ?>"><?= (int) ($b['no'] ?? 0) ?></td>
                    <td rowspan="<?= $rs ?>">
                      <?= esc((string) ($b['sasaran'] ?? '')) ?>
                      <?php if (! $cetak && isset($sumberLbl[$b['sumber_tipe'] ?? ''])): [$l, $ik, $cls] = $sumberLbl[$b['sumber_tipe']]; ?>
                        <div class="pkd-sumber"><span class="pkd-chip <?= $cls ?>"><i class="fas <?= $ik ?>"></i><?= esc($l) ?></span></div>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                  <td><?= esc((string) ($b['indikator'] ?? '')) ?></td>
                  <td class="tengah"><?= esc((string) (($b['satuan'] ?? '') ?: '–')) ?></td>
                  <?php for ($m = 1; $m <= 12; $m++): $v = $b['target_bulan'][(string) $m] ?? $b['target_bulan'][$m] ?? null; ?>
                    <td class="angka<?= $v === null ? ' kosong' : '' ?>"><?= $v === null ? '–' : esc($angka($v)) ?></td>
                  <?php endfor; ?>
                  <td class="angka jumlah"><?= esc($angka($b['jumlah'] ?? null)) ?><?php if (($b['metode'] ?? 'sum') === 'akhir'): ?> <span class="pkd-ket-akhir" title="Nilai akhir, bukan jumlah">*</span><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="pkd-catatan-kaki">Target output bulan 1–12 = target rencana aksi setiap indikator. JUMLAH = jumlah 12 bulan; bertanda * = nilai akhir (persentase/indeks), bukan jumlah.</p>
    <div class="pkd-ttd">
      <div class="kolom">
        <div class="tempat">&nbsp;</div>
        <div>Mengetahui,</div>
        <div><?= esc((string) ($p2['jabatan'] ?? 'Pejabat Penilai Kinerja')) ?></div>
        <div class="ruang"><?= $capP2 ?></div>
        <div class="nama"><?= $orang($p2) ?></div>
        <div><?= $pangkat($p2) ?></div>
        <div><?= $nip($p2) ?></div>
      </div>
      <div class="kolom">
        <div class="tempat">Pringsewu, <?= esc($tanggal($tgl)) ?></div>
        <div>Pihak Pertama,</div>
        <div><?= esc((string) ($p1['jabatan'] ?? '')) ?></div>
        <div class="ruang"><?= $capP1 ?></div>
        <div class="nama"><?= $orang($p1) ?></div>
        <div><?= $pangkat($p1) ?></div>
        <div><?= $nip($p1) ?></div>
      </div>
    </div>
  </section>
</div>
