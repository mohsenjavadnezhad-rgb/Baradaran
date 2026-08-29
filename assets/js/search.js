document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.querySelector('.search-input');
    if (!searchInput) return;

    var debounceTimer, activeXhr;
    var wrap = searchInput.closest('.header-search') || searchInput.parentNode;
    wrap.classList.add('search-live-wrap');

    var dropdown = document.createElement('div');
    dropdown.className = 'search-drop';
    dropdown.hidden = true;
    wrap.appendChild(dropdown);

    /* برای دراپ‌داون، نامِ محصول را خودمان با <mark> عبارتِ جست‌وجوشده را
       پررنگ می‌کنیم — بدونِ کتابخانه، با escape دستی تا HTML تزریق نشود. */
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function highlight(text, q) {
        var e = esc(text);
        var eq = esc(q).trim();
        if (!eq) return e;
        try {
            var re = new RegExp('(' + eq.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'i');
            return e.replace(re, '<mark>$1</mark>');
        } catch (err) { return e; }
    }

    function render(list, q) {
        if (!list.length) {
            dropdown.innerHTML = '<div class="search-drop-empty">نتیجه‌ای برای «' + esc(q) + '» پیدا نشد</div>';
            dropdown.hidden = false;
            return;
        }
        var rows = list.slice(0, 8).map(function (p) {
            var tags = '';
            if (p.brand_name) tags += '<span class="sd-tag sd-tag-brand">' + esc(p.brand_name) + '</span>';
            if (p.part_cat_name) tags += '<span class="sd-tag sd-tag-cat">' + esc(p.part_cat_name) + '</span>';

            var img = p.image
                ? '<img src="uploads/products/' + esc(p.image) + '" alt="">'
                : '<span class="sd-noimg">&#9881;</span>';

            return '<a href="product.php?id=' + encodeURIComponent(p.id) + '" class="search-drop-row">' +
                '<span class="sd-img">' + img + '</span>' +
                '<span class="sd-body">' +
                    '<span class="sd-name">' + highlight(p.name, q) + '</span>' +
                    (tags ? '<span class="sd-tags">' + tags + '</span>' : '') +
                    '<span class="sd-tech" dir="ltr">' + esc(p.technical_number || '') + '</span>' +
                '</span>' +
                '<span class="sd-price">' + esc(p.price_formatted) + '<i>تومان</i></span>' +
            '</a>';
        }).join('');

        dropdown.innerHTML = rows +
            '<a href="search.php?q=' + encodeURIComponent(q) + '" class="search-drop-more">مشاهدهٔ همهٔ نتایج برای «' + esc(q) + '»</a>';
        dropdown.hidden = false;
    }

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var q = this.value.trim();

        if (q.length < 2) {
            dropdown.hidden = true;
            dropdown.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(function () {
            if (activeXhr) activeXhr.abort();
            var xhr = new XMLHttpRequest();
            activeXhr = xhr;
            xhr.open('GET', 'search-ajax.php?q=' + encodeURIComponent(q), true);
            xhr.onload = function () {
                if (xhr !== activeXhr) return;
                try {
                    render(JSON.parse(xhr.responseText), q);
                } catch (e) {
                    dropdown.hidden = true;
                }
            };
            xhr.onerror = function () { if (xhr === activeXhr) dropdown.hidden = true; };
            xhr.send();
        }, 300);
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) dropdown.hidden = true;
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') dropdown.hidden = true;
    });

    searchInput.addEventListener('focus', function () {
        if (dropdown.innerHTML !== '' && searchInput.value.trim().length >= 2) dropdown.hidden = false;
    });
});
