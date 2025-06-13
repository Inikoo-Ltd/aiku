<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/thumbs'

import { Navigation, Autoplay, Thumbs } from 'swiper/modules'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleLeft, faChevronCircleRight } from '@fal' // pastikan path ini benar

import { ulid } from 'ulid'
import Image from '@/Components/Image.vue'

const props = defineProps<{
  images: string[]
}>()

const keySwiperMain = ref(ulid())
const keySwiperThumb = ref(ulid())

const thumbsSwiper = ref(null)

const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const navigation = ref({ prevEl: null, nextEl: null })

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
    <!-- Main Swiper with Navigation -->
    <div class="relative w-full">
      <Swiper :key="keySwiperMain" :slides-per-view="1" :loop="true" :autoplay="false" :navigation="navigation"
        :modules="[Navigation, Autoplay, Thumbs]" :thumbs="{ swiper: thumbsSwiper }"
        class="aspect-square w-full rounded-lg mb-4">
        <SwiperSlide v-for="(image, index) in images" :key="index" class="flex justify-center items-center">
          <div class="bg-gray-100 w-full aspect-square flex items-center justify-center overflow-hidden rounded-lg">
            <Image :src="image.source" :alt="`Image ${index + 1}`" class="w-full h-full object-cover" />
          </div>
        </SwiperSlide>
      </Swiper>

      <!-- Navigation Buttons -->
      <div ref="prevEl"
        class="absolute left-4 top-1/2 -translate-y-1/2 z-30 text-3xl cursor-pointer text-gray-700 select-none">
        <FontAwesomeIcon :icon="faChevronCircleLeft" />
      </div>
      <div ref="nextEl"
        class="absolute right-4 top-1/2 -translate-y-1/2 z-30 text-3xl cursor-pointer text-gray-700 select-none">
        <FontAwesomeIcon :icon="faChevronCircleRight" />
      </div>
    </div>

    <!-- Thumbnail Swiper -->
    <Swiper :key="keySwiperThumb" :slides-per-view="auto" :space-between="8" watch-slides-progress :modules="[Thumbs]"
      @swiper="(swiper) => (thumbsSwiper = swiper)" :style="{ width: 'fit-content', marginLeft: '0px' }">
      <SwiperSlide v-for="(image, index) in images" :key="index"
        class="cursor-pointer rounded overflow-hidden border border-gray-300 !w-[60px]">
        <slot name="image-thumbnail" :image="image">
          <div class="aspect-square w-full">
            <Image :src="image.thumbnail" :alt="`Thumbnail ${index + 1}`" class="w-full h-full object-cover" />
          </div>
        </slot>

      </SwiperSlide>
    </Swiper>


  </div>
</template>



<style scoped lang="scss">
.swiper {
  touch-action: pan-y;
}

/* Sesuaikan posisi tombol navigasi supaya di luar gambar */
.absolute {
  user-select: none;
}
</style>
