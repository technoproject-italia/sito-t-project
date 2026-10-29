/* Technoproject Italia — menu mobile, anno nel footer, modulo di contatto */
(function () {
  "use strict";
  var CFG = window.TP_CONFIG || {};

  // Menu mobile
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.getElementById("menu");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Chiudi il menu" : "Apri il menu");
    });
  }

  // Anno corrente
  document.querySelectorAll("[data-anno]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  // Modulo di contatto
  var form = document.getElementById("modulo");
  if (!form || form.tagName !== "FORM") return;
  var msg = form.querySelector(".form-msg");
  var btn = form.querySelector('button[type="submit"]');

  function show(text, ok) {
    msg.hidden = false;
    msg.className = "form-msg " + (ok ? "ok" : "ko");
    msg.textContent = text;
  }
  function setErr(name, text) {
    var el = form.querySelector('[data-err="' + name + '"]');
    if (el) el.textContent = text || "";
  }
  function validate() {
    var ok = true;
    var nome = form.nome.value.trim(), email = form.email.value.trim(), testo = form.messaggio.value.trim();
    setErr("nome"); setErr("email"); setErr("messaggio"); setErr("privacy");
    if (nome.length < 2) { setErr("nome", "Scrivi il tuo nome e cognome."); ok = false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { setErr("email", "Controlla l'indirizzo email: sembra incompleto."); ok = false; }
    if (testo.length < 10) { setErr("messaggio", "Descrivi la richiesta in almeno qualche parola."); ok = false; }
    if (!form.privacy.checked) { setErr("privacy", "Per inviare la richiesta serve il consenso al trattamento dei dati."); ok = false; }
    return ok;
  }

  // Ritorno dal modulo senza JavaScript (redirect del PHP)
  var q = new URLSearchParams(location.search);
  if (q.get("inviato") === "1") show("Grazie! Abbiamo ricevuto la tua richiesta e ti risponderemo entro un giorno lavorativo.", true);
  if (q.get("inviato") === "0") show("Non siamo riusciti a inviare il messaggio. Riprova o scrivici a " + (CFG.email || "info@technoproject.it") + ".", false);

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!validate()) { show("Controlla i campi evidenziati.", false); return; }
    btn.disabled = true; btn.textContent = "Invio in corso…";

    if (CFG.demo) {
      setTimeout(function () {
        show("Anteprima: in questa versione dimostrativa il messaggio non viene inviato. Sul sito pubblicato arriverà via email.", true);
        form.reset(); btn.disabled = false; btn.textContent = "Invia la richiesta";
      }, 600);
      return;
    }

    fetch(CFG.contactEndpoint || form.action, {
      method: "POST",
      headers: { "Accept": "application/json", "X-Requested-With": "fetch" },
      body: new FormData(form)
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (data) {
        if (data.ok) { show("Grazie! Abbiamo ricevuto la tua richiesta e ti risponderemo entro un giorno lavorativo.", true); form.reset(); }
        else { show(data.errore || "Non siamo riusciti a inviare il messaggio. Riprova tra qualche minuto.", false); }
      })
      .catch(function () { show("Connessione non riuscita. Controlla la rete e riprova.", false); })
      .finally(function () { btn.disabled = false; btn.textContent = "Invia la richiesta"; });
  });
})();
