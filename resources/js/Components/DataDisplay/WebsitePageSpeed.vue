<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 21 Sep 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDesktop, faMobile } from "@fal"

type Strategy = "desktop" | "mobile"
type Score = "performance" | "accessibility" | "best_practices" | "seo"
type HistoryRecord = { date: string; webpages: Record<Strategy, number> } & Record<Strategy, Record<Score, number | null>>

const props = defineProps<{
    pagespeed?: {
        frequency: "daily" | "weekly"
        start_date: string
        end_date: string
        measured_webpages: number
        last_measured_on: string | null
        history: HistoryRecord[]
    }
}>()

const SCORE_COLOR = "#E8710A"

const bandColor = {
    good: "#0CCE6B",
    average: "#FFA400",
    poor: "#FF4E42",
}

const scores: Array<{ key: Score; label: string }> = [
    { key: "performance", label: ctrans("Performance") },
    { key: "accessibility", label: ctrans("Accessibility") },
    { key: "best_practices", label: ctrans("Best practices") },
    { key: "seo", label: ctrans("SEO") },
]

const strategies: Array<{ key: Strategy; label: string; icon: typeof faDesktop; borderDash: number[]; pointStyle: string }> = [
    { key: "desktop", label: ctrans("Desktop"), icon: faDesktop, borderDash: [], pointStyle: "circle" },
    { key: "mobile", label: ctrans("Mobile"), icon: faMobile, borderDash: [6, 4], pointStyle: "rectRot" },
]

const score = ref<Score>("performance")

const isLoading = computed(() => props.pagespeed === undefined)
const history = computed(() => props.pagespeed?.history ?? [])
const hasHistory = computed(() => history.value.length > 0)
const latest = computed(() => history.value[history.value.length - 1] ?? null)

const colorOfScore = (value: number | null) => {
    if (value === null) {
        return "#9ca3af"
    }

    return value >= 90 ? bandColor.good : value >= 50 ? bandColor.average : bandColor.poor
}

const chartData = computed(() => ({
    labels: history.value.map((record) => record.date),
    datasets: strategies.map((strategy) => ({
        label: strategy.label,
        data: history.value.map((record) => record[strategy.key]?.[score.value] ?? null),
        borderColor: SCORE_COLOR,
        backgroundColor: SCORE_COLOR,
        borderDash: strategy.borderDash,
        borderWidth: 2,
        pointRadius: 3,
        pointStyle: strategy.pointStyle,
        spanGaps: true,
    })),
}))

const webpagesOn = (index: number, strategy: Strategy) => history.value[index]?.webpages?.[strategy] ?? 0

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: "#fff",
            titleColor: "#111827",
            bodyColor: "#374151",
            borderColor: "#d1d5db",
            borderWidth: 1,
            padding: 10,
            callbacks: {
                title: (items: any[]) =>
                    (props.pagespeed?.frequency === "weekly" ? ctrans("Week of") + " " : "") + useFormatTime(items[0].label, { formatTime: "PPP" }),
                label: (item: any) =>
                    `${item.dataset.label}: ${item.raw ?? "-"} (${ctrans(":count pages", {
                        count: webpagesOn(item.dataIndex, strategies[item.datasetIndex].key),
                    })})`,
            },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 10, color: "#4b5563" } },
        y: { min: 0, max: 100, grid: { color: "#f3f4f6" }, ticks: { stepSize: 25, color: "#4b5563" } },
    },
}))
</script>

<template>
    <div class="rounded-lg bg-white shadow">
        <div class="flex flex-wrap items-center gap-3 border-b px-6 py-3">
            <span class="text-sm font-semibold">{{ ctrans("PageSpeed Insights") }}</span>

            <span class="text-xs text-gray-600">
                {{
                    pagespeed?.frequency === "weekly"
                        ? ctrans("Weekly average of every measured page of this website")
                        : ctrans("Daily average of every measured page of this website")
                }}
            </span>

            <select v-model="score" :aria-label="ctrans('PageSpeed score')" class="ml-auto rounded border-gray-300 py-1 pl-2 pr-8 text-xs">
                <option v-for="option in scores" :key="option.key" :value="option.key">{{ option.label }}</option>
            </select>
        </div>

        <div v-if="isLoading" class="space-y-3 px-6 py-6">
            <div class="h-4 w-56 animate-pulse rounded bg-gray-200" />
            <div class="h-64 w-full animate-pulse rounded bg-gray-100" />
        </div>

        <div v-else-if="!hasHistory" class="px-6 py-6 text-sm text-gray-600">
            {{ ctrans("No pages of this website have been measured yet. The nightly crawl adds a point for every page it measures.") }}
        </div>

        <div v-else class="space-y-4 px-6 py-6">
            <div class="flex flex-wrap items-center gap-4">
                <div v-for="strategy in strategies" :key="strategy.key" class="flex items-center gap-2">
                    <FontAwesomeIcon :icon="strategy.icon" class="text-gray-500" fixed-width aria-hidden="true" />
                    <span class="text-xs text-gray-600">{{ strategy.label }}</span>
                    <span class="text-lg font-semibold" :style="{ color: colorOfScore(latest?.[strategy.key]?.[score] ?? null) }">
                        {{ latest?.[strategy.key]?.[score] ?? ctrans("n/a") }}
                    </span>
                    <svg width="18" height="4" aria-hidden="true">
                        <line x1="0" y1="2" x2="18" y2="2" :stroke="SCORE_COLOR" stroke-width="2" :stroke-dasharray="strategy.borderDash.join(' ')" />
                    </svg>
                </div>

                <span class="text-xs text-gray-600">
                    {{ ctrans(":count pages measured since :date", { count: pagespeed?.measured_webpages ?? 0, date: pagespeed?.start_date ?? "" }) }}
                </span>
            </div>

            <div class="h-64 w-full">
                <Chart type="line" class="h-full" :data="chartData" :options="chartOptions" />
            </div>
        </div>
    </div>
</template>
