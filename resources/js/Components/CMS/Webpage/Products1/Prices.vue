<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Image as ImageTS } from "@/types/Image"
import { trans } from "laravel-vue-i18n"

const layout = inject("layout", retinaLayoutStructure)
const locale = useLocaleStore()

interface ProductResource {
  id: number
  name: string
  code: string
  image?: {
    source: ImageTS,
  }
  rpp?: number
  unit: string
  stock: number
  rating: number
  price: number
  url: string | null
  units: number
  bestseller?: boolean
  is_favourite?: boolean
  exist_in_portfolios_channel: number[]
  is_exist_in_all_channel: boolean
  top_seller: number | null
  web_images: {
    main: {
      original: ImageTS,
      gallery: ImageTS
    }
  }
}

defineProps<{
  product: ProductResource
  currency?: {
    code: string
    name: string
  }
}>()



</script>

<template>
  <div v-if="layout?.iris?.is_logged_in" class="border-t border-b border-gray-200 p-1 px-0 mb-1
         flex flex-col gap-1 text-gray-800 tabular-nums text-xs">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
      <span class="font-medium">
        {{ trans("Retail") }} :
      </span>

      <span class="font-semibold">
        {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit || 0) }}
        <span class="text-gray-600">/{{ product.unit }}</span>
      </span>
    </div>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between text-gray-500">
      <span class="font-medium">
        {{ trans("Profit") }} :
      </span>

      <span class="font-semibold flex items-center">
        {{ locale.currencyFormat(currency?.code, product?.profit_per_unit || 0) }}
        <span v-tooltip="trans('Profit margin')" class="ml-1">
          ({{ product?.margin }})
        </span>
      </span>
    </div>
  </div>

  <div v-if="layout?.iris?.is_logged_in" class="p-1 px-0 mb-3
         flex flex-col gap-1 text-gray-800 tabular-nums text-xs">
    <div v-if="product.units == 1" class="flex flex-col md:flex-row md:items-center md:justify-between">
      <span class="font-medium">
        {{ trans("Price") }} :
      </span>

      <span class="font-semibold">
        {{ locale.currencyFormat(currency?.code, product.price) }}
        <span class="text-gray-600">/{{ product.unit }}</span>
      </span>
    </div>
    <div v-else class="flex flex-col gap-1">
      <!-- Label -->
      <span class="font-medium">
        {{ trans("Price") }} :
      </span>

      <!-- Value row -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1">
        <!-- Outer price -->
        <span class="font-semibold">
          {{ locale.currencyFormat(currency?.code, product.price) }}
          / {{ trans("outer") }}
        </span>

        <!-- Per unit price -->
        <span class="text-gray-600 text-xs">
          {{ locale.currencyFormat(currency?.code, product.price_per_unit) }}
          / {{ product.unit }}
        </span>
      </div>
    </div>
  </div>


</template>


<style scoped></style>