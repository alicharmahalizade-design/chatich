/* Dorian — testimonials carousel (auto-rotate + dots, FA/EN aware). */
window.Dorian = window.Dorian || {};

window.Dorian.TESTIMONIALS = [
  {
    fa: "وارد دوریان که می‌شوی، انگار وقت می‌ایستد. هر طبقه یک دنیای جداست.",
    en: "Step into Dorian and time slows down. Every floor is its own world.",
    who: "آرش ک.",
    whoEn: "Arash K.",
    role: "داماد ۱۴۰۴",
    roleEn: "Groom, 2025",
  },
  {
    fa: "حسِ یک باشگاهِ خصوصیِ اشرافی را دارد، نه یک آرایشگاه. بی‌نظیر بود.",
    en: "It feels like a private gentlemen's club, not a salon. Flawless.",
    who: "بهراد م.",
    whoEn: "Behrad M.",
    role: "مشتری دائمی",
    roleEn: "Member",
  },
  {
    fa: "جزئیات و کیفیت در سطحی است که در ایران ندیده بودم. تجربه‌ای ماندگار.",
    en: "A level of detail and quality I had not seen before. Unforgettable.",
    who: "کاوه ر.",
    whoEn: "Kaveh R.",
    role: "مدیر برند",
    roleEn: "Brand Director",
  },
];

window.Dorian.initTestimonials = function () {
  var root = document.getElementById("tst");
  if (!root) return;
  var quote = root.querySelector(".tst__quote");
  var who = root.querySelector(".tst__who");
  var role = root.querySelector(".tst__role");
  var dotsWrap = root.querySelector(".tst__dots");
  var data = window.Dorian.TESTIMONIALS;
  var i = 0;
  var timer = null;

  data.forEach(function (_, idx) {
    var b = document.createElement("button");
    b.setAttribute("aria-label", "نظر " + (idx + 1));
    b.setAttribute("data-cursor", "explore");
    b.addEventListener("click", function () {
      show(idx);
      restart();
    });
    dotsWrap.appendChild(b);
  });
  var dots = dotsWrap.querySelectorAll("button");

  function show(idx) {
    i = idx;
    var t = data[idx];
    var en = document.documentElement.getAttribute("dir") === "ltr";
    var apply = function () {
      quote.textContent = en ? t.en : t.fa;
      who.textContent = en ? t.whoEn : t.who;
      role.textContent = en ? t.roleEn : t.role;
      if (window.gsap) {
        gsap.fromTo(
          [quote, who, role],
          { opacity: 0, y: 18 },
          { opacity: 1, y: 0, duration: 0.7, ease: "power3.out", stagger: 0.05 }
        );
      }
    };
    if (window.gsap) {
      gsap.to([quote, who, role], {
        opacity: 0,
        y: -14,
        duration: 0.3,
        onComplete: apply,
      });
    } else {
      apply();
    }
    dots.forEach(function (d, k) {
      d.classList.toggle("is-on", k === idx);
    });
  }

  function next() {
    show((i + 1) % data.length);
  }
  function restart() {
    clearInterval(timer);
    timer = setInterval(next, 6000);
  }

  // Re-render current quote when language switches.
  window.addEventListener("dorian:lang", function () {
    show(i);
  });

  show(0);
  restart();
};
