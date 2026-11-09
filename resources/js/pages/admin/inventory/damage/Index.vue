<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

type Report = {
    id: number;
    type: string;
    quantity: number | null;
    description: string;
    status: 'open' | 'resolved';
    resolution: string | null;
    resolution_notes: string | null;
    reported_at: string;
    resolved_at: string | null;
    item?: { id: number; code: string; name: string; tracking_method: string };
    asset?: { id: number; asset_code: string } | null;
    reporter?: { id: number; name: string } | null;
    resolver?: { id: number; name: string } | null;
};

const props = defineProps<{
    reports: Paginated<Report>;
    filters: { status: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kerusakan', href: '/admin/inventory/damage-reports' },
        ],
    },
});

const status = ref(props.filters.status);

function applyFilter(): void {
    router.get(
        '/admin/inventory/damage-reports',
        { status: status.value || undefined },
        { preserveState: true, replace: true },
    );
}

function resolve(report: Report, resolution: 'repaired' | 'written_off'): void {
    if (
        resolution === 'written_off' &&
        !window.confirm(
            'Hapuskan unit ini? Stok akan dikurangi dan tidak bisa dikembalikan.',
        )
    ) {
        return;
    }

    router.put(
        `/admin/inventory/damage-reports/${report.id}/resolve`,
        { resolution },
        { preserveScroll: true },
    );
}

const typeLabel: Record<string, string> = {
    damage: 'Rusak',
    loss: 'Hilang',
    incomplete: 'Tidak lengkap',
    maintenance: 'Perawatan',
};
</script>

<template>
    <Head title="Kerusakan" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Kerusakan &amp; kehilangan</h1>
            <p class="text-sm text-muted-foreground">
                Barang bermasalah tidak dihitung tersedia sampai diselesaikan.
            </p>
        </div>

        <form class="flex gap-2" @submit.prevent="applyFilter">
            <select
                v-model="status"
                class="h-9 rounded-md border bg-transparent px-3 text-sm"
            >
                <option value="">Semua</option>
                <option value="open">Terbuka</option>
                <option value="resolved">Selesai</option>
            </select>
            <Button type="submit" variant="outline">Terapkan</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Waktu</th>
                        <th class="p-3">Barang</th>
                        <th class="p-3">Jenis</th>
                        <th class="p-3">Jumlah</th>
                        <th class="p-3">Keterangan</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="report in reports.data"
                        :key="report.id"
                        class="border-t align-top"
                    >
                        <td class="p-3 whitespace-nowrap">
                            {{ report.reported_at }}
                        </td>
                        <td class="p-3">
                            {{ report.item?.name }}
                            <span class="block text-xs text-muted-foreground">
                                {{
                                    report.asset?.asset_code ??
                                    report.item?.code
                                }}
                            </span>
                        </td>
                        <td class="p-3">
                            {{ typeLabel[report.type] ?? report.type }}
                        </td>
                        <td class="p-3">{{ report.quantity ?? '-' }}</td>
                        <td class="p-3">{{ report.description }}</td>
                        <td class="p-3">
                            <span v-if="report.status === 'open'">Terbuka</span>
                            <span v-else>
                                Selesai ·
                                {{
                                    report.resolution === 'repaired'
                                        ? 'Diperbaiki'
                                        : 'Dihapuskan'
                                }}
                            </span>
                        </td>
                        <td class="flex flex-wrap gap-2 p-3">
                            <template v-if="report.status === 'open'">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="resolve(report, 'repaired')"
                                >
                                    Diperbaiki
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="resolve(report, 'written_off')"
                                >
                                    Dihapuskan
                                </Button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="reports.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="7">
                            Belum ada laporan.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in reports.links" :key="link.label">
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
