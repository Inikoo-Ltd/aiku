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
} from '@fal'
import { faVideo } from '@fas'
import { ulid } from 'ulid'
import Image from '@/Components/Image.vue'
import Dialog from 'primevue/dialog'

const props = defineProps<{
  images: { source: string; thumbnail: string }[]
  video?: string
  breakpoints?: {
    [key: number]: { slidesPerView: number }
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

function closeImageModal() {
  showModal.value = false
  showVideoModal.value = false
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
    if (e.key === 'Escape' || e.key === 'Esc') closeImageModal()
    if (showModal.value && !showVideoModal.value) {
      if (e.key === 'ArrowLeft') onPrevNavigation()
      if (e.key === 'ArrowRight') onRightNavigation()
    }
  })
})
</script>

<template>
  <div class="w-full flex flex-col items-center relative isolate">
    <!-- Main Swiper -->
    <Swiper
      :key="keySwiperMain"
      :slides-per-view="1"
      :loop="true"
      :autoplay="false"
      :navigation="navigation"
      :modules="[Navigation, Autoplay, Thumbs]"
      :thumbs="{ swiper: thumbsSwiper }"
      class="aspect-square w-full rounded-lg mb-4"
    >
      <!-- Shared Navigation Buttons -->
      <div class="absolute inset-0 pointer-events-none z-50">
        <div
          ref="prevEl"
          class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto"
        >
          <FontAwesomeIcon fixed-width :icon="faChevronCircleLeft" />
        </div>
        <div
          ref="nextEl"
          class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto"
        >
          <FontAwesomeIcon fixed-width :icon="faChevronCircleRight" />
        </div>
      </div>

      <!-- Image Slides -->
      <SwiperSlide
        v-for="(image, index) in props.images"
        :key="`img-${index}`"
        class="flex justify-center items-center"
      >
        <div
          class="bg-gray-100 w-full aspect-square flex items-center justify-center overflow-hidden rounded-lg cursor-pointer"
          @click="openImageModal(index)"
        >
          <Image
            :src="image.source"
            :alt="`Image ${index + 1}`"
            class="w-full h-full object-cover"
          />
        </div>
      </SwiperSlide>

      <!-- Video Slide -->
      <SwiperSlide v-if="props.video" key="video">
        <div
          class="w-full aspect-square flex items-center justify-center bg-black rounded-lg overflow-hidden cursor-pointer"
          @click="openVideoModal"
        >
          <div class="relative w-full h-full flex items-center justify-center">
            <FontAwesomeIcon :icon="faVideo" class="text-5xl text-white/80 absolute" />
            <iframe
              class="w-full h-full opacity-50 pointer-events-none"
              :src="props.video"
              frameborder="0"
              allow="autoplay; fullscreen"
              allowfullscreen
            ></iframe>
          </div>
        </div>
      </SwiperSlide>
    </Swiper>

    <!-- Thumbnail Swiper -->
    <Swiper
      :key="keySwiperThumb"
      :space-between="8"
      watch-slides-progress
      :modules="[Thumbs]"
      @swiper="(swiper) => (thumbsSwiper = swiper)"
      :breakpoints="breakpoints ?? { 0: { slidesPerView: 3 }, 640: { slidesPerView: 6 } }"
      class="w-full"
    >
      <SwiperSlide
        v-for="(image, index) in props.images"
        :key="`thumb-${index}`"
        class="cursor-pointer rounded overflow-hidden border border-gray-300"
      >
        <div class="aspect-square w-full">
          <Image :src="image.thumbnail" :alt="`Thumbnail ${index + 1}`" class="w-full h-full object-cover" />
        </div>
      </SwiperSlide>

      <!-- Video thumbnail -->
      <SwiperSlide
        v-if="props.video"
        key="thumb-video"
        class="cursor-pointer rounded overflow-hidden border border-gray-300"
        @click="openVideoModal"
      >
        <div class="aspect-square w-full flex items-center justify-center bg-gray-200 relative">
          <FontAwesomeIcon :icon="faVideo" class="text-3xl text-gray-600" />
          <span class="absolute bottom-2 text-xs text-gray-700 bg-white/70 px-2 py-0.5 rounded">Video</span>
        </div>
      </SwiperSlide>
    </Swiper>

    <!-- PrimeVue Dialog (Replaces Custom Modal) -->
    <Dialog
      v-model:visible="showModal"
      modal
      dismissable-mask
      :closable="false"
      class="w-full max-w-3xl !bg-transparent !shadow-none !border-0 !border-transparent"
    >
      <div class="relative w-full flex flex-col items-center justify-center">
        <!-- Close Button -->
        <!-- <button
          class="absolute top-0 right-4 text-white text-3xl z-50"
          @click="closeImageModal"
          aria-label="Close image viewer"
        >
          <FontAwesomeIcon :icon="faTimesCircle" />
        </button> -->

        <!-- Image Viewer -->
        <div v-if="!showVideoModal" class="block w-full h-auto min-h-[400px] max-h-[80vh] mb-1 rounded">
          <Image
            :src="props.images[selectedIndex]?.source"
            :alt="`Image ${selectedIndex + 1}`"
            :style="{ objectFit: 'contain' }"
            :imageCover="true"
          />
        </div>

        <!-- Video Viewer -->
        <div v-else class="w-full aspect-video flex items-center justify-center">
          <iframe
            class="w-full h-full rounded-lg"
            :src="props.video"
            frameborder="0"
            allow="autoplay; fullscreen"
            allowfullscreen
          ></iframe>
        </div>

        <!-- Navigation (for image only) -->
        <template v-if="!showVideoModal">
          <button
            class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl z-40"
            @click="onPrevNavigation"
          >
            <FontAwesomeIcon :icon="faChevronCircleLeft" />
          </button>
          <button
            class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl z-40"
            @click="onRightNavigation"
          >
            <FontAwesomeIcon :icon="faChevronCircleRight" />
          </button>
        </template>
      </div>
    </Dialog>
  </div>
</template>

<style scoped lang="scss">
.swiper {
  touch-action: pan-y;
}

button {
  outline: none;
}

:deep(.p-dialog-mask) {
  background-color: rgba(0, 0, 0, 0.9) !important;
}

:deep(.p-dialog) {
  background: transparent !important;
  box-shadow: none !important;
  border: none !important;
}
</style>
