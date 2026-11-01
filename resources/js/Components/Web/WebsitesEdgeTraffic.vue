<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Select from "primevue/select"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import EdgeTrafficChart from "@/Components/Web/EdgeTrafficChart.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowDown, faArrowRight, faArrowUp, faBolt, faCloud, faInfoCircle, faNetworkWired, faServer, faUser } from "@fal"

library.add(faArrowDown, faArrowRight, faArrowUp, faBolt, faCloud, faInfoCircle, faNetworkWired, faServer, faUser)

type Day = Record<string, number | null | undefined> & { date: string }

const props = defineProps<{
    data: {
        selected: string
        days: number
        days_options: number[]
        options: { label: string; items: { label: string; value: string }[] }[]
        currency_code: string
        can_view_sales: boolean
        far_countries: string[]
        events: { date: string; label: string }[]
        daily: Day[]
        countries: { code: string | null; sessions: number; logged_in_sessions: number }[]
    }
    tab?: string
}>()

const locale = useLocaleStore()
const loading = ref(false)

const reload = (query: { website?: string; days?: number }) => {
    loading.value = true
    router.reload({
        only: ["showcase"],
        data: { tab: props.tab ?? "showcase", website: props.data.selected, days: props.data.days, ...query },
        onFinish: () => (loading.value = false),
    })
}

const website = computed({ get: () => props.data.selected, set: (value: string) => reload({ website: value }) })
const days = computed({ get: () => props.data.days, set: (value: number) => reload({ days: value }) })
const daysOptions = computed(() => props.data.days_options.map((option) => ({ label: ctrans(":days days", { days: option }), value: option })))

const toIsoDay = (date: Date) => date.toISOString().slice(0, 10)
const dateLabel = (iso: string) => new Date(`${iso}T00:00:00Z`).toLocaleDateString(undefined, { day: "numeric", month: "short", timeZone: "UTC" })

const dates = computed(() => {
    const end = new Date()
    end.setUTCHours(0, 0, 0, 0)
    end.setUTCDate(end.getUTCDate() - 1)
    return Array.from({ length: props.data.days }, (_, offset) => toIsoDay(new Date(end.getTime() - (props.data.days - 1 - offset) * 86400000)))
})
const byDate = computed(() => new Map(props.data.daily.map((day) => [day.date, day])))
const calendar = computed(() => dates.value.map((date) => byDate.value.get(date) ?? null))

const num = (day: Day | null, key: string): number | null => {
    const value = day?.[key]
    return value === null || value === undefined ? null : Number(value)
}
const edgeOf = (day: Day | null) => (num(day, "requests") === null ? null : (num(day, "requests_hit") ?? 0) + (num(day, "requests_none") ?? 0))
const reachedOf = (day: Day | null) => (num(day, "requests") === null ? null : (num(day, "requests") ?? 0) - (edgeOf(day) ?? 0))
const share = (part: number | null, whole: number | null) => (part === null || !whole ? null : (part / whole) * 100)

const sum = (list: (Day | null)[], pick: (day: Day | null) => number | null) => {
    let total = 0
    let count = 0
    list.forEach((day) => {
        const value = pick(day)
        if (value !== null) {
            total += value
            count += 1
        }
    })
    return { total, count }
}
const perDay = (list: (Day | null)[], pick: (day: Day | null) => number | null) => {
    const { total, count } = sum(list, pick)
    return count ? total / count : null
}
const ratio = (list: (Day | null)[], part: (day: Day | null) => number | null, whole: (day: Day | null) => number | null) => {
    const top = sum(list, part).total
    const bottom = sum(list, whole).total
    return sum(list, whole).count && bottom ? top / bottom : null
}

const cacheStart = computed(() => props.data.events[0]?.date ?? "2026-10-31")
const since = computed(() => calendar.value.filter((day) => day && day.date >= cacheStart.value))
const before = computed(() => calendar.value.filter((day) => day && day.date < cacheStart.value))

const firstEdgeDay = computed(() => props.data.daily.find((day) => num(day, "requests") !== null)?.date ?? null)
const hasEdgeData = computed(() => firstEdgeDay.value !== null)

type Metric = (list: (Day | null)[]) => number | null
const formatPercent = (value: number) => `${locale.number(Math.round(value * 10) / 10)}%`
const formatCount = (value: number) => locale.number(Math.round(value))
const formatMs = (value: number) => `${locale.number(Math.round(value))} ms`
const formatMoney = (value: number) => String(locale.currencyFormat(props.data.currency_code, value))

