# Ghost Import Guide - Articoli MQTT Broadcast

Tutti gli articoli pronti per pubblicazione su Ghost.

---

## 📋 Ordine Pubblicazione

### FASE 1: Documentazione (pubblicare PRIMA)
1. ✅ Getting Started (EN)
2. ✅ Configuration Guide (EN)
3. ✅ IoT Tutorial (EN)
4. ✅ IoT Tutorial (IT)

### FASE 2: Annunci (pubblicare DOPO)
5. ✅ Announcement (EN)
6. ✅ Announcement (IT)

---

## 🚀 Metodo Import (2 Opzioni)

### Opzione A: Copy-Paste Manuale (Raccomandato - 5 min/articolo)

**Vantaggi:**
- Controllo totale su formattazione
- Vedi preview mentre copi
- Puoi sistemare al volo
- Più affidabile

**Procedura per ogni articolo:**

1. **Ghost Admin** → New Post
2. **Copia contenuto** da file `ghost-ready/[nome-file].md`
3. **Incolla** in Ghost editor
4. **Click ⚙️ Settings** (sidebar destra)
5. **Copia settings** da sezione articolo sotto
6. **Code Injection** → Incolla Schema.org
7. **Publish!**

---

### Opzione B: Import JSON (Avanzato - tutto in una volta)

**Ghost Admin** → Settings → Labs → Import content

⚠️ **Attenzione:** Import sovrascrive contenuti esistenti!

**File da usare:**
- `ghost-ready/ghost-import-docs.json` (4 articoli docs)
- `ghost-ready/ghost-import-announcements.json` (2 annunci)

**Pro:**
- Veloce (importa tutti in una volta)
- Mantiene metadata

**Contro:**
- Meno controllo
- Difficile sistemare errori
- Possibili problemi formatting

---

## 📄 Articoli + Settings

### 1. Getting Started Guide (EN)

**File contenuto:** `ghost-ready/01-getting-started.md`

**Settings da inserire in Ghost:**

```
URL Slug: mqtt-broadcast-getting-started
Post URL: https://enzolarosa.dev/docs/mqtt-broadcast-getting-started

Title: Getting Started with MQTT Broadcast for Laravel

Excerpt:
Install and configure MQTT Broadcast in your Laravel application in under 5 minutes. This guide covers installation, basic configuration, and your first MQTT message.

Tags (aggiungi questi):
- mqtt-broadcast
- laravel
- getting-started
- tutorial

Meta Data:
Meta Title: Getting Started with MQTT Broadcast | Laravel MQTT Guide
Meta Description: Install and configure MQTT Broadcast in Laravel in 5 minutes. Complete guide with installation steps, configuration, and first message examples.

Twitter Card:
Twitter Title: Getting Started with MQTT Broadcast for Laravel
Twitter Description: Install MQTT in Laravel in 5 minutes. Complete guide with real examples.

Facebook Card:
Facebook Title: Getting Started with MQTT Broadcast for Laravel
Facebook Description: Production-ready MQTT integration for Laravel. Complete installation guide with examples.

Featured: Yes (checkbox)
Feature Image: (opzionale - carica screenshot dashboard)
```

**Code Injection (HEAD):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Getting Started with MQTT Broadcast for Laravel",
  "description": "Install and configure MQTT Broadcast in your Laravel application",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa"
  },
  "datePublished": "2026-01-29",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/docs/mqtt-broadcast-getting-started"
  }
}
</script>
```

---

### 2. Configuration Guide (EN)

**File contenuto:** `ghost-ready/02-configuration-guide.md`

**Settings:**

```
URL Slug: mqtt-broadcast-configuration
Post URL: https://enzolarosa.dev/docs/mqtt-broadcast-configuration

Title: MQTT Broadcast Configuration Guide

Excerpt:
Complete reference for configuring MQTT Broadcast: multiple brokers, environment-specific connections, TLS/SSL, memory management, and advanced options.

Tags:
- mqtt-broadcast
- laravel
- configuration

