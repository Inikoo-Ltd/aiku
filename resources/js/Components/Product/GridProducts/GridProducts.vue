<script setup lang="ts">
/**
 * GridProducts Component
 * 
 * A reusable product grid component with search, pagination, and favorite functionality.
 * Works with Laravel's Inertia.js query builder for server-side filtering and pagination.
 */

// ============================================================================
// IMPORTS
// ============================================================================
import { ref, computed, watch } from 'vue'
import { router, usePage } from "@inertiajs/vue3"
import { debounce, forEach, findKey } from 'lodash-es'
import qs from 'qs'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'


// Table Components
import Pagination from '@/Components/Table/Pagination.vue'
import TableFilterSearch from '@/Components/Table/TableFilterSearch.vue'
import TableWrapper from '@/Components/Table/TableWrapper.vue'

// Product Components
import RecordCounter from './RecordCounter.vue'
import ProductCard from './ProductCard.vue'
import EmptyState from './EmptyState.vue'

// Types
import type { Product, QueryBuilderData } from './types'


// ============================================================================
// PROPS
// ============================================================================
const props = defineProps({
    name: {
        type: String,
        default: 'default',
        required: false,
    },
    resource: {
        type: Object,
        default: () => ({}),
        required: false,
    },
    meta: {
        type: Object,
        default: () => ({}),
        required: false,
    },
    data: {
        type: Object,
        default: () => ({}),
        required: false,
    },
    preserveScroll: {
        type: [Boolean, String],
        default: false,
        required: false,
    },
    preventOverlappingRequests: {
        type: Boolean,
        default: true,
        required: false,
    },
    inputDebounceMs: {
        type: Number,
        default: 350,
        required: false,
    },
    isParentLoading: {
        type: Boolean,
        default: false,
        required: false,
    },
    gridClass: {
        type: String,
        default: 'lg:grid-cols-3 xl:grid-cols-4 grid grid-cols-2',
        required: false,
    },
})

// ============================================================================
// STATE MANAGEMENT
// ============================================================================
const updates = ref(0)
const isVisiting = ref(false)
const visitCancelToken = ref<{ cancel: Function } | null>(null)
const isLoading = ref(false)

// ============================================================================
// QUERY BUILDER SETUP
// ============================================================================
const queryBuilderProps = computed(() => {
    const data = usePage().props.queryBuilderProps?.[props.name] || {}
    data._updates = updates.value
    return data
})

const queryBuilderData = ref<QueryBuilderData>({...queryBuilderProps.value})

// Initialize search inputs if not present
if (!queryBuilderData.value.searchInputs) {
    queryBuilderData.value.searchInputs = []
}

// Ensure global search input exists
const globalSearchInput = queryBuilderData.value.searchInputs.find(input => input?.key === 'global')
if (!globalSearchInput) {
    queryBuilderData.value.searchInputs.push({
        key: 'global',
        value: null
    })
}

// ============================================================================
// COMPUTED PROPERTIES
// ============================================================================
const pageName = computed(() => queryBuilderProps.value.pageName)

/**
 * Extract and normalize resource data from various prop formats
 */
const compResourceData = computed<Product[]>(() => {
    if (props.resource?.data?.length > 0) {
        return props.resource.data
    }
    
    if (props.data?.length > 0) {
        return props.data
    }
    
    if (Object.keys(props.resource || {}).length > 0 && !('data' in props.resource)) {
        return props.resource
    }
    
    return []
})

/**
 * Extract and normalize metadata for pagination
 */
const compResourceMeta = computed(() => {
    if (props.meta && Object.keys(props.meta).length > 0) {
        return props.meta
    }

    if ('links' in props.resource && 'meta' in props.resource) {
        if (
            Object.keys(props.resource.links).length === 4 &&
            'next' in props.resource.links &&
            'prev' in props.resource.links
        ) {
            return {
                ...props.resource.meta,
                next_page_url: props.resource.links.next,
                prev_page_url: props.resource.links.prev,
            }
        }
    }

    if ('meta' in props.resource) {
        return props.resource.meta
    }

    return props.resource || {}
})

/**
 * Check if there's data to display
 */
const hasData = computed(() => {
    return compResourceData.value.length > 0 || compResourceMeta.value.total > 0
})

// ============================================================================
// SEARCH & FILTER FUNCTIONS
// ============================================================================

/**
 * Get the current value of a search input
 */
