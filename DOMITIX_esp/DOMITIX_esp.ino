#include <Keypad.h>
#include "DHT.h"
#include <WiFi.h>
#include <HTTPClient.h>

// ── ✏️ À MODIFIER ─────────────────────────────────────────────────
const char* WIFI_SSID   = "Redmi 10";
const char* WIFI_PASS   = "00000010";
const char* SERVER_IP   = "10.217.171.100";
const char* SERVER_PATH = "/DOMITIX";
// ─────────────────────────────────────────────────────────────────

const char* API_SECRET = "ESP32SECRET";

#define GREEN_PIN  15
#define RED_PIN     2
#define BLUE_PIN    4
#define BUZZ_PIN   23
#define VOLT_PIN   34
#define DHT_PIN    19
#define DHT_TYPE   DHT11

DHT dht(DHT_PIN, DHT_TYPE);

#define PWM_FREQ       5000
#define PWM_RESOLUTION 8

const byte ROWS = 4, COLS = 4;
char keys[ROWS][COLS] = {
  {'1','2','3','A'}, {'4','5','6','B'},
  {'7','8','9','C'}, {'*','0','#','D'}
};
byte rowPins[ROWS] = {32, 33, 25, 26};
byte colPins[COLS]  = {27, 14, 12, 13};
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

String doorCode  = "1234";
String inputCode = "";
int    attempts  = 0;
bool   isLocked  = false;
unsigned long lockStart = 0, lockDuration = 0;
String susCode = "";
int    sameSus = 0;

bool buzzerOn = false;
unsigned long buzzStart = 0;
#define BUZZ_DURATION 2000

float temperature = NAN, humidity = NAN, voltage = 0.0;

unsigned long lastSensor = 0;
unsigned long lastPush   = 0;
unsigned long lastPoll   = 0;
unsigned long lastBlink  = 0;
#define T_SENSOR  2000
#define T_PUSH   10000
#define T_POLL    1000

String currentBlueLevel = "off";

// ════════════════════════════════════════════════════════════════
String buildURL(String endpoint) {
  return "http://" + String(SERVER_IP) + String(SERVER_PATH) + "/" + endpoint;
}

void setBlueLED(String level) {
  if (level == currentBlueLevel) return;
  int duty = 0;
  if      (level == "low")    duty = 85;
  else if (level == "medium") duty = 170;
  else if (level == "high")   duty = 255;
  ledcWrite(BLUE_PIN, duty);
  currentBlueLevel = level;
  Serial.println("LED bleue: " + level + " PWM=" + String(duty));
}

void setupWiFi() {
  Serial.print("Connexion WiFi");
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  int t = 0;
  while (WiFi.status() != WL_CONNECTED && t < 30) {
    delay(500); Serial.print("."); t++;
  }
  if (WiFi.status() == WL_CONNECTED)
    Serial.println("\nWiFi OK: " + WiFi.localIP().toString());
  else
    Serial.println("\nWiFi ECHEC!");
}

void pushData(String event, String stat, String codeUsed,
              int locked, int lockRemain, int att) {
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  http.begin(buildURL("fetch_data.php?action=push"));
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(2000);
  String body = "key=" + String(API_SECRET);
  body += "&temperature=" + (isnan(temperature) ? String("") : String(temperature, 2));
  body += "&humidity="    + (isnan(humidity)    ? String("") : String(humidity, 2));
  body += "&voltage="     + String(voltage, 3);
  body += "&is_locked="   + String(locked);
  body += "&lock_remain=" + String(lockRemain);
  body += "&attempts="    + String(att);
  if (event.length())    body += "&event="  + event;
  if (stat.length())     body += "&status=" + stat;
  if (codeUsed.length()) body += "&code="   + codeUsed;
  int resp = http.POST(body);
  Serial.println(resp == 200 ? "Push OK" : "Push FAIL HTTP " + String(resp));
  http.end();
}

void pollCommands() {
  if (WiFi.status() != WL_CONNECTED) return;
  HTTPClient http;
  String url = buildURL("fetch_data.php?action=get_commands&key=") + String(API_SECRET);
  http.begin(url);
  http.setTimeout(2000);
  int httpCode = http.GET();
  if (httpCode == 200) {
    String p = http.getString();
    Serial.println("CMD: " + p);

    digitalWrite(GREEN_PIN, p.indexOf("\"led_green\":1") >= 0 ? HIGH : LOW);
    digitalWrite(RED_PIN,   p.indexOf("\"led_red\":1")   >= 0 ? HIGH : LOW);

    if (p.indexOf("\"alarm_active\":1") >= 0 && !buzzerOn) {
      digitalWrite(BUZZ_PIN, HIGH);
      buzzerOn = true;
      buzzStart = millis();
      Serial.println("BUZZER ON");
    }

    if      (p.indexOf("\"led_blue_level\":\"high\"")   >= 0) setBlueLED("high");
    else if (p.indexOf("\"led_blue_level\":\"medium\"") >= 0) setBlueLED("medium");
    else if (p.indexOf("\"led_blue_level\":\"low\"")    >= 0) setBlueLED("low");
    else if (p.indexOf("\"led_blue_level\":\"off\"")    >= 0) setBlueLED("off");

    // Mise à jour code porte
    int idx = p.indexOf("\"door_code\":\"");
    if (idx >= 0) {
      int s = idx + 13;
      int e = p.indexOf("\"", s);
      if (e > s) {
        String nc = p.substring(s, e);
        if (nc.length() >= 4 && nc != doorCode) {
          doorCode = nc;
          Serial.println("NOUVEAU CODE: " + doorCode);
        }
      }
    }
  } else {
    Serial.println("Poll FAIL HTTP " + String(httpCode));
  }
  http.end();
}

