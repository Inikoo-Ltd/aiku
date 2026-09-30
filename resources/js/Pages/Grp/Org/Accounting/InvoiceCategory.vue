<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSitemap } from "@fal"
import { computed, inject } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faSitemap)

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
        monthly: { period: string, sales: number, invoices: number }[]
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number) => props.overview.currency_code
    ? locale.currencyFormat(props.overview.currency_code, amount)
    : locale.number(amount)

const change = (current: number, lastYear: number): number | null =>
    lastYear ? Math.round(((current - lastYear) / Math.abs(lastYear)) * 1000) / 10 : null

const periods = computed(() => [
    { label: ctrans("This month"), totals: props.overview.month },
    { label: ctrans("Year to date"), totals: props.overview.year },
])

const monthLabel = (period: string) => {
    const [year, month] = period.split("-").map(Number)
    return new Date(Date.UTC(year, month - 1, 1)).toLocaleDateString(undefined, { month: "short", year: "2-digit", timeZone: "UTC" })
}

const chartData = computed(() => ({
    labels: props.overview.monthly.map(record => monthLabel(record.period)),
    datasets: [{
        label: ctrans("Sales"),
        data: props.overview.monthly.map(record => record.sales),
        backgroundColor: "#6366f1",
        borderRadius: 4,
    }],
}))

const chartOptions = {
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (context: { raw: number }) => String(money(context.raw)) } },
    },
    scales: {
        y: { ticks: { callback: (value: number) => money(value) } },
    },
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 space-y-6">
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
                <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full" />
            </div>
            <div v-else class="py-10 text-center text-sm text-gray-400">{{ ctrans("No sales yet") }}</div>
        </div>
    </div>
</template>
