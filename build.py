#!/usr/bin/env python3
"""Genera il sito statico di Technoproject Italia.

Uso:
    python3 build.py              -> crea ../sito (da caricare in public_html su Hostinger)
    python3 build.py --anteprima  -> crea ../anteprima (font da Google, form e chatbot in modalità demo)
    python3 build.py --out DIR    -> genera in una cartella a scelta (usato dal deploy automatico)

Header, footer e dati aziendali sono definiti una volta sola qui sotto.
Le pagine stanno in pagine/*.html, le app in dati/progetti.json: per ogni app con
"sottodominio" viene generata la cartella <sottodominio>/ con la sua pagina, pronta
per il sottodominio omonimo su Hostinger (public_html/<sottodominio>).
"""
import html
import json
import re
import shutil
import sys
from pathlib import Path

QUI = Path(__file__).resolve().parent
ANTEPRIMA = "--anteprima" in sys.argv
# Assistente virtuale nascosto finché non sono pronte le risposte; l'anteprima lo mostra comunque
CHATBOT_ATTIVO = False
OUT = Path(sys.argv[sys.argv.index("--out") + 1]).resolve() if "--out" in sys.argv else QUI.parent / ("anteprima" if ANTEPRIMA else "sito")

# ---- Dati aziendali: modificali qui ---------------------------------------
AZIENDA = {
    "nome": "Technoproject Italia Srl",
    "indirizzo": "Viale Umberto 108/B",
    "cap_citta": "07100 Sassari (SS)",
    "piva": "02710280906",
    "email": "info@t-project.io",           # DA CONFERMARE
    "pec": "",                               # es. technoprojectitalia@pec.it
    "telefono": "",                          # es. +39 079 000 0000
    "dominio": "t-project.io",
}

PAGINE = [
    # file,            titolo <title>,                                   descrizione meta,                                                              voce menu
    ("index.html",      "Technoproject Italia · Software, siti e app su misura a Sassari", "Software house di Sassari: progettiamo e sviluppiamo software gestionali, siti web, e-commerce e app iOS e Android su misura per le aziende.", None),
    ("servizi.html",    "Servizi · Technoproject Italia",     "Software gestionali su misura, siti web ed e-commerce, app mobile, integrazioni, automazioni e soluzioni di intelligenza artificiale.", "Servizi"),
    ("progetti.html",   "Le nostre app · Technoproject Italia", "Le app e i software sviluppati da Technoproject Italia: schede, stato di sviluppo e accesso diretto alle piattaforme.", "Progetti e app"),
    ("chi-siamo.html",  "Chi siamo · Technoproject Italia",   "Technoproject Italia Srl è una software house con sede a Sassari: metodo, valori e dati societari.", "Chi siamo"),
    ("contatti.html",   "Contatti · Technoproject Italia",    "Contatta Technoproject Italia per un preventivo o una consulenza: modulo di contatto, indirizzo e recapiti.", "Contatti"),
    ("privacy.html",    "Privacy policy · Technoproject Italia", "Informativa sul trattamento dei dati personali ai sensi del Regolamento UE 2016/679 (GDPR).", None),
    ("cookie.html",     "Cookie policy · Technoproject Italia", "Informativa sui cookie e sulle tecnologie simili usate dal sito t-project.io.", None),
    ("modello-app.html", "[Nome app] · Technoproject Italia", "Modello di pagina per le app pubblicate nei sottodomini.", None),
    ("404.html",        "Pagina non trovata · Technoproject Italia", "La pagina richiesta non esiste.", None),
]

LOGO_SVG = (
    '<svg viewBox="0 0 32 32" aria-hidden="true"><rect x="1" y="1" width="30" height="30" rx="9" fill="var(--hl)"/>'
    '<path d="M9 11h14M16 11v12" stroke="var(--hl-ink)" stroke-width="3.2" stroke-linecap="round"/>'
    '<circle cx="24" cy="23" r="3" fill="var(--hl-2)"/></svg>'
)

FONT_LINK_GOOGLE = (
    '<link rel="preconnect" href="https://fonts.googleapis.com">\n'
    '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n'
    '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800'
    '&family=Manrope:wght@400;500;700&family=JetBrains+Mono:wght@400;500&display=swap">'
)
# In produzione i font sono ospitati sul nostro server (nessun dato inviato a Google)
FONT_LINK_LOCALE = '<link rel="stylesheet" href="assets/fonts/fonts.css">'


