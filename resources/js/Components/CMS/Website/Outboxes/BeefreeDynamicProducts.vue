<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSearch, faCheck, faTimes, faImage, faBoxOpen, faChevronLeft, faChevronRight } from '@fal'
import Dialog from 'primevue/dialog'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { ctrans } from '@/Composables/useTrans'
import { readableTextColor } from './EmailWorkshop/emailWorkshopBlocks'

library.add(faSearch, faCheck, faTimes, faImage, faBoxOpen, faChevronLeft, faChevronRight)

const PRODUCT_TABS = {
    PRODUCTS: 'products',
    NEW_IN: 'new_in',
    TRENDING: 'trending',
    COLLECTION_FAMILY: 'collection_family',
} as const

const TIME_FILTERS = {
    WEEK: 'week',
    MONTH: 'month',
    YEAR: 'year',
} as const

const PER_PAGE = 12
const SEARCH_DEBOUNCE_MS = 300
const MAX_SELECTED_PRODUCTS = 12

const props = defineProps<{
    shopSlug?: string
    shopId?: number
    organisationSlug: string
}>()

interface DynamicProduct {
    id: number
    code: string
    name: string | null
    description: string | null
    product_image: string | null
    url: string | null
}

const isOpen = ref(false)
const activeTab = ref<string>(PRODUCT_TABS.PRODUCTS)
const searchQuery = ref('')
const timeFilter = ref<string>(TIME_FILTERS.WEEK)
const selectedCollection = ref<string>('')
const selectedFamily = ref<string>('')
const selectedSubDepartment = ref<string>('')

const results = ref<DynamicProduct[]>([])
const isLoading = ref(false)
const currentPage = ref(1)
const totalPages = ref(0)
const totalItems = ref(0)

const selectedProducts = ref<DynamicProduct[]>([])
const productsPerRow = ref(2)
const showDescription = ref(true)
const buttonLabel = ref('SHOP NOW')
const buttonColor = ref('#1d252e')

let resolveSelection: ((value: Record<string, any>) => void) | null = null
let wantsProductData = false
let rejectSelection: (() => void) | null = null
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null
let latestRequestId = 0

const tabs = computed(() => [
    { key: PRODUCT_TABS.PRODUCTS, label: ctrans('All products') },
    { key: PRODUCT_TABS.NEW_IN, label: ctrans('New in') },
    { key: PRODUCT_TABS.TRENDING, label: ctrans('Trending') },
    { key: PRODUCT_TABS.COLLECTION_FAMILY, label: ctrans('By category') },
])

const timeFilters = computed(() => [
    { key: TIME_FILTERS.WEEK, label: ctrans('This week') },
    { key: TIME_FILTERS.MONTH, label: ctrans('This month') },
    { key: TIME_FILTERS.YEAR, label: ctrans('This year') },
])

const hasActiveFilters = computed(() => !!(selectedCollection.value || selectedFamily.value || selectedSubDepartment.value))
const isSelected = (product: DynamicProduct) => selectedProducts.value.some((selected) => selected.id === product.id)
const selectionIndex = (product: DynamicProduct) => selectedProducts.value.findIndex((selected) => selected.id === product.id) + 1
const isSelectionFull = computed(() => selectedProducts.value.length >= MAX_SELECTED_PRODUCTS)

const firstItemNumber = computed(() => results.value.length ? (currentPage.value - 1) * PER_PAGE + 1 : 0)
const lastItemNumber = computed(() => (currentPage.value - 1) * PER_PAGE + results.value.length)

const entityFetchRoutes = computed(() => ({
    family: { name: 'grp.json.shop.families', parameters: { shop: props.shopId } },
    subDepartment: { name: 'grp.json.shop.sub_departments', parameters: { shop: props.shopId } },
    collection: { name: 'grp.json.shop.catalogue.collections', parameters: { shop: props.shopSlug, scope: props.shopSlug } },
}))

const searchParams = (page: number): Record<string, any> => {
    const params: Record<string, any> = {
        search: searchQuery.value.trim(),
        tab_type: activeTab.value,
        per_page: PER_PAGE,
        page,
    }
    if (activeTab.value === PRODUCT_TABS.TRENDING) {
        params.time_filter = timeFilter.value
    }
    if (activeTab.value === PRODUCT_TABS.COLLECTION_FAMILY) {
        params.collection_id = selectedCollection.value ? parseInt(selectedCollection.value) : null
        params.family_id = selectedFamily.value ? parseInt(selectedFamily.value) : null
        params.sub_department_id = selectedSubDepartment.value ? parseInt(selectedSubDepartment.value) : null
    }

    return params
}

