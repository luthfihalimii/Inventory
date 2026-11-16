<script setup lang="ts">
import { computed } from 'vue';
import type { StatusTone } from '@/types';

const props = withDefaults(
    defineProps<{
        label: string;
        tone?: StatusTone;
    }>(),
    { tone: 'neutral' },
);

const toneClass = computed(
    () =>
        ({
            neutral:
                'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            info: 'bg-[#DBEAFE] text-[#1E40AF] dark:bg-[#1E3A8A] dark:text-[#BFDBFE]',
            success:
                'bg-[#DCFCE7] text-[#166534] dark:bg-[#14532D] dark:text-[#86EFAC]',
            warning:
                'bg-[#FEF3C7] text-[#92400E] dark:bg-[#78350F] dark:text-[#FCD34D]',
            danger: 'bg-[#FEE2E2] text-[#991B1B] dark:bg-[#7F1D1D] dark:text-[#FCA5A5]',
        })[props.tone],
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
        :class="toneClass"
    >
        {{ label }}
    </span>
</template>
