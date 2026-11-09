<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';

type Category = {
    id: number;
    name: string;
    description: string | null;
    items_count: number;
};

defineProps<{ categories: Paginated<Category> }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Kategori', href: '/admin/inventory/categories' },
        ],
    },
});

const editing = ref<Category | null>(null);
const form = useForm({ name: '', description: '' });

function resetForm(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(category: Category): void {
    editing.value = category;
    form.clearErrors();
    form.name = category.name;
    form.description = category.description ?? '';
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editing.value) {
        form.put(`/admin/inventory/categories/${editing.value.id}`, options);
    } else {
        form.post('/admin/inventory/categories', options);
    }
}
</script>

<template>
    <Head title="Kategori" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Kategori</h1>
            <p class="text-sm text-muted-foreground">
                Kategori barang inventaris.
            </p>
        </div>

        <form
            class="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="md:col-span-2">
                <h2 class="font-medium">
                    {{ editing ? `Ubah ${editing.name}` : 'Tambah kategori' }}
                </h2>
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Keterangan</Label>
                <Input id="description" v-model="form.description" />
                <InputError :message="form.errors.description" />
            </div>

            <div class="flex gap-2 md:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? 'Simpan perubahan' : 'Tambah kategori' }}
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

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Keterangan</th>
                        <th class="p-3">Barang</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="category in categories.data"
                        :key="category.id"
                        class="border-t"
                    >
                        <td class="p-3">{{ category.name }}</td>
                        <td class="p-3">{{ category.description ?? '-' }}</td>
                        <td class="p-3">{{ category.items_count }}</td>
                        <td class="p-3 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="startEdit(category)"
                            >
                                Ubah
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="categories.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="4">
                            Belum ada kategori.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in categories.links" :key="link.label">
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