const searchProducts = async (page = 1) => {
    if (!props.shopSlug) {
        results.value = []
        return
    }

    const requestId = ++latestRequestId
    isLoading.value = true
    try {
        const { data } = await axios.get(route('grp.json.shop.products_beefree_search', { shop: props.shopSlug }), { params: searchParams(page) })
        if (requestId !== latestRequestId) {
            return
        }
        results.value = data?.data ?? []
        currentPage.value = data?.meta?.current_page ?? page
        totalPages.value = data?.meta?.last_page ?? 1
        totalItems.value = data?.meta?.total ?? results.value.length
    } catch {
        if (requestId !== latestRequestId) {
            return
        }
        results.value = []
        currentPage.value = 1
        totalPages.value = 0
        totalItems.value = 0
    } finally {
        if (requestId === latestRequestId) {
            isLoading.value = false
        }
    }
}

const onSearchInput = () => {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer)
    }
    searchDebounceTimer = setTimeout(() => searchProducts(1), SEARCH_DEBOUNCE_MS)
}

const switchTab = (tab: string) => {
    if (activeTab.value === tab) {
        return
    }
    activeTab.value = tab
    searchProducts(1)
}

const changeTimeFilter = (filter: string) => {
    timeFilter.value = filter
    searchProducts(1)
}

watch([selectedCollection, selectedFamily, selectedSubDepartment], () => {
    if (activeTab.value === PRODUCT_TABS.COLLECTION_FAMILY) {
        searchProducts(1)
    }
})

const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        searchProducts(page)
    }
}

const toggleProduct = (product: DynamicProduct) => {
    if (isSelected(product)) {
        selectedProducts.value = selectedProducts.value.filter((selected) => selected.id !== product.id)
        return
    }
    if (!isSelectionFull.value) {
        selectedProducts.value = [...selectedProducts.value, product]
    }
}

const removeSelected = (product: DynamicProduct) => {
    selectedProducts.value = selectedProducts.value.filter((selected) => selected.id !== product.id)
}

const escapeHtml = (value: unknown): string =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')

const productCardHtml = (product: DynamicProduct): string => {
    const title = product.name || product.code || ctrans('Product')
    const url = product.url || '#'
    const image = product.product_image
        ? `<a href="${escapeHtml(url)}" style="text-decoration:none"><img src="${escapeHtml(product.product_image)}" alt="${escapeHtml(title)}" width="100%" style="display:block;width:100%;max-width:100%;height:auto;border:0;border-radius:8px"></a>`
        : ''
    const description = showDescription.value && product.description
        ? `<div style="margin:0 0 12px 0;color:#333333;font-family:Arial,Helvetica Neue,Helvetica,sans-serif;font-size:14px;line-height:1.4;text-align:center">${product.description}</div>`
        : ''
    const color = buttonColor.value

    return `<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse">`
        + `<tr><td style="padding:0 0 12px 0">${image}</td></tr>`
        + `<tr><td style="padding:0 0 8px 0;text-align:center;font-family:Arial,Helvetica Neue,Helvetica,sans-serif;font-size:18px;font-weight:bold;color:#333333">${escapeHtml(title)}</td></tr>`
        + (description ? `<tr><td>${description}</td></tr>` : '')
        + `<tr><td style="text-align:center"><a href="${escapeHtml(url)}" style="display:inline-block;background-color:${escapeHtml(color)};color:${readableTextColor(color)};border-radius:4px;font-family:Arial,Helvetica Neue,Helvetica,sans-serif;font-size:16px;line-height:34px;padding:4px 28px;text-decoration:none;word-break:keep-all">${escapeHtml(buttonLabel.value || ctrans('SHOP NOW'))}</a></td></tr>`
        + `</table>`
}

const generatedHtml = computed(() => {
    const perRow = Math.max(1, productsPerRow.value)
    const cellWidth = `${(100 / perRow).toFixed(2)}%`
    const rows: string[] = []

    for (let index = 0; index < selectedProducts.value.length; index += perRow) {
        const rowProducts = selectedProducts.value.slice(index, index + perRow)
        const cells = rowProducts.map((product) => `<td class="product-cell" width="${cellWidth}" valign="top" style="width:${cellWidth};padding:8px;vertical-align:top">${productCardHtml(product)}</td>`)
        while (cells.length < perRow) {
            cells.push(`<td width="${cellWidth}" style="width:${cellWidth};padding:8px"></td>`)
        }
        rows.push(`<tr>${cells.join('')}</tr>`)
    }

    return `<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;width:100%">${rows.join('')}</table>`
})

