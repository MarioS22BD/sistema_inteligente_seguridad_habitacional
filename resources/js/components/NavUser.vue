<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isMobile, state } = useSidebar();
const roleLabel = computed(() => (user.value?.rol ?? 'usuario').toString());
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="h-16 rounded-xl border border-slate-800 bg-slate-900/80 text-slate-100 hover:bg-slate-900 data-[state=open]:bg-slate-900 data-[state=open]:text-white"
                        data-test="sidebar-menu-button"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <UserInfo :user="user" :show-email="true" />
                        </div>

                        <div class="ml-auto flex items-center gap-2">
                            <Badge
                                variant="secondary"
                                class="border border-blue-500/30 bg-blue-500/10 px-1.5 py-0.5 text-[10px] font-medium text-blue-200"
                            >
                                {{ roleLabel }}
                            </Badge>
                            <ChevronsUpDown class="size-4 text-slate-400" />
                        </div>
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-xl border-slate-800 bg-slate-950 text-slate-100"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <div
                        class="mb-2 flex items-center gap-2 border-b border-slate-800 px-3 py-2 text-xs text-slate-400"
                    >
                        <ShieldCheck class="h-3.5 w-3.5 text-blue-400" />
                        Acceso verificado
                    </div>
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
