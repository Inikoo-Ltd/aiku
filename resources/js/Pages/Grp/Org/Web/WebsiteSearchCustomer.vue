<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SearchTrendChart from "@/Components/DataDisplay/Dashboard/Widget/SearchTrendChart.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faTimes, faMousePointer } from "@fal"

library.add(faExternalLink, faTimes, faMousePointer)

const props = defineProps<{
    pageHead: any
    title: string
    insights: any
    drilldown: { query: string, customer: string, params: Record<string, any> }
    data: any
}>()

const queryUrl = (query: string) => route(props.drilldown.query, { ...props.drilldown.params, q: query })

const pagePath = (url: string) => {
    try {
        const path = new URL(url).pathname.replace(/\/+$/, '')
        return path.split('/').filter(Boolean).pop() ?? '/'
    } catch {
        return url
    }
}

const queryBlocks = [
    { key: 'top_queries', label: 'Top searches', showClicks: true },
    { key: 'top_zero_queries', label: 'Searches without results', showClicks: false },
    { key: 'top_abandoned_queries', label: 'Searches not followed', showClicks: false },
] as const
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 grid grid-cols-1 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-4">
        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <h3 class="text-lg font-semibold mb-3">
                {{ insights.customer_name }}
                <span class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(insights.days) }) }}</span>
            </h3>

            <div class="flex flex-wrap gap-x-10 gap-y-3 mb-5">
                <div>
                    <p class="text-4xl font-bold">{{ insights.total_searches.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Searches") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ insights.click_through }}%</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Click-through") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold" :class="insights.zero_results_rate > 0 ? 'text-red-500' : ''">{{ insights.zero_results_rate }}%</p>
                    <p class="text-sm text-gray-600">{{ ctrans("No results") }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div v-for="block in queryBlocks" :key="block.key">
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans(block.label) }}</p>
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="q in insights[block.key]"
                            :key="q.query"
                            :href="queryUrl(q.query)"
                            class="flex justify-between gap-2 py-1 hover:bg-slate-50"
                        >
                            <span class="text-gray-600 truncate min-w-0 hover:underline">{{ q.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">
                                {{ q.searches }}<span v-if="block.showClicks" class="text-gray-400 font-normal"> / {{ q.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span>
                            </span>
                        </Link>
                        <p v-if="!insights[block.key]?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm mt-6">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Pages reached from search") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="page in insights.top_clicked_pages" :key="page.clicked_url" class="flex justify-between gap-2 py-1">
                            <a :href="page.clicked_url" target="_blank" class="text-gray-600 truncate min-w-0 hover:underline" :title="page.clicked_url">{{ pagePath(page.clicked_url) }}</a>
                            <span class="shrink-0 tabular-nums font-medium">{{ page.clicks }}</span>
                        </div>
                        <p v-if="!insights.top_clicked_pages?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
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

        <SearchTrendChart :trend="insights.trend" :title="ctrans('Trend for this customer')" />
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
</template>
