<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useConfirm } from "primevue/useconfirm"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { trans } from "laravel-vue-i18n"

/**
 * What people actually typed, and the two things worth doing about a row: bid on it deliberately, or
 * stop bidding on it. Ordered by spend, because the question is where the money went.
 *
 * Google's own status decides which action a row gets. Offering "add" on a term that is already a
 * keyword would only earn a refusal, and offering "exclude" on one already excluded would do nothing.
 */
const props = defineProps<{
    searchTerms: {
        term: string
        status: string
        matched_keyword: string | null
        impressions: number
        clicks: number
        cost: number
        conversions: number
        conversions_value: number
        cost_per_click: number | null
    }[]
    error: string | null
    currency: string
    adGroups: { id: string; name: string | null }[]
    keywordRoute: { name: string; parameters: Record<string, unknown> }
    negativeKeywordsRoute: { name: string; parameters: Record<string, unknown> }
}>()

const locale = useLocaleStore()
const confirm = useConfirm()

const money = (value: number) => locale.currencyFormat(props.currency, value)

const filter = ref("")
const onlyUnconverted = ref(false)
const busyTerm = ref<string | null>(null)
const error = ref<string | null>(null)

// A campaign usually has one ad group; when it has several, a new keyword has to be told where to go.
const targetAdGroup = ref<string>(props.adGroups[0]?.id ?? "")

const rows = computed(() => {
    const needle = filter.value.trim().toLowerCase()

    return props.searchTerms.filter((row) => {
        if (needle && !row.term.toLowerCase().includes(needle)) return false
        if (onlyUnconverted.value && !(row.conversions === 0 && row.cost > 0)) return false

        return true
    })
})

const spent = computed(() => rows.value.reduce((total, row) => total + row.cost, 0))

const submit = (url: string, payload: Record<string, unknown>, term: string) =>
    router.post(url, payload, {
        preserveScroll: true,
        onStart: () => {
            busyTerm.value = term
            error.value = null
        },
        onError: (errors) =>
            (error.value = Object.values(errors as Record<string, string>)[0] ?? ctrans("That change was refused.")),
        onFinish: () => (busyTerm.value = null),
    })

const addAsKeyword = (row: { term: string }) => {
    if (!targetAdGroup.value) return

    const group = props.adGroups.find((adGroup) => adGroup.id === targetAdGroup.value)

    confirm.require({
        header: ctrans("Bid on this search"),
        message:
            ctrans("It becomes a phrase keyword and starts costing money as soon as Google accepts it. Into ad group: ") +
            (group?.name ?? group?.id ?? "") +
            ". " + ctrans("Term: ") + row.term,
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Add it") },
        accept: () =>
            submit(
                route(props.keywordRoute.name, props.keywordRoute.parameters),
                { ad_group_id: targetAdGroup.value, text: row.term, match_type: "PHRASE" },
                row.term
            ),
    })
}

const exclude = (row: { term: string }) =>
    confirm.require({
        header: ctrans("Stop bidding on this search"),
        message: ctrans("This campaign will not show ads for it again. Term: ") + row.term,
        rejectProps: { label: ctrans("Cancel"), severity: "secondary", outlined: true },
        acceptProps: { label: ctrans("Exclude it") },
        accept: () =>
            router.patch(
                route(props.negativeKeywordsRoute.name, props.negativeKeywordsRoute.parameters),
                { text: row.term, match_type: "PHRASE" },
                {
                    preserveScroll: true,
                    onStart: () => {
                        busyTerm.value = row.term
                        error.value = null
                    },
                    onError: (errors) =>
                        (error.value = Object.values(errors as Record<string, string>)[0] ?? ctrans("That change was refused.")),
                    onFinish: () => (busyTerm.value = null),
                }
            ),
    })
</script>

