<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Bell, LayoutGrid, ShieldCheck, Settings2 } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Alertas',
        href: '/alertas',
        icon: Bell,
    },
    {
        title: 'Configuración',
        href: '/configuracion',
        icon: Settings2,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-slate-800/80 bg-slate-950 text-slate-100">
        <SidebarHeader class="border-b border-slate-800/80 bg-slate-950">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-slate-900">
                        <Link :href="dashboard()" class="flex items-center gap-3 px-2 py-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600/20 ring-1 ring-blue-500/40">
                                <ShieldCheck class="h-5 w-5 text-blue-400" />
                            </div>
                            <div class="text-left leading-tight">
                                <div class="text-sm font-semibold text-white">Sistema de</div>
                                <div class="text-xs text-slate-300">seguridad</div>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-slate-950 px-2 py-4">
            <NavMain :items="mainNavItems" label="Sistema" />
            <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900/60 p-3 text-xs text-slate-300">
                <div class="mb-1.5 flex items-center gap-2 font-medium text-slate-100">
                    <ShieldCheck class="h-4 w-4 text-blue-400" />
                    Estado
                </div>
                <p>Monitoreo y control del acceso en tiempo real.</p>
            </div>
        </SidebarContent>

        <SidebarFooter class="border-t border-slate-800 bg-slate-950">
            <div class="px-2 pb-2">
                <Link
                    :href="edit()"
                    class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-900/80 px-3 py-2 text-sm text-slate-200 transition hover:border-blue-500/50 hover:text-white"
                >
                    <Settings2 class="h-4 w-4 text-blue-400" />
                    Perfil y seguridad
                </Link>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
