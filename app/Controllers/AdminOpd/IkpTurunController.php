<?php

namespace App\Controllers\AdminOpd;

use App\Controllers\BaseController;
use App\Models\Ikp\IkpModel;
use App\Models\OpdModel;
use App\Services\IkpRekapService;
use App\Services\IkpTurunService;
use App\Services\PohonPemilikService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

/**
 * =====================================================================
 * AKSARA+ — TURUNKAN IKP (IKP turun sampai pelaksana)
 * =====================================================================
 *
 * Menu Kinerja Prioritas → tab "Turunkan IKP". Per IKP satu pohon mini dari
 * Kepala OPD (pemilik IKP, jangkar Eselon II) ke bawah: setiap simpul Eselon
 * III → IV/Ketua Tim → pelaksana dengan pemiliknya (menu Pemilik Kinerja),
 * centang "ikut memikul", peran (pemikul angka / pendukung), indikator di
 * simpul (pilih yang ada / buat dari rumusan IKP), porsi atau target utuh atau
 * indikator proses, "Usulkan dari pohon", dan pemeriksa per jenjang.
 * Aturan & penyimpanan: App\Services\IkpTurunService.
 *
 * Rute (blok AKSARA+ di ujung Config\Routes):
 *   GET  adminopd/ikp/turun                 index   daftar IKP + cakupan "Turun sampai"
 *   GET  adminopd/ikp/turun/(:num)          detail  pohon mini satu IKP (?tahun=, ?usul=1)
 *   POST adminopd/ikp/turun/(:num)/save     save    simpan pendelegasian
 *   GET  adminkab/ikp/turun[?opd_id=]       index   (baca) Admin Kabupaten/Inspektorat
 *   GET  adminkab/ikp/turun/(:num)          detail  (baca)
 *
 * SIAPA MENURUNKAN: Admin OPD atas nama Kepala OPD (pemilik IKP). Kabid belum
 * menurunkan sendiri untuk cabangnya — dicatat "Terbuka untuk dibahas".
 *
 * LINGKUP: admin_opd/admin_kecamatan → OPD sesi (opd_id kiriman diabaikan);
 * admin (super admin) → ?opd_id= divalidasi; area adminkab → ?opd_id= dari
 * daftar OPD sah (jenis opd/kecamatan), BACA saja. Kepemilikan IKP selalu
 * dicek terhadap baris DB.
 */
class IkpTurunController extends BaseController
{
    protected $helpers = ['ikp', 'cascading_label'];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private ?IkpRekapService $rekap = null;
    private ?IkpTurunService $turun = null;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    private function area(): string
    {
        return $this->request->getUri()->getSegment(1) === 'adminkab' ? 'adminkab' : 'adminopd';
    }

    /** @return array<string, string> */
    private function petaIzin(): array
    {
        return $this->area() === 'adminkab'
            ? ['index' => 'ikp_kab.view', 'detail' => 'ikp_kab.view']
            : ['index' => 'ikp_opd.view', 'detail' => 'ikp_opd.view', 'save' => 'ikp_opd.update'];
    }

    public function _remap(string $method, ...$params)
    {
        $peta = $this->petaIzin();
        if (str_starts_with($method, '_') || ! isset($peta[$method]) || ! method_exists($this, $method)
            || ! (new \ReflectionMethod($this, $method))->isPublic()) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (! user_can($peta[$method])) {
            $pesan = str_ends_with($peta[$method], '.view')
                ? 'Anda tidak memiliki akses ke Kinerja Prioritas (IKP).'
                : 'Anda hanya dapat melihat pendelegasian IKP, tidak mengubahnya.';

            return redirect()->to(base_url($this->area() . '/ikp'))->with('error', $pesan);
        }
        if (! $this->svc()->siap() || ! $this->turunSvc()->siap()) {
            return redirect()->to(base_url($this->area() . '/ikp'))
                ->with('error', 'Kolom pendelegasian IKP belum tersedia. Minta administrator menjalankan db/update_2026-09-28_ikp_turun.sql.');
        }

        return $this->$method(...$params);
    }

    // =================================================================
    // HALAMAN
    // =================================================================

