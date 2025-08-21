<template>
    <fieldset :key="`grid-${name}`" class="min-w-0" :class="{ 'opacity-75': isVisiting || isParentLoading }">
        <div class="py-2 sm:py-0 my-0">
            <!-- Header Section with Record Counter and Search -->
            <div class="grid grid-flow-col justify-between items-center flex-nowrap px-3 sm:px-4">
                <!-- Left Section: Record Counter and Search -->
                <div class="h-fit flex flex-wrap gap-y-0.5 gap-x-1 items-center my-0.5">
                    <!-- Record Counter -->
                    <div class="bg-gray-100 h-fit flex items-center border border-gray-300 overflow-hidden rounded">
                        <div class="grid justify-end items-center text-base font-normal text-gray-700">
                            <div class="px-2 py-[1px] whitespace-nowrap flex gap-x-1.5 flex-nowrap">
                                <span class="font-semibold tabular-nums">
                                    <CountUp 
                                        :endVal="compResourceMeta?.total || 0" 
                                        :duration="1.2"
                                        :scrollSpyOnce="true" 
                                        :options="{
                                            formattingFn: (number) => locale?.number ? locale.number(number) : number.toLocaleString()
                                        }" 
                                    />
                                </span>
                                <span class="font-light">
                                    {{ 
                                        compResourceMeta?.total > 1 
                                        ? queryBuilderProps?.labelRecord?.[1] || queryBuilderProps?.labelRecord?.[0] || trans('products') 
                                        : queryBuilderProps?.labelRecord?.[0] || trans('product')
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Search Input using TableFilterSearch -->
                    <div class="flex flex-row">
                        <TableFilterSearch 
                            @resetSearch="() => clearLocalSearch()" 
                            :label="'Search products...'"
                            :value="localSearchQuery" 
                            :on-change="handleLocalSearch"
                            :isVisiting="isVisiting"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid Wrapper -->
        <TableWrapper :result="compResourceMeta?.total === 0" class="mt-2">
            <!-- Products Grid -->
            <div class="grid grid-cols-3 auto-rows-auto gap-4 p-4 bg-white">
                <div 
                    v-for="(product, index) in compResourceData" 
                    :key="product.id || index"
                    class="bg-white p-2 border rounded-lg hover:shadow-md transition-shadow relative"
                >
                    <!-- Favorite Icon -->
                    <button
                        @click.stop="toggleFavorite(product)"
                        class="absolute top-3 right-3 z-10 p-2 bg-white/90 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-all hover:scale-110 flex items-center justify-center"
                        :class="[
                            product.is_favourite ? 'text-red-500' : 'text-gray-400 hover:text-red-400'
                        ]"
                    >
                        <FontAwesomeIcon 
                            :icon="product.is_favourite ? 'fas fa-heart' : 'fal fa-heart'" 
                            class="w-5 h-5"
                        />
                    </button>

                    <!-- start: product image -->
                    <div class="h-[200px] aspect-square w-full rounded-md overflow-hidden bg-gray-400 mb-4">
                        <img 
                            v-if="product.image" 
                            :src="product.image" 
                            :alt="product.name"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    <!-- end: product image -->
                    <!-- start: product & code name -->
                    <h3 class="text-base font-medium mb-1">{{ product.name || 'Product Name' }}</h3>
                    <p class="text-xs text-gray-600">{{ product.code || 'PROD-CODE' }}</p>
                    <!-- end: product & code name -->
                </div>

                <!-- Empty State -->
                <div v-if="compResourceData.length === 0 && !isVisiting" class="col-span-3 text-center py-8">
                    <p class="text-gray-500">{{ trans('No products found') }}</p>
                </div>
            </div>

            <!-- Pagination -->
            <Pagination 
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

<script setup lang="ts">
import { ref, computed, inject, watch } from 'vue'
import { router, usePage } from "@inertiajs/vue3"
import { debounce, forEach, findKey } from 'lodash-es'
import qs from 'qs'
import CountUp from 'vue-countup-v3'
import { trans } from 'laravel-vue-i18n'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faHeart as falHeart } from '@fal'
import { faHeart as fasHeart } from '@fas'
import { notify } from '@kyvg/vue3-notification'

// Add icons to library
library.add(falHeart, fasHeart)

// Import Table components
import Pagination from '@/Components/Table/Pagination.vue'
import TableFilterSearch from '@/Components/Table/TableFilterSearch.vue'
import TableWrapper from '@/Components/Table/TableWrapper.vue'

// Emits
const emit = defineEmits<{
    (e: 'toggleFavorite', product: any): void
}>()

// Props definition
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
})

// Inject locale for number formatting
const locale = inject('locale', aikuLocaleStructure)

// State management
const updates = ref(0)
const isVisiting = ref(false)
const visitCancelToken = ref<{ cancel: Function } | null>(null)
const localSearchQuery = ref('')

// Query builder props from Inertia
const queryBuilderProps = computed(() => {
    let data = usePage().props.queryBuilderProps
        ? usePage().props.queryBuilderProps[props.name] || {}
        : {}
    
    data._updates = updates.value
    return data
})

const queryBuilderData = ref({...queryBuilderProps.value})

const pageName = computed(() => {
    return queryBuilderProps.value.pageName
})

// Data computed properties
const baseResourceData = computed(() => {
    // Check if we have actual data from props
    if (props.resource?.data?.length > 0) {
        return props.resource.data
    }
    
    if (props.data?.length > 0) {
        return props.data
    }
    
    if (Object.keys(props.resource || {}).length > 0 && !('data' in props.resource)) {
        return props.resource
    }
    
    // Return empty array if no data is provided
    return []
})

