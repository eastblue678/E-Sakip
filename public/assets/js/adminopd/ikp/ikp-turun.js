/*
 * AKSARA+ — Turunkan IKP (app/Views/ikp/turun.php).
 *
 *   - centang "Ikut memikul" membuka isian simpul; peran memilih set isian
 *     (pemikul angka / pendukung); "Buat indikator baru" membuka teks & satuan;
 *   - pemeriksa per jenjang dihitung ulang seketika — CERMINAN dari
 *     App\Services\IkpTurunService::periksa() (ubah keduanya bersamaan; angka
 *     yang tersimpan tetap diperiksa server dan hasilnya tampil sesudah Simpan);
 *   - persentase porsi terhadap target induknya;
 *   - PERAN EFEKTIF (D3): pemikul angka yang satuan indikatornya berbeda dari
 *     satuan IKP ditandai "Dihitung sebagai pendukung" dan tidak ikut dijumlah —
 *     cerminan IkpTurunService::peranEfektif(); peta sinonim dari data-sinonim
 *     (Config\IkpSatuan, satu tempat; aturan garis miring & kata pertama frasa sama
 *     dengan ikp_satuan_sama()); rantai pendukung bersatuan sama diperiksa
 *     porsinya sendiri; satuan tampil di samping porsi;
 *   - POSISI TERBAGI (D4, data-terbagi="1"): pemikul angka posisi memikul porsi
 *     dan diperiksa seperti hitungan.
 * Formulir dikirim biasa (POST), tidak lewat AJAX.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-turun');
        if (!form || form.dataset.boleh !== '1') return;
        var A = window.IkpAngka;
        var pola = form.dataset.pola;
        var terbagi = form.dataset.terbagi === '1' && pola === 'posisi';
        var bagi = pola === 'hitungan' || terbagi;   // pemikul angka memikul PORSI
        var tIkp = form.dataset.target === '' ? null : parseFloat(form.dataset.target);
        var satIkp = form.dataset.satuan || '';
        var SINONIM = {};
        try { SINONIM = JSON.parse(form.dataset.sinonim || '{}') || {}; } catch (e) { SINONIM = {}; }
        var TOL = 0.005;

        // Cerminan ikp_satuan_rapikan() / ikp_satuan_kunci() / ikp_satuan_sama().
        function rapikanSatuan(t) {
            var x = String(t || '').toLowerCase().trim().replace(/\s+/g, ' ');
            var tanpa = x.replace(/\s*\([^)]*\)\s*/g, ' ').trim();
            x = tanpa !== '' ? tanpa.replace(/\s+/g, ' ') : x.replace(/[()]/g, '').trim();
            return x.replace(/\.+$/, '').trim();
        }
        function kunciSatuan(t) { var x = rapikanSatuan(t); return x === '' ? '' : (SINONIM[x] || x); }
        function satuanSama(a, b) {
            var x = kunciSatuan(a), y = kunciSatuan(b);
            if (x === '' || y === '') return null;
            if (x === y) return true;
            function bagian(k) {
                var out = {}; out[k] = true;
                var pot = k.split(/\s*\/\s*/);
                if (pot.length > 1) pot.forEach(function (p) { var kk = kunciSatuan(p); if (kk !== '') out[kk] = true; });
                return out;
            }
            var bx = bagian(x), by = bagian(y);
            for (var k in bx) { if (Object.prototype.hasOwnProperty.call(by, k)) return true; }
            function kepala(k) { return k.indexOf(' ') >= 0 ? kunciSatuan(k.split(' ')[0]) : null; }
            return (y.indexOf(' ') < 0 && kepala(x) === y) || (x.indexOf(' ') < 0 && kepala(y) === x);
        }

        function fmt(v) { return A ? A.fmt(v, 4) : String(v); }
        function baca(t) { return A ? A.baca(t, false) : (t === '' ? null : parseFloat(String(t).replace(',', '.'))); }

        function kartu(li) { return li.querySelector(':scope > .tr-kartu'); }
        function dicentang(li) { var c = kartu(li).querySelector('input[data-ikut]'); return !!(c && c.checked); }
        /** Peran yang dipilih (tersimpan). */
        function peranPilih(li) { var r = kartu(li).querySelector('input[data-peran]:checked'); return r ? r.value : 'angka'; }
        function setAktif(li) { return kartu(li).querySelector('.tr-set[data-set="' + peranPilih(li) + '"]'); }
        /** Satuan indikator set aktif: opsi terpilih (data-satuan) atau satuan yang diketik untuk indikator baru. */
        function satuan(li) {
            var set = setAktif(li);
            if (!set) return '';
            var sel = set.querySelector('select[data-pilih-ind]');
            if (sel && sel.value !== 'baru') {
                var o = sel.options[sel.selectedIndex];
                return o ? (o.getAttribute('data-satuan') || '') : '';
            }
            var t = set.querySelector('input.tr-satuan');
            return t ? t.value : '';
        }
        /** Peran EFEKTIF — cerminan IkpTurunService::peranEfektif(). */
        function peran(li) {
            var p = peranPilih(li);
            return (p === 'angka' && satuanSama(satuan(li), satIkp) === false) ? 'pendukung' : p;
        }
        function target(li) {
            if (peranPilih(li) === 'angka' && !bagi) return tIkp;
            var el = kartu(li).querySelector('.tr-set[data-set="' + peranPilih(li) + '"] input[name$="[target]"], .tr-set[data-set="' + peranPilih(li) + '"] input[name$="[target_proses]"]');
            return el ? baca(el.value) : null;
        }
        /** Simpul leluhur terdekat yang dicentang (null = langsung dari IKP). */
        function indukTercentang(li) {
            var p = li.parentElement ? li.parentElement.closest('li.tr-simpul') : null;
            while (p) {
                if (dicentang(p)) return p;
                p = p.parentElement ? p.parentElement.closest('li.tr-simpul') : null;
            }
            return null;
        }

        function h(kode, warna, pesan) { return { kode: kode, warna: warna, pesan: pesan }; }
        // Cerminan IkpTurunService::cekPorsi().
        function cekPorsi(tInduk, anak, sat) {
            var kosong = 0, jumlah = 0;
            anak.forEach(function (a) { if (a.target === null) kosong++; else jumlah += a.target; });
            var s = sat && String(sat).trim() !== '' ? ' ' + String(sat).trim() : '';
            var selisih = Math.round((jumlah - tInduk) * 10000) / 10000;
            var tambah = kosong > 0 ? ' ' + kosong + ' porsi belum diisi.' : '';
            if (Math.abs(selisih) <= TOL && kosong === 0) return h('habis', 'ok', 'Terbagi habis: ' + fmt(jumlah) + ' = ' + fmt(tInduk) + s + '.');
            if (selisih < -TOL || (kosong > 0 && Math.abs(selisih) <= TOL)) {
                return h('kurang', 'peringatan', 'Kurang ' + fmt(Math.abs(selisih)) + ': porsi ' + fmt(jumlah) + ' dari ' + fmt(tInduk) + s + ' (sisanya belum diturunkan).' + tambah);
            }
            return h('lebih', 'peringatan', 'Lebih ' + fmt(selisih) + ': porsi ' + fmt(jumlah) + ' melebihi ' + fmt(tInduk) + s + '.' + tambah);
        }

        // Cerminan IkpTurunService::periksa() (peran = peran EFEKTIF).
        function periksa(tInduk, anak, akar, peranInduk, satInduk) {
            var angka = anak.filter(function (a) { return a.peran === 'angka'; });
            var nA = angka.length, nP = anak.length - nA;
            if (peranInduk === 'pendukung' && !akar) {
                if (nA > 0) return h('terputus', 'peringatan', 'Pemikul angka di bawah pendukung: rantai angka terputus. Jadikan simpul di atasnya pemikul angka, atau jadikan simpul ini pendukung.');
                var sama = (satInduk && String(satInduk).trim() !== '')
                    ? anak.filter(function (a) { return satuanSama(a.satuan, satInduk) === true; }) : [];
                if (sama.length > 0 && tInduk !== null) {
                    var r = cekPorsi(tInduk, sama, satInduk);
                    r.pesan = 'Rantai pendukung (' + satInduk + '): ' + r.pesan
                        + (sama.length < nP ? ' ' + (nP - sama.length) + ' pendukung bersatuan lain tidak ikut dijumlah.' : '');
                    return r;
                }
                return h(nP > 0 ? 'pendukung' : 'belum', 'netral', nP > 0 ? nP + ' pendukung.' : 'Tidak diturunkan lagi.');
            }
            if (nA === 0) {
                if (akar) return nP > 0 ? h('tanpa_angka', 'peringatan', 'Belum ada pemikul angka; pendukung tidak menambah angka IKP.')
                    : h('belum', 'peringatan', 'IKP belum diturunkan ke jenjang mana pun.');
                return nP > 0 ? h('tetap_di_atas', 'netral', 'Angka tetap dipikul jenjang ini; ' + nP + ' pendukung di bawahnya.')
                    : h('belum', 'netral', 'Tidak diturunkan lagi (dipikul jenjang ini).');
            }
            if (bagi) {
                if (tInduk === null) return h('tanpa_target', 'peringatan', 'Target ' + (akar ? 'tahunan IKP' : 'induk') + ' belum berupa angka; porsi tidak dapat diperiksa.');
                var c = cekPorsi(tInduk, angka, akar ? satIkp : satInduk);
                if (terbagi && c.kode === 'habis') c.pesan += ' Posisi terbagi: jumlah posisi setiap bagian = posisi induk.';
                return c;
            }
            if (nA > 1) return h('ganda', 'peringatan', nA + ' pemikul angka. Nilai ' + (pola === 'rilis' ? 'rilis' : 'posisi') + ' tidak dibagi: pilih SATU pemikul angka di jenjang ini, yang lain jadikan pendukung'
                + (pola === 'posisi' ? ' — atau, bila posisinya jumlah dari beberapa bagian (mis. pengikut beberapa akun), centang "Dapat dipecah per bagian" di form IKP.' : '.'));
            return h('satu', 'ok', 'Satu pemikul angka' + (nP > 0 ? ' + ' + nP + ' pendukung' : '') + '.');
        }

        var IKON = { ok: 'fa-circle-check', peringatan: 'fa-triangle-exclamation', netral: 'fa-circle-info' };

        function tulis(kotak, hasil, tampil) {
            if (!kotak) return;
            kotak.hidden = !tampil;
            kotak.className = 'tr-periksa ' + hasil.warna;
            var i = kotak.querySelector('i.fas');
            if (i) i.className = 'fas ' + (IKON[hasil.warna] || 'fa-circle-info');
            var isi = kotak.querySelector('.isi');
            if (isi) isi.textContent = hasil.pesan;
        }

        function hitungUlang() {
            var semua = Array.prototype.slice.call(form.querySelectorAll('li.tr-simpul'));
            var grup = { akar: [] };
            semua.forEach(function (li) {
                if (!dicentang(li)) return;
                var ind = indukTercentang(li);
                var k = ind ? ind.dataset.node : 'akar';
                (grup[k] = grup[k] || []).push({ li: li, peran: peran(li), target: target(li), satuan: satuan(li) });
            });
            tulis(form.querySelector('.tr-periksa[data-periksa="akar"]'), periksa(tIkp, grup.akar, true, 'angka', satIkp), true);
            semua.forEach(function (li) {
                var kotak = li.querySelector(':scope > .tr-periksa');
                if (!kotak) return;
                var ikut = dicentang(li);
                var anak = grup[li.dataset.node] || [];
                tulis(kotak, periksa(target(li), anak, false, peran(li), satuan(li)), ikut && anak.length > 0);
            });
            semua.forEach(function (li) {
                var k = kartu(li);
                var ikut = dicentang(li);
                // Satuan di samping porsi/target.
                k.querySelectorAll('.tr-set').forEach(function (set) {
                    var lbl = set.querySelector('[data-satuan-porsi]');
                    if (lbl && !set.hidden) lbl.textContent = satuan(li);
                });
                // "Dihitung sebagai pendukung: satuan X ≠ Y" (peran efektif).
                var ef = k.querySelector('[data-efektif]');
                if (ef) {
                    var turun = ikut && peranPilih(li) === 'angka' && peran(li) === 'pendukung';
                    ef.hidden = !turun;
                    if (turun) {
                        var isi = ef.querySelector('.isi');
                        if (isi) isi.textContent = 'Dihitung sebagai pendukung: satuan ' + String(satuan(li)).trim() + ' ≠ ' + satIkp.trim() + '.';
                    }
                }
                // Persentase porsi terhadap induk (hitungan / posisi terbagi).
                var el = k.querySelector('[data-persen]');
                if (!el) return;
                el.textContent = '';
                if (!ikut || peran(li) !== 'angka' || !bagi) return;
                var ind = indukTercentang(li);
                var tInd = ind ? (peran(ind) === 'angka' ? target(ind) : null) : tIkp;
                var t = target(li);
                if (tInd && t !== null) el.textContent = fmt(t / tInd * 100) + '% dari ' + (ind ? 'atasannya' : 'target IKP');
            });
        }

        function segarKartu(li) {
            var k = kartu(li);
            var ikut = dicentang(li);
            k.classList.toggle('ikut', ikut);
            var isi = k.querySelector('.tr-isi');
            if (isi) isi.hidden = !ikut;
            var pr = peranPilih(li);
            k.querySelectorAll('.tr-set').forEach(function (set) {
                set.hidden = set.dataset.set !== pr;
                var sel = set.querySelector('select[data-pilih-ind]');
                var baru = set.querySelector('[data-baru]');
                if (sel && baru) baru.hidden = sel.value !== 'baru';
            });
            // Cabang yang dicentang dibuka supaya anaknya bisa ikut diatur.
            if (ikut) {
                var d = li.querySelector(':scope > details.tr-anak');
                if (d && !d.dataset.dibukaSekali) { d.open = true; d.dataset.dibukaSekali = '1'; }
            }
        }

        form.addEventListener('change', function (e) {
            var li = e.target.closest('li.tr-simpul');
            if (li) segarKartu(li);
            hitungUlang();
        });
        form.addEventListener('input', function (e) {
            if (e.target.matches('input.isian, input.tr-satuan')) hitungUlang();
        });
        form.querySelectorAll('li.tr-simpul').forEach(function (li) { if (dicentang(li)) segarKartu(li); });
        hitungUlang();

        // Tombol "Usulkan dari pohon" membuang isian yang belum disimpan: tanyakan dulu.
        var kotor = false;
        form.addEventListener('change', function () { kotor = true; });
        document.querySelectorAll('[data-aksi="usulkan"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                if (kotor && !window.confirm('Isian yang belum disimpan akan hilang. Lanjutkan mengusulkan dari pohon?')) e.preventDefault();
            });
        });
        form.addEventListener('submit', function () { kotor = false; });
        window.addEventListener('beforeunload', function (e) { if (kotor) { e.preventDefault(); e.returnValue = ''; } });
    });
})();
