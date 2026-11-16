<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import QRCode from 'qrcode.vue';
import { Button } from '@/components/ui/button';

type Item = {
    id: number;
    code: string;
    name: string;
    qr_token: string;
    tracking_method: 'serialized' | 'quantity';
    assets: { id: number; asset_code: string; qr_token: string }[];
};

defineProps<{ items: Item[] }>();

function print(): void {
    window.print();
}
</script>

<template>
    <Head title="Label QR" />

    <div
        style="color-scheme: light"
        class="min-h-screen bg-white p-6 text-slate-900 print:p-0"
    >
        <div class="mb-6 flex items-center justify-between print:hidden">
            <div>
                <h1 class="text-lg font-semibold">Label QR</h1>
                <p class="text-sm text-slate-600">{{ items.length }} barang</p>
            </div>
            <Button
                variant="outline"
                class="border-slate-300 bg-white text-slate-900 hover:bg-slate-100"
                @click="print"
            >
                Cetak
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <template v-for="item in items" :key="item.id">
                <div
                    v-if="item.tracking_method === 'quantity'"
                    class="flex break-inside-avoid flex-col items-center gap-2 rounded border border-slate-300 bg-white p-4 text-slate-900"
                >
                    <QRCode :value="item.qr_token" :size="140" />
                    <p class="text-center text-sm font-medium">
                        {{ item.name }}
                    </p>
                    <p class="font-mono text-xs">{{ item.code }}</p>
                </div>

                <div
                    v-for="asset in item.assets"
                    :key="asset.id"
                    class="flex break-inside-avoid flex-col items-center gap-2 rounded border border-slate-300 bg-white p-4 text-slate-900"
                >
                    <QRCode :value="asset.qr_token" :size="140" />
                    <p class="text-center text-sm font-medium">
                        {{ item.name }}
                    </p>
                    <p class="font-mono text-xs">{{ asset.asset_code }}</p>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
@media print {
    @page {
        margin: 12mm;
    }
}
</style>
