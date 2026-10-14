#include "esp_camera.h"
#include <WiFi.h>
#include "esp_http_server.h"
#include <cstring>

// ======= CONFIGURACION WiFi =======
const char* WIFI_SSID = "TU_SSID";
const char* WIFI_PASS = "TU_PASSWORD";
const char* CAMERA_PROXY_TOKEN = "REPLACE_WITH_RANDOM_CAMERA_PROXY_TOKEN";
// ================================

// Pines de la camara AI Thinker
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
static const char* STREAM_BOUNDARY = "\r\n--frame\r\n";
static const char* STREAM_PART = "Content-Type: image/jpeg\r\nContent-Length: %u\r\n\r\n";

httpd_handle_t servidor_pagina = NULL;
httpd_handle_t servidor_video = NULL;
unsigned long ultimoIntentoWifi = 0;

bool solicitudAutorizada(httpd_req_t *req) {
  char token[128] = {0};
  size_t tokenLength = httpd_req_get_hdr_value_len(req, "X-Camera-Token");
  if (tokenLength == 0 || tokenLength >= sizeof(token)) return false;
  if (httpd_req_get_hdr_value_str(req, "X-Camera-Token", token, sizeof(token)) != ESP_OK) return false;
  return std::strcmp(token, CAMERA_PROXY_TOKEN) == 0;
}

static esp_err_t pagina_handler(httpd_req_t *req) {
  if (!solicitudAutorizada(req)) {
    return httpd_resp_send_err(req, HTTPD_401_UNAUTHORIZED, "Unauthorized");
  }

  String html = "<!DOCTYPE html><html><head>"
                "<meta name='viewport' content='width=device-width,initial-scale=1'>"
                "<title>Camara SISH</title>"
                "<style>body{margin:0;background:#111;color:#fff;font-family:sans-serif;text-align:center}"
                "img{width:100%;max-width:640px}</style></head><body>"
                "<h2>Camara en vivo</h2>"
                "<img src='/camera/stream'>"
                "</body></html>";

  httpd_resp_set_type(req, "text/html");
  return httpd_resp_send(req, html.c_str(), html.length());
}

static esp_err_t stream_handler(httpd_req_t *req) {
  if (!solicitudAutorizada(req)) {
    return httpd_resp_send_err(req, HTTPD_401_UNAUTHORIZED, "Unauthorized");
  }

  camera_fb_t *fb = NULL;
  esp_err_t res = ESP_OK;
  char part_buf[64];

  res = httpd_resp_set_type(req, STREAM_TYPE);
  if (res != ESP_OK) return res;
  httpd_resp_set_hdr(req, "Cache-Control", "no-store");

  while (true) {
    fb = esp_camera_fb_get();
    if (!fb) {
      res = ESP_FAIL;
    } else {
      size_t hlen = snprintf(part_buf, 64, STREAM_PART, fb->len);
      res = httpd_resp_send_chunk(req, part_buf, hlen);
      if (res == ESP_OK) res = httpd_resp_send_chunk(req, (const char *)fb->buf, fb->len);
      if (res == ESP_OK) res = httpd_resp_send_chunk(req, STREAM_BOUNDARY, strlen(STREAM_BOUNDARY));
      esp_camera_fb_return(fb);
    }
    if (res != ESP_OK) break;
  }

  return res;
}

void iniciarServidores() {
  httpd_config_t config = HTTPD_DEFAULT_CONFIG();

  config.server_port = 80;
  httpd_uri_t uri_pagina = {};
  uri_pagina.uri = "/";
  uri_pagina.method = HTTP_GET;
  uri_pagina.handler = pagina_handler;
  if (httpd_start(&servidor_pagina, &config) != ESP_OK) {
    Serial.println("Error al iniciar servidor HTTP de pagina");
    servidor_pagina = NULL;
  } else if (httpd_register_uri_handler(servidor_pagina, &uri_pagina) != ESP_OK) {
    Serial.println("Error al registrar la pagina de camara");
  }

  config.server_port = 81;
  config.ctrl_port += 1;
  httpd_uri_t uri_stream = {};
  uri_stream.uri = "/stream";
  uri_stream.method = HTTP_GET;
  uri_stream.handler = stream_handler;
  if (httpd_start(&servidor_video, &config) != ESP_OK) {
    Serial.println("Error al iniciar servidor MJPEG");
    servidor_video = NULL;
  } else if (httpd_register_uri_handler(servidor_video, &uri_stream) != ESP_OK) {
    Serial.println("Error al registrar endpoint MJPEG");
  }
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
  WiFi.disconnect(true);
  delay(500);
  WiFi.begin(WIFI_SSID, WIFI_PASS);
  WiFi.setSleep(false);

  Serial.print("Conectando");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();

  iniciarServidores();

  Serial.print("Abre en el navegador: http://");
  Serial.println(WiFi.localIP());
}

void loop() {
  if (WiFi.status() != WL_CONNECTED && millis() - ultimoIntentoWifi >= 10000) {
    ultimoIntentoWifi = millis();
    Serial.println("WiFi desconectado; intentando reconectar");
    WiFi.reconnect();
  }
  delay(100);
}
