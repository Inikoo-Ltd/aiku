<script setup lang="ts">
import { computed } from "vue"
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from "chart.js"
import { Line } from "vue-chartjs"
// ctrans is a template-only global (app.config.globalProperties), script scope needs the import
import { ctrans } from "@/Composables/useTrans"

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const props = defineProps<{
    trend?: { day: string, searches: number, clicks: number, zero_results: number }[]
    title?: string
}>()

const hasData = computed(() => (props.trend ?? []).some((point) => point.searches > 0))

const labels = computed(() => (props.trend ?? []).map((point) => {
    const date = new Date(point.day)
    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}))

const chartData = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            label: ctrans("Searches"),
            data: (props.trend ?? []).map((point) => point.searches),
            borderColor: "#4f46e5",
            backgroundColor: "rgba(79,70,229,0.12)",
            fill: true,
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 4,
            borderWidth: 2,
        },
        {
            label: ctrans("Clicks"),
            data: (props.trend ?? []).map((point) => point.clicks),
            borderColor: "#16a34a",
            backgroundColor: "transparent",
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 4,
            borderWidth: 2,
        },
        {
            label: ctrans("No results"),
            data: (props.trend ?? []).map((point) => point.zero_results),
            borderColor: "#dc2626",
            backgroundColor: "transparent",
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 4,
            borderWidth: 1.5,
            borderDash: [4, 3],
        },
    ],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
        legend: {
            display: true,
            position: 'bottom' as const,
            labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 10 } } },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: "rgba(0,0,0,0.05)" } },
    },
}
</script>

<template>
    <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <h3 class="text-lg font-semibold mb-2">{{ title ?? ctrans("Search trend") }}</h3>
        <div v-if="hasData" class="h-56">
            <Line :data="chartData" :options="chartOptions" />
        </div>
        <p v-else class="text-sm text-gray-400 py-8 text-center">{{ ctrans("No search activity recorded yet") }}</p>
    </div>
</template>
