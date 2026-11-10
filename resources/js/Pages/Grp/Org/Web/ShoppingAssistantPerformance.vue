<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { useMediaQuery } from "@vueuse/core"
import Chart from "primevue/chart"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import GoogleAdsMetric from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsMetric.vue"
import TableShoppingAssistantConversations from "@/Components/Tables/Grp/Org/Web/TableShoppingAssistantConversations.vue"
import { capitalize } from "@/Composables/capitalize"
import { useCurrentTab } from "@/Composables/tab-change"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Navigation } from "@/types/Tabs"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faComments, faShoppingCart, faMoneyBillWave, faPercentage, faCoins, faUndo, faTimes, faRobot } from "@fal"

library.add(faComments, faShoppingCart, faMoneyBillWave, faPercentage, faCoins, faUndo, faTimes, faRobot)

type Metric = { value: number; previous: number | null }
type Option = { value: string | number; label: string }
type RouteRef = { name: string; parameters: Record<string, any> }

type Overview = {
    mature_until: string | null
    kpis: Record<string, Metric | null>
    cost_currency?: string
    funnel?: { key: string; value: number; previous: number | null }[]
    trend?: { granularity: "day" | "week"; points: { date: string; conversations: number; shown: number; clicked: number; added: number; ordered: number; complete: boolean }[] }
    drop_off?: { questions: string; conversations: number }[]
    families?: { id: number; code: string; name: string; shown: number; clicked: number; added: number; ordered: number; revenue: number }[]
    search?: {
        ignored_filters?: string[]
        searches?: Metric
        zero_results?: Metric
        zero_result_rate?: Metric
        suggestion_clicks?: Metric
        zero_rescued?: Metric
        by_kind?: { kind: string; clicks: number }[]
        top_queries?: { query: string; searches: number; clicks: number }[]
        top_zero_queries?: { query: string; searches: number; rescued: number }[]
    }
}

type FilterKey = "website" | "language" | "entry_point" | "shop_type" | "country"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string; navigation: Navigation }
    filters: {
        from: string
        to: string
        previous_from?: string
        previous_to?: string
        website: number | null
        language: string | null
        entry_point: string | null
        shop_type: string | null
        country: string | null
        outcome: string | null
        website_locked: boolean
        options?: {
            websites?: Option[]
            languages?: Option[]
            entry_points?: Option[]
            shop_types?: Option[]
            countries?: Option[]
        }
    }
    currency: string
    cost_currency?: string
    attribution_days: number
    budgets: { website: string; monthly: number; spent: number; paused: boolean; resumes_on: string }[]
    routes: { page: RouteRef; conversation: RouteRef }
    overview?: Overview
    conversations?: object
}>()

const locale = useLocaleStore()
const currentTab = useCurrentTab(props.tabs.current ?? "overview")
const isLoading = ref(false)

const filterKeys: FilterKey[] = ["website", "language", "entry_point", "shop_type", "country"]

const page = usePage()
const urlQuery = computed(() => new URLSearchParams(page.url.split("?")[1] ?? ""))

const filterQuery = (overrides: Record<string, any> = {}) => {
    const query: Record<string, any> = {}
    if (urlQuery.value.has("from")) query.from = props.filters.from
    if (urlQuery.value.has("to")) query.to = props.filters.to
    filterKeys.forEach((key) => {
        if (key === "website" && props.filters.website_locked) return
        if (props.filters[key] !== null && props.filters[key] !== undefined) query[key] = props.filters[key]
    })
    Object.entries(overrides).forEach(([key, value]) => {
        if (value === null || value === undefined || value === "") delete query[key]
        else query[key] = value
    })
    return query
}

const pageUrl = (query: Record<string, any>) => route(props.routes.page.name, { ...props.routes.page.parameters, ...query })

const visit = (query: Record<string, any>, tab: string) =>
    router.get(pageUrl({ ...query, tab }), {}, {
        only: ["tabs", "filters", "budgets", tab],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isLoading.value = true),
        onFinish: () => (isLoading.value = false),
    })

const keepOutcome = () => (currentTab.value === "conversations" && props.filters.outcome ? { outcome: props.filters.outcome } : {})

const setFilter = (key: string, value: string | number | null) => visit(filterQuery({ ...keepOutcome(), [key]: value }), currentTab.value)

