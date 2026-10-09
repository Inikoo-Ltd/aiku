<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, provide, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import TableWebpagesPerformance from "@/Components/Tables/Grp/Org/Web/TableWebpagesPerformance.vue"
import TableWebsiteConversionCustomers from "@/Components/Tables/Grp/Org/Web/TableWebsiteConversionCustomers.vue"
import SeoApiUsage from "@/Components/Seo/SeoApiUsage.vue"
import SeoDashboardMissingPages from "@/Components/Seo/SeoDashboardMissingPages.vue"
import SeoDashboardPageViews from "@/Components/Seo/SeoDashboardPageViews.vue"
import SeoDashboardVisitors from "@/Components/Seo/SeoDashboardVisitors.vue"
import TableSearchConsoleQueries from "@/Components/Tables/Grp/Org/Web/TableSearchConsoleQueries.vue"
import TableWebpagesPageSpeed from "@/Components/Tables/Grp/Org/Web/TableWebpagesPageSpeed.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { coreWebVitalRating, formatCoreWebVital, ratingStyles } from "@/Components/DataDisplay/coreWebVitals"
import type { CoreWebVital } from "@/Components/DataDisplay/coreWebVitals"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import { routeType } from "@/types/route"
import { Navigation } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBrowser, faSearch, faMousePointer, faTachometerAltFast, faCashRegister } from "@fal"

library.add(faBrowser, faSearch, faMousePointer, faTachometerAltFast, faCashRegister)

type DailyTraffic = {
    day: string
    visitors: number
    page_views: number
    add_to_baskets: number
    checkouts: number
    purchases: number
    revenue: number
}

type ConversionComparison = {
    from: string
    to: string
    days_with_data: number
    visitors: number
    add_to_baskets: number
    checkouts: number
    purchases: number
    revenue: number
    conversion_rate: number
}

type ComparisonKey = "previous_period" | "previous_year"

type WebsitePerformance = {
    days_with_data: number
    first_day: string | null
    last_day: string | null
    visitors: number
    sessions: number
    page_views: number
    pages_per_session: number
    avg_session_duration: number
    bounce_rate: number
    new_visitors: number
    returning_visitors: number
    sessions_desktop: number
    sessions_mobile: number
    sessions_tablet: number
    add_to_baskets: number
    checkouts: number
    purchases: number
    revenue: number
    conversion_rate: number
    average_order_value: number
    currency_code: string | null
    conversions_tracked_since: string | null
    comparisons: Partial<Record<ComparisonKey, ConversionComparison>>
    daily: DailyTraffic[]
}

type DailySearch = {
    day: string
    clicks: number
    impressions: number
}

type DeviceVitals = { lcp: number | null, inp: number | null, cls: number | null, samples?: number } | null

type PageSpeedSummary = {
    crux: { period_start: string | null, period_end: string | null, devices: Record<"phone" | "desktop", DeviceVitals> } | null
    visitors: { days: number, devices: Record<"phone" | "desktop", DeviceVitals> } | null
}

type SearchPerformance = {
    is_connected: boolean
    site_url: string | null
    service_account_email: string | null
    latest_stored_day: string | null
    days_with_data: number
    first_day: string | null
    last_day: string | null
    clicks: number
    impressions: number
    ctr: number
    position: number | null
    daily: DailySearch[]
}

type PerformanceWebsite = {
    name: string
    domain: string
    url: string
    route: routeType
}

type ShareSegment = {
    label: string
    value: number
    percentage: number
    swatchClass: string
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    intervals: Intervals
    settings: Settings
    website: PerformanceWebsite | null
    performance: WebsitePerformance | null
    search: SearchPerformance | null
    page_speed_summary: PageSpeedSummary | null
    page_speed?: object | null
    pageTabs: {
        current: string
        navigation: Navigation
    }
    tabs: {
        current: string
        navigation: Navigation
    }
    webpages?: object | null
    visitors?: object | null
    page_views?: object | null
    missing_pages?: object | null
    api_usage?: object | null
    conversions?: object | null
    search_queries?: object | null
    search_opportunities?: object | null
}>()

const currentPageTab = ref(props.pageTabs.current)

const handlePageTabUpdate = (tabSlug: string) => {
    if (tabSlug === currentPageTab.value) {
        return
    }

    router.get(`${window.location.pathname}?tab=${encodeURIComponent(tabSlug)}`, {}, {
        preserveScroll: true,
        onSuccess: () => currentPageTab.value = tabSlug,
    })
}

