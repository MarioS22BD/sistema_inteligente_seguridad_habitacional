#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Servo.h>
#include <math.h>

const char* WIFI_SSID = "TU_SSID";
const char* WIFI_PASS = "TU_PASSWORD";
const char* DEVICE_API_TOKEN = "FLASH_DEVICE_TOKEN_HERE";
const char* API_BASE_URL = "http://192.168.137.1:8080";

const unsigned long WIFI_RETRY_MS = 10000;
const unsigned long TELEMETRY_INTERVAL_MS = 10000;
const unsigned long ORDER_POLL_INTERVAL_MS = 1000;
const unsigned long SENSOR_INTERVAL_MS = 50;
const unsigned long LED_HOLD_MS = 10000;
const uint8_t FLOOR_CALIBRATION_SAMPLES = 20;
const float DISTANCE_CHANGE_DETECTION_CM = 15.0;
const uint16_t HTTP_TIMEOUT_MS = 1500;

LiquidCrystal_I2C lcd(0x27, 16, 2);

const int LED_VERDE = D0;
const int LED_ROJO = D4;
const int LED_BLANCO = D3;
const int TRIG = D8;
const int ECHO = D6;
const int SERVO_PUERTA = D7;

IPAddress deviceIp(192, 168, 137, 50);
IPAddress gatewayIp(192, 168, 137, 1);
IPAddress subnetMask(255, 255, 255, 0);
IPAddress dnsIp(8, 8, 8, 8);

Servo puerta;
bool puertaAbierta = false;
bool ledBlancoEncendido = false;
bool distanciaDisponible = false;
bool pisoCalibrado = false;
bool telemetriaPendiente = true;
int digitos = 0;
float ultimaDistanciaCm = 0.0;
float sumaCalibracionPiso = 0.0;
float distanciaPisoCm = 0.0;
uint8_t muestrasCalibracionPiso = 0;
unsigned long ultimaDeteccion = 0;
unsigned long ultimoWifiIntento = 0;
unsigned long ultimaTelemetria = 0;
unsigned long ultimoIntentoTelemetria = 0;
unsigned long ultimaConsultaOrden = 0;
unsigned long ultimaMedicion = 0;

String rutaApi(const char* ruta) {
  return String(API_BASE_URL) + ruta;
}

bool iniciarPeticion(HTTPClient& http, WiFiClient& client, const char* ruta) {
  if (WiFi.status() != WL_CONNECTED) return false;

  http.setTimeout(HTTP_TIMEOUT_MS);
  if (!http.begin(client, rutaApi(ruta))) return false;
  http.addHeader("X-Device-Token", DEVICE_API_TOKEN);
  return true;
}

