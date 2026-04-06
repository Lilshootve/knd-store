(function () {
  "use strict";

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  var app = qs(".knd-dash-app");
  if (!app) return;

  var sidebar = qs("#knd-dash-sidebar");
  var backdrop = qs("#knd-dash-sidebar-backdrop");
  var openBtn = qs("#knd-dash-sidebar-open");
  var closeEls = document.querySelectorAll("[data-knd-dash-sidebar-close]");

  function setOpen(open) {
    app.classList.toggle("knd-dash-app--sidebar-open", open);
    if (backdrop) {
      backdrop.classList.toggle("is-open", open);
      backdrop.setAttribute("aria-hidden", open ? "false" : "true");
    }
    if (openBtn) openBtn.setAttribute("aria-expanded", open ? "true" : "false");
  }

  if (openBtn) {
    openBtn.addEventListener("click", function () {
      setOpen(!app.classList.contains("knd-dash-app--sidebar-open"));
    });
  }

  closeEls.forEach(function (el) {
    el.addEventListener("click", function () {
      setOpen(false);
    });
  });

  if (backdrop) {
    backdrop.addEventListener("click", function () {
      setOpen(false);
    });
  }

  document.querySelectorAll(".knd-iris-chip").forEach(function (chip) {
    chip.addEventListener("click", function () {
      var text = chip.getAttribute("data-iris-prompt") || chip.textContent || "";
      var input = document.getElementById("iris-input");
      if (input) {
        input.value = text.trim();
        input.focus();
      }
    });
  });

  document.querySelectorAll('[data-knd-scroll-to="iris"]').forEach(function (el) {
    el.addEventListener("click", function (e) {
      var target = document.getElementById("knd-iris-section");
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: "smooth", block: "start" });
        var inp = document.getElementById("iris-input");
        if (inp) setTimeout(function () { inp.focus(); }, 400);
        if (window.matchMedia("(max-width: 900px)").matches) setOpen(false);
      }
    });
  });
})();
