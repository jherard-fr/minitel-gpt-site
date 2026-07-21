/* MINITEL GPT v2 — comportements communs à toutes les pages */
(function () {
  "use strict";
  document.documentElement.classList.add("js");
  var reduit = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var pointeurFin = window.matchMedia("(pointer:fine)").matches;

  /* --- préchargeur « connexion » (index uniquement) --------------------- */
  var boot = document.getElementById("boot");
  if (boot) {
    var finBoot = function () { boot.classList.add("fini"); };
    if (reduit) { finBoot(); } else { window.setTimeout(finBoot, 1750); }
    window.setTimeout(finBoot, 3200); // filet de sécurité
  }

  /* --- nav : état scrollé + indicateur de connexion ---------------------- */
  var nav = document.getElementById("nav");
  var etatTxt = document.getElementById("etat-txt");
  function surScroll() {
    if (nav) nav.classList.toggle("scrolle", window.scrollY > 30);
    if (etatTxt) {
      var h = document.documentElement.scrollHeight - window.innerHeight;
      var p = h > 0 ? Math.round(window.scrollY / h * 100) : 0;
      etatTxt.textContent = "CONNECTÉ · " + String(p).padStart(3, "0");
    }
  }
  window.addEventListener("scroll", surScroll, { passive: true });
  surScroll();

  /* --- menu déroulant DIY (clic + clavier ; le survol est géré en CSS) --- */
  document.querySelectorAll(".nav-deroulant").forEach(function (dd) {
    var btn = dd.querySelector(".nav-deroulant-bouton");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var ouvert = dd.classList.toggle("ouvert");
      btn.setAttribute("aria-expanded", ouvert ? "true" : "false");
    });
    document.addEventListener("click", function (e) {
      if (!dd.contains(e.target)) {
        dd.classList.remove("ouvert");
        btn.setAttribute("aria-expanded", "false");
      }
    });
  });

  /* --- burger / menu mobile ---------------------------------------------- */
  var burger = document.getElementById("burger");
  var menu = document.getElementById("menu-mobile");
  if (burger && menu) {
    burger.addEventListener("click", function () {
      var ouvert = menu.classList.toggle("ouvert");
      burger.classList.toggle("ouvert", ouvert);
      burger.setAttribute("aria-expanded", ouvert ? "true" : "false");
      document.body.style.overflow = ouvert ? "hidden" : "";
    });
    menu.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        menu.classList.remove("ouvert");
        burger.classList.remove("ouvert");
        burger.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
      });
    });
  }

  /* --- révélations au scroll ---------------------------------------------- */
  var io = new IntersectionObserver(function (entrees) {
    entrees.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
    });
  }, { threshold: .12, rootMargin: "0px 0px -8% 0px" });
  document.querySelectorAll("[data-reveal]").forEach(function (el) { io.observe(el); });

  /* --- curseur bloc terminal ----------------------------------------------- */
  var cur = document.getElementById("cursor");
  if (cur && pointeurFin && !reduit) {
    document.addEventListener("mousemove", function (e) {
      cur.style.opacity = "1";
      cur.style.transform = "translate(" + (e.clientX + 14) + "px," + (e.clientY - 6) + "px)";
    }, { passive: true });
    document.addEventListener("mouseover", function (e) {
      cur.classList.toggle("actif", !!e.target.closest("a,button,summary,input,textarea"));
    }, { passive: true });
    document.addEventListener("mouseleave", function () { cur.style.opacity = "0"; });
  }

  /* --- inclinaison du CRT au survol (index) --------------------------------- */
  var cadre = document.getElementById("crt-cadre");
  if (cadre && pointeurFin && !reduit) {
    cadre.addEventListener("mousemove", function (e) {
      var r = cadre.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - .5;
      var y = (e.clientY - r.top) / r.height - .5;
      cadre.style.setProperty("--ry", (x * 7) + "deg");
      cadre.style.setProperty("--rx", (-y * 7) + "deg");
    });
    cadre.addEventListener("mouseleave", function () {
      cadre.style.setProperty("--ry", "0deg");
      cadre.style.setProperty("--rx", "0deg");
    });
  }

  /* --- terminal Vidéotex simulé (index) -------------------------------------- */
  var ecran = document.getElementById("crt-texte");
  if (ecran) {
    var SCENARIO = [
      { t: "instant", txt: "          ┌─────────────────┐\n          │   MINITEL GPT   │\n          └─────────────────┘\n\n" },
      { t: "instant", txt: "   *** BON ANNIVERSAIRE JEF ! ***\n\n     Quelle question 80's as-tu ?\n\n" },
      { t: "pause", ms: 900 },
      { t: "tape", txt: "> OU SORTIR A PARIS CE SOIR ?", ms: 75 },
      { t: "pause", ms: 700 },
      { t: "instant", txt: "\n\n  J'INTERROGE LES ANNEES 80...\n\n" },
      { t: "pause", ms: 1100 },
      { t: "tape", txt: "  CE SOIR ? LE PALACE, VOYONS.\n  OU LES BAINS-DOUCHES SI TU\n  CONNAIS LE PHYSIONOMISTE.\n  PREVOIS DES EPAULETTES.", ms: 24 },
      { t: "pause", ms: 1400 },
      { t: "instant", txt: "\n\n      -- SUITE pour la suite --" },
      { t: "pause", ms: 2600 }
    ];
    var etape = 0, i = 0, buffer = "";
    var rendre = function (fin) {
      ecran.innerHTML = buffer.replace(/&/g, "&amp;").replace(/</g, "&lt;")
        + (fin ? "" : '<span class="curseur-crt"></span>');
    };
    var jouer = function () {
      if (etape >= SCENARIO.length) { etape = 0; buffer = ""; }
      var s = SCENARIO[etape];
      if (s.t === "instant") { buffer += s.txt; etape++; rendre(false); setTimeout(jouer, 60); }
      else if (s.t === "pause") { rendre(false); etape++; setTimeout(jouer, s.ms); }
      else {
        if (i < s.txt.length) { buffer += s.txt.charAt(i); i++; rendre(false); setTimeout(jouer, s.ms + Math.random() * 40); }
        else { i = 0; etape++; setTimeout(jouer, 120); }
      }
    };
    if (reduit) {
      buffer = SCENARIO.filter(function (s) { return s.txt; }).map(function (s) { return s.txt; }).join("");
      rendre(true);
    } else {
      setTimeout(jouer, boot ? 1900 : 600);
    }
  }

  /* --- formulaire de contact (POST vers /contact.php) -------------------------- */
  var f = document.getElementById("contactForm");
  if (f) {
    var st = document.getElementById("cf-status"), btn = document.getElementById("cf-submit");
    f.addEventListener("submit", function (e) {
      e.preventDefault();
      st.textContent = ""; st.className = "cf-status";
      if (!f.name.value.trim() || !f.email.value.trim() || !f.message.value.trim()) {
        st.textContent = "Merci de remplir tous les champs."; st.classList.add("err"); return;
      }
      btn.disabled = true; var lbl = btn.innerHTML; btn.innerHTML = "Envoi…";
      fetch("/contact.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(new FormData(f))
      })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (d) {
          if (d.ok) { f.reset(); st.textContent = "Message envoyé, merci ! Je vous répondrai vite."; st.classList.add("ok"); }
          else if (d.error === "rate") { st.textContent = "Doucement ! Patientez une minute avant d'envoyer un nouveau message."; st.classList.add("err"); }
          else { st.textContent = "Oups, l'envoi a échoué. Réessayez ou écrivez à jerome@herard.com."; st.classList.add("err"); }
        })
        .catch(function () { st.textContent = "Erreur réseau. Réessayez plus tard."; st.classList.add("err"); })
        .finally(function () { btn.disabled = false; btn.innerHTML = lbl; });
    });
  }
})();
