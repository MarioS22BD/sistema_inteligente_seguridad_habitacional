<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { computed, onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

interface EventoCritico {
    id: number;
    tipo_evento: string;
    descripcion: string;
    gravedad: string;
    usuario?: {
        name: string;
        email: string;
        rol: string;
    } | null;
}

const page = usePage();
const role = computed(
    () =>
        (
            page.props.auth.user as { rol?: string } | undefined
        )?.rol?.toLowerCase() ?? 'usuario',
);
const canReceiveCriticalAlerts = computed(() =>
    ['administrador', 'empleado'].includes(role.value),
);
const seenCriticalEventIds = new Set<number>();
let hasLoadedInitialEvents = false;
let checkingEvents = false;

const checkCriticalEvents = async (): Promise<void> => {
    if (!canReceiveCriticalAlerts.value || checkingEvents) return;

    checkingEvents = true;

    try {
        const response = await fetch('/api/v1/eventos', {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) return;

        const payload = (await response.json()) as { data?: EventoCritico[] };
        const criticalEvents = Array.isArray(payload.data)
            ? payload.data.filter((evento) => evento.gravedad === 'critico')
            : [];

        if (!hasLoadedInitialEvents) {
            criticalEvents.forEach((evento) =>
                seenCriticalEventIds.add(evento.id),
            );
            hasLoadedInitialEvents = true;
            return;
        }

        criticalEvents.reverse().forEach((evento) => {
            if (seenCriticalEventIds.has(evento.id)) return;

            seenCriticalEventIds.add(evento.id);
            toast.error(
                `Alerta crítica · Generada por: ${evento.usuario?.name ?? 'Actor no registrado'}`,
                {
                    description: `${evento.tipo_evento}: ${evento.descripcion}`,
                    duration: Infinity,
                },
            );
        });
    } catch {
        // Keep the shell available when the event API is temporarily unreachable.
    } finally {
        checkingEvents = false;
    }
};

const { pause } = useIntervalFn(checkCriticalEvents, 5000, {
    immediateCallback: false,
});

onMounted(() => void checkCriticalEvents());
onUnmounted(() => pause());
</script>

<template>
    <div class="min-h-screen w-full bg-slate-950 text-slate-100">
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                class="min-h-screen w-full flex-1 overflow-hidden bg-slate-950 text-slate-100"
            >
                <AppSidebarHeader
                    :breadcrumbs="breadcrumbs"
                    class="border-b border-slate-800/80 bg-slate-950/95 backdrop-blur supports-[backdrop-filter]:bg-slate-950/80"
                />
                <div class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto">
                    <slot />
                </div>
            </AppContent>
            <Toaster />
        </AppShell>
    </div>
</template>
