<script setup lang="ts">
import { faTimes } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { getStyles } from '@/Composables/styles'
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

//import { debounce } from 'lodash' commented becuse breaks SSR
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import ProductRender from './ProductRender.vue'

const props = defineProps<{
  fieldValue: {
    products_route: {
      iris: routeType
      workshop: routeType
    }
    container?: any
  }
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()


const products = ref<any[]>([])
const loading = ref(false)
const q = ref('')
const orderBy = ref('created_at_desc')
const page = ref(1)
const lastPage = ref(1)

const filterFamily = ref('')
const filterPriceMin = ref<number | null>(null)
const filterPriceMax = ref<number | null>(null)
const showFilters = ref(false)

function buildFilters() {
  const filters: Record<string, any> = {}
  if (filterPriceMin.value !== null) filters['between[price_min]'] = filterPriceMin.value
  if (filterPriceMax.value !== null) filters['between[price_max]'] = filterPriceMax.value
  return filters
}

const fetchProducts = async (isLoadMore = false) => {
  loading.value = true
  try {
    const filters = buildFilters()
    const response = await axios.get(route(props.fieldValue.products_route.iris.name, {
      productCategory: props.fieldValue.products_route.iris.parameters[0],
      ...filters,
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
  } catch (error) {
    console.error('Failed to fetch products:', error)
    notify({ title: 'Error', text: 'Failed to load products.', type: 'error' })
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchProducts(false)
}

watch([q, orderBy, filterFamily, filterPriceMin, filterPriceMax], debounce(() => {
  page.value = 1
  fetchProducts(false)
}, 500))

const loadMore = () => {
  if (page.value < lastPage.value) {
    page.value += 1
    fetchProducts(true)
  }
}

const orderOptions = [
  { label: 'Newest', value: 'created_at' },
  { label: 'Oldest', value: '-created_at' },
  { label: 'Price ↑', value: '-price' },
  { label: 'Price ↓', value: 'price' },
]

const selectOrder = (val: string) => {
  orderBy.value = val
  handleSearch()
}

onMounted(() => {
  fetchProducts()
})
</script>

<template>
  <div class="flex flex-col lg:flex-row">
    <!-- Sidebar Filters -->
    <aside
      class="w-full lg:w-64 bg-gray-50 border-r p-4"
      :class="{ hidden: screenType === 'mobile' && !showFilters }"
    >
      <h2 class="font-bold text-sm mb-4">Filters</h2>

      <div class="mb-4">
        <label class="text-sm font-semibold mb-1 block">Price Min</label>
        <input
          v-model.number="filterPriceMin"
          type="number"
          min="0"
          placeholder="0"
          class="w-full border rounded px-3 py-2"
        />
      </div>

      <div>
        <label class="text-sm font-semibold mb-1 block">Price Max</label>
        <input
          v-model.number="filterPriceMax"
          type="number"
          min="0"
          placeholder="99999999999"
          class="w-full border rounded px-3 py-2"
        />
      </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1">
      <!-- Search & Sort Tabs -->
      <div class="px-4 pt-4 pb-2 border-b flex flex-col md:flex-row justify-between items-center gap-4">
        <!-- Mobile Filter Toggle -->
        <button
          class="lg:hidden px-3 py-1 border rounded text-sm text-white"
          @click="showFilters = !showFilters"
          style="background-color: #1F2937;"
        >
          {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
        </button>

        <!-- Search Input -->
        <div class="relative w-full md:w-1/3">
          <input
            v-model="q"
            @keyup.enter="handleSearch"
            type="text"
            placeholder="Search products..."
            class="w-full px-4 py-2 border rounded shadow-sm pr-10"
          />
          <button
            v-if="q"
            @click="() => { q = ''; handleSearch() }"
            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-black"
            aria-label="Clear search"
          >
            <FontAwesomeIcon :icon="faTimes" class="text-lg text-red-500" />
          </button>
        </div>

        <!-- Sort Tabs -->
        <div class="flex space-x-6 border-b border-gray-300 overflow-x-auto">
          <button
            v-for="option in orderOptions"
            :key="option.value"
            @click="selectOrder(option.value)"
            class="pb-2 text-sm font-medium whitespace-nowrap"
            :class="{
              'border-b-2 text-[#1F2937] border-[#1F2937]': orderBy === option.value,
              'text-gray-600 hover:text-[#1F2937]': orderBy !== option.value
            }"
          >
            {{ option.label }}
          </button>
        </div>
      </div>

      <!-- Product Grid -->
      <div
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-4"
        :style="getStyles(fieldValue?.container?.properties, screenType)"
      >
        <div
          v-if="products.length"
          v-for="(product, index) in products"
          :key="index"
          class="border p-3 relative rounded shadow-sm bg-white"
        >
          <ProductRender :product="product" />
        </div>
      </div>

      <!-- Load More Button -->
      <div v-if="page < lastPage" class="flex justify-center mt-4">
        <button
          @click="loadMore"
          class="px-4 py-2 text-white rounded shadow"
          :disabled="loading"
          style="background-color: #1F2937;"
        >
          <span v-if="loading">Loading...</span>
          <span v-else>Load More</span>
        </button>
      </div>
    </main>
  </div>
</template>

<style scoped>
aside {
  transition: all 0.3s ease;
}
</style>
