<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { faMousePointer, faArrowRight } from '@fal'
import MiniBar from './MiniBar.vue'

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
        sources?: { source: string, label: string, searches: number, clicks: number, share: number }[]
    } | null
    logsUrl?: string | null
    logsLabel?: string
    queryUrl?: (query: string) => string
    customerUrl?: (row: SearcherStat) => string | null
    pageUrl?: (clickedUrl: string) => string
    zeroQueryStatus?: Record<string, 'unpublished' | 'not_stocked'>
    opportunitiesUrl?: string
    liveWebsiteId?: number
}>()

// Headline numbers arrive over the same website analytics channel the live visitor
// counters use; the lists stay as rendered, they are far heavier to rebuild per search
type Headline = {
    total_searches: number
    logged_in_searches: number
    guest_searches: number
    click_through: number
    zero_results_rate: number
}

const liveHeadline = ref<Headline | null>(null)
const justUpdated = ref(false)
let flashTimer: ReturnType<typeof setTimeout> | null = null

const stats = computed(() => ({ ...(props.widget ?? {}), ...(liveHeadline.value ?? {}) }) as any)

onMounted(() => {
    if (!props.liveWebsiteId || !(window as any).Echo) {
        return
    }

    ;(window as any).Echo.private(`website.${props.liveWebsiteId}.analytics`)
        .listen('.App\\Events\\Web\\WebsiteSearchStatsUpdated', (event: Headline) => {
            liveHeadline.value = event
            justUpdated.value = true
            if (flashTimer) clearTimeout(flashTimer)
            flashTimer = setTimeout(() => (justUpdated.value = false), 1200)
        })
})

onUnmounted(() => {
    if (flashTimer) clearTimeout(flashTimer)
    if (props.liveWebsiteId && (window as any).Echo) {
        ;(window as any).Echo.leave(`website.${props.liveWebsiteId}.analytics`)
    }
})

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
const resolvedLogsUrl = props.logsUrl === undefined ? route('grp.sysadmin.analytics.search_logs.index') : props.logsUrl
const statTag = resolvedLogsUrl ? Link : 'div'

// Website context drills into the customer page; the staff widget keeps its per-user filter
const searcherHref = (searcher: SearcherStat): string | null => {
    if (props.customerUrl) {
        return props.customerUrl(searcher)
    }
    if (props.logsUrl === undefined) {
        return `${route('grp.sysadmin.analytics.search_logs.index')}?filter[global]=${encodeURIComponent(searcher.username)}`
    }
    return null
}

const maxTopQueries = computed(() => Math.max(0, ...(props.widget?.top_queries.map(q => q.searches) ?? [])))
const maxZeroQueries = computed(() => Math.max(0, ...(props.widget?.top_zero_queries.map(q => q.searches) ?? [])))
const maxAbandonedQueries = computed(() => Math.max(0, ...(props.widget?.top_abandoned_queries?.map(q => q.searches) ?? [])))
const maxSearchers = computed(() => Math.max(0, ...(props.widget?.top_searchers?.map(s => s.searches) ?? [])))
const maxClickedPages = computed(() => Math.max(0, ...(props.widget?.top_clicked_pages?.map(p => p.clicks) ?? [])))
const maxDevices = computed(() => Math.max(0, ...(props.widget?.devices?.map(d => d.searches) ?? [])))

