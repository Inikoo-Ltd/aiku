<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import ProductRender from '@/Iris/Components/IrisBlocks/Products/ds/ProductCardDs/ProductCardDs1.vue'

import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import ProductRenderEcom from "@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom3.vue"
import { get } from 'lodash-es'

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Navigation, Pagination } from 'swiper/modules'

// Font Awesome
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'



const props = defineProps<{
  fieldValue: {
    settings: {
      products_data: {  //  GetWebBlockSeeAlso
        products: {}  //  ProductsWebpageResource
        top_sellers: {}
        current_family: {}
        other_family: {}
      }  // 
    }
  }
  webpageData?: any
  blockData?: Object,
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock:number
}>()

const emits = defineEmits<{
  (e: "update:fieldValue", value: string): void
  (e: "autoSave"): void
}>()


const layout: any = inject("layout", {})

const slidesPerView = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row ?? {}
  return {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }[props.screenType] ?? 1
})

// Refs untuk custom navigation
const prevEl = ref(null)
const nextEl = ref(null)

const compSwiperOptions = computed(() => {
  if (props.fieldValue?.settings?.products_data?.type == 'custom') {
    return props.fieldValue?.settings?.products_data?.products
  } else if (props.fieldValue?.settings?.products_data?.type == 'current-family') {
    return props.fieldValue?.settings?.products_data?.current_family?.option || []
  } else if (props.fieldValue?.settings?.products_data?.type == 'other-family') {
    return props.fieldValue?.settings?.products_data?.other_family?.option || []
  } else {
    return props.fieldValue?.settings?.products_data?.top_sellers || []
  }
})

console.log('see also', layout)
</script>

<template>
  <div :id="fieldValue?.id ? fieldValue?.id  : 'see-also-1'+indexBlock"   class="w-full pb-6" :style="{
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
    ...getStyles(fieldValue.container?.properties, screenType),
    width: '100%'
  }" :dropdown-type="props.fieldValue?.settings?.products_data?.type">
    <!-- Title -->
    <div class="px-3 py-6 pb-2">
      <div class="text-3xl font-semibold text-gray-800">
        <div v-html="fieldValue.title"></div>
      </div>
    </div>

    <div
      v-if="['luigi-trends', 'luigi-recently_ordered', 'luigi-last_seen', 'luigi-item_detail_alternatives'].includes(fieldValue.settings.products_data.type)">
      <!-- Render nothing due to deprecated -->
      <!-- <RecommendersLuigi1Iris :slidesPerView recommendation_type="trends" /> -->
    </div>

    <!-- Carousel with custom navigation -->
    <div v-else-if="compSwiperOptions?.length" class="relative px-4 py-6" >
      <!-- Tombol Navigasi Custom -->
      <button ref="prevEl" class="swiper-nav-button hidden lg:block left-0 top-1/2">
        <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-lg"/>
      </button>

      <button ref="nextEl" class="swiper-nav-button hidden lg:block right-0 top-1/2">
        <FontAwesomeIcon :icon="faChevronCircleRight" class="text-lg"/>
      </button>
      
      <Swiper   
        :modules="[Navigation]"
        :slides-per-view="slidesPerView"
        :navigation="{ prevEl, nextEl }"
        :autoHeight="false"
        pagination
        :loop="true"
      >
        <SwiperSlide v-for="(product, index) in compSwiperOptions" :key="product.slug" class="!h-auto">
          <div class="h-full flex flex-col">          <!-- this now fills the Swiper height -->
            <div v-if="product" class="h-full flex flex-col px-3 2xl:px-8 lg:px-8">
              <ProductRenderEcom v-if="layout.retina.type === 'b2b'" :buttonStyleHover="layout?.buttonBasket?.buttonStyleHover" :buttonStyle="layout?.buttonBasket?.buttonStyle":product="product" :hideLogin="true"  :hasInBasket="get(layout, ['family_page', 'productInBasket', 'list', product.id], [])" />
              <ProductRender v-else :product="product" :productHasPortfolio="[]" />
            </div>
          </div>
        </SwiperSlide>
      </Swiper>
    </div>

  </div>
</template>

<style scoped>
.swiper-nav-button {
  @apply absolute top-1/2 transform -translate-y-1/2 z-10;
}

.swiper-nav-button svg {
  @apply text-gray-700 w-4 h-4;
}

.swiper-wrapper {
  align-items: stretch !important;
}

.swiper-slide {
  display: flex !important;
  flex-direction: column !important;
}
</style>