<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-1 py-0">
        <SidebarGroupLabel
            class="px-2 text-[10px] font-medium tracking-[0.2em] text-slate-400 uppercase"
        >
            {{ label ?? 'Panel' }}
        </SidebarGroupLabel>
        <SidebarMenu class="space-y-1.5">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="h-11 rounded-xl border border-transparent bg-slate-900/40 text-slate-200 transition hover:border-slate-700 hover:bg-slate-900 hover:text-white data-[active=true]:border-blue-500/40 data-[active=true]:bg-blue-600/10 data-[active=true]:text-blue-200"
                >
                    <Link :href="item.href" class="flex items-center gap-3">
                        <component :is="item.icon" class="h-4 w-4 shrink-0" />
                        <span class="truncate font-medium">{{
                            item.title
                        }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