const clickThroughColor = computed(() => stats.value.click_through >= 50 ? 'text-emerald-600' : stats.value.click_through >= 25 ? 'text-amber-600' : 'text-red-500')
const zeroResultsColor = computed(() => stats.value.zero_results_rate <= 5 ? 'text-emerald-600' : stats.value.zero_results_rate <= 15 ? 'text-amber-600' : 'text-red-500')
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
                    <p class="text-4xl font-bold transition-colors duration-500" :class="justUpdated && 'text-indigo-600'">{{ stats.total_searches.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600 group-hover:underline">
                        {{ ctrans("Searches") }}
                        <span v-if="liveWebsiteId" class="inline-block w-1.5 h-1.5 rounded-full align-middle" :class="justUpdated ? 'bg-indigo-500' : 'bg-green-400'" v-tooltip="ctrans('Updating live')" />
                    </p>
                    <p v-if="stats.logged_in_searches !== undefined" class="text-xs text-gray-400">
                        {{ ctrans(":logged logged in · :guest guests", { logged: String(stats.logged_in_searches), guest: String(stats.guest_searches ?? 0) }) }}
                    </p>
                </component>
                <component :is="statTag" :href="resolvedLogsUrl" :class="resolvedLogsUrl && 'group'">
                    <p class="text-4xl font-bold transition-colors duration-500" :class="justUpdated ? 'text-indigo-600' : clickThroughColor">{{ stats.click_through }}%</p>
                    <p class="text-sm text-gray-600 group-hover:underline">{{ ctrans("Click-through") }}</p>
                </component>
                <component :is="statTag" :href="resolvedLogsUrl" :class="resolvedLogsUrl && 'group'">
                    <p class="text-4xl font-bold transition-colors duration-500" :class="justUpdated ? 'text-indigo-600' : zeroResultsColor">{{ stats.zero_results_rate }}%</p>
                    <p class="text-sm text-gray-600 group-hover:underline">{{ ctrans("No results") }}</p>
                </component>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans("Top searches") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="queryUrl ? Link : 'div'"
                            v-for="q in widget.top_queries"
                            :key="q.query"
                            :href="queryUrl ? queryUrl(q.query) : undefined"
                            class="block py-1"
                            :class="queryUrl ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0" :class="queryUrl ? 'hover:underline' : ''">{{ q.query }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}<span class="text-gray-400 font-normal"> / {{ q.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                            </div>
                            <MiniBar :value="q.searches" :max="maxTopQueries" color="bg-indigo-400" />
                        </component>
                        <p v-if="!widget.top_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-amber-400 mr-1" />{{ ctrans("Searches without results") }}
                        <Link v-if="opportunitiesUrl" :href="opportunitiesUrl" class="text-indigo-600 hover:underline font-normal">
                            · {{ ctrans("opportunities") }}
                        </Link>
                    </p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="queryUrl ? Link : 'div'"
                            v-for="q in widget.top_zero_queries"
                            :key="q.query"
                            :href="queryUrl ? queryUrl(q.query) : undefined"
                            class="block py-1"
                            :class="queryUrl ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0" :class="queryUrl ? 'hover:underline' : ''">{{ q.query }}</span>
                                <span class="shrink-0 tabular-nums font-medium flex items-center gap-1">
                                    <span
                                        v-if="zeroQueryStatus?.[q.query] === 'unpublished'"
                                        class="text-[10px] font-normal px-1.5 py-0.5 rounded bg-amber-50 text-amber-700"
                                        v-tooltip="ctrans('We stock matching products, they are not published on the website')"
                                    >{{ ctrans("unpublished") }}</span>
                                    <span
                                        v-else-if="zeroQueryStatus?.[q.query] === 'not_stocked'"
                                        class="text-[10px] font-normal px-1.5 py-0.5 rounded bg-green-50 text-green-700"
                                        v-tooltip="ctrans('Nothing in the catalogue matches, demand we do not sell')"
                                    >{{ ctrans("not sold") }}</span>
                                    {{ q.searches }}
                                </span>
                            </div>
                            <MiniBar :value="q.searches" :max="maxZeroQueries" color="bg-amber-400" />
                        </component>
                        <p v-if="!widget.top_zero_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.top_abandoned_queries">
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-orange-300 mr-1" />{{ ctrans("Searches not followed") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="queryUrl ? Link : 'div'"
                            v-for="q in widget.top_abandoned_queries"
                            :key="q.query"
                            :href="queryUrl ? queryUrl(q.query) : undefined"
                            class="block py-1"
                            :class="queryUrl ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0" :class="queryUrl ? 'hover:underline' : ''">{{ q.query }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}</span>
                            </div>
                            <MiniBar :value="q.searches" :max="maxAbandonedQueries" color="bg-orange-300" />
                        </component>
                        <p v-if="!widget.top_abandoned_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans("Top searchers") }}</p>
                    <div class="divide-y divide-gray-100">
                        <component
                            :is="searcherHref(searcher) ? Link : 'div'"
                            v-for="searcher in widget.top_searchers"
                            :key="searcher.username"
                            :href="searcherHref(searcher) ?? undefined"
                            class="block py-1"
                            :class="searcherHref(searcher) ? 'hover:bg-slate-50 cursor-pointer' : ''"
                        >
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0" :class="searcherHref(searcher) ? 'hover:underline' : ''">{{ searcher.username }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ searcher.searches }}<span class="text-gray-400 font-normal"> / {{ searcher.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                            </div>
                            <MiniBar :value="searcher.searches" :max="maxSearchers" color="bg-indigo-400" />
                        </component>
                        <p v-if="!widget.top_searchers?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.top_clicked_pages">
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1" />{{ ctrans("Top pages reached from search") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="page in widget.top_clicked_pages" :key="page.clicked_url" class="py-1">
                            <div class="flex justify-between gap-2">
                                <Link v-if="props.pageUrl" :href="props.pageUrl(page.clicked_url)" class="text-gray-600 truncate min-w-0 hover:underline" :title="page.clicked_url">{{ pagePath(page.clicked_url) }}</Link>
                                <a v-else :href="page.clicked_url" target="_blank" class="text-gray-600 truncate min-w-0 hover:underline" :title="page.clicked_url">{{ pagePath(page.clicked_url) }}</a>
                                <span class="shrink-0 tabular-nums font-medium">{{ page.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' class="text-gray-400" /></span>
                            </div>
                            <MiniBar :value="page.clicks" :max="maxClickedPages" color="bg-emerald-400" />
                        </div>
                        <p v-if="!widget.top_clicked_pages.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.sources">
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans("Where searches start") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="source in widget.sources" :key="source.source" class="py-1">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0">{{ source.label }}</span>
                                <span class="shrink-0 tabular-nums font-medium">
                                    {{ source.share }}%
                                    <span class="text-gray-400 font-normal">{{ source.searches }} / {{ source.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span>
                                </span>
                            </div>
                            <MiniBar :value="source.share" :max="100" color="bg-indigo-400" />
                        </div>
                        <p v-if="!widget.sources.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div v-if="widget.devices">
                    <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-1" />{{ ctrans("Devices") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="device in widget.devices" :key="device.device" class="py-1">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600 truncate min-w-0 capitalize">{{ device.device }}</span>
                                <span class="shrink-0 tabular-nums font-medium">{{ device.searches }}<span class="text-gray-400 font-normal"> / {{ device.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                            </div>
                            <MiniBar :value="device.searches" :max="maxDevices" color="bg-sky-400" />
                        </div>
                        <p v-if="!widget.devices.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No search activity recorded yet") }}</p>
    </div>
</template>
