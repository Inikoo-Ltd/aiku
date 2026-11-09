<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "primevue/tag"
import Popover from "primevue/popover"
import InputNumber from "primevue/inputnumber"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import { featureLabel } from "@/Components/Seo/serpFeatures"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faTimes } from "@fal"
import type { SeoKeywordOptions, SeoKeywordRoutes, SeoResearchQuery } from "@/Components/Seo/types"

type MonthlySearches = { year: number, month: number, searches: number }

type KeywordIdea = {
    keyword: string
    avg_monthly_searches: number | null
    monthly_searches: MonthlySearches[]
    competition: string | null
    cpc: number | null
    keyword_difficulty: number | null
    intent: string | null
    secondary_intents: string[]
    serp_features: string[]
    serp_updated_at: string | null
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
    keyword_data: { ideas: KeywordIdea[], error: string | null }
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
const intentFilter = ref("all")
const firstRow = ref(0)

type WordRange = { min: number | null, max: number | null }

const wordFilter = ref<WordRange>({ min: null, max: null })
const wordDraft = ref<WordRange>({ min: null, max: null })
const wordPopover = ref<InstanceType<typeof Popover> | null>(null)

const wordCount = (keyword: string) => keyword.trim().split(/\s+/).filter(Boolean).length

const matchesWords = (keyword: string, range: WordRange) => {
    const words = wordCount(keyword)

    return (range.min === null || words >= range.min) && (range.max === null || words <= range.max)
}

const isWordFilterOn = computed(() => wordFilter.value.min !== null || wordFilter.value.max !== null)

const wordFilterLabel = computed(() => {
    const { min, max } = wordFilter.value

    if (min !== null && min === max) {
        return ctrans("Words: :count", { count: min })
    }

    if (min !== null && max !== null) {
        return ctrans("Words: :min to :max", { min, max })
    }

    return min !== null ? ctrans("Words: :min or more", { min }) : ctrans("Words: up to :max", { max })
})

const openWordFilter = (event: Event) => {
    wordDraft.value = { ...wordFilter.value }
    wordPopover.value?.toggle(event)
}

const applyWordFilter = () => {
    const { min, max } = wordDraft.value

    wordFilter.value = min !== null && max !== null && min > max ? { min: max, max: min } : { min, max }
    wordPopover.value?.hide()
}

const clearWordFilter = () => {
    wordFilter.value = { min: null, max: null }
    wordPopover.value?.hide()
}

watch([intentFilter, wordFilter], () => firstRow.value = 0)

const guessScope = (url: string) => {
    const value = url.trim()
    let path = ""

    try {
        path = new URL(value.includes("://") ? value : `https://${value}`).pathname
    } catch {
        return "root_domain"
    }

    if (!value || path.replace(/^\/+|\/+$/g, "") === "") {
        return "root_domain"
    }

    return path.endsWith("/") ? "subfolder" : "exact_url"
}

watch(() => form.url, (url, previousUrl) => {
    if (form.scope === guessScope(previousUrl ?? "")) {
        form.scope = guessScope(url)
    }
})

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
        frequency: "on_request",
    }, {
        preserveScroll: true,
        only: ["research"],
        onStart: () => trackingKeyword.value = keyword,
        onFinish: () => trackingKeyword.value = null,
    })
}

const intentLabels: Record<string, string> = {
    informational: ctrans("Informational"),
    navigational: ctrans("Navigational"),
    commercial: ctrans("Commercial"),
    transactional: ctrans("Transactional"),
}

const intentOptions = computed(() => [
    { value: "all", label: ctrans("All") },
    ...Object.entries(intentLabels).map(([value, label]) => ({
        value,
        label: `${label} (${wordMatchedIdeas.value.filter((idea) => idea.intent === value).length})`,
    })),
])

const wordMatchedIdeas = computed(() => (props.data?.keyword_data.ideas ?? [])
    .filter((idea) => matchesWords(idea.keyword, wordFilter.value)))

const ideas = computed(() => wordMatchedIdeas.value
    .filter((idea) => intentFilter.value === "all" || idea.intent === intentFilter.value)
    .map((idea) => ({ ...idea, words: wordCount(idea.keyword) })))

const competitionLabels: Record<string, string> = {
    LOW: ctrans("Low"),
    MEDIUM: ctrans("Medium"),
    HIGH: ctrans("High"),
}

const tablePt = {
    pcPaginator: { root: { class: "border-t border-gray-100" } },
}

const numericColumnPt = {
    columnHeaderContent: { class: "justify-end" },
    bodyCell: { class: "!text-right tabular-nums" },
}

const centeredColumnPt = {
    columnHeaderContent: { class: "justify-center" },
    bodyCell: { class: "!text-center" },
}

const competitionSeverities: Record<string, string> = {
    LOW: "success",
    MEDIUM: "warn",
    HIGH: "danger",
}

const difficultyClass = (difficulty: number) => difficulty >= 70 ? "text-red-700" : difficulty >= 40 ? "text-amber-700" : "text-green-700"

