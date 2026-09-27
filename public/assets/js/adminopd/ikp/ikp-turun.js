/*
 * AKSARA+ — Turunkan IKP (app/Views/ikp/turun.php).
 *
 *   - centang "Ikut memikul" membuka isian simpul; peran memilih set isian
 *     (pemikul angka / pendukung); "Buat indikator baru" membuka teks & satuan;
 *   - pemeriksa per jenjang dihitung ulang seketika — CERMINAN dari
 *     App\Services\IkpTurunService::periksa() (ubah keduanya bersamaan; angka
 *     yang tersimpan tetap diperiksa server dan hasilnya tampil sesudah Simpan);
 *   - persentase porsi terhadap target induknya.
 * Formulir dikirim biasa (POST), tidak lewat AJAX.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-turun');
        if (!form || form.dataset.boleh !== '1') return;
        var A = window.IkpAngka;
        var pola = form.dataset.pola;
        var tIkp = form.dataset.target === '' ? null : parseFloat(form.dataset.target);
        var TOL = 0.005;

        function fmt(v) { return A ? A.fmt(v, 4) : String(v); }
        function baca(t) { return A ? A.baca(t, false) : (t === '' ? null : parseFloat(String(t).replace(',', '.'))); }

        function kartu(li) { return li.querySelector(':scope > .tr-kartu'); }
        function dicentang(li) { var c = kartu(li).querySelector('input[data-ikut]'); return !!(c && c.checked); }
        function peran(li) { var r = kartu(li).querySelector('input[data-peran]:checked'); return r ? r.value : 'angka'; }
        function target(li) {
            if (peran(li) === 'angka' && pola !== 'hitungan') return tIkp;
            var el = kartu(li).querySelector('.tr-set[data-set="' + peran(li) + '"] input[name$="[target]"], .tr-set[data-set="' + peran(li) + '"] input[name$="[target_proses]"]');
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

        // Cerminan IkpTurunService::periksa().
        function periksa(tInduk, anak, akar, peranInduk) {
            var angka = anak.filter(function (a) { return a.peran === 'angka'; });
            var nA = angka.length, nP = anak.length - nA;
            function h(kode, warna, pesan) { return { kode: kode, warna: warna, pesan: pesan }; }
            if (peranInduk === 'pendukung' && !akar) {
                return nA > 0
                    ? h('terputus', 'peringatan', 'Pemikul angka di bawah pendukung: rantai angka terputus. Jadikan simpul di atasnya pemikul angka, atau jadikan simpul ini pendukung.')
                    : h(nP > 0 ? 'pendukung' : 'belum', 'netral', nP > 0 ? nP + ' pendukung.' : 'Tidak diturunkan lagi.');
            }
            if (nA === 0) {
                if (akar) return nP > 0 ? h('tanpa_angka', 'peringatan', 'Belum ada pemikul angka; pendukung tidak menambah angka IKP.')
                    : h('belum', 'peringatan', 'IKP belum diturunkan ke jenjang mana pun.');
                return nP > 0 ? h('tetap_di_atas', 'netral', 'Angka tetap dipikul jenjang ini; ' + nP + ' pendukung di bawahnya.')
                    : h('belum', 'netral', 'Tidak diturunkan lagi (dipikul jenjang ini).');
            }
            if (pola === 'hitungan') {
                if (tInduk === null) return h('tanpa_target', 'peringatan', 'Target ' + (akar ? 'tahunan IKP' : 'induk') + ' belum berupa angka; porsi tidak dapat diperiksa.');
                var kosong = 0, jumlah = 0;
                angka.forEach(function (a) { if (a.target === null) kosong++; else jumlah += a.target; });
                var selisih = Math.round((jumlah - tInduk) * 10000) / 10000;
                var tambah = kosong > 0 ? ' ' + kosong + ' porsi belum diisi.' : '';
                if (Math.abs(selisih) <= TOL && kosong === 0) return h('habis', 'ok', 'Terbagi habis: ' + fmt(jumlah) + ' = ' + fmt(tInduk) + '.');
                if (selisih < -TOL || (kosong > 0 && Math.abs(selisih) <= TOL)) {
                    return h('kurang', 'peringatan', 'Kurang ' + fmt(Math.abs(selisih)) + ': porsi ' + fmt(jumlah) + ' dari ' + fmt(tInduk) + ' (sisanya belum diturunkan).' + tambah);
                }
                return h('lebih', 'peringatan', 'Lebih ' + fmt(selisih) + ': porsi ' + fmt(jumlah) + ' melebihi ' + fmt(tInduk) + '.' + tambah);
            }
            if (nA > 1) return h('ganda', 'peringatan', nA + ' pemikul angka. Nilai ' + (pola === 'rilis' ? 'rilis' : 'posisi') + ' tidak dibagi: pilih SATU pemikul angka di jenjang ini, yang lain jadikan pendukung.');
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
                (grup[k] = grup[k] || []).push({ li: li, peran: peran(li), target: target(li) });
            });
            tulis(form.querySelector('.tr-periksa[data-periksa="akar"]'), periksa(tIkp, grup.akar, true, 'angka'), true);
            semua.forEach(function (li) {
                var kotak = li.querySelector(':scope > .tr-periksa');
                if (!kotak) return;
                var ikut = dicentang(li);
                var anak = grup[li.dataset.node] || [];
                var pr = peran(li);
                tulis(kotak, periksa(pr === 'angka' ? target(li) : null, anak, false, pr), ikut && anak.length > 0);
            });
            // Persentase porsi terhadap induk (hitungan).
            semua.forEach(function (li) {
                var el = kartu(li).querySelector('[data-persen]');
                if (!el) return;
                el.textContent = '';
                if (!dicentang(li) || peran(li) !== 'angka' || pola !== 'hitungan') return;
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
            var pr = peran(li);
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
            if (e.target.matches('input.isian')) hitungUlang();
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