    /** Daftar IKP OPD + cakupan "Turun sampai" + pemeriksa ringkas. */
    public function index()
    {
        $scope = $this->scope();
        $tahun = $this->tahunDipilih();
        $data  = $this->dasar($scope, $tahun, ['title' => 'Turunkan IKP sampai Pelaksana', 'aktif' => 'turun']);
        if ($scope['opd_id'] === null) {
            if ($this->area() === 'adminopd') {
                return view('ikp/_pilih_opd', ['title' => 'Turunkan IKP', 'scope' => $scope, 'tujuan' => 'adminopd/ikp/turun', 'tahun' => $tahun]);
            }

            return view('ikp/turun_index', $data + ['baris' => [], 'ringkas' => [], 'label' => []]);
        }
        $opdId = $scope['opd_id'];
        $rekap = $this->svc()->rekapOpd($opdId, $tahun);
        $info  = [];
        foreach ($rekap as $r) {
            $info[(int) $r['ikp']['id']] = ['pola' => $r['pola']['pola'], 'target' => $r['target_tahunan']];
        }
        $kec     = $this->turunSvc()->kecamatan($opdId);
        $label   = \App\Services\RuangOpdService::labelJenjang($kec);
        $ringkas = $this->turunSvc()->ringkasIkp($info, $tahun, $kec, $label);

        return view('ikp/turun_index', $data + [
            'baris'   => $rekap,
            'ringkas' => $ringkas,
            'label'   => $label,
        ]);
    }

    /** Pohon mini satu IKP. ?usul=1 = isi otomatis dari pohon (belum tersimpan). */
    public function detail($id = null)
    {
        $scope = $this->scope();
        $ikp   = $this->ikpDalamLingkup($scope, (int) $id);
        if ($ikp === null) {
            return redirect()->to($this->u('ikp/turun', [], $scope))->with('error', 'IKP tidak ditemukan atau di luar lingkup Anda.');
        }
        if ($scope['opd_id'] === null) {
            $scope = $this->scopeDenganOpd($scope, (int) $ikp['opd_id']);
        }
        $tahun  = $this->tahunDipilih();
        $opdId  = (int) $ikp['opd_id'];
        $rekap  = $this->svc()->rekapSatu($opdId, (int) $ikp['id'], $tahun);
        $pola   = $rekap['pola'];
        $target = $rekap['target_tahunan'];
        $pohon  = $this->turunSvc()->pohon($opdId, $tahun);
        $baris  = $this->turunSvc()->baris((int) $ikp['id'], $tahun);

        $perNode = [];
        foreach ($baris as $b) {
            $perNode[$b['node_id']] ??= $b;
        }
        $aktifPohon = [];
        foreach ($baris as $b) {
            if (! isset($pohon['simpul'][$b['node_id']])) {
                $aktifPohon[] = $b;   // baris di simpul yang kini tersembunyi (IKU dihentikan) — ditampilkan sebagai catatan
            }
        }

        // Keadaan formulir: tersimpan → usulan (bila diminta) → isian lama (galat validasi).
        $ikpSederhana = ['id' => (int) $ikp['id'], 'nama' => (string) $ikp['output_prioritas'],
            'satuan' => (string) ($ikp['satuan_label'] ?? ''), 'pola' => $pola['pola'], 'target' => $target];
        $usul = [];
        if ($this->request->getGet('usul') === '1' && $this->bolehUbah()) {
            $semua = IkpTurunService::usulkan($ikpSederhana, $pohon);
            foreach ($semua as $node => $u) {
                if (! isset($perNode[$node])) {
                    $usul[$node] = $u;
                }
            }
        }
        $lama = old('turun', null, false);

        $periksa = IkpTurunService::periksaSemua($pola['pola'], $target, array_map(static fn ($b) => [
            'id' => $b['id'], 'induk' => $b['ikp_induk_id'], 'peran' => $b['ikp_peran'], 'target' => $b['target'], 'level' => $b['level'],
        ], $baris));
        $cakupan = IkpTurunService::cakupan(array_column($baris, 'level'), $pohon['kecamatan']);

        return view('ikp/turun', $this->dasar($scope, $tahun, [
            'title'       => 'Turunkan IKP',
            'aktif'       => 'turun',
            'ikp'         => $ikp,
            'ikpSederhana' => $ikpSederhana,
            'rekap'       => $rekap,
            'pola'        => $pola,
            'target'      => $target,
            'pohon'       => $pohon,
            'baris'       => $baris,
            'perNode'     => $perNode,
            'tersembunyi' => $aktifPohon,
            'usul'        => $usul,
            'minta_usul'  => $this->request->getGet('usul') === '1',
            'lama'        => is_array($lama) ? $lama : null,
            'periksa'     => $periksa,
            'cakupan'     => $cakupan,
            'es2'         => (new PohonPemilikService($this->db))->pemilikEs2($opdId, $tahun),
            'proses'      => IkpTurunService::teksProses($ikpSederhana),
        ]));
    }

