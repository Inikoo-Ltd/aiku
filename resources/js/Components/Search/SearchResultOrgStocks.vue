<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Skeleton from 'primevue/skeleton'
import { ref } from 'vue'

type OrgStock = {
    id: number
    name: string
    code: string
    state: string
}

type OrgStocksResults = {
    org_stocks: OrgStock[]
}

const model = defineModel('open')

defineProps<{
    results: OrgStocksResults | null
    isLoading: boolean
    query: string
}>()

const isLoading = ref<number|null>(null)
</script>

<template>
    <div class="col-span-3 border-r p-4 bg-gray-50">
        <p class="text-xs text-gray-400 mb-1">{{ ctrans("Query") }}</p>
        <p class="font-semibold text-sm mb-4">{{ query }}</p>
        <p class="text-xs text-gray-400 mb-2">{{ ctrans("Summary") }}</p>
        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="2.5rem" borderRadius="0.75rem" />
        </div>
        <div v-else class="space-y-2">
            <div class="p-3 rounded-md bg-white text-sm flex items-center justify-between cursor-pointer transition hover:bg-slate-100 hover:shadow-sm active:scale-[0.98]">
                <span class="font-medium text-slate-700">{{ ctrans("Org Stocks") }}</span>
                <span class="text-xs text-gray-400">{{ results?.org_stocks?.length ?? 0 }}</span>
            </div>
        </div>
    </div>

    <div class="col-span-9 flex flex-col min-h-0">
        <div class="flex-1 p-4 space-y-4 overflow-y-auto">
            <div v-if="isLoading" class="space-y-4">
                <div v-for="i in 6" :key="i" class="p-4 rounded-md border bg-white">
                    <div class="flex justify-between items-center mb-2">
                        <Skeleton width="60%" height="1rem" />
                        <Skeleton width="60px" height="0.75rem" borderRadius="999px" />
                    </div>
                    <Skeleton width="40%" height="0.75rem" />
                </div>
            </div>
            <div v-else-if="results?.org_stocks?.length">
                <Link
                    v-for="orgStock in results.org_stocks"
                    :key="orgStock.id"
                    :href="route('grp.helpers.redirect_org_stock', [orgStock.id])"
                    class="block group p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm cursor-pointer mb-3"
                    @start="() => model = false"
                    @finish="() => isLoading = null"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold">{{ orgStock.name }}</p>
                        <span
                            class="text-[10px] px-2 py-0.5 rounded-full capitalize"
                            :class="orgStock.state === 'active' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ orgStock.state }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">{{ ctrans("Code") }}: {{ orgStock.code }}</p>
                </Link>
            </div>
            <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">{{ ctrans("No org stocks") }}</div>
        </div>
    </div>
</template>
