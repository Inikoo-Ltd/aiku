<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingCart, faUsers, faCoin, faPiggyBank, faPercent, faReceipt, faChartLine, faUserFriends } from "@fal"

library.add(faShoppingCart, faUsers, faCoin, faPiggyBank, faPercent, faReceipt, faChartLine, faUserFriends)

const props = defineProps<{
    data?: {
        currency_code: string
        totals: {
            redemptions: number
            customers: number
            revenue_gross_amount: number
            revenue_net_amount: number
            discounted_amount: number
            avg_discount: number
            avg_savings_per_customer: number
            discount_rate: number
            avg_order_value: number
            return_on_discount: number
            repeat_customers: number
            repeat_rate: number
        }
        trend: { period: string, redemptions: number, discounted_amount: number, revenue_net_amount: number }[]
        first_used_at: string | null
        last_used_at: string | null
        benchmark: {
            offer_type: string
            number_offers: number
            avg_order_value: number
            discount_rate: number
            return_on_discount: number
            avg_discount: number
        }
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const currency = (value: number) => locale.currencyFormat(props.data?.currency_code ?? "", value || 0)

const totals = computed(() => props.data!.totals)
const benchmark = computed(() => props.data!.benchmark)

const verdict = computed(() => {
    if (!totals.value.redemptions) {
        return { label: ctrans("Not used yet"), class: "bg-gray-100 text-gray-600 border-gray-300", description: ctrans("No order has used this offer yet, so there is nothing to judge.") }
    }

    if (!benchmark.value.return_on_discount) {
        return { label: ctrans("No benchmark"), class: "bg-blue-50 text-blue-700 border-blue-300", description: ctrans("No other offer of this type has been used, so there is nothing to compare with.") }
    }

    const ratio = totals.value.return_on_discount / benchmark.value.return_on_discount

    if (ratio >= 1.1) {
        return { label: ctrans("Performing well"), class: "bg-green-50 text-green-700 border-green-300", description: ctrans("Brings in more sales per :currency of discount than the average :type offer.", { currency: currency(1), type: benchmark.value.offer_type }) }
    }

    if (ratio <= 0.9) {
        return { label: ctrans("Under performing"), class: "bg-red-50 text-red-700 border-red-300", description: ctrans("Brings in less sales per :currency of discount than the average :type offer.", { currency: currency(1), type: benchmark.value.offer_type }) }
    }

    return { label: ctrans("Average"), class: "bg-amber-50 text-amber-700 border-amber-300", description: ctrans("Performs in line with other :type offers.", { type: benchmark.value.offer_type }) }
})

const kpis = computed(() => [
    { label: ctrans("Redemptions (orders)"), icon: "fal fa-shopping-cart", color: "#6366f1", value: locale.number(totals.value.redemptions) },
    { label: ctrans("Customers"), icon: "fal fa-users", color: "#3b82f6", value: locale.number(totals.value.customers), subtitle: ctrans(":count came back more than once", { count: locale.number(totals.value.repeat_customers) }) },
    { label: ctrans("Revenue influenced (net)"), icon: "fal fa-coin", color: "#8b5cf6", value: currency(totals.value.revenue_net_amount), subtitle: ctrans("Gross") + ": " + currency(totals.value.revenue_gross_amount) },
    { label: ctrans("Discount given"), icon: "fal fa-piggy-bank", color: "#10b981", value: currency(totals.value.discounted_amount), subtitle: ctrans("Avg per redemption") + ": " + currency(totals.value.avg_discount) },
])

const comparisons = computed(() => [
    {
        label: ctrans("Sales per :currency of discount", { currency: currency(1) }),
        tooltip: ctrans("Net revenue of the orders divided by the discount given. Higher is better."),
        value: totals.value.return_on_discount,
        benchmark: benchmark.value.return_on_discount,
        format: (value: number) => currency(value),
        isHigherBetter: true,
    },
    {
        label: ctrans("Average order value"),
        tooltip: ctrans("Net revenue per order that used the offer. Higher is better."),
        value: totals.value.avg_order_value,
        benchmark: benchmark.value.avg_order_value,
        format: (value: number) => currency(value),
        isHigherBetter: true,
    },
    {
        label: ctrans("Margin impact"),
        tooltip: ctrans("Discount given as percentage of revenue before discount. Lower is better."),
        value: totals.value.discount_rate,
        benchmark: benchmark.value.discount_rate,
        format: (value: number) => value + "%",
        isHigherBetter: false,
    },
    {
        label: ctrans("Avg discount per order"),
        tooltip: ctrans("Discount given per order that used the offer. Lower is better."),
        value: totals.value.avg_discount,
        benchmark: benchmark.value.avg_discount,
        format: (value: number) => currency(value),
        isHigherBetter: false,
    },
])

const comparisonDelta = (value: number, benchmarkValue: number) => benchmarkValue ? ((value - benchmarkValue) / benchmarkValue) * 100 : 0

const isBetter = (comparison: { value: number, benchmark: number, isHigherBetter: boolean }) =>
    comparison.isHigherBetter ? comparison.value >= comparison.benchmark : comparison.value <= comparison.benchmark

const activeMetric = ref<"redemptions" | "discounted_amount">("redemptions")

const trendBars = computed(() => {
    const trend = props.data!.trend
    const metric = activeMetric.value
    const max = Math.max(...trend.map(record => record[metric]), 1)

    return trend.map(record => ({
        label: record.period,
        height: `${(record[metric] / max) * 100}%`,
        tooltip: metric === "redemptions"
            ? `${record.period} — ${locale.number(record.redemptions)} ${ctrans("redemptions")}`
            : `${record.period} — ${currency(record.discounted_amount)}`,
    }))
})
</script>

<template>
    <div v-if="data" class="flex flex-col gap-y-4 p-4">
        <!-- Section: verdict -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-md border border-gray-200 bg-white p-4">
            <div class="flex items-center gap-3">
                <span class="rounded-full border px-3 py-0.5 text-sm font-semibold" :class="verdict.class">{{ verdict.label }}</span>
                <span class="text-sm text-gray-600">{{ verdict.description }}</span>
            </div>
            <div class="flex gap-6 text-xs text-gray-500">
                <div>
                    <div class="uppercase tracking-wide text-gray-400">{{ ctrans("First used") }}</div>
                    <div class="text-sm font-medium text-gray-700">{{ data.first_used_at ? useFormatTime(data.first_used_at) : "-" }}</div>
                </div>
                <div>
                    <div class="uppercase tracking-wide text-gray-400">{{ ctrans("Last used") }}</div>
                    <div class="text-sm font-medium text-gray-700">{{ data.last_used_at ? useFormatTime(data.last_used_at) : "-" }}</div>
                </div>
            </div>
        </div>

        <!-- Section: KPI -->
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="(kpi, idxKpi) in kpis" :key="idxKpi" class="flex items-start gap-4 rounded-md border border-gray-200 bg-white p-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                    :style="{ backgroundColor: `color-mix(in srgb, ${kpi.color} 12%, white)`, color: kpi.color }">
                    <FontAwesomeIcon :icon="kpi.icon" fixed-width aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <dt class="truncate text-xs font-medium uppercase tracking-wide text-gray-500">{{ kpi.label }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tracking-tight tabular-nums text-gray-900">{{ kpi.value }}</dd>
                    <div v-if="kpi.subtitle" class="mt-1 truncate text-xs text-gray-500">{{ kpi.subtitle }}</div>
                </div>
            </div>
        </dl>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Section: compared with same type -->
            <div class="rounded-md border border-gray-200 bg-white p-4">
                <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Compared with other offers") }}</h3>
                <div class="text-xs text-gray-500">
                    {{ ctrans("Average of :count used :type offers in this shop", { count: locale.number(benchmark.number_offers), type: benchmark.offer_type }) }}
                </div>

                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    <li v-for="(comparison, idxComparison) in comparisons" :key="idxComparison" class="py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span v-tooltip="comparison.tooltip" class="cursor-help text-gray-600 underline decoration-dotted decoration-gray-300 underline-offset-4">{{ comparison.label }}</span>
                            <span class="font-semibold tabular-nums text-gray-900">{{ comparison.format(comparison.value) }}</span>
                        </div>
                        <div class="mt-0.5 flex items-center justify-between gap-2 text-xs">
                            <span class="text-gray-400">{{ ctrans("Average") }}: {{ comparison.format(comparison.benchmark) }}</span>
                            <span v-if="totals.redemptions && comparison.benchmark" class="font-medium tabular-nums"
                                :class="isBetter(comparison) ? 'text-green-600' : 'text-red-500'">
                                {{ comparisonDelta(comparison.value, comparison.benchmark) > 0 ? "+" : "" }}{{ comparisonDelta(comparison.value, comparison.benchmark).toFixed(1) }}%
                            </span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Section: trend -->
            <div class="rounded-md border border-gray-200 bg-white p-4 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Trend") }}</h3>
                    <div class="flex rounded-md bg-gray-100 p-1">
                        <button type="button" @click="activeMetric = 'redemptions'"
                            class="rounded px-3 py-1 text-xs font-medium transition"
                            :class="activeMetric === 'redemptions' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'">
                            {{ ctrans("Redemptions") }}
                        </button>
                        <button type="button" @click="activeMetric = 'discounted_amount'"
                            class="rounded px-3 py-1 text-xs font-medium transition"
                            :class="activeMetric === 'discounted_amount' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'">
                            {{ ctrans("Discount given") }}
                        </button>
                    </div>
                </div>

                <div v-if="data.trend.length" class="mt-5">
                    <div class="relative h-44">
                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                            <div v-for="gridLine in 4" :key="gridLine" class="border-t border-dashed border-gray-100" />
                        </div>
                        <div class="relative flex h-full items-end gap-1 border-b border-gray-200">
                            <div v-for="(bar, idxBar) in trendBars" :key="idxBar" v-tooltip.top="bar.tooltip"
                                class="group flex h-full min-w-0 flex-1 cursor-pointer items-end rounded-t hover:bg-gray-50">
                                <div class="w-full rounded-t opacity-80 transition group-hover:opacity-100"
                                    :class="activeMetric === 'redemptions' ? 'bg-[--app-accent]' : 'bg-emerald-500'"
                                    :style="{ height: bar.height }" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 flex justify-between text-xs tabular-nums text-gray-400">
                        <span>{{ trendBars[0].label }}</span>
                        <span>{{ trendBars[trendBars.length - 1].label }}</span>
                    </div>
                </div>
                <div v-else class="mt-6 flex h-44 items-center justify-center text-sm text-gray-400">
                    {{ ctrans("No redemptions yet") }}
                </div>
            </div>
        </div>
    </div>

    <div v-else class="flex animate-pulse flex-col gap-y-4 p-4">
        <div class="h-16 rounded-md bg-gray-200" />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="skeletonCard in 4" :key="skeletonCard" class="flex items-start gap-4 rounded-md border border-gray-200 p-4">
                <div class="h-10 w-10 shrink-0 rounded-md bg-gray-200" />
                <div class="flex-1 space-y-2">
                    <div class="h-3 w-1/2 rounded bg-gray-200" />
                    <div class="h-6 w-2/3 rounded bg-gray-200" />
                    <div class="h-3 w-3/4 rounded bg-gray-200" />
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="h-64 rounded-md bg-gray-200" />
            <div class="h-64 rounded-md bg-gray-200 lg:col-span-2" />
        </div>
    </div>
</template>
