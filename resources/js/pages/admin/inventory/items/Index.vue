<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Availability, Paginated } from '@/types';

type Item = {
    id: number;
    code: string;
    name: string;
    tracking_method: 'serialized' | 'quantity';
    quantity_total: number | null;
    price: string | null;
    acquired_at: string | null;
    status: string;
    notes: string | null;
    category?: { id: number; name: string };
    location?: { id: number; name: string; code: string };
    availability: Availability;
};

const props = defineProps<{
    items: Paginated<Item>;
    categories: { id: number; name: string }[];
    locations: { id: number; name: string; code: string }[];
    filters: { q: string; status: string; category_id: number | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kelola barang', href: '/admin/inventory/items' },
        ],
    },
});

const search = ref(props.filters.q);
const status = ref(props.filters.status);
const categoryId = ref<number | null>(props.filters.category_id);
const editing = ref<Item | null>(null);
const selected = ref<number[]>([]);

const importForm = useForm<{ file: File | null }>({ file: null });

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    importForm.file = input.files?.[0] ?? null;
}

function submitImport(): void {
    importForm.post('/admin/inventory/items/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForm.reset(),
    });
}

function printSelected(): void {
    if (selected.value.length === 0) {
        return;
    }

    const params = new URLSearchParams();
    selected.value.forEach((id) => params.append('ids[]', String(id)));
    window.open(`/admin/inventory/items/qr?${params.toString()}`, '_blank');
}

const form = useForm<{
    code: string;
    name: string;
    category_id: number | null;
    location_id: number | null;
    tracking_method: 'serialized' | 'quantity';
    quantity_total: number | undefined;
    price: string;
    acquired_at: string;
    status: string;
    notes: string;
}>({
    code: '',
    name: '',
    category_id: props.categories[0]?.id ?? null,
    location_id: props.locations[0]?.id ?? null,
    tracking_method: 'serialized',
    quantity_total: undefined,
    price: '',
    acquired_at: '',
    status: 'active',
    notes: '',
});

watch(
    () => form.tracking_method,
    (method) => {
        if (method === 'serialized') {
            form.quantity_total = undefined;
        }
    },
);

