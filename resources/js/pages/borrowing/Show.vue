<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

type BorrowRequest = {
    id: number;
    reference_no: string | null;
    category: string;
    pickup_at: string;
    return_at: string;
    usage_location: string | null;
    purpose: string | null;
    notes: string | null;
    status: string;
    submitted_at: string;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    approval: {
        decision: string;
        notes: string | null;
        decided_at: string;
    } | null;
    loan_status: string | null;
    items: {
        item_id: number;
        code: string | null;
        name: string | null;
        quantity: number;
        notes: string | null;
    }[];
};

const props = defineProps<{ request: BorrowRequest }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Peminjaman Saya', href: '/peminjaman' },
            { title: 'Detail', href: '#' },
        ],
    },
});

const statusLabel: Record<string, string> = {
    pending_approval: 'Menunggu persetujuan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
};

const categoryLabel: Record<string, string> = {
    scheduled: 'Terjadwal',
    urgent: 'Mendadak',
};

const canCancel = computed(
    () =>
        ['pending_approval', 'approved'].includes(props.request.status) &&
        (!props.request.loan_status ||
            props.request.loan_status === 'reserved'),
);
const cancelError = computed(() => {
    const errors = usePage().props.errors as Record<string, string>;

    return errors.reason ?? errors.status;
});

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function cancel(): void {
    const reason = window.prompt('Alasan pembatalan:');

    if (!reason) {
        return;
    }

    router.put(`/peminjaman/${props.request.id}/cancel`, { reason });
}
</script>

<template>
    <Head :title="request.reference_no ?? 'Detail pengajuan'" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ request.reference_no ?? `#${request.id}` }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ categoryLabel[request.category] ?? request.category }} ·
                    {{ statusLabel[request.status] ?? request.status }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link href="/peminjaman">Kembali</Link>
                </Button>
                <Button v-if="canCancel" variant="destructive" @click="cancel">
                    Batalkan
                </Button>
            </div>
        </div>

        <InputError :message="cancelError" />

        <dl class="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Mulai pinjam</dt>
                <dd>{{ formatDate(request.pickup_at) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Tenggat kembali</dt>
                <dd>{{ formatDate(request.return_at) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Lokasi penggunaan</dt>
                <dd>{{ request.usage_location || '-' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Diajukan</dt>
                <dd>{{ formatDate(request.submitted_at) }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-muted-foreground">Tujuan / alasan</dt>
                <dd>{{ request.purpose || '-' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-muted-foreground">Keterangan</dt>
                <dd>{{ request.notes || '-' }}</dd>
            </div>
        </dl>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode</th>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Jumlah</th>
                        <th class="p-3">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="line in request.items"
                        :key="line.item_id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ line.code ?? '-' }}</td>
                        <td class="p-3">{{ line.name ?? '-' }}</td>
                        <td class="p-3">{{ line.quantity }}</td>
                        <td class="p-3">{{ line.notes || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="request.approval" class="rounded-lg border p-4 text-sm">
            <p class="font-medium">
                Keputusan:
                {{
                    request.approval.decision === 'approved'
                        ? 'Disetujui'
                        : 'Ditolak'
                }}
            </p>
            <p class="text-muted-foreground">
                {{ formatDate(request.approval.decided_at) }}
            </p>
            <p v-if="request.approval.notes" class="mt-2">
                {{ request.approval.notes }}
            </p>
        </div>

        <div
            v-if="request.status === 'cancelled'"
            class="rounded-lg border p-4 text-sm"
        >
            <p class="font-medium">Dibatalkan</p>
            <p v-if="request.cancelled_at" class="text-muted-foreground">
                {{ formatDate(request.cancelled_at) }}
            </p>
            <p v-if="request.cancellation_reason" class="mt-2">
                {{ request.cancellation_reason }}
            </p>
        </div>
    </div>
</template>