const pageTabComponent = computed(() => ({
    visitors: SeoDashboardVisitors,
    page_views: SeoDashboardPageViews,
    missing_pages: SeoDashboardMissingPages,
    api_usage: SeoApiUsage,
} as Record<string, unknown>)[currentPageTab.value])

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => {
    if (tabSlug === currentTab.value) {
        return
    }

    const url = new URL(window.location.href)

    url.search = `?tab=overview&table=${encodeURIComponent(tabSlug)}`

    router.get(url.pathname + url.search, {}, {
        only: [tabSlug, "queryBuilderProps"],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => currentTab.value = tabSlug,
    })
}

const tabComponent = computed(() => ({
    webpages: TableWebpagesPerformance,
    conversions: TableWebsiteConversionCustomers,
    search_queries: TableSearchConsoleQueries,
    search_opportunities: TableSearchConsoleQueries,
    page_speed: TableWebpagesPageSpeed,
})[currentTab.value])

type SpeedSource = "crux" | "visitors"

const speedSource = ref<SpeedSource>(props.page_speed_summary?.crux ? "crux" : "visitors")

const speedSourceOptions = [
    { label: ctrans("Google"), value: "crux" },
    { label: ctrans("Our visitors"), value: "visitors" },
]

const speedDevices = [
    { key: "phone" as const, label: ctrans("Mobile") },
    { key: "desktop" as const, label: ctrans("Desktop") },
]

const speedMetrics: Array<{ key: CoreWebVital, label: string, description: string }> = [
    { key: "lcp", label: "LCP", description: ctrans("Largest Contentful Paint: how long until the main content shows. Good is 2.5 s or less") },
    { key: "inp", label: "INP", description: ctrans("Interaction to Next Paint: how fast the page reacts to a tap or click. Good is 200 ms or less") },
    { key: "cls", label: "CLS", description: ctrans("Cumulative Layout Shift: how much the page jumps while loading. Good is 0.1 or less") },
]

const speedData = computed(() => props.page_speed_summary?.[speedSource.value] ?? null)

const speedPeriodText = computed(() => {
    if (speedSource.value === "crux") {
        const crux = props.page_speed_summary?.crux

        return crux?.period_end
            ? ctrans("Chrome users, 28 days to :date", { date: useFormatTime(`${crux.period_end}T00:00:00`, { formatTime: "mdy" }) })
            : ""
    }

    const visitors = props.page_speed_summary?.visitors

    return visitors ? ctrans("Measured in visitors' browsers, last :days days", { days: visitors.days }) : ""
})

const deviceVerdict = (vitals: DeviceVitals) => {
    if (!vitals) {
        return null
    }

    const ratings = speedMetrics.map((metric) => coreWebVitalRating(metric.key, vitals[metric.key])).filter((rating) => rating !== null)

    if (!ratings.length) {
        return null
    }

    return ratings.includes("poor") ? "poor" : ratings.includes("needs_improvement") ? "needs_improvement" : "good"
}

const verdictText = (rating: "good" | "needs_improvement" | "poor") => ({
    good: ctrans("Passes Core Web Vitals"),
    needs_improvement: ctrans("Needs improvement"),
    poor: ctrans("Fails Core Web Vitals"),
})[rating]

const reloadOnly = computed(() => ["intervals", "performance", "search", currentTab.value])

const locale = useLocaleStore()

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)

const hasData = computed(() => (props.performance?.days_with_data ?? 0) > 0)

const formatDay = (day: string, pattern: string) => useFormatTime(`${day}T00:00:00`, { formatTime: pattern })

const periodText = computed(() => {
    if (!props.performance?.first_day || !props.performance?.last_day) {
        return ""
    }

    return ctrans(":from to :to, :days days with data", {
        from: formatDay(props.performance.first_day, "mdy"),
        to: formatDay(props.performance.last_day, "mdy"),
        days: locale.number(props.performance.days_with_data),
    })
})

const formatDuration = (totalSeconds: number) => {
    const hours = Math.floor(totalSeconds / 3600)
    const minutes = Math.floor((totalSeconds % 3600) / 60)
    const seconds = totalSeconds % 60

    if (hours > 0) {
        return `${hours}h ${minutes}m`
    }

    if (minutes > 0) {
        return `${minutes}m ${String(seconds).padStart(2, "0")}s`
    }

    return `${seconds}s`
}

const percentageOf = (part: number, whole: number) => whole > 0 ? part / whole * 100 : 0

const formatPercentage = (percentage: number) => `${percentage.toFixed(1)}%`

