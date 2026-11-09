<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

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
</script>

<template>
    <Head title="Pengembalian" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Pengembalian</h1>
            <p class="text-sm text-muted-foreground">
                Antrean verifikasi dan inspeksi barang kembali.
            </p>
        </div>

        <form class="flex gap-2" @submit.prevent="applyFilter">
            <select
                v-model="status"
                class="h-9 rounded-md border bg-transparent px-3 text-sm"
            >
                <option value="pending">Menunggu</option>
                <option value="verified">Terverifikasi</option>
                <option value="cancelled">Dibatalkan</option>
            </select>
            <Button type="submit" variant="outline">Terapkan</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Referensi</th>
                        <th class="p-3">Anggota</th>
                        <th class="p-3">Diajukan</th>
                        <th class="p-3">Tenggat</th>
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
                        <td class="p-3">{{ formatDate(item.requested_at) }}</td>
                        <td class="p-3">
                            {{
                                item.loan_transaction?.due_at
                                    ? formatDate(item.loan_transaction.due_at)
                                    : '-'
                            }}
                        </td>
                        <td class="p-3">
                            {{ statusLabel[item.status] ?? item.status }}
                        </td>
                        <td class="p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/admin/returns/${item.id}`">
                                    Periksa
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="requests.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="6">
                            Tidak ada pengembalian.
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
