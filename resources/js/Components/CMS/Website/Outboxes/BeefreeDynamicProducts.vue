<script setup lang="ts">
import { ref, inject, watch } from "vue";
import axios from "axios"
import { routeType } from "@/types/route";
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n';
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'


// Tab types for product search modal
const PRODUCT_TABS = {
    PRODUCTS: 'products',
    NEW_IN: 'new_in',
    TRENDING: 'trending',
    COLLECTION_FAMILY: 'collection_family'
}

// Time filter options
const TIME_FILTERS = {
    WEEK: 'week',
    MONTH: 'month',
    YEAR: 'year'
}

const props = defineProps<{
    shopSlug?: string
    shopId?: number
    organisationSlug: string
}>()

const locale = inject('locale', aikuLocaleStructure)

// Product search dialog state
const productSearchModalOpen = ref(false)
const productSearchQuery = ref('')
const productSearchResults = ref<Array<any>>([])
const productSearchLoading = ref(false)
const activeTab = ref(PRODUCT_TABS.PRODUCTS)
const timeFilter = ref(TIME_FILTERS.WEEK)
const selectedButtonColor = ref('#007bff')

// Pagination state
const currentPage = ref(1)
const totalPages = ref(0)
const totalItems = ref(0)
const paginationData = ref<any>(null)

// Collection filtering state
const collections = ref<Array<any>>([])
const selectedCollection = ref<string>('')
const collectionsLoading = ref(false)

// Family filtering state
const families = ref<Array<any>>([])
const selectedFamily = ref<string>('')
const familiesLoading = ref(false)

// Sub-department filtering state
const subDepartments = ref<Array<any>>([])
const selectedSubDepartment = ref<string>('')
const subDepartmentsLoading = ref(false)

// Resolve/reject handlers for product selection
let productSearchResolve: ((value: any) => void) | null = null
let productSearchReject: (() => void) | null = null
let searchDebounceTimeout: ReturnType<typeof setTimeout> | null = null

const emits = defineEmits<{
    (e: 'product-selected', value: { name: string, value: string }): void
}>()

// Product search functions
const searchProducts = async (page: number = 1) => {
    if (!props.shopSlug) {
        productSearchResults.value = []
        return
    }

    productSearchLoading.value = true
    try {
        let response

        switch (activeTab.value) {
            case PRODUCT_TABS.PRODUCTS:
                response = await searchProductsAPI(page)
                break
            case PRODUCT_TABS.NEW_IN:
                response = await searchNewInProductsAPI(page)
                break
            case PRODUCT_TABS.TRENDING:
                response = await searchTrendingProductsAPI(page)
                break
            case PRODUCT_TABS.COLLECTION_FAMILY:
                response = await searchCollectionFamilyProductsAPI(page)
                break
            default:
                response = await searchProductsAPI(page)
        }

        console.log("result data")
        console.log(response)
        productSearchResults.value = response.data.data || []

        // Extract pagination metadata
        if (response.data.meta) {
            paginationData.value = response.data.meta
            currentPage.value = response.data.meta.current_page || 1
            totalPages.value = response.data.meta.last_page || 0
            totalItems.value = response.data.meta.total || 0
        } else {
            // Fallback if no pagination metadata
            currentPage.value = page
            totalPages.value = 1
            totalItems.value = response.data.data?.length || 0
            paginationData.value = null
        }
    } catch (error) {
        console.error('Product search error:', error)
        productSearchResults.value = []
        // Reset pagination on error
        currentPage.value = 1
        totalPages.value = 0
        totalItems.value = 0
        paginationData.value = null
    } finally {
        productSearchLoading.value = false
    }
}

const searchProductsAPI = async (page: number = 1) => {
    if (!props.shopSlug) {
        return { data: { data: [] } }
    }

    return await axios.get(
        route('grp.json.shop.products_beefree_search', {
            shop: props.shopSlug!,
        }), {
        params: {
            search: productSearchQuery.value.trim(),
            tab_type: 'products',
            per_page: 10,
            page: page
        }
    }
    )
}

const searchNewInProductsAPI = async (page: number = 1) => {
    if (!props.shopSlug) {
        return { data: { data: [] } }
    }

    return await axios.get(
        route('grp.json.shop.products_beefree_search', {
            shop: props.shopSlug!,
        }), {
        params: {
            search: productSearchQuery.value.trim(),
            tab_type: 'new_in',
            per_page: 10,
            page: page
        }
    }
    )
}

const searchTrendingProductsAPI = async (page: number = 1) => {
    if (!props.shopSlug) {
        return { data: { data: [] } }
    }

    return await axios.get(
        route('grp.json.shop.products_beefree_search', {
            shop: props.shopSlug!,
        }), {
        params: {
            search: productSearchQuery.value.trim(),
            tab_type: 'trending',
            time_filter: timeFilter.value,
            per_page: 10,
            page: page
        }
    }
    )
}

