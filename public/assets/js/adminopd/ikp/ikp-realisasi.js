/*
 * Grid realisasi bulanan (app/Views/ikp/realisasi.php).
 *   - hanya bulan yang DIUBAH yang dikirim (realisasi_oleh/pada bulan lain tidak tersentuh)
 *   - Enter / tombol = simpan baris -> POST ikp/realisasi/save
 *   - ikon klip = modal keterangan & tautan bukti per bulan
 *   - balasan server membawa rekap TW + capaian terbaru untuk baris itu
 * Realisasi 0 sah (data-nol="sah"); bulan terkunci ditolak lagi di server.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tabel = document.getElementById('grid-realisasi');
        if (!tabel) return;
        var A = window.IkpAngka;
        var tahun = parseInt(tabel.dataset.tahun, 10);
        var url = tabel.dataset.urlSimpan;
        var palet = JSON.parse(tabel.dataset.palet || '{}');
        var kotor = {};   // ikp_id -> {bulan: true}

        var IKON = { hijau: 'fa-circle-check', biru: 'fa-arrow-trend-up', kuning: 'fa-circle-half-stroke', oranye: 'fa-triangle-exclamation', merah: 'fa-circle-exclamation' };

        function lencana(warna, label, judul) {
            var w = palet[warna] || palet.abu || { hex: '#8a968f', soft: '#f1f3f2' };
            var s = document.createElement('span');
            s.className = 'ikp-status';
            s.style.background = w.soft; s.style.color = w.hex;
            if (judul) s.title = judul;
            s.innerHTML = '<i class="fas ' + (IKON[warna] || 'fa-minus') + '"></i>';
            s.appendChild(document.createTextNode(label));
            return s;
        }

        function isiTw(td, t) {
            td.innerHTML = '';
            if (t.diukur === false) {
                // Triwulan tanpa bulan ukur: capaian tidak dihitung (pola ukur).
                var n = document.createElement('span'); n.className = 'sel-tidak-ukur'; n.title = t.keterangan || '';
                n.textContent = '—';
                var k = document.createElement('small'); k.textContent = t.status === 'menunggu_rilis' ? 'bukan bulan rilis' : 'tidak diukur';
                n.appendChild(k); td.appendChild(n);
                return;
            }
            if (t.realisasi === '-' && t.target === '-') { td.innerHTML = '<span class="bulan-kunci">–</span>'; return; }
            var d = document.createElement('div'); d.className = 'ikp-tw';
            var r = document.createElement('span'); r.className = 'r'; r.textContent = t.realisasi;
            var g = document.createElement('span'); g.className = 't'; g.textContent = ' / ' + t.target;
            d.appendChild(r); d.appendChild(g); td.appendChild(d);
            if (t.capaian !== null || t.status !== 'belum_ada_data') td.appendChild(lencana(t.warna, t.capaian !== null ? t.capaian : t.status_label, t.keterangan));
            if (t.berjalan) { var b = document.createElement('div'); b.className = 't'; b.style.fontSize = '.66rem'; b.style.color = '#7b8a80'; b.textContent = 'berjalan' + (t.sampai_label ? ' · capaian s.d. ' + t.sampai_label : ''); td.appendChild(b); }
        }

        function isiYtd(td, tb) {
            td.innerHTML = '';
            if (tb.persen !== null) { var p = document.createElement('div'); p.className = 'ikp-persen'; p.style.fontSize = '.9rem'; p.textContent = tb.persen; td.appendChild(p); }
            td.appendChild(lencana(tb.warna, tb.status_label, tb.keterangan));
            if (tb.sampai_bulan) {
                var s = document.createElement('div'); s.className = 't'; s.style.fontSize = '.66rem'; s.style.color = '#7b8a80';
                s.textContent = 's.d. ' + ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][tb.sampai_bulan];
                td.appendChild(s);
            }
        }

        function terapkan(tr, baris) {
            if (!baris) return;
            tr.querySelectorAll('input.isian').forEach(function (el) {
                var b = baris.bulan[el.dataset.bulan];
                if (b) el.value = b.realisasi;
                el.classList.remove('berubah', 'salah');
            });
            tr.querySelectorAll('td[data-bulan]').forEach(function (td) {
                var b = baris.bulan[td.dataset.bulan];
                if (!b || td.dataset.ukur === '0') return;
                td.dataset.ket = b.keterangan; td.dataset.bukti = b.bukti_url;
                var tombol = td.querySelector('.cat');
                if (!tombol) return;
                var ada = b.keterangan !== '' || b.bukti_url !== '';
                tombol.classList.toggle('ada', ada);
                tombol.innerHTML = tombol.classList.contains('catat-rilis')
                    ? '<i class="fas fa-certificate"></i> Catat nilai resmi'
                    : '<i class="fas fa-paperclip"></i>' + (ada ? ' ada' : '');
            });
            tr.querySelectorAll('td[data-tw]').forEach(function (td) { isiTw(td, baris.triwulan[td.dataset.tw]); });
            isiYtd(tr.querySelector('td[data-ytd]'), baris.tahun_berjalan);
            tr.classList.remove('simpan-ok'); void tr.offsetWidth; tr.classList.add('simpan-ok');
        }

        function simpanBaris(tr) {
            var id = tr.dataset.ikp;
            var ubah = kotor[id] || {};
            var bulan = {}, salah = [];
            Object.keys(ubah).forEach(function (m) {
                var el = tr.querySelector('input.isian[data-bulan="' + m + '"]');
                if (!el || el.disabled) return;
                if (!A.sah(el.value)) { salah.push(el); return; }
                bulan[m] = { realisasi: el.value };
            });
            if (salah.length) {
                salah.forEach(function (el) { el.classList.add('salah'); });
                salah[0].focus();
                A.toast('Realisasi harus berupa angka (contoh: 1.250 atau 12,5).', 'galat');
                return;
            }
            if (!Object.keys(bulan).length) { A.toast('Tidak ada perubahan pada baris ini.'); return; }
            var tombol = tr.querySelector('.tombol-simpan');
            if (tombol) tombol.disabled = true;
            A.kirim(url, { ikp_id: parseInt(id, 10), tahun: tahun, bulan: bulan })
                .then(function (d) {
                    terapkan(tr, d.baris);
                    delete kotor[id];
                    tr.classList.remove('kotor');
                    A.toast(d.pesan);
                })
                .catch(function (e) { A.toast(e.message, 'galat'); })
                .then(function () { if (tombol) tombol.disabled = false; });
        }

        tabel.querySelectorAll('tbody tr').forEach(function (tr) {
            tr.querySelectorAll('input.isian').forEach(function (el) {
                // Nilai rilis diisi lewat modal (wajib bukti publikasi), bukan diketik di kisi.
                if (el.dataset.rilis === '1') {
                    el.addEventListener('click', function () {
                        var t = el.closest('td').querySelector('button.catat-rilis');
                        if (t && !t.disabled) t.click();
                    });
                    return;
                }
                el.addEventListener('input', function () {
                    (kotor[tr.dataset.ikp] = kotor[tr.dataset.ikp] || {})[el.dataset.bulan] = true;
                    el.classList.add('berubah');
                    tr.classList.add('kotor');
                });
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); simpanBaris(tr); }
                });
            });
            var t = tr.querySelector('.tombol-simpan');
            if (t) t.addEventListener('click', function () { simpanBaris(tr); });
        });

        // ------------------- Modal keterangan & bukti -------------------
        var modalEl = document.getElementById('modal-catatan');
        var modal = (window.bootstrap && modalEl) ? new window.bootstrap.Modal(modalEl) : null;
        var aktif = null; // {tr, td, bulan}
        var inKet = document.getElementById('cat-keterangan');
        var inBukti = document.getElementById('cat-bukti');
        var buka = document.getElementById('cat-bukti-buka');

        function segarBuka() {
            var v = inBukti.value.trim();
            var ok = /^https?:\/\/\S+$/i.test(v);
            buka.hidden = !ok;
            if (ok) buka.querySelector('a').href = v;
        }
        inBukti.addEventListener('input', segarBuka);

        var blokNilai = document.getElementById('blok-nilai-rilis');
        var inNilai = document.getElementById('cat-nilai');
        tabel.addEventListener('click', function (e) {
            var b = e.target.closest('button.cat');
            if (!b || b.disabled || !modal) return;
            var td = b.closest('td'), tr = b.closest('tr');
            var rilis = b.classList.contains('catat-rilis');
            aktif = { tr: tr, td: td, bulan: td.dataset.bulan, rilis: rilis };
            blokNilai.hidden = !rilis;
            document.getElementById('cat-bukti-label').textContent = rilis ? 'Tautan bukti publikasi resmi (wajib)' : 'Tautan bukti dukung (opsional)';
            document.getElementById('cat-bukti-label').classList.toggle('wajib', rilis);
            if (rilis) {
                inNilai.value = td.querySelector('input.isian').value;
                var pen = tr.dataset.penerbit;
                document.getElementById('cat-nilai-ket').textContent = 'Tulis persis nilai yang diumumkan' + (pen ? ' ' + pen : ' penerbit') + '. Nilai rilis tidak dicicil dan tidak dijumlah.';
            }
            var nama = tr.querySelector('.lekat .nama').textContent.trim();
            document.getElementById('modal-catatan-judul').textContent = 'Keterangan & bukti — ' + ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][td.dataset.bulan] + ' ' + tahun;
            document.getElementById('modal-catatan-ikp').textContent = nama;
            inKet.value = td.dataset.ket || '';
            inBukti.value = td.dataset.bukti || '';
            var bisa = !!document.getElementById('cat-simpan') && !td.querySelector('input.isian').disabled;
            inKet.disabled = !bisa; inBukti.disabled = !bisa; inNilai.disabled = !bisa;
            var sb = document.getElementById('cat-simpan');
            if (sb) sb.hidden = !bisa;
            segarBuka();
            modal.show();
        });

        var catSimpan = document.getElementById('cat-simpan');
        if (catSimpan) catSimpan.addEventListener('click', function () {
            if (!aktif) return;
            var bukti = inBukti.value.trim();
            if (bukti !== '' && !/^https?:\/\/\S+$/i.test(bukti)) {
                A.toast('Tautan bukti harus diawali http:// atau https://', 'galat');
                inBukti.focus();
                return;
            }
            var bulan = {};
            bulan[aktif.bulan] = { keterangan: inKet.value.trim(), bukti_url: bukti };
            if (aktif.rilis) {
                if (!A.sah(inNilai.value)) { A.toast('Nilai resmi harus berupa angka (contoh: 97,25).', 'galat'); inNilai.focus(); return; }
                if (!A.kosong(inNilai.value) && bukti === '') { A.toast('Nilai resmi wajib disertai tautan bukti publikasi.', 'galat'); inBukti.focus(); return; }
                bulan[aktif.bulan].realisasi = inNilai.value;
            }
            catSimpan.disabled = true;
            A.kirim(url, { ikp_id: parseInt(aktif.tr.dataset.ikp, 10), tahun: tahun, bulan: bulan })
                .then(function (d) {
                    // Jangan menimpa isian realisasi yang belum disimpan pada baris ini.
                    var belum = kotor[aktif.tr.dataset.ikp] || {};
                    var simpanNilai = {};
                    Object.keys(belum).forEach(function (m) {
                        var el = aktif.tr.querySelector('input.isian[data-bulan="' + m + '"]');
                        if (el) simpanNilai[m] = el.value;
                    });
                    terapkan(aktif.tr, d.baris);
                    Object.keys(simpanNilai).forEach(function (m) {
                        var el = aktif.tr.querySelector('input.isian[data-bulan="' + m + '"]');
                        el.value = simpanNilai[m]; el.classList.add('berubah');
                    });
                    modal.hide();
                    A.toast(d.pesan);
                })
                .catch(function (e) { A.toast(e.message, 'galat'); })
                .then(function () { catSimpan.disabled = false; });
        });

        window.addEventListener('beforeunload', function (e) {
            if (Object.keys(kotor).length) { e.preventDefault(); e.returnValue = ''; }
        });
    });
})();
