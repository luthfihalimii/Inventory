<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';

type Announcement = {
    id: number;
    title: string;
    content: string;
    status: 'draft' | 'published';
    published_at: string | null;
    creator?: { id: number; name: string } | null;
};

defineProps<{
    announcements: Paginated<Announcement>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pengumuman', href: '/admin/announcements' }],
    },
});

const form = useForm({ title: '', content: '' });
const editing = ref<Announcement | null>(null);
const editForm = useForm({ title: '', content: '' });

function submit(): void {
    form.post('/admin/announcements', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function startEdit(announcement: Announcement): void {
    editing.value = announcement;
    editForm.title = announcement.title;
    editForm.content = announcement.content;
}

function update(): void {
    if (!editing.value) {
        return;
    }

    editForm.put(`/admin/announcements/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function toggleStatus(announcement: Announcement): void {
    router.put(
        `/admin/announcements/${announcement.id}/status`,
        { status: announcement.status === 'published' ? 'draft' : 'published' },
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head title="Pengumuman" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Pengumuman</h1>
            <p class="text-sm text-muted-foreground">
                Pengumuman yang sudah dipublikasikan tampil di dashboard semua
                pengguna.
            </p>
        </div>

        <form class="grid gap-3 rounded-lg border p-4" @submit.prevent="submit">
            <h2 class="font-medium">Pengumuman baru</h2>
            <div class="grid gap-2">
                <Label for="title">Judul</Label>
                <Input id="title" v-model="form.title" />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="content">Isi</Label>
                <textarea
                    id="content"
                    v-model="form.content"
                    rows="4"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.content" />
            </div>
            <div>
                <Button type="submit" :disabled="form.processing">
                    Simpan sebagai draft
                </Button>
            </div>
        </form>

        <form
            v-if="editing"
            class="grid gap-3 rounded-lg border p-4"
            @submit.prevent="update"
        >
            <h2 class="font-medium">Ubah pengumuman</h2>
            <div class="grid gap-2">
                <Label for="edit_title">Judul</Label>
                <Input id="edit_title" v-model="editForm.title" />
                <InputError :message="editForm.errors.title" />
            </div>
            <div class="grid gap-2">
                <Label for="edit_content">Isi</Label>
                <textarea
                    id="edit_content"
                    v-model="editForm.content"
                    rows="4"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                />
                <InputError :message="editForm.errors.content" />
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="editForm.processing">
                    Simpan
                </Button>
                <Button type="button" variant="outline" @click="editing = null">
                    Batal
                </Button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Judul</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Dipublikasikan</th>
                        <th class="p-3">Pembuat</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="announcement in announcements.data"
                        :key="announcement.id"
                        class="border-t"
                    >
                        <td class="p-3">
                            {{ announcement.title }}
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                {{ announcement.content }}
                            </span>
                        </td>
                        <td class="p-3">
                            {{
                                announcement.status === 'published'
                                    ? 'Terbit'
                                    : 'Draft'
                            }}
                        </td>
                        <td class="p-3">
                            {{ formatDate(announcement.published_at) }}
                        </td>
                        <td class="p-3">
                            {{ announcement.creator?.name ?? '-' }}
                        </td>
                        <td class="p-3">
                            <div class="flex justify-end gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="startEdit(announcement)"
                                >
                                    Ubah
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="toggleStatus(announcement)"
                                >
                                    {{
                                        announcement.status === 'published'
                                            ? 'Tarik'
                                            : 'Terbitkan'
                                    }}
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="announcements.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="5">
                            Belum ada pengumuman.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in announcements.links" :key="link.label">
                <Button
                    v-if="link.url"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    as-child
                >
                    <a :href="link.url" v-html="link.label" />
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
