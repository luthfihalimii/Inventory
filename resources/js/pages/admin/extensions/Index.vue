<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

type Extension = {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    current_due_at: string;
    requested_due_at: string;
    reason: string;
    decided_at: string | null;
    decision_notes: string | null;
    requester?: { id: number; name: string } | null;
    decider?: { id: number; name: string } | null;
    loan: {
        id: number;
        status: string;
        due_at: string | null;
        reference_no: string | null;
        member: { name: string; nrp: string };
    };
};

defineProps<{ extensions: Paginated<Extension> }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Perpanjangan', href: '/admin/extensions' }],
    },
});

const error = computed(
    () => (usePage().props.errors as Record<string, string>).extension,
);

function approve(extension: Extension): void {
    if (
        !window.confirm(
            `Setujui perpanjangan ${extension.loan.reference_no ?? ''} sampai ${formatDate(extension.requested_due_at)}?`,
        )
    ) {
        return;
    }

    router.post(
        `/admin/extensions/${extension.id}/approve`,
        {},
        { preserveScroll: true },
    );
}

function reject(extension: Extension): void {
    const notes = window.prompt('Catatan penolakan (opsional):');

    if (notes === null) {
        return;
    }

    router.post(
        `/admin/extensions/${extension.id}/reject`,
        { decision_notes: notes || undefined },
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

const statusLabel: Record<string, string> = {
    pending: 'Menunggu',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};
</script>

<template>
    <Head title="Perpanjangan pinjaman" />

    <div class="flex flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Perpanjangan pinjaman</h1>
            <p class="text-sm text-muted-foreground">
                Persetujuan mengecek ulang ketersediaan barang untuk periode
                perpanjangan.
            </p>
        </div>

        <InputError :message="error" />

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Referensi</th>
                        <th class="p-3">Anggota</th>
                        <th class="p-3">Tenggat saat ini</th>
                        <th class="p-3">Tenggat baru</th>
                        <th class="p-3">Alasan</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="extension in extensions.data"
                        :key="extension.id"
                        class="border-t align-top"
                    >
                        <td class="p-3 font-mono">
                            {{ extension.loan.reference_no ?? '-' }}
                        </td>
                        <td class="p-3">
                            {{ extension.loan.member.name }}
                            <span class="block text-xs text-muted-foreground">
                                {{ extension.loan.member.nrp }}
                            </span>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{ formatDate(extension.current_due_at) }}
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{ formatDate(extension.requested_due_at) }}
                        </td>
                        <td class="p-3">{{ extension.reason }}</td>
                        <td class="p-3">
                            {{
                                statusLabel[extension.status] ??
                                extension.status
                            }}
                            <span
                                v-if="extension.decision_notes"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ extension.decision_notes }}
                            </span>
                        </td>
                        <td class="p-3">
                            <div
                                v-if="extension.status === 'pending'"
                                class="flex flex-wrap gap-2"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="approve(extension)"
                                >
                                    Setujui
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="reject(extension)"
                                >
                                    Tolak
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="extensions.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="7">
                            Belum ada pengajuan perpanjangan.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in extensions.links" :key="link.label">
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
