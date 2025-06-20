<script setup lang="ts">
import { faFilter, faTimes, faBoxOpen } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { getStyles } from '@/Composables/styles'
import { ref, onMounted, watch, computed } from 'vue'
import axios from 'axios'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import ProductRender from './ProductRender.vue'
import FilterProducts from './FilterProduct.vue'
import Drawer from 'primevue/drawer'
import Skeleton from 'primevue/skeleton'
import { debounce } from 'lodash-es'

const props = defineProps<{
    fieldValue: {
        products_route: {
            iris: {
                route_products : routeType,
                route_out_of_stock_products : routeType
            }
            workshop: routeType
        }
        container?: any
    }
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const products = ref<any[]>([])
const loadingInitial = ref(true)
const loadingMore = ref(false)
const q = ref('')
const orderBy = ref('created_at_desc')
const page = ref(1)
const lastPage = ref(1)
const filter = ref({ data: {} })
const showFilters = ref(false)
const showAside = ref(false)

const loadingOutOfStock = ref(false)
const isFetchingOutOfStock = ref(false)

function buildFilters(): Record<string, any> {
    const filters: Record<string, any> = {}
    const raw = filter.value.data || {}

    for (const [key, val] of Object.entries(raw)) {
        if (val === null || val === undefined || val === '') continue
        if (typeof val === 'object' && !Array.isArray(val)) {
            for (const [subKey, subVal] of Object.entries(val)) {
                if (subVal === null || subVal === undefined || subVal === '') continue
                filters[subKey] = subVal
            }
        } else {
            filters[key] = val
        }
    }

    return filters
}

const fetchProducts = async (isLoadMore = false) => {
    if (isLoadMore) {
        loadingMore.value = true
    } else {
        loadingInitial.value = true
    }

    const filters = buildFilters()

    const useOutOfStock = isFetchingOutOfStock.value
    const currentRoute = useOutOfStock
        ? props.fieldValue.products_route.iris.route_out_of_stock_products
        : props.fieldValue.products_route.iris.route_products

    try {
        const response = await axios.get(route(currentRoute.name, {
            ...currentRoute.parameters,
            ...filters,
            'filter[global]': q.value,
            index_sort: orderBy.value,
            index_perPage: 25,
            page: page.value,
        }))

        const data = response.data
        lastPage.value = data?.meta.last_page ?? 1

        if (isLoadMore) {
            products.value = [...products.value, ...(data?.data ?? [])]
        } else {
            products.value = data?.data ?? []
        }

        // If we've reached the end of in-stock products and haven't fetched out-of-stock yet
        if (!useOutOfStock && page.value >= lastPage.value) {
            isFetchingOutOfStock.value = true
            page.value = 1 // reset page for out-of-stock
            await fetchProducts(true)
        }

    } catch (error) {
        console.error(error)
        notify({ title: 'Error', text: 'Failed to load products.', type: 'error' })
    } finally {
        loadingInitial.value = false
        loadingMore.value = false
    }
}


const debFetchProducts = debounce(fetchProducts, 300)

const handleSearch = () => {
    page.value = 1
    debFetchProducts(false)
}

watch([q, orderBy], () => {
    page.value = 1
    debFetchProducts(false)
}, { deep: true })

watch(filter, () => {
    page.value = 1
    debFetchProducts(false)
}, { deep: true })

const loadMore = () => {
    if (page.value < lastPage.value && !loadingMore.value) {
        page.value += 1
        debFetchProducts(true)
    }
}

const sortKey = ref<'price' | 'name' | 'code' | 'created_at'>('created_at')
const isAscending = ref(true)


const getArrow = (key: typeof sortKey.value) => {
  if (sortKey.value !== key) return ''
  return isAscending.value ? '↑' : '↓'
}


const isMobile = computed(() => props.screenType === 'mobile')

onMounted(() => {
    // Ambil dari URL atau props
    const urlParams = new URLSearchParams(window.location.search)
    const sortParam = urlParams.get('order_by')

    if (sortParam) {
        orderBy.value = sortParam
        // Set sortKey dan isAscending agar sinkron
        const key = sortParam.replace('-', '')
        sortKey.value = key as typeof sortKey.value
        isAscending.value = !sortParam.startsWith('-')
    }

    debFetchProducts()
})

const updateQueryParams = () => {
    const url = new URL(window.location.href)
    url.searchParams.set('order_by', orderBy.value)
    window.history.replaceState({}, '', url.toString())
}

const toggleSort = (key: typeof sortKey.value) => {
    if (sortKey.value === key) {
        isAscending.value = !isAscending.value
    } else {
        sortKey.value = key
        isAscending.value = true
    }

    orderBy.value = isAscending.value ? key : `-${key}`
    updateQueryParams()
    handleSearch()
}


const channels = ref({
    isLoading: false,
    list: []
})
const fetchChannels = async () => {
    channels.value.isLoading = true
    try {
        const response = await axios.get(route('iris.json.channels.index'))
        console.log('Channels response:', response.data.data)
        
        channels.value.list = response.data.data || []

        
    } catch (error) {
        console.log(error)
        notify({ title: 'Error', text: 'Failed to load channels.', type: 'error' })
    } finally {
        channels.value.isLoading = false
    }
}

const responsiveGridClass = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row ?? {}

  const columnCount = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }

  const count = columnCount[props.screenType] ?? 1
  return `grid-cols-${count}`
})
</script>

