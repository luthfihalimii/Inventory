<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type ApprovalRequest = {
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
    member: {
        name: string;
        nrp: string;
        membership_status: string;
        borrowing_status: string;
        division: string | null;
    };
    items: {
        item_id: number;
        code: string | null;
        name: string | null;
        tracking_method: string | null;
        quantity: number;
        available: number;
    }[];
    approval: {
        decision: string;
        notes: string | null;
        decided_at: string;
        approver_role: string;
    } | null;
};

const props = defineProps<{
    request: ApprovalRequest;
    canDecide: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Approval', href: '/admin/approval' },
            { title: 'Detail', href: '#' },
        ],
    },
});

const rejectForm = useForm({ notes: '' });
const approveForm = useForm({ notes: '' });
const cancelForm = useForm({ reason: '' });
const approveErrors = computed(
    () => approveForm.errors as Record<string, string>,
);
const cancelErrors = computed(
    () => cancelForm.errors as Record<string, string>,
);
const showReject = ref(false);
const showCancel = ref(false);

function cancelRequest(): void {
    cancelForm.put(`/peminjaman/${props.request.id}/cancel`);
}

function approve(): void {
    if (!window.confirm('Setujui pengajuan ini dan kunci reservasi barang?')) {
        return;
    }

    approveForm.post(`/admin/approval/${props.request.id}/approve`);
}

function reject(): void {
    rejectForm.post(`/admin/approval/${props.request.id}/reject`);
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
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
                    {{
                        request.category === 'urgent' ? 'Mendadak' : 'Terjadwal'
                    }}
                    · {{ request.member.name }} ({{ request.member.nrp }}) ·
                    {{ request.member.division ?? '-' }}
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link href="/admin/approval">Kembali</Link>
            </Button>
        </div>

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
                <dt class="text-muted-foreground">Status anggota</dt>
                <dd>
                    {{ request.member.membership_status }} /
                    {{ request.member.borrowing_status }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Lokasi penggunaan</dt>
                <dd>{{ request.usage_location || '-' }}</dd>
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
                        <th class="p-3">Metode</th>
                        <th class="p-3">Diminta</th>
                        <th class="p-3">Tersedia periode</th>
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
                        <td class="p-3">
                            {{
                                line.tracking_method === 'serialized'
                                    ? 'Per unit'
                                    : 'Jumlah'
                            }}
                        </td>
                        <td class="p-3">{{ line.quantity }}</td>
                        <td
                            class="p-3"
                            :class="
                                line.available < line.quantity
                                    ? 'text-destructive'
                                    : ''
                            "
                        >
                            {{ line.available }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="request.approval" class="rounded-lg border p-4 text-sm">
            <p class="font-medium">
                {{
                    request.approval.decision === 'approved'
                        ? 'Disetujui'
                        : 'Ditolak'
                }}
                oleh {{ request.approval.approver_role }}
            </p>
            <p class="text-muted-foreground">
                {{ formatDate(request.approval.decided_at) }}
            </p>
            <p v-if="request.approval.notes" class="mt-2">
                {{ request.approval.notes }}
            </p>
        </div>

        <div v-if="canDecide" class="flex flex-col gap-3 rounded-lg border p-4">
            <div class="flex flex-wrap gap-2">
                <Button :disabled="approveForm.processing" @click="approve">
                    Setujui
                </Button>
                <Button variant="destructive" @click="showReject = !showReject">
                    Tolak
                </Button>
            </div>
            <InputError :message="approveErrors.decision" />
            <InputError :message="approveErrors.items" />
            <InputError :message="approveErrors.member" />

            <form v-if="showReject" class="grid gap-2" @submit.prevent="reject">
                <Label for="notes">Alasan penolakan</Label>
                <textarea
                    id="notes"
                    v-model="rejectForm.notes"
                    rows="3"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    required
                />
                <InputError :message="rejectForm.errors.notes" />
                <div>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="rejectForm.processing"
                    >
                        Kirim penolakan
                    </Button>
                </div>
            </form>
        </div>

        <div
            v-if="['pending_approval', 'approved'].includes(request.status)"
            class="flex flex-col gap-3 rounded-lg border p-4"
        >
            <h2 class="font-medium">Batalkan pengajuan</h2>
            <div>
                <Button variant="destructive" @click="showCancel = !showCancel">
                    Batalkan
                </Button>
            </div>
            <form
                v-if="showCancel"
                class="grid gap-2"
                @submit.prevent="cancelRequest"
            >
                <Label for="cancel_reason">Alasan pembatalan</Label>
                <textarea
                    id="cancel_reason"
                    v-model="cancelForm.reason"
                    rows="3"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    required
                />
                <InputError :message="cancelForm.errors.reason" />
                <InputError :message="cancelErrors.status" />
                <div>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="cancelForm.processing"
                    >
                        Konfirmasi pembatalan
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
