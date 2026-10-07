<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, provide, ref } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import TableWebpagesPerformance from "@/Components/Tables/Grp/Org/Web/TableWebpagesPerformance.vue"
import TableSearchConsoleQueries from "@/Components/Tables/Grp/Org/Web/TableSearchConsoleQueries.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import { routeType } from "@/types/route"
import { Navigation } from "@/types/Tabs"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBrowser, faSearch, faMousePointer } from "@fal"

library.add(faBrowser, faSearch, faMousePointer)

type DailyTraffic = {
    day: string
    visitors: number
    page_views: number
}

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
    daily: DailyTraffic[]
}

type DailySearch = {
    day: string
    clicks: number
    impressions: number
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
    tabs: {
        current: string
        navigation: Navigation
    }
    webpages?: object | null
    search_queries?: object | null
    search_opportunities?: object | null
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => currentTab.value === "webpages" ? TableWebpagesPerformance : TableSearchConsoleQueries)

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
        v-if="website"
        :aria-label="ctrans('Webpages and search queries')"
        :aria-busy="isLoadingOnTable"
        class="relative mx-4 mb-4 rounded-xl bg-white pb-3 pt-2 ring-1 ring-gray-200">
        <div v-if="isLoadingOnTable" class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/60">
            <LoadingIcon class="text-3xl text-[--app-accent-strong]" />
        </div>

        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

        <div class="pt-3">
            <component :is="tabComponent" v-if="props[currentTab]" :key="currentTab" :data="props[currentTab]" :tab="currentTab" />
        </div>
    </section>
</template>
