<script setup lang="ts">
import { faHeart } from '@far'
import { faCircle, faStar } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"



const locale = useLocaleStore();

defineProps<{
    product: {}
}>()
</script>

<template>
  <div class="relative flex flex-col justify-between h-full bg-white">

    <!-- Top Section -->
    <div>
      <!-- Bestseller Badge -->
      <div v-if="product.bestseller"
        class="absolute top-2 left-2 bg-white border border-black text-black text-xs font-bold px-2 py-0.5 rounded">
        BESTSELLER
      </div>

      <!-- Favorite Icon -->
      <FontAwesomeIcon :icon="faHeart" class="absolute top-2 right-2 text-gray-400 text-xl" />

      <!-- Product Image -->
      <div class="w-full h-64 mb-3 rounded">
        <Image :src="product.image?.source" alt="product image" :imageCover="true" />
      </div>

      <!-- Title -->
      <div class="font-medium text-sm mb-1">{{ product.name }}</div>

      <!-- SKU and RRP -->
      <div class="flex justify-between text-xs text-gray-600 mb-1 capitalize">
        <span>{{ product?.code }}</span>
        <span>
          RRP: {{ locale.currencyFormat(product?.currency?.code, (product.rpp || 0)) }}/ {{ product.unit }}
        </span>
      </div>

      <!-- Rating and Stock -->
      <div class="flex justify-between items-center text-xs mb-2">
        <div class="flex items-center gap-1"
             :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
          <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
          <span>({{ product.stock > 0 ? product.stock : 0 }})</span>
        </div>
        <div class="flex items-center space-x-[1px] text-gray-500">
          <FontAwesomeIcon v-for="i in 5" :key="i" :class="i <= product.rating ? 'fas' : 'far'" :icon="faStar"
                           class="text-xs" />
          <span class="ml-1">5</span>
        </div>
      </div>

      <!-- Prices -->
      <div class="mb-3">
        <div class="flex justify-between text-sm font-semibold">
          <span>{{ locale.currencyFormat(product?.currency?.code, product.price) }}</span>
          <span class="text-xs">({{ parseInt(product.units, 10) }}/{{ product.unit }})</span>
        </div>
      </div>
    </div>

    <!-- Bottom Section (fixed position in layout) -->
    <div>
      <div v-if="product.stock > 1" class="flex items-center gap-2 mt-2">
        <div class="flex items-center border px-2 rounded">
          <button class="text-lg font-bold text-gray-600">-</button>
          <span class="px-2 text-sm">1</span>
          <button class="text-lg font-bold text-gray-600">+</button>
        </div>
        <button class="bg-gray-800 text-white px-3 py-1 rounded text-sm w-full">
          Order Now
        </button>
      </div>
      <div v-else class="mt-2">
        <button class="w-full text-sm px-3 py-1 bg-gray-300 text-gray-600 rounded" disabled>
          Out of Stock
        </button>
      </div>
    </div>
  </div>
</template>
