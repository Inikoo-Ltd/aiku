<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Fri, 25 Sep 2026 Malaga, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import GoodsViewToggle from "@/Components/Goods/GoodsViewToggle.vue"
import AnalysisLineChart from "@/Components/Goods/AnalysisLineChart.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"

type Granularity = "month" | "quarter" | "year"

interface OrganisationMeta {
    code: string
    name: string
    slug: string
    warehouse_slug: string | null
}

interface Filters {
    organisation: string | null
    family: string | null
    search: string | null
    granularity: Granularity
}

interface Product {
    id: number
    code: string
    name: string | null
}

interface Summary {
    current: number
    previous: number
    last_year: number
    change_vs_previous: number | null
    change_vs_last_year: number | null
}

interface SeriesPoint {
    period: string
    by_organisation: Record<string, number>
}

interface GroupRow {
    key: string
    current: number
    previous: number
    last_year: number
    change_vs_previous: number | null
    change_vs_last_year: number | null
}

interface ProductRow {
    id: number
    code: string
    name: string | null
    current: number
    previous: number
    change: number
}

interface StockTrendPoint {
    period: string
    by_organisation: Record<string, { stock_value: number; percentage_out_of_stock: number | null }>
}

interface ExceptionItem {
    stock_id: number
    code: string
    name: string | null
    organisation: string
    available: number
    stock_value: number | null
    days_of_cover: number | null
    lead_time_days: number | null
    understock_days: number | null
    overstock_days: number | null
}

interface UrgentItem {
    stock_id: number
    code: string
    name: string | null
    organisation: string
    stock_value?: number | null
    inbound?: number
    next_expected_at?: string | null
    days_overdue?: number
}

interface PromotionCandidate {
    stock_id: number
    code: string
    name: string | null
    reasons: string[]
    stock_value: number
}

interface StatusChange {
    code: string
    name: string | null
    organisation: string
    from: string | null
    to: string | null
    reason: string | null
    who: string | null
    at: string
}

const props = defineProps<{
    title: string
    pageHead: object
    built_at: string
    granularity: Granularity
    granularities: Granularity[]
    organisations: OrganisationMeta[]
    families: string[]
    filters: Filters
    product: Product | null
    summary: Summary
    series: SeriesPoint[]
    families_table: GroupRow[]
    organisations_table: GroupRow[]
    products_table: { risers: ProductRow[]; fallers: ProductRow[] }
    stock_trend: StockTrendPoint[]
    exceptions: { understocked: ExceptionItem[]; overstocked: ExceptionItem[]; offline: ExceptionItem[] }
    urgent_actions: {
        out_of_stock_no_po: UrgentItem[]
        understocked_no_po: UrgentItem[]
        overdue_inbound: UrgentItem[]
        offline_with_stock: UrgentItem[]
        counts: Record<string, number>
    }
    promotion_candidates: PromotionCandidate[]
    status_history: StatusChange[]
    inbound: { open_quantity: number; open_value_approx: number; overdue: UrgentItem[]; overdue_count: number }
    currency_code: string
}>()

const granularityLabels: Record<Granularity, string> = {
    month: ctrans("Month"),
    quarter: ctrans("Quarter"),
    year: ctrans("Year"),
}

const reasonLabels: Record<string, string> = {
    overstocked: ctrans("Overstocked"),
    dead_stock: ctrans("Dead stock"),
    sell_through: ctrans("Sell through"),
}

const palette = ["#4f46e5", "#0f766e", "#b45309", "#be185d", "#334155", "#7c3aed"]

const filterState = reactive({ ...props.filters })

watch(
    () => props.filters,
    (newFilters) => {
        Object.assign(filterState, newFilters)
    },
    { deep: true }
)

const loading = ref(false)
let stopStart: (() => void) | undefined
let stopFinish: (() => void) | undefined

onMounted(() => {
    stopStart = router.on("start", () => {
        loading.value = true
    })
    stopFinish = router.on("finish", () => {
        loading.value = false
    })
})

onUnmounted(() => {
    stopStart?.()
    stopFinish?.()
})

