<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Mon, 06 Mar 2023 13:45:35 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { inject, ref, computed, defineAsyncComponent, watch } from 'vue'
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { debounce } from 'lodash-es'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { faTimes, faSearch, faSpinnerThird } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Skeleton from 'primevue/skeleton'
import LoadingIcon from './Utils/LoadingIcon.vue'

library.add(faTimes, faSearch, faSpinnerThird)

const SearchResultGeneric = defineAsyncComponent(() => import('@/Components/Search/SearchResultGeneric.vue'))

const scopeComponents: Record<string, ReturnType<typeof defineAsyncComponent>> = {
    sysadmin: defineAsyncComponent(() => import('@/Components/Search/SearchResultSysAdmin.vue')),
    catalogue: defineAsyncComponent(() => import('@/Components/Search/SearchResultCatalogue.vue')),
    customers: defineAsyncComponent(() => import('@/Components/Search/SearchResultCustomers.vue')),
    inventory: defineAsyncComponent(() => import('@/Components/Search/SearchResultOrgStocks.vue')),
    locations: defineAsyncComponent(() => import('@/Components/Search/SearchResultLocations.vue')),
    prospects: SearchResultGeneric,
    orders: SearchResultGeneric,
    reviews: SearchResultGeneric,
    accounting: SearchResultGeneric,
    dispatching: SearchResultGeneric,
    goods: SearchResultGeneric,
    supply_chain: SearchResultGeneric,
    billables: SearchResultGeneric,
    offers: SearchResultGeneric,
    marketing: SearchResultGeneric,
    website: SearchResultGeneric,
    master_shop: SearchResultGeneric,
    trade_units: SearchResultGeneric,
}

const isOpen = defineModel<boolean>()

const layout = inject('layout', layoutStructure)
const isLoadingSearch = ref(false)
const searchValue = ref('')
const scope = ref<string | null>(null)
const resultsSearch = ref<Record<string, any> | null>(null)
let abortController: AbortController | null = null

const sessionId = ref('')
const searchLogUlid = ref<string | null>(null)
const suggestions = ref<string[]>([])

watch(isOpen, async (open) => {
    if (!open) return
    sessionId.value = crypto.randomUUID()
    if (!suggestions.value.length) {
        try {
            const response = await fetch(`${urlSearch()}/suggestions`)
            suggestions.value = (await response.json()).suggestions ?? []
        } catch {
            suggestions.value = []
        }
    }
})

const searchSuggestion = (suggestion: string) => {
    searchValue.value = suggestion
    onTypeSearch()
}

const onResultsClick = (event: Event) => {
    const anchor = (event.target as HTMLElement).closest('a')
    if (!anchor?.href || !searchLogUlid.value) return
    window.axios.post(`${urlSearch()}/click`, {
        ulid: searchLogUlid.value,
        url: anchor.href,
    }).catch(() => {})
}

const activeComponent = computed(() => {
    return scope.value ? scopeComponents[scope.value] ?? null : null
})

const hasResults = computed(() => resultsSearch.value !== null)
const isInitialLoading = computed(() => isLoadingSearch.value && !hasResults.value)
const isRefreshing = computed(() => isLoadingSearch.value && hasResults.value)

const paramsToString = () => {
    return route().routeParams
        ? '&' + Object.entries(route().routeParams).map(([key, value]) => `${key}=${encodeURIComponent(String(value))}`).join('&')
        : ''
}

const urlSearch = () => {
    return layout.app.name == 'retina'
        ? `${location.origin}/app/search`
        : `${location.origin}/search`
}

let requestId = 0

const CACHE_TTL_MS = 30_000
const CACHE_MAX_ENTRIES = 50
const responseCache = new Map<string, { data: Record<string, any>, expiresAt: number }>()

const buildSearchUrl = (query: string) => {
    return `${urlSearch()}?q=${encodeURIComponent(query)}&session=${sessionId.value}&route_src=${route().current()}${paramsToString()}`
}

const cacheResponse = (url: string, data: Record<string, any>) => {
    if (responseCache.size >= CACHE_MAX_ENTRIES) {
        responseCache.delete(responseCache.keys().next().value as string)
    }
    responseCache.set(url, { data, expiresAt: Date.now() + CACHE_TTL_MS })
}

const getCachedResponse = (url: string): Record<string, any> | null => {
    const entry = responseCache.get(url)
    if (!entry) return null
    if (entry.expiresAt < Date.now()) {
        responseCache.delete(url)
        return null
    }
    return entry.data
}

const applyResponse = (data: Record<string, any>) => {
    scope.value = data.scope ?? null
    resultsSearch.value = data.results ?? null
    searchLogUlid.value = data.search_log_ulid ?? null
}

