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
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
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

watch(intentFilter, () => firstRow.value = 0)

const search = () => {
    router.get(window.location.pathname, { tab: "research", ...form }, {
        preserveState: true,
        preserveScroll: true,
        only: ["research", "query", "spend"],
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
        label: `${label} (${props.data?.keyword_data.ideas.filter((idea) => idea.intent === value).length ?? 0})`,
    })),
])

const ideas = computed(() => (props.data?.keyword_data.ideas ?? [])
    .filter((idea) => intentFilter.value === "all" || idea.intent === intentFilter.value))

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
        <form class="grid grid-cols-1 gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 lg:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end" @submit.prevent="search">
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Keywords") }}</span>
                <InputText v-model="form.seed" class="h-10 w-full" :placeholder="ctrans('Up to 5, comma separated, for example: incense sticks, aroma oil')" />
            </label>
            <label class="flex flex-col gap-1 text-sm">
                <span class="font-medium text-gray-700">{{ ctrans("Or a URL") }}</span>
                <InputText v-model="form.url" class="h-10 w-full" :placeholder="ctrans('Lists the keywords the page ranks for')" />
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
                    <SegmentedToggle v-if="data.keyword_data.ideas.length" v-model="intentFilter" :options="intentOptions" :ariaLabel="ctrans('Search intent')" />
                </div>

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
                                <span v-for="bar in trendBars(idea)" :key="bar.key" :title="bar.title" class="w-1.5 rounded-sm bg-[--app-accent-muted]" :style="{ height: `${bar.height}px` }" />
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
