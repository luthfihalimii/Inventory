<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Availability, Paginated } from '@/types';

type CatalogItem = {
    id: number;
    code: string;
    name: string;
    tracking_method: 'serialized' | 'quantity';
    quantity_total: number | null;
    status: string;
    notes: string | null;
    category?: { id: number; name: string };
    location?: { id: number; name: string; code: string };
    availability: Availability;
};

const props = defineProps<{
    items: Paginated<CatalogItem>;
    categories: { id: number; name: string }[];
    filters: { q: string; category_id: number | null; status: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventaris', href: '/inventory' }],
    },
});

const search = ref(props.filters.q);
const categoryId = ref<number | null>(props.filters.category_id);
const status = ref(props.filters.status || 'active');

function applyFilters(): void {
    router.get(
        '/inventory',
        {
            q: search.value || undefined,
            category_id: categoryId.value ?? undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Inventaris" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Inventaris</h1>
            <p class="text-sm text-muted-foreground">
                Katalog barang UKM Jurnalistik dan ketersediaannya.
            </p>
        </div>

        <form class="grid gap-2 md:grid-cols-4" @submit.prevent="applyFilters">
            <Input v-model="search" placeholder="Cari nama atau kode" />
            <select
                v-model="categoryId"
                class="h-9 rounded-md border bg-transparent px-3 text-sm"
            >
                <option :value="null">Semua kategori</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-transparent px-3 text-sm"
            >
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="">Semua status</option>
            </select>
            <Button type="submit" variant="outline">Terapkan</Button>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode</th>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3">Lokasi</th>
                        <th class="p-3">Tersedia</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items.data"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ item.code }}</td>
                        <td class="p-3">
                            <Link
                                :href="`/inventory/${item.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ item.name }}
                            </Link>
                        </td>
                        <td class="p-3">{{ item.category?.name ?? '-' }}</td>
                        <td class="p-3">{{ item.location?.name ?? '-' }}</td>
                        <td class="p-3">
                            {{ item.availability.available }} /
                            {{ item.availability.total }}
                        </td>
                        <td class="p-3">
                            {{
                                item.status === 'active' ? 'Aktif' : 'Nonaktif'
                            }}
                        </td>
                    </tr>
                    <tr v-if="items.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="6">
                            Tidak ada barang yang cocok.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in items.links" :key="link.label">
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
