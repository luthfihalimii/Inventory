<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';

type Location = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    items_count: number;
};

defineProps<{ locations: Paginated<Location> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Lokasi', href: '/admin/inventory/locations' }],
    },
});

const editing = ref<Location | null>(null);
const form = useForm({ name: '', code: '', description: '' });

function resetForm(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(location: Location): void {
    editing.value = location;
    form.clearErrors();
    form.name = location.name;
    form.code = location.code;
    form.description = location.description ?? '';
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editing.value) {
        form.put(`/admin/inventory/locations/${editing.value.id}`, options);
    } else {
        form.post('/admin/inventory/locations', options);
    }
}
</script>

<template>
    <Head title="Lokasi" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Lokasi"
            description="Kelola lokasi penyimpanan barang inventaris."
        />

        <form
            class="grid gap-4 rounded-2xl border bg-card p-5 md:grid-cols-3"
            @submit.prevent="submit"
        >
            <div class="md:col-span-3">
                <h2 class="font-medium">
                    {{ editing ? `Ubah ${editing.name}` : 'Tambah lokasi' }}
                </h2>
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="code">Kode</Label>
                <Input id="code" v-model="form.code" required />
                <InputError :message="form.errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Keterangan</Label>
                <Input id="description" v-model="form.description" />
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex gap-2 md:col-span-3">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? 'Simpan perubahan' : 'Tambah lokasi' }}
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

        <TableShell caption="Daftar lokasi penyimpanan">
            <thead>
                <tr>
                    <th class="p-3">Kode</th>
                    <th class="p-3">Nama</th>
                    <th class="p-3">Keterangan</th>
                    <th class="p-3">Barang</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="location in locations.data"
                    :key="location.id"
                    class="border-t"
                >
                    <td class="p-3 font-mono">{{ location.code }}</td>
                    <td class="p-3">{{ location.name }}</td>
                    <td class="p-3">{{ location.description ?? '-' }}</td>
                    <td class="p-3">{{ location.items_count }}</td>
                    <td class="p-3 text-right">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="startEdit(location)"
                        >
                            Ubah
                        </Button>
                    </td>
                </tr>
                <tr v-if="locations.data.length === 0">
                    <td class="p-3 text-muted-foreground" colspan="5">
                        Belum ada lokasi. Tambahkan lokasi melalui formulir agar
                        dapat dipilih saat menyimpan barang.
                    </td>
                </tr>
            </tbody>
        </TableShell>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in locations.links" :key="link.label">
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
