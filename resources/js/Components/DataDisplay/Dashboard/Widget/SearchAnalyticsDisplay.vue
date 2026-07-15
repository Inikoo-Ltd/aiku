<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMousePointer } from '@fal'

library.add(faMousePointer)

type QueryStat = {
    query: string
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
    } | null
}>()
</script>

<template>
    <div class="bg-white rounded-lg p-4 flex flex-col shadow-sm border border-gray-300">
        <h3 class="text-lg font-semibold mb-2">
            {{ ctrans("Search insights") }}
            <span v-if="widget" class="text-xs font-normal text-gray-400">{{ ctrans("last :days days", { days: String(widget.days) }) }}</span>
        </h3>

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

            <div class="grid grid-cols-2 gap-6 text-sm">
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
            </div>
        </template>

        <p v-else class="text-sm text-gray-500">{{ ctrans("No search activity recorded yet") }}</p>
    </div>
</template>
