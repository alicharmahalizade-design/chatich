# DORIAN — Gentlemen's Studio

صفحه‌ی فرود سینمایی و اشرافی برای **دوریان**، مجموعه‌ی لاکچری دامادسرا و
آرایشگاه تخصصی مردانه در سه طبقه. کاربر مثل ورود به یک ساختمان VIP، طبقه‌به‌طبقه
تجربه را کشف می‌کند: اسکرول = حرکت آسانسور بین طبقات.

> A cinematic, RTL (Persian) luxury landing experience. Scroll = an elevator
> ascending through a three-floor building.

## ساختار تجربه (Section map)

| #  | سکشن | جزئیات |
|----|------|--------|
| 00 | **Preloader** | مهر موم آبی «D» که stamp می‌شود + سوییپ طلایی + شمارنده (≤ ۲ ثانیه) |
| 01 | **Hero** | قاب بیضی طلایی (draw-on) + پرتره، شعار اسکریپت، لوگو، مهر موم، پارالاکس |
| 02 | **Manifesto / 360°** | «خدمات ۳۶۰ درجه در سه طبقه» + برش معماری ساختمان |
| 03–05 | **Elevator** (pinned) | آسانسور بین Underground / First / Second؛ طبقهٔ فعال روشن، بقیه کم‌نور، شمارندهٔ طبقه + ding |
| 06 | **Characters** | تیم در قالب کارت‌های اشرافی (Royal/Noble/Duke/Gentle) با tilt و flip |
| 07 | **Gift Cards** | چهار سطح ۱/۲/۵/۱۰ میلیون تومان با tilt و درخشش طلایی |
| 08 | **Reservation** | فرم لاکچری با گلوی طلایی، اعتبارسنجی هوشمند و انیمیشن موفقیت |
| 09 | **Footer** | لوگو، شبکه‌های اجتماعی، مهر طلایی «New Experiences» |

## ویژگی‌های سراسری

- **اسکرول نرم:** Lenis، هماهنگ با GSAP ScrollTrigger.
- **کرسور اختصاصی:** نقطه + حلقهٔ دنبال‌کننده با حالت‌های Explore/Enter/Scroll/Open/View.
- **موشن سینمایی:** منحنی `cubic-bezier(0.22, 1, 0.36, 1)`، draw-on SVG، reveal خطی.
- **صدا (اختیاری):** ding آسانسور، tick و whoosh — کاملاً قابل خاموش‌کردن.
- **اتمسفر:** grain، vignette، light rays طلایی.
- **RTL کامل** و احترام به `prefers-reduced-motion`.

## پالت برند

| نقش | کد |
|-----|-----|
| کرمی کاغذی | `#ECDEC9` |
| آبی برند | `#0066B3` |
| سرمه‌ای عمیق | `#0A1A2F` |
| طلایی | `#C9A227` → `#E8C766` |
| مشکی پارچه‌ای | `#111315` |

## تکنولوژی

سایت **استاتیک، بدون فریم‌ورک سنگین** است (طبق توصیهٔ PRD برای انتقال آسان به
Page Template وردپرس). کتابخانه‌ها در `assets/vendor/` به‌صورت محلی قرار دارند:

- GSAP + ScrollTrigger + DrawSVGPlugin
- Lenis smooth scroll
- فونت‌ها: Cinzel (نمایشی)، Pinyon Script (شعار)، Vazirmatn (فارسی) از Google Fonts

## اجرا / پیش‌نمایش

چون استاتیک است، فقط کافی است با یک سرور ساده اجرا شود (به‌خاطر مسیرهای نسبی):

```bash
cd dorian-landing
python3 -m http.server 8000
# سپس باز کنید: http://localhost:8000
```

یا با هر سرور استاتیک دیگری (`npx serve`, افزونهٔ Live Server و …).

## ساختار فایل

```
dorian-landing/
├─ index.html
└─ assets/
   ├─ css/main.css
   ├─ js/        # sound, cursor, preloader, hero, elevator,
   │             # characters, giftcards, reserve, main
   ├─ vendor/    # gsap, ScrollTrigger, DrawSVGPlugin, lenis
   ├─ img/  svg/  fonts/
```

## جای‌گذاری دارایی‌های واقعی (Placeholders)

این نسخه با جای‌گزین‌های باکیفیت CSS/SVG ساخته شده تا قبل از دریافت فایل‌های
نهایی قابل مشاهده باشد. برای نسخهٔ نهایی این‌ها را جایگزین کنید:

- **پرترهٔ دوریان** داخل `.hero__portrait` (الان silhouette با CSS است).
- **پرتره‌های تیم** در کارت‌های `.char-card .portrait`.
- **لوگوی SVG**، **مهر موم** و **مهر طلایی** (الان SVG درون‌خطی‌اند).
- **رندر طبقات** و **برش ساختمان** با کیفیت بالا.
- متن‌های نهایی، اطلاعات تماس واقعی و لینک سیستم رزرو.

## مسیر وردپرس (فاز بعد)

این نسخهٔ استاتیک عمداً ساده نگه داشته شده تا به‌سادگی به یک **Custom Page
Template** تبدیل شود: انتقال `index.html` به `template-dorian.php` و لود اسکریپت/
استایل‌ها با `wp_enqueue_*`.
