<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import ProductRender from '@/Components/CMS/Webpage/Products1/Droppshiping/ProductRender.vue'
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from './Blueprint'

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Navigation } from 'swiper/modules'

// Font Awesome
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"
import { trans } from "laravel-vue-i18n"
library.add(faChevronLeft, faChevronRight)


const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  indexBlock?: Number
  screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
  (e: "autoSave"): void
}>()

const layout: any = inject("layout", {})
const bKeys = Blueprint(props.webpageData)?.blueprint?.map(b => b?.key?.join("-")) || []

const slidesPerView = computed(() => {
  const perRow = props.modelValue?.settings?.per_row ?? {}
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
    if (props.modelValue?.settings?.products_data?.type == 'custom') {
        return props.modelValue?.settings?.products_data?.products
    } else if (props.modelValue?.settings?.products_data?.type == 'current-family') {
        return props.modelValue?.settings?.products_data?.current_family?.option || []
    } else if (props.modelValue?.settings?.products_data?.type == 'other-family') {
        return props.modelValue?.settings?.products_data?.other_family?.option || []
    } else {
        return props.modelValue?.settings?.products_data?.top_sellers || []
    }
})
</script>

<template>
  <div id="see-also-1-workshop" class="w-full pb-6" :style="{
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
    ...getStyles(modelValue.container?.properties, screenType),
    width: 'auto'
  }" :dropdown-type="props.modelValue?.settings?.products_data?.type">
    <!-- Title -->
    <div class="px-4 py-6 pb-2">
      <div class="text-3xl font-semibold text-gray-800">
        <EditorV2 v-model="modelValue.title" @focus="() => {
          sendMessageToParent('activeBlock', indexBlock)
        }" @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
          name: webpageData.images_upload_route.name,
          parameters: { modelHasWebBlocks: blockData.id }
        }" />

      </div>
    </div>

    <div v-if="['luigi-trends', 'luigi-recently_ordered', 'luigi-last_seen', 'luigi-item_detail_alternatives'].includes(modelValue.settings.products_data.type)">
        <div class="py-4">
            <div class="h-48 px-12 text-center flex text-lg font-semibold flex-col items-center justify-center  w-full bg-gray-200 border border-gray-300">
                <div>{{ trans("Will not showed in live website") }}</div>
                <div class="text-sm italic text-gray-500 font-normal">{{ trans("Luigi: Top Trending, Luigi: Customer Recently Ordered, Luigi: Recently Viewed, and Luigi: You might also like is no longer supported. Please change to another type.") }}</div>
            </div>
        </div>
        <!-- <RecommendersLuigi1Workshop recommendation_type="trends" :slidesPerView /> -->
    </div>

    <!-- Carousel with custom navigation -->
    <div v-else-if="compSwiperOptions?.length" class="relative px-4 py-6" @click="() => {
      `sendMessageToParent('activeBlock', indexBlock)`
      sendMessageToParent('activeChildBlock', bKeys[0])
    }">
      <!-- Tombol Navigasi Custom -->
      <button ref="prevEl" class="swiper-nav-button left-0">
        <FontAwesomeIcon :icon="['fas', 'chevron-left']" />
      </button>
      <button ref="nextEl" class="swiper-nav-button right-0">
        <FontAwesomeIcon :icon="['fas', 'chevron-right']" />
      </button>

      <!-- Swiper -->
      <Swiper :modules="[Navigation]" :slides-per-view="slidesPerView" :space-between="20"
        :navigation="{ prevEl, nextEl }" pagination>
        <SwiperSlide v-for="(product, index) in  compSwiperOptions" :key="product.slug"
          class="h-full">
          <div class="h-full">
            <div v-if="product" class="h-full flex flex-col">
              <ProductRenderEcom v-if="layout.retina.type === 'b2b'" :product="product" />
              <ProductRender v-else :product="product" :productHasPortfolio="[]" />
            </div>
            <div v-else
              class="h-full text-gray-400 text-sm text-center py-6 p-3 relative rounded-lg shadow-sm bg-white hover:shadow-md transition-all duration-200">
              No Product
            </div>
          </div>
        </SwiperSlide>

      </Swiper>
    </div>
  </div>
</template>

<style scoped>
.swiper-nav-button {
  @apply absolute top-1/2 transform -translate-y-1/2 z-10 bg-white border border-gray-300 rounded-full shadow-md p-2 hover:bg-gray-100 transition-all duration-300;
}

.swiper-nav-button svg {
  @apply text-gray-700 w-4 h-4;
}
</style>
