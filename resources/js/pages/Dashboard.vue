<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BellRing,
    DoorClosed,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

interface EstadoSistemaApi {
    id: number;
    esta_activado: boolean;
    puerta_abierta: boolean;
    movimiento_detectado: boolean;
    estado: string;
    descripcion_ultima_alerta: string | null;
    fecha_ultima_alerta: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface EventoApi {
    id: number;
    tipo_evento: string;
    descripcion: string;
    gravedad: string;
    fecha_creacion: string | null;
}

const page = usePage();
const loading = ref(true);
const error = ref<string | null>(null);
const estado = ref<EstadoSistemaApi | null>(null);
const eventos = ref<EventoApi[]>([]);
const usuario = computed(
    () => page.props.auth.user as { name?: string; rol?: string },
);

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

const fetchEstado = async () => {
    try {
        const response = await fetch('/api/v1/estado-sistema', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el estado del sistema.');
        }

        const payload = await response.json();
        estado.value = payload.data ?? null;
    } catch (err) {
        error.value =
            err instanceof Error
                ? err.message
                : 'Error al consultar el estado del sistema.';
    } finally {
        loading.value = false;
    }
};

const fetchEventos = async () => {
    try {
        const response = await fetch('/api/v1/eventos', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        eventos.value = Array.isArray(payload.data) ? payload.data : [];
    } catch {
        eventos.value = [];
    }
};

onMounted(async () => {
    await Promise.all([fetchEstado(), fetchEventos()]);
});

const estadoChip = computed(() => {
    const value = estado.value?.estado ?? 'SIN INFORMACIÓN';

    if (value === 'ALARMA') {
        return {
            label: 'ALARMA',
            className: 'bg-red-500/10 text-red-600 border-red-500/40',
        };
    }

    if (value === 'NORMAL') {
        return {
            label: 'NORMAL',
            className:
                'bg-emerald-500/10 text-emerald-600 border-emerald-500/40',
        };
    }

    return {
        label: value,
        className: 'bg-slate-500/10 text-slate-300 border-slate-500/30',
    };
});

const metricCards = computed(() => [
    {
        title: 'Sistema',
        value: estado.value?.esta_activado ? 'ACTIVADO' : 'DESACTIVADO',
        icon: ShieldCheck,
        tone: estado.value?.esta_activado
            ? 'text-emerald-600 bg-emerald-500/10 border-emerald-500/30'
            : 'text-slate-600 bg-slate-500/10 border-slate-500/20',
    },
    {
        title: 'Puerta',
        value: estado.value?.puerta_abierta ? 'ABIERTA' : 'CERRADA',
        icon: DoorClosed,
        tone: estado.value?.puerta_abierta
            ? 'text-amber-600 bg-amber-500/10 border-amber-500/30'
            : 'text-emerald-600 bg-emerald-500/10 border-emerald-500/30',
    },
    {
        title: 'Movimiento',
        value: estado.value?.movimiento_detectado
            ? 'DETECTADO'
            : 'NO DETECTADO',
        icon: Activity,
        tone: estado.value?.movimiento_detectado
            ? 'text-red-600 bg-red-500/10 border-red-500/30'
            : 'text-emerald-600 bg-emerald-500/10 border-emerald-500/30',
    },
    {
        title: 'Estado',
        value: estado.value?.estado ?? 'SIN INFORMACIÓN',
        icon: ShieldAlert,
        tone:
            estado.value?.estado === 'ALARMA'
                ? 'text-red-600 bg-red-500/10 border-red-500/30'
                : 'text-blue-600 bg-blue-500/10 border-blue-500/30',
    },
]);

const ultimaAlerta = computed(() => {
    const alertEvent =
        eventos.value.find(
            (evento) =>
                evento.gravedad === 'critico' ||
                evento.gravedad === 'advertencia',
        ) ?? null;

    if (!alertEvent) {
        return {
            title: 'No hay alertas registradas',
            description: 'Sin información disponible',
            fecha: null,
        };
    }

    return {
        title: alertEvent.tipo_evento,
        description: alertEvent.descripcion,
        fecha: alertEvent.fecha_creacion,
    };
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <p
                    class="text-sm font-medium tracking-[0.2em] text-blue-600 uppercase"
                >
                    Sistema
                </p>
                <h1 class="text-3xl font-semibold text-slate-900">
                    Panel de seguridad
                </h1>
            </div>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                <Badge
                    variant="outline"
                    class="border-slate-200 bg-white text-slate-700"
                >
                    {{ usuario.name ?? 'Usuario' }} ·
                    {{ usuario.rol ?? 'Sin rol' }}
                </Badge>
            </div>
        </div>

        <div
            v-if="error"
            class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700"
        >
            {{ error }}
        </div>

        <div v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Skeleton v-for="n in 4" :key="n" class="h-32 w-full rounded-2xl" />
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="card in metricCards"
                :key="card.title"
                class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium text-slate-500">
                            {{ card.title }}
                        </div>
                        <div
                            :class="[
                                'flex h-10 w-10 items-center justify-center rounded-xl border',
                                card.tone,
                            ]"
                        >
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-slate-900">
                        {{ card.value }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.6fr_0.9fr]">
            <Card
                class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <BellRing class="h-5 w-5 text-red-500" />
                        <CardTitle>Última alerta</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-red-700">
                                {{ ultimaAlerta.title }}
                            </div>
                            <Badge
                                variant="outline"
                                class="border-red-200 bg-red-100 text-red-700"
                            >
                                {{
                                    ultimaAlerta.title.includes('PUERTA')
                                        ? 'Advertencia'
                                        : 'Alerta'
                                }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-red-700">
                            {{ ultimaAlerta.description }}
                        </p>
                        <p class="mt-3 text-xs text-red-600">
                            {{ formatDate(ultimaAlerta.fecha) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                class="rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <Sparkles class="h-5 w-5 text-blue-500" />
                        <CardTitle>Vista rápida</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        class="grid grid-cols-3 gap-3 text-center text-sm text-slate-600"
                    >
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="text-xs text-slate-500">Sistema</div>
                            <div class="mt-2 font-semibold text-slate-900">
                                {{ estado?.esta_activado ? 'On' : 'Off' }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="text-xs text-slate-500">Puerta</div>
                            <div class="mt-2 font-semibold text-slate-900">
                                {{
                                    estado?.puerta_abierta
                                        ? 'Abierta'
                                        : 'Cerrada'
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="text-xs text-slate-500">Movimiento</div>
                            <div class="mt-2 font-semibold text-slate-900">
                                {{ estado?.movimiento_detectado ? 'Sí' : 'No' }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="mt-5 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <span class="text-sm text-slate-600">
                            Estado del sistema
                        </span>
                        <Badge :class="['border', estadoChip.className]">
                            {{ estadoChip.label }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <CardHeader class="pb-3">
                <div class="flex items-center gap-2">
                    <TriangleAlert class="h-5 w-5 text-amber-500" />
                    <CardTitle>Historial de eventos</CardTitle>
                </div>
            </CardHeader>
            <CardContent>
                <div
                    v-if="eventos.length === 0"
                    class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500"
                >
                    No hay eventos registrados todavía.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="evento in eventos.slice(0, 5)"
                        :key="evento.id"
                        class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <div>
                            <div class="font-medium text-slate-900">
                                {{ evento.tipo_evento }}
                            </div>
                            <div class="text-sm text-slate-600">
                                {{ evento.descripcion }}
                            </div>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <div>{{ evento.gravedad }}</div>
                            <div class="mt-1">
                                {{ formatDate(evento.fecha_creacion) }}
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
