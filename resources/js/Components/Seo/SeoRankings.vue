<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link, router, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBell, faChevronDown, faChevronRight, faSyncAlt } from "@fal"
import { faBell as fasBell } from "@fas"
import SeoKeywordHistoryChart from "@/Components/Seo/SeoKeywordHistoryChart.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import SeoKeywordFilters from "@/Components/Seo/SeoKeywordFilters.vue"
import qs from "qs"
import { featureLabel } from "@/Components/Seo/serpFeatures"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import type { SeoKeywordFilterOptions, SeoKeywordOptions } from "@/Components/Seo/types"

type Summary = {
    tracked: number
    checked: number
    pending: number
    top_3: number
    top_10: number
    top_20: number
    in_ai_overview: number
    improved: number
    declined: number
    last_checked_at: string | null
}

type IntentRow = { intent: string | null, keywords: number, top_10: number }

type CompetitorRow = { domain: string, label: string | null, checked: number, top_3: number, top_10: number }

type CompetitorPosition = { domain: string, label: string | null, position: number | null, url: string | null }

type RankingRow = {
    id: number
    keyword: string
    country_code: string
    device: string
    frequency: string
    last_checked_at: string | null
    is_pending: boolean
    can_check_now: boolean
    position: number | null
    previous_position: number | null
    has_previous_check: boolean
    ranking_url: string | null
    serp_features: string[]
    serp_features_owned: string[]
    in_ai_overview: boolean
    avg_monthly_searches: number | null
    keyword_difficulty: number | null
    intent: string | null
    tags: { id: number, name: string }[]
    search_console_position: number | null
    competitor_positions: CompetitorPosition[]
    is_watched: boolean
    alert: string | null
    history_route: routeType
    watch_route: routeType
    check_ids: number[]
    can_check_any: boolean
    other_countries: RankingRow[]
}

type RankingsData = {
    domain: string | null
    summary: Summary
    intents: IntentRow[]
    competitors: CompetitorRow[]
    isConfigured: boolean
    depths: { weekly: number, daily: number }
    costPerPage: number
    checkNow: { keywords: number, cost: number, route: routeType } | null
    table: object
}

defineOptions({ inheritAttrs: false })

library.add(faBell, fasBell, faChevronDown, faChevronRight, faSyncAlt)

const props = defineProps<{
    data?: RankingsData
    tab: string
    options: SeoKeywordOptions
    filterOptions: SeoKeywordFilterOptions
}>()

const summary = computed(() => props.data!.summary)
const depths = computed(() => props.data!.depths)

const runError = ref<string | null>(null)

const locale = useLocaleStore()
const page = usePage()

const CONFIRM_FROM_USD = 1

const usd = (amount: number) => new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: amount < 1 ? 4 : 2 }).format(amount)

const countryName = (code: string) => props.options.countries.find((country) => country.value === code)?.label ?? code

const records = computed<RankingRow[]>(() => (props.data?.table as { data?: RankingRow[] } | undefined)?.data ?? [])

const expandedKeywords = ref<Record<number, boolean>>({})

const toggleCountries = (ranking: RankingRow) => {
    expandedKeywords.value[ranking.id] = !expandedKeywords.value[ranking.id]
}

const costOf = (ranking: RankingRow) => (ranking.check_ids.length * (ranking.frequency === "daily" ? depths.value.daily : depths.value.weekly)) / 10 * (props.data?.costPerPage ?? 0)

const checking = ref<number | "all" | null>(null)

const checkNow = (ranking?: RankingRow) => {
    const checkNowData = props.data?.checkNow

    if (!checkNowData) {
        return
    }

    if (!ranking && checkNowData.cost >= CONFIRM_FROM_USD && !window.confirm(ctrans("Check :count keywords in Google now? It costs about :cost of DataForSEO credit.", { count: locale.number(checkNowData.keywords), cost: usd(checkNowData.cost) }))) {
        return
    }

    router.post(route(checkNowData.route.name, checkNowData.route.parameters), ranking ? { tracked_keyword_ids: ranking.check_ids } : {}, {
        preserveScroll: true,
        only: [props.tab],
        onStart: () => {
            checking.value = ranking ? ranking.id : "all"
            runError.value = null
        },
        onError: (errors) => runError.value = Object.values(errors)[0] ?? null,
        onFinish: () => checking.value = null,
    })
}

