<script setup lang="ts">
import { computed, inject } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"
import { layoutStructure } from "@/Composables/useLayoutStructure"

const props = defineProps<{
    daily: { day: string, cost: number, calls: number }[]
}>()

const DAYS_SHOWN = 30

const layout = inject("layout", layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])

const toDayKey = (date: Date) => {
    const month = String(date.getMonth() + 1).padStart(2, "0")
    const day = String(date.getDate()).padStart(2, "0")
    return `${date.getFullYear()}-${month}-${day}`
}

const series = computed(() => {
    const byDay = new Map(props.daily.map(record => [record.day.slice(0, 10), record]))
    const lastDay = props.daily.length
        ? new Date(Math.max(Date.now(), ...props.daily.map(record => new Date(record.day.slice(0, 10) + "T00:00:00").getTime())))
        : new Date()

    return Array.from({ length: DAYS_SHOWN }, (_, index) => {
        const date = new Date(lastDay)
        date.setDate(lastDay.getDate() - (DAYS_SHOWN - 1 - index))
        const key = toDayKey(date)
        const record = byDay.get(key)

        return {
            key,
            label: date.toLocaleDateString(undefined, { day: "numeric", month: "short" }),
            cost: record?.cost ?? 0,
            calls: record?.calls ?? 0,
        }
    })
})

const maxDailyCost = computed(() => Math.max(...series.value.map(day => day.cost), 0.000001))
const totalCost = computed(() => series.value.reduce((sum, day) => sum + day.cost, 0))

const gridLines = computed(() => [1, 0.5, 0].map(fraction => usd(maxDailyCost.value * fraction)))

const axisLabels = computed(() => series.value.filter((_, index) => (DAYS_SHOWN - 1 - index) % 7 === 0))

const barHeight = (cost: number) => cost > 0 ? Math.max((cost / maxDailyCost.value) * 100, 2) + "%" : "2px"

const tooltip = (day: { label: string, cost: number, calls: number }) =>
    `${day.label} — ${usd(day.cost)} · ${day.calls.toLocaleString()} ${ctrans("calls")}`
</script>

<template>
    <div class="flex h-full flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-baseline justify-between">
            <div>
                <div class="text-sm font-semibold text-gray-800">{{ ctrans("Daily spend") }}</div>
                <div class="text-xs text-gray-500">{{ ctrans("Last 30 days") }} · {{ usd(totalCost) }}</div>
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs tabular-nums" :style="{ backgroundColor: `color-mix(in srgb, ${primaryColor} 12%, transparent)`, color: primaryColor }">
                {{ ctrans("peak") }} {{ usd(maxDailyCost) }}
            </span>
        </div>

        <div class="mt-4 flex min-h-[12rem] flex-1 gap-2">
            <div class="flex flex-col justify-between pb-5 text-right text-[10px] tabular-nums text-gray-400">
                <span v-for="(gridLabel, idxGrid) in gridLines" :key="idxGrid" class="-translate-y-1/2 last:translate-y-1/2">{{ gridLabel }}</span>
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                        <div class="border-t border-dashed border-gray-100" />
                        <div class="border-t border-dashed border-gray-100" />
                        <div class="border-t border-gray-200" />
                    </div>
                    <div class="relative flex h-full items-end gap-[3px]">
                        <div v-for="day in series" :key="day.key" v-tooltip.top="tooltip(day)"
                            class="group flex h-full min-w-0 flex-1 cursor-pointer items-end rounded-t hover:bg-gray-50">
                            <div class="w-full rounded-t transition-opacity"
                                :class="day.cost > 0 ? 'opacity-75 group-hover:opacity-100' : 'opacity-20'"
                                :style="{ height: barHeight(day.cost), backgroundColor: day.cost > 0 ? primaryColor : '#9ca3af' }" />
                        </div>
                    </div>
                </div>
                <div class="relative mt-1.5 h-4 text-[10px] tabular-nums text-gray-400">
                    <span v-for="day in axisLabels" :key="day.key" class="absolute -translate-x-1/2 whitespace-nowrap last:-translate-x-full"
                        :style="{ left: ((series.findIndex(item => item.key === day.key) + 0.5) / series.length) * 100 + '%' }">
                        {{ day.label }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
