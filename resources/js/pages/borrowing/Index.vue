<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import type { Paginated, StatusTone } from '@/types';

type BorrowRequest = {
    id: number;
    reference_no: string | null;
    category: string;
    pickup_at: string;
    return_at: string;
    status: string;
    submitted_at: string;
    items_count: number;
};

defineProps<{ requests: Paginated<BorrowRequest> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Peminjaman Saya', href: '/peminjaman' }],
    },
});

const statusLabel: Record<string, string> = {
    pending_approval: 'Menunggu persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const categoryLabel: Record<string, string> = {
    scheduled: 'Terjadwal',
    urgent: 'Mendadak',
};

const statusTone: Record<string, StatusTone> = {
    pending_approval: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};

const categoryTone: Record<string, StatusTone> = {
    scheduled: 'info',
    urgent: 'warning',
};

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Peminjaman Saya" />

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Peminjaman Saya"
            description="Riwayat dan status pengajuan peminjaman."
        >
            <template #actions>
                <Button class="h-11 rounded-xl" as-child>
                    <Link href="/peminjaman/baru">Ajukan peminjaman</Link>
                </Button>
            </template>
        </PageHeader>

        <EmptyState
            v-if="requests.data.length === 0"
            title="Belum ada pengajuan."
        >
            <template #actions>
                <Button class="h-11 rounded-xl" as-child>
                    <Link href="/peminjaman/baru">Ajukan peminjaman</Link>
                </Button>
            </template>
        </EmptyState>

        <TableShell v-else caption="Daftar pengajuan peminjaman">
            <thead>
                <tr>
                    <th>Referensi</th>
                    <th class="hidden md:table-cell">Kategori</th>
                    <th class="hidden lg:table-cell">Mulai</th>
                    <th class="hidden md:table-cell">Kembali</th>
                    <th class="hidden md:table-cell">Barang</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in requests.data" :key="item.id">
                    <td class="font-mono">
                        {{ item.reference_no ?? `#${item.id}` }}
                    </td>
                    <td class="hidden md:table-cell">
                        <StatusBadge
                            :label="
                                categoryLabel[item.category] ?? item.category
                            "
                            :tone="categoryTone[item.category] ?? 'neutral'"
                        />
                    </td>
                    <td class="hidden whitespace-nowrap lg:table-cell">
                        {{ formatDate(item.pickup_at) }}
                    </td>
                    <td class="hidden whitespace-nowrap md:table-cell">
                        {{ formatDate(item.return_at) }}
                    </td>
                    <td class="hidden md:table-cell">{{ item.items_count }}</td>
                    <td>
                        <StatusBadge
                            :label="statusLabel[item.status] ?? item.status"
                            :tone="statusTone[item.status] ?? 'neutral'"
                        />
                    </td>
                    <td class="text-right">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/peminjaman/${item.id}`">Detail</Link>
                        </Button>
                    </td>
                </tr>
            </tbody>
        </TableShell>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in requests.links" :key="link.label">
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