const frequencyLabels: Record<string, string> = {
    on_request: ctrans("On request"),
    monthly: ctrans("Monthly"),
    weekly: ctrans("Weekly"),
    daily: ctrans("Daily"),
}

const intentLabels: Record<string, string> = {
    informational: ctrans("Informational"),
    navigational: ctrans("Navigational"),
    commercial: ctrans("Commercial"),
    transactional: ctrans("Transactional"),
}

const share = (count: number, total: number) => total ? Math.round(count / total * 100) : 0

const visibility = computed(() => [
    { label: ctrans("Top 3"), count: summary.value.top_3 },
    { label: ctrans("Top 10"), count: summary.value.top_10 },
    { label: ctrans("Top :depth", { depth: depths.value.daily }), count: summary.value.top_20 },
])

const filtersParam = computed(() => `${props.tab}_filters`)

const currentQuery = computed(() => qs.parse(new URL(page.url, window.location.origin).search, { ignoreQueryPrefix: true, arrayLimit: 100 }) as Record<string, Record<string, unknown> | unknown>)

const currentIntent = computed(() => {
    const intents = ((currentQuery.value[filtersParam.value] ?? {}) as { intents?: string[] }).intents ?? []

    return intents.length === 1 ? intents[0] : null
})

const intentHref = (intent: string | null) => {
    const url = new URL(page.url, window.location.origin)
    const query = { ...currentQuery.value }
    const filters = { ...((query[filtersParam.value] ?? {}) as Record<string, unknown>) }

    delete query[`${props.tab}Page`]

    if (intent) {
        filters.intents = [intent]
    } else {
        delete filters.intents
    }

    query[filtersParam.value] = filters

    return url.pathname + qs.stringify(query, { addQueryPrefix: true, encodeValuesOnly: true })
}

const total = computed(() => (props.data?.table as { meta?: { total?: number } } | undefined)?.meta?.total ?? null)

const positionText = (ranking: RankingRow) => {
    if (!ranking.last_checked_at) {
        return ranking.is_pending ? ctrans("Checking") : ctrans("Not checked yet")
    }

    if (ranking.position === null) {
        return ctrans("Not in top :depth", { depth: ranking.frequency === "daily" ? depths.value.daily : depths.value.weekly })
    }

    return locale.number(ranking.position)
}

const change = (ranking: RankingRow) => {
    if (!ranking.last_checked_at || !ranking.has_previous_check) {
        return null
    }

    if (ranking.previous_position === null && ranking.position !== null) {
        return { text: ctrans("New"), class: "text-green-700" }
    }

    if (ranking.previous_position !== null && ranking.position === null) {
        return { text: ctrans("Lost"), class: "text-red-700" }
    }

    if (ranking.previous_position === null || ranking.position === null || ranking.previous_position === ranking.position) {
        return null
    }

    const difference = ranking.previous_position - ranking.position

    return difference > 0
        ? { text: `+${difference}`, class: "text-green-700" }
        : { text: `${difference}`, class: "text-red-700" }
}

const isFeatureOurs = (ranking: RankingRow, feature: string) => ranking.serp_features_owned.includes(feature) || (feature === "ai_overview" && ranking.in_ai_overview)

const historyFor = ref<RankingRow | null>(null)

const watching = ref<number | null>(null)

const toggleWatch = (ranking: RankingRow) => {
    router.post(route(ranking.watch_route.name, ranking.watch_route.parameters), {}, {
        preserveScroll: true,
        preserveState: true,
        only: [props.tab],
        onStart: () => watching.value = ranking.id,
        onFinish: () => watching.value = null,
    })
}

const alertLabels: Record<string, string> = {
    left_top_10: ctrans("Left the top 10"),
    lost: ctrans("Out of the results"),
    dropped: ctrans("Dropped"),
}

const urlPath = (url: string) => {
    try {
        const parsed = new URL(url)

        return parsed.pathname + parsed.search
    } catch {
        return url
    }
}
</script>

