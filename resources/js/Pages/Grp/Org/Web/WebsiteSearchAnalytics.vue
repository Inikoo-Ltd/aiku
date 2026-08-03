<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { ref } from "vue"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SearchAnalyticsDisplay from "@/Components/DataDisplay/Dashboard/Widget/SearchAnalyticsDisplay.vue"
import SearchMerchandising from "@/Components/DataDisplay/Dashboard/Widget/SearchMerchandising.vue"
import SearchTrendChart from "@/Components/DataDisplay/Dashboard/Widget/SearchTrendChart.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faTimes, faSearch, faUsers } from "@fal"

library.add(faExternalLink, faTimes, faSearch, faUsers)

const props = defineProps<{
    pageHead: any
    title: string
    search_insights: any
    search_merchandising: any
    zero_query_status?: Record<string, 'unpublished' | 'not_stocked'>
    drilldown: { query: string, customer: string, opportunities?: string, params: Record<string, any> }
    data: any
    customers: any
}>()

const activeTab = ref<'searches' | 'customers'>('searches')

const tabs = [
    { key: 'searches', label: 'Searches', icon: 'fal fa-search' },
    { key: 'customers', label: 'By customer', icon: 'fal fa-users' },
] as const

const queryUrl = (query: string) => route(props.drilldown.query, { ...props.drilldown.params, q: query })

const customerUrl = (row: { customer_slug?: string }) =>
    row.customer_slug ? route(props.drilldown.customer, { ...props.drilldown.params, customer: row.customer_slug }) : null

const pageUrl = (clickedUrl: string) => route('grp.org.shops.show.web.analytics.search.page', { ...props.drilldown.params, url: clickedUrl })

const opportunitiesUrl = props.drilldown.opportunities
    ? route(props.drilldown.opportunities, props.drilldown.params)
    : undefined
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 pt-4">
        <SearchMerchandising
            v-if="search_merchandising"
            :merchandising="search_merchandising"
            :top-zero-queries="search_insights?.top_zero_queries"
        />
    </div>

    <div class="p-4 grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-4">
        <SearchAnalyticsDisplay
            :widget="search_insights"
            :logs-url="null"
            :query-url="queryUrl"
            :customer-url="customerUrl"
            :page-url="pageUrl"
            :zero-query-status="zero_query_status"
            :opportunities-url="opportunitiesUrl"
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

        <template #cell(source)="{ item }">
            <span v-if="item.source_label" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">{{ item.source_label }}</span>
            <span v-else class="text-gray-300 text-xs">-</span>
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
