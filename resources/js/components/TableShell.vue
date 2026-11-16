<script setup lang="ts">
defineProps<{
    caption: string;
}>();
</script>

<template>
    <div class="overflow-hidden rounded-2xl border bg-card">
        <div v-if="$slots.toolbar" class="border-b p-4">
            <slot name="toolbar" />
        </div>
        <div class="max-h-[70vh] overflow-auto">
            <table
                data-slot="responsive-table"
                class="w-full text-sm [&_tbody_tr]:border-t [&_tbody_tr]:border-border/70 [&_tbody_tr:hover]:bg-slate-50/70 dark:[&_tbody_tr:hover]:bg-slate-900/40 [&_td]:px-2 [&_td]:py-2 [&_td]:align-middle sm:[&_td]:px-4 sm:[&_td]:py-3 [&_th]:px-2 [&_th]:py-2 [&_th]:text-left [&_th]:text-xs [&_th]:font-semibold [&_th]:text-muted-foreground sm:[&_th]:px-4 sm:[&_th]:py-3 [&_thead]:bg-slate-50 dark:[&_thead]:bg-slate-900/60 [&_thead_th]:sticky [&_thead_th]:top-0 [&_thead_th]:z-10 [&_thead_th]:bg-slate-50 dark:[&_thead_th]:bg-slate-900"
            >
                <caption class="sr-only">
                    {{
                        caption
                    }}
                </caption>
                <slot />
            </table>
        </div>
    </div>
</template>