<template>
    <div v-if="data" class="pb-4">
        <div v-if="data.checkNow && summary.tracked" class="mx-4 mt-4 flex flex-wrap items-center justify-end gap-2">
            <Button
                type="tertiary"
                :label="data.checkNow.keywords ? ctrans('Check :count keywords now', { count: locale.number(data.checkNow.keywords) }) : ctrans('Check now')"
                icon="fal fa-sync-alt"
                :loading="checking === 'all'"
                :disabled="!data.checkNow.keywords"
                v-tooltip="data.checkNow.keywords
                    ? ctrans('Reads the Google position of every active keyword not checked today, about :cost of DataForSEO credit. Results arrive within minutes to an hour.', { cost: usd(data.checkNow.cost) })
                    : ctrans('Every active keyword was checked today or is waiting for its result.')"
                @click="checkNow()" />
        </div>

        <p v-if="runError" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-red-700 ring-1 ring-gray-200">
            {{ runError }}
        </p>

        <p v-if="!data.isConfigured" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-700 ring-1 ring-gray-200">
            {{ ctrans("DataForSEO is not set up, so no keyword is checked. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD to the environment.") }}
        </p>

        <section v-if="!summary.tracked" class="mx-4 mt-4 rounded-xl bg-white px-5 py-6 text-sm text-gray-600 ring-1 ring-gray-200">
            <p class="font-medium text-gray-900">{{ ctrans("No keywords are tracked for this shop yet.") }}</p>
            <p class="mt-1">
                {{ ctrans("Add keywords in the Tracked keywords tab, or with Track on a Research result, and competitor domains in SEO > Competitors. A keyword is checked in Google when you press Check now, or monthly, weekly or daily if you set it so, and its position appears here.") }}
            </p>
        </section>

        <template v-else>
            <section :aria-label="ctrans('Visibility')" class="mx-4 mt-4 rounded-xl bg-white ring-1 ring-gray-200">
                <div class="flex flex-wrap items-end gap-x-10 gap-y-4 px-5 py-4">
                    <div v-if="!summary.checked" class="text-sm text-gray-600">
                        <p class="font-medium text-gray-900">{{ ctrans("No check has finished yet.") }}</p>
                        <p class="mt-1">
                            {{ summary.pending
                                ? ctrans("Google results are on their way and usually arrive within the hour.")
                                : ctrans("Press Check now to read the positions. Keywords set to monthly, weekly or daily are also checked at night from 00:30 UTC.") }}
                        </p>
                    </div>

                    <dl v-else class="flex flex-wrap gap-x-8 gap-y-3">
                        <div v-for="tier in visibility" :key="tier.label">
                            <dt class="text-xs text-gray-500">{{ tier.label }}</dt>
                            <dd class="mt-0.5 flex items-baseline gap-x-1.5">
                                <span class="text-2xl font-semibold tabular-nums tracking-tight text-gray-900">{{ share(tier.count, summary.checked) }}%</span>
                                <span class="text-xs tabular-nums text-gray-500">{{ ctrans(":count of :total", { count: locale.number(tier.count), total: locale.number(summary.checked) }) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ ctrans("Since the previous check") }}</dt>
                            <dd class="mt-1.5 flex items-baseline gap-x-3 text-sm tabular-nums">
                                <span class="text-green-700">{{ ctrans(":count up", { count: locale.number(summary.improved) }) }}</span>
                                <span class="text-red-700">{{ ctrans(":count down", { count: locale.number(summary.declined) }) }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">{{ ctrans("Cited in AI Overview") }}</dt>
                            <dd class="mt-0.5 text-2xl font-semibold tabular-nums tracking-tight text-gray-900">{{ locale.number(summary.in_ai_overview) }}</dd>
                        </div>
                    </dl>

                    <div class="ml-auto text-right text-xs text-gray-500">
                        <div v-if="data.domain" class="font-medium text-gray-900">{{ data.domain }}</div>
                        <div>{{ ctrans(":checked of :tracked keywords checked", { checked: locale.number(summary.checked), tracked: locale.number(summary.tracked) }) }}</div>
                        <div v-if="summary.last_checked_at">{{ ctrans("Latest check :date", { date: useFormatTime(summary.last_checked_at) }) }}</div>
                        <div v-if="summary.pending">{{ ctrans(":count checks waiting for Google results", { count: locale.number(summary.pending) }) }}</div>
                    </div>
                </div>
            </section>

            <div v-if="summary.checked" class="mx-4 mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section :aria-labelledby="'rankings-intents'" class="rounded-xl bg-white ring-1 ring-gray-200">
                    <div class="flex items-baseline justify-between gap-2 border-b border-gray-100 px-5 py-3">
                        <h2 id="rankings-intents" class="text-sm font-medium text-gray-900">{{ ctrans("By search intent") }}</h2>
                        <Link v-if="currentIntent" :href="intentHref(null)" preserve-scroll class="text-xs text-[var(--app-accent)] underline-offset-2 hover:underline focus-visible:underline">{{ ctrans("Show all keywords") }}</Link>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="row in data.intents" :key="row.intent ?? 'unknown'" class="flex items-center gap-x-4 px-5 py-2.5 text-sm">
                            <Link
                                v-if="row.intent"
                                :href="intentHref(row.intent)"
                                preserve-scroll
                                class="w-32 shrink-0 underline-offset-2 hover:underline focus-visible:underline"
                                :class="currentIntent === row.intent ? 'font-medium text-[var(--app-accent)]' : 'text-gray-900'">
                                {{ intentLabels[row.intent] ?? row.intent }}
                            </Link>
                            <span v-else class="w-32 shrink-0 text-gray-500">{{ ctrans("Unknown") }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                <div class="h-full rounded-full bg-[var(--app-accent-muted)]" :style="{ width: `${share(row.keywords, summary.checked)}%` }" />
                            </div>
                            <span class="w-24 shrink-0 text-right text-xs tabular-nums text-gray-500">{{ ctrans(":share% of keywords", { share: share(row.keywords, summary.checked) }) }}</span>
                            <span class="w-28 shrink-0 text-right text-xs tabular-nums text-gray-700">{{ ctrans(":share% in our top 10", { share: share(row.top_10, row.keywords) }) }}</span>
                        </li>
                    </ul>
                </section>

                <section :aria-labelledby="'rankings-competitors'" class="rounded-xl bg-white ring-1 ring-gray-200">
                    <div class="border-b border-gray-100 px-5 py-3">
                        <h2 id="rankings-competitors" class="text-sm font-medium text-gray-900">{{ ctrans("Competitors on the same keywords") }}</h2>
                    </div>
                    <p v-if="!data.competitors.length" class="px-5 py-4 text-sm text-gray-600">
                        {{ ctrans("No competitors yet. Add their domains in SEO > Competitors, and their positions are read from the same checks at no extra cost.") }}
                    </p>
                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs text-gray-500">
                                <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Domain") }}</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Top 3") }}</th>
                                <th scope="col" class="px-5 py-2 text-right font-medium">{{ ctrans("Top 10") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-gray-50">
                                <td class="px-5 py-2 font-medium text-gray-900">{{ data.domain ?? ctrans("Our website") }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ share(summary.top_3, summary.checked) }}%</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ share(summary.top_10, summary.checked) }}%</td>
                            </tr>
                            <tr v-for="competitor in data.competitors" :key="competitor.domain">
                                <td class="px-5 py-2 text-gray-900">
                                    {{ competitor.domain }}
                                    <span v-if="competitor.label" class="text-xs text-gray-500">{{ competitor.label }}</span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ competitor.checked ? `${share(competitor.top_3, competitor.checked)}%` : "-" }}</td>
                                <td class="px-5 py-2 text-right tabular-nums">{{ competitor.checked ? `${share(competitor.top_10, competitor.checked)}%` : "-" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <div class="mt-4">
                <div class="mx-4 mb-2 flex flex-wrap items-center justify-end gap-2">
                    <SeoExportButton table="rankings" />
                </div>
                <SeoKeywordFilters :tab="tab" :filterOptions="filterOptions" :total="total" />
                <Table :resource="data.table" :name="tab" :useExpandTable="true">
                    <template #cell(keyword)="{ item: ranking }: { item: RankingRow }">
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <button
                                type="button"
                                class="text-left text-gray-900 underline-offset-2 hover:underline focus-visible:underline"
                                v-tooltip="ctrans('Position history')"
                                @click="historyFor = ranking">
                                {{ ranking.keyword }}
                            </button>
                            <button
                                type="button"
                                :aria-pressed="ranking.is_watched"
                                :disabled="watching === ranking.id"
                                :aria-label="ranking.is_watched ? ctrans('Stop watching :keyword', { keyword: ranking.keyword }) : ctrans('Watch :keyword', { keyword: ranking.keyword })"
                                v-tooltip="ranking.is_watched ? ctrans('You are told when it falls. Click to stop.') : ctrans('Watch: be told by notification and email when it leaves the top 10 or drops :places places', { places: 5 })"
                                class="shrink-0 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
                                :class="ranking.is_watched ? 'text-[var(--app-accent)]' : 'text-gray-300 hover:text-gray-500'"
                                @click="toggleWatch(ranking)">
                                <FontAwesomeIcon :icon="ranking.is_watched ? fasBell : faBell" fixed-width aria-hidden="true" />
                            </button>
                            <button
                                v-if="data.checkNow"
                                type="button"
                                :disabled="!ranking.can_check_any || checking !== null"
                                :aria-label="ctrans('Check :keyword now', { keyword: ranking.keyword })"
                                v-tooltip="ranking.can_check_any
                                    ? ctrans('Check now, about :cost of DataForSEO credit', { cost: usd(costOf(ranking)) })
                                    : (ranking.is_pending ? ctrans('Waiting for the Google result') : ctrans('Already checked today'))"
                                class="shrink-0 rounded text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)] enabled:hover:text-gray-500 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="checkNow(ranking)">
                                <FontAwesomeIcon icon="fal fa-sync-alt" fixed-width aria-hidden="true" :spin="checking === ranking.id" />
                            </button>
                        </div>
                        <div class="flex items-center gap-1.5 whitespace-nowrap text-xs text-gray-500">
                            <button
                                v-if="ranking.other_countries.length"
                                type="button"
                                class="flex items-center gap-1 rounded text-gray-600 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
                                :aria-expanded="!!expandedKeywords[ranking.id]"
                                v-tooltip="expandedKeywords[ranking.id] ? ctrans('Hide the other countries') : ctrans('Best result shown. :count more countries', { count: ranking.other_countries.length })"
                                @click="toggleCountries(ranking)">
                                <FontAwesomeIcon :icon="expandedKeywords[ranking.id] ? faChevronDown : faChevronRight" fixed-width aria-hidden="true" />
                                <span>{{ countryName(ranking.country_code) }} {{ ctrans("+:count", { count: ranking.other_countries.length }) }}</span>
                            </button>
                            <span v-else>{{ countryName(ranking.country_code) }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ frequencyLabels[ranking.frequency] ?? ranking.frequency }}</span>
                        </div>
                    </template>

                    <template #cell(position)="{ item: ranking }: { item: RankingRow }">
                        <span class="whitespace-nowrap" :class="ranking.position !== null ? 'font-medium tabular-nums text-gray-900' : 'text-xs text-gray-500'">{{ positionText(ranking) }}</span>
                    </template>

                    <template #cell(change)="{ item: ranking }: { item: RankingRow }">
                        <span class="whitespace-nowrap">
                            <span v-if="change(ranking)" class="tabular-nums" :class="change(ranking)!.class">{{ change(ranking)!.text }}</span>
                            <span v-else class="text-gray-400">-</span>
                            <span v-if="ranking.alert" class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-xs text-red-700">{{ alertLabels[ranking.alert] ?? ranking.alert }}</span>
                        </span>
                    </template>

                    <template #cell(search_console_position)="{ item: ranking }: { item: RankingRow }">
                        <span class="tabular-nums text-gray-700">{{ ranking.search_console_position === null ? "-" : locale.number(ranking.search_console_position) }}</span>
                    </template>

                    <template #cell(ranking_url)="{ item: ranking }: { item: RankingRow }">
                        <a
                            v-if="ranking.ranking_url"
                            :href="ranking.ranking_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block max-w-xs truncate text-[var(--app-accent)] underline-offset-2 hover:underline focus-visible:underline"
                            :title="ranking.ranking_url">
                            {{ urlPath(ranking.ranking_url) }}
                        </a>
                        <span v-else class="text-gray-400">-</span>
                    </template>

                    <template #cell(avg_monthly_searches)="{ item: ranking }: { item: RankingRow }">
                        <span class="tabular-nums">{{ ranking.avg_monthly_searches === null ? "-" : locale.number(ranking.avg_monthly_searches) }}</span>
                    </template>

                    <template #cell(keyword_difficulty)="{ item: ranking }: { item: RankingRow }">
                        <span v-if="ranking.keyword_difficulty === null" class="text-gray-400" v-tooltip="ctrans('No data')">-</span>
                        <span v-else class="tabular-nums">{{ ranking.keyword_difficulty }}</span>
                    </template>

                    <template #cell(tags)="{ item: ranking }: { item: RankingRow }">
                        <div v-if="ranking.tags.length" class="flex gap-1">
                            <span v-for="keywordTag in ranking.tags" :key="keywordTag.id" class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ keywordTag.name }}</span>
                        </div>
                        <span v-else class="text-gray-400">-</span>
                    </template>

                    <template #cell(intent)="{ item: ranking }: { item: RankingRow }">
                        <span class="whitespace-nowrap">{{ ranking.intent ? intentLabels[ranking.intent] ?? ranking.intent : "-" }}</span>
                    </template>

                    <template #cell(serp_features)="{ item: ranking }: { item: RankingRow }">
                        <div class="flex gap-1">
                            <span
                                v-for="feature in ranking.serp_features"
                                :key="feature"
                                class="whitespace-nowrap rounded px-1.5 py-0.5 text-xs"
                                :class="isFeatureOurs(ranking, feature) ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700'"
                                v-tooltip="isFeatureOurs(ranking, feature) ? ctrans('Shows our website') : ctrans('Does not show our website')">
                                {{ featureLabel(feature) }}
                            </span>
                        </div>
                    </template>

                    <template #cell(competitor_positions)="{ item: ranking }: { item: RankingRow }">
                        <ul v-if="ranking.competitor_positions.some((competitor) => competitor.position !== null)" class="space-y-0.5 text-xs">
                            <li v-for="competitor in ranking.competitor_positions.filter((competitor) => competitor.position !== null)" :key="competitor.domain" class="flex justify-between gap-3 whitespace-nowrap">
                                <span class="text-gray-600">{{ competitor.label || competitor.domain }}</span>
                                <span class="tabular-nums text-gray-900">{{ competitor.position }}</span>
                            </li>
                        </ul>
                        <span v-else class="text-gray-400">-</span>
                    </template>

                    <template #expandRow="{ item }">
                        <div
                            v-if="records[item.rowIndex]?.other_countries.length && expandedKeywords[records[item.rowIndex].id]"
                            class="border-y border-gray-200 bg-gray-50 px-4 py-2">
                            <table class="w-full whitespace-nowrap text-xs">
                                <thead>
                                    <tr class="text-gray-500">
                                        <th scope="col" class="py-1 pl-6 text-left font-medium">{{ ctrans("Country") }}</th>
                                        <th scope="col" class="py-1 pr-3 text-right font-medium">{{ ctrans("Position") }}</th>
                                        <th scope="col" class="py-1 pr-3 text-right font-medium">{{ ctrans("Change") }}</th>
                                        <th scope="col" class="py-1 pr-3 text-left font-medium">{{ ctrans("Ranking page") }}</th>
                                        <th scope="col" class="py-1 pr-3 text-right font-medium">{{ ctrans("Monthly searches") }}</th>
                                        <th scope="col" class="py-1 pr-3 text-left font-medium">{{ ctrans("On the results page") }}</th>
                                        <th scope="col" class="py-1" />
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="other in records[item.rowIndex].other_countries" :key="other.id">
                                        <td class="py-1.5 pl-6 text-gray-700">
                                            <button
                                                type="button"
                                                class="text-left underline-offset-2 hover:underline focus-visible:underline"
                                                v-tooltip="ctrans('Position history')"
                                                @click="historyFor = other">
                                                {{ countryName(other.country_code) }}
                                            </button>
                                            <span class="ml-1 text-gray-500">{{ frequencyLabels[other.frequency] ?? other.frequency }}</span>
                                        </td>
                                        <td class="py-1.5 pr-3 text-right">
                                            <span :class="other.position !== null ? 'font-medium tabular-nums text-gray-900' : 'text-gray-500'">{{ positionText(other) }}</span>
                                        </td>
                                        <td class="py-1.5 pr-3 text-right">
                                            <span v-if="change(other)" class="tabular-nums" :class="change(other)!.class">{{ change(other)!.text }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                            <span v-if="other.alert" class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-red-700">{{ alertLabels[other.alert] ?? other.alert }}</span>
                                        </td>
                                        <td class="py-1.5 pr-3">
                                            <a
                                                v-if="other.ranking_url"
                                                :href="other.ranking_url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="block max-w-xs truncate text-[var(--app-accent)] underline-offset-2 hover:underline focus-visible:underline"
                                                :title="other.ranking_url">
                                                {{ urlPath(other.ranking_url) }}
                                            </a>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="py-1.5 pr-3 text-right tabular-nums text-gray-700">{{ other.avg_monthly_searches === null ? "-" : locale.number(other.avg_monthly_searches) }}</td>
                                        <td class="py-1.5 pr-3">
                                            <div class="flex gap-1">
                                                <span
                                                    v-for="feature in other.serp_features"
                                                    :key="feature"
                                                    class="whitespace-nowrap rounded px-1.5 py-0.5"
                                                    :class="isFeatureOurs(other, feature) ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-700'">
                                                    {{ featureLabel(feature) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-1.5 pr-2 text-right">
                                            <span class="inline-flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    :aria-pressed="other.is_watched"
                                                    :disabled="watching === other.id"
                                                    :aria-label="other.is_watched ? ctrans('Stop watching :keyword', { keyword: countryName(other.country_code) }) : ctrans('Watch :keyword', { keyword: countryName(other.country_code) })"
                                                    v-tooltip="other.is_watched ? ctrans('You are told when it falls. Click to stop.') : ctrans('Watch: be told by notification and email when it leaves the top 10 or drops :places places', { places: 5 })"
                                                    class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
                                                    :class="other.is_watched ? 'text-[var(--app-accent)]' : 'text-gray-300 hover:text-gray-500'"
                                                    @click="toggleWatch(other)">
                                                    <FontAwesomeIcon :icon="other.is_watched ? fasBell : faBell" fixed-width aria-hidden="true" />
                                                </button>
                                                <button
                                                    v-if="data.checkNow"
                                                    type="button"
                                                    :disabled="!other.can_check_now || checking !== null"
                                                    :aria-label="ctrans('Check :keyword now', { keyword: countryName(other.country_code) })"
                                                    v-tooltip="other.can_check_now
                                                        ? ctrans('Check now, about :cost of DataForSEO credit', { cost: usd(costOf(other)) })
                                                        : (other.is_pending ? ctrans('Waiting for the Google result') : ctrans('Already checked today'))"
                                                    class="rounded text-gray-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)] enabled:hover:text-gray-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                    @click="checkNow(other)">
                                                    <FontAwesomeIcon icon="fal fa-sync-alt" fixed-width aria-hidden="true" :spin="checking === other.id" />
                                                </button>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </Table>
            </div>
        </template>

        <Dialog :visible="historyFor !== null" modal :header="historyFor ? ctrans('Google position of :keyword', { keyword: historyFor.keyword }) : ''" :style="{ width: 'min(56rem, 95vw)' }" @update:visible="(visible: boolean) => { if (!visible) historyFor = null }">
            <SeoKeywordHistoryChart v-if="historyFor" :key="historyFor.id" :historyRoute="historyFor.history_route" />
        </Dialog>
    </div>
</template>
