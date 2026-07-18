// Consentement cookies — stockage dans localStorage uniquement (aucun cookie
// de suivi n'est nécessaire pour se souvenir du choix).
(function () {
  "use strict";
  var KEY = "mgpt_cookie_consent";
  var modal = document.getElementById("cookie-modal");
  if (!modal) return;

  if (!localStorage.getItem(KEY)) {
    window.setTimeout(function () {
      modal.classList.add("show");
    }, 600);
  }

  window.cookieChoice = function (choice) {
    localStorage.setItem(KEY, choice);
    localStorage.setItem(KEY + "_date", new Date().toISOString());
    modal.classList.remove("show");
  };
})();
