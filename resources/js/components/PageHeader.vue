<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0">
            <h1
                class="text-[22px] leading-tight font-bold tracking-tight text-foreground md:text-[28px]"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-1 max-w-2xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