const handleTabUpdate = (tab: string) => {
    if (tab === currentTab.value) return
    visit(filterQuery(), tab)
}

const conversationsUrl = (outcome?: string) => pageUrl({ ...filterQuery(), tab: "conversations", ...(outcome ? { outcome } : {}) })

const hasActiveFilters = computed(
    () => urlQuery.value.has("from") || urlQuery.value.has("to") || filterKeys.some((key) => !(key === "website" && props.filters.website_locked) && props.filters[key] !== null && props.filters[key] !== undefined)
)
const resetFilters = () => visit({}, currentTab.value)

const fromIsoDate = (iso: string | null | undefined): Date | null => {
    if (!iso) return null
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateModel = (key: "from" | "to") =>
    computed<Date | null>({
        get: () => fromIsoDate(props.filters[key]),
        set: (value) => {
            if (!value) return
            setFilter(key, toLocalIsoDate(value))
        },
    })
const rangeFrom = dateModel("from")
const rangeTo = dateModel("to")

const isNarrowScreen = useMediaQuery("(max-width: 639px)")
const dateFormat = computed(() => (isNarrowScreen.value ? "dd/mm/yy" : "d M yy"))
const datePickerPt = computed(() => ({ pcInputText: { root: { class: isNarrowScreen.value ? "!w-28 !py-1 !text-xs" : "!w-32 !py-1 !text-xs" } } }))
const filterSelectPt = { label: { class: "!py-1 !text-xs" } }
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const selectFilters = computed(() =>
    [
        { key: "website" as const, placeholder: ctrans("All websites"), options: props.filters.options?.websites ?? [], hidden: props.filters.website_locked },
        { key: "language" as const, placeholder: ctrans("All languages"), options: props.filters.options?.languages ?? [] },
        { key: "entry_point" as const, placeholder: ctrans("All entry points"), options: props.filters.options?.entry_points ?? [] },
        { key: "shop_type" as const, placeholder: ctrans("All shop types"), options: props.filters.options?.shop_types ?? [] },
        { key: "country" as const, placeholder: ctrans("All countries"), options: props.filters.options?.countries ?? [] },
    ].filter((filter) => !filter.hidden)
)

const shortDate = (iso: string | null | undefined) => (iso ? useFormatTime(iso, { formatTime: "PP" }) : "")

const outcomeLabels: Record<string, string> = {
    shown: ctrans("shown products"),
    clicked: ctrans("clicked a product"),
    added: ctrans("added to basket"),
    ordered: ctrans("ordered"),
}

const budgetRows = computed(() =>
    (props.budgets ?? []).map((budget) => {
        const used = budget.monthly > 0 ? budget.spent / budget.monthly : 1
        return { ...budget, width: Math.min(100, used * 100), warning: !budget.paused && used >= 0.8 }
    })
)

const kpis = computed(() => props.overview?.kpis ?? {})
const kpi = (key: string): Metric => kpis.value[key] ?? { value: 0, previous: null }
const costCurrency = computed(() => props.overview?.cost_currency ?? props.cost_currency ?? props.currency)
const money = (amount: number | null | undefined, code = props.currency) => locale.currencyFormat(code, amount ?? 0)
const percent = (fraction: number | null | undefined) => (fraction === null || fraction === undefined ? "—" : (fraction * 100).toFixed(1) + "%")

const change = (metric: Metric | null | undefined, better: "up" | "down" | "none" = "up", points = false) => {
    if (!metric || metric.previous === null || metric.previous === undefined) return null
    let text: string
    let direction: number
    if (points) {
        const delta = (metric.value - metric.previous) * 100
        text = (delta >= 0 ? "+" : "") + delta.toFixed(1) + " " + ctrans("pt")
        direction = Math.sign(delta)
    } else if (metric.previous === 0) {
        if (metric.value <= 0) return null
        text = ctrans("new")
        direction = 1
    } else {
        const delta = ((metric.value - metric.previous) / metric.previous) * 100
        text = (delta >= 0 ? "+" : "") + delta.toFixed(1) + "%"
        direction = Math.sign(delta)
    }
    const improved = better === "up" ? direction > 0 : direction < 0
    const cls = direction === 0 || better === "none" ? "text-gray-500" : improved ? "text-[#006300]" : "text-[#d03b3b]"
    return { text, cls }
}

const comparable = (metric: Metric | null | undefined) =>
    !metric || metric.previous === null || metric.previous === undefined || (!metric.previous && !metric.value) ? undefined : metric.previous

const hasConversations = computed(() => kpi("conversations").value > 0)

const kpiCards = computed(() => {
    const share = kpis.value.ai_cost_share
    return [
        {
            key: "conversations",
            label: ctrans("Conversations"),
            icon: faComments,
            metric: kpi("conversations"),
            kind: "count" as const,
            better: "up" as const,
            href: conversationsUrl(),
            lines: [
                ctrans(":count customers", { count: locale.number(kpi("customers").value) }),
                ctrans(":count returning customers", { count: locale.number(kpi("returning_customers").value) }),
            ],
        },
        {
            key: "orders",
            label: ctrans("Orders after being shown"),
            icon: faShoppingCart,
            metric: kpi("orders"),
            kind: "count" as const,
            better: "up" as const,
            href: conversationsUrl("ordered"),
            lines: [ctrans("strict: :count added from the assistant", { count: locale.number(kpi("strict_orders").value) })],
        },
        {
            key: "revenue",
            label: ctrans("Revenue of the products shown"),
            icon: faMoneyBillWave,
            metric: kpi("revenue"),
            kind: "money" as const,
            currency: props.currency,
            better: "up" as const,
            href: conversationsUrl("ordered"),
            lines: [ctrans("strict: :amount", { amount: money(kpi("strict_revenue").value) })],
        },
        {
            key: "conversion",
            label: ctrans("Conversion"),
            icon: faPercentage,
            metric: kpi("conversion_rate"),
            kind: "rate" as const,
            better: "up" as const,
            href: conversationsUrl("ordered"),
            lines: [ctrans("conversations older than :days days", { days: props.attribution_days ?? 7 })],
        },
        {
            key: "ai_cost",
            label: ctrans("AI cost"),
            icon: faCoins,
            metric: kpi("ai_cost"),
            kind: "money" as const,
            currency: costCurrency.value,
            better: "none" as const,
            href: null,
            lines: [
                ctrans(":amount per conversation", { amount: locale.currencyFormatUnitCost(costCurrency.value, kpi("ai_cost_per_conversation").value) }),
                ...(share && share.value !== null && share.value !== undefined ? [ctrans(":share of revenue", { share: percent(share.value) })] : []),
            ],
        },
    ]
})

const showMaturityNote = computed(() => {
    const until = props.overview?.mature_until
    return !!until && until < props.filters.to
})

const funnelLabels: Record<string, string> = {
    conversations: ctrans("Conversations"),
    shown: ctrans("Products shown"),
    clicked: ctrans("Clicked"),
    added: ctrans("Added to basket"),
    ordered: ctrans("Ordered"),
}
const funnel = computed(() => {
    const steps = props.overview?.funnel ?? []
    const first = steps[0]?.value ?? 0
    return steps.map((step, index) => {
        const previousStep = index > 0 ? steps[index - 1].value : null
        return {
            ...step,
            label: funnelLabels[step.key] ?? step.key,
            width: first > 0 ? Math.max((step.value / first) * 100, step.value > 0 ? 1 : 0) : 0,
            ofPrevious: previousStep && step.value <= previousStep ? step.value / previousStep : null,
            ofFirst: index > 0 && first > 0 ? step.value / first : null,
            href: index > 0 ? conversationsUrl(step.key) : null,
        }
    })
})

const seriesColours = { conversations: "#2a78d6", clicked: "#eb6834", added: "#1baf7a", ordered: "#eda100" }
const trendPoints = computed(() => props.overview?.trend?.points ?? [])
const hasIncompletePoints = computed(() => trendPoints.value.some((point) => !point.complete))
const isWeekly = computed(() => props.overview?.trend?.granularity === "week")

const chartData = computed(() => {
    const points = trendPoints.value
    const dashed = (context: any) => (points[context.p1DataIndex] && !points[context.p1DataIndex].complete ? [5, 4] : undefined)
    const series = [
        { key: "conversations", label: ctrans("Conversations") },
        { key: "clicked", label: ctrans("Clicked") },
        { key: "added", label: ctrans("Added to basket") },
        { key: "ordered", label: ctrans("Ordered") },
    ] as const
    return {
        labels: points.map((point) => point.date),
        datasets: series.map((serie) => ({
            label: serie.label,
            data: points.map((point) => point[serie.key] ?? 0),
            borderColor: seriesColours[serie.key],
            backgroundColor: seriesColours[serie.key],
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
            segment: { borderDash: dashed },
        })),
    }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } } },
        tooltip: {
            callbacks: {
                title: (items: any[]) => {
                    const point = trendPoints.value[items[0].dataIndex]
                    const label = (isWeekly.value ? ctrans("Week of") + " " : "") + useFormatTime(items[0].label, { formatTime: "PP" })
                    return point && !point.complete ? `${label} (${ctrans("not final yet")})` : label
                },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                autoSkip: true,
                maxTicksLimit: isNarrowScreen.value ? 4 : 10,
                maxRotation: 0,
                font: { size: 10 },
                callback: function (this: any, value: number) {
                    return useFormatTime(this.getLabelForValue(value), { formatTime: "d MMM" })
                },
            },
        },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: "rgba(0,0,0,0.05)" } },
    },
}))

