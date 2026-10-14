<script setup lang="ts">
import { useIntervalFn } from '@vueuse/core';
import { onMounted, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const streamUrl = '/camera/stream';
const retryKey = ref(0);
const connected = ref(false);
const streamStarted = ref(false);
const failed = ref(false);
let healthRequest: AbortController | null = null;

const checkConnection = async (): Promise<void> => {
    healthRequest?.abort();
    healthRequest = new AbortController();
    const timeout = window.setTimeout(() => healthRequest?.abort(), 3000);

    try {
        const response = await fetch('/camera/health', {
            credentials: 'same-origin',
            cache: 'no-store',
            signal: healthRequest.signal,
        });
        connected.value = response.ok;
        if (!response.ok) streamStarted.value = false;
    } catch {
        connected.value = false;
        streamStarted.value = false;
    } finally {
        window.clearTimeout(timeout);
    }
};

const { pause } = useIntervalFn(() => void checkConnection(), 10000, {
    immediateCallback: false,
});

const reconnect = (): void => {
    connected.value = false;
    streamStarted.value = false;
    failed.value = false;
    retryKey.value += 1;
    void checkConnection();
};

onMounted(() => void checkConnection());

onUnmounted(() => {
    connected.value = false;
    pause();
    healthRequest?.abort();
});
</script>

<template>
    <Card class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm">
        <CardHeader class="flex flex-row items-center justify-between gap-3">
            <CardTitle class="text-lg">Cámara ESP32-CAM</CardTitle>
            <span
                :class="[
                    'rounded-full px-2.5 py-1 text-xs font-medium',
                    connected
                        ? 'bg-emerald-50 text-emerald-700'
                        : 'bg-amber-50 text-amber-800',
                ]"
                role="status"
            >
                {{
                    failed
                        ? 'Error de transmisión'
                        : streamStarted && connected
                          ? 'Stream iniciado'
                          : connected
                            ? 'Cámara accesible'
                            : 'Desconectada'
                }}
            </span>
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                class="relative aspect-video overflow-hidden rounded-md bg-slate-950"
            >
                <img
                    :key="retryKey"
                    :src="`${streamUrl}?reconnect=${retryKey}`"
                    alt="Transmisión local en vivo de la cámara de seguridad"
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
                    No se pudo abrir el stream. Compruebe la cámara y el proxy
                    local autorizado.
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-slate-500">
                    La transmisión requiere el proxy local autenticado y
                    restringido por red; su pérdida no indica que el sistema
                    esté seguro.
                </p>
                <Button variant="outline" size="sm" @click="reconnect">
                    Reconectar
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
