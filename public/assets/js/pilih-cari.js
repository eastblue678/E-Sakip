/*
 * AKSARA+ — isian pilihan yang bisa dicari (Select2) dan kotak SARING daftar,
 * untuk semua halaman admin. Dimuat footer adminOpd & adminKabupaten lewat
 * app/Views/templates/pilih_cari.php, SESUDAH jQuery + Select2.
 *
 * 1. JEMBATAN EVENT. Select2 mengabarkan pilihan dengan jQuery
 *    .trigger('change'). Event buatan jQuery itu TIDAK sampai ke pendengar
 *    addEventListener('change') bawaan peramban — akibatnya "Fokus cabang" dan
 *    "Tahun" di bagan Pohon Kinerja dulu tidak bereaksi saat diklik. Di sini
 *    trigger('change') TANPA namespace pada <select> ber-Select2 diganti SATU
 *    event 'change' bawaan yang menggelembung. Pendengar jQuery dan atribut
 *    onchange menerima event yang sama itu, jadi tidak ada panggilan ganda.
 *    trigger('change.select2') (menyegarkan tampilan saja) tidak disentuh.
 * 2. PASANG OTOMATIS saat DOMContentLoaded:
 *    - select.form-select di luar form POST dan modal (aturan lama footer);
 *    - select[data-pilih-cari] di mana pun — juga di form POST dan modal
 *      (dropdownParent = modal itu, agar kotak cari bisa diketik).
 *    data-no-select2 tetap berarti "jangan".
 * 3. KOTAK CARI hanya bila pilihan > 8 (minimumResultsForSearch 9), sebagai
 *    bawaan SEMUA Select2 non-AJAX yang tidak menyetelnya sendiri.
 *    Untuk SEMUA Select2 (juga yang dipasang skrip lama, mis. initSelect2
 *    pk-form*.js) lewat select2:open di document: kotak cari difokuskan di
 *    layar lebar, dan Enter/Esc yang sudah dipakai Select2 (memilih hasil,
 *    menutup daftar) tidak diteruskan ke leluhurnya — tidak menyimpan
 *    formulir modal, tidak menutup modal Bootstrap.
 * 4. API window.AksaraPilih:
 *    pasang(akar, pemilih?)  pasang Select2 pada baris yang baru disisipkan;
 *    segarkan(el)            segarkan tampilan sesudah .value diubah dari kode;
 *    klon(node)              cloneNode tanpa sisa Select2 (baris kloning);
 *    saring(kotak)           kotak saring daftar kartu/baris (lihat di bawah).
 */
