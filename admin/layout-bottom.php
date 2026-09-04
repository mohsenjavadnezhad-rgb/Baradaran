</div></main></div>
<script>
/* «انتخاب فایل» سفارشی برای هر input[type=file].form-control — رجوع کنید به
   کامنت .file-pick در assets/css/style.css. */
(function () {
    var UPLOAD_ICON = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2.5 15.5v3a2 2 0 0 0 2 2h15a2 2 0 0 0 2-2v-3"/><path d="m7 8.5 5-5 5 5"/><path d="M12 3.5v13"/></svg>';

    function enhance(input) {
        if (input.dataset.filePickDone) return;
        input.dataset.filePickDone = '1';

        var wrap = document.createElement('div');
        wrap.className = 'file-pick';
        input.parentNode.insertBefore(wrap, input);

        var box = document.createElement('div');
        box.className = 'file-pick-box';
        var defaultTxt = input.multiple ? 'عکس‌ها را آپلود کنید' : 'عکس را آپلود کنید';
        box.innerHTML = UPLOAD_ICON + '<span class="file-pick-txt">' + defaultTxt + '</span>';
        wrap.appendChild(box);
        wrap.appendChild(input);
        input.classList.add('file-pick-input');

        input.addEventListener('change', function () {
            var txt = box.querySelector('.file-pick-txt');
            if (!input.files || !input.files.length) {
                txt.textContent = defaultTxt;
                box.classList.remove('has-file');
                return;
            }
            txt.textContent = input.files.length > 1
                ? (input.files.length + ' فایل انتخاب شد')
                : input.files[0].name;
            box.classList.add('has-file');
        });
    }

    document.querySelectorAll('input[type="file"].form-control').forEach(enhance);
})();

/* کادر تأیید — جایگزین پاپ‌آپ بومی confirm()/alert() (خواستهٔ کاربر: «یک
   پیام روی خود صفحه با یک کادر زیبا بیاد، دیگه مثل پاپ‌آپ نشون نده»؛
   ۲۰۲۶-۰۹-۰۳: «هرجا پاپ ... هرجا این‌کارو کردی تو سایت پیغامش رو بیار
   توی صفحه، طراحی کم» — یعنی همین دو کامپوننت زیر باید همه‌جای پنل ادمین
   جای confirm()/alert() خام را بگیرند، نه فقط دکمه‌های حذف).
   ---------------------------------------------------------------------
   ۱) showConfirm(msg, onYes, opts) — هر <a>/<button> با data-confirm="متن"
      را می‌گیرد: کلیک اول پیش‌فرض را می‌گیرد، کادر را نشان می‌دهد؛ تأیید
      یعنی همان اقدام اصلی (رفتن به href یا submit فرم) دوباره اجرا شود —
      این‌بار بدون دخالت، چون data-confirm-ok روی خود عنصر گذاشته می‌شود.
      پیش‌فرض هنوز همان کادر «حذف» قرمز قدیمی است (بک‌ورد-سازگار با ۶ دکمهٔ
      حذف موجود، بدون نیاز به تغییر خودشان)؛ برای اقدام‌های غیرمخرب (تأیید
      واریز، ثبت تسویه، ...) سه ویژگی اختیاری تن را عوض می‌کنند:
      data-confirm-icon="check" (پیش‌فرض trash)، data-confirm-label="تأیید"
      (پیش‌فرض «حذف شود»)، data-confirm-tone="primary" (پیش‌فرض danger).
      window.adminConfirm همین تابع را هم بیرون می‌گذارد تا جاوااسکریپت‌های
      دیگر صفحات (مثلا تأیید پویا با شمارش در product-edit.php) هم بتوانند
      مستقیم صدایش بزنند، نه فقط از data-confirm.
   ۲) adminToast(msg, ok) — جایگزین alert(): همان «توست» شناور سبد خرید
      (کلاس‌های cart-toast مشترک در style.css)، پایین صفحه، خودش بعد از
      چند ثانیه محو می‌شود؛ محتوای صفحه هیچ‌وقت با آمدنش نمی‌پرد. */
(function () {
    var ICONS = {
        trash: <?= json_encode(icon('trash', 'ic-sm'), JSON_UNESCAPED_UNICODE) ?>,
        check: <?= json_encode(icon('check-circle', 'ic-sm'), JSON_UNESCAPED_UNICODE) ?>,
        alert: <?= json_encode(icon('alert', 'ic-sm'), JSON_UNESCAPED_UNICODE) ?>,
        x:     <?= json_encode(icon('x', 'ic-sm'), JSON_UNESCAPED_UNICODE) ?>
    };

    function showConfirm(msg, onYes, opts) {
        opts = opts || {};
        var iconKey = ICONS[opts.icon] ? opts.icon : 'trash';
        var label   = opts.label || 'حذف شود';
        var tone    = opts.tone === 'primary' ? 'btn-primary' : 'btn-danger';

        var overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML =
            '<div class="confirm-box" role="alertdialog" aria-modal="true">' +
                '<div class="confirm-icon">' + ICONS[iconKey] + '</div>' +
                '<div class="confirm-msg"></div>' +
                '<div class="confirm-actions">' +
                    '<button type="button" class="btn btn-secondary btn-sm" data-act="no">انصراف</button>' +
                    '<button type="button" class="btn ' + tone + ' btn-sm" data-act="yes"></button>' +
                '</div>' +
            '</div>';
        overlay.querySelector('.confirm-msg').textContent = msg;
        overlay.querySelector('[data-act="yes"]').textContent = label;
        document.body.appendChild(overlay);

        function close() { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); document.removeEventListener('keydown', onKey); }
        function onKey(e) { if (e.key === 'Escape') close(); }
        document.addEventListener('keydown', onKey);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        overlay.querySelector('[data-act="no"]').addEventListener('click', close);
        overlay.querySelector('[data-act="yes"]').addEventListener('click', function () { close(); onYes(); });
        overlay.querySelector('[data-act="yes"]').focus();
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (!el || el.dataset.confirmOk) return;
        e.preventDefault();
        showConfirm(el.getAttribute('data-confirm'), function () {
            el.dataset.confirmOk = '1';
            if (el.tagName === 'A') {
                window.location.href = el.href;
            } else {
                var form = el.closest('form');
                if (form) { if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') el.click(); else form.submit(); }
            }
        }, {
            icon:  el.getAttribute('data-confirm-icon'),
            label: el.getAttribute('data-confirm-label'),
            tone:  el.getAttribute('data-confirm-tone')
        });
    });

    /* توست شناور — جایگزین alert()، هم‌شکل پیغام سبد خرید سمت مشتری
       (includes/header.php، همان کلاس‌های cart-toast در style.css). */
    function showToast(msg, ok) {
        var t = document.createElement('div');
        t.className = 'cart-toast ' + (ok ? 'is-ok' : 'is-err');
        t.setAttribute('role', 'status');
        t.setAttribute('aria-live', 'polite');
        t.innerHTML =
            '<span class="cart-toast-ic">' + ICONS[ok ? 'check' : 'alert'] + '</span>' +
            '<span class="cart-toast-msg"></span>' +
            '<button type="button" class="cart-toast-x" aria-label="بستن پیام">' + ICONS.x + '</button>';
        t.querySelector('.cart-toast-msg').textContent = msg;
        document.body.appendChild(t);
        function hide() {
            t.classList.add('is-hide');
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 300);
        }
        t.querySelector('.cart-toast-x').addEventListener('click', hide);
        setTimeout(hide, 4500);
    }

    window.adminConfirm = showConfirm;
    window.adminToast   = showToast;
})();
</script>
</body></html>