Meta Title: MQTT Broadcast Configuration Guide | Complete Reference
Meta Description: Complete configuration reference for MQTT Broadcast: brokers, environments, TLS/SSL, memory management, and all advanced options explained.

Twitter Title: MQTT Broadcast Configuration Guide
Twitter Description: Complete config reference: multi-broker, TLS/SSL, environments, and advanced options.

Facebook Title: MQTT Broadcast Configuration Guide
Facebook Description: Complete reference for configuring MQTT Broadcast in Laravel applications.

Featured: No
```

**Code Injection (HEAD):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "MQTT Broadcast Configuration Guide",
  "description": "Complete reference for configuring MQTT Broadcast",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa"
  },
  "datePublished": "2026-01-29",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/docs/mqtt-broadcast-configuration"
  }
}
</script>
```

---

### 3. IoT Temperature Tutorial (EN)

**File contenuto:** `ghost-ready/03-iot-tutorial.md`

**Settings:**

```
URL Slug: iot-temperature-monitoring-laravel-esp32
Post URL: https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32

Title: Build an IoT Temperature Monitoring System with Laravel and ESP32

Excerpt:
Learn how to build a complete IoT temperature monitoring system using Laravel MQTT Broadcast and ESP32. Includes Arduino code, real-time dashboard, email alerts, and production deployment.

Tags:
- mqtt-broadcast
- laravel
- iot
- esp32
- arduino
- tutorial
- temperature-sensor

Meta Title: IoT Temperature Monitoring with Laravel & ESP32 - Complete Tutorial
Meta Description: Build a production-ready IoT temperature monitoring system with Laravel MQTT Broadcast and ESP32. Includes database storage, real-time dashboard, alerts, and Arduino code.

Twitter Title: Build IoT Temperature Monitor with Laravel & ESP32
Twitter Description: Complete tutorial: ESP32 sensors → MQTT → Laravel → Real-time dashboard. Includes Arduino sketch and production deployment.

Facebook Title: Build IoT Temperature Monitor with Laravel & ESP32
Facebook Description: Complete tutorial showing how to build a production-ready IoT system with Laravel and ESP32.

Featured: Yes
```

**Code Injection (HEAD):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Build an IoT Temperature Monitoring System with Laravel and ESP32",
  "description": "Complete tutorial showing how to build a production-ready IoT temperature monitoring system",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa"
  },
  "datePublished": "2026-01-29",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32"
  },
  "proficiencyLevel": "Intermediate",
  "timeRequired": "PT45M"
}
</script>

<link rel="alternate" hreflang="en" href="https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32" />
<link rel="alternate" hreflang="it" href="https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32" />
```

---

### 4. IoT Temperature Tutorial (IT)

**File contenuto:** `ghost-ready/03-IT-iot-tutorial.md`

**Settings:**

```
URL Slug: monitoraggio-temperatura-iot-laravel-esp32
Post URL: https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32

Title: Come Creare un Sistema IoT di Monitoraggio Temperatura con Laravel e ESP32

Excerpt:
Impara a costruire un sistema completo di monitoraggio temperatura IoT usando Laravel MQTT Broadcast e ESP32. Include codice Arduino, dashboard real-time, alert via email e deploy in produzione.

Tags:
- mqtt-broadcast
- laravel
- iot
- esp32
- arduino
- tutorial-italiano
- sensori-temperatura
- italiano

Meta Title: Sistema IoT Monitoraggio Temperatura con Laravel & ESP32 - Tutorial Completo
Meta Description: Costruisci un sistema di monitoraggio temperatura IoT con Laravel MQTT Broadcast ed ESP32. Include database, dashboard real-time, alert e codice Arduino completo.

Twitter Title: Sistema IoT Temperatura: Laravel + ESP32 Tutorial Italiano 🇮🇹
Twitter Description: Tutorial completo: sensori ESP32 → MQTT → Laravel → Dashboard real-time. Include sketch Arduino e deploy in produzione.

