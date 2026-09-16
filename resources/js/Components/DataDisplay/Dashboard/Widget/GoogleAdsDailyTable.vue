<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
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
    } else {
        sortKey.value = key
        sortDescending.value = true
    }

    page.value = 1
}

const sortIcon = (key: SortKey) => {
    if (sortKey.value !== key) return "fal fa-sort"

    return sortDescending.value ? "fal fa-sort-down" : "fal fa-sort-up"
}

const ariaSort = (key: SortKey) => {
    if (sortKey.value !== key) return "none"

    return sortDescending.value ? "descending" : "ascending"
}

/* Fifty rather than thirty so a month, the default period, never spills its last day or two onto a
   second page and the controls stay out of the way until a quarter or a year is asked for. */
const PER_PAGE_OPTIONS = [50, 100, 365]

const perPage = ref(PER_PAGE_OPTIONS[0])
const page = ref(1)

const sorted = computed(() => {
    const key = sortKey.value
    const direction = sortDescending.value ? -1 : 1

    return [...props.daily].sort((a, b) => {
        if (key === "date") return a.date.localeCompare(b.date) * direction

        return ((a[key] as number) - (b[key] as number)) * direction
    })
})

const pageCount = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage.value)))

/* A shorter period, or more rows per page, can leave the reader on a page that no longer exists. */
watch([pageCount], () => {
    if (page.value > pageCount.value) page.value = pageCount.value
})

const firstRow = computed(() => (sorted.value.length ? (page.value - 1) * perPage.value + 1 : 0))
const lastRow = computed(() => Math.min(page.value * perPage.value, sorted.value.length))

const rows = computed(() => sorted.value.slice(firstRow.value - 1, lastRow.value))

const isPaged = computed(() => sorted.value.length > PER_PAGE_OPTIONS[0])
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
                    <tr v-for="day in rows" :key="day.date" class="border-b border-gray-50 text-gray-600">
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

        <div v-if="isPaged" class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
            <span aria-live="polite">
                {{ trans("Showing") }} {{ locale.number(firstRow) }} {{ trans("to") }} {{ locale.number(lastRow) }}
                {{ trans("of") }} {{ locale.number(sorted.length) }} {{ trans("days") }}
            </span>

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-1.5">
                    {{ trans("Days per page") }}
                    <select
                        v-model.number="perPage"
                        class="rounded-md border-gray-300 py-0.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                        @change="page = 1">
                        <option v-for="option in PER_PAGE_OPTIONS" :key="option" :value="option">{{ option }}</option>
                    </select>
                </label>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        :disabled="page === 1"
                        class="rounded px-2 py-1 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40 disabled:hover:bg-transparent"
                        @click="page = page - 1">
                        {{ trans("Previous") }}
                    </button>
                    <span class="tabular-nums">{{ page }} / {{ pageCount }}</span>
                    <button
                        type="button"
                        :disabled="page === pageCount"
                        class="rounded px-2 py-1 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40 disabled:hover:bg-transparent"
                        @click="page = page + 1">
                        {{ trans("Next") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
