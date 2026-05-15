<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ctrans } from "@/Composables/useTrans"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"
import ProductRender from '@/Components/CMS/Webpage/Products1/Dropshipping/ProductRender.vue'

import { Swiper, SwiperSlide } from 'swiper/vue'

import 'swiper/css'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Navigation } from "swiper/modules"

library.add(faChevronLeft, faChevronRight)

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
  const perRow = props.fieldValue?.settings?.per_row
  switch (props.screenType) {
    case 'mobile':
      return perRow?.mobile ?? 2
    case 'tablet':
      return perRow?.tablet ?? 4
    default:
      return perRow?.desktop ?? 5
  }
})

const products = computed(() => props.fieldValue?.products_recommended ?? [])

const minAmountShown = computed(() => props.fieldValue?.recommendation_settings?.min_amt_shown ?? 5)

const shouldShowComponent = computed(() => products.value.length >= minAmountShown.value)

const shouldShowNavigation = computed(() => products.value.length > slidesPerView.value)




const componentId = computed(() => props.fieldValue?.id ?? `recommended-master${props.indexBlock ?? ''}`)

const titleContent = computed(() => props.fieldValue?.recommendation_settings?.title ?? ctrans('Recommendations'))



const prevEl = ref(null)
const nextEl = ref(null)

</script>

<template>
  <div
    v-if="shouldShowComponent"
    :id="componentId"
    class="w-full pb-6"
    :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType),
    }"
    :dropdown-type="fieldValue?.settings?.products_data?.type"
  >
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
      <button ref="prevEl" class="swiper-nav-button  left-0 top-1/2">
        <FontAwesomeIcon :icon="faChevronLeft" />
      </button>

      <button ref="nextEl" class="swiper-nav-button  right-0 top-1/2">
        <FontAwesomeIcon :icon="faChevronRight" />
      </button>

      <!-- Swiper -->
      <div class="py-4  md:px-12 px-[50px]">
        <Swiper
          ref="swiperRef"
          :slides-per-view="slidesPerView"
          :space-between="20"
          :loop="shouldShowNavigation"
          :auto-height="false"
          :modules="[Navigation]"
          class="w-full px-[50px]"
          :navigation="{ prevEl, nextEl }"
        >
          <SwiperSlide v-for="(product, index) in products" :key="product?.id || index" class="!h-auto">
            <div class="h-full flex flex-col">
              <div v-if="product" class="flex-1 flex flex-col product-card">
                <ProductRenderEcom v-if="layout?.retina?.type === 'b2b'" :product="product" />
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
  @apply absolute top-1/2 -translate-y-1/2 z-10 bg-white border border-gray-300 rounded-full shadow-md p-2 hover:bg-gray-100 transition-all duration-300;
}

.swiper-nav-button svg {
  @apply text-gray-700 w-4 h-4;
}

.product-card :deep(img) {
@apply w-full max-w-[160px] sm:max-w-[220px] 2xl:max-w-[320px] aspect-square object-contain;
}
</style>