const previewDocument = computed(() =>
    `<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{margin:0;padding:12px;background:#ffffff}</style></head><body>${generatedHtml.value}</body></html>`
)

const insertName = computed(() => {
    const names = selectedProducts.value.map((product) => product.name || product.code)
    if (names.length === 1) {
        return names[0]
    }

    return `${names.length} ${ctrans('products')}: ${names.slice(0, 3).join(', ')}${names.length > 3 ? '…' : ''}`
})

const resetState = () => {
    activeTab.value = PRODUCT_TABS.PRODUCTS
    searchQuery.value = ''
    timeFilter.value = TIME_FILTERS.WEEK
    selectedCollection.value = ''
    selectedFamily.value = ''
    selectedSubDepartment.value = ''
    results.value = []
    selectedProducts.value = []
    currentPage.value = 1
    totalPages.value = 0
    totalItems.value = 0
}

const insertSelected = () => {
    if (!selectedProducts.value.length) {
        return
    }
    const selection = { name: insertName.value, value: generatedHtml.value }
    resolveSelection?.(wantsProductData
        ? {
            ...selection,
            products: [...selectedProducts.value],
            appearance: { productsPerRow: productsPerRow.value, showDescription: showDescription.value, buttonLabel: buttonLabel.value, buttonColor: buttonColor.value },
        }
        : selection)
    resolveSelection = null
    rejectSelection = null
    isOpen.value = false
}

const onHide = () => {
    rejectSelection?.()
    resolveSelection = null
    rejectSelection = null
}

const close = () => {
    isOpen.value = false
}

const openModal = (options: { withProductData?: boolean } = {}) => {
    wantsProductData = !!options.withProductData
    return new Promise((resolve, reject) => {
        resolveSelection = resolve
        rejectSelection = reject
        resetState()
        isOpen.value = true
        searchProducts(1)
    })
}

defineExpose({
    openModal,
})
</script>

