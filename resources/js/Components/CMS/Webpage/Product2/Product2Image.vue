<script setup lang="ts">
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'
import { Navigation, Thumbs } from 'swiper/modules'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleLeft, faChevronCircleRight } from '@fal'
import { faVideo } from '@fas'

import Image from '@/Components/Image.vue'
import Dialog from 'primevue/dialog'

const props = defineProps<{
  images: { source: string; thumbnail: string }[]
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

const closeModal = () => {
  showModal.value = false
  showVideoModal.value = false
}

const onPrevNavigation = () => {
  selectedIndex.value =
    (selectedIndex.value - 1 + props.images.length) % props.images.length
}

const onNextNavigation = () => {
  selectedIndex.value =
    (selectedIndex.value + 1) % props.images.length
}

/* ---------------- LIFECYCLE ---------------- */
const handleKeydown = (e: KeyboardEvent) => {
  if (!showModal.value) return

  if (e.key === 'Escape') closeModal()
  if (!showVideoModal.value) {
    if (e.key === 'ArrowLeft') onPrevNavigation()
    if (e.key === 'ArrowRight') onNextNavigation()
  }
}

onMounted(async () => {
  await nextTick()

  mainNavigation.value.prevEl = mainPrevEl.value
  mainNavigation.value.nextEl = mainNextEl.value

  thumbNavigation.value.prevEl = thumbPrevEl.value
  thumbNavigation.value.nextEl = thumbNextEl.value

  window.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
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
          class="bg-gray-100 w-full aspect-square overflow-hidden rounded-lg cursor-pointer"
          @click="openImageModal(index)"
        >
          <Image
            :src="image.source"
            :alt="`Image ${index + 1}`"
            class="w-full h-full object-cover"
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
            :alt="`Thumbnail ${index + 1}`"
            class="w-full h-full object-contain"
          />
        </div>
      </SwiperSlide>
    </Swiper>

    <!-- ================= MODAL ================= -->
    <Dialog
      v-model:visible="showModal"
      modal
      dismissable-mask
      :closable="false"
      class="w-full max-w-4xl !bg-transparent !shadow-none"
    >
      <div class="relative w-full flex justify-center items-center">
        <div v-if="!showVideoModal" class="w-full max-h-[80vh]">
          <Image
            :src="props.images[selectedIndex]?.source"
            :imageCover="true"
            :style="{ objectFit: 'contain' }"
          />
        </div>

        <div v-else class="w-full aspect-video">
          <iframe class="w-full h-full" :src="props.video" allowfullscreen />
        </div>

        <button
          v-if="!showVideoModal"
          class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl"
          @click="onPrevNavigation"
        >
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>

        <button
          v-if="!showVideoModal"
          class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl"
          @click="onNextNavigation"
        >
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </button>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.p-dialog-mask) {
  background: rgba(0, 0, 0, 0.9);
}
</style>
