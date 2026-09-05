document.addEventListener('DOMContentLoaded', function () {
    var flashes = document.querySelectorAll('.flash');
    flashes.forEach(function (f) {
        setTimeout(function () {
            f.style.transition = 'opacity 0.5s';
            f.style.opacity = '0';
            setTimeout(function () { if (f.parentNode) f.parentNode.removeChild(f); }, 500);
        }, 4000);
    });

    /* Brand cascade fade on click — این کادرها فقط توی shop.php هستند (نه
       index.php/خانه که فقط بنر است، طبق [[banners-feature]])، پس مقصد باید
       خود shop.php بماند. قبلا به‌اشتباه به index.php می‌رفت — از قبل از
       جداشدن فروشگاه از صفحهٔ اصلی مانده بود — و چون index.php برندها را
       نمی‌خواند، کلیک روی هر برند عملا به صفحهٔ اصلی می‌پرید. */
    var wrap = document.getElementById('brandTagsWrap');
    var backBtn = document.getElementById('backBtn');
    var modelsReveal = document.getElementById('modelsReveal');
    var modelsTitle = document.getElementById('modelsTitle');

    if (wrap && !wrap.classList.contains('is-faded')) {
        wrap.querySelectorAll('.brand-tag[data-id]').forEach(function (tag) {
            tag.addEventListener('click', function (e) {
                e.preventDefault();
                var url = 'shop.php?brand=' + this.getAttribute('data-id');
                var clickedTag = this;

                /* خواستهٔ کاربر (۲۰۲۶-۰۹-۰۵): «اون کلیده حرکت می‌کنه و جابجا
                   می‌شه، برش دار، مثل سایه محو بشه» — پرواز فیزیکی یک کپی
                   (ghost) از جای کلیک‌شده تا کنار «همه برندها» حذف شد؛
                   کلیک‌شده هم درست مثل بقیهٔ برچسب‌ها (پایین، .is-faded)
                   فقط سرجایش محو می‌شود، هیچ‌جا جابه‌جا نمی‌شود. */
                if (modelsTitle) {
                    var faSpan = clickedTag.querySelector('.brand-fa');
                    modelsTitle.textContent = faSpan ? faSpan.textContent : clickedTag.textContent;
                }
                if (modelsReveal) modelsReveal.classList.add('is-open');

                clickedTag.classList.add('is-sticky');
                wrap.classList.add('is-faded');

                setTimeout(function () {
                    window.location.href = url;
                }, 450);
            });
        });
    }

    if (backBtn) {
        backBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (wrap) {
                wrap.classList.remove('is-faded');
                var sticky = wrap.querySelector('.is-sticky');
                if (sticky) sticky.classList.remove('is-sticky', 'active');
            }
            var rev = document.getElementById('modelsReveal');
            if (rev) rev.classList.remove('is-open');
            history.pushState(null, '', 'shop.php');
        });
    }
});