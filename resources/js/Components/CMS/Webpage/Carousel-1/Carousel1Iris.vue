<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/pagination'
import 'swiper/css/navigation'

import { Pagination, Autoplay } from 'swiper/modules'
import { getStyles } from '@/Composables/styles'
import { faImage } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'

const props = defineProps<{
    fieldValue: FieldValue
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: 'autoSave'): void
}>()

const keySwiper = ulid()

const getHref = (item: number) => {
    return item?.link?.href ? true : false
}


</script>

<template>
    <Swiper :key="keySwiper" :slides-per-view="fieldValue.carousel_data.carousel_setting.slidesPerView.desktop"
     :loop="true"
      :autoplay="true"
        :simulate-touch="true" :touch-ratio="1" :pagination="{ clickable: true }" :modules="[Pagination, Autoplay]"
        :spaceBetween="fieldValue.carousel_data.carousel_setting.spaceBetween" class="touch-pan-x"
        :style="getStyles(fieldValue.container?.properties, screenType)" :breakpoints="{
            0: { slidesPerView: fieldValue.carousel_data.carousel_setting.slidesPerView.mobile },
            640: { slidesPerView: fieldValue.carousel_data.carousel_setting.slidesPerView.mobile },
            768: { slidesPerView: fieldValue.carousel_data.carousel_setting.slidesPerView.tablet },
            1024: { slidesPerView: fieldValue.carousel_data.carousel_setting.slidesPerView.desktop }
        }">
        <SwiperSlide v-for="(card, index) in fieldValue.carousel_data.cards" :key="index"
            :style="getStyles(fieldValue.carousel_data.card_container.properties, screenType)"
            class=" flex flex-col">
            <component :is="getHref(card) ? 'a' : 'div'" :href="card?.link?.href" :target="card?.link?.target"  class="flex-1 flex flex-col">
              <div class="flex justify-center">
                <div :style="getStyles(fieldValue?.carousel_data?.card_container?.image_properties, screenType)"
                    :class="[!card.image?.source && 'bg-gray-100 w-full h-36 sm:h-44 md:h-48  flex items-center justify-center overflow-auto', 'overflow-hidden']">
                    <Image v-if="card.image?.source" :src="card.image.source" :alt="card.image.alt || `image ${index}`"
                        :imageCover="true" :style="getStyles(card.image.properties, screenType)" /> 
                     <font-awesome-icon v-else :icon="faImage" class="text-gray-400 text-4xl" />
                </div>
            </div>

                <div  v-if="fieldValue?.carousel_data?.carousel_setting?.use_text" class="p-4 flex-1 flex flex-col justify-between">
                    <div v-html="card.text"></div>
                </div>
            </component>
        </SwiperSlide>

    </Swiper>
</template>

<style scoped lang="scss">
:deep(.swiper-pagination-bullet) {
    background-color: #d1d5db !important; // Tailwind's gray-300
    opacity: 1;
    transition: background-color 0.3s ease;
}


:deep(.swiper-pagination-bullet-active) {
    background: #4b5563 !important;
}
</style>