const metrics = computed(() => {
    const performance = props.performance

    if (!performance) {
        return []
    }

    return [
        { label: ctrans("Sessions"), value: locale.number(performance.sessions) },
        { label: ctrans("Page views"), value: locale.number(performance.page_views) },
        { label: ctrans("Pages per session"), value: locale.number(performance.pages_per_session) },
        { label: ctrans("Avg. session duration"), value: formatDuration(performance.avg_session_duration) },
        { label: ctrans("Bounce rate"), value: `${locale.number(performance.bounce_rate)}%` },
    ]
})

const visitorTypes = computed<ShareSegment[]>(() => {
    const performance = props.performance

    if (!performance) {
        return []
    }

    return [
        { label: ctrans("New"), value: performance.new_visitors, swatchClass: "bg-[--app-accent]" },
        { label: ctrans("Returning"), value: performance.returning_visitors, swatchClass: "bg-[--app-accent-muted]" },
    ].map((segment) => ({ ...segment, percentage: percentageOf(segment.value, performance.visitors) }))
})

const deviceSessions = computed<ShareSegment[]>(() => {
    const performance = props.performance

    if (!performance) {
        return []
    }

    const unclassified = Math.max(0, performance.sessions - performance.sessions_desktop - performance.sessions_mobile - performance.sessions_tablet)

    return [
        { label: ctrans("Desktop"), value: performance.sessions_desktop, swatchClass: "bg-[--app-accent]" },
        { label: ctrans("Mobile"), value: performance.sessions_mobile, swatchClass: "bg-[--app-accent-muted]" },
        { label: ctrans("Tablet"), value: performance.sessions_tablet, swatchClass: "bg-gray-500" },
        { label: ctrans("Other"), value: unclassified, swatchClass: "bg-gray-300" },
    ].map((segment) => ({ ...segment, percentage: percentageOf(segment.value, performance.sessions) }))
})

const accentColor = () => getComputedStyle(document.documentElement).getPropertyValue("--app-accent").trim() || "#4f46e5"

const toDayKey = (date: Date) => [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, "0"),
    String(date.getDate()).padStart(2, "0"),
].join("-")

const fillCalendar = <Key extends string>(records: Array<{ day: string } & Record<Key, number>>, keys: Key[]) => {
    const toCalendarDay = (day: string, record?: Record<Key, number>) => ({
        day,
        ...Object.fromEntries(keys.map((key) => [key, record?.[key] ?? null])),
    }) as { day: string } & Record<Key, number | null>

    if (records.length < 2) {
        return records.map((record) => toCalendarDay(record.day, record))
    }

    const recordsByDay = new Map(records.map((record) => [record.day, record]))
    const calendar = []
    const cursor = new Date(`${records[0].day}T00:00:00`)
    const lastDay = new Date(`${records[records.length - 1].day}T00:00:00`)

    while (cursor <= lastDay) {
        const day = toDayKey(cursor)

        calendar.push(toCalendarDay(day, recordsByDay.get(day)))
        cursor.setDate(cursor.getDate() + 1)
    }

    return calendar
}

const dailyTraffic = computed(() => fillCalendar(props.performance?.daily ?? [], ["visitors", "page_views"]))

const showDailyChart = computed(() => (props.performance?.daily.length ?? 0) > 1)

const dailyChartData = computed(() => {
    const pointRadius = dailyTraffic.value.length > 45 ? 0 : 2

    return {
        labels: dailyTraffic.value.map((record) => formatDay(record.day, "d MMM")),
        datasets: [
            {
                label: ctrans("Visitors"),
                data: dailyTraffic.value.map((record) => record.visitors),
                spanGaps: false,
                borderColor: accentColor(),
                backgroundColor: accentColor(),
                borderWidth: 2,
                pointRadius,
                tension: 0.25,
            },
            {
                label: ctrans("Page views"),
                data: dailyTraffic.value.map((record) => record.page_views),
                spanGaps: false,
                borderColor: "#9ca3af",
                backgroundColor: "#9ca3af",
                borderWidth: 1.5,
                borderDash: [4, 3],
                pointRadius,
                tension: 0.25,
            },
        ],
    }
})

const dailyChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", align: "start", labels: { boxWidth: 12, boxHeight: 2, color: "#4b5563" } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxTicksLimit: 8, maxRotation: 0 } },
        y: { beginAtZero: true, border: { display: false }, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", precision: 0, maxTicksLimit: 5 } },
    },
}

type ConversionMetricKey = "add_to_baskets" | "checkouts" | "purchases" | "revenue" | "conversion_rate" | "average_order_value"

