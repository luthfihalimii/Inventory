<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-3">
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-pressed="appearance === value"
            :class="[
                'flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border bg-card p-4 font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring',
                appearance === value
                    ? 'border-primary bg-primary-soft text-primary'
                    : 'text-muted-foreground hover:border-primary/50 hover:text-foreground',
            ]"
            @click="updateAppearance(value)"
        >
            <component :is="Icon" class="size-6" aria-hidden="true" />
            <span>{{ label }}</span>
        </button>
    </div>
</template>
