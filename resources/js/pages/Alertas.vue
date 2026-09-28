<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    Clock3,
    Inbox,
    RefreshCw,
    Search,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

interface EventosApiPayload {
    data?: EventoApi[];
    links?: {
        next?: string | null;
    };
}

type GravedadFiltro = 'todos' | 'informativo' | 'advertencia' | 'critico';

const page = usePage();
const loading = ref(true);
const refreshing = ref(false);
const deleting = ref(false);
const eventos = ref<EventoApi[]>([]);
const error = ref<string | null>(null);
const searchTerm = ref('');
const selectedSeverity = ref<GravedadFiltro>('todos');
const eventToDelete = ref<EventoApi | null>(null);
const deleteDialogOpen = ref(false);
const usuario = computed(() => page.props.auth.user as { rol?: string });
const isAdmin = computed(
    () => usuario.value.rol?.toLowerCase() === 'administrador',
);

const filteredEvents = computed(() => {
    const query = searchTerm.value.trim().toLocaleLowerCase('es');

    return eventos.value.filter((evento) => {
        const matchesSearch =
            !query ||
            `${evento.tipo_evento} ${evento.descripcion}`
                .toLocaleLowerCase('es')
                .includes(query);
        const matchesSeverity =
            selectedSeverity.value === 'todos' ||
            evento.gravedad === selectedSeverity.value;

        return matchesSearch && matchesSeverity;
    });
});

