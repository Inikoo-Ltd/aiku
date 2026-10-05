<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSitemap, faBullseyeArrow, faArrowRight } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faSitemap, faBullseyeArrow, faArrowRight)

interface PeriodTotals {
    sales: number
    sales_last_year: number
    invoices: number
    invoices_last_year: number
    refunds: number
    refunds_last_year: number
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    overview: {
        currency_code: string | null
        month: PeriodTotals
        year: PeriodTotals
        monthly: { period: string, sales: number, invoices: number, refunds: number }[]
    }
    shopTarget?: {
        shop_name: string
        url: string
        month: { label: string, currency_code: string, sales_so_far: number, target: number | null, expected: number, remaining_days: number }
    } | null
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number) => props.overview.currency_code
    ? locale.currencyFormat(props.overview.currency_code, amount)
    : locale.number(amount)

const compactMoney = (amount: number) => props.overview.currency_code
    ? new Intl.NumberFormat(undefined, { style: "currency", currency: props.overview.currency_code, notation: "compact", maximumFractionDigits: 1 }).format(amount)
    : new Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 }).format(amount)

const change = (current: number, lastYear: number): number | null =>
    lastYear ? Math.round(((current - lastYear) / Math.abs(lastYear)) * 1000) / 10 : null

const targetMoney = (amount: number) => locale.currencyFormat(props.shopTarget!.month.currency_code, Math.round(amount))

const targetProgress = computed(() => {
    const month = props.shopTarget?.month
    return month?.target ? Math.min(100, (month.sales_so_far / month.target) * 100) : 0
})

const expectedOfTarget = computed(() => {
    const month = props.shopTarget?.month
    return month?.target ? Math.round((month.expected / month.target) * 100) : null
})

const periods = computed(() => [
    { label: ctrans("This month"), totals: props.overview.month },
    { label: ctrans("Year to date"), totals: props.overview.year },
])

const monthLabel = (period: string) => {
    const [year, month] = period.split("-").map(Number)
    return new Date(Date.UTC(year, month - 1, 1)).toLocaleDateString(undefined, { month: "short", year: "2-digit", timeZone: "UTC" })
}

const line = (label: string, color: string, data: number[], yAxisID: string) => ({
    label,
    data,
    borderColor: color,
    backgroundColor: color,
    tension: 0,
    borderWidth: 1.5,
    pointRadius: 2,
    yAxisID,
})

const chartData = computed(() => ({
    labels: props.overview.monthly.map(record => monthLabel(record.period)),
    datasets: [
        line(ctrans("Sales"), "#1f845a", props.overview.monthly.map(record => record.sales), "sales"),
        line(ctrans("Invoices"), "#3b82f6", props.overview.monthly.map(record => record.invoices), "count"),
        line(ctrans("Refunds"), "#dc2626", props.overview.monthly.map(record => record.refunds), "count"),
    ],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 12, boxHeight: 12 } },
        tooltip: {
            callbacks: {
                label: (context: { dataset: { label: string, yAxisID: string }, raw: number }) =>
                    `${context.dataset.label}: ${context.dataset.yAxisID === "sales" ? money(context.raw) : locale.number(context.raw)}`,
            },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 0 } },
        sales: { type: "linear", position: "left", beginAtZero: true, ticks: { callback: (value: number) => compactMoney(value) } },
        count: { type: "linear", position: "right", beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0 } },
    },
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 space-y-6">
        <Link v-if="shopTarget" :href="shopTarget.url"
            class="flex items-center gap-4 rounded-lg border border-[--app-accent] bg-[--app-accent-soft] px-4 py-3 transition-colors hover:brightness-95">
            <FontAwesomeIcon icon="fal fa-bullseye-arrow" class="text-xl text-[--app-accent]" fixed-width aria-hidden="true" />
            <div class="min-w-0 flex-1 space-y-1.5">
                <div class="font-semibold text-gray-800">{{ ctrans("See :shop sales target", { shop: shopTarget.shop_name }) }}</div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm tabular-nums">
                    <span class="font-medium text-gray-700">{{ shopTarget.month.label }}</span>
                    <template v-if="shopTarget.month.target">
                        <span class="h-2 w-32 overflow-hidden rounded-full bg-white">
                            <span class="block h-full rounded-full bg-[--app-accent]" :style="{ width: targetProgress + '%' }" />
                        </span>
                        <span class="text-gray-700">{{ targetMoney(shopTarget.month.sales_so_far) }} {{ ctrans("of") }} {{ targetMoney(shopTarget.month.target) }} ({{ Math.round(targetProgress) }}%)</span>
                        <span :class="expectedOfTarget! >= 100 ? 'text-green-600' : 'text-red-600'">
                            {{ ctrans("expected") }} {{ targetMoney(shopTarget.month.expected) }} ({{ expectedOfTarget }}%)
                        </span>
                    </template>
                    <span v-else class="text-gray-500">{{ ctrans("No target yet") }}</span>
                    <span class="text-gray-500">{{ ctrans(":days days left", { days: shopTarget.month.remaining_days }) }}</span>
                </div>
                <div class="text-xs text-gray-500">{{ ctrans("This category is the shop's own sales: everything the shop sells that no other invoice category takes") }}</div>
            </div>
            <FontAwesomeIcon icon="fal fa-arrow-right" class="text-[--app-accent]" fixed-width aria-hidden="true" />
        </Link>

        <div class="grid gap-4 md:grid-cols-2">
            <div v-for="period in periods" :key="period.label" class="rounded-lg border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-500">{{ period.label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ money(period.totals.sales) }}</div>
                <div class="mt-0.5 text-sm tabular-nums text-gray-500">
                    {{ ctrans("Last year") }}: {{ money(period.totals.sales_last_year) }}
                    <span v-if="change(period.totals.sales, period.totals.sales_last_year) !== null"
                        :class="change(period.totals.sales, period.totals.sales_last_year)! >= 0 ? 'text-green-600' : 'text-red-600'">
                        ({{ change(period.totals.sales, period.totals.sales_last_year)! > 0 ? "+" : "" }}{{ change(period.totals.sales, period.totals.sales_last_year) }}%)
                    </span>
                </div>
                <div class="mt-3 flex gap-6 text-sm tabular-nums">
                    <div>
                        <span class="text-gray-500">{{ ctrans("Invoices") }}</span>
                        {{ locale.number(period.totals.invoices) }}
                        <span class="text-gray-400">/ {{ locale.number(period.totals.invoices_last_year) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">{{ ctrans("Refunds") }}</span>
                        {{ locale.number(period.totals.refunds) }}
                        <span class="text-gray-400">/ {{ locale.number(period.totals.refunds_last_year) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4">
            <div class="mb-2 text-sm font-medium text-gray-500">{{ ctrans("Sales, last 13 months") }}</div>
            <div v-if="overview.monthly.length" class="h-72">
                <Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
            </div>
            <div v-else class="py-10 text-center text-sm text-gray-400">{{ ctrans("No sales yet") }}</div>
        </div>
    </div>
</template>
