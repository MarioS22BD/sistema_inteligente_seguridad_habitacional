<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BellRing,
    DoorClosed,
    DoorOpen,
    RefreshCw,
    ShieldAlert,
    ShieldCheck,
    TriangleAlert,
} from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
const isRefreshing = ref(false);
const isSubmitting = ref(false);
const error = ref<string | null>(null);
const estado = ref<EstadoSistemaApi | null>(null);
const eventos = ref<EventoApi[]>([]);
const usuario = computed(
    () => page.props.auth.user as { name?: string; rol?: string },
);
const userRole = computed(() => usuario.value?.rol?.toLowerCase() ?? 'usuario');
const canControlSystem = computed(() =>
    ['administrador', 'empleado'].includes(userRole.value),
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

const fetchEstado = async (): Promise<void> => {
    try {
        const response = await fetch('/api/v1/estado-sistema', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el estado del sistema.');
        }

        const payload = (await response.json()) as { data?: EstadoSistemaApi };
        const nextState = payload.data ?? null;
        const previousState = estado.value?.estado;
        estado.value = nextState;

        if (
            previousState &&
            previousState !== 'ALARMA' &&
            nextState?.estado === 'ALARMA'
        ) {
            toast.error('Alarma activa. Revise el estado de la habitación.');
        }

        error.value = null;
    } catch (err) {
        error.value =
            err instanceof Error
                ? err.message
                : 'Error al consultar el estado del sistema.';
    }
};

const fetchEventos = async (): Promise<void> => {
    try {
        const response = await fetch('/api/v1/eventos', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('No se pudo cargar el historial de eventos.');
        }

        const payload = (await response.json()) as { data?: EventoApi[] };
        eventos.value = Array.isArray(payload.data) ? payload.data : [];
    } catch {
        eventos.value = [];
    }
};

const refreshDashboard = async (): Promise<void> => {
    if (isRefreshing.value) return;

    isRefreshing.value = true;
    await Promise.all([fetchEstado(), fetchEventos()]);
    isRefreshing.value = false;
    loading.value = false;
};

const { pause } = useIntervalFn(refreshDashboard, 4000, {
    immediateCallback: false,
});

onMounted(() => {
    void refreshDashboard();
});

onUnmounted(() => pause());

const toggleSystem = async (): Promise<void> => {
    if (!canControlSystem.value || !estado.value || isSubmitting.value) return;

    isSubmitting.value = true;

    try {
        const nextActive = !estado.value.esta_activado;
        const response = await fetch('/api/v1/seguridad/actualizar', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                esta_activado: nextActive,
                puerta_abierta: estado.value.puerta_abierta,
                movimiento_detectado: estado.value.movimiento_detectado,
            }),
        });

        if (!response.ok) {
            throw new Error('No se pudo actualizar el estado del sistema.');
        }

        await refreshDashboard();
        toast.success(nextActive ? 'Sistema armado.' : 'Sistema desarmado.');
    } catch (err) {
        toast.error(
            err instanceof Error
                ? err.message
                : 'Error al actualizar el sistema.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

const manualRefresh = async (): Promise<void> => {
    await refreshDashboard();
};

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
        className: 'bg-slate-500/10 text-slate-600 border-slate-500/30',
    };
});

const ultimaAlerta = computed(() => {
    if (!estado.value?.descripcion_ultima_alerta) {
        return {
            title: 'No hay alertas registradas',
            description: 'Sin información disponible',
            fecha: null,
        };
    }

    return {
        title:
            estado.value.estado === 'ALARMA'
                ? 'ALARMA ACTIVA'
                : 'Última incidencia',
        description: estado.value.descripcion_ultima_alerta,
        fecha: estado.value.fecha_ultima_alerta,
    };
});

