<script setup lang="ts">
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
