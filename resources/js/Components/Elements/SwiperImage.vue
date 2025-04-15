<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import { EffectCoverflow, Navigation, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/effect-coverflow'
import EmptyState from '../Utils/EmptyState.vue';
import { faImage } from '@far';

const props = defineProps<{
  images: { image: string }[]
}>()
</script>

<template>
  <div class="bg-gray-200 py-4 flex justify-center w-full">
    <Swiper v-if="props.images?.length > 0 && props.images" :modules="[EffectCoverflow, Navigation, Pagination]"
      effect="coverflow" grab-cursor="true" centered-slides="true" slides-per-view="auto" loop="true" :coverflowEffect="{
        rotate: 0,
        stretch: 0,
        depth: 200,
        modifier: 2,
        slideShadows: false,
      }" class="w-fit h-fit max-w-5xl">
      <SwiperSlide v-for="(img, index) in props.images" :key="index"
        class="!w-[300px] flex justify-center items-center">
        <div class="w-full h-[400px] rounded-2xl overflow-hidden shadow-xl">
          <img :src="img.image" :alt="'Image ' + (index + 1)" class="w-full h-full object-cover" />
        </div>
      </SwiperSlide>
    </Swiper>
    <div v-else class="w-full h-[400px]">
      <EmptyState :data="{
        title: 'No Image Found',
        description: 'Please upload an image',
      }" :isNoIcon="true" />
    </div>

  </div>
</template>
