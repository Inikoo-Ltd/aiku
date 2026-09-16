<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import GoogleAdsPager from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsPager.vue"
import { useLocalPagination } from "@/Composables/useLocalPagination"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSort, faSortUp, faSortDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

library.add(faSort, faSortUp, faSortDown)

type Day = {
    date: string
    impressions: number
    clicks: number
    conversions: number
    cost: number
    conversions_value: number
    shop_cost: number
}

type SortKey = keyof Day

/**
 * The day-by-day figures, sorted and paged in the browser. The whole series is already on the page
 * for the chart above, so neither control costs a request; a year of days would otherwise be a
 * thousand-row wall under a chart somebody is reading.
 *
 * Sorting by anything but the date is how the worst day of a month gets found, which is why every
 * column offers it rather than only the date.
 */
const props = defineProps<{
    daily: Day[]
    currency: string
    shopCurrency: string
}>()

const locale = useLocaleStore()

const money = (value: number, currency: string) => locale.currencyFormat(currency, value)

const columns: { key: SortKey; label: string; align: "left" | "right"; format: (day: Day) => string }[] = [
    { key: "date", label: trans("Date"), align: "left", format: (day) => useFormatTime(day.date, { formatTime: "mdy" }) },
    { key: "impressions", label: trans("Impressions"), align: "right", format: (day) => locale.number(day.impressions) },
    { key: "clicks", label: trans("Clicks"), align: "right", format: (day) => locale.number(day.clicks) },
    { key: "conversions", label: trans("Conversions"), align: "right", format: (day) => locale.number(day.conversions) },
    { key: "cost", label: trans("Cost") + " (" + props.currency + ")", align: "right", format: (day) => money(day.cost, props.currency) },
    { key: "shop_cost", label: trans("Spend") + " (" + props.shopCurrency + ")", align: "right", format: (day) => money(day.shop_cost, props.shopCurrency) },
]

const sortKey = ref<SortKey>("date")
const sortDescending = ref(true)

const sortBy = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDescending.value = !sortDescending.value
        return
    }

    sortKey.value = key
    sortDescending.value = true
}

const sortIcon = (key: SortKey) => {
    if (sortKey.value !== key) return "fal fa-sort"

    return sortDescending.value ? "fal fa-sort-down" : "fal fa-sort-up"
}

const ariaSort = (key: SortKey) => {
    if (sortKey.value !== key) return "none"

    return sortDescending.value ? "descending" : "ascending"
}

const sorted = computed(() => {
    const key = sortKey.value
    const direction = sortDescending.value ? -1 : 1

    return [...props.daily].sort((a, b) => {
        if (key === "date") return a.date.localeCompare(b.date) * direction

        return ((a[key] as number) - (b[key] as number)) * direction
    })
})

/* Fifty rather than twenty-five so a month, the default period, never spills its last day or two onto
   a second page and the controls stay out of the way until a quarter or a year is asked for. */
const { perPage, perPageOptions, page, pageCount, total, firstRow, lastRow, paged, isPaged, toFirstPage } =
    useLocalPagination<Day>(sorted, [50, 100, 365])

watch([sortKey, sortDescending], toFirstPage)
</script>

<template>
    <div>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[40rem] text-xs">
                <thead>
                    <tr class="border-b border-gray-100 text-gray-500">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :aria-sort="ariaSort(column.key)"
                            class="px-1 py-0.5 font-normal"
                            :class="column.align === 'left' ? 'text-left' : 'text-right'">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 whitespace-nowrap rounded px-1 py-1 transition hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                :class="sortKey === column.key ? 'text-gray-800' : ''"
                                @click="sortBy(column.key)">
                                {{ column.label }}
                                <FontAwesomeIcon :icon="sortIcon(column.key)" fixed-width aria-hidden="true" />
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="day in paged" :key="day.date" class="border-b border-gray-50 text-gray-600">
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-2 py-2"
                            :class="column.align === 'left' ? 'text-left' : 'text-right tabular-nums'">
                            {{ column.format(day) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <GoogleAdsPager
            v-if="isPaged"
            :first-row="firstRow"
            :last-row="lastRow"
            :total="total"
            :page="page"
            :page-count="pageCount"
            :per-page="perPage"
            :per-page-options="perPageOptions"
            :unit="trans('days')"
            @update:page="page = $event"
            @update:per-page="((perPage = $event), toFirstPage())" />
    </div>
</template>
