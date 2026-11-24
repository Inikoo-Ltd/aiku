<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faEdit } from "@fal"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
    data: {
        score: number
        complete: number
        total: number
        items: Array<{
            key: string
            label: string
            is_complete: boolean
        }>
    }
    shopsTotal: number
}>()

const emit = defineEmits<{
    edit: []
}>()

const GAUGE_RADIUS = 80

const gaugeBands = [
    { from: 0, to: 0.6, color: "#f87171" },
    { from: 0.6, to: 0.9, color: "#fbbf24" },
    { from: 0.9, to: 1, color: "#34d399" },
]

const gaugePoint = (fraction: number, radius = GAUGE_RADIUS) => {
    const angle = Math.PI * (1 - fraction)

    return { x: 100 + radius * Math.cos(angle), y: 100 - radius * Math.sin(angle) }
}

const gaugeArc = (from: number, to: number) => {
    const start = gaugePoint(from)
    const end = gaugePoint(to)

    return `M ${start.x} ${start.y} A ${GAUGE_RADIUS} ${GAUGE_RADIUS} 0 0 1 ${end.x} ${end.y}`
}

const scoreFraction = computed(() => Math.min(Math.max(props.data.score, 0), 100) / 100)
const marker = computed(() => gaugePoint(scoreFraction.value))
const needleTip = computed(() => gaugePoint(scoreFraction.value, GAUGE_RADIUS - 28))

const scoreStatus = computed(() => {
    if (props.data.score === 100) {
        return { label: ctrans("Complete"), class: "bg-green-100 text-green-700" }
    }
    if (props.data.score >= 75) {
        return { label: ctrans("Nearly complete"), class: "bg-amber-100 text-amber-700" }
    }
    if (props.data.score >= 50) {
        return { label: ctrans("Needs work"), class: "bg-orange-100 text-orange-700" }
    }

    return { label: ctrans("Incomplete"), class: "bg-red-100 text-red-700" }
})

const sortedItems = computed(() => [...props.data.items].sort((a, b) => Number(a.is_complete) - Number(b.is_complete)))
const missingItems = computed(() => props.data.items.filter(item => !item.is_complete))
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Master completeness") }}</h3>
                <p class="text-[11px] text-gray-500">{{ ctrans("Information the Master provides for all :count shop pages", { count: shopsTotal }) }}</p>
            </div>
            <Button type="tertiary" size="xs" :icon="faEdit" v-tooltip="ctrans('Edit in page settings')" :aria-label="ctrans('Edit in page settings')" @click="emit('edit')" />
        </div>

        <div class="mt-2 flex items-center gap-3">
            <svg viewBox="0 0 200 110" class="w-24 shrink-0" role="img" :aria-label="ctrans(':score out of 100', { score: data.score })">
                <path v-for="band in gaugeBands" :key="band.from" :d="gaugeArc(band.from, band.to)" :stroke="band.color" stroke-width="22" fill="none" />
                <line x1="100" y1="100" :x2="needleTip.x" :y2="needleTip.y" stroke="#1f2937" stroke-width="5" stroke-linecap="round" />
                <circle cx="100" cy="100" r="7" fill="#1f2937" />
                <circle :cx="marker.x" :cy="marker.y" r="9" fill="#fff" stroke="#1f2937" stroke-width="4" />
            </svg>
            <div class="min-w-0">
                <div class="flex items-baseline gap-1">
                    <span class="text-2xl font-bold leading-none text-gray-800">{{ data.score }}</span>
                    <span class="text-xs text-gray-500">/ 100</span>
                </div>
                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="scoreStatus.class">{{ scoreStatus.label }}</span>
                <p class="mt-0.5 text-[11px] text-gray-500">
                    {{ ctrans(":complete of :total items complete", { complete: data.complete, total: data.total }) }}
                </p>
            </div>
        </div>

        <div class="mt-2 flex flex-wrap gap-1">
            <span
                v-for="item in sortedItems"
                :key="item.key"
                class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-[11px] leading-tight"
                :class="item.is_complete ? 'border-green-200 bg-green-50 text-gray-600' : 'border-red-300 bg-red-50 font-semibold text-red-700'">
                <FontAwesomeIcon :icon="item.is_complete ? faCheck : faTimes" :class="item.is_complete ? 'text-green-600' : 'text-red-500'" fixed-width aria-hidden="true" />
                {{ item.label }}
            </span>
        </div>

        <p v-if="missingItems.length" class="mt-2 text-[11px] font-semibold text-red-600">
            {{ ctransChoice(":count item missing|:count items missing", missingItems.length, { count: missingItems.length }) }}
        </p>
    </div>
</template>
