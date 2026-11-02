<script setup lang="ts">
import { computed } from "vue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"

const props = defineProps<{
    daily: { day: string, cost: number, calls: number }[]
}>()

const DAYS_SHOWN = 30

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

const LINE_COLOR = "#c0399f"

const maxDailyCost = computed(() => Math.max(...series.value.map(day => day.cost), 0))
const totalCost = computed(() => series.value.reduce((sum, day) => sum + day.cost, 0))

const lineChart = computed(() => ({
    labels: series.value.map(day => day.label),
    datasets: [{ label: ctrans("Spend"), data: series.value.map(day => day.cost), borderColor: LINE_COLOR, backgroundColor: LINE_COLOR, tension: 0, borderWidth: 1.5, pointRadius: 2 }],
}))

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (item: { dataIndex: number }) => `${usd(series.value[item.dataIndex].cost)} · ${series.value[item.dataIndex].calls.toLocaleString()} ${ctrans("calls")}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 10, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { callback: (value: number) => usd(value) } },
    },
}
</script>

<template>
    <div class="flex h-full flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-baseline justify-between">
            <div>
                <div class="text-sm font-semibold text-gray-800">{{ ctrans("Daily spend") }}</div>
                <div class="text-xs text-gray-500">{{ ctrans("Last 30 days") }} · {{ usd(totalCost) }}</div>
            </div>
            <span class="text-xs tabular-nums text-gray-500">{{ ctrans("peak") }} {{ usd(maxDailyCost) }}</span>
        </div>

        <div class="mt-4 h-56 flex-1">
            <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
        </div>
    </div>
</template>
