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
</script>
</body></html>