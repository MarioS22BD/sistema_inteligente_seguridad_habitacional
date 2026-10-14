# Firmware de referencia SISH

Este directorio reúne los sketches de referencia para los tres dispositivos del sistema, basados en los archivos proporcionados por el usuario y adaptados sin exponer credenciales reales ni secretos.

## Dispositivos incluidos

- `arduino-uno/arduino-uno.ino`
- `esp8266-nodemcu/esp8266-nodemcu.ino`
- `esp32-cam/esp32-cam.ino`

## Configuración y notas

- La cámara ESP32-CAM mantiene la transmisión MJPEG en puerto 81 con el endpoint `/stream`.
- El Arduino Uno conserva el protocolo serial legado:
  - `p`: tecla presionada
  - `c`: borrar clave
  - `T`: clave correcta
  - `X`: clave incorrecta
- El ESP8266 conserva la lógica del servomotor de la puerta, el LED verde, el LED rojo, el LED blanco y el sensor HC-SR04 como sensor de proximidad/distancia, no como detector PIR.
- Se elimina el control del servomotor de la ventana, como exige la arquitectura de SISH.
- La contraseña del ejemplo se mantiene solo como referencia y debe cambiarse en cada instalación real.
- Las credenciales WiFi se reemplazan por placeholders seguros para evitar exponer datos reales del entorno del usuario.
- El ESP8266 publica datos hacia `POST /api/v1/dispositivos/esp8266/estado` usando el header `X-Device-Token`, con un valor configurado en `DEVICE_API_TOKEN` del entorno Laravel.

## Recomendación de despliegue

Antes de subir estos sketches a hardware real:

1. Revisar la alimentación y la estabilidad del sistema eléctrico.
2. Confirmar los pines físicos de cada módulo y tablero.
3. Sustituir los valores de ejemplo por credenciales de la red local.
4. Probar el flujo completo de apertura/cierre por teclado y la visualización del estado en LCD.
5. Verificar la transmisión HTTP del ESP32-CAM y su acceso desde la red interna.

> Los sketches aquí incluidos son una base de referencia para integración con la aplicación web SISH y no sustituyen la validación final en hardware real.
