# ESP32 Temperature Sensor - Arduino Code

Complete Arduino sketch for ESP32 to publish temperature/humidity data via MQTT.

## Hardware Requirements

- **ESP32 DevKit** (or any ESP32 board)
- **DHT22 Temperature/Humidity Sensor** (or DHT11)
- **Wiring:**
  - DHT22 VCC → ESP32 3.3V
  - DHT22 GND → ESP32 GND
  - DHT22 DATA → ESP32 GPIO 4 (D4)

## Arduino Libraries

Install via Library Manager:

```
1. WiFi (built-in)
2. PubSubClient by Nick O'Leary
3. DHT sensor library by Adafruit
4. Adafruit Unified Sensor
5. ArduinoJson by Benoit Blanchon
```

## Complete Sketch

```cpp
#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>

// ========================================
// CONFIGURATION - Edit these values
// ========================================

// WiFi credentials
const char* ssid = "YOUR_WIFI_SSID";
const char* password = "YOUR_WIFI_PASSWORD";

// MQTT Broker
const char* mqtt_server = "192.168.1.100";  // Your Laravel server IP
const int mqtt_port = 1883;
const char* mqtt_user = "";                 // Leave empty if no auth
const char* mqtt_password = "";

// MQTT Topics (include prefix if configured in Laravel)
const char* topic_prefix = "myapp/";        // Match Laravel config prefix
const char* sensor_id = "sensor1";
String full_topic = String(topic_prefix) + "sensors/temp/" + sensor_id;

// Sensor Configuration
#define DHTPIN 4           // GPIO pin connected to DHT sensor
#define DHTTYPE DHT22      // DHT22 (or DHT11)
const char* location = "Office";

// Publishing interval (milliseconds)
const unsigned long publish_interval = 5000;  // 5 seconds

// ========================================
// Global Objects
// ========================================

WiFiClient espClient;
PubSubClient client(espClient);
DHT dht(DHTPIN, DHTTYPE);

unsigned long last_publish = 0;
int failed_readings = 0;

// ========================================
// Setup
// ========================================

void setup() {
  Serial.begin(115200);
  Serial.println("\n\n=================================");
  Serial.println("ESP32 MQTT Temperature Monitor");
  Serial.println("=================================\n");

  // Initialize DHT sensor
  dht.begin();
  Serial.println("✓ DHT sensor initialized");

  // Connect to WiFi
  setup_wifi();

  // Configure MQTT
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqtt_callback);

  Serial.println("\n✓ Setup complete");
  Serial.println("=================================\n");
}

// ========================================
// Main Loop
// ========================================

void loop() {
  // Maintain MQTT connection
  if (!client.connected()) {
    reconnect_mqtt();
  }
  client.loop();

  // Publish sensor data periodically
  unsigned long now = millis();
  if (now - last_publish >= publish_interval) {
    last_publish = now;
    publish_temperature();
  }
}

// ========================================
// WiFi Connection
// ========================================

void setup_wifi() {
  delay(10);
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi connected");
    Serial.print("  IP address: ");
    Serial.println(WiFi.localIP());
    Serial.print("  Signal strength: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    Serial.println("\n✗ WiFi connection failed!");
    Serial.println("  Restarting in 5 seconds...");
    delay(5000);
    ESP.restart();
  }
}

// ========================================
// MQTT Connection
// ========================================

void reconnect_mqtt() {
  int attempts = 0;

  while (!client.connected() && attempts < 5) {
    Serial.print("Connecting to MQTT broker: ");
    Serial.print(mqtt_server);
    Serial.print(":");
    Serial.println(mqtt_port);

    // Generate unique client ID
    String clientId = "ESP32-" + String(sensor_id) + "-" + String(random(0xffff), HEX);

    // Attempt connection
    bool connected;
    if (strlen(mqtt_user) > 0) {
      connected = client.connect(clientId.c_str(), mqtt_user, mqtt_password);
    } else {
      connected = client.connect(clientId.c_str());
    }

    if (connected) {
      Serial.println("✓ MQTT connected");
      Serial.print("  Client ID: ");
      Serial.println(clientId);
      Serial.print("  Publishing to: ");
      Serial.println(full_topic);

      // Subscribe to control topics (optional)
      String control_topic = String(topic_prefix) + "sensors/temp/" + sensor_id + "/control";
      client.subscribe(control_topic.c_str());
      Serial.print("  Subscribed to: ");
      Serial.println(control_topic);

    } else {
      Serial.print("✗ MQTT connection failed, rc=");
      Serial.println(client.state());
      Serial.println("  Error codes:");
      Serial.println("    -4 : Connection timeout");
      Serial.println("    -3 : Connection lost");
      Serial.println("    -2 : Connect failed");
      Serial.println("    -1 : Disconnected");
      Serial.println("     0 : Connected");
      Serial.println("     1 : Bad protocol");
      Serial.println("     2 : Bad client ID");
      Serial.println("     3 : Server unavailable");
      Serial.println("     4 : Bad credentials");
      Serial.println("     5 : Not authorized");

      delay(3000);
      attempts++;
    }
  }

  if (!client.connected()) {
    Serial.println("✗ Failed to connect after 5 attempts");
    Serial.println("  Restarting ESP32...");
    delay(5000);
    ESP.restart();
  }
}

// ========================================
// MQTT Callback (Receive Messages)
// ========================================

void mqtt_callback(char* topic, byte* payload, unsigned int length) {
  Serial.print("Message received on topic: ");
  Serial.println(topic);

  // Convert payload to string
  String message = "";
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }

  Serial.print("  Payload: ");
  Serial.println(message);

  // Handle control commands (optional)
  // Example: {"command": "restart"}
  StaticJsonDocument<200> doc;
  DeserializationError error = deserializeJson(doc, message);

  if (!error) {
    const char* command = doc["command"];
    if (command && strcmp(command, "restart") == 0) {
      Serial.println("  Restarting ESP32...");
      delay(1000);
      ESP.restart();
    }
  }
}

// ========================================
// Publish Temperature Data
// ========================================

void publish_temperature() {
  // Read sensor
  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature();  // Celsius

  // Check if readings are valid
  if (isnan(humidity) || isnan(temperature)) {
    failed_readings++;
    Serial.println("✗ Failed to read from DHT sensor");

    if (failed_readings > 10) {
      Serial.println("✗ Too many failed readings, restarting...");
      delay(2000);
      ESP.restart();
    }
    return;
  }

  failed_readings = 0;  // Reset counter on successful read

  // Create JSON payload
  StaticJsonDocument<200> doc;
  doc["temperature"] = round(temperature * 100) / 100.0;  // Round to 2 decimals
  doc["humidity"] = round(humidity * 100) / 100.0;
  doc["location"] = location;
  doc["sensor_id"] = sensor_id;
  doc["timestamp"] = millis();
  doc["rssi"] = WiFi.RSSI();

  // Serialize to string
  String payload;
  serializeJson(doc, payload);

  // Publish to MQTT
  if (client.publish(full_topic.c_str(), payload.c_str())) {
    Serial.println("✓ Published temperature reading:");
    Serial.print("  Temperature: ");
    Serial.print(temperature);
    Serial.println("°C");
    Serial.print("  Humidity: ");
    Serial.print(humidity);
    Serial.println("%");
    Serial.print("  Payload: ");
    Serial.println(payload);
  } else {
    Serial.println("✗ Failed to publish");
  }
}

// ========================================
// Optional: Deep Sleep Mode
// ========================================

// Uncomment to enable deep sleep (battery powered)
/*
#define DEEP_SLEEP_ENABLED
#define SLEEP_DURATION_SECONDS 60

void publish_and_sleep() {
  publish_temperature();

  Serial.print("Going to deep sleep for ");
  Serial.print(SLEEP_DURATION_SECONDS);
  Serial.println(" seconds...");

  delay(1000);
  esp_deep_sleep(SLEEP_DURATION_SECONDS * 1000000);
}
*/
```

