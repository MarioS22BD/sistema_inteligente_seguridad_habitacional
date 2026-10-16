<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const cameraPageUrl =
    import.meta.env.VITE_CAMERA_PAGE_URL ?? 'http://192.168.137.28/';
const streamUrl = new URL(cameraPageUrl);
streamUrl.port = '81';
streamUrl.pathname = '/stream';
streamUrl.search = '';
const retryKey = ref(0);
const streamStarted = ref(false);
const failed = ref(false);

const reconnect = (): void => {
    streamStarted.value = false;
    failed.value = false;
    retryKey.value += 1;
};
</script>

<template>
    <Card class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between gap-3">
            <CardTitle class="text-lg">Cámara ESP32-CAM</CardTitle>
            <span
                :class="[
                    'rounded-full px-2.5 py-1 text-xs font-medium',
                    streamStarted
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-800',
                ]"
                role="status"
            >
                {{
                    failed
                        ? 'Stream no disponible'
                        : streamStarted
                          ? 'Video en vivo'
                          : 'Conectando'
                }}
            </span>
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                class="relative aspect-video overflow-hidden rounded-md bg-slate-950"
            >
                <img
                    :key="retryKey"
                    :src="`${streamUrl.toString()}?reconnect=${retryKey}`"
                    alt="Video en vivo de la cámara ESP32-CAM"
                    class="h-full w-full object-contain"
                    @load="
                        streamStarted = true;
                        failed = false;
                    "
                    @error="
                        streamStarted = false;
                        failed = true;
                    "
                />
                <div
                    v-if="failed"
                    class="absolute inset-0 flex items-center justify-center bg-slate-950/90 p-5 text-center text-sm text-white"
                    role="alert"
                >
                    No se recibe video desde
                    {{ streamUrl.origin }}/stream. Comprueba que la cámara
                    transmita MJPEG por el puerto 81.
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    Conexión directa al stream de la cámara por la red local; no
                    pasa por el proxy de autenticación.
                </p>
                <Button variant="outline" size="sm" @click="reconnect">
                    Reconectar
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
