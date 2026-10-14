# SISH: firmware IoT, instalación y límites verificados

Los sketches están separados por dispositivo:

- `arduino-uno/arduino-uno.ino`
- `esp8266-nodemcu/esp8266-nodemcu.ino`
- `esp32-cam/esp32-cam.ino`

La conexión local por teclado sigue funcionando aunque el WiFi o Laravel estén fuera de servicio. La apertura local alterna la puerta con `T`; las órdenes HTTP usan acciones explícitas y no alternan.

## Flujo de puerta y API

El dashboard solicita una apertura autenticada. Laravel persiste una orden `abrir_puerta` como pendiente y devuelve `202 Accepted`; no marca la puerta abierta. El ESP8266 autenticado reclama cada orden una sola vez, fija el servo a 90 grados y confirma si envió el comando. Laravel registra la confirmación electrónica y actualiza el último estado reportado. No existe un sensor de posición de la puerta: `confirmacion_fisica` permanece en `false` y el dashboard lo indica.

Rutas relevantes:

| Método | Ruta                                                    | Autenticación                                                  |
| ------ | ------------------------------------------------------- | -------------------------------------------------------------- |
| `PUT`  | `/api/v1/sistema/armado`                                | Sesión/Sanctum; solo administrador                             |
| `POST` | `/api/v1/sistema/apertura-emergencia`                   | Sesión/Sanctum; solo administrador; requiere `Idempotency-Key` |
| `GET`  | `/api/v1/sistema/ordenes`                               | Sesión/Sanctum; lectura autorizada                             |
| `GET`  | `/api/v1/sistema/ordenes/{uuid}`                        | Sesión/Sanctum; lectura autorizada                             |
| `POST` | `/api/v1/dispositivos/esp8266/telemetria`               | Header `X-Device-Token`                                        |
| `POST` | `/api/v1/dispositivos/esp8266/ordenes/reclamar`         | Header `X-Device-Token`                                        |
| `POST` | `/api/v1/dispositivos/esp8266/ordenes/{uuid}/confirmar` | Header `X-Device-Token`                                        |

La telemetría no acepta cambios del dispositivo a `esta_activado`. Los reportes cada 10 segundos también actualizan el heartbeat. Las órdenes expiradas se descartan; una orden reclamada que no se confirma termina como resultado incierto y no vuelve a entregarse automáticamente.

La apertura de emergencia es una excepción deliberada cuando el servicio ordinario de puerta está detenido. Actualmente solo existe esa acción remota y está permitida exclusivamente al administrador. Desactivar/reanudar el servicio no afecta la apertura local por teclado.

## Preparar Laravel y el ESP8266

1. Configura `.env` con la URL y la base de datos reales de Laravel. Establece `CAMERA_ALLOWED_CIDRS` con el CIDR LAN autorizado, por ejemplo `192.168.1.0/24`; no uses un rango global.
2. Ejecuta `php artisan migrate --force`.
3. Genera y almacena una credencial específica del ESP8266:

    ```powershell
    php artisan device:token esp8266
    ```

    Copia el secreto que se muestra una sola vez. El servidor guarda solo su SHA-256. Para invalidarlo: `php artisan device:token esp8266 --revoke`. Para renovarlo, vuelve a ejecutar el comando y actualiza el firmware antes de reanudar el dispositivo.

4. En el sketch ESP8266 sustituye `TU_SSID`, `TU_PASSWORD`, `FLASH_DEVICE_TOKEN_HERE`, la URL HTTPS de `API_BASE_URL` y `SERVER_CERT_FINGERPRINT`.
5. La API del ESP8266 usa HTTPS y validación por fingerprint del certificado. Instala un certificado TLS que corresponda al endpoint local y copia el fingerprint SHA-1 en el formato que imprime OpenSSL. No sustituyas la validación por `setInsecure()`.
6. El dispositivo intenta reportar telemetría cada 10 segundos y reclamar órdenes cada segundo. Las peticiones HTTP tienen un timeout acotado; el loop continúa atendiendo el serial y el HC-SR04 entre peticiones. La comunicación HTTP sigue siendo síncrona y puede pausar brevemente el loop dentro de ese límite.

### Librerías y placas

