<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const unreadNotifications = computed(
    () => (page.props.unreadNotifications as number | undefined) ?? 0,
);
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 bg-background px-4 md:h-[72px] md:px-6 lg:px-8"
    >
        <SidebarTrigger />

        <div
            v-if="breadcrumbs.length > 0"
            class="hidden min-w-0 items-center md:flex"
        >
            <Breadcrumbs :breadcrumbs="breadcrumbs" />
        </div>

        <Link
            href="/notifications"
            class="relative ml-auto flex size-11 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden md:w-9"
            :aria-label="
                unreadNotifications > 0
                    ? `Notifikasi, ${unreadNotifications} belum dibaca`
                    : 'Notifikasi'
            "
        >
            <Bell class="size-5" />
            <span
                v-if="unreadNotifications > 0"
                class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground"
            >
                {{ unreadNotifications > 99 ? '99+' : unreadNotifications }}
            </span>
        </Link>
    </header>
</template>
