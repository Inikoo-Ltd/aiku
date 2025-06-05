<script setup lang="ts">
import { faHeart } from '@far'
import { faCircle, faStar } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { getStyles } from '@/Composables/styles'

import { ref, onMounted, onUnmounted, inject } from 'vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'

import { layoutStructure } from '@/Composables/useLayoutStructure'
import { routeType } from '@/types/route'

const dummyProductImage = '/product/product_dummy.jpeg'
const isMember = false

const props = defineProps<{
  fieldValue: {
    products_route: {
      iris: routeType
      workshop:routeType
    }
    container?: any
  }
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout = inject('layout', layoutStructure)

const products = ref<any[]>([])
const loading = ref(false)
let timeoutId: any
const q = ref('')
const meta = ref(1)

const fetchProducts = async () => {
  loading.value = true
  try {
    const response = await axios.get(route(props.fieldValue.products_route.iris.name, props.fieldValue.products_route.iris.parameters))
    products.value = response.data?.data ?? []
  } catch (error) {
    console.error('Failed to fetch products:', error)
    notify({
      title: 'Error',
      text: 'Failed to load products.',
      type: 'error',
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchProducts()
})
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-4"
    :style="getStyles(fieldValue.container?.properties, screenType)">
    <div v-for="(product, index) in products" :key="index" class="border p-3 relative rounded shadow-sm bg-white">

      <!-- Bestseller Badge -->
      <div v-if="product.bestseller"
        class="absolute top-2 left-2 bg-white border border-black text-black text-xs font-bold px-2 py-0.5 rounded">
        BESTSELLER
      </div>

      <!-- Favorite Icon -->
      <FontAwesomeIcon :icon="faHeart" class="absolute top-2 right-2 text-gray-400 text-xl"></FontAwesomeIcon>

      <!-- Product Image -->
      <img :src="dummyProductImage" class="w-full h-62 object-cover mb-3 rounded" />

      <!-- Title -->
      <div class="font-medium text-sm mb-1">{{ product.title }}</div>

      <!-- SKU and RRP -->
      <div class="flex justify-between text-xs text-gray-600 mb-1">
        <span>{{ product.sku }}</span>
        <span>RRP: £{{ product.rrp }}/Piece</span>
      </div>

      <!-- Rating and Stock -->
      <div class="flex justify-between items-center text-xs mb-2">
        <!-- Stock -->
        <div class="flex items-center gap-1 text-green-600">
          <FontAwesomeIcon :icon="faCircle" class="text-[8px]"></FontAwesomeIcon>
          <span>({{ product.stock }})</span>
        </div>
        <!-- Stars -->
        <div class="flex items-center space-x-[1px] text-gray-500">
          <FontAwesomeIcon v-for="i in 5" :key="i" :class="i <= product.rating ? 'fas' : 'far'" :icon="faStar"
            class="text-xs"></FontAwesomeIcon>
          <span class="ml-1">{{ product.stock }}</span>
        </div>
      </div>



      <!-- Prices -->
      <div class="mb-3">
        <!-- Retail Price -->
        <div class="flex justify-between text-sm font-semibold">
          <span>£{{ product.price.toFixed(2) }} <span class="text-xs">({{ (product.price / 8).toFixed(2)
          }}/Piece)</span></span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="product.inStock" class="flex items-center gap-2">
        <!-- Quantity Selector -->
        <div class="flex items-center border px-2 rounded">
          <button class="text-lg font-bold text-gray-600">-</button>
          <span class="px-2 text-sm">1</span>
          <button class="text-lg font-bold text-gray-600">+</button>
        </div>
        <!-- Order Button -->
        <button class="bg-gray-800 text-white px-3 py-1 rounded text-sm w-full">
          Order Now
        </button>
      </div>

      <!-- Out of Stock -->
      <div v-else>
        <button class="w-full text-sm px-3 py-1 bg-gray-300 text-gray-600 rounded" disabled>
          Out of Stock
        </button>
      </div>
    </div>
  </div>
</template>


<style scoped></style>