<script setup lang="ts">
import { faHeart } from '@far';
import { faCircle, faStar } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { getStyles } from "@/Composables/styles"
import { computed } from "vue";

const dummyProductImage = '/product/product_dummy.jpeg'

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
}>()

const products = [
  {
    title: 'Macrame Soft Chandelier - Natural',
    sku: 'Macrame-C1',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 41,
    inStock: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Soft Chandelier - Black',
    sku: 'Macrame-C2',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 41,
    inStock: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Natural',
    sku: 'Macrame-C3',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 41,
    inStock: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  {
    title: 'Macrame Large Drop Chandelier - Black',
    sku: 'Macrame-C4',
    rrp: 3.95,
    price: 9.6,
    oldPrice: 9.6,
    memberPrice: 8.0,
    oldMemberPrice: 8.0,
    stock: 0,
    inStock: false,
    bestseller: true,
    memberOnly: true,
    image: ''
  },
  
]

const responsiveGridClass = computed(() => {
  const perRow = props.modelValue?.settings?.per_row ?? {}

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
  <div
    class="grid gap-6 p-4"
    :class="responsiveGridClass"
    :style="getStyles(modelValue.container?.properties, screenType)"
  >
    <div
      v-for="(product, index) in products"
      :key="index"
      class="border p-3 relative rounded shadow-sm bg-white"
    >
      <!-- Bestseller Badge -->
      <div
        v-if="product.bestseller"
        class="absolute top-2 left-2 bg-white border border-black text-black text-xs font-bold px-2 py-0.5 rounded"
      >
        BESTSELLER
      </div>

      <!-- Favorite Icon -->
      <FontAwesomeIcon
        :icon="faHeart"
        class="absolute top-2 right-2 text-gray-400 text-xl"
      />

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
        <div class="flex items-center gap-1 text-green-600">
          <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
          <span>({{ product.stock }})</span>
        </div>
        <div class="flex items-center space-x-[1px] text-gray-500">
          <FontAwesomeIcon
            v-for="i in 5"
            :key="i"
            :class="i <= product.rating ? 'fas' : 'far'"
            :icon="faStar"
            class="text-xs"
          />
          <span class="ml-1">{{ product.stock }}</span>
        </div>
      </div>

      <!-- Prices -->
      <div class="mb-3">
        <div class="flex justify-between text-sm font-semibold">
          <span>£{{ product.price.toFixed(2) }}
            <span class="text-xs">({{ (product.price / 8).toFixed(2) }}/Piece)</span>
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div v-if="product.inStock" class="flex items-center gap-2">
        <div class="flex items-center border px-2 rounded">
          <button class="text-lg font-bold text-gray-600">-</button>
          <span class="px-2 text-sm">1</span>
          <button class="text-lg font-bold text-gray-600">+</button>
        </div>
        <button class="bg-gray-800 text-white px-3 py-1 rounded text-sm w-full">
          Order Now
        </button>
      </div>

      <div v-else>
        <button class="w-full text-sm px-3 py-1 bg-gray-300 text-gray-600 rounded" disabled>
          Out of Stock
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped></style>
