<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from "laravel-vue-i18n"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"
import ProductRender from '@/Components/CMS/Webpage/Products1/Dropshipping/ProductRender.vue'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation } from 'swiper/modules'

import 'swiper/css'
import 'swiper/css/navigation'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: any
  indexBlock?: number
  screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: any): void
  (e: "autoSave"): void
}>()

const layout = inject('layout', retinaLayoutStructure)

const prevEl = ref()
const nextEl = ref()

const slidesPerView = computed(() => {
  switch (props.screenType) {
    case 'mobile':
      return props.modelValue?.settings?.per_row.mobile || 2
    case 'tablet':
      return props.modelValue?.settings?.per_row.tablet || 4
    default:
      return props.modelValue?.settings?.per_row.desktop || 5
  }
})

const compSwiperOptions = computed(() => {
  return props.modelValue?.products_recommended ?? []
})

const sendMessageToParent = (type: string, value: any) => {
  window.parent.postMessage(
    {
      type,
      value,
    },
    '*'
  )
}
</script>

<template>
  <div v-if="compSwiperOptions.length >= (modelValue?.recommendation_settings?.min_amt_shown || 5)"
    :id="modelValue?.id ? modelValue?.id : 'recommended-master' + indexBlock" class="w-full pb-6" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue.container?.properties, screenType),
      width: 'auto'
    }" :dropdown-type="props.modelValue?.settings?.products_data?.type">
    <!-- Title -->
    <div class="px-4 py-6 pb-2 flex items-center justify-center">
      <div class="text-3xl font-semibold text-gray-800">
        <div v-if="modelValue?.recommendation_settings.title" v-html="modelValue?.recommendation_settings.title">
        </div>
        <div v-else>
          {{ ctrans("Recommendations") }}
        </div>
      </div>
    </div>

    <!-- Products -->
    <div v-if="compSwiperOptions.length" class="relative px-4 py-6" @click="() => {
      sendMessageToParent('activeBlock', indexBlock)
    }">
      <!-- Navigation -->
      <button ref="prevEl" class="swiper-nav-button left-0">
        <FontAwesomeIcon :icon="['fas', 'chevron-left']" />
      </button>

      <button ref="nextEl" class="swiper-nav-button right-0">
        <FontAwesomeIcon :icon="['fas', 'chevron-right']" />
      </button>

      <!-- Swiper -->
      <Swiper :modules="[Navigation]" :slides-per-view="slidesPerView" :space-between="20" :navigation="{
        prevEl,
        nextEl
      }" :loop="compSwiperOptions.length > slidesPerView" :auto-height="false" class="w-full">
        <SwiperSlide v-for="(product, index) in compSwiperOptions" :key="product?.id || index" class="!h-auto">
          <div class="h-full flex flex-col">
            <div v-if="product" class="flex-1 flex flex-col">
              <ProductRenderEcom v-if="layout.retina.type === 'b2b'" :product="product" />

              <ProductRender v-else :product="product" :productHasPortfolio="[]" />
            </div>

            <div v-else class="flex-1 flex items-center justify-center text-gray-400">
              No Product
            </div>
          </div>
        </SwiperSlide>
      </Swiper>
    </div>

    <!-- Empty -->
    <div v-else class="px-4 py-10 text-center text-gray-400">
      No products available
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
</style>