    /** POST: simpan pendelegasian satu IKP untuk satu tahun. */
    public function save($id = null)
    {
        $scope = $this->scope();
        $ikp   = $this->ikpDalamLingkup($scope, (int) $id);
        if ($ikp === null || $this->area() !== 'adminopd') {
            return redirect()->to($this->u('ikp/turun', [], $scope))->with('error', 'IKP tidak ditemukan atau bukan milik perangkat daerah Anda.');
        }
        $tahun = (int) $this->request->getPost('tahun');
        if (! in_array($tahun, $this->svc()->tahunPeriode(), true)) {
            return redirect()->back()->withInput()->with('error', 'Tahun di luar periode RPJMD.');
        }
        $opdId = (int) $ikp['opd_id'];
        $rekap = $this->svc()->rekapSatu($opdId, (int) $ikp['id'], $tahun);
        $pohon = $this->turunSvc()->pohon($opdId, $tahun, false);

        // Formulir punya dua set isian per simpul: pemikul angka (indikator/teks/satuan/target)
        // dan pendukung (…_proses). Peran yang dipilih menentukan set yang dipakai.
        $masuk = $this->request->getPost('turun');
        $isian = [];
        foreach (is_array($masuk) ? $masuk : [] as $node => $v) {
            if (! is_array($v) || ! ctype_digit((string) $node)) {
                continue;
            }
            $peran = (string) ($v['peran'] ?? 'angka');
            $p     = $peran === 'pendukung' ? '_proses' : '';
            $isian[(int) $node] = [
                'ikut'      => ! empty($v['ikut']),
                'peran'     => $peran,
                'indikator' => (string) ($v['indikator' . $p] ?? ''),
                'teks'      => (string) ($v['teks' . $p] ?? ''),
                'satuan'    => (string) ($v['satuan' . $p] ?? ''),
                'target'    => (string) ($v['target' . $p] ?? ''),
            ];
        }
        if (count($isian) > 400) {
            return redirect()->back()->withInput()->with('error', 'Terlalu banyak simpul dalam satu kiriman.');
        }

        $data = $ikp + ['pola' => $rekap['pola'], 'target' => $rekap['target_tahunan']];
        try {
            $hasil = $this->turunSvc()->simpan($data, $tahun, $pohon, $isian, $this->userId());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->with('error', pesanGalatBerawalan($e, 'Pendelegasian IKP gagal disimpan', 'opd.ikp.turun'));
        }
        if ($hasil['galat'] !== []) {
            return redirect()->back()->withInput()->with('error', 'Belum tersimpan. ' . implode(' ', $hasil['galat']));
        }
        if ($hasil['tambah'] + $hasil['ubah'] + $hasil['cabut'] > 0) {
            log_activity('ubah', 'ikp', 'IKP #' . (int) $ikp['id'] . ' diturunkan (' . $tahun . '): +' . $hasil['tambah']
                . ' baru, ' . $hasil['ubah'] . ' diubah, ' . $hasil['cabut'] . ' dicabut');
        }

        // Pemeriksa sesudah simpan (peringatan, tidak memblokir).
        $baris   = $this->turunSvc()->baris((int) $ikp['id'], $tahun);
        $periksa = IkpTurunService::periksaSemua($rekap['pola']['pola'], $rekap['target_tahunan'], array_map(static fn ($b) => [
            'id' => $b['id'], 'induk' => $b['ikp_induk_id'], 'peran' => $b['ikp_peran'], 'target' => $b['target'],
        ], $baris));
        $pesan = $hasil['tambah'] + $hasil['ubah'] + $hasil['cabut'] === 0
            ? 'Tidak ada perubahan.'
            : 'Pendelegasian tersimpan: ' . $hasil['tambah'] . ' simpul baru, ' . $hasil['ubah'] . ' diubah, ' . $hasil['cabut'] . ' dicabut.'
                . ' Pemilik simpul akan melihatnya di eKin (Tarik dari SAKIP).';
        $redir = redirect()->to($this->u('ikp/turun/' . (int) $ikp['id'], ['tahun' => $tahun], $scope))->with('success', $pesan);
        if ($periksa['n_peringatan'] > 0) {
            $redir = $redir->with('warning', 'Pemeriksa: ' . $periksa['pesan'] . ($periksa['n_peringatan'] > 1 ? ' (+' . ($periksa['n_peringatan'] - 1) . ' catatan lain di pohon)' : ''));
        }

        return $redir;
    }

    // =================================================================
    // LINGKUP & DATA BERSAMA
    // =================================================================