function getSearchInputValue(key: string): any {
    const intKey = findDataKey('searchInputs', key)
    return intKey !== undefined ? queryBuilderData.value.searchInputs![intKey].value : null
}

/**
 * Update a search input value and trigger a new search
 */
function changeSearchInputValue(key: string, value: any): void {
    if (visitCancelToken.value && props.preventOverlappingRequests) {
        visitCancelToken.value.cancel()
    }

    const intKey = findDataKey('searchInputs', key)
    if (intKey !== undefined && queryBuilderData.value.searchInputs) {
        queryBuilderData.value.searchInputs[intKey].value = value
        queryBuilderData.value.cursor = null
        queryBuilderData.value.page = 1
    }
}

/**
 * Debounced global search handler
 */
const changeGlobalSearchValue = debounce((value?: string) => {
    changeSearchInputValue('global', value)
}, props.inputDebounceMs)

/**
 * Find the index of a data item by key
 */
function findDataKey(dataKey: string, key: string): number | undefined {
    return findKey(queryBuilderData.value[dataKey as keyof QueryBuilderData], (value: any) => {
        return value.key === key
    })
}

/**
 * Reset all filters and search inputs
 */
function resetQuery(): void {
    if (queryBuilderData.value.filters) {
        forEach(queryBuilderData.value.filters, (filter, key) => {
            queryBuilderData.value.filters![key].value = null
        })
    }

    if (queryBuilderData.value.searchInputs) {
        forEach(queryBuilderData.value.searchInputs, (filter, key) => {
            queryBuilderData.value.searchInputs![key].value = null
        })
    }

    queryBuilderData.value.sort = null
    queryBuilderData.value.cursor = null
    queryBuilderData.value.page = 1
}

// ============================================================================
// PAGINATION
// ============================================================================

/**
 * Handle per-page change
 */
function onPerPageChange(value: number): void {
    queryBuilderData.value.cursor = null
    queryBuilderData.value.perPage = value
    queryBuilderData.value.page = 1
}

// ============================================================================
// URL QUERY STRING GENERATION
// ============================================================================

/**
 * Get all active filters for the query string
 */
function getFilterForQuery(): Record<string, any> {
    const filtersWithValue: Record<string, any> = {}

    if (queryBuilderData.value.searchInputs) {
        forEach(queryBuilderData.value.searchInputs, (searchInput) => {
            if (searchInput.value !== null) {
                filtersWithValue[searchInput.key] = searchInput.value
            }
        })
    }

    if (queryBuilderData.value.filters) {
        forEach(queryBuilderData.value.filters, (filter) => {
            if (filter.value !== null) {
                filtersWithValue[filter.key] = filter.value
            }
        })
    }

    return filtersWithValue
}

/**
 * Build data object for the new query string
 */
function dataForNewQueryString(): Record<string, any> {
    const filterForQuery = getFilterForQuery()
    const queryData: Record<string, any> = {}

    if (Object.keys(filterForQuery).length > 0) {
        queryData.filter = filterForQuery
    }

    const { cursor, page, sort, perPage } = queryBuilderData.value

    if (cursor) queryData.cursor = cursor
    if (page && page > 1) queryData.page = page
    if (perPage && perPage > 1) queryData.perPage = perPage
    if (sort) queryData.sort = sort

    return queryData
}

/**
 * Generate the complete query string for the URL
 */
function generateNewQueryString(): string {
    const queryStringData = qs.parse(location.search.substring(1))
    const prefix = props.name === 'default' ? '' : props.name + '_'

    // Remove old query parameters
    forEach(['filter', 'columns', 'cursor', 'sort'], (key) => {
        delete queryStringData[prefix + key]
    })
    delete queryStringData[pageName.value]

    // Add new query parameters
    forEach(dataForNewQueryString(), (value, key) => {
        if (key === 'page') {
            queryStringData[pageName.value] = value
        } else {
            queryStringData[prefix + key] = value
        }
    })

    const query = qs.stringify(queryStringData, {
        encodeValuesOnly: true,
        skipNulls: true,
        strictNullHandling: true,
        arrayFormat: 'comma',
    })

    return (!query || query === pageName.value + '=1') ? '' : query
}

// ============================================================================
// NAVIGATION
// ============================================================================

/**
 * Navigate to a new URL with Inertia
 */
