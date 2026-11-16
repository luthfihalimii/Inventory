<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    loan: {
        id: number;
        status: string;
        checked_out_at: string | null;
        due_at: string | null;
        completed_at: string | null;
        return_status: string | null;
    } | null;
    items: {
        item_id: number;
        code: string | null;
        name: string | null;
        quantity: number;
        notes: string | null;
    }[];
    extension: {
        id: number;
        status: string;
        current_due_at: string;
        requested_due_at: string;
        reason: string;
        decided_at: string | null;
        decision_notes: string | null;
    } | null;
    canRequestExtension: boolean;
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

const canRequestReturn = computed(
    () =>
        props.request.loan !== null &&
        ['borrowed', 'overdue'].includes(props.request.loan.status),
);

const loanError = computed(
    () => (usePage().props.errors as Record<string, string>).status,
);

const extensionLoanError = computed(
    () => (usePage().props.errors as Record<string, string>).loan,
);

const loanStatusLabel: Record<string, string> = {
    reserved: 'Menunggu penyerahan',
    borrowed: 'Dipinjam',
    overdue: 'Terlambat',
    return_pending_verification: 'Menunggu verifikasi pengembalian',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const returnStatusLabel: Record<string, string> = {
    pending: 'Menunggu verifikasi',
    verified: 'Terverifikasi',
    cancelled: 'Dibatalkan',
};

const extensionStatusLabel: Record<string, string> = {
    pending: 'Menunggu keputusan',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

const showExtensionForm = ref(false);
const extensionForm = useForm({
    requested_due_at: '',
    reason: '',
});

const minDue = computed(() => {
    if (!props.request.loan?.due_at) {
        return undefined;
    }

    const date = new Date(props.request.loan.due_at);
    date.setMinutes(date.getMinutes() + 1);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
});

function submitExtension(): void {
    if (!props.request.loan) {
        return;
    }

    extensionForm.post(`/peminjaman/${props.request.loan.id}/extensions`, {
        preserveScroll: true,
        onSuccess: () => {
            extensionForm.reset();
            showExtensionForm.value = false;
        },
    });
}

function requestReturn(): void {
    if (!props.request.loan || !window.confirm('Ajukan pengembalian barang?')) {
        return;
    }

    router.post(
        `/peminjaman/${props.request.loan.id}/return`,
        {},
        { preserveScroll: true },
    );
}

const statusTone: Record<string, 'warning' | 'success' | 'danger' | 'neutral'> =
    {
        pending_approval: 'warning',
        approved: 'success',
        rejected: 'danger',
        cancelled: 'neutral',
    };

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

    <div
        class="flex min-w-0 flex-col gap-6 p-4 [&_button]:h-11 [&_button]:rounded-xl"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-[22px] leading-tight font-bold tracking-tight md:text-[28px]"
                    >
                        {{ request.reference_no ?? `#${request.id}` }}
                    </h1>
                    <StatusBadge
                        :label="statusLabel[request.status] ?? request.status"
                        :tone="statusTone[request.status] ?? 'neutral'"
                    />
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ categoryLabel[request.category] ?? request.category }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link href="/peminjaman">Kembali</Link>
                </Button>
                <Button v-if="canCancel" variant="destructive" @click="cancel">
                    Batalkan pengajuan
                </Button>
            </div>
        </div>

        <InputError :message="cancelError" />

        <dl
            class="grid gap-4 rounded-2xl border bg-card p-4 text-sm sm:grid-cols-2 md:p-5"
        >
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

        <TableShell caption="Barang yang diajukan">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Jumlah</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in request.items" :key="line.item_id">
                    <td class="font-mono">{{ line.code ?? '-' }}</td>
                    <td>{{ line.name ?? '-' }}</td>
                    <td>{{ line.quantity }}</td>
                    <td>{{ line.notes || '-' }}</td>
                </tr>
            </tbody>
        </TableShell>

        <section class="rounded-2xl border bg-card p-4 md:p-5">
            <h2 class="font-semibold">Riwayat pengajuan dan pinjaman</h2>
            <ol class="mt-4 space-y-4 border-l pl-4 text-sm">
                <li class="relative">
                    <span
                        class="absolute top-1 -left-[21px] size-2 rounded-full bg-primary"
                        aria-hidden="true"
                    />
                    <p class="font-medium">Pengajuan dikirim</p>
                    <p class="text-muted-foreground">
                        {{ formatDate(request.submitted_at) }}
                    </p>
                </li>
                <li class="relative">
                    <span
                        class="absolute top-1 -left-[21px] size-2 rounded-full bg-primary"
                        aria-hidden="true"
                    />
                    <p class="font-medium">Persetujuan pengajuan</p>
                    <p v-if="request.approval" class="text-muted-foreground">
                        {{ formatDate(request.approval.decided_at) }}
                    </p>
                </li>
                <li v-if="request.loan" class="relative">
                    <span
                        class="absolute top-1 -left-[21px] size-2 rounded-full bg-primary"
                        aria-hidden="true"
                    />
                    <p class="font-medium">
                        Status pinjaman:
                        {{
                            loanStatusLabel[request.loan.status] ??
                            request.loan.status
                        }}
                    </p>
                    <p
                        v-if="request.loan.checked_out_at"
                        class="text-muted-foreground"
                    >
                        Diserahkan:
                        {{ formatDate(request.loan.checked_out_at) }}
                    </p>
                </li>
            </ol>
        </section>

        <div
            v-if="request.approval"
            class="rounded-2xl border bg-card p-4 text-sm md:p-5"
        >
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
            v-if="request.loan"
            class="rounded-2xl border bg-card p-4 text-sm md:p-5"
        >
            <p class="font-medium">Detail pinjaman</p>
            <p v-if="request.loan.checked_out_at" class="text-muted-foreground">
                Diserahkan: {{ formatDate(request.loan.checked_out_at) }}
            </p>
            <p v-if="request.loan.due_at" class="text-muted-foreground">
                Tenggat: {{ formatDate(request.loan.due_at) }}
            </p>
            <p v-if="request.loan.return_status" class="text-muted-foreground">
                Pengembalian:
                {{
                    returnStatusLabel[request.loan.return_status] ??
                    request.loan.return_status
                }}
            </p>
            <div v-if="canRequestReturn" class="mt-3">
                <Button @click="requestReturn">Ajukan pengembalian</Button>
            </div>
            <InputError :message="loanError" />

            <div
                v-if="request.extension"
                class="mt-3 rounded border p-3 text-sm"
            >
                <p class="font-medium">
                    Perpanjangan:
                    {{
                        extensionStatusLabel[request.extension.status] ??
                        request.extension.status
                    }}
                </p>
                <p class="text-muted-foreground">
                    Tenggat diminta:
                    {{ formatDate(request.extension.requested_due_at) }}
                </p>
                <p v-if="request.extension.decision_notes" class="mt-1">
                    {{ request.extension.decision_notes }}
                </p>
            </div>

            <div v-if="request.canRequestExtension" class="mt-3 grid gap-2">
                <Button
                    v-if="!showExtensionForm"
                    variant="outline"
                    @click="showExtensionForm = true"
                >
                    Ajukan perpanjangan
                </Button>
                <form
                    v-else
                    class="grid gap-3 rounded-xl border p-4 [&_input]:h-11 [&_input]:rounded-xl [&_textarea]:rounded-xl"
                    @submit.prevent="submitExtension"
                >
                    <Label for="requested_due_at">Tenggat baru</Label>
                    <Input
                        id="requested_due_at"
                        v-model="extensionForm.requested_due_at"
                        type="datetime-local"
                        :min="minDue"
                        required
                    />
                    <InputError
                        :message="extensionForm.errors.requested_due_at"
                    />

                    <Label for="extension_reason">Alasan</Label>
                    <textarea
                        id="extension_reason"
                        v-model="extensionForm.reason"
                        rows="2"
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                        required
                    />
                    <InputError :message="extensionForm.errors.reason" />
                    <InputError :message="extensionLoanError" />

                    <div class="flex gap-2">
                        <Button
                            type="submit"
                            :disabled="extensionForm.processing"
                        >
                            Kirim pengajuan
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showExtensionForm = false"
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="request.status === 'cancelled'"
            class="rounded-2xl border bg-card p-4 text-sm md:p-5"
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
