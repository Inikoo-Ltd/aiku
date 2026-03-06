<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { SelectItemCollector } from '@/Composables/Unique/LuigiDataCollector'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ProductHit } from '@/types/Luigi/LuigiTypes'
import { faCircle } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Prices2 from '@/Components/CMS/Webpage/Products1/Prices2.vue'
import Image from '@/Components/Image.vue'
import Prices from '@/Components/CMS/Webpage/Products1/Prices.vue'
import NewAddToCartButton from '@/Components/CMS/Webpage/Products/NewAddToCartButton.vue'

const props = defineProps<{
  product: ProductHit
  isProductLoading: (productId: string) => boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
const currency = layout?.iris?.currency
const isLoadingVisit = ref(false)

console.log('dddd',layout.buttonBasket)

</script>

<template>
  <div class="relative flex flex-col h-full md:p-3 rounded bg-white">

    <!-- IMAGE -->
    <div class="mb-3 flex justify-center relative">
      <component :is="product.url ? LinkIris : 'div'" :href="product.url"
        class="w-full max-w-[220px] aspect-square flex items-center justify-center"
        @start="() => (SelectItemCollector(product), isLoadingVisit = true)" @finish="() => isLoadingVisit = false">
        <Image :src="product?.web_images?.main?.original" :alt="product.name" class="object-contain w-full h-full" />
      </component>

      <div v-if="layout?.iris?.is_logged_in" class="absolute right-2 bottom-2">
        <NewAddToCartButton 
          v-if="product.stock" 
          :hasInBasket="layout?.family_page?.productInBasket?.[product.id]"
          :product="product" :key="product.id" 
          :addToBasketRoute="{ name: 'iris.models.transaction.store'}" 
          :updateBasketQuantityRoute="{ name: 'iris.models.transaction.update'}" 
          :buttonStyleHover="layout?.buttonBasket?.buttonStyleHover"
          :buttonStyle="layout?.buttonBasket?.buttonStyle"
        />
      </div>
    </div>

    <!-- TITLE -->
    <span class="mb-1 text-[13px] md:text-[16px] text-justify font-semibold leading-snug line-clamp-2 min-h-[3em]"  :title="product.name">
      <component :is="product.url ? LinkIris : 'div'"  :href="product.url" class="hover:underline"
        @start="() => (SelectItemCollector(product), isLoadingVisit = true)"
        @finish="() => isLoadingVisit = false">
        {{ product.name }}
      </component>
    </span>

    <!-- CODE -->
    <div class="text-xs text-gray-400 mb-2">
      {{ product.code }}
    </div>

    <!-- STOCK -->
    <div v-if="layout?.iris?.is_logged_in" class="flex items-center gap-1 text-xs mb-3"
      :class="Number(product.stock) > 0 ? 'text-green-600' : 'text-red-600'">
      <FontAwesomeIcon :icon="faCircle" class="text-[7px]" />
      <span>
        {{ locale.number(Number(product.stock)) }} {{ trans('available') }}
      </span>
    </div>

    <!-- LOADING -->
    <div v-if="isLoadingVisit" class="absolute inset-0 z-10 grid place-items-center bg-black/50 text-white text-4xl">
      <LoadingIcon />
    </div>

  </div>

  <!-- PRICES (KEEP COMPONENTS) -->
  <div v-if="layout?.iris?.is_logged_in">
    <Prices2 v-if="layout.retina?.type === 'b2b'" :product="product" :currency="currency" :basketButton="true" />
    <Prices v-else :product="product" :currency="currency" :basketButton="true" />
  </div>
</template>