<template>
    <div>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-medium text-gray-800">
                {{ trans("What people actually searched") }}
                <span v-if="searchTerms.length" class="font-normal text-gray-500">· {{ searchTerms.length }}</span>
            </h2>
            <span class="text-xs text-gray-500">{{ trans("Read from Google for the period above, highest spend first") }}</span>
        </div>

        <p v-if="error" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-[#d03b3b]">{{ error }}</p>

        <p v-if="props.error" class="mt-3 text-xs text-[#a15c00]">{{ props.error }}</p>

        <template v-else-if="searchTerms.length">
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="gads-term-filter" class="sr-only">{{ trans("Search these terms") }}</label>
                    <input
                        id="gads-term-filter"
                        v-model="filter"
                        type="search"
                        :placeholder="trans('Search these terms')"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-64" />
                </div>

                <label class="flex items-center gap-2 pb-2 text-xs text-gray-600">
                    <input
                        v-model="onlyUnconverted"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    {{ trans("Only those that cost money and converted nothing") }}
                </label>

                <div v-if="adGroups.length > 1" class="ml-auto">
                    <label for="gads-target-group" class="block text-xs text-gray-500">
                        {{ trans("Add keywords into") }}
                    </label>
                    <select
                        id="gads-target-group"
                        v-model="targetAdGroup"
                        class="mt-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option v-for="group in adGroups" :key="group.id" :value="group.id">
                            {{ group.name ?? group.id }}
                        </option>
                    </select>
                </div>
            </div>

            <p class="mt-3 text-xs text-gray-500">
                {{ trans("Showing") }} {{ rows.length }} {{ trans("of") }} {{ searchTerms.length }},
                {{ money(spent) }} {{ trans("spent on them") }}.
                <span v-if="onlyUnconverted">
                    {{ trans("A search with no conversion is not automatically waste: this account records conversions without a value, so judge these on whether the search reads like a customer.") }}
                </span>
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[46rem] text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th scope="col" class="py-1.5 pr-2 text-left font-normal">{{ trans("Search term") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-left font-normal">{{ trans("Matched") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ trans("Clicks") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ trans("Cost") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ trans("Conv.") }}</th>
                            <th scope="col" class="py-1.5 pl-2 text-right font-normal">
                                <span class="sr-only">{{ trans("Actions") }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.term" class="border-b border-gray-50 text-gray-600">
                            <td class="py-2 pr-2">
                                <span class="block max-w-[18rem] truncate" :title="row.term">{{ row.term }}</span>
                                <span v-if="row.status === 'ADDED'" class="text-gray-500">{{ trans("already a keyword") }}</span>
                                <span v-else-if="row.status === 'EXCLUDED'" class="text-gray-500">{{ trans("already excluded") }}</span>
                            </td>
                            <td class="max-w-[12rem] truncate px-2" :title="row.matched_keyword ?? ''">
                                {{ row.matched_keyword ?? "—" }}
                            </td>
                            <td class="px-2 text-right tabular-nums">{{ locale.number(row.clicks) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ money(row.cost) }}</td>
                            <td
                                class="px-2 text-right tabular-nums"
                                :class="row.conversions > 0 ? 'text-[#006300]' : 'text-gray-500'">
                                {{ locale.number(row.conversions) }}
                            </td>
                            <td class="whitespace-nowrap py-2 pl-2 text-right">
                                <span v-if="busyTerm === row.term" class="text-gray-500">{{ trans("Saving") }}</span>
                                <template v-else>
                                    <button
                                        v-if="row.status !== 'ADDED'"
                                        type="button"
                                        :disabled="!targetAdGroup"
                                        class="rounded px-1.5 py-0.5 text-[#006300] underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-40"
                                        @click="addAsKeyword(row)">
                                        {{ trans("Bid on it") }}
                                    </button>
                                    <button
                                        v-if="row.status !== 'EXCLUDED'"
                                        type="button"
                                        class="ml-1 rounded px-1.5 py-0.5 text-[#d03b3b] underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                        @click="exclude(row)">
                                        {{ trans("Exclude it") }}
                                    </button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="6" class="py-4 text-center text-gray-500">
                                {{ trans("Nothing matches those filters.") }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <p v-else class="mt-4 text-xs text-gray-500">
            {{ trans("Google reported no searches for this campaign in this period. Performance Max and Shopping campaigns report far fewer than Search ones, and a paused campaign reports none.") }}
        </p>
    </div>
</template>