// ════════════════════════════════════════════════════════════════
void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(GREEN_PIN, OUTPUT);
  pinMode(RED_PIN,   OUTPUT);
  pinMode(BUZZ_PIN,  OUTPUT);
  pinMode(VOLT_PIN,  INPUT);
  digitalWrite(GREEN_PIN, LOW);
  digitalWrite(RED_PIN,   LOW);
  digitalWrite(BUZZ_PIN,  LOW);

  ledcAttach(BLUE_PIN, PWM_FREQ, PWM_RESOLUTION);
  ledcWrite(BLUE_PIN, 0);

  dht.begin();

  Serial.println("================================");
  Serial.println("  ESP32 SecurePanel v3.1");
  Serial.println("  Code: " + doorCode);
  Serial.println("================================");

  setupWiFi();
  delay(300);
  pollCommands(); // Premier poll pour récupérer le code
  Serial.println("Pret — entrez code:");
}

// ════════════════════════════════════════════════════════════════
void loop() {
  unsigned long now = millis();

  // Buzzer timeout
  if (buzzerOn && now - buzzStart >= BUZZ_DURATION) {
    digitalWrite(BUZZ_PIN, LOW);
    buzzerOn = false;
    Serial.println("Buzzer OFF");
  }

  // Lockout — sans goto, utilise un flag
  if (isLocked) {
    long remain = (long)(lockDuration - (now - lockStart)) / 1000;
    if (remain > 0) {
      if (now - lastBlink >= 1000) {
        lastBlink = now;
        Serial.println("BLOQUE " + String(remain) + "s");
      }
      digitalWrite(RED_PIN, (now / 500) % 2 == 0 ? HIGH : LOW);
    } else {
      isLocked = false;
      digitalWrite(RED_PIN, LOW);
      Serial.println("DEBLOQUE");
    }
  } else {
    // Keypad — seulement si pas bloqué
    char key = keypad.getKey();
    if (key) {
      if (key == '*') {
        inputCode = "";
        Serial.println("Reset");
      } else {
        inputCode += key;
        Serial.print("*");
        delay(150);
      }
    }

    if (inputCode.length() == 4) {
      Serial.println("\nCode: " + inputCode);
      if (inputCode == doorCode) {
        digitalWrite(GREEN_PIN, HIGH);
        Serial.println("ACCES ACCORDE");
        delay(800);
        digitalWrite(GREEN_PIN, LOW);
        attempts = 0; sameSus = 0;
        pushData("Keypad Entry", "success", inputCode, 0, 0, 0);
      } else {
        attempts++;
        digitalWrite(BUZZ_PIN, HIGH);
        buzzStart = millis(); buzzerOn = true;
        Serial.println("MAUVAIS CODE tentative " + String(attempts));

        if (inputCode == susCode) sameSus++;
        else { sameSus = 1; susCode = inputCode; }

        if      (sameSus >= 3)  lockDuration = 60000;
        else if (attempts == 1) lockDuration = 15000;
        else if (attempts == 2) lockDuration = 30000;
        else { lockDuration = 60000; attempts = 0; }

        Serial.println("BLOQUE " + String(lockDuration/1000) + "s");
        lockStart = millis(); isLocked = true;
        pushData("Keypad Entry", "fail", inputCode, 1, lockDuration/1000, attempts);
      }
      inputCode = "";
    }
  }

  // Capteurs toutes les 2s
  if (now - lastSensor >= T_SENSOR) {
    lastSensor = now;
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    if (!isnan(t) && !isnan(h)) {
      temperature = t; humidity = h;
      Serial.printf("Temp:%.1fC Hum:%.1f%%\n", temperature, humidity);
    } else {
      Serial.println("DHT: pas de lecture");
    }
    int raw = analogRead(VOLT_PIN);
    voltage = raw * (3.3f / 4095.0f);
    Serial.printf("Tension:%.3fV raw=%d\n", voltage, raw);
  }

  // Envoi toutes les 10s
  if (now - lastPush >= T_PUSH) {
    lastPush = now;
    int rem = isLocked ? max(0L, (long)(lockDuration-(now-lockStart))/1000) : 0;
    pushData("", "", "", isLocked?1:0, rem, attempts);
  }

  // Poll toutes les 1s
  if (now - lastPoll >= T_POLL) {
    lastPoll = now;
    pollCommands();
  }
}