- Arduino Uno: `Keypad`; `SoftwareSerial` está incluida con el core AVR.
- ESP8266 NodeMCU: core ESP8266 Arduino, `ArduinoJson`, `LiquidCrystal_I2C`, `Servo`.
- ESP32-CAM AI Thinker: core Arduino-ESP32 con soporte `esp_camera`; habilita PSRAM cuando la placa la tenga.

`CLAVE` en el sketch Uno sigue siendo un valor de ejemplo y debe sustituirse antes de usar la instalación. La clave ya no se imprime en el monitor serial.

## ESP32-CAM: proxy local y acceso de video

El dashboard usa la ruta same-origin `/camera/stream`. El proxy de referencia está en `docker/nginx/sish-camera-proxy.conf`; debe incluirse dentro del `server` Nginx que sirve el mismo dominio que Laravel.

1. Sustituye las dos direcciones `192.168.1.60` por la IP reservada de la ESP32-CAM.
2. Genera un secreto aleatorio de al menos 32 bytes. Pon el mismo valor en `CAMERA_PROXY_TOKEN` del sketch de cámara y en los headers `X-Camera-Token` del proxy Nginx.
3. Cambia las credenciales `TU_SSID` y `TU_PASSWORD` del sketch de la cámara.
4. Instala la ESP32-CAM con IP reservada y no publiques los puertos 80/81 en Internet.
5. Configura firewall/VLAN para aceptar tráfico hacia la ESP32-CAM solo desde el host Nginx. El firmware también rechaza `/` y `/stream` si falta el token de proxy.
6. La subsolicitud `auth_request` valida la sesión Laravel y el controlador exige un IP dentro de `CAMERA_ALLOWED_CIDRS`. Laravel confía el encabezado de IP reenviado solo desde el proxy local `127.0.0.1`; conserva esa dirección de upstream o configura explícitamente la IP privada real de Nginx como proxy de confianza. El navegador solo carga el stream del proxy autenticado; no consume la URL directa de la cámara.
7. Configura TLS en el virtual host Nginx. El firmware ESP32-CAM sirve HTTP solo dentro de la VLAN aislada; no es un endpoint expuesto a Internet.

El archivo Nginx es una plantilla de infraestructura, no una configuración ya desplegada. Hasta instalarla y validar las reglas del router/firewall, la protección end-to-end del video **no está confirmada**. El acceso directo desde otros dispositivos de la LAN también debe quedar bloqueado por firewall; el token del firmware protege las rutas si el bloqueo de red falla.

## Pines y seguridad eléctrica que requieren comprobarse físicamente

Se conserva el cableado del prototipo documentado:

| Componente               | ESP8266 NodeMCU               |
| ------------------------ | ----------------------------- |
| LED verde/rojo/blanco    | D0 / D4 / D3                  |
| HC-SR04 TRIG/ECHO        | D5 / D6                       |
| Servo de puerta          | D7                            |
| LCD I2C SDA/SCL          | D2 / D1                       |
| Serial hacia Arduino Uno | RX/TX de hardware del NodeMCU |

- Arduino Uno TX es de 5 V: coloca divisor o conversor de nivel antes del RX de 3.3 V del ESP8266. El TX de 3.3 V del ESP8266 puede ir al RX del Uno.
- HC-SR04 ECHO normalmente entrega 5 V: usa divisor/conversor de nivel antes de D6.
- Alimenta el servo desde fuente regulada externa adecuada a su corriente de arranque y comparte GND con el NodeMCU. No lo alimentes desde el pin 3.3 V.
- Verifica que el módulo LCD I2C no eleve SDA/SCL a 5 V; usa alimentación a 3.3 V compatible o conversor de nivel.
- D3/GPIO0 y D4/GPIO2 son pines de arranque. La carga del LED blanco y sus resistencias no debe llevar D3 a nivel bajo durante el reset; si impide arrancar, conserva el pin definido en el firmware y corrige el circuito de polarización.
- La ESP32-CAM requiere una fuente estable con margen para picos de WiFi/flash. Se mantiene activa la protección brownout; no se enmascaran caídas de alimentación.

No se dispone de hardware físico en este entorno. Compilación de los sketches, niveles eléctricos, servo, red local, reglas Nginx/firewall, confirmación del movimiento y stream real deben verificarse en los dispositivos instalados.
