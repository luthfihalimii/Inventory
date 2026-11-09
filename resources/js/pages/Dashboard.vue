<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';

type Stat = {
    label: string;
    value: number;
    href: string | null;
};

type Announcement = {
    id: number;
    title: string;
    content: string;
    published_at: string | null;
};

defineProps<{
    stats: Stat[];
    announcements: Announcement[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        dateStyle: 'medium',
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Dashboard</h1>
            <p class="text-sm text-muted-foreground">
                Ringkasan aktivitas inventaris dan peminjaman.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <component
                :is="stat.href ? Link : 'div'"
                v-for="stat in stats"
                :key="stat.label"
                :href="stat.href ?? undefined"
                class="rounded-lg border p-4"
                :class="stat.href ? 'transition-colors hover:bg-muted/50' : ''"
            >
                <p class="text-sm text-muted-foreground">{{ stat.label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ stat.value }}
                </p>
            </component>
        </div>

        <div class="rounded-lg border p-4">
            <h2 class="font-medium">Pengumuman</h2>

            <ul v-if="announcements.length > 0" class="mt-3 grid gap-3">
                <li
                    v-for="announcement in announcements"
                    :key="announcement.id"
                    class="border-b pb-3 last:border-b-0 last:pb-0"
                >
                    <p class="font-medium">{{ announcement.title }}</p>
                    <p
                        class="mt-1 text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ announcement.content }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatDate(announcement.published_at) }}
                    </p>
                </li>
            </ul>

            <p v-else class="mt-3 text-sm text-muted-foreground">
                Belum ada pengumuman.
            </p>
        </div>
    </div>
</template>
