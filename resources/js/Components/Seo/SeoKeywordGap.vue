<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Column from "primevue/column"
import DataTable from "primevue/datatable"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import SeoDomainPicker from "@/Components/Seo/SeoDomainPicker.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type GapRow = {
    keyword: string
    intent: string | null
    search_volume: number | null
    keyword_difficulty: number | null
    positions: Record<string, number | null>
    our_url: string | null
    groups: string[]
}

type GapData = {
    competitors: string[]
    domains: string[]
    max: number
    parameter: string
    result: { domains: string[], counts: Record<string, number>, rows: GapRow[] } | null
    error: string | null
    tracked: string[]
    trackRoute: routeType | null
    market: { country_code: string, language_code: string }
}

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    data?: GapData | null
    tab: string
    market: { domain: string, country: string, language: string } | null
}>()

const locale = useLocaleStore()

const groupLabels: Record<string, { label: string, hint: string }> = {
    missing: { label: ctrans("Missing"), hint: ctrans("Every other domain ranks for it and we do not") },
    untapped: { label: ctrans("Untapped"), hint: ctrans("At least one other domain ranks for it and we do not") },
    weak: { label: ctrans("Weak"), hint: ctrans("Every domain ranks for it and we are the lowest") },
    strong: { label: ctrans("Strong"), hint: ctrans("We rank higher than every other domain that ranks") },
    shared: { label: ctrans("Shared"), hint: ctrans("Every domain ranks for it") },
    unique: { label: ctrans("Unique"), hint: ctrans("Only we rank for it") },
}

const group = ref("missing")
const intent = ref<string | null>(null)
const bestPosition = ref<number | null>(null)
const firstRow = ref(0)

watch([group, intent, bestPosition], () => firstRow.value = 0)

const groupOptions = computed(() => Object.entries(groupLabels).map(([value, { label }]) => ({
    value,
    label: `${label} (${locale.number(props.data?.result?.counts[value] ?? 0)})`,
})))

const intentLabels: Record<string, string> = {
    informational: ctrans("Informational"),
    navigational: ctrans("Navigational"),
    commercial: ctrans("Commercial"),
    transactional: ctrans("Transactional"),
}

const intentOptions = [
    { value: null, label: ctrans("Every intent") },
    ...Object.entries(intentLabels).map(([value, label]) => ({ value, label })),
]

const positionOptions = [
    { value: null, label: ctrans("Any position") },
    { value: 3, label: ctrans("Someone in the top 3") },
    { value: 10, label: ctrans("Someone in the top 10") },
    { value: 20, label: ctrans("Someone in the top 20") },
]

const ourDomain = computed(() => props.data?.result?.domains[0] ?? "")

const rows = computed(() => (props.data?.result?.rows ?? []).filter((row) =>
    row.groups.includes(group.value)
    && (intent.value === null || row.intent === intent.value)
    && (bestPosition.value === null || Object.values(row.positions).some((position) => position !== null && position <= bestPosition.value!))))

const trackedKeywords = ref<Set<string>>(new Set(props.data?.tracked ?? []))
const trackingKeyword = ref<string | null>(null)

watch(() => props.data?.tracked, (tracked) => trackedKeywords.value = new Set(tracked ?? []))

const track = (keyword: string) => {
    if (!props.data?.trackRoute) {
        return
    }

    router.post(route(props.data.trackRoute.name, props.data.trackRoute.parameters), {
        keyword,
        country_code: props.data.market.country_code,
        language_code: props.data.market.language_code,
        device: "mobile",
        frequency: "weekly",
    }, {
        preserveScroll: true,
        preserveState: true,
        only: [props.tab],
        onStart: () => trackingKeyword.value = keyword,
        onSuccess: () => trackedKeywords.value = new Set([...trackedKeywords.value, keyword]),
        onFinish: () => trackingKeyword.value = null,
    })
}

const numericPt = { columnHeaderContent: { class: "justify-end" }, bodyCell: { class: "!text-right tabular-nums" } }
</script>

