# GitHub Topics Configuration

Add these topics to improve discoverability on GitHub and search engines.

## Recommended Topics (Max 20)

**Primary Topics:**
```
laravel
mqtt
laravel-package
iot
real-time
```

**Technology Topics:**
```
mqtt-client
laravel-mqtt
supervisor
horizon
php
mqtt-broker
```

**Hardware/IoT Topics:**
```
mosquitto
esp32
arduino
raspberry-pi
```

**Use Case Topics:**
```
websockets
iot-platform
industry-40
smart-home
automation
```

## How to Add Topics

### Option 1: Via GitHub Web Interface

1. Go to: https://github.com/enzolarosa/mqtt-broadcast
2. Click "⚙️" (Settings icon) next to "About" section
3. Add topics in the "Topics" field
4. Save changes

### Option 2: Via GitHub CLI

```bash
gh repo edit enzolarosa/mqtt-broadcast \
  --add-topic laravel \
  --add-topic mqtt \
  --add-topic laravel-package \
  --add-topic iot \
  --add-topic real-time \
  --add-topic mqtt-client \
  --add-topic laravel-mqtt \
  --add-topic supervisor \
  --add-topic horizon \
  --add-topic php \
  --add-topic mqtt-broker \
  --add-topic mosquitto \
  --add-topic esp32 \
  --add-topic arduino \
  --add-topic websockets
```

### Option 3: Via GitHub API

```bash
curl -X PUT \
  -H "Accept: application/vnd.github+json" \
  -H "Authorization: Bearer YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/enzolarosa/mqtt-broadcast/topics \
  -d '{
    "names": [
      "laravel",
      "mqtt",
      "laravel-package",
      "iot",
      "real-time",
      "mqtt-client",
      "laravel-mqtt",
      "supervisor",
      "horizon",
      "php",
      "mqtt-broker",
      "mosquitto",
      "esp32",
      "arduino",
      "websockets"
    ]
  }'
```

## SEO Impact

These topics help with:
- **GitHub Search**: Better ranking for "laravel mqtt", "iot laravel", etc.
- **Google Search**: Topics appear in meta tags and improve SEO
- **Related Repositories**: Increases discoverability via GitHub's recommendation system
- **Awesome Lists**: Makes it easier for curators to find and list the package

## Monitoring

Check topic effectiveness:
- GitHub Insights → Traffic → Referring sites
- Search "topic:laravel+mqtt" on GitHub
- Google Search Console (if configured)
