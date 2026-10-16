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

1. Configura `.env` con la URL y la base de datos reales de Laravel. Para esta instalación, `CAMERA_ALLOWED_CIDRS` debe incluir la LAN `192.168.137.0/24`; no uses un rango global.
2. Ejecuta `php artisan migrate --force`.
3. Genera y almacena una credencial específica del ESP8266:

    ```powershell
    php artisan device:token esp8266
    ```

    Copia el secreto que se muestra una sola vez. El servidor guarda solo su SHA-256. Para invalidarlo: `php artisan device:token esp8266 --revoke`. Para renovarlo, vuelve a ejecutar el comando y actualiza el firmware antes de reanudar el dispositivo.

4. En el sketch ESP8266 sustituye `TU_SSID`, `TU_PASSWORD` y `FLASH_DEVICE_TOKEN_HERE`. Para esta red compartida, el sketch usa la IP local de la laptop `192.168.137.1:8080`; permite conexiones entrantes al puerto TCP 8080 en el Firewall de Windows.
5. La conexión local de desarrollo usa HTTP sin cifrar. Mantén el puerto 8080 dentro de la LAN y no lo expongas a Internet. Para desplegar fuera de esta red, configura HTTPS y validación del certificado antes de conectar el dispositivo.
6. El dispositivo intenta reportar telemetría cada 10 segundos y reclamar órdenes cada segundo. Las peticiones HTTP tienen un timeout acotado; el loop continúa atendiendo el serial y el HC-SR04 entre peticiones. La comunicación HTTP sigue siendo síncrona y puede pausar brevemente el loop dentro de ese límite.

El HC-SR04 apunta al piso, por lo que una distancia menor a un límite fijo no significa movimiento. Al encender, el ESP8266 promedia 20 mediciones del piso y luego marca proximidad solo si la distancia cambia 15 cm o más respecto de esa referencia. Enciende el sistema con el área despejada; reinícialo si cambia la posición del sensor. La señal de proximidad se mantiene hasta 10 segundos después de la última detección.

### Librerías y placas

- Arduino Uno: `Keypad`; `SoftwareSerial` está incluida con el core AVR.
- ESP8266 NodeMCU: core ESP8266 Arduino, `ArduinoJson`, `LiquidCrystal_I2C`, `Servo`.
- ESP32-CAM AI Thinker: core Arduino-ESP32 con soporte `esp_camera`; habilita PSRAM cuando la placa la tenga.

`CLAVE` en el sketch Uno sigue siendo un valor de ejemplo y debe sustituirse antes de usar la instalación. La clave ya no se imprime en el monitor serial.

## ESP32-CAM: visualización de la página de cámara

El dashboard incrusta directamente la página o URL configurada de la cámara mediante un `iframe`; no descarga ni interpreta el video y no depende de una ruta MJPEG concreta.

1. Configura `VITE_CAMERA_PAGE_URL` en `.env` con la dirección HTTP que ya muestra el video (por ejemplo `http://192.168.137.28/`). Reinicia Vite o vuelve a compilar los assets después de cambiar la variable.
2. El navegador del dispositivo que abre el dashboard debe poder acceder a esa dirección directamente. El contenido de una página externa no necesita CORS para mostrarse en el `iframe`.
3. La página de cámara debe servir el video por sí misma. Si esa dirección solo muestra una página de estado, una imagen fija o un error, el dashboard incrustará ese mismo contenido y no puede convertirlo en video.
4. El acceso directo no queda protegido por la sesión de Laravel: otros equipos de la LAN que conozcan la URL también pueden abrir la cámara. No publiques el puerto HTTP de la cámara en Internet.
5. Para autenticación por sesión, configura el proxy de `docker/nginx/sish-camera-proxy.conf` y usa `/camera/stream` en la interfaz en vez de incrustar directamente la página de cámara.

## Pines y seguridad eléctrica que requieren comprobarse físicamente

Se conserva el cableado del prototipo documentado:

| Componente               | ESP8266 NodeMCU               |
| ------------------------ | ----------------------------- |
| LED verde/rojo/blanco    | D0 / D4 / D3                  |
| HC-SR04 TRIG/ECHO        | D8 / D6                       |
| Servo de puerta          | D7                            |
| LCD I2C SDA/SCL          | D2 / D1                       |
| Serial hacia Arduino Uno | RX/TX de hardware del NodeMCU |

- Arduino Uno TX es de 5 V: coloca divisor o conversor de nivel antes del RX de 3.3 V del ESP8266. El TX de 3.3 V del ESP8266 puede ir al RX del Uno.
- En el sketch del Uno, SoftwareSerial usa D11 como TX hacia RX del ESP8266 y D10 como RX desde TX del ESP8266; conecta también GND entre ambas placas. Desconecta RX/TX entre placas mientras cargas firmware si interfieren con la programación.
- HC-SR04 ECHO normalmente entrega 5 V: usa divisor/conversor de nivel antes de D6.
- D8/GPIO15 debe permanecer en nivel bajo durante el arranque del NodeMCU. El TRIG del HC-SR04 normalmente es una entrada, pero verifica que el módulo no eleve esa línea al encender.
- Alimenta el servo desde fuente regulada externa adecuada a su corriente de arranque y comparte GND con el NodeMCU. No lo alimentes desde el pin 3.3 V.
- Verifica que el módulo LCD I2C no eleve SDA/SCL a 5 V; usa alimentación a 3.3 V compatible o conversor de nivel.
- D3/GPIO0 y D4/GPIO2 son pines de arranque. La carga del LED blanco y sus resistencias no debe llevar D3 a nivel bajo durante el reset; si impide arrancar, conserva el pin definido en el firmware y corrige el circuito de polarización.
- La ESP32-CAM requiere una fuente estable con margen para picos de WiFi/flash. Se mantiene activa la protección brownout; no se enmascaran caídas de alimentación.

No se dispone de hardware físico en este entorno. Compilación de los sketches, niveles eléctricos, servo, red local, reglas Nginx/firewall, confirmación del movimiento y stream real deben verificarse en los dispositivos instalados.
