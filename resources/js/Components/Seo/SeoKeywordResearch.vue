<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { reactive, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import type { SeoKeywordOptions, SeoKeywordRoutes, SeoResearchQuery } from "@/Components/Seo/types"

type MonthlySearches = { year: number, month: string, searches: number }

type KeywordIdea = {
    keyword: string
    avg_monthly_searches: number | null
    monthly_searches: MonthlySearches[]
    competition: string | null
    low_top_of_page_bid_micros: number | null
    high_top_of_page_bid_micros: number | null
    is_tracked: boolean
}

type SearchConsoleQuery = {
    keyword: string
    clicks: number
    impressions: number
    position: number | null
    is_tracked: boolean
}

type ResearchData = {
    keyword_planner: { account_shop: string | null, ideas: KeywordIdea[], error: string | null }
    search_console: SearchConsoleQuery[]
} | null

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: ResearchData
    canEdit: boolean
    routes: SeoKeywordRoutes
    options: SeoKeywordOptions
    query: SeoResearchQuery
}>()

const locale = useLocaleStore()

const form = reactive({ ...props.query })
const isSearching = ref(false)
const trackingKeyword = ref<string | null>(null)

const search = () => {
    router.get(window.location.pathname, { tab: "research", ...form }, {
        preserveState: true,
        preserveScroll: true,
        only: ["research", "query"],
        onStart: () => isSearching.value = true,
        onFinish: () => isSearching.value = false,
    })
}

const track = (keyword: string) => {
    router.post(route(props.routes.track.name, props.routes.track.parameters), {
        keyword,
        country_code: props.query.country_code,
        language_code: props.query.language_code,
        device: "mobile",
        frequency: "weekly",
    }, {
        preserveScroll: true,
        only: ["research"],
        onStart: () => trackingKeyword.value = keyword,
        onFinish: () => trackingKeyword.value = null,
    })
}

const competitionLabels: Record<string, string> = {
    LOW: ctrans("Low"),
    MEDIUM: ctrans("Medium"),
    HIGH: ctrans("High"),
}

const formatBid = (micros: number | null) => micros === null ? null : locale.number(Math.round(micros / 10000) / 100)

const bidRange = (idea: KeywordIdea) => {
    const low = formatBid(idea.low_top_of_page_bid_micros)
    const high = formatBid(idea.high_top_of_page_bid_micros)

    return low && high ? `${low} - ${high}` : low ?? high ?? "-"
}

const trendBars = (idea: KeywordIdea) => {
    const months = idea.monthly_searches.slice(-12)
    const max = Math.max(1, ...months.map((month) => month.searches))

    return months.map((month) => ({
        key: `${month.year}-${month.month}`,
        height: Math.max(2, Math.round(month.searches / max * 20)),
        title: `${month.month} ${month.year}: ${locale.number(month.searches)}`,
    }))
}
</script>