<template>
    <div v-if="data" class="space-y-4 px-4 py-4">
        <SeoDomainPicker
            :tab="tab"
            :parameter="data.parameter"
            :competitors="data.competitors"
            :domains="data.domains"
            :max="data.max"
            :label="ctrans('Find the gap')" />

        <p v-if="data.error" role="alert" class="rounded-xl bg-white px-5 py-4 text-sm text-red-700 ring-1 ring-gray-200">{{ data.error }}</p>

        <p v-if="!data.result" class="text-sm text-gray-600">
            {{ ctrans("Pick up to :max competitors to compare their Google keywords in :country with :domain: the top 100 positions of each, up to 1,000 keywords per domain with the highest volume. A domain not fetched in the last four weeks costs about 15 cents of DataForSEO credit.", { max: data.max, country: market?.country ?? "", domain: market?.domain ?? "" }) }}
        </p>

        <section v-else class="rounded-xl bg-white ring-1 ring-gray-200" :aria-label="ctrans('Keyword gap')">
            <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-5 py-3">
                <SegmentedToggle v-model="group" :options="groupOptions" :ariaLabel="ctrans('Keyword group')" />
                <Select v-model="intent" :options="intentOptions" optionLabel="label" optionValue="value" class="h-9 items-center" :aria-label="ctrans('Search intent')" />
                <Select v-model="bestPosition" :options="positionOptions" optionLabel="label" optionValue="value" class="h-9 items-center" :aria-label="ctrans('Position')" />
                <SeoExportButton class="ml-auto" table="keyword_gap" :extra="{ gap_domains: data.domains.join(','), group, intent, best: bestPosition }" />
            </div>
            <p class="border-b border-gray-100 px-5 py-2 text-xs text-gray-500">{{ groupLabels[group].hint }}. {{ ctrans(":count keywords", { count: locale.number(rows.length) }) }}</p>

            <p v-if="!rows.length" class="px-5 py-4 text-sm text-gray-600">{{ ctrans("No keyword matches these filters.") }}</p>

            <DataTable
                v-else
                v-model:first="firstRow"
                :value="rows"
                dataKey="keyword"
                paginator
                :rows="25"
                :rowsPerPageOptions="[25, 50, 100]"
                removableSort
                size="small"
                class="text-sm">
                <Column field="keyword" :header="ctrans('Keyword')" sortable />
                <Column field="intent" :header="ctrans('Intent')" sortable>
                    <template #body="{ data: row }">{{ row.intent ? intentLabels[row.intent] ?? row.intent : "-" }}</template>
                </Column>
                <Column field="search_volume" :header="ctrans('Monthly searches')" sortable :pt="numericPt">
                    <template #body="{ data: row }">{{ row.search_volume === null ? "-" : locale.number(row.search_volume) }}</template>
                </Column>
                <Column field="keyword_difficulty" :header="ctrans('Difficulty')" sortable :pt="numericPt">
                    <template #body="{ data: row }">{{ row.keyword_difficulty ?? "-" }}</template>
                </Column>
                <Column v-for="domain in data.result.domains" :key="domain" :header="domain" :pt="numericPt">
                    <template #body="{ data: row }">
                        <span v-if="row.positions[domain] !== null" :class="domain === ourDomain ? 'font-medium text-gray-900' : 'text-gray-700'">{{ row.positions[domain] }}</span>
                        <span v-else class="text-gray-300">-</span>
                    </template>
                </Column>
                <Column :pt="{ bodyCell: { class: '!text-right' } }">
                    <template #body="{ data: row }">
                        <span v-if="trackedKeywords.has(row.keyword)" class="text-xs text-gray-500">{{ ctrans("Tracked") }}</span>
                        <Button v-else-if="data.trackRoute" type="tertiary" size="xs" :label="ctrans('Track')" :loading="trackingKeyword === row.keyword" @click="track(row.keyword)" />
                    </template>
                </Column>
            </DataTable>
        </section>
    </div>
</template>
