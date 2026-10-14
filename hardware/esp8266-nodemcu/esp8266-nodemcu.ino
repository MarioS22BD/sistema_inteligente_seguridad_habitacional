#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Servo.h>

const char* WIFI_SSID = "TU_SSID";
const char* WIFI_PASS = "TU_PASSWORD";
const char* DEVICE_API_TOKEN = "change-me-in-production";
const char* DEVICE_API_URL = "http://192.168.1.50/api/v1/dispositivos/esp8266/estado";

// Pantalla LCD 16x2 con modulo I2C
LiquidCrystal_I2C lcd(0x27, 16, 2);

// Pines del ESP8266 NodeMCU
const int LED_VERDE = D0;
const int LED_ROJO = D4;
const int LED_BLANCO = D3;

const int TRIG = D5;
const int ECHO = D6;

const int SERVO_PUERTA = D7;

// Distancia (cm) a la que se considera que hay alguien
const float DISTANCIA_DETECCION = 50.0;

// Tiempo (ms) que el LED blanco se queda encendido despues de la ultima deteccion
const unsigned long TIEMPO_LED_BLANCO = 10000;

// Servomotor de la puerta
Servo puerta;

// Variables de estado
bool puertaAbierta = false;
int digitos = 0;
unsigned long ultimaDeteccion = 0;
bool ledBlancoEncendido = false;
float ultimaDistanciaCm = 0.0;

bool enviarEstadoDispositivo(bool activado, bool puertaAbierta, bool movimientoDetectado, float distanciaCm) {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  WiFiClient client;
  HTTPClient http;
  String payload = "{\"esta_activado\":" + String(activado ? "true" : "false") +
                   ",\"puerta_abierta\":" + String(puertaAbierta ? "true" : "false") +
                   ",\"movimiento_detectado\":" + String(movimientoDetectado ? "true" : "false") +
                   ",\"distancia_cm\":" + String(distanciaCm, 1) + "}";

  http.begin(client, DEVICE_API_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Token", DEVICE_API_TOKEN);

  int codigo = http.POST(payload);
  bool exito = codigo >= 200 && codigo < 300;
  http.end();

  return exito;
}

void setup() {
  Serial.begin(9600);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
  }

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
  delay(1500);

  pantallaClave();
}

void loop() {
  if (Serial.available() > 0) {
    char cmd = Serial.read();

    switch (cmd) {
      case 'p':
        digitos++;
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
        delay(1500);
        pantallaClave();
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

  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);

  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  unsigned long duracion = pulseIn(ECHO, HIGH, 25000);

  if (duracion > 0) {
    float distancia = duracion * 0.0343 / 2.0;
    ultimaDistanciaCm = distancia;

    if (distancia < DISTANCIA_DETECCION) {
      ultimaDeteccion = millis();
      ledBlancoEncendido = true;
      digitalWrite(LED_BLANCO, HIGH);
    }
  }

  if (ledBlancoEncendido && (millis() - ultimaDeteccion > TIEMPO_LED_BLANCO)) {
    ledBlancoEncendido = false;
    digitalWrite(LED_BLANCO, LOW);
  }

  bool movimientoDetectado = ledBlancoEncendido;
  bool estadoActivado = true;

  enviarEstadoDispositivo(estadoActivado, puertaAbierta, movimientoDetectado, ultimaDistanciaCm);

  delay(50);
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
  for (int i = 0; i < digitos; i++) {
    lcd.print('*');
  }
}
