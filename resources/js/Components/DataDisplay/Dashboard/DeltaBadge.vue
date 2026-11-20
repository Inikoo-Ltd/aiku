<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

/**
 * The change of a figure against the compared period. `unit` shows it as a percentage of the
 * compared value (counts, money), as percentage points (rates already in %) or as a plain
 * difference (positions). `unavailable` marks a compared period with no data at all, so a drop to
 * nothing is not shown as -100%.
 */
const props = withDefaults(defineProps<{
    current: number | null | undefined
    previous: number | null | undefined
    better?: "up" | "down" | "none"
    format?: (value: number) => string | number
    unit?: "percent" | "points" | "difference"
    unavailable?: boolean
    label?: string
}>(), {
    better: "up",
    unit: "percent",
    unavailable: false,
})

const round = (value: number) => Math.round(value * 10) / 10

const change = computed(() => {
    if (props.current === null || props.current === undefined || props.previous === null || props.previous === undefined) {
        return null
    }
    if (props.unit !== "percent") {
        const difference = round(props.current - props.previous)
        const value = Math.abs(difference).toLocaleString(undefined, { maximumFractionDigits: 1 })
        return { label: props.unit === "points" ? ctrans(":value pts", { value }) : value, direction: Math.sign(difference) }
    }
    if (props.previous === 0) {
        return props.current === 0 ? { label: "0%", direction: 0 } : { label: ctrans("new"), direction: Math.sign(props.current) }
    }
    const percent = Math.round((props.current - props.previous) / Math.abs(props.previous) * 100)
    return { label: `${Math.abs(percent)}%`, direction: Math.sign(percent) }
})

const tone = computed(() => {
    if (!change.value || change.value.direction === 0 || props.better === "none") {
        return "bg-gray-100 text-gray-600"
    }
    const isGood = props.better === "up" ? change.value.direction > 0 : change.value.direction < 0
    return isGood ? "bg-green-50 text-green-700" : "bg-red-50 text-red-700"
})

const tooltip = computed(() => {
    if (props.unavailable) {
        return props.label ? ctrans(":period has no data", { period: props.label }) : ctrans("The compared period has no data")
    }
    if (props.previous === null || props.previous === undefined) {
        return undefined
    }
    const value = String(props.format ? props.format(props.previous) : props.previous)
    return props.label ? ctrans(":period: :value", { period: props.label, value }) : ctrans("Compared period: :value", { value })
})
</script>

<template>
    <span v-if="unavailable" v-tooltip="tooltip" class="inline-flex items-center whitespace-nowrap rounded-full bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-500">
        {{ ctrans("No data") }}
    </span>
    <span v-else-if="change" v-tooltip="tooltip" class="inline-flex items-center gap-0.5 whitespace-nowrap rounded-full px-1.5 py-0.5 text-xs font-medium tabular-nums" :class="tone">
        <span aria-hidden="true">{{ change.direction > 0 ? "▲" : change.direction < 0 ? "▼" : "" }}</span>
        <span v-if="change.direction !== 0" class="sr-only">{{ change.direction > 0 ? ctrans("Up") : ctrans("Down") }}</span>
        <span>{{ change.label }}</span>
    </span>
</template>