function load(changes: Partial<Filters> = {}): void {
    Object.assign(filterState, changes)
    const params: Record<string, string> = {}
    for (const [key, value] of Object.entries(filterState)) {
        if (value !== null && value !== "" && !(key === "granularity" && value === "month")) {
            params[key] = value as string
        }
    }

    router.get(route("grp.goods.analysis"), params, { preserveState: true, preserveScroll: true })
}

function reset(): void {
    load({ organisation: null, family: null, search: null, granularity: "month" })
}

const hasFilters = computed(() => ["organisation", "family", "search"].some((key) => props.filters[key as keyof Filters]))

const money = (amount: number | null, compact = false): string =>
    amount === null
        ? "-"
        : new Intl.NumberFormat("en-GB", {
              style: "currency",
              currency: props.currency_code,
              notation: compact ? "compact" : "standard",
              maximumFractionDigits: compact ? 2 : 0,
          }).format(amount)

const quantity = (value: number): string => new Intl.NumberFormat("en-GB", { maximumFractionDigits: 1 }).format(value)

const trend = (value: number | null): string => (value === null ? ctrans("n/a") : `${value >= 0 ? "↑" : "↓"}${Math.abs(value)}%`)

const trendClass = (value: number | null): string => (value === null ? "text-gray-400" : value >= 0 ? "text-green-600" : "text-red-600")

const builtAt = computed(() => new Date(props.built_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }))

function formatPeriod(period: string): string {
    const date = new Date(period)
    if (props.granularity === "year") {
        return String(date.getFullYear())
    }
    if (props.granularity === "quarter") {
        const quarter = Math.floor(date.getMonth() / 3) + 1
        return `Q${quarter} ${String(date.getFullYear()).slice(2)}`
    }
    return date.toLocaleDateString(undefined, { month: "short", year: "2-digit" })
}

const salesChart = computed(() => ({
    labels: props.series.map((point) => formatPeriod(point.period)),
    datasets: props.organisations.map((organisation, index) => ({
        label: organisation.code,
        data: props.series.map((point) => point.by_organisation[organisation.code] ?? null),
        color: palette[index % palette.length],
    })),
}))

const stockTrendChart = computed(() => ({
    labels: props.stock_trend.map((point) => formatPeriod(point.period)),
    datasets: props.organisations.map((organisation, index) => ({
        label: organisation.code,
        data: props.stock_trend.map((point) => point.by_organisation[organisation.code]?.stock_value ?? null),
        color: palette[index % palette.length],
    })),
}))

const dashboardRoute = (params: Record<string, string | undefined>): string => {
    const query: Record<string, string> = {}
    for (const [key, value] of Object.entries(params)) {
        if (value) {
            query[key] = value
        }
    }
    return route("grp.goods.dashboard", query)
}

const urgentPanelOpen = ref(false)
const totalUrgent = computed(() => Object.values(props.urgent_actions.counts).reduce((sum, count) => sum + count, 0))

