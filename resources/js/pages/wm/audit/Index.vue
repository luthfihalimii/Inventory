<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type AuditLog = {
    id: number;
    action: string;
    actor_role: string | null;
    actor_user_id: number | null;
    entity_type: string | null;
    entity_id: number | null;
    ip_address: string | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    logs: Paginated<AuditLog>;
    filters: { action: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Audit', href: '/wm/audit' }],
    },
});

const action = ref(props.filters.action);

function search(): void {
    router.get(
        '/wm/audit',
        { action: action.value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Audit" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Audit"
            description="Riwayat perubahan data dan aktivitas login."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form class="flex flex-wrap gap-3" @submit.prevent="search">
                <Input
                    v-model="action"
                    placeholder="Filter aksi, mis. member"
                />
                <Button type="submit" variant="outline">Filter</Button>
            </form>
        </section>

        <TableShell caption="Riwayat aktivitas sistem">
            <thead>
                <tr>
                    <th class="p-3">Waktu</th>
                    <th class="p-3">Aksi</th>
                    <th class="hidden p-3 md:table-cell">Aktor</th>
                    <th class="hidden p-3 lg:table-cell">Entitas</th>
                    <th class="hidden p-3 md:table-cell">IP</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="log in logs.data" :key="log.id" class="border-t">
                    <td class="p-3 whitespace-nowrap">
                        {{ log.created_at }}
                    </td>
                    <td class="p-3 font-mono">{{ log.action }}</td>
                    <td class="hidden p-3 md:table-cell">
                        {{ log.actor_role ?? '-' }}
                        <span class="block text-xs text-muted-foreground">
                            {{
                                log.actor_user_id
                                    ? `#${log.actor_user_id}`
                                    : 'anonim'
                            }}
                        </span>
                    </td>
                    <td class="hidden p-3 lg:table-cell">
                        {{ log.entity_type ?? '-' }} #{{ log.entity_id ?? '-' }}
                    </td>
                    <td class="hidden p-3 md:table-cell">
                        {{ log.ip_address ?? '-' }}
                    </td>
                </tr>
                <tr v-if="logs.data.length === 0">
                    <td class="p-3 text-muted-foreground" colspan="5">
                        Tidak ada catatan yang cocok. Hapus filter aksi untuk
                        melihat seluruh riwayat.
                    </td>
                </tr>
            </tbody>
        </TableShell>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in logs.links" :key="link.label">
                <Button
                    v-if="link.url"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    as-child
                >
                    <Link :href="link.url" v-html="link.label" />
                </Button>
                <span
                    v-else
                    class="px-2 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
