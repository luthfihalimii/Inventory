<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Component } from 'vue';

const props = defineProps<{
    label: string;
    value: number;
    href?: string | null;
    icon?: Component;
}>();

const formatted = computed(() =>
    new Intl.NumberFormat('id-ID').format(props.value),
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        class="flex min-h-32 flex-col justify-between rounded-2xl border bg-card p-5 transition-colors"
        :class="href ? 'hover:border-primary/40 hover:bg-primary-soft/40' : ''"
    >
        <div class="flex items-start justify-between gap-3">
            <span class="text-sm font-medium text-muted-foreground">
                {{ label }}
            </span>
            <span
                v-if="icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"
            >
                <component :is="icon" class="size-5" aria-hidden="true" />
            </span>
        </div>
        <p
            class="mt-4 text-[26px] leading-none font-bold tabular-nums md:text-[32px]"
        >
            {{ formatted }}
        </p>
    </component>
</template>
