<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Select from "primevue/select"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import EdgeTrafficChart from "@/Components/Web/EdgeTrafficChart.vue"
import EdgeRequestSankey from "@/Components/Web/EdgeRequestSankey.vue"
import { daysOptionLabel, edgeDrillHref } from "@/Components/Web/edgeDrill"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowDown, faArrowUp, faInfoCircle } from "@fal"

library.add(faArrowDown, faArrowUp, faInfoCircle)

type Day = Record<string, number | null | undefined> & { date: string }

const props = defineProps<{
    data: {
        selected: string
        days: number
        days_options: number[]
        options: { label: string; items: { label: string; value: string }[] }[]
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
const daysOptions = computed(() => props.data.days_options.map((option) => ({ label: daysOptionLabel(option), value: option })))

const drill = (tab: string, query: Record<string, string | number | null> = {}) => edgeDrillHref({ website: props.data.selected, days: props.data.days }, tab, query)
const bySite = (sort: string, day: string | null = null) => drill("edge_websites", { edge_websites_sort: sort, day })

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

const statDefinitions = computed<{ label: string; help: string; metric: Metric; format: (value: number) => string; upIsGood: boolean; points?: boolean; sort: string }[]>(() => [
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
        sort: "-edge_share",
    },
    {
        label: ctrans("Reached our servers / day"),
        help: ctrans("Page requests per day that Cloudflare had to fetch from us: no copy yet, copy too old, not cacheable (logged in, baskets) or a rule said skip."),
        metric: (list) => perDay(list, reachedOf),
        format: formatCount,
        upIsGood: false,
        sort: "-requests_reached",
    },
    {
        label: ctrans("Avg origin time"),
        help: ctrans("Average time our servers took to answer the requests that reached them, as Cloudflare measured it."),
        metric: (list) => ratio(list, (day) => num(day, "origin_response_ms_total"), reachedOf),
        format: formatMs,
        upIsGood: false,
        sort: "-avg_origin_ms",
    },
])

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

const cacheTotal = (key: string) => sum(calendar.value, (day) => num(day, key)).total
const originSplit = computed(() => {
    const counted = calendar.value.filter((day) => num(day, "requests_octane") !== null)
    const reachedCounted = sum(counted, reachedOf).total
    const octane = sum(counted, (day) => num(day, "requests_octane")).total
    const varnish = sum(counted, (day) => Math.max(0, (reachedOf(day) ?? 0) - (num(day, "requests_octane") ?? 0))).total
    return { octane, varnish, notCounted: totals.value.reached - reachedCounted }
})
const towardsUs = ["dynamic", "miss", "bypass", "expired", "other"]
const sankeyNodes = computed(() => [
    { id: "requests", label: ctrans("Page requests"), value: totals.value.requests, color: "#9ca3af", column: 0 },
    { id: "dynamic", label: ctrans("Dynamic"), value: cacheTotal("requests_dynamic"), color: "#eda100", column: 1, note: ctrans("not cacheable"), href: bySite("-requests_dynamic") },
    { id: "miss", label: ctrans("Miss"), value: cacheTotal("requests_miss"), color: "#eb6834", column: 1, note: ctrans("no copy yet"), href: bySite("-requests_miss") },
    { id: "bypass", label: ctrans("Bypass"), value: cacheTotal("requests_bypass"), color: "#e87ba4", column: 1, note: ctrans("a rule said skip"), href: bySite("-requests_bypass") },
    { id: "expired", label: ctrans("Expired"), value: cacheTotal("requests_expired"), color: "#1baf7a", column: 1, note: ctrans("copy too old"), href: bySite("-requests_expired") },
    { id: "other", label: ctrans("Other"), value: cacheTotal("requests_other_cache_status"), color: "#9ca3af", column: 1 },
    { id: "hit", label: ctrans("Edge copy"), value: totals.value.hit, color: "#2a78d6", column: 1, note: ctrans("answered by Cloudflare"), href: bySite("-requests_hit"), emphasis: true },
    { id: "rules", label: ctrans("Cloudflare rules"), value: totals.value.none, color: "#60a5fa", column: 1, note: ctrans("blocks, challenges"), href: bySite("-requests_none") },
    { id: "reached", label: ctrans("Reached our servers"), value: totals.value.reached, color: "#6b7280", column: 2, href: bySite("-requests_reached") },
    { id: "octane", label: ctrans("Octane"), value: originSplit.value.octane, color: "#6250d6", column: 3, note: ctrans("rendered"), href: bySite("-requests_octane") },
    { id: "varnish", label: ctrans("Varnish"), value: originSplit.value.varnish, color: "#0d9488", column: 3, note: ctrans("copy"), href: bySite("-requests_varnish") },
    { id: "notCounted", label: ctrans("Varnish / Octane"), value: originSplit.value.notCounted, color: "#9ca3af", column: 3, note: ctrans("not counted yet") },
])
const sankeyNode = (id: string) => sankeyNodes.value.find((node) => node.id === id)
const sankeyLinks = computed(() => [
    ...sankeyNodes.value.filter((node) => node.column === 1).map((node) => ({ source: "requests", target: node.id, value: node.value })),
    ...towardsUs.map((id) => ({ source: id, target: "reached", value: sankeyNode(id)?.value ?? 0, color: sankeyNode(id)?.color })),
    ...["octane", "varnish", "notCounted"].map((id) => ({ source: "reached", target: id, value: sankeyNode(id)?.value ?? 0 })),
])

const labels = computed(() => dates.value.map(dateLabel))
const eventIndexes = computed(() => props.data.events.map((event) => dates.value.indexOf(event.date)).filter((index) => index >= 0))
const eventsCaption = computed(() => props.data.events.map((event) => `${dateLabel(event.date)} — ${event.label}`).join(" · "))

const series = (pick: (day: Day | null) => number | null) => calendar.value.map(pick)

const cacheDatasets = computed(() => [
    { label: ctrans("Answered at the edge"), color: "#2a78d6", data: series(edgeOf), sort: "-edge_share" },
    { label: ctrans("Miss"), color: "#eb6834", data: series((day) => num(day, "requests_miss")), sort: "-requests_miss" },
    { label: ctrans("Expired"), color: "#1baf7a", data: series((day) => num(day, "requests_expired")), sort: "-requests_expired" },
    { label: ctrans("Dynamic"), color: "#eda100", data: series((day) => num(day, "requests_dynamic")), sort: "-requests_dynamic" },
    { label: ctrans("Bypass"), color: "#e87ba4", data: series((day) => num(day, "requests_bypass")), sort: "-requests_bypass" },
    { label: ctrans("Other"), color: "#9ca3af", data: series((day) => num(day, "requests_other_cache_status")), sort: "-requests" },
])

const botDatasets = computed(() => [
    { label: ctrans("Browsers"), color: "#2a78d6", data: series((day) => num(day, "requests_browsers")), sort: "-requests_browsers" },
    { label: ctrans("Search bots"), color: "#eb6834", data: series((day) => (num(day, "requests") === null ? null : (num(day, "requests_google_bot") ?? 0) + (num(day, "requests_bing_bot") ?? 0) + (num(day, "requests_apple_bot") ?? 0))), sort: "-requests_google_bot" },
    { label: ctrans("Other bots"), color: "#1baf7a", data: series((day) => num(day, "requests_other_bots")), sort: "-requests_other_bots" },
    { label: ctrans("Unknown agent"), color: "#6250d6", data: series((day) => num(day, "requests_unknown_agent")), sort: "-requests_unknown_agent" },
])

const farDatasets = computed(() => [
    { label: ctrans("Share of all requests"), color: "#2a78d6", data: series((day) => share(num(day, "requests_far_countries"), num(day, "requests"))) },
    { label: ctrans("Of those, got the edge copy"), color: "#eb6834", data: series((day) => share(num(day, "requests_far_countries_hit"), num(day, "requests_far_countries"))) },
])


const sessionsDatasets = computed(() => [
    { label: ctrans("Logged in"), color: "#eb6834", data: series((day) => num(day, "logged_in_sessions")) },
    { label: ctrans("Anonymous"), color: "#2a78d6", data: series((day) => (num(day, "logged_in_sessions") === null ? null : Math.max(0, (num(day, "sessions") ?? 0) - (num(day, "logged_in_sessions") ?? 0)))) },
    { label: ctrans("Logged-in share not kept"), color: "#9ca3af", data: series((day) => (num(day, "logged_in_sessions") === null ? num(day, "sessions") : null)) },
])

const openDay = (datasets: { sort?: string }[], fallbackSort: string) => (index: number, datasetIndex: number | null) => {
    router.visit(bySite((datasetIndex === null ? undefined : datasets[datasetIndex]?.sort) ?? fallbackSort, dates.value[index]))
}
const openCacheDay = (index: number, datasetIndex: number | null) => openDay(cacheDatasets.value, "-requests")(index, datasetIndex)
const openBotDay = (index: number, datasetIndex: number | null) => openDay(botDatasets.value, "-requests")(index, datasetIndex)
const openFarDay = (index: number) => router.visit(bySite("-far_share", dates.value[index]))
const openVisitsDay = (index: number) => router.visit(drill("visitor_countries", { day: dates.value[index] }))

const farShareWindow = computed(() => ratio(calendar.value, (day) => num(day, "requests_far_countries"), (day) => num(day, "requests")))
const farHitWindow = computed(() => ratio(calendar.value, (day) => num(day, "requests_far_countries_hit"), (day) => num(day, "requests_far_countries")))
const loggedInWindow = computed(() => ratio(calendar.value, (day) => num(day, "logged_in_sessions"), (day) => (num(day, "logged_in_sessions") === null ? null : num(day, "sessions"))))

const statusCodes = computed(() => {
    const total = sum(calendar.value, (day) => num(day, "requests")).total
    return [
        { key: "2xx", label: "2xx", color: "#2a78d6", sort: "-requests_2xx", value: sum(calendar.value, (day) => num(day, "requests_2xx")).total },
        { key: "3xx", label: "3xx", color: "#86b6ef", sort: "-requests_3xx", value: sum(calendar.value, (day) => num(day, "requests_3xx")).total },
        { key: "4xx", label: "4xx", color: "#fab219", sort: "-share_4xx", value: sum(calendar.value, (day) => num(day, "requests_4xx")).total },
        { key: "5xx", label: "5xx", color: "#d03b3b", sort: "-requests_5xx", value: sum(calendar.value, (day) => num(day, "requests_5xx")).total },
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
                {{ ctrans("No Cloudflare figures for this selection yet. They are recorded daily from :date for every live storefront; visits below come from our own beacon.", { date: dateLabel(cacheStart) }) }}
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div v-for="stat in stats" :key="stat.label" class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap">
                        <span class="truncate">{{ stat.label }}</span>
                        <FontAwesomeIcon v-tooltip.top="stat.help" icon="fal fa-info-circle" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                    </div>
                    <Link :href="bySite(stat.sort)" class="mt-1 block text-sm font-semibold text-gray-900 hover:underline">{{ stat.headline }}</Link>
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
                    <div class="text-xs text-gray-500">
                        {{ ctrans("Storefront HTML page requests in the window, by the layer that answered them. Redirects Cloudflare makes are left out, the page they lead to is counted. Click a part to see its websites.") }}
                        <Link :href="drill('edge_datacenters', { edge_datacenters_sort: '-requests' })" class="ml-1 whitespace-nowrap text-gray-600 underline decoration-dotted underline-offset-4 hover:text-gray-800">{{ ctrans("By datacenter") }}</Link>
                    </div>
                </div>
                <EdgeRequestSankey class="mt-3" :nodes="sankeyNodes" :links="sankeyLinks" :total="totals.requests" />
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Cache status per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Edge = served from the Cloudflare copy or by its rules. Miss = no copy yet, expired = copy too old, dynamic = not cacheable (logged in, baskets), bypass = a rule said skip.") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="cacheDatasets" stacked :eventIndexes="eventIndexes" clickable @select="openCacheDay" />
                    <div class="mt-1 text-xs text-gray-500">{{ eventsCaption }} · {{ ctrans("Click a day to see its websites") }}</div>
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Bots and people per day") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("Search bots are Google, Bing and Apple. Unknown agent is traffic Cloudflare could not classify, mostly scrapers, kept apart from browsers and from known bots.") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="botDatasets" stacked :eventIndexes="eventIndexes" clickable @select="openBotDay" />
                    <div class="mt-1 text-xs text-gray-500">{{ eventsCaption }} · {{ ctrans("Click a day to see its websites") }}</div>
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
                    <div class="text-xs text-gray-500">{{ ctrans("Visitors from :countries are almost all scrapers.", { countries: data.far_countries.join(", ") }) }}</div>
                    <EdgeTrafficChart class="mt-3" type="line" :labels="labels" :datasets="farDatasets" unit="percent" :eventIndexes="eventIndexes" clickable @select="openFarDay" />
                </div>
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="font-medium text-gray-700">{{ ctrans("Visits per day (our beacon)") }}</div>
                        <div v-if="loggedInWindow !== null" class="text-xs text-gray-500 tabular-nums">{{ ctrans(":share logged in", { share: formatPercent(loggedInWindow * 100) }) }}</div>
                    </div>
                    <div class="text-xs text-gray-500">{{ ctrans("Logged-in visitors always bypass the edge copy. Their share is only known for the last 30 days, when raw visits are kept.") }}</div>
                    <EdgeTrafficChart class="mt-3" :labels="labels" :datasets="sessionsDatasets" stacked :eventIndexes="eventIndexes" clickable @select="openVisitsDay" />
                </div>
            </div>

            <div class="grid items-start gap-4 xl:grid-cols-2">
                <div class="rounded-md border border-gray-200 bg-white px-4 py-3">
                    <div class="font-medium text-gray-700">{{ ctrans("Response status at the edge") }}</div>
                    <div class="text-xs text-gray-500">{{ ctrans("What the visitor received, whichever layer answered") }}</div>
                    <div class="mt-3 flex h-3 w-full gap-0.5 overflow-hidden rounded-sm bg-gray-100">
                        <Link v-for="code in statusCodes" :key="code.key" :href="bySite(code.sort)" v-tooltip.top="`${code.label}: ${formatCount(code.value)} (${formatPercent(code.share)})`" class="block h-3" :style="{ width: `${code.share}%`, backgroundColor: code.color }" />
                    </div>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        <Link v-for="code in statusCodes" :key="code.key" :href="bySite(code.sort)" class="group text-xs">
                            <div class="flex items-center gap-1.5 text-gray-500">
                                <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: code.color }" />
                                {{ code.label }}
                            </div>
                            <div class="font-medium tabular-nums text-gray-700 group-hover:underline">{{ formatCount(code.value) }} <span class="font-normal text-gray-500">{{ formatPercent(code.share) }}</span></div>
                        </Link>
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
                            <Link :href="drill('visitor_countries', { country: country.code })" class="truncate text-gray-700 hover:underline">
                                {{ country.name }}
                                <span v-if="country.far" v-tooltip.top="ctrans('Far country: almost all scrapers')" class="ml-1 rounded-sm bg-amber-50 px-1 text-amber-700">{{ ctrans("far") }}</span>
                            </Link>
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