## Configuration Steps

1. **Install ESP32 Board Support:**
   - In Arduino IDE: File → Preferences
   - Add: `https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json`
   - Tools → Board → Boards Manager → Search "ESP32" → Install

2. **Configure WiFi:**
   ```cpp
   const char* ssid = "YourWiFiName";
   const char* password = "YourWiFiPassword";
   ```

3. **Configure MQTT:**
   ```cpp
   const char* mqtt_server = "192.168.1.100";  // Your Laravel server IP
   const char* topic_prefix = "myapp/";         // Match Laravel config
   ```

4. **Upload to ESP32:**
   - Connect ESP32 via USB
   - Tools → Board → ESP32 Dev Module
   - Tools → Port → Select your ESP32 port
   - Click Upload

## Serial Monitor Output

```
=================================
ESP32 MQTT Temperature Monitor
=================================

✓ DHT sensor initialized
Connecting to WiFi: MyNetwork
.....
✓ WiFi connected
  IP address: 192.168.1.50
  Signal strength: -45 dBm

Connecting to MQTT broker: 192.168.1.100:1883
✓ MQTT connected
  Client ID: ESP32-sensor1-A3F2
  Publishing to: myapp/sensors/temp/sensor1
  Subscribed to: myapp/sensors/temp/sensor1/control

✓ Setup complete
=================================

✓ Published temperature reading:
  Temperature: 24.50°C
  Humidity: 62.30%
  Payload: {"temperature":24.5,"humidity":62.3,"location":"Office","sensor_id":"sensor1","timestamp":5234,"rssi":-45}

✓ Published temperature reading:
  Temperature: 24.60°C
  Humidity: 62.10%
  Payload: {"temperature":24.6,"humidity":62.1,"location":"Office","sensor_id":"sensor1","timestamp":10468,"rssi":-46}
```

