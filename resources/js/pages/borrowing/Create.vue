<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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

const props = defineProps<{ sop: Sop }>();

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
        <div>
            <h1 class="text-xl font-semibold">Pengajuan peminjaman</h1>
            <p class="text-sm text-muted-foreground">
                Periksa barang, isi jadwal, lalu setujui SOP.
            </p>
        </div>

        <div v-if="lines.length === 0" class="rounded-lg border p-6 text-sm">
            <p class="text-muted-foreground">
                Keranjang kosong. Tambahkan barang dari
                <Link href="/inventory" class="underline"
                    >katalog inventaris</Link
                >.
            </p>
        </div>

        <template v-else>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="p-3">Kode</th>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Jumlah</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="line in lines"
                            :key="line.item_id"
                            class="border-t"
                        >
                            <td class="p-3 font-mono">{{ line.code }}</td>
                            <td class="p-3">{{ line.name }}</td>
                            <td class="p-3">
                                <Input
                                    class="w-24"
                                    type="number"
                                    min="1"
                                    :model-value="line.quantity"
                                    @update:model-value="
                                        (value) =>
                                            setQuantity(
                                                line.item_id,
                                                Number(value),
                                            )
                                    "
                                />
                            </td>
                            <td class="p-3 text-right">
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
                </table>
            </div>

            <form
                class="grid gap-4 rounded-lg border p-4 md:grid-cols-2"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="category">Kategori</Label>
                    <select
                        id="category"
                        v-model="form.category"
                        class="h-9 rounded-md border bg-transparent px-3 text-sm"
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
                        <span v-if="purposeRequired" class="text-destructive"
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
                        class="rounded-md border bg-transparent px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <InputError class="md:col-span-2" :message="itemsError" />

                <div class="rounded-lg border p-4 md:col-span-2">
                    <p class="font-medium">
                        {{ props.sop.title }} (versi {{ props.sop.version }})
                    </p>
                    <div
                        class="mt-2 max-h-48 overflow-y-auto text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ props.sop.content }}
                    </div>
                    <label class="mt-3 flex items-start gap-2 text-sm">
                        <input
                            v-model="form.sop_accepted"
                            type="checkbox"
                            class="mt-1"
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
            </form>
        </template>
    </div>
</template>
