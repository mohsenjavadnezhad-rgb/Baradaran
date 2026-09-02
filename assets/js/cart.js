document.addEventListener('DOMContentLoaded', function () {
    function sendCartAjax(formData, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'cart.php', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function () {
            if (callback) callback(xhr);
        };
        var params = [];
        formData.forEach(function (v, k) { params.push(encodeURIComponent(k) + '=' + encodeURIComponent(v)); });
        xhr.send(params.join('&'));
    }

    document.querySelectorAll('.add-to-cart-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var fd = new FormData();
            fd.append('action', 'add');
            fd.append('product_id', id);
            fd.append('quantity', '1');
            sendCartAjax(fd, function (xhr) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    showFlash(resp.success ? 'flash-success' : 'flash-error', resp.message);
                    if (resp.success) updateCartBadge(resp.cart ? resp.cart.count : null);
                } catch (e) {}
            });
        });
    });

    function showFlash(type, msg) {
        var existing = document.querySelector('.ajax-flash');
        if (existing) existing.parentNode.removeChild(existing);

        var div = document.createElement('div');
        div.className = 'flash ' + type + ' ajax-flash';
        div.textContent = msg;
        var main = document.querySelector('.site-main');
        if (main) main.insertBefore(div, main.firstChild);

        setTimeout(function () {
            div.style.transition = 'opacity 0.5s';
            div.style.opacity = '0';
            setTimeout(function () { if (div.parentNode) div.parentNode.removeChild(div); }, 500);
        }, 3000);
    }

    /* count عددی = مقدار دقیق از سرور، null = فقط یکی اضافه کن */
    function updateCartBadge(count) {
        var badge = document.querySelector('.cart-badge');
        if (!badge) {
            var cartLink = document.querySelector('.cart-link');
            if (cartLink) {
                badge = document.createElement('span');
                badge.className = 'cart-badge';
                cartLink.appendChild(badge);
            }
        }
        if (!badge) return;

        var n = (count === null || count === undefined || isNaN(count))
              ? (parseInt(badge.textContent, 10) || 0) + 1
              : parseInt(count, 10);
        badge.textContent = n;
        badge.style.display = n > 0 ? '' : 'none';
    }

    /* ==================================================================
       استپر تعداد در صفحهٔ سبد خرید
       ------------------------------------------------------------------
       دکمه‌های +/− در HTML از نوع submit هستند تا بدون جاوااسکریپت هم
       کار کنند (سرور با name="step" تعداد را یکی کم/زیاد می‌کند). این‌جا
       کلیک را می‌گیریم و همان کار را بدون رفرش انجام می‌دهیم. قیمت واحد،
       نشان «کلی/جزئی»، جمع ردیف و مبلغ کل همیشه از پاسخ سرور می‌آیند،
       چون آستانهٔ قیمت عمده باید سمت سرور محاسبه شود.
       ================================================================== */
    var faDigits = '۰۱۲۳۴۵۶۷۸۹', arDigits = '٠١٢٣٤٥٦٧٨٩';

    function toLatinDigits(s) {
        return String(s)
            .replace(/[۰-۹]/g, function (d) { return faDigits.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return arDigits.indexOf(d); });
    }

    function fieldQty(field) {
        var n = parseInt(toLatinDigits(field.value).replace(/\D+/g, ''), 10);
        return isNaN(n) ? 0 : n;
    }

    function closestRow(el) {
        while (el && el.nodeName !== 'TR') el = el.parentNode;
        return (el && el.nodeName === 'TR') ? el : null;
    }

    function applyCartState(form, cart) {
        if (!cart) return;
        var row = closestRow(form);

        if (cart.item && row) {
            if (cart.item.removed) {
                if (row.parentNode) row.parentNode.removeChild(row);
            } else {
                var field = form.querySelector('.cart-qty-field');
                if (field) field.value = cart.item.quantity;

                var sub = row.querySelector('.cart-subtotal');
                if (sub) sub.innerHTML = cart.item.sub_html;

                var unit = row.querySelector('.cart-unit-price');
                if (unit) unit.innerHTML = cart.item.price_html;

                var tag = row.querySelector('.cart-price-type');
                if (tag) {
                    tag.className = 'cart-price-type ' + cart.item.price_type;
                    tag.textContent = cart.item.type_label;
                }
            }
        }

        var total = document.querySelector('.cart-total-val');
        if (total) total.innerHTML = cart.total_html;

        /* ردیف مالیات: نمایش/مخفی + مقدار. وقتی بلوک ارسال روی صفحه نیست،
           ردیف «مبلغ کل» را هم همین‌جا زنده می‌کنیم چون applyShipState برای
           این حالت چیزی به‌روز نمی‌کند (cart.ship وقتی ارسال خاموش است null است). */
        var taxRow = document.getElementById('cart-tax-row');
        var taxCell = document.getElementById('cart-tax-cell');
        if (taxCell) taxCell.innerHTML = cart.tax_html || '';
        if (taxRow) taxRow.hidden = !cart.tax_html;
        var allTotal = document.getElementById('cart-alltotal-cell');
        if (allTotal) allTotal.innerHTML = cart.alltotal_html || cart.total_html;

        updateCartBadge(cart.count);
        applyShipState(cart.ship);

        /* سبد خالی شد → صفحه را نو کن تا بلوک «سبد خالی است» بیاید */
        if (cart.empty) window.location.reload();
    }

    /* ==================================================================
       بلوک «روش و هزینهٔ ارسال» صفحهٔ سبد
       ------------------------------------------------------------------
       با هر تغییر تعداد، وزن سبد عوض می‌شود و نرخ همهٔ روش‌ها، «کم‌ترین
       هزینه»، هزینهٔ روش انتخاب‌شده و مبلغ قابل پرداخت باید با آن عوض شوند
       (خواستهٔ مدیر: «وزن و هزینه بر اساس تعداد در سبد من تغییر کنه»).
       هیچ محاسبه‌ای این‌جا انجام نمی‌شود: همهٔ رشته‌ها را سرور با همان
       shippingCartSummary() می‌سازد که رندر اول صفحه را ساخته، پس عدد روی
       صفحه هرگز با عددی که در ثبت سفارش حساب می‌شود اختلاف پیدا نمی‌کند.
       ================================================================== */
    function applyShipState(ship) {
        if (!ship) return;

        (ship.rows || []).forEach(function (r) {
            var lbl = document.querySelector('.cart-ship-row[data-key="' + r.key + '"]');
            if (!lbl) return;

            var price = lbl.querySelector('.csr-price');
            if (price) {
                price.textContent = r.price || '';
                price.classList.toggle('is-soft', !!r.soft);
            }
            lbl.classList.toggle('is-nocost', !!r.badge_only);
            lbl.classList.toggle('is-best', !!r.best);
            lbl.classList.toggle('is-on', r.key === ship.pick);
            /* روشی که برای شهر مشتری فعال نیست باید غیرفعال بماند */
            lbl.classList.toggle('is-off', !!r.off);

            var radio = lbl.querySelector('input[type="radio"]');
            if (radio) {
                radio.disabled = !!r.off;
                radio.checked = (r.key === ship.pick);
            }
        });

        var wLine = document.querySelector('#cart-ship-weight .csw-line');
        if (wLine) wLine.textContent = ship.weight_line || '';
        var wBest = document.querySelector('#cart-ship-weight .csw-best');
        if (wBest) wBest.innerHTML = ship.best_html || '';

        var costCell = document.querySelector('.cart-ship-val');
        if (costCell) {
            costCell.textContent = ship.cost_text || '';
            costCell.classList.toggle('is-soft', !!ship.cost_soft);
        }
        var payCell = document.querySelector('.cart-pay-val');
        if (payCell) payCell.innerHTML = ship.payable_html || '';

        /* تا روش ارسال انتخاب نشده، رفتن به صفحهٔ ثبت سفارش بی‌معنی است */
        var next = document.getElementById('cart-next');
        if (next) {
            next.classList.toggle('is-locked', !ship.ready);
            if (ship.ready) next.removeAttribute('aria-disabled');
            else next.setAttribute('aria-disabled', 'true');
        }
        var hint = document.getElementById('cart-go-hint');
        if (hint) hint.hidden = !!ship.ready;
    }

    function pushQty(form, qty) {
        /* هر درخواست یک شمارهٔ ترتیب دارد؛ پاسخ دیرکردهٔ کلیک‌های سریع
           نباید مقدار تازه‌تر را خراب کند. */
        form.__seq = (form.__seq || 0) + 1;
        var mine = form.__seq;
        var stepper = form.querySelector('.cart-stepper');
        if (stepper) stepper.classList.add('is-busy');

        var pid = form.querySelector('input[name="product_id"]');
        var fd = new FormData();
        fd.append('action', 'update');
        fd.append('product_id', pid ? pid.value : '');
        fd.append('quantity', String(qty));

        sendCartAjax(fd, function (xhr) {
            if (form.__seq !== mine) return;
            if (stepper) stepper.classList.remove('is-busy');
            var resp;
            try { resp = JSON.parse(xhr.responseText); }
            catch (e) { form.submit(); return; }   /* پاسخ نامعتبر → رفرش معمولی */

            if (!resp.success && resp.message) showFlash('flash-error', resp.message);
            applyCartState(form, resp.cart);
        });
    }

    /* تعداد پیشنهادی را در بازهٔ [1..موجودی] نگه می‌دارد (حذف فقط با دکمهٔ «حذف») */
    function clampQty(field, wanted) {
        var max = parseInt(field.getAttribute('data-max'), 10);
        if (wanted < 1) return 1;
        if (!isNaN(max) && max > 0 && wanted > max) {
            showFlash('flash-error', 'بیشتر از موجودی انبار (' + max + ' عدد) نمی‌توان سفارش داد.');
            return max;
        }
        return wanted;
    }

    document.querySelectorAll('.cart-update-form').forEach(function (form) {
        var field = form.querySelector('.cart-qty-field');
        if (!field) return;

        form.querySelectorAll('.qty-step').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var delta = parseInt(btn.value, 10) || 0;
                var next = clampQty(field, fieldQty(field) + delta);
                if (next === fieldQty(field)) return;
                field.value = next;
                pushQty(form, next);
            });
        });

        field.addEventListener('change', function () {
            var next = clampQty(field, fieldQty(field));
            field.value = next;
            pushQty(form, next);
        });

        /* Enter داخل فیلد → اعمال همان عدد، بدون submit شدن فرم */
        field.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); field.blur(); }
        });

        form.addEventListener('submit', function (e) { e.preventDefault(); });
    });

    /* ------------------------------------------------------------------
       انتخاب روش ارسال در همین صفحه
       انتخاب در نشست سرور ذخیره می‌شود (نه در فرم صفحهٔ بعد)، تا صفحهٔ
       ثبت سفارش فقط ثبت نهایی باشد و رقم ارسال دست‌کاری‌پذیر نباشد.
       بدون جاوااسکریپت همین فرم submit می‌شود و صفحه با انتخاب تازه
       دوباره رندر می‌گردد؛ این‌جا فقط رفرش را حذف می‌کنیم.
       ------------------------------------------------------------------ */
    var shipForm = document.getElementById('cart-ship-form');
    if (shipForm) {
        shipForm.addEventListener('submit', function (e) { e.preventDefault(); });

        shipForm.querySelectorAll('input[name="shipping_method"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!radio.checked) return;

                shipForm.__seq = (shipForm.__seq || 0) + 1;
                var mine = shipForm.__seq;
                shipForm.classList.add('is-busy');

                var fd = new FormData();
                fd.append('action', 'ship');
                fd.append('shipping_method', radio.value);

                sendCartAjax(fd, function (xhr) {
                    if (shipForm.__seq !== mine) return;
                    shipForm.classList.remove('is-busy');
                    var resp;
                    try { resp = JSON.parse(xhr.responseText); }
                    catch (e) { shipForm.submit(); return; }   /* پاسخ نامعتبر → رفرش معمولی */

                    if (!resp.success && resp.message) showFlash('flash-error', resp.message);
                    applyShipState(resp.cart ? resp.cart.ship : null);
                    if (resp.cart) updateCartBadge(resp.cart.count);
                });
            });
        });
    }
});
