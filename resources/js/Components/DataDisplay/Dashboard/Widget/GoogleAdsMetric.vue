<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { impressionShareLabel } from "@/Composables/googleAdsFormat"
import { trans } from "laravel-vue-i18n"

/**
 * One Google Ads figure, and under it how it moved against the period before when the page is
 * comparing. Counts and money move in percent; rates and shares move in points, because a rate that
 * went from 2% to 3% did not rise 50% in any sense a marketer acts on.
 *
 * `better` says which way is good. Cost per click going up is red; clicks going up is green; spend
 * going either way is neither, because more spend that bought more sales is not bad news.
 */
const props = withDefaults(
    defineProps<{
        value: number | null
        kind: "count" | "money" | "percent" | "share" | "roas"
        currency?: string
        previous?: number | null
        better?: "up" | "down" | "none"
        size?: "sm" | "lg"
        strong?: boolean
    }>(),
    { better: "up", size: "sm", strong: false }
)

const locale = useLocaleStore()

const label = computed(() => {
    if (props.value === null) return "—"

    switch (props.kind) {
        case "count":
            return locale.number(props.value)
        case "money":
            return locale.currencyFormat(props.currency ?? "", props.value)
        case "percent":
            return props.value.toFixed(2) + "%"
        case "share":
            return impressionShareLabel(props.value)
        case "roas":
            return props.value.toFixed(2) + "×"
    }
})

const valueClass = computed(() => {
    if (props.value === null) return "text-gray-400"
    if (props.kind === "roas") return props.value >= 1 ? "text-[#006300]" : "text-[#d03b3b]"

    return props.size === "lg" || props.strong ? "text-gray-900" : "text-gray-600"
})

const isComparing = computed(() => props.previous !== undefined)

const usesPoints = computed(() => props.kind === "percent" || props.kind === "share")

/* Null when there is nothing to compare against: a figure that was zero last period cannot have
   grown by a percentage, so the line says it is new rather than inventing an infinite rise. */
const change = computed<{ text: string; direction: number } | null>(() => {
    if (!isComparing.value || props.value === null) return null

    const previous = props.previous ?? null

    if (usesPoints.value) {
        if (previous === null) return null

        const points = props.value - previous

        return { text: (points >= 0 ? "+" : "") + points.toFixed(1) + " " + trans("pt"), direction: Math.sign(points) }
    }

    if (previous === null || previous === 0) {
        return props.value > 0 ? { text: trans("new"), direction: 1 } : null
    }

    const percent = ((props.value - previous) / previous) * 100

    return { text: (percent >= 0 ? "+" : "") + percent.toFixed(1) + "%", direction: Math.sign(percent) }
})

const changeClass = computed(() => {
    if (!change.value || change.value.direction === 0 || props.better === "none") return "text-gray-500"

    const improved = props.better === "up" ? change.value.direction > 0 : change.value.direction < 0

    return improved ? "text-[#006300]" : "text-[#d03b3b]"
})

const previousLabel = computed(() => {
    if (!isComparing.value) return ""

    const previous = props.previous ?? null

    if (previous === null) return trans("Period before: no figure")

    switch (props.kind) {
        case "count":
            return trans("Period before") + ": " + locale.number(previous)
        case "money":
            return trans("Period before") + ": " + locale.currencyFormat(props.currency ?? "", previous)
        case "percent":
            return trans("Period before") + ": " + previous.toFixed(2) + "%"
        case "share":
            return trans("Period before") + ": " + impressionShareLabel(previous)
        case "roas":
            return trans("Period before") + ": " + previous.toFixed(2) + "×"
    }
})
</script>

<template>
    <div :title="previousLabel">
        <div class="tabular-nums" :class="[valueClass, size === 'lg' ? 'text-lg' : '']">{{ label }}</div>
        <div v-if="isComparing" class="text-xs tabular-nums" :class="change ? changeClass : 'text-gray-400'">
            {{ change ? change.text : "—" }}
        </div>
    </div>
</template>
