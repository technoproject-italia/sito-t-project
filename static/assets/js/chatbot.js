/* Technoproject Italia — assistente virtuale
   Modalità AI: invia la conversazione a api/chat.php (che usa Claude).
   Modalità locale: se l'API non è configurata o non risponde, cerca la risposta
   in chatbot/conoscenza.json per parole chiave. */
(function () {
  "use strict";
  var CFG = window.TP_CONFIG || {};
  var KB = null;
  var history = [];
  var busy = false;
  var aiDisponibile = !CFG.demo;

  var ICON_BOT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z"/><path d="M8.5 11h.01M12 11h.01M15.5 11h.01"/></svg>';
  var ICON_SEND = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

  function store(get, val) {
    try {
      if (get) return JSON.parse(sessionStorage.getItem("tp_chat") || "[]");
      sessionStorage.setItem("tp_chat", JSON.stringify(val.slice(-30)));
    } catch (e) { return []; }
  }
  function esc(s) { return s.replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); }
  // Rende cliccabili i riferimenti alle pagine del sito e le email
  function linkify(s) {
    return esc(s)
      .replace(/\b(pagina )?Contatti\b/g, function (m) { return '<a href="contatti.html#modulo">' + m + "</a>"; })
      .replace(/\bProgetti e app\b/g, '<a href="progetti.html">Progetti e app</a>')
      .replace(/([\w.+-]+@[\w-]+\.[\w.]+)/g, '<a href="mailto:$1">$1</a>');
  }

  // ---- UI ----
  var launch = document.createElement("button");
  launch.className = "tp-chat-launch";
  launch.type = "button";
  launch.setAttribute("aria-label", "Apri l'assistente virtuale");
  launch.innerHTML = '<span class="bubble">Ciao! Vuoi sapere cosa possiamo creare per te?</span><span class="av">' + ICON_BOT + "</span>";

  var panel = document.createElement("section");
  panel.className = "tp-chat";
  panel.hidden = true;
  panel.setAttribute("role", "dialog");
  panel.setAttribute("aria-label", "Assistente virtuale Technoproject");
  panel.innerHTML =
    '<div class="tp-chat__head"><span class="av">' + ICON_BOT + '</span><div><strong>Assistente Technoproject</strong><small>Risponde subito, 24 ore su 24</small></div>' +
    '<button type="button" aria-label="Chiudi l\'assistente">×</button></div>' +
    '<div class="tp-chat__log" aria-live="polite"></div>' +
    '<div class="tp-chat__quick"></div>' +
    '<div><form class="tp-chat__form"><label class="sr" for="tp-chat-input">Scrivi un messaggio</label>' +
    '<input id="tp-chat-input" autocomplete="off" maxlength="800" placeholder="Scrivi la tua domanda…">' +
    '<button type="submit" aria-label="Invia">' + ICON_SEND + "</button></form>" +
    '<p class="tp-chat__note">Risposte generate automaticamente. Non inserire dati personali. <a href="privacy.html">Privacy</a></p></div>';

  document.body.appendChild(launch);
  document.body.appendChild(panel);

  var log = panel.querySelector(".tp-chat__log");
  var quick = panel.querySelector(".tp-chat__quick");
  var form = panel.querySelector("form");
  var input = panel.querySelector("input");

  try { if (localStorage.getItem("tp_chat_hint") === "1") launch.querySelector(".bubble").hidden = true; } catch (e) {}

  function add(role, text) {
    var el = document.createElement("div");
    el.className = "tp-msg tp-msg--" + (role === "user" ? "me" : "bot");
    el.innerHTML = role === "user" ? esc(text) : linkify(text);
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }
  function typing() {
    var el = document.createElement("div");
    el.className = "tp-msg tp-msg--bot";
    el.innerHTML = '<span class="tp-typing" aria-label="Sto scrivendo"><i></i><i></i><i></i></span>';
    log.appendChild(el); log.scrollTop = log.scrollHeight;
    return el;
  }

  function loadKB() {
    if (KB) return Promise.resolve(KB);
    return fetch(CFG.knowledgeBase || "chatbot/conoscenza.json")
      .then(function (r) { return r.json(); })
      .then(function (j) { KB = j; return j; })
      .catch(function () { KB = { assistente: {}, argomenti: [] }; return KB; });
  }

  function norm(s) { return s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9\s-]/g, " "); }
  function localAnswer(q) {
    var words = norm(q).split(/\s+/).filter(Boolean);
    var best = null, bestScore = 0;
    (KB.argomenti || []).forEach(function (a) {
      var score = 0;
      a.parole_chiave.forEach(function (k) {
        var nk = norm(k);
        words.forEach(function (w) {
          if (w === nk) score += 3;
          else if (w.length > 3 && nk.length > 3 && (w.indexOf(nk) === 0 || nk.indexOf(w) === 0)) score += 2;
        });
      });
      if (score > 0) score += a.priorita || 0;
      if (score > bestScore) { bestScore = score; best = a; }
    });
    if (/^(ciao|buongiorno|buonasera|salve|hey)\b/.test(norm(q)) && bestScore < 3) return KB.assistente.benvenuto;
    if (/\b(grazie|perfetto|ok)\b/.test(norm(q)) && bestScore < 3) return "Grazie a te! Se vuoi approfondire, scrivici dalla pagina Contatti: ti rispondiamo entro un giorno lavorativo.";
    return best && bestScore >= 2 ? best.risposta : (KB.assistente.non_capito || "Puoi riformulare la domanda?");
  }

  function ask(text) {
    if (busy || !text.trim()) return;
    busy = true;
    quick.hidden = true;
    add("user", text);
    history.push({ role: "user", content: text });
    store(false, history);
    var t = typing();

    var done = function (reply) {
      t.remove();
      add("assistant", reply);
      history.push({ role: "assistant", content: reply });
      store(false, history);
      busy = false;
      input.focus();
    };

    var fallback = function () { loadKB().then(function () { setTimeout(function () { done(localAnswer(text)); }, 350); }); };

    if (!aiDisponibile) { fallback(); return; }
    fetch(CFG.chatEndpoint || "api/chat.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify({ messages: history.slice(-12) })
    })
      .then(function (r) { if (!r.ok && r.status !== 429) throw new Error(r.status); return r.json(); })
      .then(function (d) { if (d && d.reply) done(d.reply); else throw new Error("vuoto"); })
      .catch(function () { aiDisponibile = false; fallback(); });
  }

  function open() {
    panel.hidden = false; launch.hidden = true;
    try { localStorage.setItem("tp_chat_hint", "1"); } catch (e) {}
    loadKB().then(function (kb) {
      if (!log.children.length) {
        history = store(true) || [];
        if (history.length) history.forEach(function (m) { add(m.role, m.content); });
        else add("assistant", kb.assistente.benvenuto || "Ciao! Come posso aiutarti?");
        quick.innerHTML = "";
        if (!history.length) (kb.assistente.suggerimenti || []).forEach(function (s) {
          var b = document.createElement("button"); b.type = "button"; b.textContent = s;
          b.addEventListener("click", function () { ask(s); });
          quick.appendChild(b);
        });
      }
      input.focus();
    });
  }
  function close() { panel.hidden = true; launch.hidden = false; launch.querySelector(".bubble").hidden = true; launch.focus(); }

  launch.addEventListener("click", open);
  panel.querySelector(".tp-chat__head button").addEventListener("click", close);
  panel.addEventListener("keydown", function (e) { if (e.key === "Escape") close(); });
  form.addEventListener("submit", function (e) { e.preventDefault(); var v = input.value; input.value = ""; ask(v); });
  document.querySelectorAll("[data-open-chat]").forEach(function (b) { b.addEventListener("click", open); });
})();
