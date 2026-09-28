<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, BellRing, Clock3 } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Alertas', href: '/alertas' },
        ],
    },
});

interface EventoApi {
    id: number;
    tipo_evento: string;
    descripcion: string;
    gravedad: string;
    fecha_creacion: string | null;
}

const loading = ref(true);
const eventos = ref<EventoApi[]>([]);
const error = ref<string | null>(null);

const formatDate = (value: string | null | undefined) => {
    if (!value) return 'Sin información disponible';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Sin información disponible';

    return new Intl.DateTimeFormat('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const severityColor = (gravedad: string) => {
    switch (gravedad) {
        case 'critico':
            return 'bg-red-500/10 text-red-700 border-red-200';
        case 'advertencia':
            return 'bg-amber-500/10 text-amber-700 border-amber-200';
        default:
            return 'bg-sky-500/10 text-sky-700 border-sky-200';
    }
};

onMounted(async () => {
    try {
        const response = await fetch('/api/v1/eventos', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el historial de alertas.');
        }

        const payload = await response.json();
        eventos.value = Array.isArray(payload.data) ? payload.data : [];
    } catch (err) {
        error.value =
            err instanceof Error
                ? err.message
                : 'No se pudo cargar el historial de eventos.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Alertas" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-blue-600">
                    Alertas
                </p>
                <h1 class="text-3xl font-semibold text-slate-900">Historial de eventos</h1>
            </div>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700">
            {{ error }}
        </div>

        <Card class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <CardHeader class="pb-3">
                <div class="flex items-center gap-2">
                    <BellRing class="h-5 w-5 text-blue-500" />
                    <CardTitle>Eventos registrados</CardTitle>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-3">
                    <Skeleton v-for="n in 5" :key="n" class="h-16 w-full rounded-xl" />
                </div>
                <div v-else-if="eventos.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                    No hay eventos disponibles para mostrar.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="evento in eventos" :key="evento.id" class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700">
                                <AlertTriangle class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-900">{{ evento.tipo_evento }}</span>
                                    <Badge :class="['border', severityColor(evento.gravedad)]">{{ evento.gravedad }}</Badge>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ evento.descripcion }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-500 md:text-right">
                            <Clock3 class="h-3.5 w-3.5" />
                            {{ formatDate(evento.fecha_creacion) }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
