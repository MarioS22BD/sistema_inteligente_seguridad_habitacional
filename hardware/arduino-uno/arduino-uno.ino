#include <Keypad.h>
#include <SoftwareSerial.h>

// Comunicacion con el ESP8266: RX (sin usar) = 10, TX = 11
SoftwareSerial esp(10, 11);

// Configuracion del teclado matricial 4x4
const byte FILAS = 4;
const byte COLUMNAS = 4;

char teclas[FILAS][COLUMNAS] = {
  {'1', '2', '3', 'A'},
  {'4', '5', '6', 'B'},
  {'7', '8', '9', 'C'},
  {'*', '0', '#', 'D'}
};

// Pines del teclado (corregidos: el conector esta invertido)
byte pinesFilas[FILAS] = {9, 8, 7, 6};
byte pinesColumnas[COLUMNAS] = {5, 4, 3, 2};

Keypad teclado = Keypad(
  makeKeymap(teclas),
  pinesFilas,
  pinesColumnas,
  FILAS,
  COLUMNAS
);

// Contraseña de referencia. Cambiar por la configuración real del sitio.
const String CLAVE = "1234";
String ingresada = "";

void setup() {
  Serial.begin(9600);
  esp.begin(9600);

  Serial.println("Teclado listo");
}

void loop() {
  char tecla = teclado.getKey();

  if (tecla) {
    if (tecla == '#') {
      if (ingresada == CLAVE) {
        esp.write('T');
        Serial.println("-> Clave correcta");
      } else {
        esp.write('X');
        Serial.println("-> Clave incorrecta");
      }

      ingresada = "";
    }
    else if (tecla == '*') {
      ingresada = "";
      esp.write('c');
      Serial.println("-> Borrado");
    }
    else if (ingresada.length() < 8) {
      ingresada += tecla;
      esp.write('p');
    }
  }
}
