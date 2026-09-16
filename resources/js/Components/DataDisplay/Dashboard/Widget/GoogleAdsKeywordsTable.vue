<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSort, faSortUp, faSortDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import GoogleAdsElementToggle from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsElementToggle.vue"
import GoogleAdsPager from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsPager.vue"
import { useLocalPagination } from "@/Composables/useLocalPagination"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"

library.add(faSort, faSortUp, faSortDown)

type Metrics = {
    impressions: number
    clicks: number
    cost: number
    avg_cpc: number | null
    conversions: number
    conversions_value: number
}

type Keyword = {
    id?: string
    text: string | null
    match_type: string | null
    status: string | null
    quality_score?: number | null
    metrics?: Metrics | null
}

type AdGroup = { id: string; name: string | null; keywords: Keyword[] }

type Row = Keyword & { adGroupId: string; adGroupName: string }

/**
 * Every keyword in the campaign as one list rather than one table per ad group: the questions a
 * marketer brings here, which keywords cost the most and which have a quality score worth fixing,
 * are asked across the campaign, and a Search campaign here carries six hundred keywords over sixty
 * ad groups. The ad group travels as a column so nothing is lost by flattening.
 *
 * Figures are the ones the nightly fetch read for its own window, not the period chosen at the top of
 * the page, which is why the window is named under the table.
 */
const props = defineProps<{
    adGroups: AdGroup[]
    currency: string
    elementRoute: { name: string; parameters: Record<string, unknown> }
    windowLabel: string | null
}>()

const locale = useLocaleStore()

const money = (value: number) => locale.currencyFormat(props.currency, value)
const moneyOrDash = (value: number | null) => (value === null ? "—" : money(value))
const enumLabel = (value: string | null) => (value ? value.replace(/_/g, " ").toLowerCase() : "")

const rows = computed<Row[]>(() =>
    props.adGroups.flatMap((group) =>
        group.keywords.map((keyword) => ({
            ...keyword,
            adGroupId: String(group.id),
            adGroupName: group.name ?? String(group.id),
        }))
    )
)

const hasMetrics = computed(() => rows.value.some((row) => row.metrics))

type SortKey = "text" | "adGroupName" | "match_type" | "status" | "quality_score" | keyof Metrics

const sortKey = ref<SortKey>("cost")
const sortDescending = ref(true)

const filter = ref("")
const onlyUnconverted = ref(false)

const sortIcon = (key: SortKey) => {
    if (sortKey.value !== key) return "fal fa-sort"

    return sortDescending.value ? "fal fa-sort-down" : "fal fa-sort-up"
}

const ariaSort = (key: SortKey) => {
    if (sortKey.value !== key) return "none"

    return sortDescending.value ? "descending" : "ascending"
}

const valueOf = (row: Row, key: SortKey): string | number | null => {
    if (key === "text" || key === "adGroupName" || key === "match_type" || key === "status") return row[key] ?? ""
    if (key === "quality_score") return row.quality_score ?? null

    return row.metrics ? row.metrics[key] : null
}

const filtered = computed(() => {
    const needle = filter.value.trim().toLowerCase()

    return rows.value.filter((row) => {
        if (needle && !(row.text ?? "").toLowerCase().includes(needle) && !row.adGroupName.toLowerCase().includes(needle)) {
            return false
        }

        if (onlyUnconverted.value && !(row.metrics && row.metrics.cost > 0 && row.metrics.conversions === 0)) {
            return false
        }

        return true
    })
})

const sorted = computed(() => {
    const key = sortKey.value
    const direction = sortDescending.value ? -1 : 1

    return [...filtered.value].sort((a, b) => {
        const left = valueOf(a, key)
        const right = valueOf(b, key)

        if (left === null && right === null) return 0
        if (left === null) return 1
        if (right === null) return -1

        if (typeof left === "string" || typeof right === "string") {
            return String(left).localeCompare(String(right)) * direction
        }

        return (left - right) * direction
    })
})

const { perPage, perPageOptions, page, pageCount, total, firstRow, lastRow, paged, isPaged, toFirstPage } =
    useLocalPagination<Row>(sorted, [25, 50, 100])

watch([filter, onlyUnconverted, sortKey, sortDescending], toFirstPage)

const sortBy = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDescending.value = !sortDescending.value
        return
    }

    sortKey.value = key
    sortDescending.value = key !== "text" && key !== "adGroupName" && key !== "match_type" && key !== "status"
}

const textColumns: { key: SortKey; label: string }[] = [
    { key: "text", label: trans("Text") },
    { key: "adGroupName", label: trans("Ad group") },
    { key: "match_type", label: trans("Match") },
    { key: "status", label: trans("Status") },
]

const metricColumns: { key: SortKey; label: string; format: (row: Row) => string; good?: boolean }[] = [
    { key: "impressions", label: trans("Impr."), format: (row) => (row.metrics ? locale.number(row.metrics.impressions) : "—") },
    { key: "clicks", label: trans("Clicks"), format: (row) => (row.metrics ? locale.number(row.metrics.clicks) : "—") },
    { key: "avg_cpc", label: trans("CPC"), format: (row) => (row.metrics ? moneyOrDash(row.metrics.avg_cpc) : "—") },
    { key: "cost", label: trans("Cost"), format: (row) => (row.metrics ? money(row.metrics.cost) : "—") },
    { key: "conversions", label: trans("Conv."), format: (row) => (row.metrics ? locale.number(row.metrics.conversions) : "—"), good: true },
    { key: "conversions_value", label: trans("Conv. value"), format: (row) => (row.metrics ? money(row.metrics.conversions_value) : "—") },
]
</script>

