<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'
import { Navigation, Thumbs } from 'swiper/modules'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleLeft, faChevronCircleRight, faSearchPlus } from '@fal'
import { faVideo } from '@fas'

import Image from "@common/Components/Image.vue"
import ProductImageViewerDialog from '@/Components/Product/ProductImageViewerDialog.vue'

const props = defineProps<{
  images: { source: any; thumbnail?: any; zoom?: any; alt?: string }[]
  video?: string
}>()

/* ---------------- SWIPER INSTANCE ---------------- */
const mainSwiper = ref<any>(null)
const thumbsSwiper = ref<any>(null)

/* ---------------- NAV ELEMENTS ---------------- */
const mainPrevEl = ref<HTMLElement | null>(null)
const mainNextEl = ref<HTMLElement | null>(null)
const thumbPrevEl = ref<HTMLElement | null>(null)
const thumbNextEl = ref<HTMLElement | null>(null)

/* ---------------- REACTIVE NAV ---------------- */
const mainNavigation = ref({
  prevEl: null as HTMLElement | null,
  nextEl: null as HTMLElement | null,
})

const thumbNavigation = ref({
  prevEl: null as HTMLElement | null,
  nextEl: null as HTMLElement | null,
})

/* ---------------- MODAL ---------------- */
const showModal = ref(false)
const showVideoModal = ref(false)
const selectedIndex = ref(0)

/* ---------------- ACTIONS ---------------- */
const openImageModal = (index: number) => {
  selectedIndex.value = index
  showVideoModal.value = false
  showModal.value = true
}

const openVideoModal = () => {
  showVideoModal.value = true
  showModal.value = true
}

/* ---------------- LIFECYCLE ---------------- */
onMounted(async () => {
  await nextTick()

  mainNavigation.value.prevEl = mainPrevEl.value
  mainNavigation.value.nextEl = mainNextEl.value

  thumbNavigation.value.prevEl = thumbPrevEl.value
  thumbNavigation.value.nextEl = thumbNextEl.value
})
</script>

<template>
  <div class="w-full flex flex-col items-center relative isolate">
    <!-- ================= MAIN SWIPER ================= -->
    <Swiper
      v-if="props.images.length"
      :modules="[Navigation, Thumbs]"
      :slides-per-view="1"
      :loop="props.images.length > 1"
      :navigation="mainNavigation"
      :thumbs="{ swiper: thumbsSwiper }"
      class="aspect-square w-full rounded-lg mb-4"
      @swiper="swiper => (mainSwiper = swiper)"
    >
      <!-- NAV -->
      <div class="absolute inset-0 pointer-events-none z-50">
        <div
          ref="mainPrevEl"
          class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-60 hover:opacity-100 pointer-events-auto"
        >
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </div>

        <div
          ref="mainNextEl"
          class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-60 hover:opacity-100 pointer-events-auto"
        >
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </div>
      </div>

      <!-- IMAGE SLIDES -->
      <SwiperSlide
        v-for="(image, index) in props.images"
        :key="index"
        class="flex justify-center items-center"
      >
        <div
          class="relative w-full aspect-square overflow-hidden rounded-lg cursor-zoom-in"
          @click="openImageModal(index)"
        >
          <Image
            :src="image.source"
            :alt="image.alt"
            class="w-full h-full flex items-center justify-center"
            :style="{ width: 'auto', height: 'auto', maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }"
          />
        </div>
      </SwiperSlide>

      <!-- VIDEO -->
      <SwiperSlide v-if="props.video">
        <div
          class="w-full aspect-square flex items-center justify-center bg-black rounded-lg cursor-pointer"
          @click="openVideoModal"
        >
          <FontAwesomeIcon
            :icon="faVideo"
            class="text-5xl text-white/80 absolute"
          />
          <iframe
            class="w-full h-full opacity-50 pointer-events-none"
            :src="props.video"
            allowfullscreen
          />
        </div>
      </SwiperSlide>
    </Swiper>

    <!-- ================= THUMBS ================= -->
    <Swiper
      v-if="props.images.length"
      :modules="[Thumbs, Navigation]"
      watch-slides-progress
      :loop="props.images.length > 1"
      :space-between="12"
      :navigation="thumbNavigation"
      :breakpoints="{ 0: { slidesPerView: 2.5 } }"
      class="w-full relative"
      @swiper="swiper => (thumbsSwiper = swiper)"
    >
      <div class="absolute inset-0 pointer-events-none z-50">
        <div
          ref="thumbPrevEl"
          class="absolute left-0 top-1/2 -translate-y-1/2 text-2xl pointer-events-auto"
        >
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </div>

        <div
          ref="thumbNextEl"
          class="absolute right-0 top-1/2 -translate-y-1/2 text-2xl pointer-events-auto"
        >
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </div>
      </div>

      <SwiperSlide
        v-for="(image, index) in props.images"
        :key="index"
        class="cursor-pointer border rounded"
      >
        <div class="aspect-square bg-gray-100">
          <Image
            :src="image.source"
            :alt="image.alt || `Thumbnail ${index + 1}`"
            class="w-full h-full flex items-center justify-center"
            :style="{ width: 'auto', height: 'auto', maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }"
          />
        </div>
      </SwiperSlide>
    </Swiper>

    <ProductImageViewerDialog v-model:visible="showModal" v-model:index="selectedIndex" :images="props.images"
      :video="props.video" :show-video="showVideoModal" />
  </div>
</template>

