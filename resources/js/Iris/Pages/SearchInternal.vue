<script setup lang='ts'>
import { inject, ref, computed, watch, onBeforeMount, onMounted, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { get } from 'lodash-es'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { useLocaleStore } from '@/Stores/locale'
import { ctrans } from '@/Composables/useTrans'
import { getStyles } from '@/Composables/styles'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import RenderProduct from '@/Iris/Components/IrisBlocks/Products/Ecom/RenderProduct.vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { FreeMode } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/free-mode'
import { ProductResource } from '@/types/Iris/Products'

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

// Section: product card, `web_block_family_code` is the website's products web block
// (products-1 / products-2) and `web_block_family` its settings, both taken from the family page
// so a search hit renders exactly like it does in its family
const props = defineProps<{
    web_block_family?: {
        fieldValue?: Record<string, any>
    }
    web_block_family_code?: string
}>()

const fieldValue = computed(() => props.web_block_family?.fieldValue)
const productCardCode = computed(() => props.web_block_family_code || 'products-1')

const layout = inject('layout', retinaLayoutStructure)

const page = usePage()
const searchQuery = computed(() => {
    const queryString = page.url.split('?')[1] ?? ''
    return new URLSearchParams(queryString).get('q') ?? ''
})

const products = ref<ProductResource[]>([])
const searchLogUlid = ref<string | null>(null)

// keepalive fetch survives the navigation the click triggers
const recordClick = (url?: string | null) => {
    if (!searchLogUlid.value || !url) return
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')
    fetch(route('iris.json.search.click'), {
        method: 'POST',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify({ ulid: searchLogUlid.value, url }),
    }).catch(() => {})
}
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
                    source: 'search_page',
                },
                signal: internalAbort.signal,
            }
        )
        if (requestId !== internalRequestId) {
            return
        }
        const results = data.results ?? {}
        searchLogUlid.value = data.search_log_ulid ?? searchLogUlid.value
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

// Section: product cards (same grid settings as the family page block)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')

const checkScreenType = () => {
    if (typeof window === 'undefined') return
    screenType.value = window.innerWidth < 640 ? 'mobile' : window.innerWidth < 1024 ? 'tablet' : 'desktop'
}

onMounted(() => {
    checkScreenType()
    window.addEventListener('resize', checkScreenType)
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') return
    window.removeEventListener('resize', checkScreenType)
})

const gridColsVars = computed(() => {
    const perRow = fieldValue.value?.settings?.per_row ?? {}
    const basketOpen = layout.rightbasket?.show

    return {
        '--cols-mobile': basketOpen ? 2 : (perRow.mobile ?? 2),
        '--cols-tablet': basketOpen ? 3 : (perRow.tablet ?? 3),
        '--cols-desktop': basketOpen ? 3 : (perRow.desktop ?? 4),
    }
})

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

// The rail is a free mode Swiper: draggable, with arrows that disable themselves at both ends
const railSwiper = ref<any>(null)
const railAtStart = ref(true)
const railAtEnd = ref(false)

const updateRailEdges = (swiper: any) => {
    railAtStart.value = swiper.isBeginning
    railAtEnd.value = swiper.isEnd
}

const onRailSwiper = (swiper: any) => {
    railSwiper.value = swiper
    updateRailEdges(swiper)
}

const scrollRail = (direction: number) => {
    if (direction < 0) {
        railSwiper.value?.slidePrev()
    } else {
        railSwiper.value?.slideNext()
    }
}

const isMobileFilterOpen = ref(false)
</script>