const statDefinitions = computed<{ label: string; help: string; metric: Metric; format: (value: number) => string; upIsGood: boolean; points?: boolean; isSales?: boolean }[]>(() => [
    {
        label: ctrans("Answered at the edge"),
        help: ctrans("Share of storefront page requests Cloudflare answered itself, from its edge copy or its own rules, without touching our servers."),
        metric: (list) => {
            const edgeRatio = ratio(list, edgeOf, (day) => num(day, "requests"))
            return edgeRatio === null ? null : edgeRatio * 100
        },
        format: formatPercent,
        upIsGood: true,
        points: true,
    },
    {
        label: ctrans("Reached our servers / day"),
        help: ctrans("Page requests per day that Cloudflare had to fetch from us: no copy yet, copy too old, not cacheable (logged in, baskets) or a rule said skip."),
        metric: (list) => perDay(list, reachedOf),
        format: formatCount,
        upIsGood: false,
    },
    {
        label: ctrans("Avg origin time"),
        help: ctrans("Average time our servers took to answer the requests that reached them, as Cloudflare measured it."),
        metric: (list) => ratio(list, (day) => num(day, "origin_response_ms_total"), reachedOf),
        format: formatMs,
        upIsGood: false,
    },
    {
        label: ctrans("Orders / day"),
        help: ctrans("Orders per day in the shops behind the selected websites."),
        metric: (list) => perDay(list, (day) => num(day, "orders")),
        isSales: true,
        format: formatCount,
        upIsGood: true,
    },
    {
        label: ctrans("New customers / day"),
        help: ctrans("Customer registrations per day in the shops behind the selected websites."),
        metric: (list) => perDay(list, (day) => num(day, "new_customers")),
        isSales: true,
        format: formatCount,
        upIsGood: true,
    },
    {
        label: ctrans("Sales / day"),
        help: ctrans("Sales per day in the group currency, in the shops behind the selected websites."),
        metric: (list) => perDay(list, (day) => num(day, "sales")),
        isSales: true,
        format: formatMoney,
        upIsGood: true,
    },
].filter((definition) => props.data.can_view_sales || !definition.isSales))

const stats = computed(() => statDefinitions.value.map((definition) => {
    const after = definition.metric(since.value)
    const earlier = definition.metric(before.value)
    const headline = after ?? earlier
    const change = after === null || earlier === null ? null : definition.points ? after - earlier : earlier === 0 ? null : ((after - earlier) / Math.abs(earlier)) * 100
    return {
        ...definition,
        headline: headline === null ? "—" : definition.format(headline),
        scope: after !== null ? ctrans("since :date", { date: dateLabel(cacheStart.value) }) : ctrans("before :date", { date: dateLabel(cacheStart.value) }),
        earlier: after !== null && earlier !== null ? definition.format(earlier) : null,
        change,
        changeLabel: change === null ? "" : definition.points ? ctrans(":value pts", { value: locale.number(Math.round(Math.abs(change) * 10) / 10) }) : formatPercent(Math.abs(change)),
        sides: ctrans(":after days with data since :date vs :before days before", { after: since.value.length, before: before.value.length, date: dateLabel(cacheStart.value) }),
        good: change === null || Math.abs(change) < 0.5 ? null : (change > 0) === definition.upIsGood,
    }
}))

const totals = computed(() => {
    const requests = sum(calendar.value, (day) => num(day, "requests")).total
    const edge = sum(calendar.value, edgeOf).total
    return { requests, edge, reached: requests - edge, hit: sum(calendar.value, (day) => num(day, "requests_hit")).total, none: sum(calendar.value, (day) => num(day, "requests_none")).total }
})

const layers = computed(() => [
    { key: "visitor", label: ctrans("Visitors"), icon: "fal fa-user", value: totals.value.requests, note: ctrans("page requests"), color: "#6b7280" },
    { key: "edge", label: ctrans("Cloudflare edge"), icon: "fal fa-cloud", value: totals.value.edge, note: ctrans("answered here, incl. tiered cache"), color: "#2a78d6" },
    { key: "haproxy", label: "HAProxy", icon: "fal fa-network-wired", value: totals.value.reached, note: ctrans("reached our servers"), color: "#eb6834" },
    { key: "varnish", label: "Varnish", icon: "fal fa-server", value: totals.value.reached, note: ctrans("split not recorded yet"), color: "#eb6834" },
    { key: "octane", label: "Octane", icon: "fal fa-bolt", value: totals.value.reached, note: ctrans("split not recorded yet"), color: "#eb6834" },
])

const labels = computed(() => dates.value.map(dateLabel))
const eventIndexes = computed(() => props.data.events.map((event) => dates.value.indexOf(event.date)).filter((index) => index >= 0))
const eventsCaption = computed(() => props.data.events.map((event) => `${dateLabel(event.date)} — ${event.label}`).join(" · "))