const dropOffLabels: Record<string, string> = {
    "1": ctrans("1 question"),
    "2": ctrans("2 questions"),
    "3": ctrans("3 questions"),
    "4+": ctrans("4 or more questions"),
}
const dropOff = computed(() => {
    const rows = props.overview?.drop_off ?? []
    const total = rows.reduce((sum, row) => sum + (row.conversations ?? 0), 0)
    return rows.map((row) => ({
        label: dropOffLabels[row.questions] ?? row.questions,
        count: row.conversations ?? 0,
        share: total > 0 ? (row.conversations ?? 0) / total : 0,
    }))
})

const aovDifference = computed(() => kpi("aov_assistant").value - kpi("aov_others").value)
const engagementRows = computed(() => [
    { label: ctrans("Average time"), value: kpi("avg_minutes").value.toFixed(1) + " " + ctrans("min"), change: change(kpis.value.avg_minutes, "none") },
    { label: ctrans("Average messages"), value: kpi("avg_messages").value.toFixed(1), change: change(kpis.value.avg_messages, "none") },
    { label: ctrans("Average order, assistant users"), value: money(kpi("aov_assistant").value), change: change(kpis.value.aov_assistant) },
    { label: ctrans("Average order, other customers"), value: money(kpi("aov_others").value), change: change(kpis.value.aov_others) },
    {
        label: ctrans("Difference"),
        value: (aovDifference.value > 0 ? "+" : "") + money(aovDifference.value),
        valueClass: aovDifference.value > 0 ? "text-[#006300]" : aovDifference.value < 0 ? "text-[#d03b3b]" : "",
        change: null,
    },
    { label: ctrans("Families ordered for the first time"), value: locale.number(kpi("new_families").value), change: change(kpis.value.new_families) },
])