<template>
    <div class="xmd:py-16 w-full mx-auto px-8">
        <div id="lb-search-element" class="md:mt-4 min-h-44">
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
                    <main class="box-border md:w-[72%] md:pl-5 lg:pl-7 flex-1 min-w-0">
                        <div class="text-[22px] md:text-[26px] leading-none font-normal lg:mb-[30px]">
                            {{ ctrans('Results for') }}
                            <strong class="text-[var(--theme-color-0)]">{{ searchQuery }}</strong>
                            <span v-if="!isInternalLoading"> ({{ totalResults }})</span>
                        </div>

                        <!-- Quick searches: tabs + card rail -->
                        <div v-if="!isInternalLoading && quickSearchTabs.length" class="my-4">
                            <!-- Section: Result box categories, collections, etc -->
                            <div class="mb-2 pb-2 flex flex-wrap justify-end gap-y-2.5 gap-x-3 border-b border-[color-mix(in_srgb,var(--iris-color-0)_30%,transparent)]">
                                <div v-for="tab in quickSearchTabs" :key="tab.key"
                                    class="m-0 flex-grow text-center max-w-64 px-2 py-1 text-base rounded cursor-pointer text-[18px] font-normal border border-[color-mix(in_srgb,var(--iris-color-0)_40%,transparent)]"
                                    :class="activeQuickSearch === tab.key ? 'bg-[color-mix(in_srgb,var(--iris-color-0)_10%,transparent)]' : 'text-[#767676] hover:text-black'"
                                    @click="activeQuickSearch = tab.key">
                                    <strong>{{ tab.label }}</strong> ({{ tab.items.length }})
                                </div>
                            </div>

                            <!-- Section: Slider result of box categories, collections, etc -->
                            <div v-if="activeRailItems.length" class="relative mt-3">
                                <!-- Left arrow -->
                                <button type="button" aria-label="Scroll left" :disabled="railAtStart"
                                    class="hidden sm:flex absolute left-0 top-1/2 -translate-y-1/2 z-10 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white/90 shadow-sm hover:bg-white hover:shadow transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:shadow-sm disabled:hover:bg-white/90"
                                    @click="scrollRail(-1)">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-700">
                                        <path fill-rule="evenodd"
                                            d="M12.707 15.707a1 1 0 0 1-1.414 0l-5-5a1 1 0 0 1 0-1.414l5-5a1 1 0 1 1 1.414 1.414L8.414 10l4.293 4.293a1 1 0 0 1 0 1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </button>

                                <!-- Right arrow -->
                                <button type="button" aria-label="Scroll right" :disabled="railAtEnd"
                                    class="hidden sm:flex absolute right-0 top-1/2 -translate-y-1/2 z-10 h-9 w-9 items-center justify-center rounded-full border border-gray-200 bg-white/90 shadow-sm hover:bg-white hover:shadow transition disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:shadow-sm disabled:hover:bg-white/90"
                                    @click="scrollRail(1)">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-gray-700">
                                        <path fill-rule="evenodd"
                                            d="M7.293 4.293a1 1 0 0 1 1.414 0l5 5a1 1 0 0 1 0 1.414l-5 5a1 1 0 1 1-1.414-1.414L11.586 10 7.293 5.707a1 1 0 0 1 0-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                </button>

                                <!-- Rail -->
                                <Swiper
                                    :key="activeQuickSearch"
                                    slides-per-view="auto"
                                    :space-between="16"
                                    :free-mode="true"
                                    :modules="[FreeMode]"
                                    class="w-full px-1 pb-2 sm:px-10"
                                    @swiper="onRailSwiper"
                                    @progress="updateRailEdges"
                                    @slide-change="updateRailEdges"
                                    @resize="updateRailEdges">
                                    <SwiperSlide v-for="item in activeRailItems" :key="item.id" class="!w-[125px] lg:!w-[200px]">
                                        <component
                                            :is="item.url ? LinkIris : 'button'"
                                            :href="item.url || undefined"
                                            :type="item.url ? undefined : 'button'"
                                            class="block w-full overflow-hidden rounded-lg border bg-white shadow-sm hover:shadow-md transition text-left"
                                            :class="!item.url && isFacetSelected('tags', item.id)
                                                ? 'border-[var(--theme-color-0)] ring-1 ring-[var(--theme-color-0)]'
                                                : 'border-gray-200'"
                                            @click="onRailItemClick(item)"
                                        >
                                            <!-- Image (full cover, square) -->
                                            <div class="relative w-full aspect-square">
                                                <Image v-if="item.image" :src="item.image" class="absolute inset-0 h-full w-full object-cover" />
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
                                    </SwiperSlide>
                                </Swiper>
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
                        <div v-if="isInternalLoading" xstyle="gridColsVars" class="xproducts-grid grid-cols-4 grid gap-x-6 gap-y-10">
                            <div v-for="i in 10" :key="i">
                                <div class="aspect-square skeleton rounded mb-2"></div>
                                <div class="h-4 w-4/5 skeleton rounded mb-1.5"></div>
                                <div class="h-4 w-2/5 skeleton rounded"></div>
                            </div>
                        </div>

                        <!-- Section: Results (same product card as the family page) -->
                        <div v-else-if="products.length" xstyle="gridColsVars"
                            class="xproducts-grid grid-cols-1 lg:grid-cols-4 grid gap-x-4 gap-y-10 transition-opacity bg-gray-100 p-4 rounded-md"
                            :class="isResultsRefreshing ? 'opacity-60 pointer-events-none' : ''">
                            <div v-for="product in products" :key="product.id"
                                :style="getStyles(fieldValue?.card_product?.properties, screenType)"
                                class="relative rounded flex md:flex-1 justify-center"
                                @click.capture="() => recordClick(product.url)">
                                <RenderProduct :code="productCardCode" :product="product"
                                    :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                    :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType) ?? undefined"
                                    :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false)"
                                    :button="fieldValue?.button"
                                    :hasInBasketList="get(layout, ['family_page', 'productInBasket', 'list'], [])"
                                    :bestSeller="fieldValue?.bestseller" :screenType />
                            </div>
                        </div>

                        <div v-else class="flex h-40 items-center justify-center text-[#767676] bg-[#ececec] rounded-sm p-2.5 md:p-5 transition-opacity"
                            :class="isResultsRefreshing ? 'opacity-60' : ''">
                            {{ ctrans("We couldn't find any suitable results") }}
                        </div>

                        <!-- Pagination: load more + info -->
                        <div v-if="!isInternalLoading && products.length" class="lg:pt-[30px] text-center">
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
    </div>
</template>

<style scoped>
.products-grid {
    grid-template-columns: repeat(var(--cols-mobile), minmax(0, 1fr));
}

@media (min-width: 640px) {
    .products-grid {
        grid-template-columns: repeat(var(--cols-tablet), minmax(0, 1fr));
    }
}

@media (min-width: 1024px) {
    .products-grid {
        grid-template-columns: repeat(var(--cols-desktop), minmax(0, 1fr));
    }
}
</style>
