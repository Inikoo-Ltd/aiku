<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import Skeleton from 'primevue/skeleton'
import { ref } from 'vue'

type OrgStock = {
    id: number
    name: string
    slug: string
    code: string
    state: string
}

type OrgStocksResults = {
    org_stocks: OrgStock[]
    org_stock_families: OrgStock[]
}

const model = defineModel('open')

defineProps<{
    results: OrgStocksResults | null
    isLoading: boolean
    query: string
}>()

type Tab = 'org_stocks' | 'org_stock_families'

const activeTab = ref<Tab>('org_stocks')
const loadingId = ref<number | null>(null)

const routeParams = route().routeParams
</script>

<template>
    <div class="col-span-3 border-r p-4 bg-gray-50">
        <p class="text-xs text-gray-400 mb-1">{{ ctrans("Query") }}</p>
        <p class="font-semibold text-sm mb-4">{{ query }}</p>
        <p class="text-xs text-gray-400 mb-2">{{ ctrans("Summary") }}</p>
        <div v-if="isLoading" class="space-y-2">
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
            <Skeleton height="2.5rem" borderRadius="0.375rem" />
        </div>
        <div v-else class="space-y-2">
            <button
                type="button"
                class="w-full p-3 rounded-md text-sm flex items-center justify-between transition active:scale-[0.98]"
                :class="activeTab === 'org_stocks'
                    ? 'bg-white shadow-sm ring-1 ring-slate-200 text-slate-900'
                    : 'bg-white/60 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'org_stocks'"
            >
                <span class="font-medium">
                    <FontAwesomeIcon icon='fal fa-box' class='' fixed-width aria-hidden='true' />
                    {{ ctrans("Org Stocks") }}</span>
                <span class="text-xs text-gray-400">{{ results?.org_stocks?.length ?? 0 }}</span>
            </button>
            <button
                type="button"
                class="w-full p-3 rounded-md text-sm flex items-center justify-between transition active:scale-[0.98]"
                :class="activeTab === 'org_stock_families'
                    ? 'bg-white shadow-sm ring-1 ring-slate-200 text-slate-900'
                    : 'bg-white/60 text-slate-600 hover:bg-slate-100'"
                @click="activeTab = 'org_stock_families'"
            >
                <span class="font-medium inline text-left">
                    <FontAwesomeIcon icon='fal fa-boxes-alt' class='' fixed-width aria-hidden='true' />
                    <span class="inline ml-2">{{ ctrans("Org Stock Families") }}</span>
                </span>
                <span class="text-xs text-gray-400">{{ results?.org_stock_families?.length ?? 0 }}</span>
            </button>
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

            <template v-else-if="activeTab === 'org_stocks'">
                <div v-if="results?.org_stocks?.length">
                    <Link
                        v-for="orgStock in results.org_stocks"
                        :key="orgStock.id"
                        :href="route('grp.helpers.redirect_org_stock', [orgStock.id])"
                        class="block group p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm cursor-pointer mb-3"
                        @start="() => { model = false; loadingId = orgStock.id }"
                        @finish="() => loadingId = null"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold truncate min-w-0">{{ orgStock.code }}</p>
                            <span
                                class="shrink-0 text-[10px] px-2 py-0.5 rounded-full capitalize"
                                :class="orgStock.state === 'active' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ orgStock.state }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 truncate">{{ orgStock.name }}</p>
                    </Link>
                </div>
                <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">
                    {{ ctrans("No org stocks") }}
                </div>
            </template>

            <template v-else>
                <div v-if="results?.org_stock_families?.length">
                    <Link
                        v-for="family in results.org_stock_families"
                        :key="family.id"
                        :href="route('grp.org.warehouses.show.inventory.org_stock_families.show', {
                            organisation: routeParams.organisation,
                            warehouse: routeParams.warehouse,
                            orgStockFamily: family.slug,

                        })"
                        class="block p-4 rounded-md border border-transparent bg-slate-50 hover:border-slate-200 hover:bg-slate-150 hover:shadow-sm cursor-pointer mb-3"
                        @start="() => { model = false; loadingId = family.id }"
                        @finish="() => loadingId = null"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold truncate min-w-0">{{ family.code }}</p>
                            <span
                                class="shrink-0 text-[10px] px-2 py-0.5 rounded-full capitalize"
                                :class="family.state === 'active' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ family.state }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 truncate">{{ family.name }}</p>
                    </Link>
                </div>
                <div v-else class="flex h-full items-center justify-center text-gray-400 text-sm">
                    {{ ctrans("No org stock families") }}
                </div>
            </template>
        </div>
    </div>
</template>