def header(attiva: str, base: str = "") -> str:
    """base: prefisso dei link verso il sito principale ("" per le sue pagine)."""
    voci = []
    for file, _, _, voce in PAGINE:
        if voce:
            cur = ' aria-current="page"' if file == attiva else ""
            voci.append(f'<li><a href="{base}{file}"{cur}>{voce}</a></li>')
    return f"""<a class="skip" href="#contenuto">Vai al contenuto</a>
<header class="site-header">
  <div class="wrap">
    <a class="logo" href="{base}index.html" aria-label="Technoproject Italia, home">{LOGO_SVG}<span>techno<b>project</b></span></a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="menu" aria-label="Apri il menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
    <nav class="nav" id="menu" aria-label="Menu principale">
      <ul>{''.join(voci)}</ul>
      <a class="btn" href="{base}contatti.html#modulo">Richiedi un preventivo</a>
    </nav>
  </div>
</header>"""


def footer(base: str = "") -> str:
    a = AZIENDA
    tel = f'<br>Tel. {a["telefono"]}' if a["telefono"] else ""
    pec = f'<br>PEC: {a["pec"]}' if a["pec"] else ""
    return f"""<footer class="site-footer">
  <div class="wrap">
    <div class="cols">
      <div>
        <a class="logo" href="{base}index.html">{LOGO_SVG}<span>techno<b>project</b></span></a>
        <p class="muted" style="margin-top:14px;max-width:34ch">Software, siti e app su misura. Progettati a Sassari per aziende di tutta Italia.</p>
      </div>
      <div><h4>Esplora</h4><ul>
        <li><a href="{base}servizi.html">Servizi</a></li><li><a href="{base}progetti.html">Progetti e app</a></li>
        <li><a href="{base}chi-siamo.html">Chi siamo</a></li><li><a href="{base}contatti.html">Contatti</a></li></ul></div>
      <div><h4>Informazioni legali</h4><ul>
        <li><a href="{base}privacy.html">Privacy policy</a></li><li><a href="{base}cookie.html">Cookie policy</a></li></ul></div>
      <div><h4>Sede</h4><address>{a["nome"]}<br>{a["indirizzo"]}<br>{a["cap_citta"]}<br>
        <a href="mailto:{a["email"]}">{a["email"]}</a>{tel}{pec}</address></div>
    </div>
    <div class="legal"><span>© <span data-anno>2026</span> {a["nome"]} · P.IVA {a["piva"]}</span>
      <span>{a["indirizzo"]}, {a["cap_citta"]}</span></div>
  </div>
</footer>"""


def url_app(p: dict, dominio: str) -> str:
    """Indirizzo della scheda: sito esterno oppure sottodominio (in anteprima la cartella locale)."""
    if p.get("sito_esterno"):
        return p["sito_esterno"]
    return f'{p["sottodominio"]}/index.html' if ANTEPRIMA else f'https://{p["sottodominio"]}.{dominio}'


def card_app(p: dict, dominio: str) -> str:
    stato = {"online": ("online", "Online"), "sviluppo": ("sviluppo", "In sviluppo"), "arrivo": ("arrivo", "In arrivo")}[p["stato"]]
    url = url_app(p, dominio)
    esterno = ' target="_blank" rel="noopener"' if p.get("sito_esterno") else ""
    chips = "".join(f"<span>{html.escape(c)}</span>" for c in p.get("piattaforme", []))
    if p.get("accesso"):
        accedi = f'<a class="btn" href="{p["accesso"]}" target="_blank" rel="noopener">Accedi</a>'
    else:
        accedi = '<span class="btn btn--ghost" aria-disabled="true" style="opacity:.55;cursor:default">Accesso in arrivo</span>'
    etichetta = p.get("etichetta_url") or f'{p["sottodominio"]}.{dominio}'
    esempio = ' <span class="muted" style="font-size:var(--t-xs)">(esempio)</span>' if p.get("esempio") else ""
    return f"""<article class="app">
  <div class="app__top"><span class="app__ico" style="background:{p.get('colore', 'var(--hl)')}">{html.escape(p['sigla'])}</span>
    <span class="status status--{stato[0]}">{stato[1]}</span></div>
  <div class="app__body"><h3>{html.escape(p['nome'])}{esempio}</h3>
    <p>{html.escape(p['descrizione'])}</p>
    <span class="app__url">{etichetta}</span>
    <div class="app__chips">{chips}</div></div>
  <div class="app__foot">{accedi}<a class="btn btn--ghost" href="{url}"{esterno}>Scheda dell'app</a></div>
</article>"""


