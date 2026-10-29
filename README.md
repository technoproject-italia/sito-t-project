# Sito Technoproject Italia · t-project.io

- `python3 build.py` crea `../sito` (da caricare in `public_html` su Hostinger);
  `python3 build.py --anteprima` crea `../anteprima`; `--out DIR` genera in una cartella a scelta.
- Dati aziendali (email, dominio, telefono, PEC): in cima a `build.py`.
- Testi delle pagine: `pagine/*.html`. App e loro pagine nei sottodomini: `dati/progetti.json`
  (ogni app con `sottodominio` genera la cartella omonima, da collegare al sottodominio su Hostinger).
- Base di conoscenza del chatbot: `chatbot/conoscenza.json`.
- Stile: token in cima a `static/assets/css/style.css`.
- Pubblicazione automatica: `.github/workflows/deploy.yml` genera il sito e lo carica via FTP a ogni push su `main`.
  Le credenziali stanno nei secrets del repository GitHub, mai nel codice.