const fetchApi = debounce(async (query: string) => {
    const currentRequestId = ++requestId

    abortController?.abort()
    abortController = new AbortController()

    isLoadingSearch.value = true

    try {
        const url = buildSearchUrl(query)
        const response = await fetch(url, { signal: abortController.signal })
        const data = await response.json()
        if (currentRequestId !== requestId) return
        cacheResponse(url, data)
        applyResponse(data)
    } catch (e) {
        if ((e as DOMException).name === 'AbortError' || currentRequestId !== requestId) return
        resultsSearch.value = null
        scope.value = null
    } finally {
        if (currentRequestId === requestId) {
            isLoadingSearch.value = false
        }
    }
}, 250)

const resetSearchState = () => {
    requestId++
    fetchApi.cancel()
    abortController?.abort()
    resultsSearch.value = null
    scope.value = null
    isLoadingSearch.value = false
}

const onTypeSearch = () => {
    if (!searchValue.value.trim()) {
        resetSearchState()
        return
    }

    const cached = getCachedResponse(buildSearchUrl(searchValue.value))
    if (cached) {
        requestId++
        fetchApi.cancel()
        abortController?.abort()
        isLoadingSearch.value = false
        applyResponse(cached)
        return
    }

    isLoadingSearch.value = true
    fetchApi(searchValue.value)
}

const closeModal = () => {
    isOpen.value = false
    searchValue.value = ''
    resetSearchState()
}
</script>

<template>
    <TransitionRoot :show="isOpen" as="template" @after-leave="() => { searchValue = ''; resultsSearch = null }" appear>
        <Dialog as="div" class="relative z-50" @close="closeModal">

            <TransitionChild enter="ease-out duration-200" enter-from="opacity-0" enter-to="opacity-100"
                leave="ease-in duration-150" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-black/30 backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 flex items-start justify-center pt-16 px-4">
                <TransitionChild enter="ease-out duration-200" enter-from="opacity-0 scale-95"
                    enter-to="opacity-100 scale-100" leave="ease-in duration-150" leave-from="opacity-100 scale-100"
                    leave-to="opacity-0 scale-95">
                    <DialogPanel
                        class="w-full max-w-lg sm:max-w-2xl md:min-w-[60vw] lg:max-w-4xl xl:max-w-[1200px] h-[75vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">

                        <div class="border-b p-5 flex items-center gap-2">
                            <LoadingIcon v-if="isLoadingSearch" class="text-gray-400" />
                            <FontAwesomeIcon v-else icon="fal fa-search" class="text-gray-400" fixed-width />
                            <input
                                v-model="searchValue"
                                @input="onTypeSearch"
                                type="text"
                                class="h-12 w-full border-0 bg-transparent text-gray-900 placeholder:text-gray-400 focus:ring-0 sm:text-sm"
                                :placeholder="trans('Search...')"
                            />
                            <button @click="closeModal">
                                <FontAwesomeIcon icon="fal fa-times" class="text-lg" fixed-width />
                            </button>
                        </div>

                        <div v-if="!searchValue && !hasResults" class="flex flex-1 items-center justify-center text-center text-gray-400 p-6">
                            <div class="space-y-2">
                                <p class="text-base font-medium text-gray-500">{{ ctrans('Type to search...') }}</p>
                                <p class="text-sm">{{ ctrans('Search across orders, customers, prospects and more') }}</p>
                                <div v-if="suggestions.length" class="pt-4">
                                    <p class="text-xs text-gray-400 mb-2">{{ ctrans('Popular searches') }}</p>
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button
                                            v-for="suggestion in suggestions"
                                            :key="suggestion"
                                            type="button"
                                            class="px-3 py-1 rounded-full text-xs bg-slate-100 text-slate-600 hover:bg-slate-200 transition"
                                            @click="searchSuggestion(suggestion)"
                                        >
                                            {{ suggestion }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!isLoadingSearch && !activeComponent" class="flex flex-1 items-center justify-center text-gray-400 p-6">
                            <p class="text-sm">{{ ctrans('No results found') }}</p>
                        </div>

                        <div v-else-if="isInitialLoading && !activeComponent" class="grid grid-cols-12 flex-1 min-h-0">
                            <div class="col-span-3 border-r p-4 bg-gray-50 space-y-2">
                                <Skeleton height="2.5rem" borderRadius="0.75rem" />
                                <Skeleton height="2.5rem" borderRadius="0.75rem" />
                            </div>
                            <div class="col-span-9 p-4 space-y-4">
                                <div v-for="i in 5" :key="i" class="p-4 rounded-xl border bg-white">
                                    <Skeleton width="60%" height="1rem" class="mb-2" />
                                    <Skeleton width="80%" height="0.75rem" />
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="grid grid-cols-12 flex-1 min-h-0 overflow-hidden transition-opacity duration-200 [&>*]:min-w-0"
                            :class="isRefreshing ? 'opacity-60' : 'opacity-100'"
                            @click.capture="onResultsClick"
                        >
                            <component
                                v-model:open="isOpen"
                                :is="activeComponent"
                                :results="resultsSearch"
                                :is-loading="isInitialLoading"
                                :query="searchValue"
                            />
                        </div>

                    </DialogPanel>
                </TransitionChild>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
