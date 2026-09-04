<script setup lang="ts">
import { computed, ref } from "vue"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

const props = withDefaults(defineProps<{
  products: any[]
  perRow?: {
    desktop?: number
    tablet?: number
    mobile?: number
  }
  screenType?: "mobile" | "tablet" | "desktop"
}>(), {
  screenType: "desktop",
})

const columns = computed(() => {
  return {
    desktop: props.perRow?.desktop ?? 5,
    tablet: props.perRow?.tablet ?? 4,
    mobile: props.perRow?.mobile ?? 2,
  }[props.screenType] ?? 5
})

const allProducts = computed(() => props.products ?? [])

const prevEl = ref(null)
const nextEl = ref(null)
const idxSlideLoading = ref<number | null>(null)

const getImage = (product: any) => {
  return product?.web_images?.main?.gallery ?? product?.web_images?.main?.original ?? null
}
</script>

<template>
  <div class="relative mx-auto w-full max-w-[1400px] px-4 py-6 2xl:max-w-[1700px] 2xl:px-10 2xl:py-8">
    <button ref="prevEl" class="swiper-nav-button hidden lg:block left-0">
      <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-lg" />
    </button>

    <button ref="nextEl" class="swiper-nav-button hidden lg:block right-0">
      <FontAwesomeIcon :icon="faChevronCircleRight" class="text-lg" />
    </button>

    <Swiper
      :modules="[Navigation, Pagination]"
      :slides-per-view="columns"
      :space-between="24"
      :navigation="{ prevEl, nextEl }"
      :pagination="{ clickable: true, dynamicBullets: true }"
      :loop="false"
      :autoHeight="false"
    >
      <SwiperSlide v-for="(product, productIndex) in allProducts" :key="product.id ?? product.slug" class="!h-auto">
        <component
          :is="product.url ? LinkIris : 'div'"
          :href="product.url"
          type="internal"
          class="group relative flex h-full flex-col items-center text-center"
          @start="() => idxSlideLoading = productIndex"
          @finish="() => idxSlideLoading = null"
        >
          <div class="relative w-[180px] h-[180px] shrink-0 overflow-hidden bg-white 2xl:w-[240px] 2xl:h-[240px]">
            <Image
              v-if="getImage(product)"
              :src="getImage(product)"
              :alt="product.name"
              class="absolute inset-0 w-full h-full flex items-center justify-center"
              :style="{ width: '100%', height: '100%', objectFit: 'contain', objectPosition: 'center' }"
            />
            <FontAwesomeIcon
              v-else
              icon="fal fa-image"
              class="opacity-20 text-3xl absolute top-1/2 left-1/2 -translate-y-1/2 -translate-x-1/2 2xl:text-5xl"
            />
          </div>

          <div class="mt-3 w-[180px] text-sm leading-tight text-[#1d2d44] group-hover:text-gray-500 2xl:mt-4 2xl:w-[240px] 2xl:text-base">
            {{ product.name }}
          </div>

          <div
            v-if="idxSlideLoading === productIndex"
            class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-2xl"
          >
            <LoadingIcon />
          </div>
        </component>
      </SwiperSlide>
    </Swiper>
  </div>
</template>

<style scoped>
.swiper-nav-button {
  @apply absolute top-1/2 transform -translate-y-1/2 z-10;
}

.swiper-nav-button svg {
  @apply text-gray-700 w-4 h-4;
}

:deep(.swiper-slide) {
  display: flex !important;
  flex-direction: column !important;
  align-items: center;
}

:deep(.swiper-pagination) {
  position: relative;
  bottom: auto;
  margin-top: 1.5rem;
  display: none;
}

:deep(.swiper-pagination.swiper-pagination-lock) {
  display: none;
}

:deep(.swiper-pagination-bullet) {
  background-color: #cbd5e1;
  opacity: 1;
}

:deep(.swiper-pagination-bullet-active) {
  background-color: #1d2d44;
}
</style>