def corpo_app(p: dict, base: str) -> str:
    """Pagina descrittiva di un'app, pubblicata nel suo sottodominio."""
    g = p["pagina"]
    stato = {"online": ("online", "Online"), "sviluppo": ("sviluppo", "In sviluppo"), "arrivo": ("arrivo", "In arrivo")}[p["stato"]]
    righe = "".join(f'<li class="done"><span class="n">✓</span>{html.escape(k)}<em>{html.escape(v)}</em></li>' for k, v in g["scheda"])
    funzioni = "".join(f'<article class="card"><h3>{html.escape(t)}</h3><p>{html.escape(d)}</p></article>' for t, d in g["funzioni"])
    sezioni = "".join(f'<div><h3>{html.escape(t)}</h3><p>{html.escape(d)}</p></div>' for t, d in g["sezioni"])
    accedi = (f'<a class="btn" href="{p["accesso"]}">Accedi</a>' if p.get("accesso")
              else f'<a class="btn" href="{base}contatti.html#modulo">Avvisami al lancio</a>')
    out = f"""<section class="hero grid-paper">
  <div class="wrap">
    <div>
      <span class="pill"><i></i>App di Technoproject Italia · {stato[1]}</span>
      <h1>{g['titolo']}</h1>
      <p class="lead">{html.escape(g['lead'])}</p>
      <div class="btn-row">{accedi}{'<a class="btn btn--ghost" href="#funzioni">Scopri le funzioni</a>' if funzioni else ''}</div>
    </div>
    <div class="plan" aria-label="Scheda tecnica">
      <div class="plan__label">scheda tecnica</div>
      <div class="plan__title">{html.escape(p['nome'])}</div>
      <ul class="steps" style="list-style:none">{righe}</ul>
    </div>
  </div>
</section>
"""
    if funzioni:
        out += f"""<section class="section" id="funzioni">
  <div class="wrap">
    <div class="sec-head"><span class="tag">Funzioni</span><h2>Cosa puoi fare con {html.escape(p['nome'])}.</h2></div>
    <div class="cards">{funzioni}</div>
  </div>
</section>
"""
    if sezioni:
        out += f"""<section class="section section--white">
  <div class="wrap"><div class="points">{sezioni}</div></div>
</section>
"""
    out += f"""<section class="cta-band">
  <div class="wrap">
    <div><h2>{html.escape(p['nome'])}</h2><p>{html.escape(g['cta'])}</p></div>
    <a class="btn btn--hl" href="{base}contatti.html#modulo">Scrivici</a>
  </div>
</section>"""
    return out


def documento(titolo: str, descr: str, canonical: str, corpo: str, attiva: str, base: str, assets: str, chatbot: bool) -> str:
    dominio = AZIENDA["dominio"]
    config_js = json.dumps({
        "chatEndpoint": "api/chat.php",
        "contactEndpoint": "api/contatto.php",
        "knowledgeBase": "chatbot/conoscenza.json",
        "email": AZIENDA["email"],
        "demo": ANTEPRIMA,
    })
    font = FONT_LINK_GOOGLE if ANTEPRIMA else f'<link rel="stylesheet" href="{assets}assets/fonts/fonts.css">'
    script_chat = f'\n<script src="{assets}assets/js/chatbot.js" defer></script>' if chatbot else ""
    return f"""<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{titolo}</title>
<meta name="claude:viewport-fit-sides" content="cover">
<meta name="description" content="{html.escape(descr)}">
<link rel="canonical" href="{canonical}">
<meta property="og:type" content="website">
<meta property="og:title" content="{html.escape(titolo)}">
<meta property="og:description" content="{html.escape(descr)}">
<meta property="og:locale" content="it_IT">
<meta name="theme-color" content="#07121b">
<link rel="icon" href="{assets}assets/img/favicon.svg" type="image/svg+xml">
{font}
<link rel="stylesheet" href="{assets}assets/css/style.css">
<script type="application/ld+json">{json.dumps({
    "@context": "https://schema.org", "@type": "Organization", "name": AZIENDA["nome"],
    "url": f"https://www.{dominio}/", "email": AZIENDA["email"], "vatID": "IT" + AZIENDA["piva"],
    "address": {"@type": "PostalAddress", "streetAddress": AZIENDA["indirizzo"], "postalCode": "07100",
                "addressLocality": "Sassari", "addressRegion": "SS", "addressCountry": "IT"}}, ensure_ascii=False)}</script>
</head>
<body>
{header(attiva, base)}
<main id="contenuto">
{corpo}
</main>
{footer(base)}
<script>window.TP_CONFIG = {config_js};</script>
<script src="{assets}assets/js/main.js" defer></script>{script_chat}
</body>
</html>
"""


