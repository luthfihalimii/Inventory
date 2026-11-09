<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Division = {
    id: number;
    name: string;
    code: string;
    members_count: number;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

defineProps<{ divisions: Paginated<Division> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Divisi', href: '/wm/divisions' }],
    },
});

const editing = ref<Division | null>(null);

const form = useForm({
    name: '',
    code: '',
});

function startCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(division: Division): void {
    editing.value = division;
    form.clearErrors();
    form.name = division.name;
    form.code = division.code;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => startCreate(),
    };

    if (editing.value) {
        form.put(`/wm/divisions/${editing.value.id}`, options);
    } else {
        form.post('/wm/divisions', options);
    }
}
</script>

<template>
    <Head title="Divisi" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Divisi</h1>
            <p class="text-sm text-muted-foreground">
                Kelola divisi UKM Jurnalistik.
            </p>
        </div>

        <form
            class="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="md:col-span-2">
                <h2 class="font-medium">
                    {{ editing ? `Ubah ${editing.name}` : 'Tambah divisi' }}
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

            <div class="flex gap-2 md:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? 'Simpan perubahan' : 'Tambah divisi' }}
                </Button>
                <Button
                    v-if="editing"
                    type="button"
                    variant="outline"
                    @click="startCreate"
                >
                    Batal
                </Button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode</th>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Anggota</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="division in divisions.data"
                        :key="division.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ division.code }}</td>
                        <td class="p-3">{{ division.name }}</td>
                        <td class="p-3">{{ division.members_count }}</td>
                        <td class="p-3 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="startEdit(division)"
                            >
                                Ubah
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="divisions.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="4">
                            Belum ada divisi.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in divisions.links" :key="link.label">
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
