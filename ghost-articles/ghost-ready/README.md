# Ghost-Ready Articles - Pronti per Pubblicazione

Questi file sono **puliti e pronti** per copy-paste diretto in Ghost.

---

## 🚀 Quick Start (5 min per articolo)

### Per Ogni Articolo:

1. **Apri Ghost** → https://enzolarosa.dev/ghost → New Post
2. **Copia TUTTO** dal file `.md`
3. **Incolla** in Ghost editor
4. **Verifica** formattazione (Ghost auto-formatta Markdown)
5. **Settings** → Usa configurazioni da `../GHOST_IMPORT_GUIDE.md`
6. **Publish!**

---

## 📋 File Disponibili

### FASE 1: Docs (pubblica per primi)

✅ **01-getting-started.md**
- URL target: `/docs/mqtt-broadcast-getting-started`
- Featured: Yes
- ~8000 parole

✅ **02-configuration-guide.md**
- URL target: `/docs/mqtt-broadcast-configuration`
- Featured: No
- ~10000 parole

✅ **03-iot-temperature-monitoring-tutorial.md** (EN)
- URL target: `/tutorials/iot-temperature-monitoring-laravel-esp32`
- Featured: Yes
- ~15000 parole

✅ **03-IT-monitoraggio-temperatura-iot-laravel-esp32.md** (IT)
- URL target: `/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32`
- Featured: Yes
- ~14000 parole
- Hreflang: Link a versione EN

### FASE 2: Annunci (pubblica dopo docs)

✅ **00-announcement-laravel-news.md** (EN)
- URL target: `/blog/announcing-mqtt-broadcast-laravel-package`
- Featured: Yes
- ~6000 parole
- Per Laravel News submission

✅ **00-IT-annuncio-mqtt-broadcast.md** (IT)
- URL target: `/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel`
- Featured: Yes
- ~5800 parole
- Hreflang: Link a versione EN

---

## ⚙️ Settings per Ogni Articolo

**Tutti i settings dettagliati sono in:**
`../GHOST_IMPORT_GUIDE.md`

Include per ogni articolo:
- URL Slug
- Title
- Excerpt
- Tags
- Meta Title/Description
- Twitter/Facebook cards
- Schema.org code injection
- Hreflang (quando applicabile)

---

## ✅ Procedura Completa per UN Articolo

### Esempio: Getting Started

**1. Prepara file**
```bash
# Apri file per lettura
cat 01-getting-started.md
```

**2. In Ghost**
- New Post
- Incolla tutto il contenuto
- Verifica rendering

**3. Settings (sidebar destra)**
```
URL: mqtt-broadcast-getting-started
Title: Getting Started with MQTT Broadcast for Laravel
Excerpt: [copia da GHOST_IMPORT_GUIDE.md]
Tags: mqtt-broadcast, laravel, getting-started, tutorial
Featured: ✓
```

**4. Meta Data (in Settings)**
```
Meta Title: [copia da guida]
Meta Description: [copia da guida]
```

**5. Social Cards (in Settings)**
```
Twitter/Facebook: [copia da guida]
```

**6. Code Injection (in Settings)**
```html
<!-- Incolla Schema.org da GHOST_IMPORT_GUIDE.md -->
```

**7. Preview & Publish**
- Click Preview (verifica formattazione)
- Check mobile view
- Publish!

**8. Test**
```bash
# Verifica URL funziona
curl -I https://enzolarosa.dev/docs/mqtt-broadcast-getting-started
```

---

## 🎯 Ordine Pubblicazione Ottimale

### Giorno 1 - Mattina (2h)
1. `01-getting-started.md`
2. `02-configuration-guide.md`

### Giorno 1 - Pomeriggio (2h)
3. `03-iot-temperature-monitoring-tutorial.md` (EN)
4. `03-IT-monitoraggio-temperatura-iot-laravel-esp32.md` (IT)

### Pausa 1 giorno
- Rileggi articoli pubblicati
- Correggi errori/typos
- Testa tutti i link

### Giorno 3 - Lancio (1h)
5. `00-announcement-laravel-news.md` (EN)
6. `00-IT-annuncio-mqtt-broadcast.md` (IT)
7. Submit Laravel News
8. Social media

---

## 💡 Tips Ghost Editor

### Markdown Supportato
```markdown
# Heading 1
## Heading 2
### Heading 3

**Bold** e *italic*

`inline code`

\`\`\`bash
code block
\`\`\`

[Link text](URL)

- Lista
- Bullet

1. Lista
2. Numerata
```

### Keyboard Shortcuts
- `/` = Quick menu
- `Cmd+B` = Bold
- `Cmd+I` = Italic
- `Cmd+K` = Insert link
- `Cmd+Shift+C` = Inline code
- ` ``` ` (triple backtick) = Code block

### Common Issues

**Code blocks non formattati?**
→ Assicurati di avere ` ``` ` all'inizio E fine

**Link rotti?**
→ Verifica che altri articoli siano già pubblicati

**Immagini non caricate?**
→ Questi articoli non hanno immagini embedded
→ Opzionale: aggiungi feature image in Settings

**URL sbagliato?**
→ In Settings → Post URL → Forza URL corretto

---

## 📊 Checklist Post-Pubblicazione

Per ogni articolo pubblicato:

```
□ URL accessibile (test con curl)
□ Formattazione corretta (code blocks, headers)
□ Links interni funzionano
□ Schema.org presente (view source)
□ Meta tags corretti (view source)
□ Mobile-friendly (test su telefono)
□ Hreflang presente (articoli bilingue)
□ Nessun errore 404
□ Velocità caricamento OK
```

### Validators

**Schema.org:**
https://validator.schema.org/
→ Incolla URL articolo
→ Verifica zero errori

**Mobile-Friendly:**
https://search.google.com/test/mobile-friendly
→ Incolla URL articolo
→ Verifica "Mobile-friendly"

**PageSpeed:**
https://pagespeed.web.dev/
→ Incolla URL articolo
→ Target: >90 su mobile/desktop

---

## 🆘 Need Help?

**Ghost Documentation:**
https://ghost.org/docs/

**Ghost Editor Shortcuts:**
https://ghost.org/help/markdown/

**Questions?**
- Ghost Forum: https://forum.ghost.org/
- Ghost Docs: https://ghost.org/docs/publishing/

---

**File pronti! Inizia da 01-getting-started.md 🚀**

Tutto il contenuto è pulito, testato, e pronto per pubblicazione immediata.
