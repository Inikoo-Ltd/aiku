<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { ref, watch } from "vue"
import axios from "axios"
import { debounce } from "lodash-es"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Modal from "@/Components/Utils/Modal.vue"
import SearchAnalyticsDisplay from "@/Components/DataDisplay/Dashboard/Widget/SearchAnalyticsDisplay.vue"
import SearchTrendChart from "@/Components/DataDisplay/Dashboard/Widget/SearchTrendChart.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faTimes, faSearch, faUsers, faRocket } from "@fal"

library.add(faExternalLink, faTimes, faSearch, faUsers, faRocket)

interface BoostItem {
    type: 'product' | 'product_category' | 'collection'
    id: number
    code: string
    name: string | null
}

const props = defineProps<{
    pageHead: any
    title: string
    search_insights: any
    search_boosts: BoostItem[]
    boost_routes: { update: { name: string, parameters: Record<string, any> }, candidates: { name: string, parameters: Record<string, any> } }
    drilldown: { query: string, customer: string, params: Record<string, any> }
    data: any
    customers: any
}>()

const isBoostModalOpen = ref(false)
const boostSelection = ref<BoostItem[]>([...props.search_boosts])
const boostQuery = ref("")
const boostCandidates = ref<BoostItem[]>([])
const isSearchingCandidates = ref(false)
const isSavingBoosts = ref(false)

const boostTypeLabels: Record<BoostItem['type'], string> = {
    product: 'Product',
    product_category: 'Category',
    collection: 'Collection',
}

const searchCandidates = debounce(async (q: string) => {
    if (q.length < 2) {
        boostCandidates.value = []
        return
    }
    isSearchingCandidates.value = true
    try {
        const { data } = await axios.get(route(props.boost_routes.candidates.name, props.boost_routes.candidates.parameters), { params: { q } })
        boostCandidates.value = data
    } finally {
        isSearchingCandidates.value = false
    }
}, 300)

watch(boostQuery, (q) => searchCandidates(q))

const isSelected = (item: BoostItem) =>
    boostSelection.value.some(boost => boost.type === item.type && boost.id === item.id)

const addBoost = (item: BoostItem) => {
    if (boostSelection.value.length >= 3 || isSelected(item)) return
    boostSelection.value.push(item)
}

const removeBoost = (item: BoostItem) => {
    boostSelection.value = boostSelection.value.filter(boost => !(boost.type === item.type && boost.id === item.id))
}

const saveBoosts = () => {
    isSavingBoosts.value = true
    router.post(
        route(props.boost_routes.update.name, props.boost_routes.update.parameters),
        { boosts: boostSelection.value.map(({ type, id }) => ({ type, id })) },
        {
            preserveScroll: true,
            onFinish: () => {
                isSavingBoosts.value = false
                isBoostModalOpen.value = false
            },
        }
    )
}

const openBoostModal = () => {
    boostSelection.value = [...props.search_boosts]
    boostQuery.value = ""
    boostCandidates.value = []
    isBoostModalOpen.value = true
}

const activeTab = ref<'searches' | 'customers'>('searches')

const tabs = [
    { key: 'searches', label: 'Searches', icon: 'fal fa-search' },
    { key: 'customers', label: 'By customer', icon: 'fal fa-users' },
] as const

const queryUrl = (query: string) => route(props.drilldown.query, { ...props.drilldown.params, q: query })

