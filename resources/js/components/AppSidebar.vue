<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    LayoutDashboard,
    Settings,
    ShieldCheck,
    ShieldEllipsis,
} from '@lucide/vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Alertas',
        href: '/alertas',
        icon: Bell,
    },
    {
        title: 'Configuración',
        href: '/configuracion',
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-r border-slate-800/80 bg-slate-950 text-slate-100 shadow-[0_0_30px_rgba(15,23,42,0.35)]"
    >
        <SidebarHeader class="border-b border-slate-800/80 bg-slate-950/80">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-slate-900/80"
                    >
                        <Link
                            :href="dashboard()"
                            class="flex w-full items-center gap-3 rounded-xl px-2 py-2.5"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600/15 ring-1 ring-blue-500/30"
                            >
                                <ShieldCheck class="h-5 w-5 text-blue-400" />
                            </div>
                            <div class="min-w-0 text-left leading-tight">
                                <div
                                    class="truncate text-sm font-semibold text-white"
                                >
                                    Sistema de
                                </div>
                                <div class="truncate text-xs text-slate-300">
                                    seguridad
                                </div>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-slate-950 px-2 py-4">
            <NavMain :items="mainNavItems" label="Sistema" />

            <div
                class="mt-5 rounded-xl border border-slate-800 bg-slate-900/80 p-3 text-xs text-slate-300 shadow-inner shadow-slate-950/40"
            >
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div
                        class="flex items-center gap-2 font-medium text-slate-100"
                    >
                        <ShieldEllipsis class="h-4 w-4 text-blue-400" />
                        Estado del sistema
                    </div>
                    <span
                        class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-300"
                    >
                        ACTIVADO
                    </span>
                </div>
                <p class="leading-relaxed text-slate-400">
                    Monitoreo y control del acceso en tiempo real.
                </p>
            </div>
        </SidebarContent>

        <SidebarFooter class="border-t border-slate-800 bg-slate-950/90">
            <div class="px-2 pt-2 pb-2">
                <Link
                    :href="dashboard()"
                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2.5 text-sm font-medium text-slate-200 transition hover:border-blue-500/60 hover:bg-slate-900 hover:text-white"
                >
                    <ShieldCheck class="h-4 w-4 text-blue-400" />
                    Panel principal
                </Link>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
