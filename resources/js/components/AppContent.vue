<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset
        v-if="props.variant === 'sidebar'"
        id="main-content"
        :class="className"
        class="min-w-0 bg-background px-4 py-5 md:px-6 md:py-6 lg:px-8 lg:py-8"
    >
        <slot />
    </SidebarInset>
    <main
        v-else
        id="main-content"
        class="flex h-full w-full min-w-0 flex-1 flex-col gap-4 rounded-xl"
        :class="className"
    >
        <slot />
    </main>
</template>
