<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    filters: { from: string; to: string };
    counts: {
        loans: number;
        returns: number;
        damage: number;
        audit: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Laporan', href: '/admin/reports' }],
    },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const error = computed(
    () => (usePage().props.errors as Record<string, string>).from,
);

const exportTypes = [
    {
        type: 'loans',
        title: 'Pinjaman',
        description: 'Seluruh transaksi pinjaman pada rentang tanggal.',
    },
    {
        type: 'returns',
        title: 'Pengembalian',
        description: 'Permintaan pengembalian dan status verifikasinya.',
    },
    {
        type: 'damage',
        title: 'Kerusakan',
        description: 'Laporan kerusakan, biaya, dan vendor perbaikan.',
    },
    {
        type: 'audit',
        title: 'Audit',
        description: 'Jejak tindakan administratif (khusus pengawasan).',
    },
] as const;

function applyFilter(): void {
    router.get(
        '/admin/reports',
        { from: from.value || undefined, to: to.value || undefined },
        { preserveState: true, replace: true },
    );
}

function exportUrl(type: string): string {
    const params = new URLSearchParams({
        from: from.value,
        to: to.value,
    });

    return `/admin/reports/${type}.csv?${params.toString()}`;
}
</script>

<template>
    <Head title="Laporan" />

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Ekspor laporan"
            description="Unduh CSV untuk LPJ dan pengawasan. Filter tanggal memakai tanggal transaksi atau laporan masing-masing jenis."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="applyFilter"
            >
                <div class="grid gap-2">
                    <Label for="from">Dari</Label>
                    <Input
                        id="from"
                        v-model="from"
                        type="date"
                        class="h-11 rounded-xl"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="to">Sampai</Label>
                    <Input
                        id="to"
                        v-model="to"
                        type="date"
                        class="h-11 rounded-xl"
                    />
                </div>
                <Button type="submit" variant="outline">Terapkan</Button>
                <InputError :message="error" />
            </form>
        </section>

        <div class="grid gap-4 md:grid-cols-2">
            <div
                v-for="entry in exportTypes"
                :key="entry.type"
                class="flex flex-col gap-3 rounded-2xl border bg-card p-4"
            >
                <div>
                    <p class="font-medium">{{ entry.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ entry.description }}
                    </p>
                </div>
                <p class="text-2xl font-semibold">
                    {{ props.counts[entry.type] }}
                    <span class="text-sm font-normal text-muted-foreground">
                        baris
                    </span>
                </p>
                <div>
                    <Button variant="outline" as-child>
                        <a :href="exportUrl(entry.type)">Unduh CSV</a>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
