<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paginated } from '@/types';

type Transaction = {
    id: number;
    due_at: string;
    borrow_request: {
        reference_no: string | null;
        pickup_at: string;
        return_at: string;
    };
    member: { name: string; nrp: string };
};

const props = defineProps<{
    transactions: Paginated<Transaction>;
    filters: { q: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Checkout', href: '/admin/checkout' }],
    },
});

const q = ref(props.filters.q);

function search(): void {
    router.get(
        '/admin/checkout',
        { q: q.value || undefined },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Checkout barang" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Checkout barang</h1>
            <p class="text-sm text-muted-foreground">
                Pengajuan yang sudah disetujui dan siap diserahkan.
            </p>
        </div>

        <form class="flex gap-2" @submit.prevent="search">
            <Input v-model="q" placeholder="Cari nomor referensi" />
            <Button type="submit" variant="outline">Cari</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Referensi</th>
                        <th class="p-3">Anggota</th>
                        <th class="p-3">Mulai</th>
                        <th class="p-3">Tenggat</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in transactions.data"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">
                            {{
                                item.borrow_request?.reference_no ??
                                `#${item.id}`
                            }}
                        </td>
                        <td class="p-3">
                            {{ item.member?.name ?? '-' }}
                            <span class="block text-xs text-muted-foreground">
                                {{ item.member?.nrp }}
                            </span>
                        </td>
                        <td class="p-3">
                            {{ formatDate(item.borrow_request.pickup_at) }}
                        </td>
                        <td class="p-3">{{ formatDate(item.due_at) }}</td>
                        <td class="p-3 text-right">
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="`/admin/checkout/${item.id}`">
                                    Serahkan
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="transactions.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="5">
                            Tidak ada barang yang menunggu diserahkan.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in transactions.links" :key="link.label">
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
