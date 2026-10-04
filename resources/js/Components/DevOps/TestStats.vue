<script setup lang="ts">
import { computed } from "vue"
import { Bar } from "vue-chartjs"
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from "chart.js"
import { ctrans } from "@/Composables/useTrans"

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

export interface TestStatsData {
    days: number
    runs: number
    runs_per_day: number
    pass_rate: number | null
    average_seconds: number | null
    average_tests: number | null
    daily: { day: string, passed: number, failed: number }[]
}

const props = defineProps<{ stats: TestStatsData }>()

const formatDuration = (total: number | null) => total === null ? "-" : total >= 60 ? `${Math.floor(total / 60)}m ${String(total % 60).padStart(2, "0")}s` : `${total}s`

const tiles = computed(() => [
    { label: ctrans("Runs per day"), value: props.stats.runs_per_day.toLocaleString(), note: ctrans(":count runs", { count: String(props.stats.runs) }) },
    { label: ctrans("Pass rate"), value: props.stats.pass_rate === null ? "-" : `${props.stats.pass_rate}%`, note: null },
    { label: ctrans("Average passing run"), value: formatDuration(props.stats.average_seconds), note: null },
    { label: ctrans("Tests per run"), value: props.stats.average_tests === null ? "-" : props.stats.average_tests.toLocaleString(), note: ctrans("average") },
])

const chart = computed(() => ({
    labels: props.stats.daily.map(day => new Date(`${day.day}T00:00:00`).toLocaleDateString([], { day: "numeric", month: "short" })),
    datasets: [
        { label: ctrans("Passed"), data: props.stats.daily.map(day => day.passed), backgroundColor: "#10b981", stack: "runs" },
        { label: ctrans("Failed"), data: props.stats.daily.map(day => day.failed), backgroundColor: "#ef4444", stack: "runs" },
    ],
}))

const options = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false as const,
    plugins: { legend: { position: "bottom" as const } },
    scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } },
}
</script>

<template>
    <div class="rounded-lg border border-gray-200 p-4">
        <div class="flex items-baseline justify-between">
            <span class="text-sm font-medium">{{ ctrans("Tests on main, last :days days", { days: String(stats.days) }) }}</span>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="tile in tiles" :key="tile.label" class="rounded bg-gray-50 p-3">
                <div class="text-xs text-gray-500">{{ tile.label }}</div>
                <div class="mt-1 text-lg font-semibold tabular-nums">{{ tile.value }}</div>
                <div v-if="tile.note" class="text-xs text-gray-400">{{ tile.note }}</div>
            </div>
        </div>
        <div class="mt-4 h-40"><Bar :data="chart" :options="options" /></div>
    </div>
</template>