bool reportarTelemetria() {
  if (WiFi.status() != WL_CONNECTED) return false;

  WiFiClient client;
  HTTPClient http;
  if (!iniciarPeticion(http, client, "/api/v1/dispositivos/esp8266/telemetria")) {
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  String payload = "{\"puerta_abierta\":";
  payload += puertaAbierta ? "true" : "false";
  payload += ",\"movimiento_detectado\":";
  payload += ledBlancoEncendido ? "true" : "false";
  payload += ",\"distancia_cm\":";
  payload += distanciaDisponible ? String(ultimaDistanciaCm, 1) : "null";
  payload += "}";

  int status = http.POST(payload);
  http.end();
  if (status < 200 || status >= 300) return false;

  ultimaTelemetria = millis();
  telemetriaPendiente = false;
  return true;
}

bool confirmarOrden(const String& id, bool ejecutada, const String& error) {
  WiFiClient client;
  HTTPClient http;
  String path = "/api/v1/dispositivos/esp8266/ordenes/" + id + "/confirmar";
  if (!iniciarPeticion(http, client, path.c_str())) return false;

  http.addHeader("Content-Type", "application/json");
  String payload = "{\"ejecutada\":";
  payload += ejecutada ? "true" : "false";
  payload += ",\"puerta_abierta\":";
  payload += puertaAbierta ? "true" : "false";
  if (error.length() > 0) {
    payload += ",\"error\":\"";
    payload += error;
    payload += "\"";
  }
  payload += "}";

  int status = http.POST(payload);
  http.end();
  if (status < 200 || status >= 300) return false;

  telemetriaPendiente = true;
  return true;
}

void ejecutarOrden(const String& id, const String& accion) {
  if (accion != "abrir_puerta") {
    confirmarOrden(id, false, "Acción no admitida");
    return;
  }

  // Este comando es idempotente: siempre fija la posición de apertura, nunca alterna.
  puertaAbierta = true;
  puerta.write(90);
  digitalWrite(LED_VERDE, HIGH);
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Orden recibida");
  lcd.setCursor(0, 1);
  lcd.print("Abriendo puerta");

  delay(400);
  bool confirmada = confirmarOrden(id, true, "");
  if (!confirmada) {
    // El servidor no vuelve a entregar órdenes reclamadas; el resultado queda incierto.
    telemetriaPendiente = true;
  }
  pantallaClave();
}

void reclamarOrden() {
  WiFiClient client;
  HTTPClient http;
  if (!iniciarPeticion(http, client, "/api/v1/dispositivos/esp8266/ordenes/reclamar")) {
    return;
  }

  int status = http.POST("");
  if (status != HTTP_CODE_OK) {
    http.end();
    return;
  }

  DynamicJsonDocument response(384);
  DeserializationError jsonError = deserializeJson(response, http.getString());
  http.end();
  if (jsonError) return;

  JsonVariantConst data = response["data"];
  if (data.isNull()) return;

  const char* commandId = data["id"] | "";
  const char* action = data["action"] | "";
  if (commandId[0] == '\0') return;
  ejecutarOrden(String(commandId), String(action));
}

void conectarWifiSinBloquear() {
  unsigned long ahora = millis();
  if (WiFi.status() == WL_CONNECTED || ahora - ultimoWifiIntento < WIFI_RETRY_MS) {
    return;
  }

  ultimoWifiIntento = ahora;
  WiFi.begin(WIFI_SSID, WIFI_PASS);
}

void manejarTecladoSerial() {
  while (Serial.available() > 0) {
    char cmd = (char)Serial.read();

    switch (cmd) {
      case 'p':
        if (digitos < 8) digitos++;
        pantallaClave();
        break;

      case 'c':
        digitos = 0;
        pantallaClave();
        break;

      case 'T':
        digitos = 0;
        puertaAbierta = !puertaAbierta;
        puerta.write(puertaAbierta ? 90 : 0);
        digitalWrite(LED_VERDE, puertaAbierta ? HIGH : LOW);
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Clave correcta");
        lcd.setCursor(0, 1);
        lcd.print(puertaAbierta ? "Puerta ABIERTA" : "Puerta CERRADA");
        telemetriaPendiente = true;
        pantallaTrasPausa();
        break;

      case 'X':
        digitos = 0;
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Clave incorrecta");
        parpadearRojo(3);
        pantallaClave();
        break;
    }
  }
}

void medirDistancia() {
  unsigned long ahora = millis();
  if (ahora - ultimaMedicion < SENSOR_INTERVAL_MS) return;
  ultimaMedicion = ahora;

  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  unsigned long duracion = pulseIn(ECHO, HIGH, 25000);
  bool previousDistanceAvailable = distanciaDisponible;
  bool previousProximity = ledBlancoEncendido;
  distanciaDisponible = duracion > 0;
  if (distanciaDisponible) {
    ultimaDistanciaCm = duracion * 0.0343 / 2.0;

    if (!pisoCalibrado) {
      sumaCalibracionPiso += ultimaDistanciaCm;
      muestrasCalibracionPiso++;

      if (muestrasCalibracionPiso >= FLOOR_CALIBRATION_SAMPLES) {
        distanciaPisoCm = sumaCalibracionPiso / muestrasCalibracionPiso;
        pisoCalibrado = true;
      }
    } else if (fabsf(ultimaDistanciaCm - distanciaPisoCm) >= DISTANCE_CHANGE_DETECTION_CM) {
      ultimaDeteccion = ahora;
      ledBlancoEncendido = true;
      digitalWrite(LED_BLANCO, HIGH);
    }
  }

  if (ledBlancoEncendido && ahora - ultimaDeteccion > LED_HOLD_MS) {
    ledBlancoEncendido = false;
    digitalWrite(LED_BLANCO, LOW);
  }

  if (previousDistanceAvailable != distanciaDisponible
      || previousProximity != ledBlancoEncendido) {
    telemetriaPendiente = true;
  }
}

void setup() {
  Serial.begin(9600);
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.config(deviceIp, gatewayIp, subnetMask, dnsIp);

  Wire.begin(D2, D1);
  lcd.init();
  lcd.backlight();

  pinMode(LED_VERDE, OUTPUT);
  pinMode(LED_ROJO, OUTPUT);
  pinMode(LED_BLANCO, OUTPUT);
  pinMode(TRIG, OUTPUT);
  pinMode(ECHO, INPUT);
  digitalWrite(TRIG, LOW);

  puerta.attach(SERVO_PUERTA);
  puerta.write(0);
  digitalWrite(LED_VERDE, LOW);
  digitalWrite(LED_ROJO, LOW);
  digitalWrite(LED_BLANCO, LOW);

  lcd.setCursor(0, 0);
  lcd.print("Sistema SISH");
  lcd.setCursor(0, 1);
  lcd.print("Iniciando...");
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  pantallaTrasPausa();
  pantallaClave();
}

void loop() {
  manejarTecladoSerial();
  medirDistancia();
  conectarWifiSinBloquear();

  unsigned long ahora = millis();
  if (WiFi.status() == WL_CONNECTED) {
    if ((telemetriaPendiente && ahora - ultimoIntentoTelemetria >= 200)
        || ahora - ultimaTelemetria >= TELEMETRY_INTERVAL_MS) {
      ultimoIntentoTelemetria = ahora;
      reportarTelemetria();
    }

    if (ahora - ultimaConsultaOrden >= ORDER_POLL_INTERVAL_MS) {
      ultimaConsultaOrden = ahora;
      reclamarOrden();
    }
  }

  delay(5);
}

void parpadearRojo(int veces) {
  for (int i = 0; i < veces; i++) {
    digitalWrite(LED_ROJO, HIGH);
    delay(250);
    digitalWrite(LED_ROJO, LOW);
    delay(250);
  }
}

void pantallaClave() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(puertaAbierta ? "Puerta: ABIERTA" : "Puerta: CERRADA");
  lcd.setCursor(0, 1);
  lcd.print("Clave: ");
  for (int i = 0; i < digitos; i++) lcd.print('*');
}

void pantallaTrasPausa() {
  delay(500);
}
