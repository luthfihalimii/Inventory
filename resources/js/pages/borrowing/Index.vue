<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

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

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Peminjaman Saya" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Peminjaman Saya</h1>
                <p class="text-sm text-muted-foreground">
                    Riwayat dan status pengajuan peminjaman.
                </p>
            </div>
            <Button as-child>
                <Link href="/peminjaman/baru">Ajukan peminjaman</Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Referensi</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3">Mulai</th>
                        <th class="p-3">Kembali</th>
                        <th class="p-3">Barang</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in requests.data"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            {{ item.reference_no ?? `#${item.id}` }}
                        </td>
                        <td class="p-3">
                            {{ categoryLabel[item.category] ?? item.category }}
                        </td>
                        <td class="p-3">{{ formatDate(item.pickup_at) }}</td>
                        <td class="p-3">{{ formatDate(item.return_at) }}</td>
                        <td class="p-3">{{ item.items_count }}</td>
                        <td class="p-3">
                            {{ statusLabel[item.status] ?? item.status }}
                        </td>
                        <td class="p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/peminjaman/${item.id}`"
                                    >Detail</Link
                                >
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="requests.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="7">
                            Belum ada pengajuan.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

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