Facebook Title: Come Costruire un Sistema IoT con Laravel & ESP32
Facebook Description: Tutorial completo per costruire un sistema IoT di monitoraggio temperatura con Laravel ed ESP32.

Featured: Yes
```

**Code Injection (HEAD):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "Come Creare un Sistema IoT di Monitoraggio Temperatura con Laravel e ESP32",
  "description": "Tutorial completo per costruire un sistema di monitoraggio temperatura IoT",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa"
  },
  "datePublished": "2026-01-29",
  "inLanguage": "it-IT",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32"
  },
  "proficiencyLevel": "Intermedio",
  "timeRequired": "PT45M"
}
</script>

<link rel="alternate" hreflang="it" href="https://enzolarosa.dev/it/tutorials/monitoraggio-temperatura-iot-laravel-esp32" />
<link rel="alternate" hreflang="en" href="https://enzolarosa.dev/tutorials/iot-temperature-monitoring-laravel-esp32" />
```

---

### 5. Package Announcement (EN)

**File contenuto:** `ghost-ready/00-announcement.md`

**Settings:**

```
URL Slug: announcing-mqtt-broadcast-laravel-package
Post URL: https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package

Title: MQTT Broadcast: Production-Ready MQTT Integration for Laravel

Excerpt:
A new Laravel package brings robust MQTT integration with Horizon-style supervisor architecture, multiple broker support, and real-time monitoring. Perfect for IoT, real-time messaging, and industrial automation.

Tags:
- laravel
- mqtt
- package-announcement
- iot
- real-time

Meta Title: Announcing MQTT Broadcast: Production-Ready MQTT for Laravel
Meta Description: New Laravel package for MQTT integration with Horizon-style architecture, auto-reconnection, multiple brokers, and real-time dashboard. Battle-tested with 356 tests.

Twitter Title: New: MQTT Broadcast for Laravel 🚀
Twitter Description: Production-ready MQTT integration for Laravel. Perfect for IoT, real-time messaging, and industrial automation.

Facebook Title: MQTT Broadcast: Bring MQTT to Your Laravel Apps
Facebook Description: Production-ready MQTT package for Laravel. Horizon-style supervisor, multiple brokers, and real-time monitoring dashboard.

Featured: Yes
Feature Image: (carica cover image se disponibile)
```

**Code Injection (HEAD):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "MQTT Broadcast: Production-Ready MQTT Integration for Laravel",
  "description": "Announcing MQTT Broadcast, a new Laravel package for robust MQTT integration",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa"
  },
  "datePublished": "2026-01-29",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package"
  }
}
</script>

<link rel="alternate" hreflang="en" href="https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package" />
<link rel="alternate" hreflang="it" href="https://enzolarosa.dev/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel" />
```

---

### 6. Package Announcement (IT)

**File contenuto:** `ghost-ready/00-IT-announcement.md`

**Settings:**

```
URL Slug: annuncio-mqtt-broadcast-pacchetto-laravel
Post URL: https://enzolarosa.dev/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel

Title: MQTT Broadcast: Integrazione MQTT Production-Ready per Laravel

Excerpt:
Sono felice di presentare MQTT Broadcast, un nuovo pacchetto Laravel per l'integrazione MQTT con architettura Horizon, supporto multi-broker e dashboard real-time. Perfetto per IoT, messaggistica real-time e automazione industriale.

Tags:
- laravel
- mqtt
- pacchetto-laravel
- iot
- real-time
- italiano

Meta Title: Annuncio MQTT Broadcast: MQTT Production-Ready per Laravel
Meta Description: Nuovo pacchetto Laravel per integrazione MQTT con architettura Horizon, auto-reconnection, multi-broker e dashboard real-time. Testato con 356 test.

Twitter Title: Nuovo: MQTT Broadcast per Laravel 🚀
Twitter Description: Integrazione MQTT production-ready per Laravel. Perfetto per IoT, messaggistica real-time e automazione industriale.

Facebook Title: MQTT Broadcast: Porta MQTT nelle Tue App Laravel
Facebook Description: Pacchetto MQTT production-ready per Laravel. Architettura Horizon, multi-broker e dashboard real-time.

