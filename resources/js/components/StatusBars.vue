<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { StatusTone } from '@/types';

type StatusItem = {
    label: string;
    value: number;
    tone: StatusTone;
};

const props = defineProps<{
    items: StatusItem[];
    title: string;
    description?: string;
}>();

const toneColors: Record<StatusTone, string> = {
    info: '#2563EB',
    success: '#16A34A',
    warning: '#D97706',
    danger: '#DC2626',
    neutral: '#64748B',
};
const max = computed(() =>
    Math.max(1, ...props.items.map((item) => item.value)),
);
</script>

<template>
    <figure class="rounded-2xl border bg-card p-5">
        <figcaption>
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </figcaption>

        <ul class="mt-4 grid gap-3">
            <li v-for="item in items" :key="item.label">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <StatusBadge :label="item.label" :tone="item.tone" />
                    <span class="tabular-nums">{{ item.value }}</span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-muted" aria-hidden="true">
                    <div
                        class="h-2 rounded-full"
                        :style="{
                            backgroundColor: toneColors[item.tone],
                            width: `${(item.value / max) * 100}%`,
                        }"
                    />
                </div>
            </li>
        </ul>
    </figure>
</template>
