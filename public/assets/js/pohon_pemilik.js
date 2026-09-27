/**
 * AKSARA+ — pemilik & pelaksana di bagan Pohon Kinerja (adminOpd/cascading/_pohon_pemilik_*.php).
 *
 * - "Tampilkan di pohon" / "Sorot yang belum berpemilik": kelas pada pembungkus bagan (pilihan diingat per peramban).
 * - "Fokus cabang": bagan digambar ulang hanya dengan jalur akar → cabang Eselon III terpilih (salinan simpul,
 *   bukan menyembunyikan saudara, supaya garis penghubung .tree tetap benar), lalu dipaskan ke layar.
 * - "Paskan layar": skala = lebar layar / lebar bagan (0,3–1), memakai pohonZoom() milik halaman.
 * - Daftar "simpul belum berpemilik" menunjuk simpulnya di bagan; daftar pegawai bisa dicari & disaring.
 */
(function () {
    'use strict';

    var alat = document.getElementById('ppAlat');
    var tree = document.getElementById('tree-container');
    if (!alat || !tree) { return; }
    var bungkus = tree.parentElement;          // .tree-container (overflow-x)
    var asli = tree.innerHTML;                 // bagan penuh, untuk kembali dari "Fokus cabang"

    function simpan(k, v) { try { localStorage.setItem('aksara.pohonPemilik.' + k, v); } catch (e) { /* mode privat */ } }
    function baca(k) { try { return localStorage.getItem('aksara.pohonPemilik.' + k); } catch (e) { return null; } }

    function tandaiBelum() {
        Array.prototype.forEach.call(tree.querySelectorAll('.tree-node[data-simpul]'), function (n) {
            n.classList.toggle('pp-belum', !!n.querySelector(':scope > .bp-kosong'));
        });
    }

    function terapkanSkala(s) {
        s = Math.min(1.2, Math.max(0.3, Math.round(s * 100) / 100));
        if (typeof pohonZoom === 'function') {
            try { _pohonZoom = s; } catch (e) { /* variabel halaman tidak ada */ }
            pohonZoom(0);
        }
    }
    function lebarAlami() {
        var t = tree.style.transform;
        tree.style.transform = 'none';
        var w = tree.scrollWidth;
        tree.style.transform = t;
        return w;
    }
    function paskan() {
        var ruang = bungkus.clientWidth - 8;
        var w = lebarAlami();
        if (w > 0 && ruang > 0) { terapkanSkala(Math.min(1, ruang / w)); }
        tengahkan();
    }
    function tengahkan() {
        bungkus.scrollLeft = Math.max(0, (bungkus.scrollWidth - bungkus.clientWidth) / 2);
    }

    // ---- Sakelar ----
    var cTampil = document.getElementById('ppTampil');
    var cSorot = document.getElementById('ppSorot');
    function aturTampil() {
        bungkus.classList.toggle('pp-tanpa-pemilik', !cTampil.checked);
        if (!cTampil.checked && cSorot.checked) { cSorot.checked = false; aturSorot(); }
        simpan('tampil', cTampil.checked ? '1' : '0');
        terapkanSkala(typeof _pohonZoom === 'number' ? _pohonZoom : 0.6);
    }
    function aturSorot() {
        if (cSorot.checked && !cTampil.checked) { cTampil.checked = true; aturTampil(); }
        bungkus.classList.toggle('pp-sorot', cSorot.checked);
    }
    if (baca('tampil') === '0') { cTampil.checked = false; }
    cTampil.addEventListener('change', aturTampil);
    cSorot.addEventListener('change', aturSorot);

    // ---- Fokus cabang ----
    var sFokus = document.getElementById('ppFokus');
    function fokus(id) {
        tree.innerHTML = asli;
        if (id) {
            var node = tree.querySelector('.tree-node[data-simpul="' + id + '"]');
            var li = node ? node.parentElement : null;
            if (li && li.tagName === 'LI') {
                var rantai = [];
                for (var x = li.parentElement; x && x !== tree; x = x.parentElement) {
                    if (x.tagName === 'LI') { rantai.unshift(x); }
                }
                var akar = document.createElement('ul'), kini = akar;
                rantai.forEach(function (induk) {
                    var baru = document.createElement('li');
                    var kotak = induk.querySelector(':scope > .tree-node');
                    if (kotak) { baru.appendChild(kotak.cloneNode(true)); }
                    var ul = document.createElement('ul');
                    baru.appendChild(ul);
                    kini.appendChild(baru);
                    kini = ul;
                });
                kini.appendChild(li.cloneNode(true));
                tree.innerHTML = '';
                tree.appendChild(akar);
            }
        }
        tandaiBelum();
        paskan();
    }
    sFokus.addEventListener('change', function () { fokus(sFokus.value); });

    // ---- Tahun & paskan ----
    document.getElementById('ppTahun').addEventListener('change', function () {
        var u = new URL(window.location.href);
        u.searchParams.set('tahun', this.value);
        window.location.href = u.toString();
    });
    document.getElementById('ppPaskan').addEventListener('click', paskan);

    // ---- Daftar "simpul belum berpemilik" → tunjukkan di bagan ----
    document.addEventListener('click', function (e) {
        var semua = e.target.closest('[data-pp-semua]');
        if (semua) {
            Array.prototype.forEach.call(document.querySelectorAll('#' + semua.getAttribute('data-pp-semua') + ' > li'), function (li) { li.hidden = false; });
            semua.remove();
            return;
        }
        var b = e.target.closest('[data-pp-ke]');
        if (!b) { return; }
        var id = b.getAttribute('data-pp-ke');
        if (sFokus.value) { sFokus.value = ''; fokus(''); }
        if (!cTampil.checked) { cTampil.checked = true; aturTampil(); }
        var node = tree.querySelector('.tree-node[data-simpul="' + id + '"]');
        if (!node) { return; }
        Array.prototype.forEach.call(tree.querySelectorAll('.pp-tuju'), function (n) { n.classList.remove('pp-tuju'); });
        node.classList.add('pp-tuju');
        node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        setTimeout(function () { node.classList.remove('pp-tuju'); }, 4000);
    });

    // ---- Pegawai belum punya tugas: cari, saring kategori, tampilkan semua ----
    var daftar = document.getElementById('ppDaftarPegawai');
    if (daftar) {
        var cari = document.getElementById('ppCariPegawai');
        var lebih = document.getElementById('ppLebih');
        var takAda = document.getElementById('ppTakAda');
        var kat = '', semua = false;
        var saring = function () {
            var q = (cari.value || '').trim().toLowerCase(), terlihat = 0;
            Array.prototype.forEach.call(daftar.children, function (li) {
                var cocok = (!kat || li.getAttribute('data-kat') === kat) && (!q || li.getAttribute('data-cari').indexOf(q) !== -1);
                var batas = !semua && !q && !kat && li.hasAttribute('data-pp-lebih');
                li.hidden = !cocok || batas;
                if (!li.hidden) { terlihat++; }
            });
            takAda.classList.toggle('d-none', terlihat > 0);
            if (lebih) { lebih.hidden = semua || !!q || !!kat; }
        };
        cari.addEventListener('input', saring);
        Array.prototype.forEach.call(document.querySelectorAll('.pp-kat'), function (b) {
            b.addEventListener('click', function () {
                kat = b.getAttribute('data-pp-kat') || '';
                Array.prototype.forEach.call(document.querySelectorAll('.pp-kat'), function (x) { x.classList.toggle('aktif', x === b); });
                saring();
            });
        });
        if (lebih) { lebih.addEventListener('click', function () { semua = true; saring(); }); }
    }

    // ---- Awal: bagan terbuka dengan akarnya di tengah, bukan di tepi kanan ----
    aturTampil();
    tandaiBelum();
    window.addEventListener('load', function () { setTimeout(tengahkan, 50); });
})();