const comparisonLabels: Record<ComparisonKey, string> = {
    previous_period: ctrans("Previous period"),
    previous_year: ctrans("Same period last year"),
}

const formatMoney = (amount: number) => props.performance?.currency_code
    ? String(locale.currencyFormat(props.performance.currency_code, amount))
    : locale.number(amount)

const metricValue = (source: Pick<ConversionComparison, "add_to_baskets" | "checkouts" | "purchases" | "revenue" | "conversion_rate">, key: ConversionMetricKey) => {
    if (key === "average_order_value") {
        return source.purchases > 0 ? source.revenue / source.purchases : 0
    }

    return source[key]
}

const isTrackedSinceEventStart = (key: ConversionMetricKey) => key !== "add_to_baskets"

const comparisonText = (key: ConversionMetricKey, comparisonKey: ComparisonKey) => {
    const performance = props.performance
    const comparison = performance?.comparisons?.[comparisonKey]

    if (!performance || !comparison) {
        return null
    }

    const trackedSince = performance.conversions_tracked_since
    const isBeforeTracking = isTrackedSinceEventStart(key) && (!trackedSince || comparison.from < trackedSince)

    if (comparison.days_with_data === 0 || isBeforeTracking) {
        return { text: ctrans("No data"), tone: "text-gray-500" }
    }

    const current = metricValue(performance, key)
    const previous = metricValue(comparison, key)

    if (key === "conversion_rate") {
        const points = current - previous

        return {
            text: ctrans(":change pts", { change: `${points > 0 ? "+" : ""}${locale.number(Math.round(points * 100) / 100)}` }),
            tone: points > 0 ? "text-green-700" : points < 0 ? "text-red-700" : "text-gray-500",
        }
    }

    if (previous === 0) {
        return current > 0
            ? { text: ctrans("New"), tone: "text-green-700" }
            : { text: ctrans("No change"), tone: "text-gray-500" }
    }

    const change = (current - previous) / previous * 100

    return {
        text: `${change > 0 ? "+" : ""}${change.toFixed(1)}%`,
        tone: change > 0 ? "text-green-700" : change < 0 ? "text-red-700" : "text-gray-500",
    }
}

const comparisonRange = (comparisonKey: ComparisonKey) => {
    const comparison = props.performance?.comparisons?.[comparisonKey]

    return comparison
        ? ctrans(":from to :to", { from: formatDay(comparison.from, "mdy"), to: formatDay(comparison.to, "mdy") })
        : ""
}

const availableComparisons = computed(() => (Object.keys(comparisonLabels) as ComparisonKey[])
    .filter((comparisonKey) => props.performance?.comparisons?.[comparisonKey]))

const conversionMetrics = computed(() => {
    const performance = props.performance

    if (!performance) {
        return []
    }

    return [
        { key: "add_to_baskets" as const, label: ctrans("Add to basket"), value: locale.number(performance.add_to_baskets) },
        { key: "checkouts" as const, label: ctrans("Checkouts"), value: locale.number(performance.checkouts) },
        { key: "purchases" as const, label: ctrans("Purchases"), value: locale.number(performance.purchases) },
        { key: "revenue" as const, label: ctrans("Revenue"), value: formatMoney(performance.revenue) },
        { key: "conversion_rate" as const, label: ctrans("Conversion rate"), hint: ctrans("Purchases per 100 visitors"), value: `${locale.number(performance.conversion_rate)}%` },
        { key: "average_order_value" as const, label: ctrans("Avg. order value"), value: formatMoney(performance.average_order_value) },
    ]
})

const hasConversions = computed(() => {
    const performance = props.performance

    return !!performance && performance.add_to_baskets + performance.checkouts + performance.purchases > 0
})

const noConversionsText = computed(() => props.performance?.conversions_tracked_since
    ? ctrans("No add to basket, checkout or purchase in this period. Pick a longer interval above to compare with older days.")
    : ctrans("No checkout or purchase has been recorded on this website yet. They are recorded from the storefront as customers add to basket, reach the checkout and submit their order."))

const conversionsTrackedText = computed(() => props.performance?.conversions_tracked_since
    ? ctrans("Checkouts and purchases recorded since :date", { date: formatDay(props.performance.conversions_tracked_since, "mdy") })
    : ctrans("No checkout or purchase recorded yet"))

const dailyConversions = computed(() => fillCalendar(props.performance?.daily ?? [], ["add_to_baskets", "checkouts", "purchases"]))