Featured: Yes
```

**Code Injection (HEAD):**

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "TechArticle",
  "headline": "MQTT Broadcast: Integrazione MQTT Production-Ready per Laravel",
  "description": "Annuncio di MQTT Broadcast, un nuovo pacchetto Laravel per integrazione MQTT robusta",
  "author": {
    "@type": "Person",
    "name": "Enzo La Rosa",
    "url": "https://enzolarosa.dev/author/enzo"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Enzo La Rosa"
  },
  "datePublished": "2026-01-29",
  "inLanguage": "it-IT",
  "mainEntityOfPage": {
    "@type": "WebPage",
    "@id": "https://enzolarosa.dev/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel"
  }
}
</script>

<link rel="alternate" hreflang="it" href="https://enzolarosa.dev/it/blog/annuncio-mqtt-broadcast-pacchetto-laravel" />
<link rel="alternate" hreflang="en" href="https://enzolarosa.dev/blog/announcing-mqtt-broadcast-laravel-package" />
```

---

## ✅ Checklist Pubblicazione

Per ogni articolo:

```
□ Apri Ghost → New Post
□ Copia contenuto da file ghost-ready/
□ Incolla in editor
□ Verifica formattazione (code blocks, headers, links)
□ Click ⚙️ Settings
□ Copia tutti i settings da sopra
□ Click </> Code Injection
□ Incolla Schema.org + hreflang
□ Preview (verifica su desktop e mobile)
□ Publish!
□ Testa URL finale (deve caricare)
□ Check su mobile
```

---

## 🎯 Ordine Pubblicazione Raccomandato

**GIORNO 1 - Documentazione:**
1. Getting Started (mattina)
2. Configuration Guide (pausa pranzo)
3. IoT Tutorial EN (pomeriggio)
4. IoT Tutorial IT (sera)

**Pausa 1 giorno** → Rileggi tutto, correggi errori

**GIORNO 3 - Lancio:**
5. Announcement EN (mattina presto)
6. Announcement IT (subito dopo)
7. Submit Laravel News
8. Social media blast

---

## 🔧 Troubleshooting

**Problem: Code blocks non formattati**
→ In Ghost editor, seleziona codice e premi ` (backtick) o usa /code

**Problem: Schema.org non appare**
→ Verifica di averlo incollato in Settings → Code Injection → Post Header (non Footer!)

**Problem: Links interni rotti**
→ Assicurati che gli altri articoli siano già pubblicati con URL corretti

**Problem: URL slug diverso**
→ In Ghost Settings → Post URL, forza l'URL esatto come indicato sopra

---

## 📊 Dopo Pubblicazione

**Test finale per ogni articolo:**

```bash
# Verifica caricamento
curl -I https://enzolarosa.dev/docs/mqtt-broadcast-getting-started

# Verifica Schema.org
https://validator.schema.org/
→ Incolla URL articolo
→ Verifica nessun errore

# Verifica mobile
→ Apri su telefono
→ Check leggibilità
→ Code blocks visibili

# Verifica hreflang (per articoli bilingue)
→ View Page Source
→ Cerca <link rel="alternate" hreflang
→ Verifica entrambi i link presenti
```

---

## 💡 Pro Tips

**Ghost Editor Tips:**
- `/` = Menu rapido
- `Cmd+B` = Bold
- `Cmd+K` = Add link
- `Cmd+Shift+C` = Code inline
- `[]()` = Markdown link funziona

**Settings Rapide:**
- Excerpt = max 300 caratteri
- Meta Description = max 160 caratteri
- Tags = max 10, usa quelli esistenti quando possibile
- Featured = Solo articoli importanti

**SEO:**
- URL slug sempre in lowercase
- No spazi (usa -)
- No caratteri speciali (solo lettere, numeri, -)
- Mantieni URL corti ma descriptivi

---

**Pronto per pubblicare! File ghost-ready/ contengono tutto il contenuto. 🚀**
