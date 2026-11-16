<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import TableShell from '@/components/TableShell.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBorrowCart } from '@/composables/useBorrowCart';

type Sop = {
    id: number;
    version: number;
    title: string;
    content: string;
};

type Block = {
    reference_no: string | null;
    due_at: string | null;
};

const props = defineProps<{ sop: Sop; blocks: Block[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Peminjaman Saya', href: '/peminjaman' },
            { title: 'Baru', href: '/peminjaman/baru' },
        ],
    },
});

const cart = useBorrowCart();
const lines = cart.lines;
const quantity = ref<Record<number, number>>({});

const form = useForm({
    category: 'scheduled',
    pickup_at: '',
    return_at: '',
    usage_location: '',
    purpose: '',
    notes: '',
    sop_accepted: false,
});

const purposeRequired = computed(() => form.category === 'urgent');
const memberError = computed(
    () => (usePage().props.errors as Record<string, string>).member,
);
const itemsError = computed(
    () => (form.errors as Record<string, string>).items,
);

function setQuantity(itemId: number, value: number): void {
    const line = lines.value.find((l) => l.item_id === itemId);

    if (line) {
        line.quantity = Math.max(1, value);
    }
}

function remove(itemId: number): void {
    cart.remove(itemId);
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        items: lines.value.map((line) => ({
            item_id: line.item_id,
            quantity: line.quantity,
        })),
    })).post('/peminjaman', {
        onSuccess: () => cart.clear(),
    });
}

function cancel(): void {
    router.get('/peminjaman');
}
</script>

<template>
    <Head title="Pengajuan baru" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Pengajuan peminjaman"
            description="Periksa barang, isi jadwal, lalu setujui SOP."
        />

        <div
            v-if="props.blocks.length > 0"
            class="rounded-2xl border border-destructive/40 bg-destructive/5 p-4 text-sm"
        >
            <p class="font-medium text-destructive">
                Peminjaman diblokir karena pinjaman terlambat.
            </p>
            <ul class="mt-1 list-disc pl-5 text-muted-foreground">
                <li v-for="(block, index) in props.blocks" :key="index">
                    {{ block.reference_no ?? 'Transaksi' }}: tenggat
                    {{
                        block.due_at
                            ? new Date(block.due_at).toLocaleDateString('id-ID')
                            : '-'
                    }}
                </li>
            </ul>
            <p class="mt-1 text-muted-foreground">
                Kembalikan barang dan tunggu verifikasi untuk membuka blokir.
            </p>
        </div>

        <InputError :message="memberError" />

        <EmptyState
            v-if="lines.length === 0"
            title="Keranjang kosong."
            description="Tambahkan barang dari katalog inventaris."
        >
            <template #actions>
                <Button class="h-11 rounded-xl" variant="outline" as-child>
                    <Link href="/inventory">katalog inventaris</Link>
                </Button>
            </template>
        </EmptyState>

        <template v-else>
            <TableShell caption="Barang di keranjang">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Jumlah</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in lines" :key="line.item_id">
                        <td class="font-mono">{{ line.code }}</td>
                        <td>{{ line.name }}</td>
                        <td>
                            <Input
                                class="h-11 w-24 rounded-xl"
                                type="number"
                                min="1"
                                :model-value="line.quantity"
                                @update:model-value="
                                    (value) =>
                                        setQuantity(line.item_id, Number(value))
                                "
                            />
                        </td>
                        <td class="text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                type="button"
                                @click="remove(line.item_id)"
                            >
                                Hapus
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </TableShell>

            <form @submit.prevent="submit">
                <section
                    class="grid gap-4 rounded-2xl border bg-card p-4 md:grid-cols-2 md:p-6 [&_button]:h-11 [&_button]:rounded-xl [&_input:not([type='checkbox'])]:h-11 [&_input:not([type='checkbox'])]:rounded-xl [&_select]:h-11 [&_select]:rounded-xl"
                >
                    <div class="grid gap-2">
                        <Label for="category">Kategori</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            class="h-11 rounded-xl border border-input bg-transparent px-3 text-sm"
                        >
                            <option value="scheduled">Terjadwal</option>
                            <option value="urgent">Mendadak</option>
                        </select>
                        <InputError :message="form.errors.category" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="usage_location">Lokasi penggunaan</Label>
                        <Input
                            id="usage_location"
                            v-model="form.usage_location"
                            placeholder="Gedung / ruang"
                        />
                        <InputError :message="form.errors.usage_location" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="pickup_at">Mulai pinjam</Label>
                        <Input
                            id="pickup_at"
                            v-model="form.pickup_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="form.errors.pickup_at" />
                        <p
                            v-if="form.category === 'scheduled'"
                            class="text-xs text-muted-foreground"
                        >
                            Terjadwal minimal H-2.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="return_at">Tenggat kembali</Label>
                        <Input
                            id="return_at"
                            v-model="form.return_at"
                            type="datetime-local"
                            required
                        />
                        <InputError :message="form.errors.return_at" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="purpose">
                            Tujuan / alasan
                            <span
                                v-if="purposeRequired"
                                class="text-destructive"
                                >*</span
                            >
                        </Label>
                        <Input
                            id="purpose"
                            v-model="form.purpose"
                            :required="purposeRequired"
                        />
                        <InputError :message="form.errors.purpose" />
                    </div>

                    <div class="grid gap-2 md:col-span-2">
                        <Label for="notes">Keterangan tambahan</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="rounded-xl border bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>

                    <InputError class="md:col-span-2" :message="itemsError" />

                    <div class="rounded-xl border p-4 md:col-span-2">
                        <p class="font-medium">
                            {{ props.sop.title }} (versi
                            {{ props.sop.version }})
                        </p>
                        <div
                            class="mt-2 max-h-48 overflow-y-auto text-sm whitespace-pre-line text-muted-foreground"
                        >
                            {{ props.sop.content }}
                        </div>
                        <label
                            class="mt-3 flex min-h-11 items-center gap-3 text-sm"
                        >
                            <input
                                v-model="form.sop_accepted"
                                type="checkbox"
                                class="size-4"
                            />
                            <span
                                >Saya sudah membaca dan menyetujui SOP
                                peminjaman.</span
                            >
                        </label>
                        <InputError :message="form.errors.sop_accepted" />
                    </div>

                    <div class="flex gap-2 md:col-span-2">
                        <Button
                            type="submit"
                            :disabled="form.processing || lines.length === 0"
                        >
                            Kirim pengajuan
                        </Button>
                        <Button type="button" variant="outline" @click="cancel">
                            Batal
                        </Button>
                    </div>
                </section>
            </form>
        </template>
    </div>
</template>
