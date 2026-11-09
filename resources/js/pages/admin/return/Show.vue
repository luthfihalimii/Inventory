<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Item = {
    id: number;
    code: string | null;
    name: string | null;
    tracking_method: 'serialized' | 'quantity' | null;
    quantity: number;
    condition_out: string;
    asset: { id: number; asset_code: string; condition: string } | null;
};

type ReturnRequest = {
    id: number;
    status: string;
    requested_at: string;
    notes: string | null;
    cancelled_reason: string | null;
    verified_at: string | null;
    loan: {
        id: number;
        status: string;
        checked_out_at: string | null;
        due_at: string | null;
        reference_no: string | null;
        member: { name: string; nrp: string; division: string | null };
    };
    items: Item[];
    inspection:
        | {
              loan_item_id: number;
              condition: string;
              quantity_returned: number;
              notes: string | null;
          }[]
        | null;
};

const props = defineProps<{
    request: ReturnRequest;
    canVerify: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Pengembalian', href: '/admin/returns' },
            { title: 'Verifikasi', href: '#' },
        ],
    },
});

const form = useForm({
    notes: '',
    items: props.request.items.map((item) => ({
        loan_item_id: item.id,
        condition: 'good' as 'good' | 'damaged' | 'incomplete' | 'lost',
        quantity_returned: item.quantity,
        notes: '',
    })),
});

const cancelForm = useForm({ reason: '' });
const showCancel = ref(false);
const itemsError = computed(
    () => (form.errors as Record<string, string>).items,
);

function addRow(): void {
    const first = props.request.items[0];

    if (!first) {
        return;
    }

    form.items.push({
        loan_item_id: first.id,
        condition: 'good',
        quantity_returned: 1,
        notes: '',
    });
}

function removeRow(index: number): void {
    form.items.splice(index, 1);
}

function submit(): void {
    form.post(`/admin/returns/${props.request.id}/verify`, {
        preserveScroll: true,
    });
}

function cancel(): void {
    cancelForm.post(`/admin/returns/${props.request.id}/cancel`, {
        preserveScroll: true,
    });
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

const conditionLabel: Record<string, string> = {
    good: 'Baik',
    damaged: 'Rusak',
    incomplete: 'Tidak lengkap',
    lost: 'Hilang',
};
</script>

<template>
    <Head :title="`Pengembalian ${request.loan.reference_no ?? ``}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ request.loan.reference_no ?? `#${request.id}` }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ request.loan.member.name }}
                    ({{ request.loan.member.nrp }}) ·
                    {{ request.loan.member.division ?? '-' }}
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link href="/admin/returns">Kembali</Link>
            </Button>
        </div>

        <dl class="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted-foreground">Diserahkan</dt>
                <dd>{{ formatDate(request.loan.checked_out_at) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Tenggat</dt>
                <dd>{{ formatDate(request.loan.due_at) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Diajukan kembali</dt>
                <dd>{{ formatDate(request.requested_at) }}</dd>
            </div>
            <div class="sm:col-span-3">
                <dt class="text-muted-foreground">Catatan peminjam</dt>
                <dd>{{ request.notes || '-' }}</dd>
            </div>
        </dl>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode</th>
                        <th class="p-3">Nama</th>
                        <th class="p-3">Unit</th>
                        <th class="p-3">Jumlah</th>
                        <th class="p-3">Kondisi keluar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in request.items"
                        :key="item.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ item.code ?? '-' }}</td>
                        <td class="p-3">
                            {{ item.name ?? '-' }}
                            <span
                                v-if="item.asset"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ item.asset.asset_code }}
                            </span>
                        </td>
                        <td class="p-3">
                            {{
                                item.tracking_method === 'serialized'
                                    ? 'Per unit'
                                    : 'Jumlah'
                            }}
                        </td>
                        <td class="p-3">{{ item.quantity }}</td>
                        <td class="p-3">
                            {{
                                conditionLabel[item.condition_out] ??
                                item.condition_out
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="request.inspection" class="rounded-lg border p-4 text-sm">
            <p class="font-medium">Hasil inspeksi</p>
            <ul class="mt-2 grid gap-1">
                <li
                    v-for="row in request.inspection"
                    :key="`${row.loan_item_id}-${row.condition}`"
                >
                    {{ row.quantity_returned }} ×
                    {{ conditionLabel[row.condition] ?? row.condition }}
                    <span v-if="row.notes" class="text-muted-foreground">
                        — {{ row.notes }}
                    </span>
                </li>
            </ul>
        </div>

        <form
            v-if="canVerify"
            class="grid gap-4 rounded-lg border p-4"
            @submit.prevent="submit"
        >
            <div class="flex items-center justify-between">
                <h2 class="font-medium">Inspeksi pengembalian</h2>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addRow"
                >
                    Tambah kondisi
                </Button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-2">Barang</th>
                            <th class="p-2">Kondisi</th>
                            <th class="p-2">Jumlah kembali</th>
                            <th class="p-2">Catatan</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in form.items"
                            :key="index"
                            class="border-t"
                        >
                            <td class="p-2">
                                <select
                                    v-model="row.loan_item_id"
                                    class="h-9 rounded-md border bg-transparent px-2 text-sm"
                                >
                                    <option
                                        v-for="item in request.items"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name }} ({{ item.quantity }})
                                    </option>
                                </select>
                            </td>
                            <td class="p-2">
                                <select
                                    v-model="row.condition"
                                    class="h-9 rounded-md border bg-transparent px-2 text-sm"
                                >
                                    <option value="good">Baik</option>
                                    <option value="damaged">Rusak</option>
                                    <option value="incomplete">
                                        Tidak lengkap
                                    </option>
                                    <option value="lost">Hilang</option>
                                </select>
                            </td>
                            <td class="p-2">
                                <Input
                                    v-model.number="row.quantity_returned"
                                    type="number"
                                    class="w-24"
                                    min="1"
                                />
                            </td>
                            <td class="p-2">
                                <Input v-model="row.notes" />
                            </td>
                            <td class="p-2 text-right">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="removeRow(index)"
                                >
                                    Hapus
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <InputError :message="itemsError" />

            <div class="grid gap-2">
                <Label for="notes">Catatan inspeksi</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="2"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div>
                <Button type="submit" :disabled="form.processing">
                    Selesaikan transaksi
                </Button>
            </div>
        </form>

        <div
            v-if="request.status === 'pending'"
            class="flex flex-col gap-3 rounded-lg border p-4"
        >
            <h2 class="font-medium">Batalkan pengembalian</h2>
            <div>
                <Button variant="destructive" @click="showCancel = !showCancel">
                    Batalkan
                </Button>
            </div>
            <form v-if="showCancel" class="grid gap-2" @submit.prevent="cancel">
                <Label for="reason">Alasan pembatalan</Label>
                <textarea
                    id="reason"
                    v-model="cancelForm.reason"
                    rows="2"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    required
                />
                <InputError :message="cancelForm.errors.reason" />
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

        <div
            v-if="request.status === 'cancelled'"
            class="rounded-lg border p-4 text-sm"
        >
            <p class="font-medium">Pengembalian dibatalkan</p>
            <p v-if="request.cancelled_reason" class="mt-1">
                {{ request.cancelled_reason }}
            </p>
        </div>
    </div>
</template>
