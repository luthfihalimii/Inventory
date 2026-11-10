<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBorrowCart } from '@/composables/useBorrowCart';
import type { Availability } from '@/types';

type Asset = {
    id: number;
    asset_code: string;
    serial_number: string | null;
    condition: string;
    status: string;
};

type ScheduleEntry = {
    start: string;
    end: string;
    quantity: number;
    kind: 'reservation' | 'loan';
};

type MaintenanceHistory = {
    id: number;
    type: string;
    quantity: number | null;
    description: string;
    status: string;
    resolution: string | null;
    resolution_notes: string | null;
    cost: string | null;
    vendor: string | null;
    reported_at: string;
    resolved_at: string | null;
    resolver: { id: number; name: string } | null;
};

type Item = {
    id: number;
    code: string;
    name: string;
    tracking_method: 'serialized' | 'quantity';
    quantity_total: number | null;
    price: string | null;
    acquired_at: string | null;
    status: string;
    notes: string | null;
    category?: { id: number; name: string };
    location?: { id: number; name: string; code: string };
    assets: Asset[];
};

const props = defineProps<{
    item: Item;
    availability: Availability;
    schedule: ScheduleEntry[];
    maintenanceHistory: MaintenanceHistory[];
    canManage: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventaris', href: '/inventory' }],
    },
});

const form = useForm({
    asset_id: null as number | null,
    type: 'damage',
    quantity: 1,
    description: '',
});

const { count: cartCount, add: pushCartLine } = useBorrowCart();
const quantity = ref(1);
const roles = computed(
    () =>
        (usePage().props.auth?.user as { roles?: string[] } | null)?.roles ??
        [],
);
const canBorrow = computed(
    () => roles.value.includes('user') && props.item.status === 'active',
);

function busyOn(day: Date): number {
    const dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
    const dayEnd = new Date(dayStart);
    dayEnd.setDate(dayEnd.getDate() + 1);

    return props.schedule.reduce((total, entry) => {
        const start = new Date(entry.start);
        const end = new Date(entry.end);

        return start < dayEnd && end > dayStart
            ? total + entry.quantity
            : total;
    }, 0);
}

const months = computed(() => {
    const result: {
        label: string;
        offset: number;
        days: { day: number; busy: number; level: string }[];
    }[] = [];
    const base = new Date();

    for (let index = 0; index < 3; index++) {
        const first = new Date(base.getFullYear(), base.getMonth() + index, 1);
        const daysInMonth = new Date(
            first.getFullYear(),
            first.getMonth() + 1,
            0,
        ).getDate();
        const days: { day: number; busy: number; level: string }[] = [];

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(first.getFullYear(), first.getMonth(), day);
            const busy = busyOn(date);

            days.push({
                day,
                busy,
                level:
                    props.availability.total > 0 &&
                    busy >= props.availability.total
                        ? 'full'
                        : busy > 0
                          ? 'partial'
                          : 'free',
            });
        }

        result.push({
            label: first.toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            }),
            offset: (first.getDay() + 6) % 7,
            days,
        });
    }

    return result;
});

function addToCart(): void {
    pushCartLine({
        item_id: props.item.id,
        code: props.item.code,
        name: props.item.name,
        quantity: Math.max(1, quantity.value),
        tracking_method: props.item.tracking_method,
    });
}

function report(): void {
    form.post(`/admin/inventory/items/${props.item.id}/damage-reports`, {
        preserveScroll: true,
        onSuccess: () => form.reset('description'),
    });
}

const statusLabel: Record<string, string> = {
    available: 'Tersedia',
    borrowed: 'Dipinjam',
    damaged: 'Rusak',
    maintenance: 'Perawatan',
    lost: 'Hilang',
    inactive: 'Nonaktif',
};

const typeLabel: Record<string, string> = {
    damage: 'Rusak',
    loss: 'Hilang',
    incomplete: 'Tidak lengkap',
    maintenance: 'Perawatan',
};

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        dateStyle: 'medium',
    });
}
</script>