// Filtered data based on local search
const compResourceData = computed(() => {
    if (!localSearchQuery.value) {
        return baseResourceData.value
    }
    
    const query = localSearchQuery.value.toLowerCase()
    return baseResourceData.value.filter((product: any) => {
        const name = (product.name || '').toLowerCase()
        const code = (product.code || '').toLowerCase()
        const description = (product.description || '').toLowerCase()
        return name.includes(query) || code.includes(query) || description.includes(query)
    })
})

const compResourceMeta = computed(() => {
    // If we have meta in props, use it
    if (props.meta && Object.keys(props.meta).length > 0) {
        return props.meta
    }

    // Check for resource meta
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

    // Default meta if no data
    return props.resource || {}
})

const hasData = computed(() => {
    if (compResourceData.value.length > 0) {
        return true
    }

    return compResourceMeta.value.total > 0
})

// Search functionality - ES6 arrow functions
const changeSearchInputValue = (key: string, value: any) => {
    if (visitCancelToken.value && props.preventOverlappingRequests) {
        visitCancelToken.value.cancel()
    }

    const intKey = findDataKey('searchInputs', key)
    queryBuilderData.value.searchInputs[intKey].value = value
    queryBuilderData.value.cursor = null
    queryBuilderData.value.page = 1
}

const changeGlobalSearchValue = debounce((value?: string) => {
    changeSearchInputValue('global', value)
}, props.inputDebounceMs)

const findDataKey = (dataKey: string, key: string) => {
    return findKey(queryBuilderData.value[dataKey], (value) => {
        return value.key === key
    })
}

// Reset functionality - ES6 arrow function
const resetQuery = () => {
    forEach(queryBuilderData.value.filters, (filter, key) => {
        queryBuilderData.value.filters[key].value = null
    })

    forEach(queryBuilderData.value.searchInputs, (filter, key) => {
        queryBuilderData.value.searchInputs[key].value = null
    })

    queryBuilderData.value.sort = null
    queryBuilderData.value.cursor = null
    queryBuilderData.value.page = 1
}

// Pagination - ES6 arrow function
const onPerPageChange = (value: number) => {
    queryBuilderData.value.cursor = null
    queryBuilderData.value.perPage = value
    queryBuilderData.value.page = 1
}

// Query string generation - ES6 arrow functions
const getFilterForQuery = () => {
    let filtersWithValue = {}

    forEach(queryBuilderData.value.searchInputs, (searchInput) => {
        if (searchInput.value !== null) {
            filtersWithValue[searchInput.key] = searchInput.value
        }
    })

    forEach(queryBuilderData.value.filters, (filters) => {
        if (filters.value !== null) {
            filtersWithValue[filters.key] = filters.value
        }
    })

    return filtersWithValue
}

const dataForNewQueryString = () => {
    const filterForQuery = getFilterForQuery()
    const queryData: any = {}

    if (Object.keys(filterForQuery).length > 0) {
        queryData.filter = filterForQuery
    }

    const cursor = queryBuilderData.value.cursor
    const page = queryBuilderData.value.page
    const sort = queryBuilderData.value.sort
    const perPage = queryBuilderData.value.perPage

    if (cursor) {
        queryData.cursor = cursor
    }

    if (page > 1) {
        queryData.page = page
    }

    if (perPage > 1) {
        queryData.perPage = perPage
    }

    if (sort) {
        queryData.sort = sort
    }

    return queryData
}

const generateNewQueryString = () => {
    const queryStringData = qs.parse(location.search.substring(1))
    const prefix = props.name === 'default' ? '' : props.name + '_'

    forEach(['filter', 'columns', 'cursor', 'sort'], (key) => {
        delete queryStringData[prefix + key]
    })

    delete queryStringData[pageName.value]

    forEach(dataForNewQueryString(), (value, key) => {
        if (key === 'page') {
            queryStringData[pageName.value] = value
        } else {
            queryStringData[prefix + key] = value
        }
    })

    let query = qs.stringify(queryStringData, {
        encodeValuesOnly: true,
        skipNulls: true,
        strictNullHandling: true,
        arrayFormat: 'comma',
    })

    if (!query || query === pageName.value + '=1') {
        query = ''
    }

    return query
}

// Navigation
const visit = (url?: string) => {
    if (!url) {
        return
    }

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
                    queryBuilderData.value.cursor = queryBuilderProps.value.cursor
                    queryBuilderData.value.page = queryBuilderProps.value.page
                }
                updates.value++
            },
        },
    )
}

// Watch for query changes
watch(queryBuilderData, async () => {
    try {
        visit(location.pathname + '?' + generateNewQueryString())
    } catch {
        console.error("Can't visit expected path")
    }
}, { deep: true })

// Local search handlers - ES6 arrow functions
const handleLocalSearch = debounce((value?: string) => {
    localSearchQuery.value = value || ''
}, 300)

const clearLocalSearch = () => {
    localSearchQuery.value = ''
}

// Favorite toggle handler - ES6 arrow function
const toggleFavorite = (product: any) => {
    // Toggle the local state immediately for better UX
    product.is_favourite = !product.is_favourite
    
    // Emit event to parent component to handle the API call
    emit('toggleFavorite', product)
    
    // Show notification
    if (product.is_favourite) {
        notify({
            title: 'Added to favorites',
            text: `${product.name || 'Product'} has been added to your favorites`,
            type: 'success',
            duration: 3000
        })
    } else {
        notify({
            title: 'Removed from favorites',
            text: `${product.name || 'Product'} has been removed from your favorites`,
            type: 'info',
            duration: 3000
        })
    }
}
</script>