(function (w, d) {
    'use strict';

    var MIN_CARI = 9; // kotak cari muncul bila pilihan > 8

    // ------------------------------------------------------------------
    // 1. Jembatan event
    // ------------------------------------------------------------------
    function pasangJembatan($) {
        if (!$ || !$.event || !$.event.special) { return; }
        var sp = $.event.special.change || ($.event.special.change = {});
        if (sp._aksaraJembatan) { return; }
        var lama = sp.trigger;
        sp.trigger = function (ev) {
            if (lama && lama.apply(this, arguments) === false) { return false; }
            // Ada namespace (change.select2) atau data tambahan: biarkan jQuery.
            if (arguments.length > 1 || (ev && ev.namespace)) { return; }
            if (this && this.nodeName === 'SELECT' && this.classList.contains('select2-hidden-accessible')) {
                this.dispatchEvent(new Event('change', { bubbles: true }));
                return false; // jQuery tidak memanggil pendengarnya lagi
            }
        };
        sp._aksaraJembatan = true;
    }

    // ------------------------------------------------------------------
    // 3. Bawaan kotak cari (hanya untuk daftar statis)
    // ------------------------------------------------------------------
    function pasangBawaanCari($) {
        var asli = $ && $.fn && $.fn.select2;
        if (!asli || asli._aksaraBawaan) { return; }
        var bungkus = function (opsi) {
            if (opsi == null || $.isPlainObject(opsi)) {
                var o = $.extend({}, opsi || {});
                // AJAX & tags: hasil baru ada sesudah mengetik — kotak cari wajib ada.
                if (o.minimumResultsForSearch === undefined && !o.ajax && !o.tags) {
                    o.minimumResultsForSearch = MIN_CARI;
                }
                var args = Array.prototype.slice.call(arguments);
                args[0] = o;
                return asli.apply(this, args);
            }
            return asli.apply(this, arguments);
        };
        for (var k in asli) {
            if (Object.prototype.hasOwnProperty.call(asli, k)) { bungkus[k] = asli[k]; }
        }
        bungkus._aksaraBawaan = true;
        $.fn.select2 = bungkus;
    }

    // ------------------------------------------------------------------
    // 3b. Perilaku seragam saat daftar Select2 dibuka (semua instans)
    // ------------------------------------------------------------------
    // jQuery 3.6 memutus fokus otomatis Select2 ke kotak cari. Di layar lebar
    // kotak cari langsung difokuskan; di ponsel tidak, supaya papan ketik tidak
    // menutup daftar (pengguna tinggal mengetuk kotak cari bila perlu).
    function fokusCari(inst) {
        if (w.innerWidth < 768 || !inst.$dropdown) { return; }
        setTimeout(function () {
            var f = inst.$dropdown.find('.select2-search--dropdown:not(.select2-search--hide) .select2-search__field')[0];
            if (f && d.activeElement !== f) { f.focus(); }
        }, 0);
    }

    // Enter (memilih hasil) dan Esc (menutup daftar) yang sudah ditangani Select2
    // ditandai preventDefault. Tanpa ini keydown-nya terus menggelembung: Enter
    // memicu pendengar "Enter = simpan" di modal, Esc menutup seluruh modal
    // Bootstrap (keydown.dismiss.bs.modal tidak memeriksa defaultPrevented).
    // Esc saat daftar TERTUTUP tidak di-preventDefault Select2, jadi tetap
    // menutup modal seperti biasa.
    var TOMBOL_DIPAKAI = { Enter: true, Escape: true, Esc: true };
    function tahanTombol(e) {
        if (TOMBOL_DIPAKAI[e.key] && e.isDefaultPrevented()) { e.stopPropagation(); }
    }

    function pasangSaatBuka($) {
        if ($._aksaraSaatBuka) { return; }
        $._aksaraSaatBuka = true;
        $(d).on('select2:open.aksaraPilih', function (e) {
            var inst = $(e.target).data('select2');
            if (!inst) { return; }
            if (!inst._aksaraTombol) {
                inst._aksaraTombol = true;
                // $container: kotak terpilih (+ kotak cari inline pilihan ganda);
                // $dropdown: daftar + kotak cari — keduanya di bawah dropdownParent.
                if (inst.$container) { inst.$container.on('keydown.aksaraPilih', tahanTombol); }
                if (inst.$dropdown) { inst.$dropdown.on('keydown.aksaraPilih', tahanTombol); }
            }
            fokusCari(inst);
        });
    }

    function siapkan() {
        var $ = w.jQuery;
        pasangJembatan($);
        pasangBawaanCari($);
        if ($ && $.fn && $.fn.select2) { pasangSaatBuka($); return $; }
        return null;
    }

    // ------------------------------------------------------------------
    // 2. Pemasangan
    // ------------------------------------------------------------------
    function pasangSatu($, el) {
        var $s = $(el);
        if ($s.hasClass('select2-hidden-accessible') || $s.is('[data-no-select2]')) { return; }
        var $modal = $s.closest('.modal');
        var opsi = { width: '100%', theme: 'bootstrap-5', dropdownParent: $modal.length ? $modal : $(d.body) };
        // Ukuran kecil ikut form-select-sm / input-group-sm (kelas tema bootstrap-5).
        if ($s.hasClass('form-select-sm') || $s.closest('.input-group-sm').length) {
            opsi.selectionCssClass = 'select2--small';
            opsi.dropdownCssClass = 'select2--small';
        }
        $s.select2(opsi); // fokus kotak cari & Enter/Esc: pasangSaatBuka (semua instans)
    }

    function bolehOtomatis($s) {
        if ($s.hasClass('pg-size')) { return false; }                // pemilih baris pagination
        if ($s.closest('.modal').length) { return false; }           // modal: hanya lewat data-pilih-cari
        var f = $s.closest('form');                                  // form input (POST): idem
        return !(f.length && (f.attr('method') || 'get').toLowerCase() === 'post');
    }

    /** Pasang Select2 pada select[data-pilih-cari] (atau `pemilih`) di dalam/akar itu sendiri. */
    function pasang(akar, pemilih) {
        var $ = siapkan();
        if (!$) { return; }
        var sel = pemilih || 'select[data-pilih-cari]';
        var $akar = $(akar || d);
        $akar.filter(sel).add($akar.find(sel)).each(function () { pasangSatu($, this); });
    }

    /**
     * Segarkan tampilan Select2 sesudah .value/option diubah dari kode, TANPA
     * memicu pendengar apa pun. (trigger('change.select2') ikut menjalankan
     * atribut onchange inline — perilaku bawaan jQuery — jadi dihindari.)
     */
    function segarkan(el) {
        var $ = w.jQuery;
        if (!$ || !el || !el.classList || !el.classList.contains('select2-hidden-accessible')) { return; }
        var inst = $(el).data('select2');
        if (inst && inst.dataAdapter && typeof inst.trigger === 'function') {
            inst.dataAdapter.current(function (data) { inst.trigger('selection:update', { data: data }); });
        } else {
            $(el).trigger('change.select2');
        }
    }

    /** cloneNode(true) tanpa sisa Select2: kontainer dibuang, select kembali polos. */
    function klon(node) {
        var c = node.cloneNode(true);
        Array.prototype.forEach.call(c.querySelectorAll('.select2-container'), function (x) { x.remove(); });
        Array.prototype.forEach.call(c.querySelectorAll('select.select2-hidden-accessible'), function (s) {
            s.classList.remove('select2-hidden-accessible');
            ['data-select2-id', 'aria-hidden', 'tabindex'].forEach(function (a) { s.removeAttribute(a); });
            Array.prototype.forEach.call(s.querySelectorAll('[data-select2-id]'), function (o) { o.removeAttribute('data-select2-id'); });
        });
        return c;
    }

    // Validasi bawaan (required) pada select yang disembunyikan Select2: tandai
    // kontainernya merah (tema bootstrap-5: .is-invalid + .select2-container).
    d.addEventListener('invalid', function (e) {
        var t = e.target;
        if (t && t.nodeName === 'SELECT' && t.classList.contains('select2-hidden-accessible')) {
            t.classList.add('is-invalid');
            t.setAttribute('data-pilih-invalid', '');
        }
    }, true);
    d.addEventListener('change', function (e) {
        var t = e.target;
        if (t && t.nodeName === 'SELECT' && t.hasAttribute('data-pilih-invalid') && t.checkValidity()) {
            t.classList.remove('is-invalid');
            t.removeAttribute('data-pilih-invalid');
        }
    });

    // ------------------------------------------------------------------
    // 4. Kotak saring daftar kartu/baris
    //
    // <div data-saring-wadah="#id" data-saring-butir="li.x"
    //      data-saring-teks=".a, .b">          (opsional; bawaan: seluruh teks butir)
    //   <input type="search" data-saring-input>
    //   <input type="checkbox" data-saring-hanya=":scope > .y.ikut">   (opsional)
    //   <span data-saring-info></span>  <p data-saring-kosong hidden>…</p>
    // </div>
    // Butir boleh bersarang (pohon): butir tampil bila cocok atau punya turunan
    // yang cocok (diberi .saring-konteks), dan <details> di jalurnya dibuka.
    // Isian di butir tersembunyi TETAP ikut terkirim — saringan hanya tampilan.
    // ------------------------------------------------------------------
    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
    }

    function saring(kotak) {
        if (!kotak || kotak._aksaraSaring) { return; }
        var wadah = d.querySelector(kotak.getAttribute('data-saring-wadah'));
        var pilihButir = kotak.getAttribute('data-saring-butir');
        var input = kotak.querySelector('[data-saring-input]');
        if (!wadah || !pilihButir || !input) { return; }
        kotak._aksaraSaring = true;
        var hanya = kotak.querySelector('[data-saring-hanya]');
        var info = kotak.querySelector('[data-saring-info]');
        var kosong = kotak.querySelector('[data-saring-kosong]');
        var pilihTeks = kotak.getAttribute('data-saring-teks');
        var satuan = kotak.getAttribute('data-saring-satuan') || 'butir';
        var butir = Array.prototype.slice.call(wadah.querySelectorAll(pilihButir));
        var induk = butir.map(function (b) { return b.parentElement ? b.parentElement.closest(pilihButir) : null; });
        var teks = butir.map(function (b) {
            if (!pilihTeks) { return norm(b.textContent); }
            return norm(Array.prototype.filter.call(b.querySelectorAll(pilihTeks), function (x) {
                return x.closest(pilihButir) === b;
            }).map(function (x) { return x.textContent; }).join(' '));
        });
        var detailsAsli = null; // keadaan <details> sebelum saringan aktif

        function terapkan() {
            var kata = norm(input.value).split(' ').filter(Boolean);
            var selHanya = hanya && hanya.checked ? hanya.getAttribute('data-saring-hanya') : '';
            var aktif = kata.length > 0 || selHanya !== '';
            var semuaDetails = wadah.querySelectorAll('details');

            if (!aktif) {
                butir.forEach(function (b) { b.classList.remove('saring-sembunyi', 'saring-konteks'); });
                if (detailsAsli) {
                    detailsAsli.forEach(function (p) { p[0].open = p[1]; });
                    detailsAsli = null;
                }
                if (info) { info.textContent = ''; }
                if (kosong) { kosong.hidden = true; }
                return;
            }
            if (!detailsAsli) {
                detailsAsli = Array.prototype.map.call(semuaDetails, function (x) { return [x, x.open]; });
            }

            var cocok = butir.map(function (b, i) {
                if (selHanya && !b.querySelector(selHanya)) { return false; }
                return kata.every(function (k) { return teks[i].indexOf(k) !== -1; });
            });
            var tampil = cocok.slice();
            // Dari belakang (turunan selalu sesudah leluhurnya dalam urutan dokumen).
            for (var i = butir.length - 1; i >= 0; i--) {
                if (tampil[i] && induk[i]) {
                    var j = butir.indexOf(induk[i]);
                    if (j >= 0) { tampil[j] = true; }
                }
            }
            var n = 0;
            butir.forEach(function (b, i) {
                b.classList.toggle('saring-sembunyi', !tampil[i]);
                b.classList.toggle('saring-konteks', tampil[i] && !cocok[i]);
                if (cocok[i]) { n++; }
            });
            // Buka <details> cabang yang memuat butir tampil; tutup cabang lain.
            Array.prototype.forEach.call(semuaDetails, function (x) {
                var isi = x.querySelectorAll(pilihButir);
                if (!isi.length) { return; }
                x.open = Array.prototype.some.call(isi, function (b) { return !b.classList.contains('saring-sembunyi'); });
            });
            if (info) { info.textContent = n + ' dari ' + butir.length + ' ' + satuan + ' cocok'; }
            if (kosong) { kosong.hidden = n > 0; }
        }

        input.addEventListener('input', terapkan);
        input.addEventListener('search', terapkan);
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
        // Sengaja tidak menyaring ulang saat isian di dalam daftar berubah:
        // butir yang baru saja di-uncheck tidak boleh hilang dari bawah kursor.
        if (hanya) { hanya.addEventListener('change', terapkan); }
        if (input.value || (hanya && hanya.checked)) { terapkan(); }
    }

    // ------------------------------------------------------------------
    siapkan();
    w.AksaraPilih = { pasang: pasang, segarkan: segarkan, klon: klon, saring: saring, MIN_CARI: MIN_CARI };

    function mulai() {
        var $ = siapkan(); // halaman bisa memuat ulang jQuery/Select2 sesudah footer
        if ($) {
            $('select.form-select').each(function () {
                var $s = $(this);
                if ($s.is('[data-pilih-cari]') || !bolehOtomatis($s)) { return; }
                pasangSatu($, this);
            });
            pasang(d);
        }
        Array.prototype.forEach.call(d.querySelectorAll('[data-saring-wadah]'), saring);
    }
    if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', mulai); } else { mulai(); }
})(window, document);
