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

/* کادر تأیید حذف — جایگزین پاپ‌آپ بومی confirm() (خواستهٔ کاربر: «یک
   پیام روی خود صفحه با یک کادر زیبا بیاد، دیگه مثل پاپ‌آپ نشون نده»).
   هر <a>/<button> با ویژگی data-confirm="متن" را می‌گیرد: کلیک اول
   پیش‌فرض را می‌گیرد، کادر را نشان می‌دهد؛ تأیید یعنی همان اقدام اصلی
   (رفتن به href یا submit فرم) دوباره اجرا شود — این‌بار بدون دخالت،
   چون data-confirm-ok روی خود عنصر گذاشته می‌شود. */
(function () {
    var TRASH_ICON = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2.5 6h19"/><path d="M8.5 6V3.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1V6"/><path d="m5 6 1.2 15.1a1.5 1.5 0 0 0 1.5 1.4h8.6a1.5 1.5 0 0 0 1.5-1.4L19 6"/><path d="M10 11v6.5M14 11v6.5"/></svg>';

    function showConfirm(msg, onYes) {
        var overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML =
            '<div class="confirm-box" role="alertdialog" aria-modal="true">' +
                '<div class="confirm-icon">' + TRASH_ICON + '</div>' +
                '<div class="confirm-msg"></div>' +
                '<div class="confirm-actions">' +
                    '<button type="button" class="btn btn-secondary btn-sm" data-act="no">انصراف</button>' +
                    '<button type="button" class="btn btn-danger btn-sm" data-act="yes">حذف شود</button>' +
                '</div>' +
            '</div>';
        overlay.querySelector('.confirm-msg').textContent = msg;
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
        });
    });
})();
</script>
</body></html>