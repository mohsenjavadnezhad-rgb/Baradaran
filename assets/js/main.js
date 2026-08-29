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
       خودِ shop.php بماند. قبلاً به‌اشتباه به index.php می‌رفت — از قبل از
       جداشدنِ فروشگاه از صفحهٔ اصلی مانده بود — و چون index.php برندها را
       نمی‌خواند، کلیک روی هر برند عملاً به صفحهٔ اصلی می‌پرید. */
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
                var fromRect = clickedTag.getBoundingClientRect();

                /* اصلِ برچسب و کپیِ ghost باید در همان تیکِ سنکرون جای‌عوض کنند —
                   وگرنه یک فریم (~۱۶ms) اصل هنوز کاملاً دیده می‌شود و بعد
                   ناگهان مخفی می‌شود، که «یک کوچولو قبل از محو شدن نشون دادن»
                   دیده می‌شد. پس ghost همین‌جا، هم‌زمان با مخفی‌کردنِ اصل،
                   ساخته و در دقیقاً همان مختصات کاشته می‌شود؛ فقط مختصاتِ
                   مقصد (که به باز شدنِ کادر بستگی دارد) یک فریم بعد خوانده
                   می‌شود. */
                var ghost = clickedTag.cloneNode(true);
                ghost.style.position = 'fixed';
                ghost.style.zIndex = '999';
                ghost.style.margin = '0';
                ghost.style.left = fromRect.left + 'px';
                ghost.style.top = fromRect.top + 'px';
                ghost.style.width = fromRect.width + 'px';
                ghost.style.height = fromRect.height + 'px';
                ghost.style.pointerEvents = 'none';
                document.body.appendChild(ghost);
                clickedTag.style.visibility = 'hidden';

                /* اول خودِ جایگاهِ مقصد را واقعاً نمایان می‌کنیم: تگِ ثابتِ کنار
                   دکمهٔ «همه برندها» (که بعد از reload هم دقیقاً همین‌جا و با
                   همین متن می‌ماند — [[batch9-fixes]]) را با نامِ برندِ کلیک‌شده
                   پر می‌کنیم و کادرِ مدل‌ها را باز می‌کنیم. قبلاً پرواز به سمتِ
                   #backBtn وقتی می‌رفت که .models-reveal هنوز max-height:0 و
                   جمع‌شده بود؛ مختصاتش درست خوانده می‌شد ولی چیزی آنجا دیده
                   نمی‌شد، پس انگار برچسب به یک نقطهٔ خالی می‌رفت. */
                if (modelsTitle) {
                    var faSpan = clickedTag.querySelector('.brand-fa');
                    modelsTitle.textContent = faSpan ? faSpan.textContent : clickedTag.textContent;
                }
                if (modelsReveal) modelsReveal.classList.add('is-open');

                var flyTarget = modelsTitle || backBtn;
                if (flyTarget) {
                    /* یک فریم صبر می‌کنیم تا باز شدنِ کادر روی چیدمان اثر بگذارد،
                       بعد مختصاتِ مقصد را می‌خوانیم — وگرنه هنوز جای جمع‌شدهٔ
                       قبلی را می‌خواندیم. */
                    requestAnimationFrame(function () {
                        var toRect = flyTarget.getBoundingClientRect();
                        var dx = toRect.left - fromRect.left;
                        var dy = toRect.top - fromRect.top;

                        requestAnimationFrame(function () {
                            ghost.style.transition = 'transform 0.7s cubic-bezier(0.65, 0, 0.35, 1), opacity 0.7s ease';
                            ghost.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
                        });
                    });
                }

                clickedTag.classList.add('is-sticky');
                wrap.classList.add('is-faded');

                setTimeout(function () {
                    window.location.href = url;
                }, 700);
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