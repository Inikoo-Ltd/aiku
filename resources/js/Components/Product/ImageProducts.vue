<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'
import { Navigation, Autoplay, Thumbs } from 'swiper/modules'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faChevronCircleLeft,
  faChevronCircleRight,
  faTimesCircle
} from '@fal'
import { ulid } from 'ulid'
import Image from '@/Components/Image.vue'
import { faVideo } from '@fas'

const props = defineProps<{
  images: { source: string; thumbnail: string }[]
  video?: string
  breakpoints?: {
    [key: number]: {
      slidesPerView: number
    }
  }
}>()

const keySwiperMain = ref(ulid())
const keySwiperThumb = ref(ulid())

const thumbsSwiper = ref(null)
const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const navigation = ref({ prevEl: null, nextEl: null })

const showModal = ref(false)
const selectedIndex = ref(0)

function openImageModal(index: number) {
  selectedIndex.value = index
  showModal.value = true
}

function closeImageModal() {
  showModal.value = false
}

const onPrevNavigation = () => {
  selectedIndex.value = (selectedIndex.value - 1 + props.images.length) % props.images.length
}

const onRightNavigation = () => {
  selectedIndex.value = (selectedIndex.value + 1) % props.images.length
}

onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value
  }

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
      closeImageModal()
    }
    if (showModal.value) {
      if (e.key === 'ArrowLeft') {
        onPrevNavigation()
      }
      if (e.key === 'ArrowRight') {
        onRightNavigation()
      }
    }
  })
})

console.log(props.video)
</script>

<template>

  <div class="w-full flex flex-col items-center relative">
    <!-- Shared Navigation Buttons -->
    <div class="absolute inset-0 pointer-events-none z-50">
      <div ref="prevEl"
        class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl text-gray-800 cursor-pointer pointer-events-auto">
        <FontAwesomeIcon :icon="faChevronCircleLeft" />
      </div>
      <div ref="nextEl"
        class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl text-gray-800 cursor-pointer pointer-events-auto">
        <FontAwesomeIcon :icon="faChevronCircleRight" />
      </div>
    </div>

    <!-- Main Swiper -->
    <Swiper :key="keySwiperMain" :slides-per-view="1" :loop="true" :autoplay="false" :navigation="navigation"
      :modules="[Navigation, Autoplay, Thumbs]" :thumbs="{ swiper: thumbsSwiper }"
      class="aspect-square w-full rounded-lg mb-4">
      <!-- Images -->
      <SwiperSlide v-for="(image, index) in props.images" :key="`img-${index}`"
        class="flex justify-center items-center">
        <div
          class="bg-gray-100 w-full aspect-square flex items-center justify-center overflow-hidden rounded-lg cursor-pointer"
          @click="openImageModal(index)">
          <Image :src="image.source" :alt="`Image ${index + 1}`" class="w-full h-full object-cover" />
        </div>
      </SwiperSlide>

      <!-- Video Slide -->
      <SwiperSlide v-if="props.video" key="video">
        <div class="w-full aspect-square flex items-center justify-center bg-black rounded-lg overflow-hidden">
          <iframe class="w-full h-full" :src="props.video" frameborder="0" allow="autoplay; fullscreen"
            allowfullscreen></iframe>
        </div>
      </SwiperSlide>
    </Swiper>


    <!-- Thumbnail Swiper -->
    <Swiper :key="keySwiperThumb" :space-between="8" watch-slides-progress :modules="[Thumbs]"
      @swiper="(swiper) => (thumbsSwiper = swiper)"
      :breakpoints="breakpoints ?? { 0: { slidesPerView: 3 }, 640: { slidesPerView: 6 } }" class="w-full">
      <!-- Image thumbnails -->
      <SwiperSlide v-for="(image, index) in props.images" :key="`thumb-${index}`"
        class="cursor-pointer rounded overflow-hidden border border-gray-300">
        <div class="aspect-square w-full">
          <Image :src="image.thumbnail || image.source" :alt="`Thumbnail ${index + 1}`"
            class="w-full h-full object-cover" />
        </div>
      </SwiperSlide>

      <!-- Video thumbnail -->
      <SwiperSlide v-if="props.video" key="thumb-video"
        class="cursor-pointer rounded overflow-hidden border border-gray-300">
        <div class="aspect-square w-full flex items-center justify-center bg-gray-200 relative">
          <!-- Thumbnail placeholder -->
          <FontAwesomeIcon :icon=faVideo class="text-3xl text-gray-600" />
          <span class="absolute bottom-2 text-xs text-gray-700 bg-white/70 px-2 py-0.5 rounded">
            Video
          </span>
        </div>
      </SwiperSlide>

    </Swiper>


    <!-- Modal Viewer -->
    <div v-if="showModal" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center"
      @click.self="closeImageModal">
      <div class="relative w-full max-w-5xl px-4 py-6">
        <!-- Close Button -->
        <button class="absolute top-0 right-4 text-white text-3xl z-50" @click="closeImageModal"
          aria-label="Close image viewer">
          <FontAwesomeIcon :icon="faTimesCircle" />
        </button>

        <!-- Manual Navigation Buttons -->
        <button class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl z-40" @click="onPrevNavigation">
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>
        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl z-40" @click="onRightNavigation">
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </button>

        <!-- Image Display -->
        <div class="block w-full h-[80vh] mb-1 rounded">
          <Image :src="props.images[selectedIndex]?.source" :alt="`Image ${selectedIndex + 1}`"
            :style="{ objectFit: 'contain' }" :imageCover="true" />
        </div>
      </div>
    </div>
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
