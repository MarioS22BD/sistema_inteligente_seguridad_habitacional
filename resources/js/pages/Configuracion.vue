<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bell, LockKeyhole, ShieldCheck, ToggleLeft } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Configuración', href: '/configuracion' },
        ],
    },
});
</script>

<template>
    <Head title="Configuración" />

    <div class="space-y-6 p-4 md:p-6">
        <div>
            <p class="text-sm font-medium uppercase tracking-[0.2em] text-blue-600">
                Configuración
            </p>
            <h1 class="text-3xl font-semibold text-slate-900">
                Sistema de seguridad
            </h1>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <Card class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="h-5 w-5 text-blue-500" />
                        <CardTitle>Estado del sistema</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <span class="text-sm text-slate-700">Activado</span>
                        <div class="flex items-center gap-2 text-emerald-600">
                            <ToggleLeft class="h-5 w-5" />
                            <span class="text-sm font-medium">Activo</span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                        Actualmente el sistema refleja el estado real proporcionado por el backend. La configuración detallada requiere capacidades adicionales del API.
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <Bell class="h-5 w-5 text-amber-500" />
                        <CardTitle>Notificaciones</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <span class="text-sm text-slate-700">Alertas activas</span>
                        <span class="text-sm font-semibold text-slate-900">Habilitadas</span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                        Se muestran notificaciones cuando el backend registra nuevos eventos de seguridad.
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <LockKeyhole class="h-5 w-5 text-violet-500" />
                        <CardTitle>Seguridad</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-sm text-slate-500">Perfil</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900">Usuario autenticado</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-sm text-slate-500">Rol</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900">Según permisos</div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-sm text-slate-500">Acceso</div>
                            <div class="mt-2 text-lg font-semibold text-slate-900">Protegido por Sanctum</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