const conversionChartData = computed(() => {
    const pointRadius = dailyConversions.value.length > 45 ? 0 : 2

    return {
        labels: dailyConversions.value.map((record) => formatDay(record.day, "d MMM")),
        datasets: [
            {
                label: ctrans("Purchases"),
                data: dailyConversions.value.map((record) => record.purchases),
                spanGaps: false,
                borderColor: accentColor(),
                backgroundColor: accentColor(),
                borderWidth: 2,
                pointRadius,
                tension: 0.25,
            },
            {
                label: ctrans("Checkouts"),
                data: dailyConversions.value.map((record) => record.checkouts),
                spanGaps: false,
                borderColor: "#4b5563",
                backgroundColor: "#4b5563",
                borderWidth: 1.5,
                pointRadius,
                tension: 0.25,
            },
            {
                label: ctrans("Add to basket"),
                data: dailyConversions.value.map((record) => record.add_to_baskets),
                spanGaps: false,
                borderColor: "#9ca3af",
                backgroundColor: "#9ca3af",
                borderWidth: 1.5,
                borderDash: [4, 3],
                pointRadius,
                tension: 0.25,
            },
        ],
    }
})

const conversionChartSummary = computed(() => ctrans("Add to basket, checkouts and purchases per day, :from to :to", {
    from: props.performance?.first_day ? formatDay(props.performance.first_day, "mdy") : "",
    to: props.performance?.last_day ? formatDay(props.performance.last_day, "mdy") : "",
}))

const isSearchConnected = computed(() => props.search?.is_connected ?? false)

const hasSearchData = computed(() => (props.search?.days_with_data ?? 0) > 0)

const searchPeriodText = computed(() => {
    if (!props.search?.first_day || !props.search?.last_day) {
        return ""
    }

    return ctrans(":from to :to, :days days with data", {
        from: formatDay(props.search.first_day, "mdy"),
        to: formatDay(props.search.last_day, "mdy"),
        days: locale.number(props.search.days_with_data),
    })
})

const latestSearchDayText = computed(() => props.search?.latest_stored_day
    ? ctrans("Latest day from Google: :day", { day: formatDay(props.search.latest_stored_day, "mdy") })
    : "")

const searchMetrics = computed(() => {
    const search = props.search

    if (!search) {
        return []
    }

    return [
        { label: ctrans("Impressions"), value: locale.number(search.impressions) },
        { label: ctrans("CTR"), value: `${locale.number(search.ctr)}%` },
        { label: ctrans("Avg. position"), value: search.position !== null ? locale.number(search.position) : "-" },
    ]
})

const dailySearch = computed(() => fillCalendar(props.search?.daily ?? [], ["clicks", "impressions"]))

const showSearchChart = computed(() => (props.search?.daily.length ?? 0) > 1)

const searchChartData = computed(() => {
    const pointRadius = dailySearch.value.length > 45 ? 0 : 2

    return {
        labels: dailySearch.value.map((record) => formatDay(record.day, "d MMM")),
        datasets: [
            {
                label: ctrans("Clicks"),
                data: dailySearch.value.map((record) => record.clicks),
                yAxisID: "y",
                spanGaps: false,
                borderColor: accentColor(),
                backgroundColor: accentColor(),
                borderWidth: 2,
                pointRadius,
                tension: 0.25,
            },
            {
                label: ctrans("Impressions"),
                data: dailySearch.value.map((record) => record.impressions),
                yAxisID: "impressions",
                spanGaps: false,
                borderColor: "#9ca3af",
                backgroundColor: "#9ca3af",
                borderWidth: 1.5,
                borderDash: [4, 3],
                pointRadius,
                tension: 0.25,
            },
        ],
    }
})

const searchChartOptions = {
    ...dailyChartOptions,
    scales: {
        ...dailyChartOptions.scales,
        impressions: { position: "right", beginAtZero: true, border: { display: false }, grid: { display: false }, ticks: { color: "#9ca3af", precision: 0, maxTicksLimit: 5 } },
    },
}

const searchChartSummary = computed(() => ctrans("Google Search clicks and impressions per day, :from to :to", {
    from: props.search?.first_day ? formatDay(props.search.first_day, "mdy") : "",
    to: props.search?.last_day ? formatDay(props.search.last_day, "mdy") : "",
}))

