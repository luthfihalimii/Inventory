<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Item = {
    id: number;
    code: string | null;
    name: string | null;
    tracking_method: 'serialized' | 'quantity' | null;
    quantity: number;
    quantity_returned: number;
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
    inspectionPhotos: string[];
    handoverPhotos: string[];
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
    photos: [] as File[],
    items: props.request.items
        .filter((item) => item.quantity > item.quantity_returned)
        .map((item) => ({
            loan_item_id: item.id,
            condition: 'good' as 'good' | 'damaged' | 'incomplete' | 'lost',
            quantity_returned: item.quantity - item.quantity_returned,
            notes: '',
        })),
});

const cancelForm = useForm({ reason: '' });
const showCancel = ref(false);
const itemsError = computed(
    () => (form.errors as Record<string, string>).items,
);

function remainingFor(loanItemId: number): number {
    const item = props.request.items.find((entry) => entry.id === loanItemId);
    const used = form.items
        .filter((row) => row.loan_item_id === loanItemId)
        .reduce(
            (total, row) => total + (Number(row.quantity_returned) || 0),
            0,
        );

    return Math.max(
        0,
        (item?.quantity ?? 0) - (item?.quantity_returned ?? 0) - used,
    );
}

function addRow(): void {
    // Point the new row at the first item that still has quantity left to account for.
    const target = props.request.items.find(
        (item) => remainingFor(item.id) > 0,
    );

    if (!target) {
        return;
    }

    form.items.push({
        loan_item_id: target.id,
        condition: 'good',
        quantity_returned: remainingFor(target.id),
        notes: '',
    });
}

function removeRow(index: number): void {
    form.items.splice(index, 1);
}

function submit(): void {
    form.post(`/admin/returns/${props.request.id}/verify`, {
        preserveScroll: true,
        forceFormData: true,
    });
}

function onPhotosChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.photos = Array.from(input.files ?? []);
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

    <div
        class="flex min-w-0 flex-col gap-6 p-4 [&_button]:h-11 [&_button]:rounded-xl"
    >
        <PageHeader
            :title="request.loan.reference_no ?? `#${request.id}`"
            :description="`${request.loan.member.name} (${request.loan.member.nrp}) · ${request.loan.member.division ?? '-'}`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/admin/returns">Kembali</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <div class="grid min-w-0 gap-4">
                <section class="rounded-2xl border bg-card p-4">
                    <h2 class="mb-4 font-semibold">Detail pinjaman</h2>
                    <dl class="grid gap-4 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-muted-foreground">Diserahkan</dt>
                            <dd>
                                {{ formatDate(request.loan.checked_out_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Tenggat</dt>
                            <dd>{{ formatDate(request.loan.due_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">
                                Diajukan kembali
                            </dt>
                            <dd>{{ formatDate(request.requested_at) }}</dd>
                        </div>
                        <div class="sm:col-span-3">
                            <dt class="text-muted-foreground">
                                Catatan peminjam
                            </dt>
                            <dd>{{ request.notes || '-' }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-if="request.handoverPhotos.length > 0"
                    class="rounded-2xl border bg-card p-4 text-sm"
                >
                    <p class="mb-2 font-medium">Foto saat serah terima</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <a
                            v-for="photo in request.handoverPhotos"
                            class="block aspect-square overflow-hidden rounded-xl border"
                            :key="photo"
                            :href="`/storage/${photo}`"
                            target="_blank"
                            rel="noopener"
                        >
                            <img
                                :src="`/storage/${photo}`"
                                alt="Foto serah terima"
                                class="aspect-square size-full object-cover"
                            />
                        </a>
                    </div>
                </section>

                <TableShell
                    caption="Daftar barang yang diajukan untuk dikembalikan"
                >
                    <thead>
                        <tr>
                            <th class="p-3">Kode</th>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Unit</th>
                            <th class="p-3">Jumlah</th>
                            <th class="p-3">Sudah kembali</th>
                            <th class="p-3">Kondisi keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in request.items"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="p-3 font-mono">
                                {{ item.code ?? '-' }}
                            </td>
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
                            <td class="p-3">{{ item.quantity_returned }}</td>
                            <td class="p-3">
                                {{
                                    conditionLabel[item.condition_out] ??
                                    item.condition_out
                                }}
                            </td>
                        </tr>
                    </tbody>
                </TableShell>

                <section
                    v-if="request.inspection"
                    class="rounded-2xl border bg-card p-4 text-sm"
                >
                    <p class="font-medium">Hasil inspeksi</p>
                    <ul class="mt-2 grid gap-1">
                        <li
                            v-for="(row, index) in request.inspection"
                            :key="`${row.loan_item_id}-${row.condition}-${index}`"
                        >
                            {{ row.quantity_returned }} ×
                            {{ conditionLabel[row.condition] ?? row.condition }}
                            <span
                                v-if="row.notes"
                                class="text-muted-foreground"
                            >
                                - {{ row.notes }}
                            </span>
                        </li>
                    </ul>
                    <div
                        v-if="request.inspectionPhotos.length > 0"
                        class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <a
                            v-for="photo in request.inspectionPhotos"
                            :key="photo"
                            class="block aspect-square overflow-hidden rounded-xl border"
                            :href="`/storage/${photo}`"
                            target="_blank"
                            rel="noopener"
                        >
                            <img
                                :src="`/storage/${photo}`"
                                alt="Foto kondisi pengembalian"
                                class="aspect-square size-full object-cover"
                            />
                        </a>
                    </div>
                </section>
            </div>

            <div class="grid min-w-0 gap-4">
                <form
                    v-if="canVerify"
                    class="grid gap-4 rounded-2xl border bg-card p-4"
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

                    <TableShell
                        caption="Formulir kondisi dan jumlah barang yang dikembalikan"
                    >
                        <thead>
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
                                        class="h-11 min-w-40 rounded-xl border bg-transparent px-2 text-sm"
                                    >
                                        <option
                                            v-for="item in request.items"
                                            :key="item.id"
                                            :value="item.id"
                                        >
                                            {{ item.name }} (sisa
                                            {{
                                                item.quantity -
                                                item.quantity_returned
                                            }})
                                        </option>
                                    </select>
                                </td>
                                <td class="p-2">
                                    <select
                                        v-model="row.condition"
                                        class="h-11 min-w-40 rounded-xl border bg-transparent px-2 text-sm"
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
                                        class="h-11 w-24 rounded-xl"
                                        min="1"
                                        :max="
                                            remainingFor(row.loan_item_id) +
                                            (Number(row.quantity_returned) || 0)
                                        "
                                    />
                                </td>
                                <td class="p-2">
                                    <Input
                                        v-model="row.notes"
                                        class="h-11 min-w-36 rounded-xl"
                                    />
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
                    </TableShell>

                    <InputError :message="itemsError" />

                    <div class="grid gap-2">
                        <Label for="notes">Catatan inspeksi</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="2"
                            class="rounded-xl border bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="photos"
                            >Foto kondisi pengembalian (maks 5)</Label
                        >
                        <input
                            id="photos"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="min-h-11 text-sm"
                            @change="onPhotosChange"
                        />
                        <InputError :message="form.errors.photos" />
                    </div>

                    <div>
                        <Button type="submit" :disabled="form.processing">
                            Selesaikan transaksi
                        </Button>
                    </div>
                </form>

                <section
                    v-if="request.status === 'pending'"
                    class="flex flex-col gap-3 rounded-2xl border bg-card p-4"
                >
                    <h2 class="font-medium">Batalkan pengembalian</h2>
                    <div>
                        <Button
                            variant="destructive"
                            @click="showCancel = !showCancel"
                        >
                            Batalkan
                        </Button>
                    </div>
                    <form
                        v-if="showCancel"
                        class="grid gap-2"
                        @submit.prevent="cancel"
                    >
                        <Label for="reason">Alasan pembatalan</Label>
                        <textarea
                            id="reason"
                            v-model="cancelForm.reason"
                            rows="2"
                            class="rounded-xl border bg-transparent px-3 py-2 text-sm"
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
                </section>
            </div>
        </div>

        <section
            v-if="request.status === 'cancelled'"
            class="rounded-2xl border bg-card p-4 text-sm"
        >
            <p class="font-medium">Pengembalian dibatalkan</p>
            <p v-if="request.cancelled_reason" class="mt-1">
                {{ request.cancelled_reason }}
            </p>
        </section>
    </div>
</template>
