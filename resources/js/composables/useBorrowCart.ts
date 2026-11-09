import { useStorage } from '@vueuse/core';
import { computed } from 'vue';

export type BorrowCartLine = {
    item_id: number;
    code: string;
    name: string;
    quantity: number;
    tracking_method: 'serialized' | 'quantity';
};

const lines = useStorage<BorrowCartLine[]>('ent-borrow-cart', []);

export function useBorrowCart() {
    const count = computed(() =>
        lines.value.reduce((total, line) => total + line.quantity, 0),
    );

    function add(line: BorrowCartLine): void {
        const existing = lines.value.find((l) => l.item_id === line.item_id);

        if (existing) {
            existing.quantity = line.quantity;

            return;
        }

        lines.value.push(line);
    }

    function remove(itemId: number): void {
        lines.value = lines.value.filter((line) => line.item_id !== itemId);
    }

    function clear(): void {
        lines.value = [];
    }

    return { lines, count, add, remove, clear };
}
