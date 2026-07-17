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
    searches: number
    clicks: number
}

defineProps<{
    widget?: {
        days: number
        total_searches: number
        click_through: number
        zero_results_rate: number
        top_queries: QueryStat[]
        top_zero_queries: QueryStat[]
        top_searchers?: SearcherStat[]
    } | null
}>()
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <div class="flex items-baseline justify-between mb-2">
            <h3 class="text-lg font-semibold">
                {{ ctrans("Search insights") }}
                <span v-if="widget" class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(widget.days) }) }}</span>
            </h3>
            <Link
                :href="route('grp.sysadmin.search_logs.index')"
                class="text-xs text-indigo-600 hover:underline whitespace-nowrap"
            >
                {{ ctrans("All searches & per-user stats") }}
                <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" />
            </Link>
        </div>

        <template v-if="widget">
            <div class="flex gap-10 mb-4">
                <div>
                    <p class="text-4xl font-bold">{{ widget.total_searches.toLocaleString() }}</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Searches") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ widget.click_through }}%</p>
                    <p class="text-sm text-gray-600">{{ ctrans("Click-through") }}</p>
                </div>
                <div>
                    <p class="text-4xl font-bold">{{ widget.zero_results_rate }}%</p>
                    <p class="text-sm text-gray-600">{{ ctrans("No results") }}</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Top searches") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="q in widget.top_queries" :key="q.query" class="flex justify-between gap-2 py-1">
                            <span class="text-gray-600 truncate min-w-0">{{ q.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}<span class="text-gray-400 font-normal"> / {{ q.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                        </div>
                        <p v-if="!widget.top_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Searches without results") }}</p>
                    <div class="divide-y divide-gray-100">
                        <div v-for="q in widget.top_zero_queries" :key="q.query" class="flex justify-between gap-2 py-1">
                            <span class="text-gray-600 truncate min-w-0">{{ q.query }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ q.searches }}</span>
                        </div>
                        <p v-if="!widget.top_zero_queries.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-gray-400 font-medium mb-1">{{ ctrans("Top searchers") }}</p>
                    <div class="divide-y divide-gray-100">
                        <Link
                            v-for="searcher in widget.top_searchers"
                            :key="searcher.username"
                            :href="`${route('grp.sysadmin.search_logs.index')}?filter[global]=${encodeURIComponent(searcher.username)}`"
                            class="flex justify-between gap-2 py-1 hover:bg-slate-50"
                        >
                            <span class="text-gray-600 truncate min-w-0">{{ searcher.username }}</span>
                            <span class="shrink-0 tabular-nums font-medium">{{ searcher.searches }}<span class="text-gray-400 font-normal"> / {{ searcher.clicks }} <FontAwesomeIcon icon='fal fa-mouse-pointer' aria-hidden='true' /></span></span>
                        </Link>
                        <p v-if="!widget.top_searchers?.length" class="py-1 text-gray-400">{{ ctrans("No data yet") }}</p>
                    </div>
                </div>
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No search activity recorded yet") }}</p>
    </div>
</template>
