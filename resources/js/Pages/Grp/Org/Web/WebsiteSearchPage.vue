<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SearchTrendChart from "@/Components/DataDisplay/Dashboard/Widget/SearchTrendChart.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faFile } from "@fal"

library.add(faExternalLink, faFile)

const props = defineProps<{
    pageHead: any
    title: string
    insights: any
    drilldown: { query: string, customer: string, params: Record<string, any> }
    data: any
}>()

const queryUrl = (query: string) => route(props.drilldown.query, { ...props.drilldown.params, q: query })

const customerUrl = (row: { customer_slug?: string }) =>
    row.customer_slug ? route(props.drilldown.customer, { ...props.drilldown.params, customer: row.customer_slug }) : null
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-4">
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <h3 class="text-lg font-semibold mb-3">
                {{ ctrans("Searches landing on this page") }}
                <a :href="insights.clicked_url" target="_blank" class="text-sm font-normal text-indigo-600 hover:underline">
                    <FontAwesomeIcon icon="fal fa-external-link" aria-hidden="true" />
                    {{ ctrans("open page") }}
                </a>
                <span class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(insights.days) }) }}</span>
            </h3>

            <div class="flex flex-wrap gap-x-10 gap-y-3 mb-5">
                <div>
                    <p class="text-4xl font-bold">{{ insights.total_clicks.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Arrivals from search") }}</p>
                    <p class="text-xs text-gray-400">
                        {{ ctrans(":logged logged in · :guest guests", { logged: String(insights.logged_in), guest: String(insights.guest) }) }}
                    </p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.unique_customers.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Customers") }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Search terms leading here") }}</p>
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="term in insights.top_queries"
                            :key="term.query"
                            :href="queryUrl(term.query)"
                            class="flex justify-between gap-2 py-1 hover:bg-slate-50 cursor-pointer"
                        >
                            <span class="text-gray-600 truncate min-w-0 hover:underline">{{ term.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ term.clicks }}</span>
                        </Link>
                        <p v-if="!insights.top_queries?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Customers arriving here") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="customerUrl(searcher) ? Link : 'div'"
                            v-for="searcher in insights.top_searchers"
                            :key="searcher.username"
                            :href="customerUrl(searcher) ?? undefined"
                            class="flex justify-between gap-2 py-1"
                            :class="customerUrl(searcher) ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <span class="text-gray-600 truncate min-w-0" :class="customerUrl(searcher) ? 'hover:underline' : ''">{{ searcher.username }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ searcher.clicks }}</span>
                        </component>
                        <p v-if="!insights.top_searchers?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Devices") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="device in insights.devices" :key="device.device" class="flex justify-between gap-2 py-1">
                            <span class="text-gray-600 truncate min-w-0 capitalize">{{ device.device }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ device.searches }}</span>
                        </div>
                        <p v-if="!insights.devices?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </div>

        <SearchTrendChart :trend="insights.trend" :title="ctrans('Arrivals trend')" />
    </div>

    <Table :resource="data" class="mt-2">
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
            <span class="tabular-nums">{{ item.results_count }}</span>
        </template>
    </Table>
</template>