function handleUrgentToggle(event: Event): void {
    urgentPanelOpen.value = (event.target as HTMLDetailsElement).open
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <GoodsViewToggle active="analysis" :organisation="filters.organisation" :family="filters.family" :search="filters.search" />

    <div class="mx-4 mt-1 text-sm text-gray-500">
        {{ product ? ctrans("Sales history, stock and demand for :code", { code: product.code }) : ctrans("Sales history, stock trends, demand, exceptions and promotional opportunities") }}
    </div>
    <div class="mx-4 mt-1 text-xs text-gray-500">{{ ctrans("Figures as of :time, refreshed periodically.", { time: builtAt }) }}</div>

    <div class="mx-4 my-3 flex flex-wrap items-center gap-2">
        <input
            v-model="filterState.family"
            list="product-analysis-families"
            class="w-36 rounded-md border-gray-300 text-sm"
            :placeholder="ctrans('Family')"
            :aria-label="ctrans('Family')"
            :disabled="loading"
            @change="load()"
        />
        <datalist id="product-analysis-families">
            <option v-for="family in families" :key="family" :value="family" />
        </datalist>
        <select v-model="filterState.organisation" class="rounded-md border-gray-300 text-sm" :aria-label="ctrans('Organisation')" :disabled="loading" @change="load()">
            <option :value="null">{{ ctrans("All organisations") }}</option>
            <option v-for="organisation in organisations" :key="organisation.code" :value="organisation.code">{{ organisation.name }}</option>
        </select>
        <input
            v-model="filterState.search"
            type="search"
            class="min-w-64 flex-1 rounded-md border-gray-300 text-sm"
            :placeholder="ctrans('Search code, description or family — one match opens its own analysis')"
            :aria-label="ctrans('Search code, description or family')"
            :disabled="loading"
            @keyup.enter="load()"
            @search="load()"
        />
        <select v-model="filterState.granularity" class="rounded-md border-gray-300 text-xs" :aria-label="ctrans('Granularity')" :disabled="loading" @change="load()">
            <option v-for="g in granularities" :key="g" :value="g">{{ granularityLabels[g] }}</option>
        </select>
        <button v-if="hasFilters" type="button" class="text-sm text-indigo-600 hover:underline" @click="reset">{{ ctrans("Reset") }}</button>
    </div>

    <div class="mx-4 mb-3 rounded-lg border border-gray-200" :class="{ 'opacity-60': loading }">
        <details :open="urgentPanelOpen" @toggle="handleUrgentToggle">
            <summary class="flex cursor-pointer items-center justify-between px-3 py-2 text-sm font-semibold text-gray-900">
                <span>{{ ctrans("Urgent actions") }} <span class="font-normal text-gray-500">{{ quantity(totalUrgent) }}</span></span>
                <span class="text-xs font-normal text-gray-500">{{ urgentPanelOpen ? ctrans("Hide") : ctrans("Show") }}</span>
            </summary>
            <div class="grid grid-cols-1 gap-3 border-t border-gray-100 p-3 text-xs md:grid-cols-4">
                <div>
                    <div class="mb-1 font-semibold text-red-600">{{ ctrans("Out of stock, no PO") }} <span class="text-gray-400">{{ urgent_actions.counts.oos_no_po }}</span></div>
                    <Link v-for="item in urgent_actions.out_of_stock_no_po" :key="`oos-${item.stock_id}-${item.organisation}`" :href="dashboardRoute({ condition: 'oos_no_po', organisation: item.organisation, search: item.code })" class="block truncate py-0.5 text-gray-700 hover:underline">
                        {{ item.organisation }} · {{ item.code }}
                    </Link>
                </div>
                <div>
                    <div class="mb-1 font-semibold text-amber-600">{{ ctrans("Understocked, no PO") }} <span class="text-gray-400">{{ urgent_actions.counts.low_no_po }}</span></div>
                    <Link v-for="item in urgent_actions.understocked_no_po" :key="`low-${item.stock_id}-${item.organisation}`" :href="dashboardRoute({ condition: 'low_no_po', organisation: item.organisation, search: item.code })" class="block truncate py-0.5 text-gray-700 hover:underline">
                        {{ item.organisation }} · {{ item.code }}
                    </Link>
                </div>
                <div>
                    <div class="mb-1 font-semibold text-orange-600">{{ ctrans("Overdue inbound") }} <span class="text-gray-400">{{ urgent_actions.counts.overdue }}</span></div>
                    <Link v-for="item in urgent_actions.overdue_inbound" :key="`overdue-${item.stock_id}-${item.organisation}`" :href="dashboardRoute({ organisation: item.organisation, search: item.code })" class="block truncate py-0.5 text-gray-700 hover:underline">
                        {{ item.organisation }} · {{ item.code }} · {{ item.days_overdue }}{{ ctrans("d late") }}
                    </Link>
                </div>
                <div>
                    <div class="mb-1 font-semibold text-pink-600">{{ ctrans("Offline with stock") }} <span class="text-gray-400">{{ urgent_actions.counts.off }}</span></div>
                    <Link v-for="item in urgent_actions.offline_with_stock" :key="`off-${item.stock_id}-${item.organisation}`" :href="dashboardRoute({ condition: 'off', organisation: item.organisation, search: item.code })" class="block truncate py-0.5 text-gray-700 hover:underline">
                        {{ item.organisation }} · {{ item.code }}
                    </Link>
                </div>
            </div>
        </details>
    </div>

    <div class="mx-4 mb-3 grid grid-cols-1 gap-3 md:grid-cols-3" :class="{ 'opacity-60': loading }">
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="text-xs text-gray-500">{{ ctrans("Current :period", { period: granularityLabels[granularity].toLowerCase() }) }}</div>
            <div class="text-2xl font-semibold tabular-nums text-gray-900">{{ money(summary.current, true) }}</div>
            <div class="text-xs" :class="trendClass(summary.change_vs_previous)">{{ ctrans("vs previous") }} {{ trend(summary.change_vs_previous) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="text-xs text-gray-500">{{ ctrans("Previous :period", { period: granularityLabels[granularity].toLowerCase() }) }}</div>
            <div class="text-2xl font-semibold tabular-nums text-gray-900">{{ money(summary.previous, true) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="text-xs text-gray-500">{{ ctrans("Same period last year") }}</div>
            <div class="text-2xl font-semibold tabular-nums text-gray-900">{{ money(summary.last_year, true) }}</div>
            <div class="text-xs" :class="trendClass(summary.change_vs_last_year)">{{ ctrans("vs last year") }} {{ trend(summary.change_vs_last_year) }}</div>
        </div>
    </div>

    <div class="mx-4 mb-3 rounded-lg border border-gray-200 p-3" :class="{ 'opacity-60': loading }">
        <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Sales over time, by organisation") }}</div>
        <AnalysisLineChart v-if="series.length" :labels="salesChart.labels" :datasets="salesChart.datasets" />
        <div v-else class="py-6 text-center text-xs text-gray-500">{{ ctrans("No sales in this window.") }}</div>
    </div>

    <div class="mx-4 mb-3 grid grid-cols-1 gap-3 lg:grid-cols-3" :class="{ 'opacity-60': loading }">
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Top families") }}</div>
            <table class="w-full text-xs">
                <thead class="text-left uppercase text-gray-600">
                    <tr><th class="py-1">{{ ctrans("Family") }}</th><th class="py-1 text-right">{{ ctrans("Current") }}</th><th class="py-1 text-right">{{ ctrans("Change") }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <tr v-for="row in families_table" :key="row.key">
                        <td class="max-w-[120px] truncate py-1">{{ row.key }}</td>
                        <td class="py-1 text-right tabular-nums">{{ money(row.current, true) }}</td>
                        <td class="py-1 text-right tabular-nums" :class="trendClass(row.change_vs_previous)">{{ trend(row.change_vs_previous) }}</td>
                    </tr>
                    <tr v-if="!families_table.length"><td colspan="3" class="py-4 text-center text-gray-400">{{ ctrans("No data.") }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("By organisation") }}</div>
            <table class="w-full text-xs">
                <thead class="text-left uppercase text-gray-600">
                    <tr><th class="py-1">{{ ctrans("Organisation") }}</th><th class="py-1 text-right">{{ ctrans("Current") }}</th><th class="py-1 text-right">{{ ctrans("Change") }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <tr v-for="row in organisations_table" :key="row.key">
                        <td class="py-1">{{ row.key }}</td>
                        <td class="py-1 text-right tabular-nums">{{ money(row.current, true) }}</td>
                        <td class="py-1 text-right tabular-nums" :class="trendClass(row.change_vs_previous)">{{ trend(row.change_vs_previous) }}</td>
                    </tr>
                    <tr v-if="!organisations_table.length"><td colspan="3" class="py-4 text-center text-gray-400">{{ ctrans("No data.") }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Risers / fallers") }}</div>
            <table class="w-full text-xs">
                <thead class="text-left uppercase text-gray-600">
                    <tr><th class="py-1">{{ ctrans("Product") }}</th><th class="py-1 text-right">{{ ctrans("Change") }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <tr v-for="row in products_table.risers.slice(0, 5)" :key="`riser-${row.id}`">
                        <td class="max-w-[140px] truncate py-1">{{ row.code }}</td>
                        <td class="py-1 text-right tabular-nums text-green-600">{{ trend(row.change) }}</td>
                    </tr>
                    <tr v-for="row in products_table.fallers.slice(0, 5)" :key="`faller-${row.id}`">
                        <td class="max-w-[140px] truncate py-1">{{ row.code }}</td>
                        <td class="py-1 text-right tabular-nums text-red-600">{{ trend(row.change) }}</td>
                    </tr>
                    <tr v-if="!products_table.risers.length && !products_table.fallers.length"><td colspan="2" class="py-4 text-center text-gray-400">{{ ctrans("No data.") }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 mb-3 rounded-lg border border-gray-200 p-3" :class="{ 'opacity-60': loading }">
        <div class="mb-1 text-sm font-semibold text-gray-900">{{ ctrans("Stock value & out-of-stock trend, by organisation") }}</div>
        <div class="mb-2 text-xs text-gray-500">{{ ctrans("On-hand unit quantity isn't tracked at group level; shown here is stock value at landed cost from the daily organisation snapshot.") }}</div>
        <AnalysisLineChart v-if="stock_trend.length" :labels="stockTrendChart.labels" :datasets="stockTrendChart.datasets" />
        <div v-else class="py-6 text-center text-xs text-gray-500">{{ ctrans("No stock history available for this window.") }}</div>
    </div>

    <div class="mx-4 mb-3 grid grid-cols-1 gap-3 lg:grid-cols-2" :class="{ 'opacity-60': loading }">
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 flex items-center justify-between text-sm font-semibold text-gray-900">
                <span>{{ ctrans("Inbound") }}</span>
                <span class="text-xs font-normal text-gray-500">{{ ctrans(":quantity units, ≈:value", { quantity: quantity(inbound.open_quantity), value: money(inbound.open_value_approx, true) }) }}</span>
            </div>
            <table class="w-full text-xs">
                <thead class="text-left uppercase text-gray-600">
                    <tr><th class="py-1">{{ ctrans("Product") }}</th><th class="py-1">{{ ctrans("Org") }}</th><th class="py-1 text-right">{{ ctrans("Qty") }}</th><th class="py-1 text-right">{{ ctrans("Days late") }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <tr v-for="item in inbound.overdue" :key="`inbound-${item.stock_id}-${item.organisation}`">
                        <td class="max-w-[120px] truncate py-1"><Link :href="dashboardRoute({ organisation: item.organisation, search: item.code })" class="hover:underline">{{ item.code }}</Link></td>
                        <td class="py-1">{{ item.organisation }}</td>
                        <td class="py-1 text-right tabular-nums">{{ quantity(item.inbound ?? 0) }}</td>
                        <td class="py-1 text-right tabular-nums text-orange-600">{{ item.days_overdue }}</td>
                    </tr>
                    <tr v-if="!inbound.overdue.length"><td colspan="4" class="py-4 text-center text-gray-400">{{ ctrans("Nothing overdue.") }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Promotion candidates") }} <span class="font-normal text-gray-500">{{ ctrans("read-only") }}</span></div>
            <table class="w-full text-xs">
                <thead class="text-left uppercase text-gray-600">
                    <tr><th class="py-1">{{ ctrans("Product") }}</th><th class="py-1">{{ ctrans("Why") }}</th><th class="py-1 text-right">{{ ctrans("Stock value") }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    <tr v-for="candidate in promotion_candidates.slice(0, 10)" :key="candidate.stock_id">
                        <td class="max-w-[120px] truncate py-1">{{ candidate.code }}</td>
                        <td class="py-1">
                            <span v-for="reason in candidate.reasons" :key="reason" class="mr-1 rounded bg-gray-100 px-1 py-0.5 text-gray-600">{{ reasonLabels[reason] ?? reason }}</span>
                        </td>
                        <td class="py-1 text-right tabular-nums">{{ money(candidate.stock_value, true) }}</td>
                    </tr>
                    <tr v-if="!promotion_candidates.length"><td colspan="3" class="py-4 text-center text-gray-400">{{ ctrans("No candidates.") }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mx-4 mb-3 grid grid-cols-1 gap-3 lg:grid-cols-3" :class="{ 'opacity-60': loading }">
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Understocked") }} <span class="font-normal text-gray-500">{{ exceptions.understocked.length }}</span></div>
            <div v-for="item in exceptions.understocked.slice(0, 10)" :key="`u-${item.stock_id}-${item.organisation}`" class="border-b border-gray-100 py-1 text-xs text-gray-700 last:border-0">
                <Link :href="dashboardRoute({ condition: 'low', organisation: item.organisation, search: item.code })" class="font-medium hover:underline">{{ item.organisation }} · {{ item.code }}</Link>
                <div class="text-gray-500">
                    {{ ctrans("available :available · cover :cover d · lead time :lead d · threshold :threshold d", {
                        available: quantity(item.available),
                        cover: item.days_of_cover ?? "-",
                        lead: item.lead_time_days ?? "-",
                        threshold: item.understock_days ?? "-",
                    }) }}
                </div>
            </div>
            <div v-if="!exceptions.understocked.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("None.") }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Overstocked") }} <span class="font-normal text-gray-500">{{ exceptions.overstocked.length }}</span></div>
            <div v-for="item in exceptions.overstocked.slice(0, 10)" :key="`o-${item.stock_id}-${item.organisation}`" class="border-b border-gray-100 py-1 text-xs text-gray-700 last:border-0">
                <Link :href="dashboardRoute({ condition: 'over', organisation: item.organisation, search: item.code })" class="font-medium hover:underline">{{ item.organisation }} · {{ item.code }}</Link>
                <div class="text-gray-500">
                    {{ ctrans("available :available · cover :cover d · threshold :threshold d · value :value", {
                        available: quantity(item.available),
                        cover: item.days_of_cover ?? "-",
                        threshold: item.overstock_days ?? "-",
                        value: money(item.stock_value, true),
                    }) }}
                </div>
            </div>
            <div v-if="!exceptions.overstocked.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("None.") }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 p-3">
            <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Offline with stock") }} <span class="font-normal text-gray-500">{{ exceptions.offline.length }}</span></div>
            <div v-for="item in exceptions.offline.slice(0, 10)" :key="`f-${item.stock_id}-${item.organisation}`" class="border-b border-gray-100 py-1 text-xs text-gray-700 last:border-0">
                <Link :href="dashboardRoute({ condition: 'off', organisation: item.organisation, search: item.code })" class="font-medium hover:underline">{{ item.organisation }} · {{ item.code }}</Link>
                <div class="text-gray-500">{{ ctrans("available :available · stock value :value, no shop sells it", { available: quantity(item.available), value: money(item.stock_value, true) }) }}</div>
            </div>
            <div v-if="!exceptions.offline.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("None.") }}</div>
        </div>
    </div>

    <div class="mx-4 mb-6 rounded-lg border border-gray-200 p-3" :class="{ 'opacity-60': loading }">
        <div class="mb-2 text-sm font-semibold text-gray-900">{{ ctrans("Status change history") }} <span class="font-normal text-gray-500">{{ ctrans("last :count", { count: status_history.length }) }}</span></div>
        <table class="w-full text-xs">
            <thead class="text-left uppercase text-gray-600">
                <tr>
                    <th class="py-1">{{ ctrans("Product") }}</th>
                    <th class="py-1">{{ ctrans("Org") }}</th>
                    <th class="py-1">{{ ctrans("Change") }}</th>
                    <th class="py-1">{{ ctrans("Reason") }}</th>
                    <th class="py-1">{{ ctrans("Who") }}</th>
                    <th class="py-1 text-right">{{ ctrans("When") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                <tr v-for="(change, index) in status_history" :key="index">
                    <td class="max-w-[140px] truncate py-1">{{ change.code }}</td>
                    <td class="py-1">{{ change.organisation }}</td>
                    <td class="py-1">{{ change.from ?? "-" }} → {{ change.to ?? "-" }}</td>
                    <td class="max-w-[160px] truncate py-1 text-gray-500">{{ change.reason ?? "-" }}</td>
                    <td class="py-1 text-gray-500">{{ change.who ?? "-" }}</td>
                    <td class="py-1 text-right tabular-nums text-gray-500">{{ new Date(change.at).toLocaleDateString() }}</td>
                </tr>
                <tr v-if="!status_history.length"><td colspan="6" class="py-4 text-center text-gray-400">{{ ctrans("No status changes for these filters.") }}</td></tr>
            </tbody>
        </table>
    </div>
</template>