<template>
    <div class="space-y-4 px-4 py-4">
        <form class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 lg:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end" @submit.prevent="search">
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Keywords") }}</span>
                <PureInput v-model="form.seed" class="mt-1" :placeholder="ctrans('Comma separated, for example: incense sticks, aroma oil')" />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Or a URL") }}</span>
                <PureInput v-model="form.url" class="mt-1" :placeholder="ctrans('A page to take keywords from')" />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Country") }}</span>
                <PureMultiselect v-model="form.country_code" class="mt-1" :options="options.countries" searchable required />
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Language") }}</span>
                <PureMultiselect v-model="form.language_code" class="mt-1" :options="options.languages" searchable required />
            </label>
            <Button type="primary" :label="ctrans('Search')" icon="fal fa-search" :loading="isSearching" @click="search" />
        </form>

        <p v-if="!data" class="text-sm text-gray-600">
            {{ ctrans("Enter keywords or a URL to see how often people search for them and which related keywords exist.") }}
        </p>

        <template v-else>
            <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Keyword Planner results')">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Google Ads Keyword Planner") }}</h2>
                    <span v-if="data.keyword_planner.account_shop" class="text-xs text-gray-500">
                        {{ ctrans("Through the Google Ads account of :shop", { shop: data.keyword_planner.account_shop }) }}
                    </span>
                </div>

                <div v-if="data.keyword_planner.error" role="alert" class="px-5 py-4 text-sm text-gray-700">
                    <p class="font-medium text-gray-900">{{ ctrans("Keyword Planner did not answer.") }}</p>
                    <p class="mt-1">{{ data.keyword_planner.error }}</p>
                </div>

                <p v-else-if="!data.keyword_planner.ideas.length" class="px-5 py-4 text-sm text-gray-600">
                    {{ ctrans("Google returned no keyword ideas for this search.") }}
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                                <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Keyword") }}</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('Average monthly Google searches over the last 12 months, from Keyword Planner')">{{ ctrans("Monthly searches") }}</th>
                                <th scope="col" class="px-3 py-2 font-medium">{{ ctrans("Last 12 months") }}</th>
                                <th scope="col" class="px-3 py-2 font-medium" v-tooltip="ctrans('How many advertisers bid on the keyword. Not the same as how hard it is to rank organically')">{{ ctrans("Ad competition") }}</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium" v-tooltip="ctrans('What advertisers paid for the top of the page, in the Google Ads account currency')">{{ ctrans("Top of page bid") }}</th>
                                <th scope="col" class="px-5 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="idea in data.keyword_planner.ideas" :key="idea.keyword">
                                <td class="px-5 py-2 text-gray-900">{{ idea.keyword }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ idea.avg_monthly_searches === null ? "-" : locale.number(idea.avg_monthly_searches) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex h-5 items-end gap-px" aria-hidden="true">
                                        <span v-for="bar in trendBars(idea)" :key="bar.key" :title="bar.title" class="w-1.5 rounded-sm bg-[--app-accent-muted]" :style="{ height: `${bar.height}px` }" />
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ idea.competition ? competitionLabels[idea.competition] ?? idea.competition : "-" }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-700">{{ bidRange(idea) }}</td>
                                <td class="px-5 py-2 text-right">
                                    <span v-if="idea.is_tracked" class="text-xs text-gray-500">{{ ctrans("Tracked") }}</span>
                                    <Button v-else-if="canEdit" type="tertiary" size="xs" :label="ctrans('Track')" :loading="trackingKeyword === idea.keyword" @click="track(idea.keyword)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Search Console queries')">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-gray-100 px-5 py-3">
                    <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Your Search Console queries with these words") }}</h2>
                    <span class="text-xs text-gray-500">{{ ctrans("Last 90 days, impressions are not search volume") }}</span>
                </div>

                <p v-if="!data.search_console.length" class="px-5 py-4 text-sm text-gray-600">
                    {{ ctrans("No Google Search query of this website contains these words in the last 90 days.") }}
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                                <th scope="col" class="px-5 py-2 font-medium">{{ ctrans("Query") }}</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Impressions") }}</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Clicks") }}</th>
                                <th scope="col" class="px-3 py-2 text-right font-medium">{{ ctrans("Position") }}</th>
                                <th scope="col" class="px-5 py-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in data.search_console" :key="row.keyword">
                                <td class="px-5 py-2 text-gray-900">{{ row.keyword }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(row.impressions) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ locale.number(row.clicks) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ row.position === null ? "-" : locale.number(row.position) }}</td>
                                <td class="px-5 py-2 text-right">
                                    <span v-if="row.is_tracked" class="text-xs text-gray-500">{{ ctrans("Tracked") }}</span>
                                    <Button v-else-if="canEdit" type="tertiary" size="xs" :label="ctrans('Track')" :loading="trackingKeyword === row.keyword" @click="track(row.keyword)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>
