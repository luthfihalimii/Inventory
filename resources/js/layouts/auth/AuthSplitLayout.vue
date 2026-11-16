<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AuthShowcase from '@/components/AuthShowcase.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-dvh bg-background p-4 md:p-6 lg:p-8">
        <div
            class="mx-auto grid w-full max-w-6xl overflow-hidden rounded-3xl border bg-card lg:max-w-[1120px] lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
        >
            <div
                class="flex flex-col justify-center px-6 py-10 md:px-12 lg:px-16"
            >
                <div class="mx-auto w-full max-w-sm">
                    <div class="mb-8 flex items-center gap-3">
                        <AppLogo />
                    </div>
                    <div class="mb-8 space-y-2">
                        <h1 v-if="title" class="text-2xl font-bold">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
            <AuthShowcase class="hidden lg:block" />
        </div>
    </div>
</template>
