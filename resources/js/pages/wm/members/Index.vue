<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Division = { id: number; name: string; code: string };

type Member = {
    id: number;
    member_code: string;
    nrp: string;
    name: string;
    division_id: number;
    generation: number | null;
    position: string | null;
    notes: string | null;
    membership_status: string;
    borrowing_status: string;
    division?: Division;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    members: Paginated<Member>;
    divisions: Division[];
    filters: { q: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Anggota', href: '/wm/members' }],
    },
});

const search = ref(props.filters.q);
const editing = ref<Member | null>(null);

const form = useForm({
    member_code: '',
    name: '',
    nrp: '',
    division_id: props.divisions[0]?.id ?? null,
    generation: undefined as number | undefined,
    position: '',
    notes: '',
    membership_status: 'active',
    borrowing_status: 'eligible',
});

function submitSearch(): void {
    router.get(
        '/wm/members',
        { q: search.value },
        { preserveState: true, replace: true },
    );
}

function startCreate(): void {
    editing.value = null;
    form.reset();
    form.clearErrors();
}

function startEdit(member: Member): void {
    editing.value = member;
    form.clearErrors();
    form.member_code = member.member_code;
    form.name = member.name;
    form.nrp = member.nrp;
    form.division_id = member.division_id;
    form.generation = member.generation ?? undefined;
    form.position = member.position ?? '';
    form.notes = member.notes ?? '';
    form.membership_status = member.membership_status;
    form.borrowing_status = member.borrowing_status;
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => startCreate(),
    };

    if (editing.value) {
        form.put(`/wm/members/${editing.value.id}`, options);
    } else {
        form.post('/wm/members', options);
    }
}
</script>

<template>
    <Head title="Anggota" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Anggota</h1>
            <p class="text-sm text-muted-foreground">
                Kelola identitas anggota UKM Jurnalistik.
            </p>
        </div>

        <form class="flex gap-2" @submit.prevent="submitSearch">
            <Input v-model="search" placeholder="Cari nama atau NRP" />
            <Button type="submit" variant="outline">Cari</Button>
        </form>

        <form
            class="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="md:col-span-2">
                <h2 class="font-medium">
                    {{ editing ? `Ubah ${editing.name}` : 'Tambah anggota' }}
                </h2>
            </div>

            <div class="grid gap-2">
                <Label for="member_code">Kode anggota</Label>
                <Input id="member_code" v-model="form.member_code" required />
                <InputError :message="form.errors.member_code" />
            </div>

            <div class="grid gap-2">
                <Label for="nrp">NRP</Label>
                <Input id="nrp" v-model="form.nrp" required />
                <InputError :message="form.errors.nrp" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="division_id">Divisi</Label>
                <select
                    id="division_id"
                    v-model="form.division_id"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    required
                >
                    <option
                        v-for="division in divisions"
                        :key="division.id"
                        :value="division.id"
                    >
                        {{ division.name }}
                    </option>
                </select>
                <InputError :message="form.errors.division_id" />
            </div>

            <div class="grid gap-2">
                <Label for="generation">Angkatan</Label>
                <Input
                    id="generation"
                    v-model.number="form.generation"
                    type="number"
                />
                <InputError :message="form.errors.generation" />
            </div>

            <div class="grid gap-2">
                <Label for="position">Jabatan</Label>
                <Input id="position" v-model="form.position" />
                <InputError :message="form.errors.position" />
            </div>

            <div class="grid gap-2">
                <Label for="notes">Catatan</Label>
                <Input id="notes" v-model="form.notes" />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="grid gap-2">
                <Label for="membership_status">Status keanggotaan</Label>
                <select
                    id="membership_status"
                    v-model="form.membership_status"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>
                <InputError :message="form.errors.membership_status" />
            </div>

            <div class="grid gap-2">
                <Label for="borrowing_status">Status peminjaman</Label>
                <select
                    id="borrowing_status"
                    v-model="form.borrowing_status"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="eligible">Boleh meminjam</option>
                    <option value="borrowing_blocked">Diblokir</option>
                </select>
                <InputError :message="form.errors.borrowing_status" />
            </div>

            <div class="flex gap-2 md:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    {{ editing ? 'Simpan perubahan' : 'Tambah anggota' }}
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
                        <th class="p-3">NRP</th>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Divisi</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="member in members.data"
                        :key="member.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ member.nrp }}</td>
                        <td class="p-3">{{ member.name }}</td>
                        <td class="p-3">{{ member.division?.name }}</td>
                        <td class="p-3">
                            {{
                                member.membership_status === 'active'
                                    ? 'Aktif'
                                    : 'Nonaktif'
                            }}
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="startEdit(member)"
                            >
                                Ubah
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="members.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="5">
                            Belum ada anggota.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in members.links" :key="link.label">
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
