<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

type Block = {
    id: number;
    reason: string;
    started_at: string;
    ended_at: string | null;
    notes: string | null;
    member?: {
        id: number;
        name: string;
        nrp: string;
        borrowing_status: string;
    };
    loan_transaction?: {
        id: number;
        status: string;
        due_at: string | null;
        overdue_at: string | null;
        borrow_request?: { reference_no: string | null } | null;
    } | null;
};

const props = defineProps<{
    blocks: Paginated<Block>;
    filters: { status: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Anggota terblokir', href: '/wm/blocked' }],
    },
});

const status = ref(props.filters.status);

function applyFilter(): void {
    router.get(
        '/wm/blocked',
        { status: status.value },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Anggota terblokir" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Anggota terblokir"
            description="Blokir dibuat otomatis saat pinjaman terlambat dan dilepas setelah pengembalian diverifikasi. Tidak ada pembukaan manual."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form class="flex flex-wrap gap-3" @submit.prevent="applyFilter">
                <select
                    v-model="status"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option value="active">Aktif</option>
                    <option value="released">Sudah dilepas</option>
                    <option value="all">Semua</option>
                </select>
                <Button type="submit" variant="outline">Terapkan</Button>
            </form>
        </section>

        <TableShell caption="Daftar blokir anggota">
            <thead>
                <tr>
                    <th class="p-3">Anggota</th>
                    <th class="p-3">Referensi</th>
                    <th class="p-3">Mulai</th>
                    <th class="p-3">Tenggat</th>
                    <th class="p-3">Berakhir</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="block in blocks.data"
                    :key="block.id"
                    class="border-t"
                >
                    <td class="p-3">
                        {{ block.member?.name ?? '-' }}
                        <StatusBadge
                            class="ml-2"
                            :label="block.ended_at ? 'Dilepas' : 'Aktif'"
                            :tone="block.ended_at ? 'success' : 'danger'"
                        />
                        <span class="block text-xs text-muted-foreground">
                            {{ block.member?.nrp }}
                        </span>
                    </td>
                    <td class="p-3 font-mono">
                        {{
                            block.loan_transaction?.borrow_request
                                ?.reference_no ?? '-'
                        }}
                    </td>
                    <td class="p-3">{{ formatDate(block.started_at) }}</td>
                    <td class="p-3">
                        {{ formatDate(block.loan_transaction?.due_at ?? null) }}
                    </td>
                    <td class="p-3">
                        {{
                            block.ended_at
                                ? formatDate(block.ended_at)
                                : 'Aktif'
                        }}
                    </td>
                </tr>
                <tr v-if="blocks.data.length === 0">
                    <td class="p-3 text-muted-foreground" colspan="5">
                        Tidak ada blokir pada filter ini. Pilih Semua untuk
                        melihat riwayat blokir.
                    </td>
                </tr>
            </tbody>
        </TableShell>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in blocks.links" :key="link.label">
                <Button
                    v-if="link.url"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    as-child
                >
                    <a :href="link.url" v-html="link.label" />
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
