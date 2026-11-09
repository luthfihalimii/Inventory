<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Line = {
    reservation_id: number;
    item_id: number;
    code: string | null;
    name: string | null;
    tracking_method: 'serialized' | 'quantity' | null;
    quantity: number;
    asset: {
        id: number;
        asset_code: string;
        condition: string;
    } | null;
};

type Transaction = {
    id: number;
    status: string;
    reference_no: string | null;
    pickup_at: string;
    return_at: string;
    usage_location: string | null;
    member: { name: string; nrp: string; division: string | null };
    lines: Line[];
};

type Entry = {
    reservation_id: number;
    code: string | null;
    name: string | null;
    tracking_method: 'serialized' | 'quantity';
    reserved_quantity: number;
    asset_id: number | null;
    asset_code: string | null;
    delivered: boolean;
    quantity: number;
    condition_out: 'good' | 'damaged';
    notes: string;
    swap: boolean;
};

type ScanResponse = {
    valid: boolean;
    message?: string;
    type?: 'asset' | 'item';
    reservation_id?: number;
    swap?: boolean;
    asset?: { id: number; asset_code: string; condition: string };
    item?: { id: number; code: string; name: string };
    quantity?: number;
};

const props = defineProps<{
    transaction: Transaction;
    canCheckout: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Checkout', href: '/admin/checkout' },
            { title: 'Serah terima', href: '#' },
        ],
    },
});

const entries = ref<Entry[]>(
    props.transaction.lines.map((line) => ({
        reservation_id: line.reservation_id,
        code: line.code,
        name: line.name,
        tracking_method: line.tracking_method ?? 'quantity',
        reserved_quantity: line.quantity,
        asset_id: line.asset?.id ?? null,
        asset_code: line.asset?.asset_code ?? null,
        delivered:
            line.tracking_method === 'serialized' ? line.asset !== null : true,
        quantity: line.quantity,
        condition_out: 'good',
        notes: '',
        swap: false,
    })),
);

const form = useForm({
    lines: [] as {
        reservation_id: number;
        asset_id: number | null;
        quantity: number;
        condition_out: 'good' | 'damaged';
        notes: string;
    }[],
    reason: '',
    notes: '',
    checklist: {
        identity_verified: false,
        condition_checked: false,
        sop_explained: false,
    },
});

const scanToken = ref('');
const scanning = ref(false);
const scanMessage = ref<{ ok: boolean; text: string } | null>(null);

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function scan(): Promise<void> {
    if (!scanToken.value.trim()) {
        return;
    }

    scanning.value = true;

    try {
        const response = await fetch(
            `/admin/checkout/${props.transaction.id}/scan`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ token: scanToken.value.trim() }),
            },
        );
        const data = (await response.json()) as ScanResponse;

        if (!response.ok || !data.valid) {
            scanMessage.value = {
                ok: false,
                text: data.message ?? 'Token tidak valid.',
            };

            return;
        }

        const entry = entries.value.find(
            (item) => item.reservation_id === data.reservation_id,
        );

        if (!entry) {
            scanMessage.value = { ok: false, text: 'Baris tidak ditemukan.' };

            return;
        }

        if (data.type === 'asset' && data.asset) {
            entry.asset_id = data.asset.id;
            entry.asset_code = data.asset.asset_code;
            entry.swap = data.swap === true;
            entry.delivered = true;
            scanMessage.value = {
                ok: true,
                text: `${data.asset.asset_code} cocok${data.swap ? ' (tukar unit)' : ''}.`,
            };
        } else if (data.type === 'item') {
            entry.delivered = true;
            entry.quantity = data.quantity ?? entry.quantity;
            scanMessage.value = {
                ok: true,
                text: `${data.item?.name ?? 'Barang'} cocok.`,
            };
        }

        scanToken.value = '';
    } finally {
        scanning.value = false;
    }
}

