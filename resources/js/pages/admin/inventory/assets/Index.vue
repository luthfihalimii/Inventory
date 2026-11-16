<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated, StatusTone } from '@/types';

const assetStatusLabel: Record<string, string> = {
    available: 'Tersedia',
    borrowed: 'Dipinjam',
    damaged: 'Rusak',
    maintenance: 'Perawatan',
    lost: 'Hilang',
    inactive: 'Nonaktif',
};

const assetStatusTone: Record<string, StatusTone> = {
    available: 'success',
    borrowed: 'info',
    damaged: 'danger',
    maintenance: 'warning',
    lost: 'danger',
    inactive: 'neutral',
};

type Asset = {
    id: number;
    asset_code: string;
    serial_number: string | null;
    condition: string;
    status: string;
    notes: string | null;
    item?: { id: number; code: string; name: string };
};

const props = defineProps<{
    assets: Paginated<Asset>;
    items: { id: number; code: string; name: string }[];
    filters: { q: string; item_id: number | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Unit aset', href: '/admin/inventory/assets' }],
    },
});

const search = ref(props.filters.q);
const itemId = ref<number | null>(props.filters.item_id);
const editing = ref<Asset | null>(null);

const form = useForm({
    item_id: props.filters.item_id ?? props.items[0]?.id ?? null,
    asset_code: '',
    serial_number: '',
    condition: 'good',
    status: 'available',
    notes: '',
});

function applyFilters(): void {
    router.get(
        '/admin/inventory/assets',
        {
            q: search.value || undefined,
            item_id: itemId.value ?? undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetForm(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(asset: Asset): void {
    editing.value = asset;
    form.clearErrors();
    form.asset_code = asset.asset_code;
    form.serial_number = asset.serial_number ?? '';
    form.condition = asset.condition;
    form.status = asset.status;
    form.notes = asset.notes ?? '';
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editing.value) {
        form.put(`/admin/inventory/assets/${editing.value.id}`, options);
    } else {
        form.post('/admin/inventory/assets', options);
    }
}
</script>

<template>
    <Head title="Unit aset" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Unit aset"
            description="Kelola unit fisik untuk barang yang dilacak per unit."
        />

        <section class="rounded-2xl border bg-card p-4">
            <form
                class="grid gap-3 md:grid-cols-3"
                @submit.prevent="applyFilters"
            >
                <Input
                    v-model="search"
                    placeholder="Cari kode aset atau serial"
                />
                <select
                    v-model="itemId"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option :value="null">Semua barang</option>
                    <option
                        v-for="item in items"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.code }})
                    </option>
                </select>
                <Button type="submit" variant="outline">Terapkan</Button>
            </form>
        </section>

        <form
            class="grid gap-4 rounded-2xl border bg-card p-5 md:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="md:col-span-3">
                <h2 class="font-medium">
                    {{ editing ? `Ubah ${editing.asset_code}` : 'Tambah unit' }}
                </h2>
            </div>

            <div v-if="!editing" class="grid gap-2">
                <Label for="item_id">Barang</Label>
                <select
                    id="item_id"
                    v-model="form.item_id"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                    required
                >
                    <option :value="null" disabled>Pilih barang</option>
                    <option
                        v-for="item in items"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.code }})
                    </option>
                </select>
                <InputError :message="form.errors.item_id" />
            </div>

            <div class="grid gap-2">
                <Label for="asset_code">Kode aset</Label>
                <Input id="asset_code" v-model="form.asset_code" required />
                <InputError :message="form.errors.asset_code" />
            </div>

            <div class="grid gap-2">
                <Label for="serial_number">Serial number</Label>
                <Input id="serial_number" v-model="form.serial_number" />
                <InputError :message="form.errors.serial_number" />
            </div>

            <div class="grid gap-2">
                <Label for="condition">Kondisi</Label>
                <select
                    id="condition"
                    v-model="form.condition"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option value="good">Baik</option>
                    <option value="damaged">Rusak</option>
                </select>
                <InputError :message="form.errors.condition" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <select
                    id="status"
                    v-model="form.status"
                    class="h-11 rounded-xl border bg-transparent px-3 text-sm"
                >
                    <option value="available">Tersedia</option>
                    <option value="borrowed">Dipinjam</option>
                    <option value="damaged">Rusak</option>
                    <option value="maintenance">Perawatan</option>
                    <option value="lost">Hilang</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="notes">Keterangan</Label>
                <Input id="notes" v-model="form.notes" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex gap-2 md:col-span-3">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? 'Simpan perubahan' : 'Tambah unit' }}
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

        <TableShell caption="Daftar unit aset">
            <thead>
                <tr>
                    <th class="p-3">Kode aset</th>
                    <th class="p-3">Barang</th>
                    <th class="p-3">Serial</th>
                    <th class="p-3">Kondisi</th>
                    <th class="p-3">Status</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="asset in assets.data"
                    :key="asset.id"
                    class="border-t"
                >
                    <td class="p-3 font-mono">{{ asset.asset_code }}</td>
                    <td class="p-3">{{ asset.item?.name ?? '-' }}</td>
                    <td class="p-3">{{ asset.serial_number ?? '-' }}</td>
                    <td class="p-3">
                        <StatusBadge
                            :label="
                                asset.condition === 'good' ? 'Baik' : 'Rusak'
                            "
                            :tone="
                                asset.condition === 'good'
                                    ? 'success'
                                    : 'danger'
                            "
                        />
                    </td>
                    <td class="p-3">
                        <StatusBadge
                            :label="
                                assetStatusLabel[asset.status] ?? asset.status
                            "
                            :tone="assetStatusTone[asset.status] ?? 'neutral'"
                        />
                    </td>
                    <td class="p-3 text-right">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="startEdit(asset)"
                        >
                            Ubah
                        </Button>
                    </td>
                </tr>
                <tr v-if="assets.data.length === 0">
                    <td class="p-3 text-muted-foreground" colspan="6">
                        Tidak ada unit yang cocok. Ubah filter atau tambahkan
                        unit melalui formulir.
                    </td>
                </tr>
            </tbody>
        </TableShell>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in assets.links" :key="link.label">
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