    /** @return array{role:string, opd_id:?int, opd_nama:?string, can_pick:bool, opd_list:array} */
    private function scope(): array
    {
        $role = (string) session()->get('role');
        if ($this->area() === 'adminopd' && in_array($role, ['admin_opd', 'admin_kecamatan'], true)) {
            $opdId = (int) (session()->get('opd_id') ?? 0) ?: null;

            return ['role' => $role, 'opd_id' => $opdId, 'opd_nama' => $opdId ? $this->namaOpd($opdId) : null, 'can_pick' => false, 'opd_list' => []];
        }
        $b = $this->db->table('opd')->select('id, nama_opd, jenis')->whereNotIn('id', OpdModel::EXCLUDED_OPD_IDS);
        if ($this->area() === 'adminkab') {
            $b->whereIn('jenis', [OpdModel::JENIS_OPD, OpdModel::JENIS_KECAMATAN]);
        }
        $list  = $b->orderBy('nama_opd', 'ASC')->get()->getResultArray();
        $minta = $this->request->getGet('opd_id') ?? $this->request->getPost('opd_id');
        $minta = ctype_digit((string) $minta) ? (int) $minta : null;
        $sah   = array_map('intval', array_column($list, 'id'));
        $opdId = ($minta !== null && in_array($minta, $sah, true)) ? $minta : null;

        return ['role' => $role, 'opd_id' => $opdId, 'opd_nama' => $opdId ? $this->namaOpd($opdId) : null, 'can_pick' => true, 'opd_list' => $list];
    }

    private function scopeDenganOpd(array $scope, int $opdId): array
    {
        $sah = array_map('intval', array_column($scope['opd_list'], 'id'));

        return in_array($opdId, $sah, true) ? ['opd_id' => $opdId, 'opd_nama' => $this->namaOpd($opdId)] + $scope : $scope;
    }

    /** IKP aktif dalam lingkup: OPD sesi (admin OPD) atau OPD sah (kabupaten/super admin). */
    private function ikpDalamLingkup(array $scope, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $r = $this->db->table('ikp')->select('opd_id')->where('id', $id)->where('dihapus_pada', null)->get()->getRowArray();
        if (! $r) {
            return null;
        }
        $opd = (int) $r['opd_id'];
        if ($scope['can_pick']) {
            if (! in_array($opd, array_map('intval', array_column($scope['opd_list'], 'id')), true)) {
                return null;
            }
        } elseif ($scope['opd_id'] !== $opd) {
            return null;
        }

        return $this->svc()->satu($opd, $id);
    }

    private function bolehUbah(): bool
    {
        return $this->area() === 'adminopd' && user_can('ikp_opd.update');
    }

    private function dasar(array $scope, int $tahun, array $tambahan): array
    {
        return $tambahan + [
            'area'      => $this->area(),
            'scope'     => $scope,
            'tahun'     => $tahun,
            'tahunList' => $this->svc()->tahunPeriode(),
            'periode'   => $this->svc()->periodeAktif(),
            'bolehUbah' => $this->bolehUbah(),
            'u'         => fn (string $path, array $q = []) => $this->u($path, $q, $scope),
            'kategoriMeta' => IkpController::KATEGORI_META,
            'kategoriList' => IkpModel::KATEGORI,
        ];
    }

    /** URL area aktif; opd_id ikut dibawa untuk peran yang memilih OPD. */
    private function u(string $path, array $query = [], ?array $scope = null): string
    {
        $scope ??= $this->scope();
        if ($scope['can_pick'] && $scope['opd_id'] !== null) {
            $query = ['opd_id' => $scope['opd_id']] + $query;
        }
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        $path  = str_starts_with($path, 'admin') ? $path : $this->area() . '/' . ltrim($path, '/');

        return base_url($path) . ($query === [] ? '' : '?' . http_build_query($query));
    }

    private function namaOpd(int $opdId): ?string
    {
        return $this->db->table('opd')->select('nama_opd')->where('id', $opdId)->get()->getRowArray()['nama_opd'] ?? null;
    }

    private function tahunDipilih(): int
    {
        $list  = $this->svc()->tahunPeriode();
        $minta = (int) ($this->request->getGet('tahun') ?? 0);
        if (in_array($minta, $list, true)) {
            return $minta;
        }
        $kini = (int) (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y');

        return max($list[0], min(end($list), $kini));
    }

    private function userId(): ?int
    {
        $id = (int) (session()->get('user_id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private function svc(): IkpRekapService
    {
        return $this->rekap ??= new IkpRekapService($this->db);
    }

    private function turunSvc(): IkpTurunService
    {
        return $this->turun ??= new IkpTurunService($this->db);
    }
}
