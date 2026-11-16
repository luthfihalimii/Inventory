<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import type { Paginated, StatusTone } from '@/types';

type ReturnRequest = {
    id: number;
    status: string;
    requested_at: string;
    verified_at: string | null;
    loan_transaction: {
        id: number;
        status: string;
        due_at: string | null;
        borrow_request: { reference_no: string | null };
        member: { name: string; nrp: string };
    };
    requester: { name: string } | null;
};

const props = defineProps<{
    requests: Paginated<ReturnRequest>;
    filters: { status: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pengembalian', href: '/admin/returns' }],
    },
});

const status = ref(props.filters.status);

function applyFilter(): void {
    router.get(
        '/admin/returns',
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
    pending: 'Menunggu verifikasi',
    verified: 'Terverifikasi',
    cancelled: 'Dibatalkan',
};

const statusTone: Record<string, StatusTone> = {
    pending: 'warning',
    verified: 'success',
    cancelled: 'neutral',
};
</script>

<template>
    <Head title="Pengembalian" />

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Pengembalian"
            description="Antrean verifikasi dan inspeksi barang kembali."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form class="flex flex-wrap gap-2" @submit.prevent="applyFilter">
                <select
                    v-model="status"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option value="pending">Menunggu</option>
                    <option value="verified">Terverifikasi</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
                <Button type="submit" variant="outline">Terapkan</Button>
            </form>
        </section>

        <EmptyState
            v-if="requests.data.length === 0"
            title="Tidak ada pengembalian."
            description="Belum ada pengembalian pada status ini. Periksa status lain untuk melanjutkan verifikasi."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/admin/returns?status="
                        >Lihat semua pengembalian</Link
                    >
                </Button>
            </template>
        </EmptyState>

        <TableShell
            v-else
            caption="Daftar permintaan pengembalian dan status verifikasi"
        >
            <thead>
                <tr>
                    <th class="p-3">Referensi</th>
                    <th class="p-3">Anggota</th>
                    <th class="hidden p-3 lg:table-cell">Diajukan</th>
                    <th class="hidden p-3 md:table-cell">Tenggat</th>
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
                        {{
                            item.loan_transaction?.borrow_request
                                ?.reference_no ?? `#${item.id}`
                        }}
                    </td>
                    <td class="p-3">
                        {{ item.loan_transaction?.member?.name ?? '-' }}
                        <span class="block text-xs text-muted-foreground">
                            {{ item.loan_transaction?.member?.nrp }}
                        </span>
                    </td>
                    <td class="hidden p-3 lg:table-cell">
                        {{ formatDate(item.requested_at) }}
                    </td>
                    <td class="hidden p-3 md:table-cell">
                        {{
                            item.loan_transaction?.due_at
                                ? formatDate(item.loan_transaction.due_at)
                                : '-'
                        }}
                    </td>
                    <td class="p-3">
                        <StatusBadge
                            :label="statusLabel[item.status] ?? item.status"
                            :tone="statusTone[item.status] ?? 'neutral'"
                        />
                    </td>
                    <td class="p-3 text-right">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="`/admin/returns/${item.id}`">
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
