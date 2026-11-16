<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed bg-card px-6 py-12 text-center"
    >
        <span
            class="flex size-12 items-center justify-center rounded-2xl bg-primary-soft text-primary"
        >
            <component :is="icon ?? Inbox" class="size-6" aria-hidden="true" />
        </span>
        <p class="mt-4 font-semibold text-foreground">{{ title }}</p>
        <p
            v-if="description"
            class="mt-1 max-w-md text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-4 flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
