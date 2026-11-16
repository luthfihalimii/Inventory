<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import type { Paginated, StatusTone } from '@/types';

type ApprovalRequest = {
    id: number;
    reference_no: string | null;
    category: string;
    pickup_at: string;
    return_at: string;
    status: string;
    submitted_at: string;
    member?: { id: number; name: string; nrp: string };
    items: { item_id: number; quantity: number }[];
};

const props = defineProps<{
    requests: Paginated<ApprovalRequest>;
    filters: { status: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Approval', href: '/admin/approval' }],
    },
});

const status = ref(props.filters.status);

function applyFilter(): void {
    router.get(
        '/admin/approval',
        { status: status.value },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

const statusLabel: Record<string, string> = {
    pending_approval: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const statusTone: Record<string, StatusTone> = {
    pending_approval: 'warning',
    approved: 'success',
    rejected: 'danger',
    cancelled: 'neutral',
};
</script>

<template>
    <Head title="Approval pengajuan" />

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Approval pengajuan"
            description="Pengajuan mendadak ditampilkan lebih dulu."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form class="flex flex-wrap gap-2" @submit.prevent="applyFilter">
                <select
                    v-model="status"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option value="pending_approval">Menunggu</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="cancelled">Dibatalkan</option>
                    <option value="">Semua</option>
                </select>
                <Button type="submit" variant="outline">Terapkan</Button>
            </form>
        </section>

        <EmptyState
            v-if="requests.data.length === 0"
            title="Tidak ada pengajuan."
            description="Ubah filter status untuk melihat pengajuan lain."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/admin/approval?status="
                        >Lihat semua pengajuan</Link
                    >
                </Button>
            </template>
        </EmptyState>

        <TableShell
            v-else
            caption="Daftar pengajuan peminjaman yang menunggu atau telah diputuskan"
        >
            <thead>
                <tr>
                    <th class="p-3">Referensi</th>
                    <th class="p-3">Anggota</th>
                    <th class="hidden p-3 md:table-cell">Kategori</th>
                    <th class="hidden p-3 lg:table-cell">Mulai</th>
                    <th class="hidden p-3 lg:table-cell">Kembali</th>
                    <th class="hidden p-3 md:table-cell">Barang</th>
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
                        {{ item.member?.name ?? '-' }}
                        <span class="block text-xs text-muted-foreground">
                            {{ item.member?.nrp }}
                        </span>
                    </td>
                    <td class="hidden p-3 md:table-cell">
                        <span
                            v-if="item.category === 'urgent'"
                            class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900"
                        >
                            Mendadak
                        </span>
                        <span v-else>Terjadwal</span>
                    </td>
                    <td class="hidden p-3 lg:table-cell">
                        {{ formatDate(item.pickup_at) }}
                    </td>
                    <td class="hidden p-3 lg:table-cell">
                        {{ formatDate(item.return_at) }}
                    </td>
                    <td class="hidden p-3 md:table-cell">
                        {{ item.items.length }}
                    </td>
                    <td class="p-3">
                        <StatusBadge
                            :label="statusLabel[item.status] ?? item.status"
                            :tone="statusTone[item.status] ?? 'neutral'"
                        />
                    </td>
                    <td class="p-3 text-right">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/admin/approval/${item.id}`">
                                Periksa
                            </Link>
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