function submit(): void {
    form.lines = entries.value
        .filter(
            (entry) =>
                entry.delivered &&
                (entry.tracking_method === 'quantity' ||
                    entry.asset_id !== null),
        )
        .map((entry) => ({
            reservation_id: entry.reservation_id,
            asset_id:
                entry.tracking_method === 'serialized' ? entry.asset_id : null,
            quantity:
                entry.tracking_method === 'serialized' ? 1 : entry.quantity,
            condition_out: entry.condition_out,
            notes: entry.notes,
        }));

    form.post(`/admin/checkout/${props.transaction.id}`, {
        preserveScroll: true,
    });
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Head :title="`Checkout ${transaction.reference_no ?? ``}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ transaction.reference_no ?? `#${transaction.id}` }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ transaction.member.name }}
                    ({{ transaction.member.nrp }}) ·
                    {{ transaction.member.division ?? '-' }}
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link href="/admin/checkout">Kembali</Link>
            </Button>
        </div>

        <dl class="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted-foreground">Mulai pinjam</dt>
                <dd>{{ formatDate(transaction.pickup_at) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Tenggat kembali</dt>
                <dd>{{ formatDate(transaction.return_at) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Lokasi penggunaan</dt>
                <dd>{{ transaction.usage_location || '-' }}</dd>
            </div>
        </dl>

        <div v-if="canCheckout" class="grid gap-3 rounded-lg border p-4">
            <Label for="scan_token">Scan QR / masukkan token</Label>
            <div class="flex flex-wrap gap-2">
                <Input
                    id="scan_token"
                    v-model="scanToken"
                    class="max-w-sm font-mono"
                    placeholder="Token aset atau barang"
                    @keyup.enter="scan"
                />
                <Button
                    type="button"
                    variant="outline"
                    :disabled="scanning"
                    @click="scan"
                >
                    Periksa
                </Button>
            </div>
            <p
                v-if="scanMessage"
                class="text-sm"
                :class="
                    scanMessage.ok ? 'text-emerald-600' : 'text-destructive'
                "
            >
                {{ scanMessage.text }}
            </p>
        </div>

        <p v-else class="rounded-lg border p-4 text-sm text-muted-foreground">
            Transaksi ini tidak bisa di-checkout (status tidak siap atau jadwal
            sudah lewat).
        </p>

        <form class="grid gap-4" @submit.prevent="submit">
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">Serah</th>
                            <th class="p-3">Kode</th>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Unit</th>
                            <th class="p-3">Jumlah</th>
                            <th class="p-3">Kondisi</th>
                            <th class="p-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in entries"
                            :key="entry.reservation_id"
                            class="border-t"
                        >
                            <td class="p-3">
                                <input
                                    v-model="entry.delivered"
                                    type="checkbox"
                                    :disabled="!canCheckout"
                                />
                            </td>
                            <td class="p-3 font-mono">
                                {{ entry.code ?? '-' }}
                            </td>
                            <td class="p-3">
                                {{ entry.name ?? '-' }}
                                <span
                                    v-if="
                                        entry.tracking_method === 'serialized'
                                    "
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ entry.asset_code ?? 'Belum ada unit' }}
                                    <span v-if="entry.swap">(tukar)</span>
                                </span>
                            </td>
                            <td class="p-3">
                                {{
                                    entry.tracking_method === 'serialized'
                                        ? 'Per unit'
                                        : 'Jumlah'
                                }}
                            </td>
                            <td class="p-3">
                                <Input
                                    v-if="entry.tracking_method === 'quantity'"
                                    v-model.number="entry.quantity"
                                    type="number"
                                    class="w-24"
                                    min="1"
                                    :max="entry.reserved_quantity"
                                    :disabled="!canCheckout"
                                />
                                <span v-else>1</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    reservasi {{ entry.reserved_quantity }}
                                </span>
                            </td>
                            <td class="p-3">
                                <select
                                    v-model="entry.condition_out"
                                    class="h-9 rounded-md border bg-transparent px-2 text-sm"
                                    :disabled="!canCheckout"
                                >
                                    <option value="good">Baik</option>
                                    <option value="damaged">Rusak</option>
                                </select>
                            </td>
                            <td class="p-3">
                                <Input
                                    v-model="entry.notes"
                                    :disabled="!canCheckout"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="canCheckout" class="grid gap-3 rounded-lg border p-4">
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="form.checklist.identity_verified"
                            type="checkbox"
                        />
                        Identitas peminjam diverifikasi
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="form.checklist.condition_checked"
                            type="checkbox"
                        />
                        Kondisi barang diperiksa bersama
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="form.checklist.sop_explained"
                            type="checkbox"
                        />
                        SOP dijelaskan
                    </label>
                </div>

                <div class="grid gap-2">
                    <Label for="reason">Alasan partial / tukar unit</Label>
                    <Input id="reason" v-model="form.reason" />
                    <InputError :message="form.errors.reason" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Catatan serah terima</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="2"
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <InputError :message="form.errors.lines" />

                <div>
                    <Button type="submit" :disabled="form.processing">
                        Konfirmasi serah terima
                    </Button>
                </div>
            </div>
        </form>
    </div>
</template>
