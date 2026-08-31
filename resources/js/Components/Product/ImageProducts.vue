<script setup lang="ts">
import { ref, nextTick, onMounted, computed, defineAsyncComponent } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'
import { Navigation, Autoplay, Thumbs } from 'swiper/modules'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faChevronCircleLeft,
  faChevronCircleRight,
  faSearchPlus,
} from '@fal'
import { ulid } from 'ulid'
import Image from '../../Common/Components/Image.vue'
import ProductImageViewerDialog from './ProductImageViewerDialog.vue'
import { buildProductMediaItems } from './buildProductMediaItems'

const ProductSoundButton = defineAsyncComponent(() => import('@/Iris/Components/ProductSoundButton.vue'))

const props = defineProps<{
  images: { source: object; thumbnail?: object; zoom?: object; alt: string }[]
  video?: string
  audio?: string
  breakpoints?: {
    [key: number]: { slidesPerView: number }
  }
}>()

const keySwiperMain = ref(ulid())
const keySwiperThumb = ref(ulid())

const thumbsSwiper = ref<any>(null)
const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const navigation = ref({ prevEl: null, nextEl: null })

const isThumbBeginning = ref(true)
const isThumbEnd = ref(false)
const isThumbLocked = ref(true)

function onThumbSwiper(swiper: any) {
  thumbsSwiper.value = swiper
  syncThumbNavigationState(swiper)
}

function syncThumbNavigationState(swiper: any) {
  isThumbBeginning.value = swiper.isBeginning
  isThumbEnd.value = swiper.isEnd
  isThumbLocked.value = swiper.isLocked ?? (swiper.isBeginning && swiper.isEnd)
}

function slideThumbPrev() {
  thumbsSwiper.value?.slidePrev()
}

function slideThumbNext() {
  thumbsSwiper.value?.slideNext()
}

const showModal = ref(false)
const selectedIndex = ref(0)
const showVideoModal = ref(false)

function openImageModal(index: number) {
  selectedIndex.value = index
  showVideoModal.value = false
  showModal.value = true
}

function openVideoModal() {
  showVideoModal.value = true
  showModal.value = true
}

onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value
  }
})

const mediaItems = computed(() => buildProductMediaItems(props.images, props.video))

const enableLoop = computed(() => mediaItems.value.length > 1)
</script>

<template>
  <div class="w-full flex flex-col items-center relative isolate">
    <!-- Main Swiper -->
    <div class="relative w-full mb-4">
      <Swiper :key="keySwiperMain" :slides-per-view="1"   :loop="enableLoop" :autoplay="false" :navigation="navigation"
        :modules="[Navigation, Autoplay, Thumbs]" :thumbs="{ swiper: thumbsSwiper }"
        class="aspect-square w-full rounded-lg">
        <!-- Shared Navigation Buttons -->
        <div class="absolute inset-0 pointer-events-none z-50">
          <div ref="prevEl"
            class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto">
            <FontAwesomeIcon fixed-width :icon="faChevronCircleLeft" />
          </div>
          <div ref="nextEl"
            class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto">
            <FontAwesomeIcon fixed-width :icon="faChevronCircleRight" />
          </div>
        </div>

        <!-- Media Slides -->
        <SwiperSlide v-for="item in mediaItems" :key="item.type === 'video' ? 'video' : `img-${item.imageIndex}`"
          class="flex justify-center items-center">
          <div v-if="item.type === 'image'"
            class="relative w-full aspect-square flex items-center justify-center overflow-hidden rounded-lg cursor-zoom-in"
            @click="openImageModal(item.imageIndex)">
            <Image :src="item.image.source" :alt="item.image.alt" class="w-full h-full flex items-center justify-center"
              :style="{ width: '100%', height: '100%', maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }" />
          </div>

          <div v-else
            class="w-full aspect-square flex items-center justify-center  rounded-lg overflow-hidden cursor-pointer"
            @click="openVideoModal">
            <div class="relative w-full h-full flex items-center justify-center">
              <iframe class="w-full h-full  pointer-events-none" :src="props.video" frameborder="0"
                allow="autoplay; fullscreen" allowfullscreen></iframe>
            </div>
          </div>
        </SwiperSlide>
      </Swiper>

      <!-- Sound sample, kept outside <Swiper> so it is not picked up as a slide -->
      <ProductSoundButton v-if="props.audio" :src="props.audio" placement="bottom-left" />
    </div>

    <!-- Thumbnail Swiper -->
    <div class="relative w-full" :class="isThumbLocked ? '' : 'px-8'">
      <button v-show="!isThumbLocked" type="button" aria-label="Previous thumbnails" :disabled="isThumbBeginning"
        class="absolute left-0 top-1/2 -translate-y-1/2 z-20 text-xs opacity-60 hover:opacity-100 disabled:opacity-20 disabled:cursor-default"
        @click="slideThumbPrev">
        <FontAwesomeIcon fixed-width :icon="faChevronCircleLeft" />
      </button>
      <button v-show="!isThumbLocked" type="button" aria-label="Next thumbnails" :disabled="isThumbEnd"
        class="absolute right-0 top-1/2 -translate-y-1/2 z-20 text-xs opacity-60 hover:opacity-100 disabled:opacity-20 disabled:cursor-default"
        @click="slideThumbNext">
        <FontAwesomeIcon fixed-width :icon="faChevronCircleRight" />
      </button>

      <Swiper :key="keySwiperThumb" :space-between="8" watch-slides-progress :modules="[Thumbs]"
        @swiper="onThumbSwiper" @slide-change="syncThumbNavigationState" @resize="syncThumbNavigationState"
        @breakpoint="syncThumbNavigationState" @lock="syncThumbNavigationState" @unlock="syncThumbNavigationState"
        @observer-update="syncThumbNavigationState"
        :breakpoints="breakpoints ?? { 0: { slidesPerView: 3 }, 640: { slidesPerView: 6 } }" class="w-full">
        <SwiperSlide v-for="item in mediaItems"
          :key="item.type === 'video' ? 'thumb-video' : `thumb-${item.imageIndex}`"
          class="cursor-pointer rounded overflow-hidden border border-gray-300">
          <div v-if="item.type === 'image'" class="aspect-square w-full">
            <Image :src="item.image.thumbnail || item.image.source"
              :alt="item.image.alt || `Thumbnail ${item.imageIndex + 1}`"
              class="w-full h-full flex items-center justify-center"
              :style="{ width: '100%', height: '100%', maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }" />
          </div>

          <div v-else class="aspect-square w-full flex items-center justify-center bg-gray-200 relative"
            @click="openVideoModal">
            <div class="relative w-full h-full">
              <iframe class="w-full h-full rounded-lg" :src="props.video" frameborder="0" allow="autoplay; fullscreen"
                allowfullscreen></iframe>

              <div class="absolute inset-0 z-10"></div>
            </div>
          </div>
        </SwiperSlide>
      </Swiper>
    </div>

    <ProductImageViewerDialog v-model:visible="showModal" v-model:index="selectedIndex" :images="props.images"
      :video="props.video" :show-video="showVideoModal" />
  </div>
</template>

<style scoped lang="scss">
.swiper {
  touch-action: pan-y;
}

button {
  outline: none;
}
</style>