const customerUrl = (row: { customer_slug?: string }) =>
    row.customer_slug ? route(props.drilldown.customer, { ...props.drilldown.params, customer: row.customer_slug }) : null
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 pt-4 flex items-center gap-2 flex-wrap">
        <button
            type="button"
            class="px-3 py-1.5 rounded-md text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
            v-tooltip="ctrans('Boost up to 3 items to the top of matching storefront searches')"
            @click="openBoostModal"
        >
            <FontAwesomeIcon icon="fal fa-rocket" fixed-width aria-hidden="true" />
            {{ ctrans("Boosted items") }}
        </button>
        <span
            v-for="boost in search_boosts"
            :key="`${boost.type}-${boost.id}`"
            class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700"
        >
            {{ boost.code }} <span class="text-indigo-400">· {{ ctrans(boostTypeLabels[boost.type]) }}</span>
        </span>
    </div>

    <Modal :isOpen="isBoostModalOpen" width="w-full max-w-lg" @onClose="isBoostModalOpen = false">
        <div class="p-2">
            <h2 class="text-lg font-medium mb-1">
                <FontAwesomeIcon icon="fal fa-rocket" fixed-width aria-hidden="true" />
                {{ ctrans("Boosted items") }}
            </h2>
            <p class="text-sm text-gray-500 mb-4">
                {{ ctrans("Boosted items appear first in storefront search results when they match the search. Maximum 3 items.") }}
            </p>

            <div v-if="boostSelection.length" class="flex flex-wrap gap-2 mb-4">
                <span
                    v-for="boost in boostSelection"
                    :key="`${boost.type}-${boost.id}`"
                    class="inline-flex items-center gap-1.5 text-sm px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700"
                >
                    {{ boost.code }}
                    <span class="text-indigo-400 text-xs">{{ ctrans(boostTypeLabels[boost.type]) }}</span>
                    <button type="button" @click="removeBoost(boost)" :aria-label="ctrans('Remove')">
                        <FontAwesomeIcon icon="fal fa-times" aria-hidden="true" />
                    </button>
                </span>
            </div>
            <p v-else class="text-sm text-gray-400 mb-4">{{ ctrans("No boosted items yet") }}</p>

            <input
                v-model="boostQuery"
                type="text"
                :placeholder="ctrans('Search products, categories or collections...')"
                class="w-full rounded-md border-gray-300 text-sm mb-2"
                :disabled="boostSelection.length >= 3"
            />
            <p v-if="boostSelection.length >= 3" class="text-xs text-amber-600 mb-2">{{ ctrans("Limit of 3 reached, remove one to add another") }}</p>

            <div class="max-h-56 overflow-y-auto divide-y divide-gray-100">
                <div v-if="isSearchingCandidates" class="py-3 text-sm text-gray-400">{{ ctrans("Searching...") }}</div>
                <template v-else>
                <button
                    v-for="candidate in boostCandidates"
                    :key="`${candidate.type}-${candidate.id}`"
                    type="button"
                    class="w-full flex items-center justify-between gap-2 py-2 px-1 text-left text-sm hover:bg-slate-50 disabled:opacity-40"
                    :disabled="isSelected(candidate) || boostSelection.length >= 3"
                    @click="addBoost(candidate)"
                >
                    <span class="truncate">
                        <span class="font-medium">{{ candidate.code }}</span>
                        <span class="text-gray-500"> {{ candidate.name }}</span>
                    </span>
                    <span class="text-xs text-gray-400 shrink-0">{{ ctrans(boostTypeLabels[candidate.type]) }}</span>
                </button>
                </template>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button
                    type="button"
                    class="px-4 py-2 rounded-md text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200"
                    @click="isBoostModalOpen = false"
                >
                    {{ ctrans("Cancel") }}
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-md text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="isSavingBoosts"
                    @click="saveBoosts"
                >
                    {{ ctrans("Save") }}
                </button>
            </div>
        </div>
    </Modal>

    <div class="p-4 grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-4">
        <SearchAnalyticsDisplay
            :widget="search_insights"
            :logs-url="null"
            :query-url="queryUrl"
            :customer-url="customerUrl"
        />
        <SearchTrendChart :trend="search_insights?.trend" />
    </div>

    <div class="px-4 flex gap-2">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="px-4 py-2 rounded-md text-sm font-medium transition"
            :class="activeTab === tab.key ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = tab.key"
        >
            <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
            {{ ctrans(tab.label) }}
        </button>
    </div>

    <Table v-show="activeTab === 'searches'" :resource="data" class="mt-2">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>

        <template #cell(query)="{ item }">
            <Link :href="queryUrl(item.query)" class="font-medium text-indigo-600 hover:underline">{{ item.query }}</Link>
        </template>

        <template #cell(scope)="{ item }">
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 capitalize">{{ item.scope.replaceAll('_', ' ') }}</span>
        </template>

        <template #cell(customer_name)="{ item }">
            <Link v-if="item.customer_slug" :href="customerUrl(item) ?? ''" class="text-indigo-600 hover:underline">{{ item.customer_name }}</Link>
            <span v-else class="text-gray-400 text-xs">{{ ctrans("Guest") }}</span>
        </template>

        <template #cell(device)="{ item }">
            <span class="text-gray-500 text-xs capitalize" v-tooltip="item.browser">{{ item.device ?? '-' }}</span>
        </template>

        <template #cell(results_count)="{ item }">
            <span :class="item.results_count === 0 ? 'text-red-500 font-medium' : 'tabular-nums'">{{ item.results_count }}</span>
        </template>

        <template #cell(clicked_at)="{ item }">
            <a
                v-if="item.clicked_at"
                :href="item.clicked_url"
                target="_blank"
                class="text-green-600 hover:underline whitespace-nowrap text-xs"
                v-tooltip="item.clicked_url"
            >
                <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                {{ useFormatTime(item.clicked_at, { formatTime: 'hms', keepTimezone: true }) }}
            </a>
            <FontAwesomeIcon v-else icon="fal fa-times" class="text-gray-300" fixed-width aria-hidden="true" />
        </template>
    </Table>

    <Table v-show="activeTab === 'customers'" :resource="customers" name="customers" class="mt-2">
        <template #cell(customer_name)="{ item }">
            <Link v-if="item.customer_slug" :href="customerUrl(item) ?? ''" class="font-medium text-indigo-600 hover:underline">{{ item.customer_name }}</Link>
            <span v-else class="font-medium">{{ item.customer_name }}</span>
        </template>

        <template #cell(searches)="{ item }">
            <span class="tabular-nums font-medium">{{ item.searches.toLocaleString() }}</span>
        </template>

        <template #cell(clicks)="{ item }">
            <span class="tabular-nums">{{ item.clicks.toLocaleString() }}</span>
        </template>

        <template #cell(click_through)="{ item }">
            <span class="tabular-nums">{{ item.click_through }}%</span>
        </template>

        <template #cell(zero_results)="{ item }">
            <span :class="item.zero_results > 0 ? 'text-red-500' : 'text-gray-400'" class="tabular-nums">{{ item.zero_results }}</span>
        </template>

        <template #cell(last_searched_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.last_searched_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>
    </Table>
</template>