const trendBars = (idea: KeywordIdea) => {
    const months = idea.monthly_searches.slice(-12)
    const max = Math.max(1, ...months.map((month) => month.searches))

    return months.map((month) => ({
        key: `${month.year}-${month.month}`,
        height: Math.max(2, Math.round(month.searches / max * 20)),
        title: `${month.month}/${month.year}: ${locale.number(month.searches)}`,
    }))
}
</script>

<template>
    <div class="space-y-4 px-4 py-4">
        <form class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end" @submit.prevent="search">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Keywords") }}</span>
                <InputText v-model="form.seed" class="h-10 w-full" :placeholder="ctrans('Up to 5, comma separated, for example: incense sticks, aroma oil')" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Or a domain or URL") }}</span>
                <span class="flex gap-2">
                    <InputText v-model="form.url" class="h-10 min-w-0 flex-1" :placeholder="ctrans('Lists the keywords it ranks for, for example: aw-dropship.es')" />
                    <Select v-model="form.scope" class="h-10 w-36 shrink-0 items-center" :options="options.scopes" optionLabel="label" optionValue="value" :aria-label="ctrans('Scope')" />
                </span>
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Country") }}</span>
                <Select v-model="form.country_code" class="h-10 w-full items-center" :options="options.countries" optionLabel="label" optionValue="value" filter />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Language") }}</span>
                <Select v-model="form.language_code" class="h-10 w-full items-center" :options="options.languages" optionLabel="label" optionValue="value" filter />
            </label>
            <Button class="h-10 justify-center" type="primary" :label="ctrans('Search')" icon="fal fa-search" :loading="isSearching" @click="search" />
        </form>

        <p v-if="!data" class="text-sm text-gray-600">
            {{ ctrans("Enter keywords or a URL to see how often people search for them, how hard they are to rank for, and the longer keywords that contain them. Each search costs a few cents of DataForSEO credit; the same search is free again for 24 hours.") }}
        </p>

        <template v-else>
            <section class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Keyword data')">
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-gray-100 px-5 py-3">
                    <div>
                        <h2 class="text-sm font-medium text-gray-900">{{ ctrans("Keyword ideas") }}</h2>
                        <p class="text-xs text-gray-500">{{ ctrans(":count keywords, Google search data from DataForSEO", { count: locale.number(data.keyword_data.ideas.length) }) }}</p>
                    </div>
                    <div v-if="data.keyword_data.ideas.length" class="flex flex-wrap items-center gap-2">
                        <SegmentedToggle v-model="intentFilter" :options="intentOptions" :ariaLabel="ctrans('Search intent')" />
                        <button
                            type="button"
                            class="inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
                            :class="isWordFilterOn ? 'border-gray-500 bg-gray-50 font-medium text-gray-900' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                            aria-haspopup="dialog"
                            @click="openWordFilter">
                            {{ ctrans("Words") }}
                            <FontAwesomeIcon :icon="faChevronDown" class="text-xs text-gray-500" fixed-width aria-hidden="true" />
                        </button>
                        <SeoExportButton
                            table="research"
                            :extra="{ intent: intentFilter === 'all' ? null : intentFilter, words_min: wordFilter.min, words_max: wordFilter.max }"
                            :tooltip="ctrans('Download these keyword ideas as an Excel file, with the intent and word count chosen here')" />
                    </div>
                </div>

                <div v-if="isWordFilterOn" class="flex flex-wrap items-center gap-2 border-b border-gray-100 px-5 py-2 text-xs">
                    <span class="font-medium text-gray-700">{{ ctrans(":count keywords match", { count: locale.number(ideas.length) }) }}</span>
                    <span class="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white py-0.5 pl-2.5 pr-1 text-gray-700">
                        {{ wordFilterLabel }}
                        <button
                            type="button"
                            class="flex h-5 w-5 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
                            :aria-label="ctrans('Remove filter :filter', { filter: wordFilterLabel })"
                            @click="clearWordFilter">
                            <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                        </button>
                    </span>
                </div>

                <Popover ref="wordPopover">
                    <form class="w-72 space-y-3 text-sm" @submit.prevent="applyWordFilter">
                        <p class="font-medium text-gray-700">{{ ctrans("Number of words in the keyword") }}</p>
                        <div class="flex items-center gap-2">
                            <InputNumber v-model="wordDraft.min" :min="1" :max="20" :useGrouping="false" :placeholder="ctrans('From')" :aria-label="ctrans('At least this many words')" inputClass="w-full" class="flex-1" />
                            <span class="text-gray-500" aria-hidden="true">{{ ctrans("to") }}</span>
                            <InputNumber v-model="wordDraft.max" :min="1" :max="20" :useGrouping="false" :placeholder="ctrans('To')" :aria-label="ctrans('At most this many words')" inputClass="w-full" class="flex-1" />
                        </div>
                        <p class="text-xs text-gray-600">{{ ctrans("Use the same number in both for an exact count, or fill only one side.") }}</p>
                        <div class="flex justify-end gap-2">
                            <Button type="tertiary" size="xs" :label="ctrans('Clear')" @click="clearWordFilter" />
                            <Button type="primary" size="xs" :label="ctrans('Apply')" @click="applyWordFilter" />
                        </div>
                    </form>
                </Popover>

                <div v-if="data.keyword_data.error" role="alert" class="px-5 py-4 text-sm text-gray-700">
                    <p class="font-medium text-gray-900">{{ ctrans("DataForSEO did not answer.") }}</p>
                    <p class="mt-1">{{ data.keyword_data.error }}</p>
                </div>

                <p v-else-if="!data.keyword_data.ideas.length" class="px-5 py-4 text-sm text-gray-600">
                    {{ ctrans("No keyword data for this search.") }}
                </p>

                <DataTable
                    v-else
                    v-model:first="firstRow"
                    :value="ideas"
                    dataKey="keyword"
                    paginator
                    :rows="25"
                    :rowsPerPageOptions="[25, 50, 100]"
                    removableSort
                    size="small"
                    class="text-sm"
                    :pt="tablePt">
                    <Column field="keyword" :header="ctrans('Keyword')" sortable />
                    <Column field="words" sortable :pt="numericColumnPt">
                        <template #header>
                            <span v-tooltip="ctrans('Number of words in the keyword')">{{ ctrans("Words") }}</span>
                        </template>
                    </Column>
                    <Column field="intent" :header="ctrans('Intent')" sortable>
                        <template #body="{ data: idea }">
                            <span v-if="idea.intent" v-tooltip="idea.secondary_intents.length ? ctrans('Also: :intents', { intents: idea.secondary_intents.map((intent: string) => intentLabels[intent] ?? intent).join(', ') }) : undefined">
                                {{ intentLabels[idea.intent] ?? idea.intent }}
                            </span>
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column field="avg_monthly_searches" sortable :pt="numericColumnPt">
                        <template #header>
                            <span v-tooltip="ctrans('Average monthly Google searches over the last 12 months, from Google Ads data')">{{ ctrans("Monthly searches") }}</span>
                        </template>
                        <template #body="{ data: idea }">
                            {{ idea.avg_monthly_searches === null ? "-" : locale.number(idea.avg_monthly_searches) }}
                        </template>
                    </Column>
                    <Column :header="ctrans('Last 12 months')">
                        <template #body="{ data: idea }">
                            <div class="flex h-5 items-end gap-px" aria-hidden="true">
                                <span v-for="bar in trendBars(idea)" :key="bar.key" :title="bar.title" class="w-1.5 rounded-sm bg-[var(--app-accent-muted)]" :style="{ height: `${bar.height}px` }" />
                            </div>
                        </template>
                    </Column>
                    <Column field="keyword_difficulty" sortable :pt="numericColumnPt">
                        <template #header>
                            <span v-tooltip="ctrans('How hard it is to reach the top 10 organically, 1 to 100, estimated by DataForSEO from the pages that rank now. A dash means DataForSEO has no estimate')">{{ ctrans("Difficulty") }}</span>
                        </template>
                        <template #body="{ data: idea }">
                            <span v-if="idea.keyword_difficulty !== null" :class="difficultyClass(idea.keyword_difficulty)">{{ idea.keyword_difficulty }}</span>
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column field="cpc" sortable :pt="numericColumnPt">
                        <template #header>
                            <span v-tooltip="ctrans('Average cost per click advertisers paid, in USD')">{{ ctrans("CPC") }}</span>
                        </template>
                        <template #body="{ data: idea }">
                            {{ idea.cpc === null ? "-" : locale.number(idea.cpc) }}
                        </template>
                    </Column>
                    <Column>
                        <template #header>
                            <span v-tooltip="ctrans('What Google shows on the results page for this keyword besides the ten blue links. The snapshot comes from DataForSEO, so it can be some months old')">{{ ctrans("SERP features") }}</span>
                        </template>
                        <template #body="{ data: idea }">
                            <div v-if="idea.serp_features?.length" class="flex max-w-xs flex-wrap gap-1" v-tooltip="idea.serp_updated_at ? ctrans('Results page seen on :date', { date: idea.serp_updated_at }) : undefined">
                                <Tag v-for="feature in idea.serp_features" :key="feature" :value="featureLabel(feature)" severity="secondary" />
                            </div>
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column field="competition" :pt="centeredColumnPt">
                        <template #header>
                            <span v-tooltip="ctrans('How many advertisers bid on the keyword. Not the same as how hard it is to rank organically')">{{ ctrans("Ad competition") }}</span>
                        </template>
                        <template #body="{ data: idea }">
                            <Tag v-if="idea.competition" :value="competitionLabels[idea.competition] ?? idea.competition" :severity="competitionSeverities[idea.competition] ?? 'secondary'" />
                            <span v-else>-</span>
                        </template>
                    </Column>
                    <Column :pt="{ bodyCell: { class: '!text-right' } }">
                        <template #body="{ data: idea }">
                            <span v-if="idea.is_tracked" class="text-xs text-gray-500">{{ ctrans("Tracked") }}</span>
                            <Button v-else-if="canEdit" type="tertiary" size="xs" :label="ctrans('Track')" :loading="trackingKeyword === idea.keyword" @click="track(idea.keyword)" />
                        </template>
                    </Column>
                </DataTable>
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