const fetchCollections = async () => {
    if (!props.shopSlug) {
        collections.value = []
        return
    }

    collectionsLoading.value = true
    try {
        const response = await axios.get(
            route('grp.json.shop.catalogue.collections', {
                shop: props.shopSlug!,
                scope: props.shopSlug!
            })
        )
        collections.value = response.data.data || []
    } catch (error) {
        console.error('Collections fetch error:', error)
        collections.value = []
    } finally {
        collectionsLoading.value = false
    }
}

const fetchFamilies = async () => {
    if (!props.shopId) {
        families.value = []
        return
    }

    familiesLoading.value = true
    try {
        const response = await axios.get(
            route('grp.json.shop.families', {
                shop: props.shopId!
            })
        )
        families.value = response.data.data || []
    } catch (error) {
        console.error('Families fetch error:', error)
        families.value = []
    } finally {
        familiesLoading.value = false
    }
}

const fetchSubDepartments = async () => {
    if (!props.shopId) {
        subDepartments.value = []
        return
    }

    subDepartmentsLoading.value = true
    try {
        const response = await axios.get(
            route('grp.json.shop.sub_departments', {
                shop: props.shopId!
            })
        )
        subDepartments.value = response.data.data || []
    } catch (error) {
        console.error('Sub-departments fetch error:', error)
        subDepartments.value = []
    } finally {
        subDepartmentsLoading.value = false
    }
}

// Route configuration for infinite scroll components
function getEntityFetchRoute(entityType: string) {
    if (entityType === 'family') {
        return {
            name: 'grp.json.shop.families',
            parameters: { shop: props.shopId }
        }
    }

    if (entityType === 'sub_department') {
        return {
            name: 'grp.json.shop.sub_departments',
            parameters: { shop: props.shopId }
        }
    }

    if (entityType === 'collection') {
        return {
            name: 'grp.json.shop.catalogue.collections',
            parameters: { shop: props.shopSlug, scope: props.shopSlug }
        }
    }

    return null
}

const searchCollectionFamilyProductsAPI = async (page: number = 1) => {
    if (!props.shopSlug) {
        return { data: { data: [] } }
    }

    return await axios.get(
        route('grp.json.shop.products_beefree_search', {
            shop: props.shopSlug!,
        }), {
        params: {
            search: productSearchQuery.value.trim(),
            tab_type: 'collection_family',
            collection_id: selectedCollection.value || null,
            family_id: selectedFamily.value || null,
            sub_department_id: selectedSubDepartment.value || null,
            per_page: 10,
            page: page
        }
    }
    )
}

const onSearchInput = () => {
    if (searchDebounceTimeout) {
        clearTimeout(searchDebounceTimeout)
    }
    // Reset to page 1 when searching
    currentPage.value = 1
    searchDebounceTimeout = setTimeout(() => {
        searchProducts(1)
    }, 300)
}

const generateProductHtmlValue = (product: any, shopSlug: string): string => {
    const imageUrl = product.product_image || ''
    const title = product.name || product.code || 'Unknown Product'
    const description = product.description || ''
    const truncatedDescription = description
    const productUrl = product.url || '#'
    const buttonColor = selectedButtonColor.value

    return `
        <div style="width: 100%; font-family: Arial, sans-serif;">
            ${imageUrl ? `
                <div style="margin-bottom: 12px;">
                    <img src="${imageUrl}" alt="${title}" style="width: 100%; height: auto; border-radius: 8px;" />
                </div>
            ` : ''}
            <div style="margin-bottom: 12px;">
                <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: bold; color: #333;">${title}</h3>
                ${truncatedDescription ? `
                    <p style="margin: 0; font-size: 14px; color: #666; line-height: 1.4;">${truncatedDescription}</p>
                ` : ''}
            </div>
            <div style="text-align: center;">
                <a href="${productUrl}" style="display: inline-block; background-color: ${buttonColor}; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 500;">${trans('Shop Now')}</a>
            </div>
        </div>
    `.trim()
}

const selectProduct = (product: any) => {
    if (productSearchResolve) {
        const productLink = {
            name: product.name || product.code,
            value: generateProductHtmlValue(product, props.shopSlug!)
        }
        productSearchResolve(productLink)
        productSearchResolve = null
        productSearchReject = null
    }
    productSearchModalOpen.value = false
    productSearchQuery.value = ''
    productSearchResults.value = []
}

const closeProductSearchModal = () => {
    if (productSearchReject) {
        productSearchReject()
        productSearchReject = null
        productSearchResolve = null
    }
    productSearchModalOpen.value = false
    productSearchQuery.value = ''
    productSearchResults.value = []
}