<template>
    <div>
        <div class="mt-3 flex flex-wrap items-end gap-3">
            <div>
                <label for="gads-keyword-filter" class="sr-only">{{ trans("Search keywords and ad groups") }}</label>
                <input
                    id="gads-keyword-filter"
                    v-model="filter"
                    type="search"
                    :placeholder="trans('Search keywords and ad groups')"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-72" />
            </div>

            <label v-if="hasMetrics" class="flex items-center gap-2 pb-2 text-xs text-gray-600">
                <input
                    v-model="onlyUnconverted"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                {{ trans("Only those that cost money and converted nothing") }}
            </label>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-xs" :class="hasMetrics ? 'min-w-[58rem]' : 'min-w-[28rem]'">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-500">
                        <th
                            v-for="column in textColumns"
                            :key="column.key"
                            scope="col"
                            :aria-sort="ariaSort(column.key)"
                            class="px-1 py-0.5 text-left font-normal">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 whitespace-nowrap rounded px-1 py-1 transition hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :class="sortKey === column.key ? 'text-gray-800' : ''"
                                @click="sortBy(column.key)">
                                {{ column.label }}
                                <FontAwesomeIcon :icon="sortIcon(column.key)" fixed-width aria-hidden="true" />
                            </button>
                        </th>
                        <th scope="col" :aria-sort="ariaSort('quality_score')" class="px-1 py-0.5 text-right font-normal">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 whitespace-nowrap rounded px-1 py-1 transition hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :class="sortKey === 'quality_score' ? 'text-gray-800' : ''"
                                @click="sortBy('quality_score')">
                                {{ trans("Quality") }}
                                <FontAwesomeIcon :icon="sortIcon('quality_score')" fixed-width aria-hidden="true" />
                            </button>
                        </th>
                        <template v-if="hasMetrics">
                            <th
                                v-for="column in metricColumns"
                                :key="column.key"
                                scope="col"
                                :aria-sort="ariaSort(column.key)"
                                class="px-1 py-0.5 text-right font-normal">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 whitespace-nowrap rounded px-1 py-1 transition hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                    :class="sortKey === column.key ? 'text-gray-800' : ''"
                                    @click="sortBy(column.key)">
                                    {{ column.label }}
                                    <FontAwesomeIcon :icon="sortIcon(column.key)" fixed-width aria-hidden="true" />
                                </button>
                            </th>
                        </template>
                        <th scope="col" class="py-1.5 pl-2 text-right font-normal">
                            <span class="sr-only">{{ trans("Actions") }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(keyword, i) in paged" :key="keyword.id ?? keyword.adGroupId + i" class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">{{ keyword.text }}</td>
                        <td class="max-w-[14rem] truncate px-2" :title="keyword.adGroupName">{{ keyword.adGroupName }}</td>
                        <td class="px-2 capitalize">{{ enumLabel(keyword.match_type) }}</td>
                        <td class="px-2 capitalize">{{ enumLabel(keyword.status) }}</td>
                        <td
                            class="px-2 text-right tabular-nums"
                            :class="keyword.quality_score === null || keyword.quality_score === undefined ? 'text-gray-400' : keyword.quality_score <= 4 ? 'text-[#d03b3b]' : 'text-gray-600'">
                            {{ keyword.quality_score ?? "—" }}
                        </td>
                        <template v-if="hasMetrics">
                            <td
                                v-for="column in metricColumns"
                                :key="column.key"
                                class="px-2 text-right tabular-nums"
                                :class="column.good && keyword.metrics && keyword.metrics.conversions > 0 ? 'text-[#006300]' : ''">
                                {{ column.format(keyword) }}
                            </td>
                        </template>
                        <td class="whitespace-nowrap pl-2 text-right">
                            <GoogleAdsElementToggle
                                v-if="keyword.id"
                                type="keyword"
                                :ad-group-id="keyword.adGroupId"
                                :element-id="String(keyword.id)"
                                :status="keyword.status"
                                :label="trans('keyword')"
                                :update-route="elementRoute" />
                        </td>
                    </tr>
                    <tr v-if="!paged.length">
                        <td :colspan="hasMetrics ? 12 : 6" class="py-4 text-center text-gray-500">
                            {{ trans("No keyword matches those filters.") }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="hasMetrics && windowLabel" class="mt-1 text-[11px] text-gray-400">{{ windowLabel }}</p>

        <GoogleAdsPager
            v-if="isPaged"
            :first-row="firstRow"
            :last-row="lastRow"
            :total="total"
            :page="page"
            :page-count="pageCount"
            :per-page="perPage"
            :per-page-options="perPageOptions"
            :unit="trans('keywords')"
            @update:page="page = $event"
            @update:per-page="((perPage = $event), toFirstPage())" />
    </div>
</template>
