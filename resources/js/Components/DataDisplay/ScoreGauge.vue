<script setup lang="ts">
import { computed } from "vue"

const props = withDefaults(defineProps<{
    value: number | null
    max?: number
    label?: string
    caption?: string
}>(), { max: 10 })

const RADIUS = 26
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

const ratio = computed(() => (props.value === null || props.max <= 0 ? 0 : Math.min(1, Math.max(0, props.value / props.max))))

const tone = computed(() => {
    if (props.value === null) {
        return { ring: "stroke-gray-300", fill: "fill-gray-50", text: "fill-gray-400" }
    }
    if (ratio.value >= 0.9) {
        return { ring: "stroke-green-500", fill: "fill-green-50", text: "fill-green-700" }
    }
    if (ratio.value >= 0.5) {
        return { ring: "stroke-amber-400", fill: "fill-amber-50", text: "fill-amber-700" }
    }

    return { ring: "stroke-red-500", fill: "fill-red-50", text: "fill-red-700" }
})
</script>

<template>
    <div class="flex flex-col items-center text-center">
        <svg viewBox="0 0 64 64" class="size-16" role="img" :aria-label="value === null ? label : `${label ?? ''} ${Math.round(value)} / ${max}`">
            <circle cx="32" cy="32" :r="RADIUS" :class="tone.fill" class="stroke-gray-100" stroke-width="5" />
            <circle
                v-if="value !== null"
                cx="32"
                cy="32"
                :r="RADIUS"
                fill="none"
                :class="tone.ring"
                stroke-width="5"
                stroke-linecap="round"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="CIRCUMFERENCE * (1 - ratio)"
                transform="rotate(-90 32 32)" />
            <text x="32" y="33" text-anchor="middle" dominant-baseline="middle" class="text-lg font-semibold tabular-nums" :class="tone.text">
                {{ value === null ? "–" : Math.round(value) }}
            </text>
        </svg>
        <span v-if="label" class="mt-2 text-sm font-medium leading-tight text-gray-800">{{ label }}</span>
        <span v-if="caption" class="mt-1 text-xs text-gray-500">{{ caption }}</span>
    </div>
</template>