const switchTab = (tab: string) => {
    activeTab.value = tab
    productSearchQuery.value = ''
    // Reset to page 1 when switching tabs
    currentPage.value = 1
    searchProducts(1)
}

const changeTimeFilter = (filter: string) => {
    timeFilter.value = filter
    if (activeTab.value === PRODUCT_TABS.NEW_IN || activeTab.value === PRODUCT_TABS.TRENDING) {
        // Reset to page 1 when changing time filter
        currentPage.value = 1
        searchProducts(1)
    }
}

// Watchers for infinite scroll components
watch(selectedCollection, (newValue) => {
    if (activeTab.value === PRODUCT_TABS.COLLECTION_FAMILY) {
        // Reset to page 1 when changing collection
        currentPage.value = 1
        searchProducts(1)
    }
})

watch(selectedFamily, (newValue) => {
    if (activeTab.value === PRODUCT_TABS.COLLECTION_FAMILY) {
        // Reset to page 1 when changing family
        currentPage.value = 1
        searchProducts(1)
    }
})

watch(selectedSubDepartment, (newValue) => {
    if (activeTab.value === PRODUCT_TABS.COLLECTION_FAMILY) {
        // Reset to page 1 when changing sub-department
        currentPage.value = 1
        searchProducts(1)
    }
})

// Pagination functions
const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        currentPage.value = page
        searchProducts(page)
    }
}

const goToPreviousPage = () => {
    if (currentPage.value > 1) {
        goToPage(currentPage.value - 1)
    }
}

const goToNextPage = () => {
    if (currentPage.value < totalPages.value) {
        goToPage(currentPage.value + 1)
    }
}

const setupProductSearchHandlers = (resolve: (value: any) => void, reject: () => void) => {
    productSearchResolve = resolve
    productSearchReject = reject
    // Open product search modal
    productSearchModalOpen.value = true
    productSearchQuery.value = ''
    productSearchResults.value = []
    activeTab.value = PRODUCT_TABS.PRODUCTS
    selectedCollection.value = ''
    selectedFamily.value = ''
    selectedSubDepartment.value = ''
    // Reset pagination when opening modal
    currentPage.value = 1
    totalPages.value = 0
    totalItems.value = 0
    paginationData.value = null
    fetchCollections()
    fetchFamilies()
    fetchSubDepartments()
    searchProducts(1)
}

// Expose method for parent component
const openModal = () => {
    return new Promise((resolve, reject) => {
        setupProductSearchHandlers(resolve, reject)
    })
}

defineExpose({
    openModal
})
</script>