<template>
    <div class="flex flex-col lg:flex-row" :style="getStyles(fieldValue.container?.properties, screenType)">

        <!-- Sidebar Filters for Desktop -->
        <transition name="slide-fade">
            <aside v-show="!isMobile && showAside" class="w-68 p-4 transition-all duration-300 ease-in-out">
                <FilterProducts v-model="filter" />
            </aside>
        </transition>

        <!-- Main Content -->
        <main class="flex-1">
            <!-- Search & Sort -->
            <div class="px-4 pt-4 pb-2 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center w-full md:w-1/3 gap-2">
                    <Button v-if="isMobile" :icon="faFilter" @click="showFilters = true" class="!p-2 !w-auto"
                        aria-label="Open Filters" />

                    <!-- Sidebar Toggle for Desktop -->
                    <div v-else class="p-4">
                        <Button :icon="faFilter" @click="showAside = !showAside" class="!p-2 !w-auto"
                            aria-label="Open Filters" />

                    </div>
                    <input v-model="q" @keyup.enter="handleSearch" type="text" placeholder="Search products..."
                        class="flex-grow px-4 py-2 border rounded shadow-sm pr-10" />
                    <button v-if="q" @click="() => { q = ''; handleSearch() }" class="text-gray-400 hover:text-black"
                        aria-label="Clear search">
                        <FontAwesomeIcon :icon="faTimes" class="text-lg text-red-500" />
                    </button>
                </div>

                <!-- Sort Tabs -->
                <div class="flex space-x-6 overflow-x-auto mt-2 md:mt-0 border-b border-gray-300">
                    <button
                        v-for="key in ['created_at', 'price', 'code', 'name']"
                        :key="key"
                        @click="toggleSort(key)"
                        class="pb-2 text-sm font-medium whitespace-nowrap flex items-center gap-1"
                        :class="{
                        'border-b-2 text-[#1F2937] border-[#1F2937]': sortKey === key,
                        'text-gray-600 hover:text-[#1F2937]': sortKey !== key
                        }"
                        :disabled="loadingInitial || loadingMore"
                    >
                        {{ key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }} {{ getArrow(key) }}
                    </button>
                </div>
            </div>

            <!-- Product Grid -->
            <div :class="responsiveGridClass"  class="grid gap-6 p-4"
                :style="getStyles(fieldValue?.container?.properties, screenType)">
                <template v-if="loadingInitial">
                    <div v-for="n in 8" :key="n" class="border p-3 rounded shadow-sm bg-white">
                        <Skeleton height="200px" class="mb-3" />
                        <Skeleton width="80%" class="mb-2" />
                        <Skeleton width="60%" />
                    </div>
                </template>

                <template v-else-if="products.length">
                    <div v-for="(product, index) in products" :key="index"
                        class="border p-3 relative rounded shadow-sm bg-white">
                        <ProductRender
                            :channels="channels"
                            :product="product"
                            @refreshChannels="() => fetchChannels()"
                        />
                    </div>
                </template>

                <template v-else>
                    <div class="col-span-full text-center py-10 text-gray-500">
                        <FontAwesomeIcon :icon="faBoxOpen" class="text-4xl mb-4 text-gray-400" />
                        <p>No products found.</p>
                    </div>
                </template>
            </div>

            <!-- Load More -->
            <div v-if="page < lastPage && !loadingInitial" class="flex justify-center my-4">
                <Button @click="loadMore" class="px-4 py-2 text-white rounded shadow disabled:opacity-50"
                    :disabled="loadingMore" style="background-color: #1F2937;">
                    <template v-if="loadingMore">Loading...</template>
                    <template v-else>Load More</template>
                </Button>
            </div>
        </main>

        <!-- Mobile Filters Drawer -->
        <Drawer v-model:visible="showFilters" position="left" :modal="true" :dismissable="true" :closeOnEscape="true"
            class="w-80 transition-transform duration-300 ease-in-out">
            <div class="flex justify-between items-center px-4 py-2 border-b">
                <h3 class="text-lg font-semibold">Filters</h3>
                <button @click="showFilters = false" aria-label="Close filters">
                    <FontAwesomeIcon :icon="faTimes" class="text-xl" />
                </button>
            </div>
            <div class="p-4">
                <FilterProducts v-model="filter" />
            </div>
        </Drawer>
    </div>
</template>


<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}

.slide-fade-enter-from,
.slide-fade-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}

aside {
    transition: all 0.3s ease;
}
</style>
