<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { MapPin, Tag } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
const isDefaultView = computed(
    () =>
        !props.filters.q &&
        !props.filters.category_id &&
        ['', 'active'].includes(props.filters.status),
);

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

    <div class="flex min-w-0 flex-col gap-6 p-4">
        <PageHeader
            title="Inventaris"
            description="Katalog barang UKM Jurnalistik dan ketersediaannya."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <Input
                    v-model="search"
                    class="h-11 rounded-xl"
                    placeholder="Cari nama atau kode"
                />
                <select
                    v-model="categoryId"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
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
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                    <option value="">Semua status</option>
                </select>
                <Button class="h-11 rounded-xl" type="submit" variant="outline">
                    Terapkan
                </Button>
            </form>
        </section>

        <EmptyState
            v-if="items.data.length === 0 && isDefaultView"
            title="Belum ada barang di inventaris."
            description="Barang yang ditambahkan admin akan tampil di katalog ini."
        />
        <EmptyState
            v-else-if="items.data.length === 0"
            title="Tidak ada barang yang cocok."
            description="Ubah kata kunci atau filter, lalu coba lagi."
        >
            <template #actions>
                <Button class="h-11 rounded-xl" variant="outline" as-child>
                    <Link href="/inventory">Reset filter</Link>
                </Button>
            </template>
        </EmptyState>

        <div v-else class="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
            <article
                v-for="item in items.data"
                :key="item.id"
                class="flex min-w-0 flex-col gap-3 rounded-2xl border bg-card p-3 sm:gap-4 sm:p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="font-mono text-xs text-muted-foreground">
                        {{ item.code }}
                    </p>
                    <StatusBadge
                        :label="
                            item.status === 'active' ? 'Tersedia' : 'Nonaktif'
                        "
                        :tone="item.status === 'active' ? 'success' : 'neutral'"
                    />
                </div>
                <Link
                    :href="`/inventory/${item.id}`"
                    class="font-semibold hover:text-primary"
                >
                    {{ item.name }}
                </Link>
                <div class="grid gap-2 text-sm text-muted-foreground">
                    <p class="flex items-center gap-2">
                        <Tag class="size-4 shrink-0" aria-hidden="true" />
                        {{ item.category?.name ?? '-' }}
                    </p>
                    <p class="flex items-center gap-2">
                        <MapPin class="size-4 shrink-0" aria-hidden="true" />
                        {{ item.location?.name ?? '-' }}
                    </p>
                </div>
                <div
                    class="mt-auto flex flex-wrap items-center justify-between gap-2"
                >
                    <p class="text-sm font-medium">
                        Tersedia {{ item.availability.available }} dari
                        {{ item.availability.total }}
                    </p>
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/inventory/${item.id}`"
                            >Lihat detail</Link
                        >
                    </Button>
                </div>
            </article>
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
