<script setup lang="ts">
import { computed, useId } from 'vue';

type TrendPoint = {
    label: string;
    value: number;
};

const props = defineProps<{
    points: TrendPoint[];
    title: string;
    description?: string;
}>();

const gradientId = `trend-gradient-${useId()}`;
const hasData = computed(() => props.points.some((point) => point.value > 0));
const coordinates = computed(() => {
    const max = Math.max(1, ...props.points.map((point) => point.value));
    const denominator = Math.max(1, props.points.length - 1);

    return props.points.map((point, index) => ({
        x: (index / denominator) * 100,
        y: 90 - (point.value / max) * 80,
    }));
});
const linePath = computed(() =>
    coordinates.value
        .map(({ x, y }, index) => `${index === 0 ? 'M' : 'L'} ${x} ${y}`)
        .join(' '),
);
const areaPath = computed(
    () =>
        `M 0 100 ${coordinates.value
            .map(({ x, y }) => `L ${x} ${y}`)
            .join(' ')} L 100 100 Z`,
);
</script>

<template>
    <figure v-if="hasData" class="rounded-2xl border bg-card p-5">
        <figcaption>
            <h2 class="text-base font-semibold">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </figcaption>

        <svg
            viewBox="0 0 100 100"
            preserveAspectRatio="none"
            class="mt-4 h-44 w-full sm:h-52"
            aria-hidden="true"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stop-color="var(--chart-1)"
                        stop-opacity="0.22"
                    />
                    <stop
                        offset="100%"
                        stop-color="var(--chart-1)"
                        stop-opacity="0.02"
                    />
                </linearGradient>
            </defs>
            <line
                v-for="y in [20, 50, 80]"
                :key="y"
                x1="0"
                :y1="y"
                x2="100"
                :y2="y"
                stroke="var(--border)"
                vector-effect="non-scaling-stroke"
            />
            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path
                :d="linePath"
                stroke="var(--chart-1)"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
                fill="none"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        </svg>

        <div
            class="mt-2 grid text-center text-xs text-muted-foreground"
            :style="{
                gridTemplateColumns: `repeat(${points.length}, minmax(0, 1fr))`,
            }"
        >
            <span v-for="point in points" :key="point.label">
                {{ point.label }}
            </span>
        </div>

        <table class="sr-only">
            <caption>
                Data
                {{
                    title
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">Bulan</th>
                    <th scope="col">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.label">
                    <th scope="row">{{ point.label }}</th>
                    <td>{{ point.value }}</td>
                </tr>
            </tbody>
        </table>
    </figure>
</template>
