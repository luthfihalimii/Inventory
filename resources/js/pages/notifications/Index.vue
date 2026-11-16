<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';

type Notification = {
    id: number;
    type: string;
    title: string;
    body: string | null;
    read_at: string | null;
    created_at: string | null;
};

defineProps<{
    notifications: Notification[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notifikasi', href: '/notifications' }],
    },
});

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function markRead(notification: Notification): void {
    if (notification.read_at) {
        return;
    }

    router.post(
        `/notifications/${notification.id}/read`,
        {},
        { preserveScroll: true },
    );
}

function markAllRead(): void {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notifikasi" />

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Notifikasi"
            description="Pembaruan pengajuan, pengembalian, dan pengumuman."
        >
            <template #actions>
                <Button
                    class="h-11 rounded-xl"
                    variant="outline"
                    :disabled="!notifications.some((item) => !item.read_at)"
                    @click="markAllRead"
                >
                    Tandai semua dibaca
                </Button>
            </template>
        </PageHeader>

        <ul v-if="notifications.length > 0" class="grid gap-3">
            <li
                v-for="notification in notifications"
                :key="notification.id"
                class="rounded-2xl border bg-card p-4"
                :class="{ 'border-primary/40': !notification.read_at }"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold break-words">
                            {{ notification.title }}
                        </p>
                        <p
                            v-if="notification.body"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ notification.body }}
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(notification.created_at) }}
                    </p>
                </div>
                <Button
                    v-if="!notification.read_at"
                    class="mt-3"
                    size="sm"
                    variant="outline"
                    @click="markRead(notification)"
                >
                    Tandai dibaca
                </Button>
            </li>
        </ul>

        <EmptyState v-else title="Belum ada notifikasi." />
    </div>
</template>
