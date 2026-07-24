<script setup lang='ts'>
import { inject, ref, computed, watch, onBeforeMount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { useLocaleStore } from '@/Stores/locale'
import { ctrans } from '@/Composables/useTrans'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import LoadingOverlay2 from '@/Components/Utils/LoadingOverlay2.vue'

interface InternalProduct {
    id: number
    code: string
    name: string
    image: any
    price?: number | string | null
    stock?: number | null
    units?: number | string | null
    unit?: string | null
    url?: string
}

interface InternalCatalogueItem {
    id: number
    code: string
    name: string
    image: any
    url?: string
}

interface InternalFacetItem {
    id: number
    name: string
    count: number
    image?: any
    url?: string
}

interface InternalFacets {
    departments: InternalFacetItem[]
    sub_departments: InternalFacetItem[]
    families: InternalFacetItem[]
    brands: InternalFacetItem[]
    tags: InternalFacetItem[]
    price: { min: number | null, max: number | null }
}

const emptyFacets = (): InternalFacets => ({
    departments: [],
    sub_departments: [],
    families: [],
    brands: [],
    tags: [],
    price: { min: null, max: null },
})

const layout = inject('layout', retinaLayoutStructure)

const page = usePage()
const searchQuery = computed(() => {
    const queryString = page.url.split('?')[1] ?? ''
    return new URLSearchParams(queryString).get('q') ?? ''
})

const products = ref<InternalProduct[]>([])
const facets = ref<InternalFacets>(emptyFacets())
const collections = ref<InternalCatalogueItem[]>([])
const totalResults = ref(0)
const currentPage = ref(1)
const perPage = 15

const selectedCategoryIds = ref<number[]>([])
const selectedBrandIds = ref<number[]>([])
const selectedTagIds = ref<number[]>([])
const priceMin = ref('')
const priceMax = ref('')
const sortBy = ref('')

const isInternalLoading = ref(false)
const isLoadingMore = ref(false)
const isResultsRefreshing = ref(false)
let internalAbort: AbortController | null = null
let internalRequestId = 0

const resetResults = () => {
    products.value = []
    facets.value = emptyFacets()
    collections.value = []
    totalResults.value = 0
    currentPage.value = 1
}

const fetchInternalResults = async ({ pageNumber = 1, append = false, resultsOnly = false } = {}) => {
    const query = searchQuery.value
    if (!query.trim()) {
        return
    }
    const requestId = ++internalRequestId
    internalAbort?.abort()
    internalAbort = new AbortController()
    if (append) {
        isLoadingMore.value = true
    } else if (resultsOnly) {
        isResultsRefreshing.value = true
    } else {
        isInternalLoading.value = true
    }
    try {
        const { data } = await axios.get(
            route('iris.json.search.catalogue_page'),
            {
                params: {
                    q: query,
                    page: pageNumber,
                    per_page: perPage,
                    categories: selectedCategoryIds.value,
                    brands: selectedBrandIds.value,
                    tags: selectedTagIds.value,
                    price_min: priceMin.value !== '' ? priceMin.value : undefined,
                    price_max: priceMax.value !== '' ? priceMax.value : undefined,
                    sort: sortBy.value || undefined,
                },
                signal: internalAbort.signal,
            }
        )
        if (requestId !== internalRequestId) {
            return
        }
        const results = data.results ?? {}
        products.value = append ? [...products.value, ...(results.products ?? [])] : (results.products ?? [])
        totalResults.value = results.total ?? 0
        currentPage.value = results.page ?? pageNumber
        if (!append && !resultsOnly) {
            facets.value = results.facets ?? emptyFacets()
            collections.value = results.collections ?? []
        }
    } catch (error) {
        if (axios.isCancel(error) || requestId !== internalRequestId) {
            return
        }
        if (!append && !resultsOnly) {
            resetResults()
        }
    } finally {
        if (requestId === internalRequestId) {
            isInternalLoading.value = false
            isLoadingMore.value = false
            isResultsRefreshing.value = false
        }
    }
}

watch(searchQuery, (query) => {
    selectedCategoryIds.value = []
    selectedBrandIds.value = []
    selectedTagIds.value = []
    priceMin.value = ''
    priceMax.value = ''
    sortBy.value = ''
    if (!query.trim()) {
        resetResults()
        return
    }
    fetchInternalResults()
})

onBeforeMount(() => {
    if (searchQuery.value.trim()) {
        fetchInternalResults()
    }
})

// Section: facet filters (checkbox/price changes refresh the product results only,
// the side panel keeps its facets so no skeleton flashes)
const facetSelections = {
    categories: selectedCategoryIds,
    brands: selectedBrandIds,
    tags: selectedTagIds,
} as const

type FacetSelectionKey = keyof typeof facetSelections

const isFacetSelected = (selection: FacetSelectionKey, id: number) =>
    facetSelections[selection].value.includes(id)

const toggleFacet = (selection: FacetSelectionKey, id: number) => {
    const current = facetSelections[selection].value
    facetSelections[selection].value = current.includes(id)
        ? current.filter((selectedId) => selectedId !== id)
        : [...current, id]
    fetchInternalResults({ resultsOnly: true })
}

const onPriceChange = () => {
    fetchInternalResults({ resultsOnly: true })
}

const onSortChange = () => {
    fetchInternalResults({ resultsOnly: true })
}

const hasActiveFilters = computed(() =>
    selectedCategoryIds.value.length > 0
    || selectedBrandIds.value.length > 0
    || selectedTagIds.value.length > 0
    || priceMin.value !== ''
    || priceMax.value !== ''
    || !!sortBy.value
)

const resetFilters = () => {
    if (!hasActiveFilters.value) {
        return
    }
    selectedCategoryIds.value = []
    selectedBrandIds.value = []
    selectedTagIds.value = []
    priceMin.value = ''
    priceMax.value = ''
    sortBy.value = ''
    fetchInternalResults({ resultsOnly: true })
}

const loadMore = () => {
    fetchInternalResults({ pageNumber: currentPage.value + 1, append: true })
}

const facetGroups = computed(() => [
    { key: 'families', label: ctrans('Categories'), selection: 'categories' as const, items: facets.value.families },
    { key: 'departments', label: ctrans('Departments'), selection: 'categories' as const, items: facets.value.departments },
    { key: 'sub_departments', label: ctrans('Sub Departments'), selection: 'categories' as const, items: facets.value.sub_departments },
    { key: 'brands', label: ctrans('Brands'), selection: 'brands' as const, items: facets.value.brands },
    { key: 'tags', label: ctrans('Tags'), selection: 'tags' as const, items: facets.value.tags },
].filter((group) => group.items.length))

const showPriceFacet = computed(() =>
    !!layout.iris?.is_logged_in && facets.value.price?.max !== null
)

const localeStore = useLocaleStore()
const formatPrice = (price?: number | string | null) => {
    if (price === null || price === undefined || price === '') return null
    return localeStore.currencyFormat(layout.iris?.currency?.code, Number(price))
}

// Method: from 'Gemstone Obelisk Points approx 5cm - African Amethyst' to '[5x] Gemstone Obelisk Points approx 5cm - African Amethyst'
const getProductName = (product: { name: string; units?: number | string | null }): string => {
    const units = Number(product.units) || 1
    if (units === 1) {
        return product.name
    } else {
        return `[${units}x] ${product.name}`
    }
}

// Price sent from the backend is for the whole pack of `units`; show the per-unit price.
const getProductPrice = (product: { price?: number | string | null; unit?: string | null; units?: number | string | null }): string | null => {
    if (product.price === null || product.price === undefined || product.price === '') {
        return null
    }

    const units = Number(product.units) || 1
    const price = formatPrice(Number(product.price) / units)
    if (!price) return null
    if (product.unit) {
        return `${price}/${product.unit}`
    }
    return `${price}`
}

// Section: quick searches (Luigi's Box lookalike tabs + card rail)
type RailItem = InternalFacetItem | InternalCatalogueItem

const activeQuickSearch = ref<'category' | 'department' | 'sub_department' | 'tag' | 'collection'>('category')
const quickSearchTabs = computed(() => [
    { key: 'category' as const, label: ctrans('Categories'), items: facets.value.families as RailItem[] },
    { key: 'department' as const, label: ctrans('Departments'), items: facets.value.departments as RailItem[] },
    { key: 'sub_department' as const, label: ctrans('Sub Departments'), items: facets.value.sub_departments as RailItem[] },
    { key: 'tag' as const, label: ctrans('Tags'), items: facets.value.tags as RailItem[] },
    { key: 'collection' as const, label: ctrans('Collections'), items: collections.value as RailItem[] },
].filter((tab) => tab.items.length))

watch(quickSearchTabs, (tabs) => {
    if (tabs.length && !tabs.some((tab) => tab.key === activeQuickSearch.value)) {
        activeQuickSearch.value = tabs[0].key
    }
})

const activeRailItems = computed(() =>
    quickSearchTabs.value.find((tab) => tab.key === activeQuickSearch.value)?.items ?? []
)

// Tags have no storefront page; clicking a tag card toggles its facet filter instead
const onRailItemClick = (item: RailItem) => {
    if (activeQuickSearch.value === 'tag') {
        toggleFacet('tags', item.id)
    }
}

// Reuse the global rail helpers from app-iris.blade.php (lb-qs / data-lb-rail hooks)
const scrollRail = (event: Event, direction: number) => {
    const button = event.currentTarget as HTMLElement
    const lbQuickSearchScroll = (window as any).lbQuickSearchScroll
    if (typeof lbQuickSearchScroll === 'function') {
        lbQuickSearchScroll(button, direction)
        return
    }
    const rail = button.closest('.lb-qs')?.querySelector('[data-lb-rail]')
    rail?.scrollBy({ left: direction * 300, behavior: 'smooth' })
}

const isMobileFilterOpen = ref(false)

const visitingProductId = ref<number | null>(null)
</script>

<template>
    <div id="lb-search-element">
        <div v-if="layout.app.environment === 'local'" class="bg-yellow-500 w-full text-center py-1 rounded">
            Internal search
        </div>
        <div class="antialiased box-border pt-[30px] pb-[30px] md:pb-[50px]" :style="{
            fontFamily: layout?.app?.webpage_layout?.container?.properties?.text?.fontFamily
        }">
            <!-- <div id="results-scroll-to"></div> -->
            <div class="box-border flex flex-col md:flex-row items-stretch gap-6 md:gap-0">
                <!-- Aside: category facets with product counts (checkbox filters) -->
                <aside class="w-full md:w-[300px] flex-shrink-0 md:border-r md:border-[#e8e8e8] md:pr-5"
                    :class="isMobileFilterOpen ? 'block' : 'hidden md:block'">
                    <div class="text-[26px] leading-[1.2em] font-bold mb-2.5">{{ ctrans('Filters') }}</div>
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <!-- <div class="text-sm text-[#767676]">{{ totalResults }} {{ ctrans('results') }}</div> -->
                        <button v-if="hasActiveFilters" type="button"
                            class="text-sm underline hover:no-underline text-red-500 cursor-pointer"
                            @click="resetFilters">
                            {{ ctrans('Cancel all filters') }}
                        </button>
                    </div>

                    <template v-if="isInternalLoading">
                        <div class="border-t border-[#e8e8e8] pt-5 space-y-2">
                            <div v-for="i in 6" :key="i" class="h-4 w-4/5 skeleton rounded"></div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="border-t border-[#e8e8e8] pt-5 space-y-6">
                            <div v-for="group in facetGroups" :key="group.key" class="border-b border-[#e8e8e8] pb-5">
                                <p class="text-base font-bold text-[var(--theme-color-0)] mb-2.5">
                                    {{ group.label }} ({{ group.items.length }})</p>
                                <div class="space-y-1.5">
                                    <label v-for="item in group.items" :key="item.id"
                                        class="flex items-center gap-2.5 cursor-pointer text-sm text-[#484848] hover:text-[var(--theme-color-0)] transition-colors">
                                        <input type="checkbox"
                                            class="h-4 w-4 flex-shrink-0 rounded-sm accent-[var(--theme-color-0)] cursor-pointer"
                                            :checked="isFacetSelected(group.selection, item.id)"
                                            @change="toggleFacet(group.selection, item.id)" />
                                        <span class="">{{ item.name }} ({{ item.count }})</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Facet: price range (hidden for guests, they can't see prices) -->
                            <div v-if="showPriceFacet" class="border-b border-[#e8e8e8] pb-5">
                                <p class="text-base font-bold text-[var(--theme-color-0)] mb-2.5">
                                    {{ ctrans('Price') }}</p>
                                <div class="text-sm text-[#767676] mb-2">
                                    {{ ctrans('From') }} {{ formatPrice(facets.price.min) }} - {{ ctrans('to') }} {{ formatPrice(facets.price.max) }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" v-model="priceMin" min="0" step="0.01"
                                        :placeholder="String(facets.price.min ?? '')"
                                        :aria-label="ctrans('From')"
                                        @change="onPriceChange"
                                        class="w-full min-w-0 text-sm border border-[#9e9e9e] rounded-sm px-2 py-1.5 focus:border-[var(--theme-color-0)] focus:ring-[var(--theme-color-0)]" />
                                    <span class="text-[#767676]">-</span>
                                    <input type="number" v-model="priceMax" min="0" step="0.01"
                                        :placeholder="String(facets.price.max ?? '')"
                                        :aria-label="ctrans('to')"
                                        @change="onPriceChange"
                                        class="w-full min-w-0 text-sm border border-[#9e9e9e] rounded-sm px-2 py-1.5 focus:border-[var(--theme-color-0)] focus:ring-[var(--theme-color-0)]" />
                                </div>
                            </div>

                            <p v-if="!facetGroups.length" class="text-sm text-gray-400">
                                {{ ctrans('No categories found') }}
                            </p>
                        </div>
                    </template>
                </aside>

                <!-- Section: Results -->
                <main class="box-border md:w-[72%] md:pl-5 lg:pl-[60px] flex-1 min-w-0">
                    <div class="text-[22px] md:text-[26px] leading-none font-normal mb-[30px]">
                        {{ ctrans('Results for') }}
                        <strong class="text-[var(--theme-color-0)]">{{ searchQuery }}</strong>
                        <span v-if="!isInternalLoading"> ({{ totalResults }})</span>
                    </div>

                    <!-- Quick searches: tabs + card rail -->
                    <div v-if="!isInternalLoading && quickSearchTabs.length" class="mb-4">
                        <!-- Section: Result box categories, collections, etc -->
                        <div class="mb-2 pb-2 flex flex-wrap justify-end border-b border-[color-mix(in_srgb,var(--iris-color-0)_30%,transparent)]">
                            <div v-for="tab in quickSearchTabs" :key="tab.key"
                                class="m-0 flex-grow text-center max-w-64 px-2 py-1 text-base rounded cursor-pointer mt-2.5 mr-0 -mb-px ml-5 text-[18px] font-normal border border-[color-mix(in_srgb,var(--iris-color-0)_40%,transparent)]"
                                :class="activeQuickSearch === tab.key ? 'bg-[color-mix(in_srgb,var(--iris-color-0)_10%,transparent)]' : 'text-[#767676] hover:text-black'"
                                @click="activeQuickSearch = tab.key">
                                <strong>{{ tab.label }}</strong> ({{ tab.items.length }})
                            </div>
                        </div>
                        
                        <!-- Section: Slider result of box categories, collections, etc -->
                        <div v-if="activeRailItems.length" class="lb-qs relative mt-3">
                            <!-- Left arrow -->
                            <button type="button" aria-label="Scroll left"
                                class="hidden sm:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white/90 shadow-sm hover:bg-white hover:shadow transition disabled:opacity-40 disabled:hover:shadow-sm disabled:hover:bg-white/90"
                                @click="scrollRail($event, -1)">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-700">
                                    <path fill-rule="evenodd"
                                        d="M12.707 15.707a1 1 0 0 1-1.414 0l-5-5a1 1 0 0 1 0-1.414l5-5a1 1 0 1 1 1.414 1.414L8.414 10l4.293 4.293a1 1 0 0 1 0 1.414z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </button>

                            <!-- Right arrow -->
                            <button type="button" aria-label="Scroll right"
                                class="hidden sm:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white/90 shadow-sm hover:bg-white hover:shadow transition disabled:opacity-40 disabled:hover:shadow-sm disabled:hover:bg-white/90"
                                @click="scrollRail($event, 1)">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-700">
                                    <path fill-rule="evenodd"
                                        d="M7.293 4.293a1 1 0 0 1 1.414 0l5 5a1 1 0 0 1 0 1.414l-5 5a1 1 0 1 1-1.414-1.414L11.586 10 7.293 5.707a1 1 0 0 1 0-1.414z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </button>

                            <!-- Rail -->
                            <div data-lb-rail role="list"
                                class="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory px-1 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:px-10">
                                <component
                                    v-for="item in activeRailItems" :key="item.id"
                                    :is="item.url ? LinkIris : 'button'"
                                    :href="item.url || undefined"
                                    :type="item.url ? undefined : 'button'"
                                    role="listitem"
                                    class="snap-start shrink-0 w-[200px] overflow-hidden rounded-lg border bg-white shadow-sm hover:shadow-md transition text-left"
                                    :class="!item.url && isFacetSelected('tags', item.id)
                                        ? 'border-[var(--theme-color-0)] ring-1 ring-[var(--theme-color-0)]'
                                        : 'border-gray-200'"
                                    @click="onRailItemClick(item)">
                                    <!-- Image (full cover, square) -->
                                    <div class="relative w-full aspect-square">
                                        <Image v-if="item.image" :src="item.image"
                                            class="absolute inset-0 h-full w-full object-cover" />
                                        <span v-else
                                            class="absolute inset-0 flex items-center justify-center opacity-30 text-3xl md:text-5xl">
                                            <svg class="h-[1em] w-[1em]" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor">
                                                <path d="M464 64H48C21.49 64 0 85.49 0 112v288c0 26.51 21.49 48 48 48h416c26.51 0 48-21.49 48-48V112c0-26.51-21.49-48-48-48zm16 336c0 8.822-7.178 16-16 16H48c-8.822 0-16-7.178-16-16V112c0-8.822 7.178-16 16-16h416c8.822 0 16 7.178 16 16v288zM112 232c30.928 0 56-25.072 56-56s-25.072-56-56-56-56 25.072-56 56 25.072 56 56 56zm0-80c13.234 0 24 10.766 24 24s-10.766 24-24 24-24-10.766-24-24 10.766-24 24-24zm207.029 23.029L224 270.059l-31.029-31.029c-9.373-9.373-24.569-9.373-33.941 0l-88 88A23.998 23.998 0 0 0 64 344v28c0 6.627 5.373 12 12 12h360c6.627 0 12-5.373 12-12v-92c0-6.365-2.529-12.47-7.029-16.971l-88-88c-9.373-9.372-24.569-9.372-33.942 0zM416 352H96v-4.686l80-80 48 48 112-112 80 80V352z"/>
                                            </svg>
                                        </span>
                                    </div>

                                    <!-- Title bar (no white gap, centered) -->
                                    <div
                                        class="bg-gray-100 px-3 text-sm font-semibold text-gray-800 text-center flex items-center justify-center h-[52px] leading-snug">
                                        <span class="line-clamp-2">{{ item.name }}</span>
                                    </div>
                                </component>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile filter toggle + sorting -->
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <button type="button"
                            class="md:hidden rounded-sm font-bold py-[13px] px-2.5 min-w-[100px] text-center bg-[var(--theme-color-0)] text-white hover:brightness-90 transition"
                            @click="isMobileFilterOpen = !isMobileFilterOpen">
                            {{ ctrans('Filters') }}
                        </button>
                        <div v-if="layout.iris?.is_logged_in" class="flex items-center gap-2 xml-auto">
                            <span class="text-sm font-bold">{{ ctrans('Sort by') }}: </span>
                            <select v-model="sortBy" :aria-label="ctrans('Sort by')" @change="onSortChange"
                                class="text-sm bg-white border-0 border-b border-black rounded-none py-[5px] w-40 outline-none">
                                <option value="">{{ ctrans('Default') }}</option>
                                <option value="price_amount:asc">{{ ctrans('Price: Low to High') }}</option>
                                <option value="price_amount:desc">{{ ctrans('Price: High to Low') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Section: Loading skeleton -->
                    <div v-if="isInternalLoading"
                        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <div v-for="i in 10" :key="i">
                            <div class="aspect-square skeleton rounded mb-2"></div>
                            <div class="h-4 w-4/5 skeleton rounded mb-1.5"></div>
                            <div class="h-4 w-2/5 skeleton rounded"></div>
                        </div>
                    </div>

                    <!-- Section: Results -->
                    <div v-else-if="products.length"
                        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 transition-opacity"
                        :class="isResultsRefreshing ? 'opacity-60 pointer-events-none' : ''">
                        <LinkIris v-for="product in products" :key="product.id" :href="product.url"
                            class="relative group text-gray-800 isolate h-full flex flex-col flex-grow no-underline"
                            @start="visitingProductId = product.id"
                            @error="visitingProductId = null"
                            @finish="visitingProductId = null"
                        >
                            <LoadingOverlay2 v-if="visitingProductId === product.id" class="z-20 rounded" />
                            <!-- Product detail: image -->
                            <div class="relative block w-full mb-1 rounded overflow-hidden aspect-square bg-white">
                                <Image v-if="product.image" :src="product.image"
                                    xass="w-full h-full object-contain object-center"
                                    cclass="product.stock === 0
                                        ? 'grayscale opacity-60'
                                        : ''
                                    "
                                />
                                <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold uppercase text-4xl"> {{ product.code?.slice(0, 3) }}</div>
                                <div v-if="product.stock === 0" class="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
                                    <span class="w-full bg-white/95 border border-red-500 rounded-sm text-red-500 text-xs font-bold uppercase tracking-wider py-1 text-center shadow-sm">{{ ctrans('Out of stock') }}</span>
                                </div>
                            </div>

                            <!-- Product detail: code and name -->
                            <div class="mt-2 flex-1 flex flex-col border-b border-gray-200 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">{{ product.code }}</span>
                                    <span class="shrink-0 font-medium leading-snug text-[0.85rem]" :class="product.stock === 0 ? 'text-[#ea1414]' : 'text-[#10ad29]'">●</span>
                                </div>
                                <div class="font-bold !text-sm leading-4 mt-1 line-clamp-2 text-justify group-hover:underline">
                                    {{ getProductName(product) }}
                                </div>
                            </div>
                            
                            <!-- Product detail: price -->
                            <div class="font-sans mt-2 mb-1 tabular-nums leading-none text-[11px] lg:text-[12px]">
                                <div v-if="getProductPrice(product)" class="grid grid-cols-[auto_1fr] items-center gap-x-2">
                                    <div class="font-semibold whitespace-nowrap">{{ ctrans('Price') }}</div>
                                    <div class="font-bold text-right min-w-0 whitespace-nowrap">
                                        {{ formatPrice(Number(product.price)) }}
                                        <span v-if="Number(product.units) !== 1" class="font-normal opacity-80">({{ getProductPrice(product) }})</span>
                                    </div>
                                </div>
                                <a v-else-if="!layout.iris?.is_logged_in" href="/app/login" class="block text-[0.7rem] underline text-[var(--theme-color-0)]">
                                    {{ ctrans('Login for prices') }}
                                </a>
                            </div>
                        </LinkIris>
                    </div>

                    <div v-else class="flex h-40 items-center justify-center text-[#767676] bg-[#ececec] rounded-sm p-2.5 md:p-5 transition-opacity"
                        :class="isResultsRefreshing ? 'opacity-60' : ''">
                        {{ ctrans("We couldn't find any suitable results") }}
                    </div>

                    <!-- Pagination: load more + info -->
                    <div v-if="!isInternalLoading && products.length" class="pt-[30px] text-center">
                        <button v-if="products.length < totalResults" type="button"
                            class="rounded-sm font-bold py-[15px] px-2.5 w-[300px] max-w-full text-center bg-[var(--theme-color-0)] text-white hover:brightness-90 transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="isLoadingMore"
                            @click="loadMore">
                            {{ isLoadingMore ? ctrans('Loading ...') : ctrans('Load more') }}
                        </button>
                        <div class="pt-[25px] text-[#767676]">
                            1 - {{ products.length }} {{ ctrans('of') }} {{ totalResults }} {{ ctrans('results') }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