const severityClass = (gravedad: string): string => {
    if (gravedad === 'critico') return 'border-red-300 bg-red-50 text-red-700';
    if (gravedad === 'advertencia')
        return 'border-amber-300 bg-amber-50 text-amber-700';
    return 'border-sky-300 bg-sky-50 text-sky-700';
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="w-full space-y-6 p-4 sm:p-6 xl:p-8">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-xs font-semibold text-blue-600 uppercase">
                    Monitoreo en vivo
                </p>
                <h1
                    class="mt-1 text-2xl font-semibold text-slate-900 sm:text-3xl"
                >
                    Panel de seguridad
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Estado de sensores y eventos de seguridad
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <Badge
                    variant="outline"
                    class="border-slate-200 bg-white text-slate-700"
                >
                    {{ usuario.name ?? 'Usuario' }} ·
                    {{ usuario.rol ?? 'Sin rol' }}
                </Badge>
                <div
                    class="inline-flex items-center gap-2 text-xs text-slate-500"
                    aria-live="polite"
                >
                    <span
                        :class="[
                            'h-2 w-2 rounded-full',
                            isRefreshing ? 'bg-amber-400' : 'bg-emerald-500',
                        ]"
                    ></span>
                    {{ isRefreshing ? 'Actualizando' : 'En vivo · 4 s' }}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="isRefreshing"
                    @click="manualRefresh"
                >
                    <RefreshCw
                        :class="['h-4 w-4', isRefreshing && 'animate-spin']"
                    />
                    Actualizar
                </Button>
            </div>
        </header>

        <div
            v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            role="alert"
        >
            {{ error }}
        </div>

        <div
            v-if="loading"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Skeleton v-for="n in 4" :key="n" class="h-40 w-full rounded-lg" />
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Card
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle class="text-sm font-medium text-slate-500"
                            >Estado de alarma</CardTitle
                        >
                        <ShieldCheck
                            :class="[
                                'h-5 w-5',
                                estado?.esta_activado
                                    ? 'text-emerald-600'
                                    : 'text-slate-400',
                            ]"
                        />
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <Badge
                        :class="[
                            'border',
                            estado?.esta_activado
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                : 'border-slate-300 bg-slate-100 text-slate-600',
                        ]"
                    >
                        {{ estado?.esta_activado ? 'ACTIVADO' : 'DESACTIVADO' }}
                    </Badge>
                    <Button
                        class="w-full"
                        :variant="
                            estado?.esta_activado ? 'destructive' : 'default'
                        "
                        :disabled="!canControlSystem || !estado || isSubmitting"
                        :title="
                            canControlSystem
                                ? undefined
                                : 'Tu rol tiene acceso de solo lectura'
                        "
                        @click="toggleSystem"
                    >
                        <ShieldAlert class="h-4 w-4" />
                        {{
                            isSubmitting
                                ? 'Actualizando…'
                                : estado?.esta_activado
                                  ? 'Desarmar sistema'
                                  : 'Armar sistema'
                        }}
                    </Button>
                    <p v-if="!canControlSystem" class="text-xs text-slate-500">
                        Solo lectura: tu rol no permite cambiar el armado.
                    </p>
                </CardContent>
            </Card>

            <Card
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle class="text-sm font-medium text-slate-500"
                            >Puerta</CardTitle
                        >
                        <component
                            :is="estado?.puerta_abierta ? DoorOpen : DoorClosed"
                            :class="[
                                'h-5 w-5',
                                estado?.puerta_abierta
                                    ? 'text-red-600'
                                    : 'text-emerald-600',
                            ]"
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        :class="[
                            'text-2xl font-semibold',
                            estado?.puerta_abierta
                                ? 'text-red-700'
                                : 'text-emerald-700',
                        ]"
                    >
                        {{ estado?.puerta_abierta ? 'ABIERTA' : 'CERRADA' }}
                    </div>
                    <p class="mt-2 text-sm text-slate-500">Sensor de acceso</p>
                </CardContent>
            </Card>

            <Card
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle class="text-sm font-medium text-slate-500"
                            >Movimiento</CardTitle
                        >
                        <Activity
                            :class="[
                                'h-5 w-5',
                                estado?.movimiento_detectado
                                    ? 'text-amber-600'
                                    : 'text-emerald-600',
                            ]"
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        :class="[
                            'text-2xl font-semibold',
                            estado?.movimiento_detectado
                                ? 'text-amber-700'
                                : 'text-emerald-700',
                        ]"
                    >
                        {{
                            estado?.movimiento_detectado
                                ? 'DETECTADO'
                                : 'NO DETECTADO'
                        }}
                    </div>
                    <p class="mt-2 text-sm text-slate-500">
                        Sensor de presencia
                    </p>
                </CardContent>
            </Card>

            <Card
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle class="text-sm font-medium text-slate-500"
                            >Estado global</CardTitle
                        >
                        <TriangleAlert
                            :class="[
                                'h-5 w-5',
                                estado?.estado === 'ALARMA'
                                    ? 'animate-pulse text-red-600'
                                    : 'text-blue-600',
                            ]"
                        />
                    </div>
                </CardHeader>
                <CardContent>
                    <Badge
                        :class="[
                            'border',
                            estadoChip.className,
                            estado?.estado === 'ALARMA' && 'animate-pulse',
                        ]"
                    >
                        {{
                            estadoChip.label === 'ALARMA'
                                ? 'ALARMA / INCIDENCIA'
                                : estadoChip.label
                        }}
                    </Badge>
                    <p class="mt-3 text-sm text-slate-500">
                        Condición reportada por el sistema
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,0.9fr)]"
        >
            <Card
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <BellRing class="h-5 w-5 text-red-500" />
                        <CardTitle class="text-lg">Última alerta</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        :class="[
                            'rounded-lg border p-4 sm:p-5',
                            estado?.estado === 'ALARMA'
                                ? 'border-red-300 bg-red-50'
                                : 'border-slate-200 bg-slate-50',
                        ]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div
                                :class="[
                                    'min-w-0 text-base font-semibold',
                                    estado?.estado === 'ALARMA'
                                        ? 'text-red-700'
                                        : 'text-slate-900',
                                ]"
                            >
                                {{ ultimaAlerta.title }}
                            </div>
                            <Badge
                                variant="outline"
                                :class="
                                    estado?.estado === 'ALARMA'
                                        ? 'shrink-0 border-red-300 bg-red-100 text-red-700'
                                        : 'shrink-0 border-slate-300 bg-white text-slate-600'
                                "
                            >
                                {{
                                    estado?.estado === 'ALARMA'
                                        ? 'Crítica'
                                        : 'Registro'
                                }}
                            </Badge>
                        </div>
                        <p
                            :class="[
                                'mt-2 text-sm break-words',
                                estado?.estado === 'ALARMA'
                                    ? 'text-red-700'
                                    : 'text-slate-600',
                            ]"
                        >
                            {{ ultimaAlerta.description }}
                        </p>
                        <p class="mt-3 text-xs text-slate-500">
                            {{ formatDate(ultimaAlerta.fecha) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5 text-blue-500" />
                        <CardTitle class="text-lg">Vista rápida</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        class="grid grid-cols-3 gap-2 text-center text-sm text-slate-600"
                    >
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="text-xs text-slate-500">Sistema</div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{ estado?.esta_activado ? 'On' : 'Off' }}
                            </div>
                        </div>
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="text-xs text-slate-500">Puerta</div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{
                                    estado?.puerta_abierta
                                        ? 'Abierta'
                                        : 'Cerrada'
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="text-xs text-slate-500">Movimiento</div>
                            <div
                                class="mt-2 text-sm font-semibold text-slate-900"
                            >
                                {{ estado?.movimiento_detectado ? 'Sí' : 'No' }}
                            </div>
                        </div>
                    </div>
                    <div
                        class="mt-5 flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-slate-50 p-3"
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

        <Card
            class="min-w-0 rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <TriangleAlert class="h-5 w-5 text-amber-500" />
                        <CardTitle class="text-lg">Eventos recientes</CardTitle>
                    </div>
                    <span class="text-xs text-slate-500"
                        >Últimos {{ Math.min(eventos.length, 5) }}</span
                    >
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="loading" class="space-y-3">
                    <Skeleton
                        v-for="n in 4"
                        :key="n"
                        class="h-14 w-full rounded-md"
                    />
                </div>
                <div
                    v-else-if="eventos.length === 0"
                    class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500"
                >
                    No hay eventos registrados todavía.
                </div>

                <div v-else>
                    <div class="space-y-3 md:hidden">
                        <article
                            v-for="evento in eventos.slice(0, 5)"
                            :key="evento.id"
                            class="rounded-lg border border-slate-200 bg-slate-50 p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{ evento.tipo_evento }}
                                </h3>
                                <Badge
                                    :class="[
                                        'border',
                                        severityClass(evento.gravedad),
                                    ]"
                                    >{{ evento.gravedad }}</Badge
                                >
                            </div>
                            <p class="mt-2 text-sm break-words text-slate-600">
                                {{ evento.descripcion }}
                            </p>
                            <time class="mt-3 block text-xs text-slate-500">{{
                                formatDate(evento.fecha_creacion)
                            }}</time>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table
                            class="w-full min-w-[640px] table-auto text-left text-sm"
                        >
                            <thead
                                class="border-b border-slate-200 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 font-medium"
                                    >
                                        Tipo de evento
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 font-medium"
                                    >
                                        Descripción
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 font-medium"
                                    >
                                        Gravedad
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-3 py-3 font-medium"
                                    >
                                        Fecha y hora
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="evento in eventos.slice(0, 5)"
                                    :key="evento.id"
                                    class="align-top"
                                >
                                    <td
                                        class="px-3 py-4 font-medium text-slate-900"
                                    >
                                        {{ evento.tipo_evento }}
                                    </td>
                                    <td
                                        class="max-w-lg px-3 py-4 break-words text-slate-600"
                                    >
                                        {{ evento.descripcion }}
                                    </td>
                                    <td class="px-3 py-4">
                                        <Badge
                                            :class="[
                                                'border',
                                                severityClass(evento.gravedad),
                                            ]"
                                            >{{ evento.gravedad }}</Badge
                                        >
                                    </td>
                                    <td
                                        class="px-3 py-4 whitespace-nowrap text-slate-500"
                                    >
                                        {{ formatDate(evento.fecha_creacion) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
