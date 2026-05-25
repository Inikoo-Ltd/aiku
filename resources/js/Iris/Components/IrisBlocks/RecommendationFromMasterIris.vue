<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { ctrans } from "@/Composables/useTrans"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products3/ProductRenderEcom3.vue"
import ProductRender from '@/Components/CMS/Webpage/Products1/Dropshipping/ProductRender.vue'

import { Swiper, SwiperSlide } from 'swiper/vue'

import 'swiper/css'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Navigation } from "swiper/modules"
import { get } from 'lodash-es'


interface FieldValue {
  id?: string
  settings?: {
    per_row?: {
      mobile?: number
      tablet?: number
      desktop?: number
    }
    products_data?: {
      type?: string
    }
  }
  recommendation_settings?: {
    min_amt_shown?: number
    title?: string
  }
  container?: {
    properties?: any
  }
  products_recommended?: any[]
}

const props = defineProps<{
  fieldValue: FieldValue
  webpageData?: any
  blockData?: any
  indexBlock?: number
  screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
  (e: "update:fieldValue", value: any): void
  (e: "autoSave"): void
}>()

const layout = inject('layout', retinaLayoutStructure)


const slidesPerView = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row ?? {}
  return {
    desktop: perRow.desktop ?? 5,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }[props.screenType] ?? 1
})

const products = computed(() => props.fieldValue?.products_recommended ?? [])

const minAmountShown = computed(() => props.fieldValue?.recommendation_settings?.min_amt_shown ?? 5)

const shouldShowComponent = computed(() => products.value.length >= minAmountShown.value)

const shouldShowNavigation = computed(() => products.value.length > slidesPerView.value)

const componentId = computed(() => props.fieldValue?.id ?? `recommended-master${props.indexBlock ?? ''}`)

const titleContent = computed(() => props.fieldValue?.recommendation_settings?.title ?? ctrans('Related Products'))


const prevEl = ref(null)
const nextEl = ref(null)

console.log('related product :', props)
</script>

<template>
  <div v-if="shouldShowComponent" :id="componentId" class="w-full pb-6 related-product" :style="{
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
    ...getStyles(fieldValue.container?.properties, screenType),
  }" :dropdown-type="fieldValue?.settings?.products_data?.type">
    <!-- Title -->
    <div class="px-4 py-6 pb-2 flex items-center justify-center">
      <div class="text-3xl font-semibold text-gray-800">
        <div v-if="fieldValue?.recommendation_settings?.title" v-html="titleContent"></div>
        <div v-else>{{ titleContent }}</div>
      </div>
    </div>

    <!-- Products -->
    <div v-if="products.length" class="relative px-4 py-6">
      <!-- Navigation -->
      <button ref="prevEl" class="swiper-nav-button hidden lg:block left-12 top-1/2">
        <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-lg" />
      </button>

      <button ref="nextEl" class="swiper-nav-button hidden lg:block right-12 top-1/2">
        <FontAwesomeIcon :icon="faChevronCircleRight" class="text-lg" />
      </button>

      <!-- Swiper -->
      <div class="py-4 md:px-12 lg:px-[50px] px-0">
        <Swiper ref="swiperRef" :slides-per-view="slidesPerView" :loop="shouldShowNavigation" :auto-height="false"
          :modules="[Navigation]" class="w-full" :navigation="{ prevEl, nextEl }">
          <SwiperSlide v-for="(product, index) in products" :key="product?.id || index" class="!h-auto">
            <div class="h-full flex flex-col px-3 2xl:px-8 lg:px-8">
              <div v-if="product" class="flex-1 flex flex-col product-card">
                <ProductRenderEcom v-if="layout?.retina?.type === 'b2b'"
                  :buttonStyleHover="layout?.buttonBasket?.buttonStyleHover"
                  :buttonStyle="layout?.buttonBasket?.buttonStyle" :product="product" :hideLogin="true"
                  :hasInBasket="get(layout, ['family_page', 'productInBasket', 'list', product.id], [])" />
                <ProductRender v-else :product="product" :productHasPortfolio="[]" />
              </div>
            </div>
          </SwiperSlide>
        </Swiper>
      </div>
    </div>
  </div>
</template>

<style scoped>
.swiper-nav-button {
  @apply absolute top-1/2 -translate-y-1/2 z-10
}

.swiper-nav-button svg {
  @apply text-gray-700 w-4 h-4;
}

:deep(.related-product .best-seller-badge-container) {
  @apply absolute top-2 left-[2.5rem] border border-black text-xs font-bold px-2 py-0.5 rounded z-10;
}
</style>