<template>
    <!-- Product Search Modal -->
    <Modal :isOpen="productSearchModalOpen" @onClose="closeProductSearchModal" width="w-full max-w-4xl"
        :closeButton="true">
        <div class="p-4">
            <h3 class="text-lg font-semibold mb-4">{{ trans('Search Products') }}</h3>

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <button v-for="tab in [
                        { key: PRODUCT_TABS.PRODUCTS, label: trans('Products') },
                        { key: PRODUCT_TABS.NEW_IN, label: trans('New In') },
                        { key: PRODUCT_TABS.TRENDING, label: trans('Trending Products') },
                        { key: PRODUCT_TABS.COLLECTION_FAMILY, label: trans('Collection/Family') }
                    ]" :key="tab.key" @click="switchTab(tab.key)" :class="[
                        activeTab === tab.key
                            ? 'border-indigo-500 text-indigo-600'
                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                        'whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm cursor-pointer transition-colors'
                    ]">
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <!-- Filters Section -->
            <div class="mb-4 flex flex-col sm:flex-row gap-4 items-center">
                <!-- Search Input (only for Products tab) -->
                <div v-if="activeTab === PRODUCT_TABS.PRODUCTS" class="flex-1">
                    <PureInput v-model="productSearchQuery" :placeholder="trans('Type SKU or product name...')"
                        @input="onSearchInput" :autofocus="true" />
                </div>

                <!-- Time Filter (for Trending only) -->
                <div v-if="activeTab === PRODUCT_TABS.TRENDING" class="sm:w-32">
                    <select v-model="timeFilter" @change="changeTimeFilter(timeFilter)"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option :value="TIME_FILTERS.WEEK">{{ trans('Week') }}</option>
                        <option :value="TIME_FILTERS.MONTH">{{ trans('Month') }}</option>
                        <option :value="TIME_FILTERS.YEAR">{{ trans('Year') }}</option>
                    </select>
                </div>

                <!-- Family Filter (for Collection/Family) -->
                <div v-if="activeTab === PRODUCT_TABS.COLLECTION_FAMILY" class="sm:w-48">
                    <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('family')" mode="single"
                        v-model="selectedFamily" :initOptions="families || []"
                        :fetchRoute="getEntityFetchRoute('family')!" valueProp="id" labelProp="name"
                        :placeholder="trans('Select a family')" />
                </div>

                <!-- Sub-Department Filter (for Collection/Family) -->
                <div v-if="activeTab === PRODUCT_TABS.COLLECTION_FAMILY" class="sm:w-48">
                    <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('sub_department')" mode="single"
                        v-model="selectedSubDepartment" :initOptions="subDepartments || []"
                        :fetchRoute="getEntityFetchRoute('sub_department')!" valueProp="id" labelProp="name"
                        :placeholder="trans('Select a sub-department')" />
                </div>

                <!-- Collection Filter (for Collection/Family) -->
                <div v-if="activeTab === PRODUCT_TABS.COLLECTION_FAMILY" class="sm:w-48">
                    <PureMultiselectInfiniteScroll v-if="getEntityFetchRoute('collection')" mode="single"
                        v-model="selectedCollection" :initOptions="collections || []"
                        :fetchRoute="getEntityFetchRoute('collection')!" valueProp="id" labelProp="name"
                        :placeholder="trans('Select a collection')" />
                </div>

                <!-- Button Color Picker -->
                <div class="sm:w-32">
                    <label v-tooltip="trans('custom color will be use for button shop now')"
                        class="block text-sm font-medium text-gray-700 mb-1">
                        {{ trans('Custom Color') }}
                    </label>
                    <div v-tooltip="trans('custom color will be use for button shop now')"
                        class="flex items-center gap-2">
                        <input type="color" v-model="selectedButtonColor"
                            class="h-6 w-16 border border-gray-300 rounded cursor-pointer" />
                        <span class="text-xs text-gray-500">{{ selectedButtonColor }}</span>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="productSearchLoading" class="flex justify-center py-8">
                <LoadingIcon class="text-3xl" />
            </div>

            <!-- Results List -->
            <div v-else-if="productSearchResults.length > 0" class="max-h-96 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="product in productSearchResults" :key="product.id"
                        class="flex items-center gap-4 p-4 hover:bg-gray-50 border border-gray-200 rounded-lg transition-colors">
                        <!-- Product Image -->
                        <div class="w-16 h-16 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
                            <img v-if="product.product_image" :src="product.product_image" :alt="product.name"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                                {{ trans('No Image') }}
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 truncate">{{ product.name }}</div>
                            <div class="text-sm text-gray-500">{{ trans('Product Code') }}: {{ product.code }}</div>
                        </div>

                        <!-- Select Button -->
                        <Button type="secondary" :label="trans('Select')" size="sm"
                            @click.stop="selectProduct(product)" />
                    </div>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div v-if="productSearchResults.length > 0 && totalPages > 1"
                class="mt-4 flex items-center justify-between border-t pt-4">
                <!-- Page Information -->
                <div class="text-sm text-gray-600">
                    <span>{{ trans('Page') }} {{ currentPage }} {{ trans('of') }} {{ totalPages }}</span>
                    <span v-if="totalItems > 0" class="ml-2">({{ totalItems }} {{ trans('total items') }})</span>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex items-center gap-2">
                    <Button type="secondary" :label="trans('Previous')" size="sm" @click="goToPreviousPage"
                        :disabled="currentPage === 1 || productSearchLoading"
                        :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 || productSearchLoading }" />
                    <Button type="secondary" :label="trans('Next')" size="sm" @click="goToNextPage"
                        :disabled="currentPage === totalPages || productSearchLoading"
                        :class="{ 'opacity-50 cursor-not-allowed': currentPage === totalPages || productSearchLoading }" />
                </div>
            </div>

            <!-- Empty State -->
            <div v-else-if="productSearchQuery.trim() && !productSearchLoading && productSearchResults.length === 0"
                class="text-center py-8 text-gray-500">
                {{ trans('No products found matching') }} "{{ productSearchQuery }}"
            </div>

            <!-- Initial State -->
            <div v-else-if="productSearchResults.length === 0" class="text-center py-8 text-gray-400">
                <div v-if="activeTab === PRODUCT_TABS.PRODUCTS">
                    {{ trans('Type a SKU or product name to search') }}
                </div>
                <div v-else-if="activeTab === PRODUCT_TABS.NEW_IN">
                    {{ trans('Showing new products for selected time period') }}
                </div>
                <div v-else-if="activeTab === PRODUCT_TABS.TRENDING">
                    {{ trans('Showing trending products for selected time period') }}
                </div>
                <div v-else-if="activeTab === PRODUCT_TABS.COLLECTION_FAMILY">
                    {{ trans('Select a collection to browse products') }}
                </div>
            </div>

            <!-- Cancel Button -->
            <div class="mt-6 flex justify-end">
                <Button type="tertiary" :label="trans('Cancel')" @click="closeProductSearchModal" />
            </div>
        </div>
    </Modal>
</template>
