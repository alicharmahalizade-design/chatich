/* Dorian booking — provider-first flow (one specialist, their own services + calendar). */
(function () {
  "use strict";
  var DATA = window.DorianBooking || { providers: [], settings: {}, ajaxUrl: "", nonce: "" };
  if (!document.getElementById("bk")) return;

  /* ---------- Jalaali core (jalaali-js, MIT) ---------- */
  function div(a, b) { return Math.trunc(a / b); }
  function mod(a, b) { return a - Math.trunc(a / b) * b; }
  function jalCal(jy) {
    var breaks = [-61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178];
    var bl = breaks.length, gy = jy + 621, leapJ = -14, jp = breaks[0], jm, jump = 0, leap, n, i;
    for (i = 1; i < bl; i += 1) { jm = breaks[i]; jump = jm - jp; if (jy < jm) break; leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4); jp = jm; }
    n = jy - jp; leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
    var leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150; var march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    leap = mod(mod(n + 1, 33) - 1, 4); if (leap === -1) leap = 4;
    return { leap: leap, gy: gy, march: march };
  }
  function g2d(gy, gm, gd) { var d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408; d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752; return d; }
  function d2g(jdn) { var j = 4 * jdn + 139361631; j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908; var i = div(mod(j, 1461), 4) * 5 + 308; var gd = div(mod(i, 153), 5) + 1; var gm = mod(div(i, 153), 12) + 1; var gy = div(j, 1461) - 100100 + div(8 - gm, 6); return { gy: gy, gm: gm, gd: gd }; }
  function j2d(jy, jm, jd) { var r = jalCal(jy); return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1; }
  function d2j(jdn) { var gy = d2g(jdn).gy, jy = gy - 621, r = jalCal(jy), jdn1f = g2d(gy, 3, r.march), jd, jm, k; k = jdn - jdn1f; if (k >= 0) { if (k <= 185) { jm = 1 + div(k, 31); jd = mod(k, 31) + 1; return { jy: jy, jm: jm, jd: jd }; } else { k -= 186; } } else { jy -= 1; k += 179; if (r.leap === 1) k += 1; } jm = 7 + div(k, 30); jd = mod(k, 30) + 1; return { jy: jy, jm: jm, jd: jd }; }
  function toJalaali(gy, gm, gd) { return d2j(g2d(gy, gm, gd)); }
  function jMonthLen(jy, jm) { if (jm <= 6) return 31; if (jm <= 11) return 30; return jalCal(jy).leap === 0 ? 30 : 29; }
  var J_MONTHS = ["فروردین","اردیبهشت","خرداد","تیر","مرداد","شهریور","مهر","آبان","آذر","دی","بهمن","اسفند"];
  var J_WEEK = ["شنبه","یک‌شنبه","دوشنبه","سه‌شنبه","چهارشنبه","پنج‌شنبه","جمعه"];
  function jColumn(jy, jm, jd) { var g = d2g(j2d(jy, jm, jd)); return (new Date(g.gy, g.gm - 1, g.gd).getDay() + 1) % 7; }
  function jWeekday(jy, jm, jd) { return J_WEEK[jColumn(jy, jm, jd)]; }
  function greg(jy, jm, jd) { var g = d2g(j2d(jy, jm, jd)); function p(n) { return (n < 10 ? "0" : "") + n; } return g.gy + "-" + p(g.gm) + "-" + p(g.gd); }

  /* ---------- helpers ---------- */
  function toFa(s) { return String(s).replace(/[0-9]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹"[d]; }); }
  var CUR = DATA.settings.currency || "تومان";
  function money(n) { return toFa(Number(n || 0).toLocaleString("en-US")) + " " + CUR; }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  var DEPOSIT_RATE = (DATA.settings.depositRate != null ? DATA.settings.depositRate : 30) / 100;
  var PAY_AMOUNT = DATA.settings.payAmount || "full"; // "full" | "deposit"
  var PROVIDERS = DATA.providers || [];

  function api(action, extra) {
    var body = new URLSearchParams();
    body.append("action", "dorian_" + action);
    body.append("nonce", DATA.nonce);
    if (state.provider) body.append("providers[]", state.provider.id);
    subIdList().forEach(function (id) { body.append("subs[]", id); });
    if (extra) Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
    return fetch(DATA.ajaxUrl, { method: "POST", credentials: "same-origin", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body.toString() })
      .then(function (r) { return r.json(); }).catch(function () { return { success: false }; });
  }

  /* ---------- state ---------- */
  var state = { step: 1, provider: null, subs: {}, date: null, time: null, weeks: 0 };
  var today = (function () { var d = new Date(); return toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate()); })();
  var view = { jy: today.jy, jm: today.jm };

  function chosenSubs() { return Object.keys(state.subs).map(function (k) { return state.subs[k]; }); }
  function totalPrice() { return chosenSubs().reduce(function (s, x) { return s + x.price; }, 0); }
  function totalDur() { return chosenSubs().reduce(function (s, x) { return s + x.dur; }, 0); }
  function depositPrice() { return Math.round(totalPrice() * DEPOSIT_RATE / 1000) * 1000; }
  function payablePrice() { return PAY_AMOUNT === "deposit" ? depositPrice() : totalPrice(); }
  function creditCode() { var e = document.getElementById("custCode"); return e ? e.value.trim() : ""; }
  function subIdList() { return Object.keys(state.subs); }
  function refreshTotal() { document.getElementById("grandTotal").textContent = money(totalPrice()); }

  /* ---------- step 1 · provider (single) ---------- */
  function renderProviders() {
    var wrap = document.getElementById("proList"); wrap.innerHTML = "";
    if (!PROVIDERS.length) { wrap.innerHTML = '<p class="slots__empty">هنوز متخصصی ثبت نشده است.</p>'; return; }
    var grid = el("div", "pro-grid");
    PROVIDERS.forEach(function (p) {
      var card = el("button", "pro" + (state.provider && state.provider.id === p.id ? " is-on" : "")); card.type = "button";
      card.innerHTML = '<span class="pro__photo"><img src="' + p.photo + '" alt="' + p.name + '"></span><span class="pro__name">' + p.name + '</span><span class="pro__role">' + p.role + "</span>";
      card.addEventListener("click", function () {
        if (!state.provider || state.provider.id !== p.id) { state.provider = p; state.subs = {}; state.date = null; state.time = null; refreshTotal(); }
        grid.querySelectorAll(".pro").forEach(function (c) { c.classList.remove("is-on"); });
        card.classList.add("is-on");
      });
      grid.appendChild(card);
    });
    wrap.appendChild(grid);
  }

  /* ---------- step 2 · that provider's services ---------- */
  function renderServices() {
    var wrap = document.getElementById("svcList"); wrap.innerHTML = "";
    if (!state.provider) { wrap.innerHTML = '<p class="slots__empty">ابتدا متخصص را انتخاب کنید.</p>'; return; }
    (state.provider.groups || []).forEach(function (svc) {
      var card = el("div", "svc");
      card.appendChild(el("div", "svc__head", '<span class="svc__name">' + svc.name + "</span>"));
      var subsWrap = el("div", "svc__subs");
      svc.subs.forEach(function (sub) {
        var key = String(sub.id);
        var row = el("button", "subchip" + (state.subs[key] ? " is-on" : "")); row.type = "button";
        row.innerHTML = '<span class="subchip__check"></span><span class="subchip__name">' + sub.name + '</span><span class="subchip__meta">' + toFa(sub.dur) + "´ · " + money(sub.price) + "</span>";
        row.addEventListener("click", function () {
          if (state.subs[key]) { delete state.subs[key]; row.classList.remove("is-on"); }
          else { state.subs[key] = { id: key, name: sub.name, price: sub.price, dur: sub.dur }; row.classList.add("is-on"); }
          state.time = null; refreshTotal();
        });
        subsWrap.appendChild(row);
      });
      card.appendChild(subsWrap); wrap.appendChild(card);
    });
  }

  /* ---------- step 3 · calendar / slots ---------- */
  function renderCalendar() {
    document.getElementById("calTitle").textContent = J_MONTHS[view.jm - 1] + " " + toFa(view.jy);
    var grid = document.getElementById("calGrid"); grid.innerHTML = "";
    var lead = jColumn(view.jy, view.jm, 1);
    for (var i = 0; i < lead; i++) grid.appendChild(el("span", "cal__cell is-empty"));
    var len = jMonthLen(view.jy, view.jm); var todJdn = j2d(today.jy, today.jm, today.jd);
    for (var d = 1; d <= len; d++) {
      var cell = el("button", "cal__cell"); cell.type = "button"; cell.textContent = toFa(d);
      var jdn = j2d(view.jy, view.jm, d);
      if (jdn < todJdn) { cell.classList.add("is-disabled"); cell.disabled = true; }
      if (jdn === todJdn) cell.classList.add("is-today");
      if (state.date && state.date.jy === view.jy && state.date.jm === view.jm && state.date.jd === d) cell.classList.add("is-sel");
      (function (day, c) { c.addEventListener("click", function () {
        state.date = { jy: view.jy, jm: view.jm, jd: day }; state.time = null;
        grid.querySelectorAll(".cal__cell").forEach(function (x) { x.classList.remove("is-sel"); });
        c.classList.add("is-sel"); fetchSlots(); renderRecurNote();
      }); })(d, cell);
      grid.appendChild(cell);
    }
  }
  function fetchSlots(preselect) {
    var box = document.getElementById("slots");
    if (!state.date) { box.innerHTML = '<p class="slots__empty">ابتدا یک روز را انتخاب کنید.</p>'; return; }
    if (!chosenSubs().length) { box.innerHTML = '<p class="slots__empty">ابتدا خدمت را انتخاب کنید.</p>'; return; }
    box.innerHTML = '<p class="slots__empty">در حال بررسی ظرفیت…</p>';
    api("slots", { date: greg(state.date.jy, state.date.jm, state.date.jd) }).then(function (res) {
      if (!res || !res.success) { box.innerHTML = '<p class="slots__empty">خطا در دریافت ساعت‌ها.</p>'; return; }
      box.innerHTML = "";
      if (!res.data.length) { box.innerHTML = '<p class="slots__empty">این روز برای این متخصص ساعتی ندارد (تعطیل یا خارج از ساعت کاری).</p>'; return; }
      res.data.forEach(function (s) {
        var b = el("button", "slot" + (!s.free ? " is-taken" : "") + ((preselect === s.t || state.time === s.t) ? " is-on" : ""));
        b.type = "button"; b.textContent = toFa(s.t); b.disabled = !s.free;
        if (preselect === s.t) state.time = s.t;
        b.addEventListener("click", function () { state.time = s.t; box.querySelectorAll(".slot").forEach(function (x) { x.classList.remove("is-on"); }); b.classList.add("is-on"); renderRecurNote(); });
        box.appendChild(b);
      });
    });
  }
  function firstAvailable() {
    if (!state.provider || !chosenSubs().length) { toast("ابتدا متخصص و خدمت را انتخاب کنید."); return; }
    api("firstfree", {}).then(function (res) {
      if (!res || !res.success) { toast("وقت خالی یافت نشد."); return; }
      var p = res.data.date.split("-"); var g = toJalaali(+p[0], +p[1], +p[2]);
      view.jy = g.jy; view.jm = g.jm; state.date = { jy: g.jy, jm: g.jm, jd: g.jd }; state.time = res.data.time;
      renderCalendar(); fetchSlots(res.data.time); renderRecurNote();
      toast("اولین وقت: " + jWeekday(g.jy, g.jm, g.jd) + " " + toFa(g.jd) + " " + J_MONTHS[g.jm - 1] + " · " + toFa(res.data.time));
    });
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
  function stepMonth(dir) { view.jm += dir; if (view.jm < 1) { view.jm = 12; view.jy--; } if (view.jm > 12) { view.jm = 1; view.jy++; } renderCalendar(); }

  /* ---------- step 4 · summary ---------- */
  function renderSummary() {
    var box = document.getElementById("summary"); var rows = "";
    if (state.provider) rows += '<div class="sumrow sumrow--svc"><span>متخصص</span><b>' + state.provider.name + "</b></div>";
    chosenSubs().forEach(function (x) { rows += '<div class="sumrow sumrow--sub"><span>' + x.name + "</span><b>" + money(x.price) + "</b></div>"; });
    var dateStr = state.date ? (jWeekday(state.date.jy, state.date.jm, state.date.jd) + " " + toFa(state.date.jd) + " " + J_MONTHS[state.date.jm - 1] + " " + toFa(state.date.jy)) : "—";
    var recur = state.weeks ? (state.weeks === 1 ? "هر هفته" : "هر " + toFa(state.weeks) + " هفته") : "یک‌بار";
    var payRows;
    if (PAY_AMOUNT === "deposit") {
      var dep = depositPrice(), rest = totalPrice() - dep;
      payRows =
        '<div class="sumrow sumrow--deposit"><span>بیعانه (' + toFa(Math.round(DEPOSIT_RATE * 100)) + "٪) — اکنون</span><b>" + money(dep) + "</b></div>" +
        '<div class="sumrow sumrow--rest"><span>باقی‌مانده هنگام حضور</span><b>' + money(rest) + "</b></div>";
    } else {
      payRows = '<div class="sumrow sumrow--deposit"><span>مبلغ قابل پرداخت (کامل)</span><b>' + money(totalPrice()) + "</b></div>";
    }
    box.innerHTML = '<div class="sumcard">' + rows +
      '<div class="sumrow sumrow--meta"><span>زمان</span><b>' + dateStr + " · " + toFa(state.time || "—") + "</b></div>" +
      '<div class="sumrow sumrow--meta"><span>تکرار</span><b>' + recur + "</b></div>" +
      '<div class="sumrow sumrow--meta"><span>مدت کل</span><b>' + toFa(totalDur()) + " دقیقه</b></div>" +
      '<div class="sumrow sumrow--total"><span>جمع کل</span><b>' + money(totalPrice()) + "</b></div>" +
      payRows + "</div>";
  }

  /* ---------- navigation ---------- */
  function gotoStep(n) {
    state.step = n;
    document.querySelectorAll(".bk-step").forEach(function (s) { s.classList.toggle("is-active", +s.dataset.step === n); });
    document.querySelectorAll(".bk-steps__item").forEach(function (s) { var i = +s.dataset.stepdot; s.classList.toggle("is-active", i === n); s.classList.toggle("is-done", i < n); });
    document.getElementById("btnBack").hidden = n === 1;
    document.getElementById("btnNext").textContent = n === 4 ? ("پرداخت · " + money(payablePrice())) : "ادامه";
    if (n === 1) renderProviders();
    if (n === 2) renderServices();
    if (n === 3) { renderCalendar(); state.date ? fetchSlots(state.time) : (document.getElementById("slots").innerHTML = '<p class="slots__empty">ابتدا یک روز را انتخاب کنید.</p>'); renderRecurNote(); }
    if (n === 4) renderSummary();
    document.getElementById("bk").scrollIntoView({ behavior: "smooth", block: "start" });
  }
  function validateStep() {
    if (state.step === 1 && !state.provider) { toast("یک متخصص را انتخاب کنید."); return false; }
    if (state.step === 2 && chosenSubs().length === 0) { toast("حداقل یک خدمت را انتخاب کنید."); return false; }
    if (state.step === 3 && (!state.date || !state.time)) { toast("روز و ساعت نوبت را انتخاب کنید."); return false; }
    if (state.step === 4) { var nm = document.getElementById("custName").value.trim(); var ph = document.getElementById("custPhone").value.trim(); if (!nm || !/^09\d{9}$/.test(ph)) { toast("نام و شمارهٔ موبایل معتبر وارد کنید."); return false; } }
    return true;
  }
  var toastT;
  function toast(msg) {
    var t = document.getElementById("bkToast");
    if (!t) { t = el("div", "bk-toast"); t.id = "bkToast"; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add("is-show"); clearTimeout(toastT); toastT = setTimeout(function () { t.classList.remove("is-show"); }, 2800);
  }
  function finish() {
    var btn = document.getElementById("btnNext"); btn.disabled = true; btn.textContent = "در حال ثبت…";
    api("book", {
      date: greg(state.date.jy, state.date.jm, state.date.jd), time: state.time, weeks: state.weeks,
      name: document.getElementById("custName").value.trim(), phone: document.getElementById("custPhone").value.trim(),
      code: creditCode()
    }).then(function (res) {
      btn.disabled = false; btn.textContent = "پرداخت · " + money(payablePrice());
      if (!res || !res.success) { toast(res && res.data ? res.data : "ثبت نوبت ناموفق بود."); return; }
      if (res.data && res.data.redirect) { window.location.href = res.data.redirect; return; }
      var cal = document.getElementById("doneCal");
      if (res.data && res.data.event) cal.innerHTML = '<a class="bk-cal" target="_blank" rel="noopener" href="' + res.data.event + '">افزودن به Google Calendar</a>';
      var atSalon = res.data && res.data.atSalon;
      document.getElementById("doneMsg").textContent = atSalon
        ? "نوبت شما با کد اعتبار ثبت شد؛ مبلغ را هنگام حضور در آرایشگاه پرداخت می‌کنید. پیامک تأیید ارسال می‌شود."
        : "پیامک تأیید ارسال می‌شود؛ یادآوری هم پیش از نوبت ارسال خواهد شد.";
      document.getElementById("bkDone").hidden = false;
    });
  }
  function reset() {
    state.provider = null; state.subs = {}; state.date = null; state.time = null; state.weeks = 0;
    document.getElementById("custName").value = ""; document.getElementById("custPhone").value = "";
    var code = document.getElementById("custCode"); if (code) code.value = "";
    var cbox = document.getElementById("bkCreditBox"); if (cbox) cbox.hidden = true;
    document.querySelectorAll(".recur__opt").forEach(function (o, i) { o.classList.toggle("is-active", i === 0); });
    document.getElementById("bkDone").hidden = true; refreshTotal(); gotoStep(1);
  }
  function boot() {
    renderProviders(); wireRecur(); refreshTotal();
    document.getElementById("btnNext").addEventListener("click", function () { if (!validateStep()) return; if (state.step < 4) gotoStep(state.step + 1); else finish(); });
    document.getElementById("btnBack").addEventListener("click", function () { if (state.step > 1) gotoStep(state.step - 1); });
    document.getElementById("btnReset").addEventListener("click", reset);
    document.getElementById("firstFree").addEventListener("click", firstAvailable);
    var ct = document.getElementById("bkCreditToggle");
    if (ct) ct.addEventListener("click", function () {
      var box = document.getElementById("bkCreditBox");
      if (box) { box.hidden = !box.hidden; ct.classList.toggle("is-open", !box.hidden); }
    });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot); else boot();
})();
