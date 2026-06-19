/* Dorian — reservation form: elegant validation + premium success state. */
window.Dorian = window.Dorian || {};
window.Dorian.initReserve = function () {
  var form = document.getElementById("reserveForm");
  var success = document.getElementById("reserveSuccess");
  if (!form) return;

  function setError(name, msg) {
    var el = form.querySelector('[data-error="' + name + '"]');
    if (el) el.textContent = msg || "";
  }

  function validate() {
    var ok = true;
    var name = form.name.value.trim();
    var phone = form.phone.value.trim();
    var service = form.service.value;

    if (name.length < 2) {
      setError("name", "لطفاً نام خود را وارد کنید.");
      ok = false;
    } else setError("name", "");

    // Accepts Persian or Latin digits, 8+ length.
    var digits = phone.replace(/[^\d۰-۹]/g, "");
    if (digits.length < 8) {
      setError("phone", "شماره تماس معتبر وارد کنید.");
      ok = false;
    } else setError("phone", "");

    if (!service) {
      setError("service", "یک خدمت را انتخاب کنید.");
      ok = false;
    } else setError("service", "");

    return ok;
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!validate()) return;

    if (window.DorianSound) window.DorianSound.play("ding");
    form.style.display = "none";
    if (success) {
      success.classList.add("is-on");
      if (window.gsap) {
        gsap.fromTo(
          success,
          { opacity: 0, scale: 0.95 },
          { opacity: 1, scale: 1, duration: 0.6, ease: "power3.out" }
        );
        if (window.DrawSVGPlugin) {
          gsap.fromTo(
            "#successMark circle",
            { drawSVG: "0%" },
            { drawSVG: "100%", duration: 0.8 }
          );
          gsap.fromTo(
            "#successMark path",
            { drawSVG: "0%" },
            { drawSVG: "100%", duration: 0.5, delay: 0.6 }
          );
        }
      }
    }
  });

  // Live-clear errors on input.
  ["name", "phone", "service"].forEach(function (n) {
    if (form[n])
      form[n].addEventListener("input", function () {
        setError(n, "");
      });
  });
};