## Power Optimization (Battery Powered)

For battery-powered sensors, enable deep sleep:

```cpp
#define DEEP_SLEEP_ENABLED
#define SLEEP_DURATION_SECONDS 300  // Wake every 5 minutes

void loop() {
  if (!client.connected()) {
    reconnect_mqtt();
  }

  // Publish once and sleep
  publish_temperature();

  Serial.print("Entering deep sleep for ");
  Serial.print(SLEEP_DURATION_SECONDS);
  Serial.println(" seconds...");

  delay(1000);
  esp_deep_sleep(SLEEP_DURATION_SECONDS * 1000000ULL);
}
```

**Battery Life Estimation:**
- Deep sleep current: ~10 µA
- Active + WiFi: ~160 mA
- Active time: ~2 seconds every 5 minutes
- 18650 battery (3000mAh): ~6 months

## Troubleshooting

**Issue: WiFi won't connect**

```cpp
// Add better error handling
if (WiFi.status() != WL_CONNECTED) {
  Serial.print("WiFi Status: ");
  Serial.println(WiFi.status());
  // WL_NO_SSID_AVAIL = SSID not found
  // WL_CONNECT_FAILED = Wrong password
}
```

**Issue: MQTT connection fails**

1. Check broker is accessible:
   ```bash
   # From another machine on same network
   mosquitto_sub -h 192.168.1.100 -p 1883 -t '#' -v
   ```

2. Check firewall:
   ```bash
   sudo ufw allow 1883
   ```

3. Enable verbose MQTT logging:
   ```cpp
   client.setServer(mqtt_server, mqtt_port);
   client.setKeepAlive(60);
   client.setSocketTimeout(30);
   ```

**Issue: Sensor reads NaN**

- Check wiring (DATA pin to GPIO 4)
- Check sensor power (3.3V, not 5V)
- Add pull-up resistor (4.7kΩ) between DATA and VCC
- Try different GPIO pin

## Alternative Sensors

**DHT11 (cheaper, less accurate):**
```cpp
#define DHTTYPE DHT11
```

**BME280 (more accurate + pressure):**
```cpp
#include <Adafruit_BME280.h>
Adafruit_BME280 bme;

float temperature = bme.readTemperature();
float humidity = bme.readHumidity();
float pressure = bme.readPressure() / 100.0F;
```

**DS18B20 (waterproof):**
```cpp
#include <OneWire.h>
#include <DallasTemperature.h>

OneWire oneWire(4);
DallasTemperature sensors(&oneWire);

sensors.requestTemperatures();
float temperature = sensors.getTempCByIndex(0);
```

## Advanced Features

**1. OTA Updates (Over-The-Air):**

```cpp
#include <ArduinoOTA.h>

void setup() {
  // ... existing setup ...

  ArduinoOTA.setHostname(sensor_id);
  ArduinoOTA.begin();
}

void loop() {
  ArduinoOTA.handle();
  // ... existing loop ...
}
```

**2. Local Web Server (Configuration):**

```cpp
#include <WebServer.h>

WebServer server(80);

void setup() {
  server.on("/", []() {
    String html = "<h1>ESP32 Sensor</h1>";
    html += "<p>Temperature: " + String(dht.readTemperature()) + "°C</p>";
    html += "<p>Uptime: " + String(millis() / 1000) + "s</p>";
    server.send(200, "text/html", html);
  });
  server.begin();
}

void loop() {
  server.handleClient();
}
```

**3. Multiple Sensors:**

```cpp
#define NUM_SENSORS 3
DHT sensors[NUM_SENSORS] = {
  DHT(4, DHT22),
  DHT(5, DHT22),
  DHT(18, DHT22)
};

void publish_all_sensors() {
  for (int i = 0; i < NUM_SENSORS; i++) {
    String topic = String(topic_prefix) + "sensors/temp/sensor" + String(i+1);
    // Read and publish each sensor...
  }
}
```

## Production Checklist

- [ ] Change default sensor_id
- [ ] Set correct location name
- [ ] Configure WiFi credentials
- [ ] Set correct MQTT server IP
- [ ] Match topic_prefix with Laravel config
- [ ] Test MQTT connection
- [ ] Verify data appears in Laravel dashboard
- [ ] Enable watchdog timer (auto-restart on hang)
- [ ] Add status LED for visual feedback
- [ ] Implement exponential backoff for reconnections
- [ ] Add local data buffering for offline operation
- [ ] Set up OTA updates for firmware
- [ ] Document sensor placement and installation

## Resources

- [ESP32 Pinout Reference](https://randomnerdtutorials.com/esp32-pinout-reference-gpios/)
- [PubSubClient Documentation](https://pubsubclient.knolleary.net/)
- [DHT Sensor Guide](https://learn.adafruit.com/dht)
- [Arduino JSON Tutorial](https://arduinojson.org/v6/doc/)