<template>
    <Dialog v-model:visible="isOpen" modal :draggable="false" :header="ctrans('Insert products')"
        :style="{ width: '80rem' }" :breakpoints="{ '1360px': '95vw' }" @hide="onHide"
        :pt="{ header: { class: '!px-5 !py-3 border-b border-gray-200' }, content: { class: '!px-5 !pb-4 !pt-0' } }">
        <div class="flex h-[78vh] min-h-[520px] flex-col">
            <p class="border-b border-gray-100 py-2 text-sm text-gray-500">{{ ctrans('Pick one or more products, adjust how they look, then insert them into the email.') }}</p>

            <div class="flex min-h-0 flex-1">
                <section class="flex min-w-0 flex-1 flex-col pr-4 pt-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex rounded-lg bg-gray-100 p-1">
                            <button v-for="tab in tabs" :key="tab.key" type="button"
                                class="rounded-md px-3 py-1.5 text-sm transition"
                                :class="activeTab === tab.key ? 'bg-white font-medium text-[var(--theme-color-4)] shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                                @click="switchTab(tab.key)">
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="relative min-w-[220px] flex-1">
                            <FontAwesomeIcon icon="fal fa-search" class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400" fixed-width aria-hidden="true" />
                            <input v-model="searchQuery" type="search" :placeholder="ctrans('Search by product code or name')" :aria-label="ctrans('Search products')"
                                class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]"
                                @input="onSearchInput" />
                        </div>
                    </div>

                    <div v-if="activeTab === PRODUCT_TABS.TRENDING" class="mt-3 flex items-center gap-2 text-sm">
                        <span class="text-gray-500">{{ ctrans('Best sellers') }}:</span>
                        <button v-for="filter in timeFilters" :key="filter.key" type="button"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="timeFilter === filter.key ? 'border-[var(--theme-color-4)] bg-[color-mix(in_srgb,var(--theme-color-4)_10%,white)] text-[var(--theme-color-4)]' : 'border-gray-300 text-gray-600 hover:border-gray-400'"
                            @click="changeTimeFilter(filter.key)">
                            {{ filter.label }}
                        </button>
                    </div>

                    <div v-if="activeTab === PRODUCT_TABS.COLLECTION_FAMILY" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <PureMultiselectInfiniteScroll v-if="shopId" mode="single" v-model="selectedFamily" :fetchRoute="entityFetchRoutes.family"
                            valueProp="id" labelProp="name" :placeholder="ctrans('Any family')" />
                        <PureMultiselectInfiniteScroll v-if="shopId" mode="single" v-model="selectedSubDepartment" :fetchRoute="entityFetchRoutes.subDepartment"
                            valueProp="id" labelProp="name" :placeholder="ctrans('Any sub-department')" />
                        <PureMultiselectInfiniteScroll v-if="shopSlug" mode="single" v-model="selectedCollection" :fetchRoute="entityFetchRoutes.collection"
                            valueProp="id" labelProp="name" :placeholder="ctrans('Any collection')" />
                    </div>

                    <div class="mt-3 min-h-0 flex-1 overflow-y-auto rounded-lg bg-gray-50 p-3">
                        <div v-if="isLoading" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                            <div v-for="placeholder in 8" :key="placeholder" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                                <div class="aspect-square animate-pulse bg-gray-100" />
                                <div class="space-y-2 p-3">
                                    <div class="h-3 w-3/4 animate-pulse rounded bg-gray-100" />
                                    <div class="h-3 w-1/3 animate-pulse rounded bg-gray-100" />
                                </div>
                            </div>
                        </div>

                        <div v-else-if="!results.length" class="flex h-full flex-col items-center justify-center py-12 text-center">
                            <FontAwesomeIcon icon="fal fa-box-open" class="mb-3 text-4xl text-gray-300" fixed-width aria-hidden="true" />
                            <div class="text-sm font-medium text-gray-700">
                                <template v-if="searchQuery.trim()">{{ ctrans('No products found for') }} “{{ searchQuery.trim() }}”</template>
                                <template v-else-if="hasActiveFilters">{{ ctrans('No products match these filters') }}</template>
                                <template v-else>{{ ctrans('No products to show') }}</template>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">{{ ctrans('Try another search or a different tab.') }}</div>
                        </div>

                        <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                            <button v-for="product in results" :key="product.id" type="button"
                                class="group relative flex flex-col overflow-hidden rounded-lg border bg-white text-left transition"
                                :class="isSelected(product)
                                    ? 'border-[var(--theme-color-4)] ring-2 ring-[var(--theme-color-4)]'
                                    : (isSelectionFull ? 'cursor-not-allowed border-gray-200 opacity-60' : 'border-gray-200 hover:border-[var(--theme-color-4)] hover:shadow-md')"
                                :aria-pressed="isSelected(product)"
                                @click="toggleProduct(product)">
                                <span class="absolute right-2 top-2 z-10 flex h-6 w-6 items-center justify-center rounded-full border text-xs font-semibold"
                                    :class="isSelected(product) ? 'border-[var(--theme-color-4)] bg-[var(--theme-color-4)] text-[var(--theme-color-5)]' : 'border-gray-300 bg-white/90 text-transparent group-hover:text-gray-300'">
                                    <template v-if="isSelected(product)">{{ selectionIndex(product) }}</template>
                                    <FontAwesomeIcon v-else icon="fal fa-check" fixed-width aria-hidden="true" />
                                </span>
                                <div class="flex aspect-square items-center justify-center bg-white p-2">
                                    <img v-if="product.product_image" :src="product.product_image" :alt="product.name ?? product.code" loading="lazy" class="max-h-full max-w-full object-contain" />
                                    <FontAwesomeIcon v-else icon="fal fa-image" class="text-3xl text-gray-300" fixed-width aria-hidden="true" />
                                </div>
                                <div class="border-t border-gray-100 px-3 py-2">
                                    <div class="line-clamp-2 text-sm font-medium leading-snug text-gray-900" :title="product.name ?? ''">{{ product.name ?? product.code }}</div>
                                    <div class="mt-1 inline-block rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600">{{ product.code }}</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between py-2 text-xs text-gray-500">
                        <span v-if="totalItems">{{ firstItemNumber }}–{{ lastItemNumber }} {{ ctrans('of') }} {{ totalItems }}</span>
                        <span v-else />
                        <div v-if="totalPages > 1" class="flex items-center gap-x-1">
                            <button type="button" class="h-7 w-7 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
                                :disabled="currentPage <= 1 || isLoading" :aria-label="ctrans('Previous page')" @click="goToPage(currentPage - 1)">
                                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                            </button>
                            <span class="px-2">{{ currentPage }} / {{ totalPages }}</span>
                            <button type="button" class="h-7 w-7 rounded border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-40"
                                :disabled="currentPage >= totalPages || isLoading" :aria-label="ctrans('Next page')" @click="goToPage(currentPage + 1)">
                                <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </section>

                <aside class="flex w-80 shrink-0 flex-col border-l border-gray-200 pl-4 pt-3">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-gray-700">
                                    {{ ctrans('Selected') }} ({{ selectedProducts.length }}/{{ MAX_SELECTED_PRODUCTS }})
                                </span>
                                <button v-if="selectedProducts.length" type="button" class="text-xs text-red-500 hover:text-red-700" @click="selectedProducts = []">
                                    {{ ctrans('Clear') }}
                                </button>
                            </div>
                            <div v-if="!selectedProducts.length" class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-xs text-gray-500">
                                {{ ctrans('Click products on the left to add them here.') }}
                            </div>
                            <ul v-else class="space-y-1.5">
                                <li v-for="(product, index) in selectedProducts" :key="product.id" class="flex items-center gap-x-2 rounded border border-gray-200 bg-white p-1.5">
                                    <span class="w-4 text-center text-[11px] text-gray-400">{{ index + 1 }}</span>
                                    <img v-if="product.product_image" :src="product.product_image" :alt="product.name ?? product.code" class="h-8 w-8 shrink-0 rounded object-contain" />
                                    <span class="min-w-0 flex-1 truncate text-xs text-gray-800">{{ product.name ?? product.code }}</span>
                                    <button type="button" class="h-6 w-6 shrink-0 rounded text-gray-400 hover:bg-gray-100 hover:text-red-500" :aria-label="ctrans('Remove')" @click="removeSelected(product)">
                                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-3">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-700">{{ ctrans('Appearance') }}</div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">{{ ctrans('Products per row') }}</span>
                                <div class="flex overflow-hidden rounded border border-gray-300">
                                    <button v-for="count in [1, 2, 3]" :key="count" type="button" class="h-8 w-9 border-l border-gray-200 text-sm first:border-l-0"
                                        :class="productsPerRow === count ? 'bg-[var(--theme-color-4)] text-[var(--theme-color-5)]' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                        @click="productsPerRow = count">
                                        {{ count }}
                                    </button>
                                </div>
                            </div>
                            <label class="flex cursor-pointer items-center justify-between">
                                <span class="text-sm text-gray-600">{{ ctrans('Show description') }}</span>
                                <input v-model="showDescription" type="checkbox" class="rounded border-gray-300 text-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                            </label>
                            <label class="block">
                                <span class="text-sm text-gray-600">{{ ctrans('Button text') }}</span>
                                <input v-model="buttonLabel" type="text" class="mt-1 w-full rounded border-gray-300 px-2 py-1.5 text-sm focus:border-[var(--theme-color-4)] focus:ring-[var(--theme-color-4)]" />
                            </label>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">{{ ctrans('Button color') }}</span>
                                <label class="flex h-8 cursor-pointer items-center gap-x-2 rounded border border-gray-300 bg-white pl-1 pr-2">
                                    <input v-model="buttonColor" type="color" class="h-6 w-6 cursor-pointer border-0 p-0" />
                                    <span class="font-mono text-xs text-gray-600">{{ buttonColor }}</span>
                                </label>
                            </div>
                        </div>

                        <div v-if="selectedProducts.length">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-700">{{ ctrans('Preview') }}</div>
                            <iframe :srcdoc="previewDocument" sandbox="" :title="ctrans('Products preview')"
                                class="h-72 w-full rounded border border-gray-200 bg-white" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-x-2 border-t border-gray-200 pt-3">
                        <button type="button" class="h-9 rounded border border-gray-300 px-4 text-sm text-gray-700 hover:bg-gray-50" @click="close">
                            {{ ctrans('Cancel') }}
                        </button>
                        <button type="button"
                            class="h-9 rounded bg-[var(--theme-color-4)] px-4 text-sm font-medium text-[var(--theme-color-5)] hover:bg-[color-mix(in_srgb,var(--theme-color-4)_85%,black)] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!selectedProducts.length" @click="insertSelected">
                            {{ selectedProducts.length > 1 ? `${ctrans('Insert')} ${selectedProducts.length} ${ctrans('products')}` : ctrans('Insert product') }}
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    </Dialog>
</template>
