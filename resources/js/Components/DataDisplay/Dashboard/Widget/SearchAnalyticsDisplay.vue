<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { faMousePointer, faArrowRight } from '@fal'

library.add(faMousePointer, faArrowRight)

type QueryStat = {
    query: string
    searches: number
    clicks: number
}

type SearcherStat = {
    username: string
    customer_slug?: string
    searches: number
    clicks: number
}

const props = defineProps<{
    widget?: {
        days: number
        total_searches: number
        logged_in_searches?: number
        guest_searches?: number
        click_through: number
        zero_results_rate: number
        top_queries: QueryStat[]
        top_zero_queries: QueryStat[]
        top_abandoned_queries?: QueryStat[]
        top_searchers?: SearcherStat[]
        top_clicked_pages?: { clicked_url: string, clicks: number }[]
        devices?: { device: string, searches: number, clicks: number }[]
    } | null
    logsUrl?: string | null
    logsLabel?: string
    queryUrl?: (query: string) => string
    customerUrl?: (row: SearcherStat) => string | null
}>()

// The full path is unreadable in a narrow column; the last segment is the webpage url
const pagePath = (url: string) => {
    try {
        const path = new URL(url).pathname.replace(/\/+$/, '')
        return path.split('/').filter(Boolean).pop() ?? '/'
    } catch {
        return url
    }
}

// undefined keeps the historic staff-search target; explicit null hides the link
const resolvedLogsUrl = props.logsUrl === undefined ? route('grp.sysadmin.search_logs.index') : props.logsUrl
const statTag = resolvedLogsUrl ? Link : 'div'

// Website context drills into the customer page; the staff widget keeps its per-user filter
const searcherHref = (searcher: SearcherStat): string | null => {
    if (props.customerUrl) {
        return props.customerUrl(searcher)
    }
    if (props.logsUrl === undefined) {
        return `${route('grp.sysadmin.search_logs.index')}?filter[global]=${encodeURIComponent(searcher.username)}`
    }
    return null
}
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="text-lg font-semibold">
                {{ ctrans("Search insights") }}
                <span v-if="widget" class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(widget.days) }) }}</span>
            </h3>
            <Link
                v-if="resolvedLogsUrl"
                :href="resolvedLogsUrl"
                class="text-xs text-indigo-600 hover:underline whitespace-nowrap"
            >
                {{ logsLabel ?? ctrans("All searches & per-user stats") }}
                <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" />
            </Link>
        </div>

        <template v-if="widget">
            <div class="flex gap-10 mb-4">
                <component :is="statTag" :href="resolvedLogsUrl" :class="resolvedLogsUrl && 'group'">
                    <p class="text-4xl font-bold">{{ widget.total_searches.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600 group-hover:underline">{{ ctrans("Searches") }}</p>
                    <p v-if="widget.logged_in_searches !== undefined" class="text-xs text-gray-400">
                        {{ ctrans(":logged logged in · :guest guests", { logged: String(widget.logged_in_searches), guest: String(widget.guest_searches ?? 0) }) }}
                    </p>
                </component>
                <component :is="statTag" :href="resolvedLogsUrl" :class="resolvedLogsUrl && 'group'">
                    <p class="text-4xl font-bold">{{ widget.click_through }}%</p>
                    <p class="text-sm text-gray-600 group-hover:underline">{{ ctrans("Click-through") }}</p>
                </component>
                <component :is="statTag" :href="resolvedLogsUrl" :class="resolvedLogsUrl && 'group'">
                    <p class="text-4xl font-bold">{{ widget.zero_results_rate }}%</p>
                    <p class="text-sm text-gray-600 group-hover:underline">{{ ctrans("No results") }}</p>
                </component>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Top searches") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="queryUrl ? Link : 'div'"
                            v-for="q in widget.top_queries"
                            :key="q.query"
                            :href="queryUrl ? queryUrl(q.query) : undefined"
                            class="flex justify-between gap-2 py-1"
                            :class="queryUrl ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <span class="text-gray-600 truncate min-w-0" :class="queryUrl ? 'hover:underline' : ''">{{ q.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}<span class="text-gray-400 font-normal"> / {{ q.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                        </component>
                        <p v-if="!widget.top_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Searches without results") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="queryUrl ? Link : 'div'"
                            v-for="q in widget.top_zero_queries"
                            :key="q.query"
                            :href="queryUrl ? queryUrl(q.query) : undefined"
                            class="flex justify-between gap-2 py-1"
                            :class="queryUrl ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <span class="text-gray-600 truncate min-w-0" :class="queryUrl ? 'hover:underline' : ''">{{ q.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}</span>
                        </component>
                        <p v-if="!widget.top_zero_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.top_abandoned_queries">
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Searches not followed") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="queryUrl ? Link : 'div'"
                            v-for="q in widget.top_abandoned_queries"
                            :key="q.query"
                            :href="queryUrl ? queryUrl(q.query) : undefined"
                            class="flex justify-between gap-2 py-1"
                            :class="queryUrl ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <span class="text-gray-600 truncate min-w-0" :class="queryUrl ? 'hover:underline' : ''">{{ q.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}</span>
                        </component>
                        <p v-if="!widget.top_abandoned_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Top searchers") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="searcherHref(searcher) ? Link : 'div'"
                            v-for="searcher in widget.top_searchers"
                            :key="searcher.username"
                            :href="searcherHref(searcher) ?? undefined"
                            class="flex justify-between gap-2 py-1"
                            :class="searcherHref(searcher) ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <span class="text-gray-600 truncate min-w-0" :class="searcherHref(searcher) ? 'hover:underline' : ''">{{ searcher.username }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ searcher.searches }}<span class="text-gray-400 font-normal"> / {{ searcher.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                        </component>
                        <p v-if="!widget.top_searchers?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.top_clicked_pages">
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Top pages reached from search") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="page in widget.top_clicked_pages" :key="page.clicked_url" class="flex justify-between gap-2 py-1">
                            <a :href="page.clicked_url" target="_blank" class="text-gray-600 truncate min-w-0 hover:underline" :title="page.clicked_url">{{ pagePath(page.clicked_url) }}</a>
                            <span class="shrink-0 tabular-nums font-medium">{{ page.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' class="text-gray-400" /></span>
                        </div>
                        <p v-if="!widget.top_clicked_pages.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.devices">
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Devices") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="device in widget.devices" :key="device.device" class="flex justify-between gap-2 py-1">
                            <span class="text-gray-600 truncate min-w-0 capitalize">{{ device.device }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ device.searches }}<span class="text-gray-400 font-normal"> / {{ device.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                        </div>
                        <p v-if="!widget.devices.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No search activity recorded yet") }}</p>
    </div>
</template>
