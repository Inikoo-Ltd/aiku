<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'
import { Navigation, Autoplay, Thumbs } from 'swiper/modules'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleLeft, faChevronCircleRight, faTimesCircle } from '@fal'
import { ulid } from 'ulid'
import Image from '@/Components/Image.vue'

const props = defineProps<{
  images: { source: string; thumbnail: string }[]
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

onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value
  }
})
</script>

<template>
  <div class="w-full flex flex-col items-center relative">
    <!-- Shared Navigation Buttons -->
    <div class="absolute inset-0 pointer-events-none z-50">
      <div ref="prevEl"
        class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl text-white cursor-pointer pointer-events-auto">
        <FontAwesomeIcon :icon="faChevronCircleLeft" />
      </div>
      <div ref="nextEl"
        class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl text-white cursor-pointer pointer-events-auto">
        <FontAwesomeIcon :icon="faChevronCircleRight" />
      </div>
    </div>

    <!-- Main Swiper -->
    <div class="relative w-full">
      <Swiper :key="keySwiperMain" :slides-per-view="1" :loop="true" :autoplay="false" :navigation="navigation"
        :modules="[Navigation, Autoplay, Thumbs]" :thumbs="{ swiper: thumbsSwiper }"
        class="aspect-square w-full rounded-lg mb-4">
        <SwiperSlide v-for="(image, index) in props.images" :key="index" class="flex justify-center items-center">
          <div
            class="bg-gray-100 w-full aspect-square flex items-center justify-center overflow-hidden rounded-lg cursor-pointer"
            @click="openImageModal(index)">
            <Image :src="image.source" :alt="`Image ${index + 1}`" class="w-full h-full object-cover" />
          </div>
        </SwiperSlide>
      </Swiper>
    </div>

    <!-- Thumbnail Swiper -->
    <!-- Thumbnail Swiper -->
    <Swiper :key="keySwiperThumb" :space-between="8" watch-slides-progress :modules="[Thumbs]"
      @swiper="(swiper) => (thumbsSwiper = swiper)" :breakpoints="{
        0: {
          slidesPerView: 3
        },
        640: {
          slidesPerView: 6
        }
      }" class="w-full">
      <SwiperSlide v-for="(image, index) in props.images" :key="index"
        class="cursor-pointer rounded overflow-hidden border border-gray-300">
        <slot name="image-thumbnail" :image="image">
          <div class="aspect-square w-full">
            <Image :src="image.source" :alt="`Thumbnail ${index + 1}`" class="w-full h-full object-cover" />
          </div>
        </slot>
      </SwiperSlide>
    </Swiper>



    <!-- Modal Swiper -->
    <div v-if="showModal" class="fixed inset-0 bg-black/90 z-50 flex items-center justify-center"
      @click.self="closeImageModal">
      <div class="relative w-full max-w-5xl p-4">
        <!-- Close Button -->
        <button class="absolute top-4 right-4 text-white text-3xl z-50" @click="closeImageModal">
          <FontAwesomeIcon :icon="faTimesCircle" />
        </button>

        <!-- Swiper in Modal -->
        <Swiper :initial-slide="selectedIndex" :slides-per-view="1" :loop="true" :navigation="navigation"
          :modules="[Navigation]" class="w-full">
          <SwiperSlide v-for="(image, index) in props.images" :key="index" class="flex items-center justify-center">
            <Image :src="image.source" :alt="`Zoomed Image ${index + 1}`"
              class="w-full max-h-[80vh] object-contain rounded-lg shadow-lg" />
          </SwiperSlide>
        </Swiper>
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
