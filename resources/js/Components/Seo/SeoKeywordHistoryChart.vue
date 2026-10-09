<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import axios from "axios"
import { route } from "ziggy-js"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

type History = {
    keyword: string
    checks: { date: string, position: number | null, depth: number, url: string | null }[]
    search_console: Record<string, number>
    competitors: { domain: string, label: string | null, positions: Record<string, number | null> }[]
}

const props = defineProps<{
    historyRoute: routeType
}>()

const history = ref<History | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
    try {
        history.value = (await axios.get(route(props.historyRoute.name, props.historyRoute.parameters))).data
    } catch {
        error.value = ctrans("The position history could not be loaded.")
    }
})

const COMPETITOR_COLOURS = ["#d97706", "#0891b2", "#7c3aed", "#db2777", "#65a30d", "#475569"]

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const dates = computed(() => history.value?.checks.map((check) => check.date) ?? [])

const deepest = computed(() => Math.max(10, ...(history.value?.checks.map((check) => check.depth) ?? [10])))

const chartData = computed(() => {
    if (!history.value) {
        return null
    }

    return {
        labels: dates.value.map((date) => useFormatTime(date, { formatTime: "d MMM" })),
        datasets: [
            {
                label: ctrans("Our position"),
                data: history.value.checks.map((check) => check.position),
                borderColor: accentColor(),
                backgroundColor: accentColor(),
                borderWidth: 2,
                pointRadius: 3,
                spanGaps: false,
            },
            {
                label: ctrans("Search Console average"),
                data: dates.value.map((date) => history.value!.search_console[date] ?? null),
                borderColor: "#9ca3af",
                backgroundColor: "#9ca3af",
                borderDash: [4, 4],
                borderWidth: 1.5,
                pointRadius: 2,
                spanGaps: true,
            },
            ...history.value.competitors.map((competitor, index) => ({
                label: competitor.label || competitor.domain,
                data: dates.value.map((date) => competitor.positions[date] ?? null),
                borderColor: COMPETITOR_COLOURS[index % COMPETITOR_COLOURS.length],
                backgroundColor: COMPETITOR_COLOURS[index % COMPETITOR_COLOURS.length],
                borderWidth: 1,
                pointRadius: 2,
                spanGaps: false,
            })),
        ],
    }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 10, color: "#4b5563" } },
        tooltip: { callbacks: { label: (context: { dataset: { label: string }, raw: number | null }) => `${context.dataset.label}: ${context.raw ?? ctrans("not in the results read")}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0 } },
        y: { reverse: true, min: 1, suggestedMax: deepest.value, border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", precision: 0 } },
    },
}))

const notFound = computed(() => history.value?.checks.filter((check) => check.position === null).length ?? 0)
</script>

<template>
    <div class="w-full">
        <p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
        <p v-else-if="!history" class="text-sm text-gray-500">{{ ctrans("Loading the position history") }}</p>
        <p v-else-if="!history.checks.length" class="text-sm text-gray-600">{{ ctrans("No check has finished for this keyword yet.") }}</p>
        <template v-else>
            <div class="h-72" role="img" :aria-label="ctrans('Google position of :keyword per check', { keyword: history.keyword })">
                <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
            </div>
            <p class="mt-2 text-xs text-gray-500">
                {{ ctrans("1 is the top result. A gap means the website was not in the results read (top :depth).", { depth: deepest }) }}
                <template v-if="notFound">{{ ctrans(":count checks without us in them.", { count: notFound }) }}</template>
                {{ ctrans("The Search Console line is the average over all impressions that day, every country and device.") }}
            </p>
        </template>
    </div>
</template>