def sostituisci(testo: str) -> str:
    dominio = AZIENDA["dominio"]
    testo = testo.replace("__DOMINIO_RE__", dominio.replace(".", "\\.")).replace("__DOMINIO__", dominio)
    for k, v in AZIENDA.items():
        testo = testo.replace("{{" + k + "}}", v)
    return testo


def main() -> None:
    if OUT.exists():
        shutil.rmtree(OUT)
    shutil.copytree(QUI / "static", OUT, ignore=shutil.ignore_patterns("vendor"))
    shutil.copytree(QUI / "chatbot", OUT / "chatbot")
    if ANTEPRIMA:
        # in anteprima non servono gli script PHP né i font locali
        for p in ("api", ".htaccess", "assets/fonts"):
            t = OUT / p
            if t.is_dir():
                shutil.rmtree(t)
            elif t.exists():
                t.unlink()

    dominio = AZIENDA["dominio"]
    # dominio nei file di configurazione e nella base di conoscenza
    for rel in (".htaccess", "api/config.example.php", "chatbot/conoscenza.json"):
        f = OUT / rel
        if f.exists():
            f.write_text(sostituisci(f.read_text(encoding="utf-8")), encoding="utf-8")

    progetti = json.loads((QUI / "dati" / "progetti.json").read_text(encoding="utf-8"))
    tutte_app = "\n".join(card_app(p, dominio) for p in progetti)
    prime_app = "\n".join(card_app(p, dominio) for p in progetti[:4])

    for file, titolo, descr, _ in PAGINE:
        corpo = sostituisci((QUI / "pagine" / file).read_text(encoding="utf-8"))
        corpo = corpo.replace("{{app_tutte}}", tutte_app).replace("{{app_home}}", prime_app)
        chatbot = CHATBOT_ATTIVO or ANTEPRIMA
        if not chatbot:
            # senza widget, via anche i pulsanti e i riquadri che lo aprono
            corpo = re.sub(r'<button[^>]*data-open-chat[^>]*>.*?</button>', "", corpo)
            corpo = re.sub(r'<div[^>]*data-solo-chat[^>]*>.*?</div>', "", corpo)
        canonical = f"https://www.{dominio}/" + ("" if file == "index.html" else file)
        (OUT / file).write_text(documento(titolo, descr, canonical, corpo, file, "", "", chatbot), encoding="utf-8")

    # Pagine delle app nei sottodomini: ogni cartella contiene la sua copia di assets/
    for p in progetti:
        if not p.get("sottodominio") or not p.get("pagina"):
            continue
        cartella = OUT / p["sottodominio"]
        cartella.mkdir()
        base = "../" if ANTEPRIMA else f"https://www.{dominio}/"
        assets = "../" if ANTEPRIMA else ""
        if not ANTEPRIMA:
            shutil.copytree(OUT / "assets", cartella / "assets")
        titolo = f"{p['nome']} · App di Technoproject Italia"
        canonical = f"https://{p['sottodominio']}.{dominio}/"
        (cartella / "index.html").write_text(
            documento(titolo, p["descrizione"], canonical, corpo_app(p, base), "", base, assets, False), encoding="utf-8")

    # robots.txt e sitemap.xml
    url = [f"https://www.{dominio}/" + ("" if f == "index.html" else f) for f, *_ in PAGINE if f not in ("404.html", "modello-app.html")]
    url += [f"https://{p['sottodominio']}.{dominio}/" for p in progetti if p.get("sottodominio") and p.get("pagina")]
    (OUT / "sitemap.xml").write_text('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
                                     + "".join(f"  <url><loc>{u}</loc></url>\n" for u in url) + "</urlset>\n", encoding="utf-8")
    (OUT / "robots.txt").write_text(f"User-agent: *\nDisallow: /api/\nDisallow: /modello-app.html\n\nSitemap: https://www.{dominio}/sitemap.xml\n", encoding="utf-8")
    print(f"Sito generato in {OUT}")


if __name__ == "__main__":
    main()