const visit = (url?: string): void => {
    if (!url) return

    router.get(
        url,
        {},
        {
            replace: true,
            preserveState: true,
            preserveScroll: props.preserveScroll !== false,
            onBefore() {
                isVisiting.value = true
            },
            onCancelToken(cancelToken) {
                visitCancelToken.value = cancelToken
            },
            onFinish() {
                isVisiting.value = false
            },
            onSuccess() {
                if ('queryBuilderProps' in usePage().props) {
                    // Only update cursor and page like Table.vue does
                    queryBuilderData.value.cursor = queryBuilderProps.value.cursor
                    queryBuilderData.value.page = queryBuilderProps.value.page
                }
                updates.value++
            }
        },
    )
}

// ============================================================================
// WATCHERS
// ============================================================================

/**
 * Watch for query changes and trigger navigation
 */
watch(queryBuilderData, async () => {
    try {
        visit(location.pathname + '?' + generateNewQueryString())
    } catch (error) {
        console.error("Navigation error:", error)
    }
}, { deep: true })

// ============================================================================
// PRODUCT ACTIONS
// ============================================================================

/**
 * Toggle favorite status for a product
 */
const toggleFavorite = (product: Product): void => {
    // Default to true if undefined (all products are favorited by default)
    const originalState = product.is_favourite !== undefined ? product.is_favourite : true
    
    // Optimistically update the UI
    product.is_favourite = !originalState
    
    // Section: Submit - Handle favorite/unfavorite API calls
    if (originalState) {
        // Product was favorited, now unfavorite it
        router.delete(
            route('retina.models.product.unfavourite', { product: product.id }),
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => { 
                    isLoading.value = true
                },
                onSuccess: () => {
                    notify({
                        title: trans("Removed from favorites"),
                        text: `${product.name || 'Product'} ${trans('has been removed from your favorites')}`,
                        type: "info",
                        duration: 3000
                    })
                },
                onError: (errors) => {
                    // Revert on error
                    product.is_favourite = originalState
                    notify({
                        title: trans("Something went wrong"),
                        text: trans("Failed to remove from favorites"),
                        type: "error",
                        duration: 3000
                    })
                    console.error('Failed to unfavorite:', errors)
                },
                onFinish: () => {
                    isLoading.value = false
                },
            }
        )
    } 
}
</script>

<template>
    <fieldset :key="`grid-${name}`" class="min-w-0" :class="{ 'opacity-75': isVisiting || isParentLoading }">
        <!-- Header Section -->
        <div class="py-2 sm:py-0 my-0">
            <div class="grid grid-flow-col justify-between items-center flex-nowrap px-3 sm:px-4">
                <!-- Left Section: Counter and Search -->
                <div class="h-fit flex flex-wrap gap-y-0.5 gap-x-1 items-center my-0.5">
                    <!-- Record Counter -->
                    <RecordCounter :total="compResourceMeta?.total || 0"
                        :labelSingular="queryBuilderProps?.labelRecord?.[0] || trans('product')"
                        :labelPlural="queryBuilderProps?.labelRecord?.[1] || queryBuilderProps?.labelRecord?.[0] || trans('products')" />

                    <!-- Search Input -->
                    <div class="flex flex-row">
                        <TableFilterSearch @resetSearch="resetQuery" :label="trans('Search products...')"
                            :value="getSearchInputValue('global')" :on-change="changeGlobalSearchValue"
                            :isVisiting="isVisiting" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <TableWrapper :result="compResourceMeta?.total === 0" class="mt-2">
            <div v-if="compResourceData.length > 0"
                class="auto-rows-auto gap-4 p-4" :class="gridClass">
                <!-- Product Cards -->
                <div v-for="(item, index) in compResourceData" :key="`product-${index}`">
                    <slot name="card" :item="item">
                        <ProductCard :product="item" @toggle-favorite="toggleFavorite" />
                    </slot>
                </div>


            </div>

            <!-- Empty State -->
            <EmptyState
            v-else-if="!isVisiting"
                :message="getSearchInputValue('global') ? trans('No result') : ( trans('Empty ') + name) "
                :description="getSearchInputValue('global') ? trans('Try adjusting your search terms') : trans('No ') + name + trans(' are available at this time')"
            />

            <!-- Pagination -->
            <Pagination
                v-if="hasData"
                :on-click="visit"
                :has-data="hasData"
                :meta="compResourceMeta"
                :exportLinks="queryBuilderProps?.exportLinks"
                :per-page-options="queryBuilderProps?.perPageOptions"
                :on-per-page-change="onPerPageChange"
            />
        </TableWrapper>
    </fieldset>
</template>