const series = (pick: (day: Day | null) => number | null) => calendar.value.map(pick)

const cacheDatasets = computed(() => [
    { label: ctrans("Answered at the edge"), color: "#2a78d6", data: series(edgeOf) },
    { label: ctrans("Miss"), color: "#eb6834", data: series((day) => num(day, "requests_miss")) },
    { label: ctrans("Expired"), color: "#1baf7a", data: series((day) => num(day, "requests_expired")) },
    { label: ctrans("Dynamic"), color: "#eda100", data: series((day) => num(day, "requests_dynamic")) },
    { label: ctrans("Bypass"), color: "#e87ba4", data: series((day) => num(day, "requests_bypass")) },
    { label: ctrans("Other"), color: "#9ca3af", data: series((day) => num(day, "requests_other_cache_status")) },
])

const botDatasets = computed(() => [
    { label: ctrans("Browsers"), color: "#2a78d6", data: series((day) => num(day, "requests_browsers")) },
    { label: ctrans("Search bots"), color: "#eb6834", data: series((day) => (num(day, "requests") === null ? null : (num(day, "requests_google_bot") ?? 0) + (num(day, "requests_bing_bot") ?? 0) + (num(day, "requests_apple_bot") ?? 0))) },
    { label: ctrans("Other bots"), color: "#1baf7a", data: series((day) => num(day, "requests_other_bots")) },
    { label: ctrans("Unknown agent"), color: "#6250d6", data: series((day) => num(day, "requests_unknown_agent")) },
])

const farDatasets = computed(() => [
    { label: ctrans("Share of all requests"), color: "#2a78d6", data: series((day) => share(num(day, "requests_far_countries"), num(day, "requests"))) },
    { label: ctrans("Of those, got the edge copy"), color: "#eb6834", data: series((day) => share(num(day, "requests_far_countries_hit"), num(day, "requests_far_countries"))) },
])

const ordersDatasets = computed(() => [{ label: ctrans("Orders"), color: "#2a78d6", data: series((day) => num(day, "orders")) }])
const customersDatasets = computed(() => [{ label: ctrans("New customers"), color: "#2a78d6", data: series((day) => num(day, "new_customers")) }])
const salesDatasets = computed(() => [{ label: ctrans("Sales"), color: "#2a78d6", data: series((day) => num(day, "sales")) }])

const sessionsDatasets = computed(() => [
    { label: ctrans("Logged in"), color: "#eb6834", data: series((day) => num(day, "logged_in_sessions")) },
    { label: ctrans("Anonymous"), color: "#2a78d6", data: series((day) => (num(day, "logged_in_sessions") === null ? null : Math.max(0, (num(day, "sessions") ?? 0) - (num(day, "logged_in_sessions") ?? 0)))) },
    { label: ctrans("Logged-in share not kept"), color: "#9ca3af", data: series((day) => (num(day, "logged_in_sessions") === null ? num(day, "sessions") : null)) },
])

const farShareWindow = computed(() => ratio(calendar.value, (day) => num(day, "requests_far_countries"), (day) => num(day, "requests")))
const farHitWindow = computed(() => ratio(calendar.value, (day) => num(day, "requests_far_countries_hit"), (day) => num(day, "requests_far_countries")))
const loggedInWindow = computed(() => ratio(calendar.value, (day) => num(day, "logged_in_sessions"), (day) => (num(day, "logged_in_sessions") === null ? null : num(day, "sessions"))))

const statusCodes = computed(() => {
    const total = sum(calendar.value, (day) => num(day, "requests")).total
    return [
        { key: "2xx", label: "2xx", color: "#2a78d6", value: sum(calendar.value, (day) => num(day, "requests_2xx")).total },
        { key: "3xx", label: "3xx", color: "#86b6ef", value: sum(calendar.value, (day) => num(day, "requests_3xx")).total },
        { key: "4xx", label: "4xx", color: "#fab219", value: sum(calendar.value, (day) => num(day, "requests_4xx")).total },
        { key: "5xx", label: "5xx", color: "#d03b3b", value: sum(calendar.value, (day) => num(day, "requests_5xx")).total },
    ].map((code) => ({ ...code, share: total ? (code.value / total) * 100 : 0 }))
})