<template>
    <Head :title="item.name" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ item.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ item.code }} · {{ item.category?.name ?? '-' }} ·
                    {{ item.location?.name ?? '-' }}
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link href="/inventory">Kembali</Link>
            </Button>
        </div>

        <div
            v-if="canBorrow"
            class="flex flex-wrap items-end gap-3 rounded-lg border p-4"
        >
            <div class="grid gap-2">
                <Label for="borrow_quantity">Jumlah pinjam</Label>
                <Input
                    id="borrow_quantity"
                    v-model.number="quantity"
                    type="number"
                    class="w-28"
                    min="1"
                    :max="availability.available"
                />
            </div>
            <Button :disabled="availability.available < 1" @click="addToCart">
                Tambah ke keranjang
            </Button>
            <Button variant="outline" as-child>
                <Link href="/peminjaman/baru">
                    Keranjang ({{ cartCount }})
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">Total</p>
                <p class="text-2xl font-semibold">{{ availability.total }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">Tersedia</p>
                <p class="text-2xl font-semibold">
                    {{ availability.available }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">Dipinjam</p>
                <p class="text-2xl font-semibold">
                    {{ availability.borrowed }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">Rusak / hilang</p>
                <p class="text-2xl font-semibold">
                    {{ availability.damaged + availability.lost }}
                </p>
            </div>
        </div>

        <div class="rounded-lg border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-medium">Kalender ketersediaan (90 hari)</h2>
                <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1">
                        <span
                            class="inline-block size-3 rounded bg-emerald-500/20"
                        />
                        Kosong
                    </span>
                    <span class="flex items-center gap-1">
                        <span
                            class="inline-block size-3 rounded bg-amber-500/30"
                        />
                        Sebagian
                    </span>
                    <span class="flex items-center gap-1">
                        <span
                            class="inline-block size-3 rounded bg-red-500/30"
                        />
                        Penuh
                    </span>
                </div>
            </div>

            <div class="mt-4 grid gap-6 md:grid-cols-3">
                <div v-for="month in months" :key="month.label">
                    <p class="mb-2 text-sm font-medium">{{ month.label }}</p>
                    <div
                        class="grid grid-cols-7 gap-1 text-center text-xs"
                        role="group"
                        :aria-label="month.label"
                    >
                        <span
                            v-for="label in [
                                'Sen',
                                'Sel',
                                'Rab',
                                'Kam',
                                'Jum',
                                'Sab',
                                'Min',
                            ]"
                            :key="label"
                            class="py-1 text-muted-foreground"
                        >
                            {{ label }}
                        </span>
                        <span
                            v-for="blank in month.offset"
                            :key="`blank-${blank}`"
                        ></span>
                        <span
                            v-for="day in month.days"
                            :key="day.day"
                            class="rounded py-1"
                            :class="{
                                'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300':
                                    day.level === 'free',
                                'bg-amber-500/20 text-amber-800 dark:text-amber-300':
                                    day.level === 'partial',
                                'bg-red-500/20 text-red-800 dark:text-red-300':
                                    day.level === 'full',
                            }"
                            :title="`${day.busy} unit terpakai`"
                            :aria-label="`${day.day} ${month.label}: ${day.busy} unit terpakai`"
                        >
                            {{ day.day }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border p-4">
            <dl class="grid gap-2 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-muted-foreground">Metode</dt>
                    <dd>
                        {{
                            item.tracking_method === 'serialized'
                                ? 'Per unit'
                                : 'Berdasarkan jumlah'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Harga</dt>
                    <dd>{{ item.price ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Tanggal input</dt>
                    <dd>{{ item.acquired_at ?? '-' }}</dd>
                </div>
                <div class="sm:col-span-3">
                    <dt class="text-muted-foreground">Keterangan</dt>
                    <dd>{{ item.notes || '-' }}</dd>
                </div>
            </dl>
        </div>

        <div
            v-if="item.tracking_method === 'serialized'"
            class="rounded-lg border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="p-3">Kode aset</th>
                        <th class="p-3">Serial</th>
                        <th class="p-3">Kondisi</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="asset in item.assets"
                        :key="asset.id"
                        class="border-t"
                    >
                        <td class="p-3 font-mono">{{ asset.asset_code }}</td>
                        <td class="p-3">{{ asset.serial_number ?? '-' }}</td>
                        <td class="p-3">
                            {{ asset.condition === 'good' ? 'Baik' : 'Rusak' }}
                        </td>
                        <td class="p-3">
                            {{ statusLabel[asset.status] ?? asset.status }}
                        </td>
                    </tr>
                    <tr v-if="item.assets.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="4">
                            Belum ada unit.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="canManage" class="rounded-lg border">
            <div class="border-b p-4">
                <h2 class="font-medium">Riwayat maintenance &amp; biaya</h2>
                <p class="text-sm text-muted-foreground">
                    20 laporan terakhir untuk barang ini.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">Dilaporkan</th>
                            <th class="p-3">Jenis</th>
                            <th class="p-3">Jumlah</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Biaya</th>
                            <th class="p-3">Vendor / petugas</th>
                            <th class="p-3">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="entry in maintenanceHistory"
                            :key="entry.id"
                            class="border-t align-top"
                        >
                            <td class="p-3 whitespace-nowrap">
                                {{ formatDate(entry.reported_at) }}
                            </td>
                            <td class="p-3">
                                {{ typeLabel[entry.type] ?? entry.type }}
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ entry.description }}
                                </span>
                            </td>
                            <td class="p-3">{{ entry.quantity ?? '-' }}</td>
                            <td class="p-3">
                                {{
                                    entry.status === 'open'
                                        ? 'Terbuka'
                                        : entry.resolution === 'repaired'
                                          ? 'Diperbaiki'
                                          : 'Dihapuskan'
                                }}
                            </td>
                            <td class="p-3">
                                {{ entry.cost ? `Rp ${entry.cost}` : '-' }}
                            </td>
                            <td class="p-3">
                                {{
                                    entry.vendor || entry.resolver?.name || '-'
                                }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                {{ formatDate(entry.resolved_at) }}
                            </td>
                        </tr>
                        <tr v-if="maintenanceHistory.length === 0">
                            <td class="p-3 text-muted-foreground" colspan="7">
                                Belum ada riwayat maintenance.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <form
            v-if="canManage"
            class="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
            @submit.prevent="report"
        >
            <div class="md:col-span-2">
                <h2 class="font-medium">Laporkan kerusakan / kehilangan</h2>
                <p class="text-sm text-muted-foreground">
                    Barang yang dilaporkan tidak dihitung tersedia sampai
                    diselesaikan.
                </p>
            </div>

            <div
                v-if="item.tracking_method === 'serialized'"
                class="grid gap-2"
            >
                <Label for="asset_id">Unit</Label>
                <select
                    id="asset_id"
                    v-model="form.asset_id"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    required
                >
                    <option :value="null" disabled>Pilih unit</option>
                    <option
                        v-for="asset in item.assets"
                        :key="asset.id"
                        :value="asset.id"
                    >
                        {{ asset.asset_code }} ({{
                            statusLabel[asset.status] ?? asset.status
                        }})
                    </option>
                </select>
                <InputError :message="form.errors.asset_id" />
            </div>

            <div v-else class="grid gap-2">
                <Label for="quantity">Jumlah</Label>
                <Input
                    id="quantity"
                    v-model.number="form.quantity"
                    type="number"
                    min="1"
                    :max="availability.available"
                />
                <InputError :message="form.errors.quantity" />
            </div>

            <div class="grid gap-2">
                <Label for="type">Jenis</Label>
                <select
                    id="type"
                    v-model="form.type"
                    class="h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    <option value="damage">Rusak</option>
                    <option value="loss">Hilang</option>
                    <option value="incomplete">Tidak lengkap</option>
                    <option value="maintenance">Perawatan</option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="description">Keterangan</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    required
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="md:col-span-2">
                <Button type="submit" :disabled="form.processing"
                    >Kirim laporan</Button
                >
            </div>
        </form>
    </div>
</template>