const hasActiveFilters = computed(
    () => searchTerm.value.length > 0 || selectedSeverity.value !== 'todos',
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

const severityLabel = (gravedad: string): string => {
    if (gravedad === 'informativo') return 'Informativo';
    if (gravedad === 'advertencia') return 'Advertencia';
    if (gravedad === 'critico') return 'Crítico';
    return gravedad;
};

const severityColor = (gravedad: string): string => {
    switch (gravedad) {
        case 'critico':
            return 'border-red-300 bg-red-50 text-red-700';
        case 'advertencia':
            return 'border-amber-300 bg-amber-50 text-amber-800';
        default:
            return 'border-sky-300 bg-sky-50 text-sky-700';
    }
};

const severityBorder = (gravedad: string): string => {
    if (gravedad === 'critico') return 'border-l-red-500';
    if (gravedad === 'advertencia') return 'border-l-amber-500';
    return 'border-l-sky-500';
};

const loadEvents = async (showFeedback = false): Promise<void> => {
    refreshing.value = true;

    try {
        const allEvents: EventoApi[] = [];
        let nextPage: string | null = '/api/v1/eventos';

        while (nextPage) {
            const response = await fetch(nextPage, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('No se pudo cargar el historial de eventos.');
            }

            const payload = (await response.json()) as EventosApiPayload;
            if (Array.isArray(payload.data)) allEvents.push(...payload.data);
            nextPage = payload.links?.next ?? null;
        }

        eventos.value = allEvents;
        error.value = null;
        if (showFeedback) toast.success('Historial actualizado.');
    } catch (err) {
        error.value =
            err instanceof Error
                ? err.message
                : 'No se pudo cargar el historial de eventos.';
        if (showFeedback) toast.error(error.value);
    } finally {
        loading.value = false;
        refreshing.value = false;
    }
};

const clearFilters = (): void => {
    searchTerm.value = '';
    selectedSeverity.value = 'todos';
};

const requestDelete = (evento: EventoApi): void => {
    if (!isAdmin.value) return;
    eventToDelete.value = evento;
    deleteDialogOpen.value = true;
};

const updateDeleteDialog = (open: boolean): void => {
    deleteDialogOpen.value = open;
    if (!open && !deleting.value) eventToDelete.value = null;
};

const deleteEvent = async (): Promise<void> => {
    if (!isAdmin.value || !eventToDelete.value || deleting.value) return;

    deleting.value = true;

    try {
        const response = await fetch(
            `/api/v1/eventos/${eventToDelete.value.id}`,
            {
                method: 'DELETE',
                headers: { Accept: 'application/json' },
            },
        );

        if (!response.ok) {
            throw new Error('No se pudo eliminar el evento.');
        }

        eventos.value = eventos.value.filter(
            (evento) => evento.id !== eventToDelete.value?.id,
        );
        deleteDialogOpen.value = false;
        eventToDelete.value = null;
        toast.success('Evento eliminado.');
    } catch (err) {
        toast.error(
            err instanceof Error ? err.message : 'Error al eliminar el evento.',
        );
    } finally {
        deleting.value = false;
    }
};

onMounted(() => void loadEvents());
</script>

<template>
    <Head title="Alertas" />

    <div class="w-full space-y-6 p-4 sm:p-6 xl:p-8">
        <header
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="text-xs font-semibold text-blue-600 uppercase">
                    Seguridad
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold text-slate-900 sm:text-3xl"
                    >
                        Historial de Eventos de Seguridad
                    </h1>
                    <Badge
                        variant="outline"
                        class="border-slate-200 bg-white text-slate-700"
                    >
                        {{ eventos.length }} registros
                    </Badge>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    Consulta y filtra los eventos reportados por el sistema.
                </p>
            </div>
        </header>

        <div
            v-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            role="alert"
        >
            {{ error }}
        </div>

        <Card
            class="w-full rounded-lg border border-slate-200 bg-white shadow-sm"
        >
            <CardContent class="space-y-5 p-4 sm:p-5">
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="relative min-w-0 flex-1">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                        />
                        <Input
                            v-model="searchTerm"
                            type="search"
                            placeholder="Buscar por tipo o descripción..."
                            aria-label="Buscar eventos por tipo o descripción"
                            class="h-10 border-slate-200 bg-white pl-9 text-slate-900 placeholder:text-slate-400"
                        />
                    </div>

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <Select v-model="selectedSeverity">
                            <SelectTrigger
                                class="h-10 w-full border-slate-200 bg-white text-slate-700 sm:w-48"
                                aria-label="Filtrar por gravedad"
                            >
                                <SelectValue
                                    placeholder="Todas las gravedades"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="todos"
                                    >Todas las gravedades</SelectItem
                                >
                                <SelectItem value="informativo"
                                    >Informativo</SelectItem
                                >
                                <SelectItem value="advertencia"
                                    >Advertencia</SelectItem
                                >
                                <SelectItem value="critico">Crítico</SelectItem>
                            </SelectContent>
                        </Select>

                        <div class="flex gap-2">
                            <Button
                                v-if="hasActiveFilters"
                                variant="outline"
                                class="h-10 flex-1 border-slate-200 text-slate-700 sm:flex-none"
                                @click="clearFilters"
                            >
                                Limpiar filtros
                            </Button>
                            <Button
                                variant="outline"
                                class="h-10 flex-1 border-slate-200 text-slate-700 sm:flex-none"
                                :disabled="refreshing"
                                @click="loadEvents(true)"
                            >
                                <RefreshCw
                                    :class="[
                                        'h-4 w-4',
                                        refreshing && 'animate-spin',
                                    ]"
                                />
                                Actualizar
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4"
                >
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <BellRing class="h-4 w-4 text-blue-600" />
                        <span>Eventos registrados</span>
                    </div>
                    <span class="text-xs text-slate-500">
                        Mostrando {{ filteredEvents.length }} de
                        {{ eventos.length }}
                    </span>
                </div>

                <div v-if="loading" class="space-y-3">
                    <Skeleton
                        v-for="n in 6"
                        :key="n"
                        class="h-16 w-full rounded-md"
                    />
                </div>
                <div
                    v-else-if="filteredEvents.length === 0"
                    class="flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center"
                >
                    <div
                        class="mb-4 flex h-12 w-12 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500"
                    >
                        <Inbox class="h-6 w-6" />
                    </div>
                    <h2 class="font-semibold text-slate-900">
                        Sin eventos coincidentes
                    </h2>
                    <p class="mt-2 max-w-md text-sm text-slate-500">
                        No hay eventos registrados que coincidan con los filtros
                        aplicados.
                    </p>
                    <Button
                        v-if="hasActiveFilters"
                        variant="outline"
                        class="mt-4 border-slate-200"
                        @click="clearFilters"
                    >
                        Limpiar filtros
                    </Button>
                </div>

                <div v-else>
                    <div class="space-y-3 md:hidden">
                        <Card
                            v-for="evento in filteredEvents"
                            :key="evento.id"
                            :class="[
                                'overflow-hidden rounded-md border border-l-4 border-slate-200 bg-white shadow-none',
                                severityBorder(evento.gravedad),
                            ]"
                        >
                            <CardContent class="space-y-3 p-4">
                                <div
                                    class="flex min-w-0 items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <h2
                                            class="font-semibold break-words text-slate-900"
                                        >
                                            {{ evento.tipo_evento }}
                                        </h2>
                                        <p
                                            class="mt-1 text-sm leading-5 break-words text-slate-600"
                                        >
                                            {{ evento.descripcion }}
                                        </p>
                                    </div>
                                    <Button
                                        v-if="isAdmin"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 shrink-0 text-slate-500 hover:bg-red-50 hover:text-red-700"
                                        :aria-label="`Eliminar evento ${evento.tipo_evento}`"
                                        title="Eliminar evento"
                                        @click="requestDelete(evento)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3"
                                >
                                    <Badge
                                        :class="[
                                            'border',
                                            severityColor(evento.gravedad),
                                            evento.gravedad === 'critico' &&
                                                'animate-pulse',
                                        ]"
                                    >
                                        {{ severityLabel(evento.gravedad) }}
                                    </Badge>
                                    <time
                                        class="inline-flex items-center gap-1.5 text-xs text-slate-500"
                                    >
                                        <Clock3 class="h-3.5 w-3.5" />
                                        {{ formatDate(evento.fecha_creacion) }}
                                    </time>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table
                            class="w-full min-w-[760px] table-auto text-left text-sm"
                        >
                            <thead
                                class="border-y border-slate-200 bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Tipo
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Descripción
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Gravedad
                                    </th>
                                    <th
                                        scope="col"
                                        class="px-4 py-3 font-medium"
                                    >
                                        Fecha y hora
                                    </th>
                                    <th
                                        v-if="isAdmin"
                                        scope="col"
                                        class="w-16 px-4 py-3 text-right font-medium"
                                    >
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="evento in filteredEvents"
                                    :key="evento.id"
                                    class="align-top hover:bg-slate-50/70"
                                >
                                    <td
                                        class="max-w-56 px-4 py-4 font-medium break-words text-slate-900"
                                    >
                                        {{ evento.tipo_evento }}
                                    </td>
                                    <td
                                        class="max-w-xl px-4 py-4 break-words text-slate-600"
                                    >
                                        {{ evento.descripcion }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <Badge
                                            :class="[
                                                'border',
                                                severityColor(evento.gravedad),
                                                evento.gravedad === 'critico' &&
                                                    'animate-pulse',
                                            ]"
                                        >
                                            {{ severityLabel(evento.gravedad) }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="px-4 py-4 whitespace-nowrap text-slate-500"
                                    >
                                        {{ formatDate(evento.fecha_creacion) }}
                                    </td>
                                    <td
                                        v-if="isAdmin"
                                        class="px-4 py-3 text-right"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-slate-500 hover:bg-red-50 hover:text-red-700"
                                            :aria-label="`Eliminar evento ${evento.tipo_evento}`"
                                            title="Eliminar evento"
                                            @click="requestDelete(evento)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog :open="deleteDialogOpen" @update:open="updateDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-slate-900">
                        <TriangleAlert class="h-5 w-5 text-red-600" />
                        Eliminar evento
                    </DialogTitle>
                    <DialogDescription class="text-slate-600">
                        Esta acción es permanente. ¿Confirmas que deseas
                        eliminar
                        <strong class="font-semibold text-slate-900">{{
                            eventToDelete?.tipo_evento
                        }}</strong
                        >?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:justify-end">
                    <DialogClose as-child>
                        <Button variant="outline" :disabled="deleting">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="deleteEvent"
                    >
                        <RefreshCw
                            v-if="deleting"
                            class="h-4 w-4 animate-spin"
                        />
                        <Trash2 v-else class="h-4 w-4" />
                        {{
                            deleting
                                ? 'Eliminando…'
                                : 'Eliminar definitivamente'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