const families = computed(() => props.overview?.families ?? [])

const search = computed(() => props.overview?.search)
const ignoredFilterLabels: Record<string, string> = {
    entry_point: ctrans("entry point"),
    shop_type: ctrans("shop type"),
    country: ctrans("country"),
    language: ctrans("language"),
    website: ctrans("website"),
}
const ignoredFilters = computed(() => (search.value?.ignored_filters ?? []).map((key) => ignoredFilterLabels[key] ?? key.replace(/_/g, " ")).join(", "))

const kindLabels: Record<string, string> = {
    did_you_mean: ctrans("Did you mean"),
    related_search: ctrans("Related searches"),
    popular_search: ctrans("Popular searches"),
    family: ctrans("Sells well with"),
    best_sellers: ctrans("Best sellers"),
    new_arrivals: ctrans("New arrivals"),
    forgotten: ctrans("Forgotten favourites"),
}
const clicksByKind = computed(() => {
    const rows = search.value?.by_kind ?? []
    const max = Math.max(0, ...rows.map((row) => row.clicks ?? 0))
    return rows.map((row) => ({ label: kindLabels[row.kind] ?? row.kind, clicks: row.clicks ?? 0, width: max > 0 ? ((row.clicks ?? 0) / max) * 100 : 0 }))
})