function applyFilters(): void {
    router.get(
        '/admin/inventory/items',
        {
            q: search.value || undefined,
            status: status.value || undefined,
            category_id: categoryId.value ?? undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetForm(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(item: Item): void {
    editing.value = item;
    form.clearErrors();
    form.code = item.code;
    form.name = item.name;
    form.category_id = item.category?.id ?? null;
    form.location_id = item.location?.id ?? null;
    form.tracking_method = item.tracking_method;
    form.quantity_total = item.quantity_total ?? undefined;
    form.price = item.price ?? '';
    form.acquired_at = item.acquired_at ?? '';
    form.status = item.status;
    form.notes = item.notes ?? '';
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editing.value) {
        form.put(`/admin/inventory/items/${editing.value.id}`, options);
    } else {
        form.post('/admin/inventory/items', options);
    }
}
</script>

<template>
    <Head title="Kelola barang" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Kelola barang</h1>
            <p class="text-sm text-muted-foreground">
                Kategori, unit, dan ketersediaan inventaris.
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
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
            <Button type="submit" variant="outline">Terapkan</Button>
        </form>

        <form
            class="grid gap-3 rounded-lg border p-4"
            @submit.prevent="submitImport"
        >
            <div>
                <h2 class="font-medium">Impor CSV</h2>
                <p class="text-sm text-muted-foreground">
                    Header: code, name, category, location, tracking_method,
                    quantity_total, price, acquired_at, status, notes,
                    asset_codes. Beberapa kode unit dipisah titik koma (;). Satu
                    baris salah membatalkan seluruh impor.
                </p>
            </div>
            <Input
                id="import_file"
                type="file"
                accept=".csv,text/csv"
                @change="onFileChange"
            />
            <InputError :message="importForm.errors.file" />
            <div>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="importForm.processing || !importForm.file"
                >
                    Impor
                </Button>
            </div>
        </form>

        <form
            class="grid gap-4 rounded-lg border p-4 md:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="md:col-span-3">
                <h2 class="font-medium">
                    {{ editing ? `Ubah ${editing.name}` : 'Tambah barang' }}
                </h2>
            </div>

            <div class="grid gap-2">
                <Label for="code">Kode</Label>
                <Input id="code" v-model="form.code" required />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="tracking_method">Metode pelacakan</Label>
                <select
                    id="tracking_method"
                    v-model="form.tracking_method"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="serialized">Per unit</option>
                    <option value="quantity">Berdasarkan jumlah</option>
                </select>
                <InputError :message="form.errors.tracking_method" />
                <p
                    v-if="form.tracking_method === 'serialized'"
                    class="text-xs text-muted-foreground"
                >
                    Setelah disimpan, tambahkan unit di menu Unit aset agar bisa
                    dipinjam.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="category_id">Kategori</Label>
                <select
                    id="category_id"
                    v-model="form.category_id"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    required
                >
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <InputError :message="form.errors.category_id" />
            </div>

            <div class="grid gap-2">
                <Label for="location_id">Lokasi</Label>
                <select
                    id="location_id"
                    v-model="form.location_id"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    required
                >
                    <option
                        v-for="location in locations"
                        :key="location.id"
                        :value="location.id"
                    >
                        {{ location.name }} ({{ location.code }})
                    </option>
                </select>
                <InputError :message="form.errors.location_id" />
            </div>

            <div v-if="form.tracking_method === 'quantity'" class="grid gap-2">
                <Label for="quantity_total">Jumlah total</Label>
                <Input
                    id="quantity_total"
                    v-model.number="form.quantity_total"
                    type="number"
                    min="0"
                />
                <InputError :message="form.errors.quantity_total" />
            </div>

            <div class="grid gap-2">
                <Label for="price">Harga</Label>
                <Input
                    id="price"
                    v-model="form.price"
                    type="number"
                    min="0"
                    step="0.01"
                />
                <InputError :message="form.errors.price" />
            </div>

            <div class="grid gap-2">
                <Label for="acquired_at">Tanggal input</Label>
                <Input
                    id="acquired_at"
                    v-model="form.acquired_at"
                    type="date"
                />
                <InputError :message="form.errors.acquired_at" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <select
                    id="status"
                    v-model="form.status"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2 md:col-span-3">
                <Label for="notes">Keterangan</Label>
                <Input id="notes" v-model="form.notes" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex gap-2 md:col-span-3">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? 'Simpan perubahan' : 'Tambah barang' }}
                </Button>
                <Button
                    v-if="editing"
                    type="button"
                    variant="outline"
                    @click="resetForm"
                >
                    Batal
                </Button>
            </div>
        </form>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                {{ selected.length }} barang dipilih
            </p>
            <Button
                type="button"
                variant="outline"
                :disabled="selected.length === 0"
                @click="printSelected"
            >
                Cetak QR terpilih
            </Button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3"></th>
                        <th class="p-3">Kode</th>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Kategori</th>
                        <th class="p-3">Tersedia</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in items.data"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="item.id"
                                :aria-label="`Pilih ${item.name}`"
                            />
                        </td>
                        <td class="p-3 font-mono">{{ item.code }}</td>
                        <td class="p-3">{{ item.name }}</td>
                        <td class="p-3">{{ item.category?.name ?? '-' }}</td>
                        <td class="p-3">
                            {{ item.availability.available }} /
                            {{ item.availability.total }}
                        </td>
                        <td class="p-3">
                            {{
                                item.status === 'active' ? 'Aktif' : 'Nonaktif'
                            }}
                        </td>
                        <td class="flex flex-wrap justify-end gap-2 p-3">
                            <Button
                                v-if="item.tracking_method === 'serialized'"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="`/admin/inventory/assets?item_id=${item.id}`"
                                >
                                    Unit
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" as-child>
                                <Link
                                    :href="`/admin/inventory/items/${item.id}/qr`"
                                    target="_blank"
                                >
                                    QR
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="startEdit(item)"
                            >
                                Ubah
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="items.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="7">
                            Belum ada barang.
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