const regionNames = new Intl.DisplayNames(undefined, { type: "region" })
const countryName = (code: string | null) => {
    if (!code) {
        return ctrans("Unknown")
    }
    try {
        return regionNames.of(code) ?? code
    } catch {
        return code
    }
}
const countries = computed(() => {
    const top = props.data.countries[0]?.sessions ?? 0
    return props.data.countries.map((country) => ({
        ...country,
        name: countryName(country.code),
        far: !!country.code && props.data.far_countries.includes(country.code),
        width: top ? (country.sessions / top) * 100 : 0,
        loggedInShare: country.sessions ? (country.logged_in_sessions / country.sessions) * 100 : 0,
    }))
})
</script>

<template>
    <div class="px-4 py-4 space-y-4 text-sm text-gray-700">
        <div class="flex flex-wrap items-center gap-3">
            <Select
                v-model="website"
                :options="data.options"
                optionLabel="label"
                optionValue="value"
                optionGroupLabel="label"
                optionGroupChildren="items"
                size="small"
                class="w-72"
                :aria-label="ctrans('Website')"
            />
            <SegmentedToggle v-model="days" :options="daysOptions" :ariaLabel="ctrans('Days')" />
            <span class="text-xs text-gray-500">{{ ctrans("UTC days up to yesterday. Cloudflare figures are estimates from sampling.") }}</span>
        </div>

        <div class="space-y-4 transition-opacity" :class="{ 'opacity-50 pointer-events-none': loading }">
            <div v-if="!hasEdgeData" class="rounded-md border border-gray-200 bg-white px-4 py-3 text-xs text-gray-500">
                {{ ctrans("No Cloudflare figures for this selection yet. They are recorded daily from :date for every live storefront; sales and visits below come from our own records.", { date: dateLabel(cacheStart) }) }}
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <div v-for="stat in stats" :key="stat.label" class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap">
                        <span class="truncate">{{ stat.label }}</span>
                        <FontAwesomeIcon v-tooltip.top="stat.help" icon="fal fa-info-circle" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                    </div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">{{ stat.headline }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-1 text-xs text-gray-500">
                        <span>{{ stat.scope }}</span>
                        <template v-if="stat.change !== null">
                            <span class="flex items-center gap-0.5 font-medium tabular-nums" :class="stat.good === null ? 'text-gray-500' : stat.good ? 'text-green-700' : 'text-red-600'">
                                <FontAwesomeIcon :icon="stat.change > 0 ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" fixed-width aria-hidden="true" />
                                {{ stat.changeLabel }}
                            </span>
                            <span v-tooltip.top="stat.sides" class="tabular-nums">{{ ctrans("vs :value", { value: stat.earlier }) }}</span>
                        </template>
                    </div>
                </div>
            </div>

            <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="font-medium text-gray-700">{{ ctrans("Where a page request is answered") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Storefront HTML page requests in the window, by the layer that answered them") }}</div>
                </div>
                <div class="mt-3 grid grid-cols-5 gap-2">
                    <div v-for="(layer, index) in layers" :key="layer.key" class="relative">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 whitespace-nowrap">
                            <FontAwesomeIcon :icon="layer.icon" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ layer.label }}</span>
                            <FontAwesomeIcon v-if="index < layers.length - 1" icon="fal fa-arrow-right" class="ml-auto text-gray-300" fixed-width aria-hidden="true" />
                        </div>
                        <div class="mt-1.5 h-2 rounded-sm bg-gray-100">
                            <div class="h-2 rounded-sm" :style="{ width: `${totals.requests ? (layer.value / totals.requests) * 100 : 0}%`, backgroundColor: layer.color }" />
                        </div>
                        <div class="mt-1.5 font-semibold tabular-nums text-gray-700">
                            {{ formatCount(layer.value) }}
                            <span class="ml-1 font-normal text-xs text-gray-500">{{ totals.requests ? formatPercent((layer.value / totals.requests) * 100) : "" }}</span>
                        </div>
                        <div class="text-xs text-gray-500">{{ layer.note }}</div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    <span>{{ ctrans("Edge copy: :value", { value: formatCount(totals.hit) }) }}</span>
                    <span>{{ ctrans("Cloudflare rules (redirects, blocks): :value", { value: formatCount(totals.none) }) }}</span>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Cache status per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Edge = served from the Cloudflare copy or by its rules. Miss = no copy yet, expired = copy too old, dynamic = not cacheable (logged in, baskets), bypass = a rule said skip.") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="cacheDatasets" stacked :eventIndexes="eventIndexes" />
                    <div class="mt-1 text-xs text-gray-500">{{ eventsCaption }}</div>
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Bots and people per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Search bots are Google, Bing and Apple. Unknown agent is traffic Cloudflare could not classify, mostly scrapers, kept apart from browsers and from known bots.") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="botDatasets" stacked :eventIndexes="eventIndexes" />
                    <div class="mt-1 text-xs text-gray-500">{{ eventsCaption }}</div>
                </div>
            </div>

            <div v-if="data.can_view_sales" class="grid gap-4 xl:grid-cols-3">
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Orders per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Same dates as the cache chart") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="ordersDatasets" :eventIndexes="eventIndexes" height="h-44" />
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("New customers per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Registrations in the shops behind these websites") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="customersDatasets" :eventIndexes="eventIndexes" height="h-44" />
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Sales per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("In :currency", { currency: data.currency_code }) }}</div>
                    <EdgeTrafficChart class="mt-3" type="line" :labels="labels" :datasets="salesDatasets" unit="currency" :currencyCode="data.currency_code" :eventIndexes="eventIndexes" height="h-44" />
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-medium text-gray-700">{{ ctrans("Far countries") }}</div>
                        <div class="text-xs text-gray-500 tabular-nums">
                            <span v-if="farShareWindow !== null">{{ ctrans(":share of requests", { share: formatPercent(farShareWindow * 100) }) }}</span>
                            <span v-if="farHitWindow !== null"> · {{ ctrans(":share got the edge copy", { share: formatPercent(farHitWindow * 100) }) }}</span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500">{{ ctrans("Visitors from :countries are almost all scrapers and get a 24-hour edge copy from 1 Nov.", { countries: data.far_countries.join(", ") }) }}</div>
                    <EdgeTrafficChart class="mt-3" type="line" :labels="labels" :datasets="farDatasets" unit="percent" :eventIndexes="eventIndexes" />
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-medium text-gray-700">{{ ctrans("Visits per day (our beacon)") }}</div>
                        <div v-if="loggedInWindow !== null" class="text-xs text-gray-500 tabular-nums">{{ ctrans(":share logged in", { share: formatPercent(loggedInWindow * 100) }) }}</div>
                    </div>
                    <div class="text-xs text-gray-500">{{ ctrans("Logged-in visitors always bypass the edge copy. Their share is only known for the last 30 days, when raw visits are kept.") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="sessionsDatasets" stacked :eventIndexes="eventIndexes" />
                </div>
            </div>

            <div class="grid items-start gap-4 xl:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Response status at the edge") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("What the visitor received, whichever layer answered") }}</div>
                    <div class="mt-3 flex h-3 w-full gap-0.5 overflow-hidden rounded-sm bg-gray-100">
                        <div v-for="code in statusCodes" :key="code.key" v-tooltip.top="`${code.label}: ${formatCount(code.value)} (${formatPercent(code.share)})`" class="h-3" :style="{ width: `${code.share}%`, backgroundColor: code.color }" />
                    </div>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        <div v-for="code in statusCodes" :key="code.key" class="text-xs">
                            <div class="flex items-center gap-1.5 text-gray-500">
                                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: code.color }" />
                                {{ code.label }}
                            </div>
                            <div class="font-medium tabular-nums text-gray-700">{{ formatCount(code.value) }} <span class="font-normal text-gray-500">{{ formatPercent(code.share) }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Top countries by visits") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Our beacon, last 30 days at most. Far countries are flagged.") }}</div>
                    <div v-if="!countries.length" class="mt-3 text-xs text-gray-500">{{ ctrans("No visits recorded in this window") }}</div>
                    <ul v-else class="mt-3 space-y-1.5">
                        <li v-for="country in countries" :key="country.code ?? 'unknown'" class="grid grid-cols-[1.25rem_9rem_1fr_5rem_3.5rem] items-center gap-2 text-xs">
                            <img v-if="country.code" :src="`/flags/${country.code.toLowerCase()}.png`" :alt="country.code" class="h-3 w-4 rounded-sm object-cover" />
                            <span v-else class="h-3 w-4 rounded-sm bg-gray-200" />
                            <span class="truncate text-gray-700">
                                {{ country.name }}
                                <span v-if="country.far" v-tooltip.top="ctrans('Far country: 24-hour edge copy')" class="ml-1 rounded-sm bg-amber-50 px-1 text-amber-700">{{ ctrans("far") }}</span>
                            </span>
                            <span class="h-2 rounded-sm bg-gray-100"><span class="block h-2 rounded-sm" :style="{ width: `${country.width}%`, backgroundColor: country.far ? '#eb6834' : '#2a78d6' }" /></span>
                            <span class="text-right tabular-nums text-gray-700">{{ formatCount(country.sessions) }}</span>
                            <span v-tooltip.top="ctrans('Logged-in share')" class="text-right tabular-nums text-gray-500">{{ formatPercent(country.loggedInShare) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