const conversationsTable = computed(() => props.conversations)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="border-b border-gray-200 bg-white px-4 py-2">
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <div class="flex items-center gap-1.5">
                <DatePicker v-model="rangeFrom" :maxDate="rangeTo ?? undefined" :dateFormat="dateFormat" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
                <span class="text-gray-400">–</span>
                <DatePicker v-model="rangeTo" :minDate="rangeFrom ?? undefined" :dateFormat="dateFormat" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
            </div>
            <Select
                v-for="filter in selectFilters"
                :key="filter.key"
                :modelValue="filters[filter.key] ?? null"
                :options="filter.options"
                optionLabel="label"
                optionValue="value"
                :placeholder="filter.placeholder"
                :filter="filter.options.length > 10"
                showClear
                class="w-full sm:w-44"
                :class="fieldFocusClass"
                :pt="filterSelectPt"
                @update:modelValue="(value: any) => setFilter(filter.key, value ?? null)" />
            <Button v-if="hasActiveFilters" type="tertiary" size="xs" :label="ctrans('Reset')" @click="resetFilters">
                <template #icon>
                    <FontAwesomeIcon :icon="faUndo" class="text-gray-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <span v-if="isLoading" class="text-[var(--app-accent)]"><LoadingIcon /></span>
        </div>
        <p v-if="filters.previous_from && filters.previous_to" class="mt-1.5 text-xs text-gray-500">
            {{ ctrans("Compared with :from to :to", { from: shortDate(filters.previous_from), to: shortDate(filters.previous_to) }) }}
        </p>
    </div>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'overview'" class="space-y-4 p-4 transition-opacity" :class="isLoading ? 'opacity-60' : ''" :aria-busy="isLoading">
        <template v-if="!overview">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div v-for="index in 5" :key="index" class="skeleton h-28 rounded-lg" />
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="skeleton h-72 rounded-lg" />
                <div class="skeleton h-72 rounded-lg" />
            </div>
            <div class="skeleton h-48 rounded-lg" />
        </template>

        <template v-else>
            <section>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <div v-for="card in kpiCards" :key="card.key" class="min-w-0 rounded-lg border border-gray-200 bg-white px-4 py-3 max-lg:last:col-span-2">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="card.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ card.label }}</span>
                        </div>
                        <component :is="card.href ? Link : 'div'" :href="card.href ?? undefined" class="mt-1 block text-2xl font-semibold" :class="card.href ? 'hover:underline decoration-gray-300 underline-offset-4' : ''">
                            <template v-if="card.kind === 'rate'">
                                <div class="tabular-nums text-gray-900">{{ percent(card.metric.value) }}</div>
                                <div v-if="change(card.metric, 'up', true)" class="text-xs font-normal tabular-nums" :class="change(card.metric, 'up', true)?.cls">
                                    {{ change(card.metric, "up", true)?.text }}
                                </div>
                            </template>
                            <GoogleAdsMetric
                                v-else
                                :value="card.metric.value ?? 0"
                                :kind="card.kind"
                                :currency="card.currency"
                                :previous="comparable(card.metric)"
                                :better="card.better"
                                strong
                                class="[&>div:first-child]:text-2xl [&>div:first-child]:text-gray-900" />
                        </component>
                        <p v-for="line in card.lines" :key="line" class="mt-0.5 truncate text-xs text-gray-500" :title="line">{{ line }}</p>
                    </div>
                </div>
                <div v-if="budgetRows.length" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("AI budget this month") }}</h3>
                    <table class="w-full text-sm">
                        <thead class="whitespace-nowrap text-xs text-gray-400">
                            <tr>
                                <th v-if="!(budgetRows.length === 1 && filters.website_locked)" class="pb-1 pr-3 text-left font-medium">{{ ctrans("Website") }}</th>
                                <th class="pb-1 font-medium" />
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Spent") }}</th>
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Budget") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="budget in budgetRows" :key="budget.website">
                                <td v-if="!(budgetRows.length === 1 && filters.website_locked)" class="whitespace-nowrap py-1 pr-3 text-gray-700">{{ budget.website }}</td>
                                <td class="w-full py-1">
                                    <div class="h-2 rounded-sm bg-gray-100">
                                        <div class="h-2 rounded-sm" :class="budget.paused ? 'bg-red-500' : budget.warning ? 'bg-amber-500' : 'bg-[var(--app-accent)]'" :style="{ width: budget.width + '%' }" />
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-900">{{ money(budget.spent, "USD") }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-500">{{ money(budget.monthly, "USD") }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-xs">
                                    <span v-if="budget.paused" class="text-red-600">{{ ctrans("Paused until :date", { date: shortDate(budget.resumes_on) }) }}</span>
                                    <span v-else-if="budget.warning" class="text-amber-700">{{ ctrans("80% used") }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="showMaturityNote" class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
                    {{ ctrans("Conversations after :date can still turn into orders: orders, revenue and conversion for them are not final.", { date: shortDate(overview.mature_until) }) }}
                </p>
            </section>

            <section v-if="!hasConversations" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center">
                <FontAwesomeIcon :icon="faRobot" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                <h3 class="mt-2 text-sm font-semibold text-gray-700">{{ ctrans("No conversations in this period") }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ ctrans("The AI assistant is switched on per website, in the website settings.") }}</p>
            </section>

            <template v-else>
                <div class="grid gap-4 xl:grid-cols-2">
                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Funnel") }}</h3>
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium" />
                                    <th class="pb-1 text-left font-medium" />
                                    <th class="pb-1 pl-3 text-right font-medium">{{ ctrans("Count") }}</th>
                                    <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Of previous") }}</th>
                                    <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Of all") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="step in funnel" :key="step.key">
                                    <td class="whitespace-nowrap py-1.5 pr-3">
                                        <Link v-if="step.href" :href="step.href" class="primaryLink">{{ step.label }}</Link>
                                        <span v-else class="text-gray-700">{{ step.label }}</span>
                                    </td>
                                    <td class="w-full py-1.5">
                                        <div class="h-3 rounded-sm bg-gray-100">
                                            <div class="h-3 rounded-sm bg-[var(--app-accent)]" :style="{ width: step.width + '%' }" />
                                        </div>
                                    </td>
                                    <td class="py-1.5 pl-3 text-right font-medium tabular-nums text-gray-900">{{ locale.number(step.value) }}</td>
                                    <td class="py-1.5 pl-4 text-right tabular-nums text-gray-500">{{ step.ofPrevious === null ? "" : percent(step.ofPrevious) }}</td>
                                    <td class="py-1.5 pl-4 text-right tabular-nums text-gray-500">{{ step.ofFirst === null ? "" : percent(step.ofFirst) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-3 text-xs text-gray-500">
                            {{ ctrans("Ordered counts any order of a product the assistant showed, within :days days, also when it reached the basket another way.", { days: attribution_days }) }}
                        </p>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ isWeekly ? ctrans("Conversations by week") : ctrans("Conversations by day") }}</h3>
                        <div class="h-60">
                            <Chart type="line" class="h-full" :data="chartData" :options="chartOptions" />
                        </div>
                        <p v-if="hasIncompletePoints" class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="inline-block w-5 border-t-2 border-dashed border-gray-400" aria-hidden="true" />
                            {{ ctrans("not final yet") }}
                        </p>
                    </section>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Where customers leave") }}</h3>
                        <p class="mb-3 text-xs text-gray-500">{{ ctrans("Conversations by the number of questions asked") }}</p>
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium">{{ ctrans("Questions") }}</th>
                                    <th class="pb-1" />
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Count") }}</th>
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Share") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dropOff" :key="row.label">
                                    <td class="whitespace-nowrap py-1.5 pr-3 text-gray-700">{{ row.label }}</td>
                                    <td class="w-full py-1.5">
                                        <div class="h-3 rounded-sm bg-gray-100">
                                            <div class="h-3 rounded-sm bg-[var(--app-accent)]" :style="{ width: row.share * 100 + '%' }" />
                                        </div>
                                    </td>
                                    <td class="py-1.5 text-right font-medium tabular-nums text-gray-900">{{ locale.number(row.count) }}</td>
                                    <td class="py-1.5 text-right tabular-nums text-gray-500">{{ percent(row.share) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Engagement and value") }}</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in engagementRows" :key="row.label">
                                    <td class="py-1.5 pr-3 text-gray-600">{{ row.label }}</td>
                                    <td class="whitespace-nowrap py-1.5 text-right font-medium tabular-nums text-gray-900" :class="row.valueClass">{{ row.value }}</td>
                                    <td class="w-20 whitespace-nowrap py-1.5 text-right text-xs tabular-nums" :class="row.change?.cls">{{ row.change?.text ?? "" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <section v-if="families.length" class="rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Families suggested") }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Family") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Shown") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Clicked") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Added") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Ordered") }}</th>
                                    <th class="w-28 pb-1 text-right font-medium">{{ ctrans("Revenue") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="family in families" :key="family.id">
                                    <td class="py-1.5 pr-3">
                                        <span class="whitespace-nowrap font-medium text-gray-900">{{ family.code }}</span>
                                        <span class="ml-2 text-gray-500">{{ family.name }}</span>
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.shown) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.clicked) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.added) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.ordered) }}</td>
                                    <td class="whitespace-nowrap py-1.5 text-right tabular-nums">{{ money(family.revenue) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>

            <section v-if="search" class="space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Search on the website") }}</h3>
                    <p v-if="ignoredFilters" class="text-xs text-gray-500">{{ ctrans("Search figures ignore: :filters", { filters: ignoredFilters }) }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Searches") }}</div>
                        <GoogleAdsMetric :value="search.searches?.value ?? 0" kind="count" :previous="comparable(search.searches)" size="lg" />
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Searches with no results") }}</div>
                        <GoogleAdsMetric :value="search.zero_results?.value ?? 0" kind="count" :previous="comparable(search.zero_results)" better="down" size="lg" />
                        <div class="text-xs tabular-nums text-gray-500">
                            {{ ctrans(":rate of searches", { rate: percent(search.zero_result_rate?.value ?? 0) }) }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Clicks on Keep exploring") }}</div>
                        <GoogleAdsMetric :value="search.suggestion_clicks?.value ?? 0" kind="count" :previous="comparable(search.suggestion_clicks)" size="lg" />
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("No-result searches rescued") }}</div>
                        <GoogleAdsMetric :value="search.zero_rescued?.value ?? 0" kind="count" :previous="comparable(search.zero_rescued)" size="lg" />
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="mb-3 text-xs font-medium text-gray-500">{{ ctrans("Clicks by kind") }}</h4>
                        <table class="w-full text-sm">
                            <tbody>
                                <tr v-for="row in clicksByKind" :key="row.label">
                                    <td class="whitespace-nowrap py-1 pr-3 text-gray-700">{{ row.label }}</td>
                                    <td class="w-full py-1">
                                        <div class="h-2.5 rounded-sm bg-gray-100">
                                            <div class="h-2.5 rounded-sm bg-[var(--app-accent)]" :style="{ width: row.width + '%' }" />
                                        </div>
                                    </td>
                                    <td class="w-14 py-1 text-right tabular-nums text-gray-900">{{ locale.number(row.clicks) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="mb-3 text-xs font-medium text-gray-500">{{ ctrans("Top searches") }}</h4>
                        <table v-if="search.top_queries?.length" class="w-full text-sm">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium">{{ ctrans("Search") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Searches") }}</th>
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Clicks") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in search.top_queries" :key="row.query">
                                    <td class="max-w-0 truncate py-1 pr-3 text-gray-700" :title="row.query">{{ row.query }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.searches) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.clicks) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-4 text-center text-xs text-gray-400">{{ ctrans("No searches in this period") }}</p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="mb-3 text-xs font-medium text-gray-500">{{ ctrans("Searches with no results") }}</h4>
                        <table v-if="search.top_zero_queries?.length" class="w-full text-sm">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium">{{ ctrans("Search") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Searches") }}</th>
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Rescued") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in search.top_zero_queries" :key="row.query">
                                    <td class="max-w-0 truncate py-1 pr-3 text-gray-700" :title="row.query">{{ row.query }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.searches) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.rescued) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-4 text-center text-xs text-gray-400">{{ ctrans("No searches without results in this period") }}</p>
                    </div>
                </div>
            </section>
        </template>
    </div>

    <div v-else-if="currentTab === 'conversations'">
        <div v-if="filters.outcome" class="px-4 pt-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] py-0.5 pl-3 pr-1 text-xs text-gray-700">
                {{ ctrans("Only conversations: :outcome", { outcome: outcomeLabels[filters.outcome] ?? filters.outcome }) }}
                <Button type="tertiary" size="xxs" :tooltip="ctrans('Clear')" @click="setFilter('outcome', null)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </span>
        </div>
        <div v-if="!conversationsTable" class="space-y-2 p-4">
            <div v-for="index in 6" :key="index" class="skeleton h-10 rounded" />
        </div>
        <TableShoppingAssistantConversations
            v-else
            :data="conversationsTable"
            tab="conversations"
            :routes="routes"
            :currency="currency"
            :costCurrency="costCurrency" />
    </div>
</template>
