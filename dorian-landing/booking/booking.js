/* Dorian booking — interactive preview (vanilla JS).
   Mirrors the flow the WordPress plugin will ship: services + sub-services,
   provider, Jalali calendar + 30-min slots + recurrence, summary + pay. */
(function () {
  "use strict";

  /* ------------------------------------------------------------------ */
  /* Jalaali date core (jalaali-js, MIT — Behrang Noruzi Niya)          */
  /* ------------------------------------------------------------------ */
  function div(a, b) { return Math.trunc(a / b); }
  function mod(a, b) { return a - Math.trunc(a / b) * b; }
  function jalCal(jy) {
    var breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
      1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0],
      jm, jump = 0, leap, n, i;
    for (i = 1; i < bl; i += 1) {
      jm = breaks[i]; jump = jm - jp;
      if (jy < jm) break;
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4); jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    var leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    var march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;
    return { leap: leap, gy: gy, march: march };
  }
  function g2d(gy, gm, gd) {
    var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) +
      div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
    return d;
  }
  function d2g(jdn) {
    var j = 4 * jdn + 139361631;
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    var i = div(mod(j, 1461), 4) * 5 + 308;
    var gd = div(mod(i, 153), 5) + 1;
    var gm = mod(div(i, 153), 12) + 1;
    var gy = div(j, 1461) - 100100 + div(8 - gm, 6);
    return { gy: gy, gm: gm, gd: gd };
  }
  function j2d(jy, jm, jd) { var r = jalCal(jy); return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1; }
  function d2j(jdn) {
    var gy = d2g(jdn).gy, jy = gy - 621, r = jalCal(jy), jdn1f = g2d(gy, 3, r.march), jd, jm, k;
    k = jdn - jdn1f;
    if (k >= 0) { if (k <= 185) { jm = 1 + div(k, 31); jd = mod(k, 31) + 1; return { jy: jy, jm: jm, jd: jd }; } else { k -= 186; } }
    else { jy -= 1; k += 179; if (r.leap === 1) k += 1; }
    jm = 7 + div(k, 30); jd = mod(k, 30) + 1; return { jy: jy, jm: jm, jd: jd };
  }
  function toJalaali(gy, gm, gd) { return d2j(g2d(gy, gm, gd)); }
  function jMonthLen(jy, jm) { if (jm <= 6) return 31; if (jm <= 11) return 30; return jalCal(jy).leap === 0 ? 30 : 29; }

  var J_MONTHS = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
  var J_WEEK = ["شنبه", "یک‌شنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنج‌شنبه", "جمعه"];

  /* column 0..6 (Saturday-first) for a jalali date */
  function jColumn(jy, jm, jd) { var g = d2g(j2d(jy, jm, jd)); var wd = new Date(g.gy, g.gm - 1, g.gd).getDay(); return (wd + 1) % 7; }
  function jWeekday(jy, jm, jd) { return J_WEEK[jColumn(jy, jm, jd)]; }

  /* ------------------------------------------------------------------ */
  /* helpers                                                            */
  /* ------------------------------------------------------------------ */
  function toFa(s) { return String(s).replace(/[0-9]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹"[d]; }); }
  function money(n) { return toFa(n.toLocaleString("en-US")) + " تومان"; }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }

  /* ------------------------------------------------------------------ */
  /* mock data (the plugin pulls these from WP / WooCommerce)           */
  /* ------------------------------------------------------------------ */
  var SERVICES = [
    {
      id: "hair", name: "مو و ریش", icon: "M5 19l7-7 7 7M8 8l8 8",
      subs: [
        { id: "cut", name: "کوتاهی مو", price: 550000, dur: 30 },
        { id: "beard", name: "اصلاح ریش", price: 400000, dur: 30 },
        { id: "blow", name: "سشوار و حالت", price: 300000, dur: 30 },
        { id: "combo", name: "پکیج مو و ریش", price: 800000, dur: 60 }
      ]
    },
    {
      id: "facial", name: "پوست و فیشیال", icon: "M12 4a8 8 0 100 16 8 8 0 000-16zM9 14c1.5 1 4.5 1 6 0",
      subs: [
        { id: "clean", name: "پاکسازی لایت", price: 2100000, dur: 60 },
        { id: "vip", name: "پاکسازی VIP", price: 2800000, dur: 60 },
        { id: "candle", name: "شمع صورت", price: 300000, dur: 30 }
      ]
    },
    {
      id: "massage", name: "ماساژ", icon: "M4 14c4-6 12-6 16 0M12 7a2 2 0 100-4 2 2 0 000 4z",
      subs: [
        { id: "relax", name: "ماساژ ریلکسی", price: 1350000, dur: 60 },
        { id: "stone", name: "سنگ داغ", price: 1750000, dur: 60 },
        { id: "head", name: "ماساژ سر و صورت", price: 580000, dur: 30 }
      ]
    }
  ];
  var PROVIDERS = [
    { id: "arman", name: "آرمان کاراگاه", role: "Hair Master", photo: "../assets/img/team-01.jpg", services: ["hair"] },
    { id: "ehsan", name: "احسان حسن‌یاری", role: "Hair Artist", photo: "../assets/img/team-02.jpg", services: ["hair"] },
    { id: "hamid", name: "حمید جوهری", role: "Facial Expert", photo: "../assets/img/team-09.jpg", services: ["facial"] },
    { id: "ahmadreza", name: "احمدرضا جلالی", role: "Massage", photo: "../assets/img/team-08.jpg", services: ["massage"] }
  ];
  var HOURS = { start: 10, end: 22, step: 30 }; // 30-minute slots, 10:00–22:00

  /* ------------------------------------------------------------------ */
  /* state                                                              */
  /* ------------------------------------------------------------------ */
  var state = {
    step: 1,
    subs: {},            // subId -> {service, sub}
    provider: null,
    date: null,          // {jy,jm,jd}
    time: null,          // "HH:MM"
    weeks: 0             // recurrence interval (0 = once)
  };
  var today = (function () { var d = new Date(); return toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate()); })();
  var view = { jy: today.jy, jm: today.jm };

  /* ------------------------------------------------------------------ */
  /* totals                                                             */
  /* ------------------------------------------------------------------ */
  function chosenSubs() { return Object.keys(state.subs).map(function (k) { return state.subs[k]; }); }
  function totalPrice() { return chosenSubs().reduce(function (s, x) { return s + x.sub.price; }, 0); }
  function totalDur() { return chosenSubs().reduce(function (s, x) { return s + x.sub.dur; }, 0); }
  function activeServiceIds() { var ids = {}; chosenSubs().forEach(function (x) { ids[x.service.id] = 1; }); return Object.keys(ids); }

  function refreshTotal() {
    document.getElementById("grandTotal").textContent = money(totalPrice());
  }

  /* ------------------------------------------------------------------ */
  /* step 1 — services                                                  */
  /* ------------------------------------------------------------------ */
  function renderServices() {
    var wrap = document.getElementById("svcList");
    wrap.innerHTML = "";
    SERVICES.forEach(function (svc) {
      var card = el("div", "svc");
      var head = el("div", "svc__head",
        '<svg viewBox="0 0 24 24"><path d="' + svc.icon + '"/></svg>' +
        '<span class="svc__name">' + svc.name + "</span>");
      card.appendChild(head);
      var subsWrap = el("div", "svc__subs");
      svc.subs.forEach(function (sub) {
        var key = svc.id + ":" + sub.id;
        var row = el("button", "subchip" + (state.subs[key] ? " is-on" : ""));
        row.type = "button";
        row.innerHTML =
          '<span class="subchip__check" aria-hidden="true"></span>' +
          '<span class="subchip__name">' + sub.name + "</span>" +
          '<span class="subchip__meta">' + toFa(sub.dur) + "´ · " + money(sub.price) + "</span>";
        row.addEventListener("click", function () {
          if (state.subs[key]) { delete state.subs[key]; row.classList.remove("is-on"); }
          else { state.subs[key] = { service: svc, sub: sub }; row.classList.add("is-on"); }
          // dropping every service of the chosen provider invalidates it
          if (state.provider) {
            var ids = activeServiceIds();
            var ok = ids.length && state.provider.services.some(function (s) { return ids.indexOf(s) !== -1; });
            if (!ok) state.provider = null;
          }
          refreshTotal();
        });
        subsWrap.appendChild(row);
      });
      card.appendChild(subsWrap);
      wrap.appendChild(card);
    });
  }

  /* ------------------------------------------------------------------ */
  /* step 2 — providers                                                 */
  /* ------------------------------------------------------------------ */
  function renderProviders() {
    var wrap = document.getElementById("proList");
    wrap.innerHTML = "";
    var ids = activeServiceIds();
    var list = PROVIDERS.filter(function (p) { return ids.length === 0 || p.services.some(function (s) { return ids.indexOf(s) !== -1; }); });
    if (!list.length) list = PROVIDERS;
    list.forEach(function (p) {
      var card = el("button", "pro" + (state.provider && state.provider.id === p.id ? " is-on" : ""));
      card.type = "button";
      card.innerHTML =
        '<span class="pro__photo"><img src="' + p.photo + '" alt="' + p.name + '" loading="lazy"></span>' +
        '<span class="pro__name">' + p.name + "</span>" +
        '<span class="pro__role">' + p.role + "</span>";
      card.addEventListener("click", function () {
        state.provider = p;
        wrap.querySelectorAll(".pro").forEach(function (c) { c.classList.remove("is-on"); });
        card.classList.add("is-on");
      });
      wrap.appendChild(card);
    });
  }

  /* ------------------------------------------------------------------ */
  /* step 3 — calendar / slots / recurrence                             */
  /* ------------------------------------------------------------------ */
  function renderCalendar() {
    document.getElementById("calTitle").textContent = J_MONTHS[view.jm - 1] + " " + toFa(view.jy);
    var grid = document.getElementById("calGrid");
    grid.innerHTML = "";
    var lead = jColumn(view.jy, view.jm, 1);
    for (var i = 0; i < lead; i++) grid.appendChild(el("span", "cal__cell is-empty"));
    var len = jMonthLen(view.jy, view.jm);
    var todJdn = j2d(today.jy, today.jm, today.jd);
    for (var d = 1; d <= len; d++) {
      var cell = el("button", "cal__cell");
      cell.type = "button";
      cell.textContent = toFa(d);
      var jdn = j2d(view.jy, view.jm, d);
      var col = (jColumn(view.jy, view.jm, d));
      if (col === 6) cell.classList.add("is-fri"); // جمعه تعطیل
      if (jdn < todJdn || col === 6) { cell.classList.add("is-disabled"); cell.disabled = true; }
      if (jdn === todJdn) cell.classList.add("is-today");
      if (state.date && state.date.jy === view.jy && state.date.jm === view.jm && state.date.jd === d) cell.classList.add("is-sel");
      (function (day) {
        cell.addEventListener("click", function () {
          state.date = { jy: view.jy, jm: view.jm, jd: day }; state.time = null;
          grid.querySelectorAll(".cal__cell").forEach(function (c) { c.classList.remove("is-sel"); });
          cell.classList.add("is-sel");
          renderSlots(); renderRecurNote();
        });
      })(d);
      grid.appendChild(cell);
    }
  }

  function renderSlots() {
    var box = document.getElementById("slots");
    if (!state.date) { box.innerHTML = '<p class="slots__empty">ابتدا یک روز را انتخاب کنید.</p>'; return; }
    box.innerHTML = "";
    // deterministic pseudo-taken slots for realism in the preview
    var seed = (state.date.jy + state.date.jm * 31 + state.date.jd) % 7;
    var n = 0;
    for (var h = HOURS.start; h < HOURS.end; h++) {
      for (var m = 0; m < 60; m += HOURS.step) {
        var label = (h < 10 ? "0" + h : h) + ":" + (m === 0 ? "00" : m);
        var taken = ((n * 3 + seed) % 5 === 0);
        var b = el("button", "slot" + (taken ? " is-taken" : "") + (state.time === label ? " is-on" : ""));
        b.type = "button"; b.textContent = toFa(label); b.disabled = taken;
        (function (lab, btn) {
          btn.addEventListener("click", function () {
            state.time = lab;
            box.querySelectorAll(".slot").forEach(function (s) { s.classList.remove("is-on"); });
            btn.classList.add("is-on"); renderRecurNote();
          });
        })(label, b);
        box.appendChild(b); n++;
      }
    }
  }

  function renderRecurNote() {
    var note = document.getElementById("recurNote");
    if (!state.weeks || !state.date || !state.time) { note.textContent = ""; return; }
    var wd = jWeekday(state.date.jy, state.date.jm, state.date.jd);
    var every = state.weeks === 1 ? "هر هفته" : "هر " + toFa(state.weeks) + " هفته";
    note.textContent = "این نوبت " + every + " روز " + wd + " ساعت " + toFa(state.time) + " برای شما ثابت می‌شود.";
  }

  function wireRecur() {
    document.getElementById("recur").addEventListener("click", function (e) {
      var b = e.target.closest(".recur__opt"); if (!b) return;
      state.weeks = parseInt(b.dataset.weeks, 10);
      this.querySelectorAll(".recur__opt").forEach(function (o) { o.classList.remove("is-active"); });
      b.classList.add("is-active"); renderRecurNote();
    });
    document.getElementById("calPrev").addEventListener("click", function () { stepMonth(-1); });
    document.getElementById("calNext").addEventListener("click", function () { stepMonth(1); });
  }
  function stepMonth(dir) {
    view.jm += dir;
    if (view.jm < 1) { view.jm = 12; view.jy--; }
    if (view.jm > 12) { view.jm = 1; view.jy++; }
    renderCalendar();
  }

  /* ------------------------------------------------------------------ */
  /* step 4 — summary                                                   */
  /* ------------------------------------------------------------------ */
  function renderSummary() {
    var box = document.getElementById("summary");
    var rows = "";
    chosenSubs().forEach(function (x) {
      rows += '<div class="sumrow"><span>' + x.service.name + " · " + x.sub.name + "</span><b>" + money(x.sub.price) + "</b></div>";
    });
    var dateStr = state.date ? (jWeekday(state.date.jy, state.date.jm, state.date.jd) + " " + toFa(state.date.jd) + " " + J_MONTHS[state.date.jm - 1] + " " + toFa(state.date.jy)) : "—";
    var recur = state.weeks ? (state.weeks === 1 ? "هر هفته" : "هر " + toFa(state.weeks) + " هفته") : "یک‌بار";
    var proHtml = state.provider
      ? '<img src="' + state.provider.photo + '" alt=""><div><b>' + state.provider.name + "</b><span>" + state.provider.role + "</span></div>"
      : "";
    box.innerHTML =
      '<div class="sumcard">' +
      '<div class="sumcard__pro">' + proHtml + "</div>" +
      rows +
      '<div class="sumrow sumrow--meta"><span>زمان</span><b>' + dateStr + " · " + toFa(state.time || "—") + "</b></div>" +
      '<div class="sumrow sumrow--meta"><span>تکرار</span><b>' + recur + "</b></div>" +
      '<div class="sumrow sumrow--meta"><span>مدت کل</span><b>' + toFa(totalDur()) + " دقیقه</b></div>" +
      '<div class="sumrow sumrow--total"><span>جمع کل</span><b>' + money(totalPrice()) + "</b></div>" +
      "</div>";
  }

  /* ------------------------------------------------------------------ */
  /* step navigation                                                    */
  /* ------------------------------------------------------------------ */
  function gotoStep(n) {
    state.step = n;
    document.querySelectorAll(".bk-step").forEach(function (s) { s.classList.toggle("is-active", +s.dataset.step === n); });
    document.querySelectorAll(".bk-steps__item").forEach(function (s) {
      var i = +s.dataset.stepdot;
      s.classList.toggle("is-active", i === n);
      s.classList.toggle("is-done", i < n);
    });
    document.getElementById("btnBack").hidden = n === 1;
    document.getElementById("btnNext").textContent = n === 4 ? "پرداخت و ثبت نوبت" : "ادامه";
    if (n === 2) renderProviders();
    if (n === 3) { renderCalendar(); renderSlots(); renderRecurNote(); }
    if (n === 4) renderSummary();
    document.getElementById("bk").scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function validateStep() {
    if (state.step === 1 && chosenSubs().length === 0) { toast("حداقل یک خدمت را انتخاب کنید."); return false; }
    if (state.step === 2 && !state.provider) { toast("یک متخصص را انتخاب کنید."); return false; }
    if (state.step === 3 && (!state.date || !state.time)) { toast("روز و ساعت نوبت را انتخاب کنید."); return false; }
    if (state.step === 4) {
      var nm = document.getElementById("custName").value.trim();
      var ph = document.getElementById("custPhone").value.trim();
      if (!nm || !/^09\d{9}$/.test(ph)) { toast("نام و شمارهٔ موبایل معتبر وارد کنید."); return false; }
    }
    return true;
  }

  var toastT;
  function toast(msg) {
    var t = document.getElementById("bkToast");
    if (!t) { t = el("div", "bk-toast"); t.id = "bkToast"; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add("is-show");
    clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove("is-show"); }, 2600);
  }

  function finish() {
    // in the plugin: create the booking, push to WooCommerce checkout, queue FarazSMS.
    var done = document.getElementById("bkDone");
    document.getElementById("doneMsg").textContent =
      "پیامک تأیید برای شما و متخصص ارسال می‌شود؛ یادآوری هم پیش از نوبت ارسال خواهد شد.";
    done.hidden = false;
  }

  function reset() {
    state.subs = {}; state.provider = null; state.date = null; state.time = null; state.weeks = 0;
    document.getElementById("custName").value = ""; document.getElementById("custPhone").value = "";
    document.querySelectorAll(".recur__opt").forEach(function (o, i) { o.classList.toggle("is-active", i === 0); });
    document.getElementById("bkDone").hidden = true;
    renderServices(); refreshTotal(); gotoStep(1);
  }

  /* ------------------------------------------------------------------ */
  /* boot                                                               */
  /* ------------------------------------------------------------------ */
  function boot() {
    renderServices();
    wireRecur();
    refreshTotal();
    document.getElementById("btnNext").addEventListener("click", function () {
      if (!validateStep()) return;
      if (state.step < 4) gotoStep(state.step + 1); else finish();
    });
    document.getElementById("btnBack").addEventListener("click", function () { if (state.step > 1) gotoStep(state.step - 1); });
    document.getElementById("btnReset").addEventListener("click", reset);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();
})();