const dailyChartSummary = computed(() => ctrans("Visitors and page views per day, :from to :to", {
    from: props.performance?.first_day ? formatDay(props.performance.first_day, "mdy") : "",
    to: props.performance?.last_day ? formatDay(props.performance.last_day, "mdy") : "",
}))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentPageTab" :navigation="pageTabs.navigation" @update:tab="handlePageTabUpdate" />

    <component
        :is="pageTabComponent"
        v-if="currentPageTab !== 'overview'"
        :key="currentPageTab"
        :data="(props as Record<string, any>)[currentPageTab]"
        :tab="currentPageTab" />

    <template v-else>
        <div class="pt-3">
            <DashboardSettings :intervals="intervals" :settings="settings" currentTab="seo" :reloadOnly="reloadOnly" />
        </div>

        <section
            :aria-label="ctrans('Website performance')"
            :aria-busy="isLoadingOnTable"
            class="relative mx-4 my-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div v-if="isLoadingOnTable" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/60">
                <LoadingIcon class="text-3xl text-[--app-accent-strong]" />
            </div>

            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 pt-4">
                <p v-if="website" class="flex min-w-0 flex-wrap items-baseline gap-x-2 text-sm">
                    <Link
                        :href="route(website.route.name, website.route.parameters)"
                        class="font-medium text-gray-900 underline-offset-2 hover:underline focus-visible:underline">
                        {{ website.name }}
                    </Link>
                    <a
                        :href="website.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="break-all text-gray-600 underline-offset-2 hover:underline focus-visible:underline">
                        {{ website.domain }}
                    </a>
                </p>
                <span v-if="hasData" class="text-xs text-gray-500">{{ periodText }}</span>
            </div>

            <p v-if="!performance" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                {{ ctrans("This website has no traffic records yet. They are built every night from the visits tracked on the storefront.") }}
            </p>

            <p v-else-if="!hasData" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                {{ ctrans("No traffic was recorded in this period. Pick a longer interval above to see older days.") }}
            </p>

            <template v-else>
                <div
                    class="grid grid-cols-1 gap-x-8 gap-y-6 px-5 pb-5 pt-5"
                    :class="{ 'lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)]': showDailyChart }">
                    <div class="flex flex-col justify-between gap-y-5">
                        <div>
                            <div class="text-4xl font-semibold tabular-nums tracking-tight text-gray-900">{{ locale.number(performance.visitors) }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ ctrans("Visitors") }}</div>
                            <div class="text-xs text-gray-500">{{ ctrans("unique visitors per day, added up") }}</div>
                        </div>

                        <div>
                            <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                <div
                                    v-for="segment in visitorTypes"
                                    :key="segment.label"
                                    :class="segment.swatchClass"
                                    :style="{ width: `${segment.percentage}%` }" />
                            </div>
                            <dl class="mt-2 space-y-1 text-sm">
                                <div v-for="segment in visitorTypes" :key="segment.label" class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-sm" :class="segment.swatchClass" aria-hidden="true" />
                                    <dt class="text-gray-600">{{ segment.label }}</dt>
                                    <dd class="ml-auto tabular-nums text-gray-900">
                                        {{ locale.number(segment.value) }}
                                        <span class="ml-1.5 inline-block w-12 text-right text-gray-500">{{ formatPercentage(segment.percentage) }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div v-if="showDailyChart" class="min-w-0">
                        <div class="h-56 sm:h-64" role="img" :aria-label="dailyChartSummary">
                            <Chart type="line" :data="dailyChartData" :options="dailyChartOptions" class="h-full" />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-2 border-t border-gray-100 sm:grid-cols-3 lg:grid-cols-5">
                    <div
                        v-for="metric in metrics"
                        :key="metric.label"
                        class="min-w-0 px-5 py-4 lg:border-l lg:border-gray-100 lg:first:border-l-0">
                        <dt class="text-xs text-gray-500">{{ metric.label }}</dt>
                        <dd class="mt-1 break-words text-lg font-medium tabular-nums text-gray-900">{{ metric.value }}</dd>
                    </div>
                </dl>

                <div class="border-t border-gray-100 px-5 py-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                        <h3 class="text-xs font-medium text-gray-700">{{ ctrans("Sessions by device") }}</h3>
                        <span class="text-xs text-gray-500">{{ ctrans(":sessions sessions", { sessions: locale.number(performance.sessions) }) }}</span>
                    </div>
                    <div class="mt-2 flex h-2 gap-0.5 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                        <div
                            v-for="segment in deviceSessions"
                            :key="segment.label"
                            :class="segment.swatchClass"
                            :style="{ width: `${segment.percentage}%` }" />
                    </div>
                    <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="segment in deviceSessions" :key="segment.label" class="flex items-center gap-2">
                            <span class="size-2.5 shrink-0 rounded-sm" :class="segment.swatchClass" aria-hidden="true" />
                            <dt class="text-gray-600">{{ segment.label }}</dt>
                            <dd class="ml-auto tabular-nums text-gray-900">
                                {{ locale.number(segment.value) }}
                                <span class="ml-1.5 inline-block w-12 text-right text-gray-500">{{ formatPercentage(segment.percentage) }}</span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </template>
        </section>

        <section
            v-if="performance && hasData"
            :aria-label="ctrans('Conversions')"
            :aria-busy="isLoadingOnTable"
            class="relative mx-4 mb-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div v-if="isLoadingOnTable" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/60">
                <LoadingIcon class="text-3xl text-[--app-accent-strong]" />
            </div>

            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 pt-4">
                <span class="text-sm font-medium text-gray-900">{{ ctrans("Conversions") }}</span>
                <span class="text-xs text-gray-500">{{ conversionsTrackedText }}</span>
            </div>

            <p v-if="!hasConversions" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                {{ noConversionsText }}
            </p>

            <div v-else-if="showDailyChart" class="px-5 pt-4">
                <div class="h-48 sm:h-56" role="img" :aria-label="conversionChartSummary">
                    <Chart type="line" :data="conversionChartData" :options="dailyChartOptions" class="h-full" />
                </div>
            </div>

            <dl v-if="hasConversions" class="mt-4 grid grid-cols-2 border-t border-gray-100 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    v-for="metric in conversionMetrics"
                    :key="metric.key"
                    class="min-w-0 px-5 py-4 lg:border-l lg:border-gray-100 lg:first:border-l-0">
                    <dt class="text-xs text-gray-500" v-tooltip="metric.hint">{{ metric.label }}</dt>
                    <dd class="mt-1 break-words text-lg font-medium tabular-nums text-gray-900">{{ metric.value }}</dd>
                    <dd
                        v-for="comparisonKey in availableComparisons"
                        :key="comparisonKey"
                        class="mt-0.5 flex flex-wrap gap-x-1 text-xs"
                        v-tooltip="comparisonRange(comparisonKey)">
                        <span class="text-gray-500">{{ comparisonLabels[comparisonKey] }}</span>
                        <span class="tabular-nums" :class="comparisonText(metric.key, comparisonKey)?.tone">{{ comparisonText(metric.key, comparisonKey)?.text }}</span>
                    </dd>
                </div>
            </dl>
        </section>

        <section
            v-if="search"
            :aria-label="ctrans('Google Search performance')"
            :aria-busy="isLoadingOnTable"
            class="relative mx-4 mb-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div v-if="isLoadingOnTable" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/60">
                <LoadingIcon class="text-3xl text-[--app-accent-strong]" />
            </div>

            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 pt-4">
                <p class="flex min-w-0 flex-wrap items-baseline gap-x-2 text-sm">
                    <span class="font-medium text-gray-900">{{ ctrans("Google Search") }}</span>
                    <span v-if="search.site_url" class="break-all text-gray-600">{{ search.site_url }}</span>
                </p>
                <span v-if="hasSearchData" class="text-xs text-gray-500">{{ searchPeriodText }}</span>
            </div>

            <div v-if="!isSearchConnected" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                <p>{{ ctrans("Aiku cannot read Google Search Console for :domain yet.", { domain: website?.domain ?? "" }) }}</p>
                <p v-if="search.service_account_email" class="mt-1">
                    {{ ctrans("Add this account as a user on the domain's Search Console property:") }}
                    <span class="break-all font-mono text-xs text-gray-900">{{ search.service_account_email }}</span>
                </p>
                <p v-else class="mt-1">{{ ctrans("No Google service account is set up on this installation.") }}</p>
            </div>

            <p v-else-if="!search.latest_stored_day" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                {{ ctrans("Search Console data is fetched every night. The first fetch loads the last 16 months.") }}
            </p>

            <p v-else-if="!hasSearchData" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                {{ ctrans("No Google Search data in this period. Google sends it two to three days late.") }}
                <span class="text-gray-500">{{ latestSearchDayText }}</span>
            </p>

            <template v-else>
                <div
                    class="grid grid-cols-1 gap-x-8 gap-y-6 px-5 pb-5 pt-5"
                    :class="{ 'lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)]': showSearchChart }">
                    <div class="flex flex-col justify-between gap-y-5">
                        <div>
                            <div class="text-4xl font-semibold tabular-nums tracking-tight text-gray-900">{{ locale.number(search.clicks) }}</div>
                            <div class="mt-1 text-sm text-gray-600">{{ ctrans("Clicks") }}</div>
                            <div class="text-xs text-gray-500">{{ latestSearchDayText }}</div>
                        </div>

                        <dl class="space-y-1 text-sm">
                            <div v-for="metric in searchMetrics" :key="metric.label" class="flex items-baseline gap-2">
                                <dt class="text-gray-600">{{ metric.label }}</dt>
                                <dd class="ml-auto tabular-nums text-gray-900">{{ metric.value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="showSearchChart" class="min-w-0">
                        <div class="h-56 sm:h-64" role="img" :aria-label="searchChartSummary">
                            <Chart type="line" :data="searchChartData" :options="searchChartOptions" class="h-full" />
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <section
            v-if="page_speed_summary"
            :aria-label="ctrans('Page speed')"
            class="relative mx-4 mb-4 rounded-xl bg-white ring-1 ring-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 pt-4">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span class="font-medium text-gray-900">{{ ctrans("Page speed") }}</span>
                    <SegmentedToggle v-model="speedSource" :options="speedSourceOptions" :aria-label="ctrans('Data source')" />
                </div>
                <span v-if="speedPeriodText" class="text-xs text-gray-500">{{ speedPeriodText }}</span>
            </div>

            <p v-if="!speedData" class="px-5 pb-5 pt-3 text-sm text-gray-600">
                {{
                    speedSource === "crux"
                        ? ctrans("Google has not reported this website yet. It needs enough Chrome visits over 28 days, and the report is fetched every Tuesday.")
                        : ctrans("Not enough page loads measured in visitors' browsers in the last 28 days.")
                }}
            </p>

            <div v-else class="grid grid-cols-1 gap-x-8 gap-y-5 px-5 pb-5 pt-4 sm:grid-cols-2">
                <div v-for="device in speedDevices" :key="device.key">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                        <h3 class="text-sm font-medium text-gray-900">{{ device.label }}</h3>
                        <span
                            v-if="deviceVerdict(speedData.devices[device.key])"
                            class="inline-flex items-center gap-1.5 text-xs"
                            :class="ratingStyles[deviceVerdict(speedData.devices[device.key])!].text">
                            <span class="size-2 rounded-full" :class="ratingStyles[deviceVerdict(speedData.devices[device.key])!].dot" aria-hidden="true" />
                            {{ verdictText(deviceVerdict(speedData.devices[device.key])!) }}
                        </span>
                        <span v-else class="text-xs text-gray-500">{{ ctrans("No data") }}</span>
                    </div>

                    <dl v-if="speedData.devices[device.key]" class="mt-2 divide-y divide-gray-100 text-sm">
                        <div v-for="metric in speedMetrics" :key="metric.key" class="flex items-center gap-2 py-1.5">
                            <dt class="text-gray-600" v-tooltip="metric.description">{{ metric.label }}</dt>
                            <dd class="ml-auto flex items-center gap-1.5 tabular-nums text-gray-900">
                                {{ formatCoreWebVital(metric.key, speedData.devices[device.key]?.[metric.key]) }}
                                <span
                                    v-if="coreWebVitalRating(metric.key, speedData.devices[device.key]?.[metric.key])"
                                    class="size-2 rounded-full"
                                    :class="ratingStyles[coreWebVitalRating(metric.key, speedData.devices[device.key]?.[metric.key])!].dot"
                                    :title="ratingStyles[coreWebVitalRating(metric.key, speedData.devices[device.key]?.[metric.key])!].label" />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div v-if="website" class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
                {{ ctrans("75th percentile of real page loads.") }}
                <Link :href="route(website.route.name, website.route.parameters)" class="text-gray-700 underline underline-offset-2 hover:text-gray-900">{{ ctrans("See the weekly history") }}</Link>
            </div>
        </section>

        <section
            v-if="website"
            :aria-label="ctrans('Webpages and search queries')"
            :aria-busy="isLoadingOnTable"
            class="relative mx-4 mb-4 rounded-xl bg-white pb-3 pt-2 ring-1 ring-gray-200">
            <div v-if="isLoadingOnTable" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/60">
                <LoadingIcon class="text-3xl text-[--app-accent-strong]" />
            </div>

            <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

            <div class="pt-3">
                <component :is="tabComponent" v-if="props[currentTab]" :key="currentTab" :data="props[currentTab]" :tab="currentTab" v-bind="currentTab === 'conversions' ? { currencyCode: performance?.currency_code } : {}" />
            </div>
        </section>
    </template>
</template>
