<script setup lang="ts">
import { ref } from 'vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';
import 'swiper/css/free-mode';
import 'swiper/css/navigation';
import 'swiper/css/thumbs';
import { FreeMode, Navigation, Thumbs } from 'swiper/modules';

defineProps<{
    images: Array<String>
}>()

const thumbsSwiper = ref(null);
const setThumbsSwiper = (swiper) => {
    thumbsSwiper.value = swiper;
};

</script>

<template>
    <swiper :loop="true" :spaceBetween="10" :navigation="true" :thumbs="{ swiper: thumbsSwiper }"
        :modules="[FreeMode, Navigation, Thumbs]" class="mySwiper2">
        <swiper-slide v-for="image in images"><img :src="image" /></swiper-slide>
    </swiper>
    <swiper @swiper="setThumbsSwiper" :loop="true" :spaceBetween="10" :slidesPerView="4" :freeMode="true"
        :watchSlidesProgress="true" :modules="[FreeMode, Navigation, Thumbs]" class="mySwiper">
        <swiper-slide v-for="image in images"><img :src="image" /></swiper-slide>
    </swiper>
</template>



<style scoped>
body {
    @apply bg-black text-black;
}

.mySwiper2 {
    @apply h-4/5 w-full mx-auto;
}

.mySwiper {
    @apply h-1/5 box-border p-2 bg-gray-100;
}

.mySwiper .swiper-slide {
    @apply w-1/4 h-full opacity-40;
}

.mySwiper .swiper-slide-thumb-active {
    @apply opacity-100;
}

.swiper-slide img {
    @apply block w-full h-full object-cover;
}
</style>
