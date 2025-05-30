<script setup lang="ts">
import { faHeart } from '@far';
import { faCircle, faStar } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
const isMember = false

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
    bestseller : true,
    memberOnly: true,
    image: ''
  },
]
</script>
<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-4">
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
      <FontAwesomeIcon :icon="faHeart" class="absolute top-2 right-2 text-gray-400 text-xl"></FontAwesomeIcon>

      <!-- Product Image -->
      <img :src="product.image" class="w-full h-62 object-cover mb-3 rounded" />

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
          <FontAwesomeIcon
            v-for="i in 5"
            :key="i"
            :class="i <= product.rating ? 'fas' : 'far'"
            :icon="faStar"
            class="text-xs"
          ></FontAwesomeIcon>
          <span class="ml-1">{{ product.stock }}</span>
        </div>
      </div>

      <!-- Member Price Tag -->
      <div v-if="product.memberOnly && isMember" class="text-xs text-gray-500 mb-1">
        Member Price
      </div>

      <!-- Prices -->
      <div class="mb-3">
        <!-- Retail Price -->
        <div class="flex justify-between text-sm font-semibold">
          <span>£{{ product.price.toFixed(2) }} <span class="text-xs">({{ (product.price / 8).toFixed(2) }}/Piece)</span></span>
          <span class="text-[10px] text-gray-400 line-through">£{{ product.oldPrice.toFixed(2) }}</span>
        </div>

        <!-- Member Price -->
        <div
          v-if="product.memberOnly"
          class="flex justify-between text-sm font-semibold text-gray-500"
        >
          <span class="text-gray-400">£{{ product.memberPrice.toFixed(2) }} <span class="text-xs">(1.00/Piece)</span></span>
          <span class="text-[10px] line-through">£{{ product.oldMemberPrice.toFixed(2) }}</span>
        </div>
      </div>

      <!-- Member CTA -->
      <div v-if="product.memberOnly && !isMember" class="mb-3">
        <div class="text-xs text-gray-600 mb-1">NOT A MEMBER?</div>
        <button class="text-xs text-orange-500 border border-orange-500 px-2 py-1 rounded w-full font-semibold">
          Order 4 or more to get member price
        </button>
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
        <button
          class="w-full text-sm px-3 py-1 bg-gray-300 text-gray-600 rounded"
          disabled
        >
          Out of Stock
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
</style>