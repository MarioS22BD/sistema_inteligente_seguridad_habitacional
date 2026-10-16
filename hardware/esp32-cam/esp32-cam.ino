#include "esp_camera.h"
#include <WiFi.h>
#include "esp_http_server.h"

// Configura estos valores localmente y no publiques las credenciales Wi-Fi.
const char* WIFI_SSID = "TU_SSID";
const char* WIFI_PASS = "TU_PASSWORD";

#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

static const char* STREAM_TYPE = "multipart/x-mixed-replace;boundary=frame";
static const char* STREAM_PART =
  "--frame\r\nContent-Type: image/jpeg\r\nContent-Length: %u\r\n\r\n";

httpd_handle_t servidor_pagina = nullptr;
httpd_handle_t servidor_video = nullptr;
unsigned long ultimoIntentoWifi = 0;

esp_err_t paginaHandler(httpd_req_t* req) {
  httpd_resp_set_type(req, "text/plain; charset=utf-8");
  return httpd_resp_send(
    req,
    "ESP32-CAM lista. El video se consume desde el dashboard autenticado.",
    HTTPD_RESP_USE_STRLEN);
}

esp_err_t streamHandler(httpd_req_t* req) {
  esp_err_t result = httpd_resp_set_type(req, STREAM_TYPE);
  if (result != ESP_OK) return result;
  httpd_resp_set_hdr(req, "Cache-Control", "no-store");
  char partHeader[64];
  while (true) {
    camera_fb_t* frame = esp_camera_fb_get();
    if (frame == nullptr) {
      return ESP_FAIL;
    }

    const int headerLength = snprintf(
      partHeader, sizeof(partHeader), STREAM_PART,
      static_cast<unsigned int>(frame->len));
    result = httpd_resp_send_chunk(req, partHeader, headerLength);
    if (result == ESP_OK) {
      result = httpd_resp_send_chunk(
        req, reinterpret_cast<const char*>(frame->buf), frame->len);
    }
    if (result == ESP_OK) {
      result = httpd_resp_send_chunk(req, "\r\n", 2);
    }

    esp_camera_fb_return(frame);
    if (result != ESP_OK) return result;
  }
}

bool iniciarServidores() {
  httpd_config_t paginaConfig = HTTPD_DEFAULT_CONFIG();
  paginaConfig.server_port = 80;
  paginaConfig.ctrl_port = 32768;
  paginaConfig.max_open_sockets = 4;

  if (httpd_start(&servidor_pagina, &paginaConfig) != ESP_OK) {
    Serial.println("Error al iniciar servidor HTTP en puerto 80");
    return false;
  }

  httpd_uri_t paginaUri = {};
  paginaUri.uri = "/";
  paginaUri.method = HTTP_GET;
  paginaUri.handler = paginaHandler;
  if (httpd_register_uri_handler(servidor_pagina, &paginaUri) != ESP_OK) {
    Serial.println("Error al registrar pagina HTTP");
    return false;
  }

  httpd_config_t videoConfig = HTTPD_DEFAULT_CONFIG();
  videoConfig.server_port = 81;
  videoConfig.ctrl_port = 32769;
  videoConfig.max_open_sockets = 4;

  if (httpd_start(&servidor_video, &videoConfig) != ESP_OK) {
    Serial.println("Error al iniciar servidor MJPEG en puerto 81");
    return false;
  }

  httpd_uri_t streamUri = {};
  streamUri.uri = "/stream";
  streamUri.method = HTTP_GET;
  streamUri.handler = streamHandler;
  if (httpd_register_uri_handler(servidor_video, &streamUri) != ESP_OK) {
    Serial.println("Error al registrar endpoint MJPEG");
    return false;
  }

  return true;
}

bool iniciarCamara() {
  camera_config_t config = {};
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sccb_sda = SIOD_GPIO_NUM;
  config.pin_sccb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.pixel_format = PIXFORMAT_JPEG;

  if (psramFound()) {
    config.frame_size = FRAMESIZE_VGA;
    config.jpeg_quality = 12;
    config.fb_count = 2;
    config.fb_location = CAMERA_FB_IN_PSRAM;
    config.grab_mode = CAMERA_GRAB_LATEST;
  } else {
    config.frame_size = FRAMESIZE_QVGA;
    config.jpeg_quality = 15;
    config.fb_count = 1;
  }

  return esp_camera_init(&config) == ESP_OK;
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  if (!iniciarCamara()) {
    Serial.println("Error al iniciar la camara");
    delay(2000);
    ESP.restart();
  }

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASS);

  Serial.print("Conectando a Wi-Fi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();

  if (!iniciarServidores()) {
    Serial.println("No se pudieron iniciar los servidores de camara");
    while (true) delay(1000);
  }

  Serial.print("Wi-Fi conectado. IP actual: ");
  Serial.println(WiFi.localIP());
  Serial.println("MJPEG disponible en puerto 81, ruta /stream");
}

void loop() {
  if (WiFi.status() != WL_CONNECTED &&
      millis() - ultimoIntentoWifi >= 10000) {
    ultimoIntentoWifi = millis();
    Serial.println("Wi-Fi desconectado; intentando reconectar");
    WiFi.reconnect();
  }
  delay(100);
}
