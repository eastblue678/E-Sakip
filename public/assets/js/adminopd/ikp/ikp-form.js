/*
 * Form tambah/ubah IKP (app/Views/ikp/form.php).
 *   - kategori penugasan_* -> tampilkan "Dasar penugasan"
 *   - saran Buku Saku (GET ikp/buku-saku?q=, ≥ 3 huruf) -> isi kolom + buku_saku_id
 *   - satuan: daftar master atau tulis sendiri
 *   - pola ukur (hitungan/posisi/rilis): arah, periode & chip bulan ukur, penerbit + saran rilis
 *   - simpul pohon kinerja -> indikator (GET ikp/node)
 *   - penanggung jawab: Select2 AJAX (GET ikp/pegawai?q=) -> jabatan otomatis
 * Semua pilihan divalidasi ulang di server; skrip ini hanya kemudahan.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('form-ikp');
        if (!form) return;
        var cfg = window.IKP_FORM || { metodeJelas: {}, satuan: [] };
        var $ = window.jQuery;
        var A = window.IkpAngka;

        function el(id) { return document.getElementById(id); }
        function setNilai(id, nilai) { var e = el(id); if (e && nilai !== undefined && nilai !== null) e.value = nilai; }

        // ---------------- Kategori ----------------
        function kategori() {
            var r = form.querySelector('input[name="kategori"]:checked');
            return r ? r.value : '';
        }
        function segarKategori() {
            var k = kategori();
            el('blok-dasar').hidden = k.indexOf('penugasan_') !== 0;
            el('label-pu').classList.toggle('wajib', k === 'program_unggulan');
        }
        form.querySelectorAll('input[name="kategori"]').forEach(function (r) { r.addEventListener('change', segarKategori); });
        segarKategori();

        // ---------------- Pola ukur ----------------
        // hitungan -> metode sum (arah disembunyikan); posisi/rilis -> arah.
        // Periode mengisi chip bulan bawaan; chip yang diubah menurunkan periode
        // (cermin ikp_periode_dari_bulan). Rilis: penerbit + saran tabel bawaan.
        var chips = Array.prototype.slice.call(form.querySelectorAll('input[name="bulan_ukur[]"]'));
        var selPeriode = el('periode_ukur');
        function pola() {
            var r = form.querySelector('input[name="pola_ukur"]:checked');
            return r ? r.value : '';
        }
        function bulanUkur() {
            return chips.filter(function (c) { return c.checked; }).map(function (c) { return parseInt(c.value, 10); });
        }
        function setBulan(daftar) {
            chips.forEach(function (c) { c.checked = daftar.indexOf(parseInt(c.value, 10)) !== -1; });
        }
        function periodeDari(b) {
            var n = b.length;
            if (n === 1) return 'tahunan';
            var nama = { 2: 'semesteran', 4: 'triwulanan', 12: 'bulanan' }[n];
            if (!nama) return 'khusus';
            var jarak = 12 / n;
            for (var i = 1; i < n; i++) if (b[i] - b[i - 1] !== jarak) return 'khusus';
            return nama;
        }
        var NAMA_BLN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        function saranRilis() {
            var ss = el('satuan_id');
            var teks = el('output_prioritas').value + ' ' + (ss.options[ss.selectedIndex] && ss.value ? ss.options[ss.selectedIndex].text : '') + ' ' + el('satuan_teks').value;
            var daftar = cfg.saranRilis || [];
            for (var i = 0; i < daftar.length; i++) {
                try { if (new RegExp(daftar[i].cocok, 'i').test(teks)) return daftar[i]; } catch (e) { /* pola tak terbaca: lewati */ }
            }
            return null;
        }
        function segarSaran() {
            var kotak = el('saran-rilis');
            if (pola() !== 'rilis') { kotak.hidden = true; return; }
            var s = saranRilis();
            var b = bulanUkur();
            // Saran disembunyikan bila isian sudah sama persis dengan saran.
            if (!s || (el('penerbit').value.trim() === s.penerbit && b.length === 1 && b[0] === s.bulan
                && el('rilis_tahun_berikut').checked === !!s.tahun_berikut)) { kotak.hidden = true; return; }
            el('saran-rilis-teks').textContent = 'Saran: ' + s.penerbit + ' · rilis ' + NAMA_BLN[s.bulan] + (s.tahun_berikut ? ' tahun berikutnya' : '') + '. ' + s.catatan;
            kotak.hidden = false;
            kotak.dataset.saran = JSON.stringify(s);
        }
        el('saran-rilis-pakai').addEventListener('click', function () {
            var s = JSON.parse(el('saran-rilis').dataset.saran || 'null');
            if (!s) return;
            el('penerbit').value = s.penerbit;
            setBulan([s.bulan]);
            el('rilis_tahun_berikut').checked = !!s.tahun_berikut;
            segarPola();
        });
        function segarPola(ganti) {
            var p = pola();
            if (ganti) {
                // Pindah ke rilis dari "setiap bulan" = hampir pasti salah: rilis bawaan satu bulan (Des).
                var b = bulanUkur();
                if (p === 'rilis' && (b.length === 12 || b.length === 0)) {
                    var s = saranRilis();
                    setBulan([s ? s.bulan : 12]);
                    if (s && !el('penerbit').value.trim()) { el('penerbit').value = s.penerbit; el('rilis_tahun_berikut').checked = !!s.tahun_berikut; }
                } else if (p !== 'rilis' && b.length <= 1) {
                    setBulan([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
                }
            }
            el('blok-arah').hidden = p === 'hitungan' || p === '';
            el('blok-penerbit').hidden = p !== 'rilis';
            el('blok-tahun-berikut').hidden = p !== 'rilis';
            el('label-bulan-ukur').textContent = p === 'rilis' ? 'Bulan rilis' : 'Bulan ukur';
            var b2 = bulanUkur();
            selPeriode.value = periodeDari(b2);
            var ket = el('pola-ket');
            ket.innerHTML = '';
            var m = (cfg.polaMeta || {})[p];
            if (!m) { ket.textContent = 'Pilih salah satu pola ukur.'; segarSaran(); return; }
            var judul = document.createElement('strong'); judul.textContent = m.judul + ': ';
            ket.appendChild(judul);
            var bl = b2.map(function (x) { return NAMA_BLN[x]; }).join(', ') || '(belum dipilih)';
            var kal = p === 'rilis'
                ? 'target hanya pada bulan rilis (' + bl + (el('rilis_tahun_berikut').checked ? ', tahun berikutnya' : '') + '); realisasi diisi saat nilai resmi keluar, wajib dengan tautan bukti publikasi. Bulan lain tampil "—".'
                : (p === 'posisi'
                    ? 'target = posisi yang diharapkan pada bulan ukur (' + (b2.length === 12 ? 'setiap bulan' : bl) + '); capaian = posisi bulan ukur terakhir yang terisi, bukan jumlah.'
                    : 'target bulan ukur (' + (b2.length === 12 ? 'setiap bulan' : bl) + ') adalah cicilan yang jumlahnya = target tahunan; capaian = jumlah realisasi ÷ jumlah target bulan terisi.');
            ket.appendChild(document.createTextNode(kal));
            segarSaran();
        }
        form.querySelectorAll('input[name="pola_ukur"]').forEach(function (r) { r.addEventListener('change', function () { segarPola(true); }); });
        chips.forEach(function (c) { c.addEventListener('change', function () { segarPola(false); }); });
        el('rilis_tahun_berikut').addEventListener('change', function () { segarPola(false); });
        el('penerbit').addEventListener('input', segarSaran);
        selPeriode.addEventListener('change', function () {
            var d = (cfg.periodeBulan || {})[selPeriode.value];
            if (d) setBulan(d);
            segarPola(false);
        });

        // ---------------- Satuan ----------------
        var satSel = el('satuan_id'), blokTeks = el('blok-satuan-teks'), tombolTeks = el('tombol-satuan-teks');
        if ($ && $.fn.select2) {
            $(satSel).select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— pilih satuan —', allowClear: true });
        }
        function modeTeks(aktif) {
            blokTeks.hidden = !aktif;
            tombolTeks.textContent = aktif ? 'Pilih dari daftar satuan' : 'Satuan tidak ada di daftar? Tulis sendiri';
            if (aktif) {
                if ($ && $.fn.select2) $(satSel).val('').trigger('change'); else satSel.value = '';
                el('satuan_teks').focus();
            } else {
                el('satuan_teks').value = '';
            }
        }
        tombolTeks.addEventListener('click', function () { modeTeks(blokTeks.hidden); });
        function pilihSatuan(nama, id) {
            if (id) {
                blokTeks.hidden = true; el('satuan_teks').value = '';
                tombolTeks.textContent = 'Satuan tidak ada di daftar? Tulis sendiri';
                if ($ && $.fn.select2) $(satSel).val(String(id)).trigger('change'); else satSel.value = String(id);
            } else if (nama) {
                modeTeks(true);
                el('satuan_teks').value = nama;
            }
        }

        // ---------------- Buku Saku ----------------
        var ta = el('output_prioritas'), kotakSaran = el('saran-bs'), tunda = null, hasil = [], fokus = -1, terakhirQ = '';
        function tutupSaran() { kotakSaran.hidden = true; kotakSaran.innerHTML = ''; fokus = -1; }
        function tampilSaran(items) {
            hasil = items; kotakSaran.innerHTML = ''; fokus = -1;
            if (!items.length) { tutupSaran(); return; }
            var kepala = document.createElement('div');
            kepala.className = 'px-3 py-2 small text-secondary border-bottom';
            kepala.textContent = 'Saran dari Buku Saku Program Unggulan (' + items.length + ')';
            kotakSaran.appendChild(kepala);
            items.forEach(function (it, i) {
                var b = document.createElement('button');
                b.type = 'button'; b.setAttribute('role', 'option'); b.dataset.i = i;
                var o = document.createElement('div'); o.className = 'o'; o.textContent = it.output_prioritas;
                var m = document.createElement('div'); m.className = 'm';
                m.textContent = [it.pu_nama || 'Tanpa Program Unggulan', it.program_opd, (it.target_5_tahun || it.target_5_tahun_teks || '') + ' ' + (it.satuan || '')]
                    .filter(function (x) { return x && String(x).trim() !== ''; }).join(' · ');
                b.appendChild(o); b.appendChild(m);
                b.addEventListener('mousedown', function (e) { e.preventDefault(); pakai(i); });
                kotakSaran.appendChild(b);
            });
            kotakSaran.hidden = false;
        }
        function pakai(i) {
            var it = hasil[i];
            if (!it) return;
            ta.value = it.output_prioritas || ta.value;
            setNilai('indikator_outcome', it.indikator);
            setNilai('outcome', it.outcome);
            setNilai('program_opd', it.program_opd);
            setNilai('bidang_urusan', it.bidang_urusan);
            if (it.sasaran_pembangunan_id) setNilai('sasaran_pembangunan_id', String(it.sasaran_pembangunan_id));
            if (it.rpjmd_misi_id) setNilai('rpjmd_misi_id', String(it.rpjmd_misi_id));
            var pu = form.querySelector('input[name="program_unggulan_id"][value="' + (it.program_unggulan_id || '') + '"]');
            if (pu) pu.checked = true;
            pilihSatuan(it.satuan, it.satuan_id);
            if (it.target_5_tahun) { setNilai('target_5_tahun', it.target_5_tahun); setNilai('target_5_tahun_teks', ''); }
            else if (it.target_5_tahun_teks) { setNilai('target_5_tahun_teks', it.target_5_tahun_teks); }
            el('buku_saku_id').value = it.id;
            el('bs-tanda-teks').textContent = 'Terhubung ke Buku Saku #' + it.id;
            el('bs-tanda').hidden = false;
            tutupSaran();
            A.toast('Kolom diisi dari Buku Saku. Periksa kembali sebelum menyimpan.');
        }
        ta.addEventListener('input', function () {
            clearTimeout(tunda);
            var q = ta.value.trim();
            if (q.length < 3) { tutupSaran(); return; }
            tunda = setTimeout(function () {
                if (q === terakhirQ && !kotakSaran.hidden) return;
                terakhirQ = q;
                var url = form.dataset.urlBukuSaku + (form.dataset.urlBukuSaku.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q.slice(0, 100));
                A.ambil(url).then(function (d) {
                    if (ta.value.trim() === q) tampilSaran(d.items || []);
                }).catch(function () { tutupSaran(); });
            }, 300);
        });
        ta.addEventListener('keydown', function (e) {
            if (kotakSaran.hidden) return;
            var tombol = kotakSaran.querySelectorAll('button');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                fokus = e.key === 'ArrowDown' ? Math.min(tombol.length - 1, fokus + 1) : Math.max(0, fokus - 1);
                tombol.forEach(function (b, i) { b.classList.toggle('fokus', i === fokus); });
                if (tombol[fokus]) tombol[fokus].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && fokus >= 0) {
                e.preventDefault(); pakai(fokus);
            } else if (e.key === 'Escape') {
                tutupSaran();
            }
        });
        ta.addEventListener('blur', function () { setTimeout(tutupSaran, 150); });
        el('bs-lepas').addEventListener('click', function () {
            el('buku_saku_id').value = '';
            el('bs-tanda').hidden = true;
        });

        // ---------------- Simpul pohon kinerja ----------------
        var selSimpul = el('cascading_sasaran_id'), selInd = el('cascading_indikator_id');
        var simpul = [];
        function isiIndikator(idSimpul, pilih) {
            selInd.innerHTML = '';
            var n = simpul.filter(function (s) { return String(s.id) === String(idSimpul); })[0];
            var o0 = document.createElement('option');
            if (!n) { o0.value = ''; o0.textContent = '— pilih simpul dulu —'; selInd.appendChild(o0); selInd.disabled = true; return; }
            o0.value = ''; o0.textContent = n.indikator.length ? '— (opsional) pilih indikator —' : 'Simpul ini belum punya indikator';
            selInd.appendChild(o0);
            n.indikator.forEach(function (i) {
                var o = document.createElement('option');
                o.value = i.id; o.textContent = i.indikator + (i.satuan ? ' (' + i.satuan + ')' : '');
                if (String(i.id) === String(pilih)) o.selected = true;
                selInd.appendChild(o);
            });
            selInd.disabled = !n.indikator.length;
        }
        A.ambil(form.dataset.urlNode).then(function (d) {
            simpul = d.nodes || [];
            selSimpul.innerHTML = '';
            var o0 = document.createElement('option');
            o0.value = ''; o0.textContent = simpul.length ? '— tidak ditautkan —' : 'Pohon kinerja OPD ini belum berisi simpul';
            selSimpul.appendChild(o0);
            var grup = {};
            simpul.forEach(function (s) {
                if (!grup[s.level_label]) {
                    grup[s.level_label] = document.createElement('optgroup');
                    grup[s.level_label].label = s.level_label;
                    selSimpul.appendChild(grup[s.level_label]);
                }
                var o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.sasaran.length > 140 ? s.sasaran.slice(0, 137) + '…' : s.sasaran;
                if (String(s.id) === String(form.dataset.simpul)) o.selected = true;
                grup[s.level_label].appendChild(o);
            });
            isiIndikator(form.dataset.simpul, form.dataset.indikator);
            if ($ && $.fn.select2) {
                $(selSimpul).select2({ theme: 'bootstrap-5', width: '100%' }).on('change', function () { isiIndikator(selSimpul.value, ''); });
            } else {
                selSimpul.addEventListener('change', function () { isiIndikator(selSimpul.value, ''); });
            }
        }).catch(function (e) {
            selSimpul.innerHTML = '<option value="">Gagal memuat simpul</option>';
            A.toast(e.message, 'galat');
        });

        // ---------------- Penanggung jawab ----------------
        if ($ && $.fn.select2) {
            $('#pj_pegawai_id').select2({
                theme: 'bootstrap-5', width: '100%', allowClear: true,
                placeholder: 'Cari nama atau jabatan…',
                minimumInputLength: 0,
                ajax: {
                    url: form.dataset.urlPegawai, dataType: 'json', delay: 250,
                    data: function (p) { return { q: p.term || '' }; },
                    processResults: function (d) { return { results: d.results || [] }; }
                },
                language: {
                    noResults: function () { return 'Pegawai tidak ditemukan'; },
                    searching: function () { return 'Mencari…'; },
                    errorLoading: function () { return 'Gagal memuat daftar pegawai'; }
                }
            }).on('select2:select', function (e) {
                var d = e.params.data || {};
                if (d.jabatan) el('pj_jabatan_teks').value = d.jabatan;
            }).on('select2:clear', function () {
                el('pj_jabatan_teks').value = '';
            });
        }

        segarPola(false);
        // Saran penerbit ikut berubah saat nama/satuan indikator diubah.
        el('output_prioritas').addEventListener('change', segarSaran);
        if ($ && $.fn.select2) $(satSel).on('change', segarSaran); else satSel.addEventListener('change', segarSaran);

        // ---------------- Kirim ----------------
        form.addEventListener('submit', function (e) {
            var salah = [];
            if (!kategori()) salah.push('Pilih kategori IKP.');
            if (!ta.value.trim()) salah.push('Indikator IKP wajib diisi.');
            if (!satSel.value && !el('satuan_teks').value.trim()) salah.push('Satuan wajib diisi.');
            if (!pola()) salah.push('Pilih pola ukur (hitungan, posisi, atau rilis).');
            if (!bulanUkur().length) salah.push(pola() === 'rilis' ? 'Pilih bulan rilis.' : 'Pilih minimal satu bulan ukur.');
            if (pola() === 'rilis' && !el('penerbit').value.trim()) salah.push('Isi penerbit nilai resmi.');
            ['baseline', 'target_5_tahun'].forEach(function (id) {
                if (!A.sah(el(id).value)) salah.push((id === 'baseline' ? 'Baseline' : 'Target 5 tahun') + ' harus berupa angka.');
            });
            if (!el('target_5_tahun').value.trim() && !el('target_5_tahun_teks').value.trim()) salah.push('Isi target 5 tahun (angka atau uraian).');
            if (kategori() === 'program_unggulan' && !form.querySelector('input[name="program_unggulan_id"]:checked:not([value=""])')) {
                salah.push('Pilih salah satu Program Unggulan Bupati.');
            }
            if (salah.length) {
                e.preventDefault();
                A.toast(salah.join(' '), 'galat');
            }
        });
    });
})();
