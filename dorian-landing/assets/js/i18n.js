/* Dorian — lightweight FA/EN toggle. Elements carry data-en (English);
   their original Persian text is captured as the Farsi value. */
window.Dorian = window.Dorian || {};

window.Dorian.initI18n = function () {
  var btn = document.getElementById("langToggle");
  var nodes = document.querySelectorAll("[data-en]");
  var lang = "fa";

  nodes.forEach(function (el) {
    el.setAttribute("data-fa", el.textContent);
  });

  function apply() {
    var en = lang === "en";
    nodes.forEach(function (el) {
      el.textContent = en ? el.getAttribute("data-en") : el.getAttribute("data-fa");
    });
    document.documentElement.setAttribute("dir", en ? "ltr" : "rtl");
    document.documentElement.setAttribute("lang", en ? "en" : "fa");
    if (btn) btn.textContent = en ? "FA" : "EN";
    window.dispatchEvent(new CustomEvent("dorian:lang", { detail: { lang: lang } }));
    if (window.ScrollTrigger) window.ScrollTrigger.refresh();
  }

  if (btn) {
    btn.addEventListener("click", function () {
      lang = lang === "fa" ? "en" : "fa";
      if (window.DorianSound) window.DorianSound.play("tick");
      apply();
    });
  }
};
