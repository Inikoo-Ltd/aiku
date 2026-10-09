<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import Chart from "primevue/chart"
import SeoDomainPicker from "@/Components/Seo/SeoDomainPicker.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

type DomainRow = {
    domain: string
    is_ours: boolean
    rank: number | null
    referring_domains: number | null
    backlinks: number | null
    organic_keywords: number | null
    estimated_traffic: number | null
    top_3: number | null
    top_10: number | null
    intents: Record<string, number>
    fetched_at: string | null
    search_traffic: number | null
    search_traffic_year_ago: number | null
    paid_traffic: number | null
    traffic_month: string | null
    traffic_fetched_at: string | null
    traffic_history: Record<string, number>
}

type ComparisonData = {
    competitors: string[]
    domains: string[]
    max: number
    parameter: string
    result: DomainRow[] | null
    error: string | null
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: ComparisonData | null
    tab: string
    market: { domain: string, country: string, language: string } | null
}>()

const locale = useLocaleStore()

const number = (value: number | null) => value === null ? "-" : locale.number(Math.round(value))

const intentLabels: Record<string, string> = {
    informational: ctrans("Informational"),
    navigational: ctrans("Navigational"),
    commercial: ctrans("Commercial"),
    transactional: ctrans("Transactional"),
}

const yearChange = (row: DomainRow) => {
    if (row.search_traffic === null || !row.search_traffic_year_ago) {
        return { text: "-", class: "" }
    }

    const percent = Math.round((row.search_traffic - row.search_traffic_year_ago) / row.search_traffic_year_ago * 100)

    return { text: `${percent > 0 ? "+" : ""}${locale.number(percent)}%`, class: percent > 0 ? "text-green-700" : percent < 0 ? "text-red-700" : "" }
}

const sections = computed(() => [
    {
        title: ctrans("Backlinks"),
        source: ctrans("DataForSEO Backlinks, weekly"),
        metrics: [
            { label: ctrans("Rank"), hint: ctrans("DataForSEO domain rank, 0 to 100"), value: (row: DomainRow) => number(row.rank) },
            { label: ctrans("Referring domains"), value: (row: DomainRow) => number(row.referring_domains) },
            { label: ctrans("Backlinks"), value: (row: DomainRow) => number(row.backlinks) },
        ],
    },
    {
        title: ctrans("Organic search in :country", { country: props.market?.country ?? "" }),
        source: ctrans("DataForSEO Labs estimates from Google rankings, monthly"),
        metrics: [
            { label: ctrans("Organic keywords"), hint: ctrans("Keywords in the top 100"), value: (row: DomainRow) => number(row.organic_keywords) },
            { label: ctrans("Estimated traffic"), hint: ctrans("Monthly visits from Google, estimated from positions and volumes. Not a measurement"), value: (row: DomainRow) => number(row.estimated_traffic) },
            { label: ctrans("Keywords in the top 3"), value: (row: DomainRow) => number(row.top_3) },
            { label: ctrans("Keywords in the top 10"), value: (row: DomainRow) => number(row.top_10) },
            ...Object.entries(intentLabels).map(([intent, label]) => ({
                label: ctrans(":intent keywords", { intent: label }),
                hint: ctrans("Share of the domain's keywords with this search intent"),
                value: (row: DomainRow) => row.intents[intent] === undefined ? "-" : `${row.intents[intent]}%`,
            })),
        ],
    },
    {
        title: ctrans("Search traffic in :country", { country: props.market?.country ?? "" }),
        source: ctrans("DataForSEO Labs, monthly history back to October 2020"),
        metrics: [
            { label: ctrans("Organic visits, latest month"), hint: ctrans("Monthly visits from Google organic results in the latest month DataForSEO has, estimated from positions and volumes. Not a measurement"), value: (row: DomainRow) => number(row.search_traffic) },
            { label: ctrans("Change in 12 months"), hint: ctrans("Organic visits of the latest month against the same month a year before"), value: (row: DomainRow) => yearChange(row).text, class: (row: DomainRow) => yearChange(row).class },
            { label: ctrans("Paid visits"), hint: ctrans("Monthly visits from Google Ads, estimated the same way"), value: (row: DomainRow) => number(row.paid_traffic) },
        ],
    },
])

const COMPETITOR_COLOURS = ["#d97706", "#0891b2", "#7c3aed", "#db2777", "#65a30d", "#475569"]

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const trafficChart = computed(() => {
    const rows = props.data?.result ?? []
    const months = [...new Set(rows.flatMap((row) => Object.keys(row.traffic_history ?? {})))].sort()

    if (months.length < 2) {
        return null
    }

    let competitorIndex = 0

    return {
        labels: months.map((month) => useFormatTime(`${month}-01`, { formatTime: "MMM yy" })),
        datasets: rows.map((row) => {
            const colour = row.is_ours ? accentColor() : COMPETITOR_COLOURS[competitorIndex++ % COMPETITOR_COLOURS.length]

            return {
                label: row.domain,
                data: months.map((month) => row.traffic_history?.[month] ?? null),
                borderColor: colour,
                backgroundColor: colour,
                borderWidth: row.is_ours ? 2 : 1,
                pointRadius: 0,
                spanGaps: false,
            }
        }),
    }
})

const trafficChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 10, color: "#4b5563" } },
        tooltip: { callbacks: { label: (context: { dataset: { label: string }, raw: number | null }) => `${context.dataset.label}: ${context.raw === null ? "-" : locale.number(context.raw)}` } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0, autoSkipPadding: 12 } },
        y: { min: 0, border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", callback: (value: number) => locale.number(value) } },
    },
}

const hasMissing = computed(() => props.data?.result?.some((row) => row.fetched_at === null || row.traffic_fetched_at === null) ?? false)
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <SeoDomainPicker
            :tab="tab"
            :parameter="data.parameter"
            :competitors="data.competitors"
            :domains="data.domains"
            :max="data.max"
            :label="ctrans('Compare')" />

        <p v-if="data.error" role="alert" class="rounded-xl bg-white px-5 py-4 text-sm text-red-700 ring-1 ring-gray-200">{{ data.error }}</p>

        <p v-if="!data.result" class="text-sm text-gray-600">
            {{ ctrans("Pick up to :max competitors to see them next to :domain. A domain not fetched in the last four weeks costs about 15 cents of DataForSEO credit, and its search traffic history about 12 cents for all the domains compared.", { max: data.max, domain: market?.domain ?? "" }) }}
        </p>

        <section v-else class="overflow-x-auto rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Domain comparison')">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th scope="col" class="px-5 py-2 font-medium" />
                        <th v-for="row in data.result" :key="row.domain" scope="col" class="px-3 py-2 text-right font-medium" :class="row.is_ours ? 'text-gray-900' : ''">
                            {{ row.domain }}
                        </th>
                    </tr>
                </thead>
                <tbody v-for="section in sections" :key="section.title" class="divide-y divide-gray-100">
                    <tr class="bg-gray-50">
                        <th scope="colgroup" :colspan="data.result.length + 1" class="px-5 py-2 text-left text-xs font-medium text-gray-700">
                            {{ section.title }}
                            <span class="ml-1 font-normal text-gray-500">{{ section.source }}</span>
                        </th>
                    </tr>
                    <tr v-for="metric in section.metrics" :key="metric.label">
                        <th scope="row" class="px-5 py-2 text-left font-normal text-gray-600">
                            <span v-tooltip="metric.hint">{{ metric.label }}</span>
                        </th>
                        <td v-for="row in data.result" :key="row.domain" class="px-3 py-2 text-right tabular-nums" :class="[row.is_ours ? 'font-medium text-gray-900' : 'text-gray-700', metric.class?.(row)]">
                            {{ metric.value(row) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 text-xs text-gray-500">
                        <th scope="row" class="px-5 py-2 text-left font-normal">{{ ctrans("Keywords fetched") }}</th>
                        <td v-for="row in data.result" :key="row.domain" class="px-3 py-2 text-right tabular-nums">
                            {{ row.fetched_at ? useFormatTime(row.fetched_at) : ctrans("Not yet") }}
                        </td>
                    </tr>
                    <tr class="text-xs text-gray-500">
                        <th scope="row" class="px-5 py-2 text-left font-normal">{{ ctrans("Traffic fetched") }}</th>
                        <td v-for="row in data.result" :key="row.domain" class="px-3 py-2 text-right tabular-nums">
                            {{ row.traffic_fetched_at ? useFormatTime(row.traffic_fetched_at) : ctrans("Not yet") }}
                        </td>
                    </tr>
                </tfoot>
            </table>
            <p v-if="hasMissing" class="border-t border-gray-100 px-5 py-2 text-xs text-gray-500">
                {{ ctrans("Some domains have not been fetched yet. Press Compare to fetch them now, or wait for the weekly run.") }}
            </p>
        </section>

        <section v-if="trafficChart" class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Organic search traffic per month')">
            <div class="border-b border-gray-100 px-5 py-3">
                <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Organic search traffic per month") }}</h2>
                <p class="text-xs text-gray-500">{{ ctrans("Estimated monthly visits from Google in :country over the last three years. A gap means DataForSEO did not see the domain that month.", { country: market?.country ?? "" }) }}</p>
            </div>
            <div class="h-72 px-5 py-4" role="img" :aria-label="ctrans('Estimated organic visits per month for each domain')">
                <Chart type="line" :data="trafficChart" :options="trafficChartOptions" class="h-full" />
            </div>
        </section>
    </div>
</template>
