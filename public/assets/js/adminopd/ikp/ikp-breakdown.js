/*
 * Grid breakdown massal (app/Views/ikp/breakdown.php): cek per baris, TW
 * otomatis (tab bulanan), Bagi Rata per baris, simpan per baris (Enter /
 * tombol) ke POST ikp/breakdown/save. Balasan server (nilai terformat + cek)
 * menggantikan hitungan layar.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tabel = document.getElementById('grid-breakdown');
        if (!tabel) return;
        var A = window.IkpAngka;
        var jenis = tabel.dataset.jenis;          // tahunan | bulanan
        var tahun = parseInt(tabel.dataset.tahun, 10);
        var url = tabel.dataset.urlSimpan;
        var adaKotor = false;

        function angka(s) { return s === '' || s === undefined ? null : parseFloat(s); }
        function isian(tr) { return Array.prototype.slice.call(tr.querySelectorAll('input.isian')); }
        function induk(tr) { return jenis === 'tahunan' ? angka(tr.dataset.t5) : angka(tr.dataset.induk); }

        function pola(tr) { try { return JSON.parse(tr.dataset.pola || '{}'); } catch (e) { return {}; } }

        function segar(tr) {
            var inputs = isian(tr);
            var nilai = inputs.map(function (el) { return el.dataset.ukur === '0' ? null : A.baca(el.value, true); });
            var sel = tr.querySelector('.sel-cek');
            sel.innerHTML = '';
            if (jenis === 'bulanan') {
                // Pola ukur: hanya bulan ukur yang diperiksa & direkap.
                var bulan = {};
                inputs.forEach(function (el, i) { bulan[el.dataset.kunci] = nilai[i]; });
                sel.appendChild(A.lencanaCek(A.cekPola(pola(tr), induk(tr), bulan)));
                var tw = A.triwulan(bulan, tr.dataset.metode);
                tr.querySelectorAll('td[data-tw]').forEach(function (td) { td.textContent = A.fmt(tw[td.dataset.tw], 4); });
            } else {
                sel.appendChild(A.lencanaCek(A.cek(tr.dataset.metode, induk(tr), nilai)));
            }
        }

        function simpan(tr) {
            var inputs = isian(tr);
            var salah = inputs.filter(function (el) { return !A.sah(el.value); });
            if (salah.length) {
                salah.forEach(function (el) { el.classList.add('salah'); });
                salah[0].focus();
                A.toast('Masih ada isian yang bukan angka pada baris ini.', 'galat');
                return;
            }
            var nilai = {};
            // Bulan non-ukur tidak dikirim: isian lamanya tetap tersimpan (tidak dihitung, tidak dihapus).
            inputs.forEach(function (el) { if (el.dataset.ukur !== '0') nilai[el.dataset.kunci] = el.value; });
            var tombol = tr.querySelector('.tombol-simpan');
            if (tombol) tombol.disabled = true;
            A.kirim(url, { ikp_id: parseInt(tr.dataset.ikp, 10), jenis: jenis, tahun: tahun, nilai: nilai })
                .then(function (d) {
                    var sel = tr.querySelector('.sel-cek');
                    if (jenis === 'tahunan') {
                        inputs.forEach(function (el) { if (d.tahunan[el.dataset.kunci] !== undefined) el.value = d.tahunan[el.dataset.kunci]; });
                        sel.innerHTML = ''; sel.appendChild(A.lencanaCek(d.cek_tahunan));
                    } else if (d.baris) {
                        inputs.forEach(function (el) { if (el.dataset.ukur !== '0') el.value = d.baris.bulan[el.dataset.kunci].target; });
                        tr.querySelectorAll('td[data-tw]').forEach(function (td) { td.textContent = d.baris.triwulan[td.dataset.tw].target; });
                        sel.innerHTML = ''; sel.appendChild(A.lencanaCek(d.baris.cek_bulanan));
                    }
                    inputs.forEach(function (el) { el.classList.remove('berubah', 'salah'); });
                    tr.classList.remove('kotor');
                    tr.classList.remove('simpan-ok'); void tr.offsetWidth; tr.classList.add('simpan-ok');
                    adaKotor = !!tabel.querySelector('tr.kotor');
                    A.toast(d.pesan);
                })
                .catch(function (e) { A.toast(e.message, 'galat'); })
                .then(function () { if (tombol) tombol.disabled = false; });
        }

        function bagi(tr) {
            var total = induk(tr);
            if (total === null) {
                A.toast(jenis === 'tahunan' ? 'Target 5 tahun IKP ini belum berupa angka.' : 'Target tahunan ' + tahun + ' IKP ini belum diisi.', 'galat');
                return;
            }
            var inputs = isian(tr).filter(function (el) { return el.dataset.ukur !== '0'; });
            var awal = jenis === 'tahunan' ? angka(tr.dataset.baseline) : angka(tr.dataset.awal);
            var hasil;
            if (jenis === 'tahunan') {
                hasil = A.bagiRata(total, inputs.length, tr.dataset.metode, awal, tr.dataset.bulat === '1');
            } else {
                var peta = A.bagiPola(total, pola(tr), awal, tr.dataset.bulat === '1');
                hasil = Object.keys(peta).length ? inputs.map(function (el) { return peta[el.dataset.kunci]; }) : [];
            }
            if (!hasil.length) { A.toast('Pilih pola ukur IKP ini dulu (form IKP).', 'galat'); return; }
            var ada = inputs.some(function (el) { return !A.kosong(el.value); });
            (ada ? A.tanya('Isian pada baris ini akan diganti. Lanjutkan?') : Promise.resolve(true)).then(function (ya) {
                if (!ya) return;
                inputs.forEach(function (el, i) { el.value = A.fmt(hasil[i], 4); el.classList.add('berubah'); el.classList.remove('salah'); });
                tr.classList.add('kotor'); adaKotor = true;
                segar(tr);
                A.toast('Hasil Bagi Rata belum tersimpan — tekan Simpan pada baris ini.');
            });
        }

        tabel.querySelectorAll('tbody tr').forEach(function (tr) {
            segar(tr);
            isian(tr).forEach(function (el) {
                el.addEventListener('input', function () {
                    el.classList.add('berubah'); tr.classList.add('kotor'); adaKotor = true; segar(tr);
                });
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); simpan(tr); }
                });
            });
            var s = tr.querySelector('.tombol-simpan');
            if (s) s.addEventListener('click', function () { simpan(tr); });
            var b = tr.querySelector('.tombol-bagi');
            if (b) b.addEventListener('click', function () { bagi(tr); });
        });

        window.addEventListener('beforeunload', function (e) {
            if (adaKotor) { e.preventDefault(); e.returnValue = ''; }
        });
    });
})();
