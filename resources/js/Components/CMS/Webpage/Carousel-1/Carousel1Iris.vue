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
import { inject } from 'vue'

const props = defineProps<{
  fieldValue: {
    container?: { properties?: any }
    carousel_data: {
      carousel_setting: {
        slidesPerView: { mobile: number; tablet: number; desktop: number }
        loop?: boolean
        autoplay?: any
        spaceBetween?: number
        use_text?: boolean
      }
      cards: Array<any>
      card_container: {
        properties?: any
        container_image?: any
        image_properties?: any
      }
    }
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})
const keySwiper = ulid()

const getHref = (item: any) => !!item?.link?.href
</script>

<template>
  <div id="carousel">
    <div
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue?.container?.properties, screenType)
      }"
    >
      <Swiper
        :id="`carousel_1_iris_${keySwiper}`"
        :key="keySwiper"
        class="touch-pan-x touch-pan-y"
        direction="horizontal"
        :passiveListeners="true"
        :simulateTouch="false"
        :touchStartPreventDefault="false"
        :touchRatio="1"
        :touchAngle="30"
        :loop="fieldValue?.carousel_data?.carousel_setting?.loop"
        :autoplay="fieldValue?.carousel_data?.carousel_setting?.autoplay"
        :pagination="{ clickable: true }"
        :spaceBetween="fieldValue?.carousel_data?.carousel_setting?.spaceBetween || 0"
        :slidesPerView="fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.desktop"
        :breakpoints="{
          0:    { slidesPerView: fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.mobile },
          640:  { slidesPerView: fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.mobile },
          768:  { slidesPerView: fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.tablet },
          1024: { slidesPerView: fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.desktop }
        }"
        :modules="[Pagination, Autoplay]"
      >
        <SwiperSlide
          v-for="(card, index) in fieldValue.carousel_data.cards"
          :key="index"
          class="flex flex-col"
          :style="{ ...getStyles(fieldValue?.carousel_data?.card_container?.properties, screenType), height: '100%' }"
        >
          <component
            :is="getHref(card) ? 'a' : 'div'"
            :href="card?.link?.href"
            :target="card?.link?.target"
            class="flex-1 flex flex-col"
          >
            <div
              class="flex justify-center overflow-visible"
              :style="getStyles(fieldValue?.carousel_data?.card_container?.container_image, screenType)"
            >
              <div
                :class="[
                  !card?.image?.source && 'bg-gray-100 w-full  flex items-center justify-center overflow-auto',
                  'overflow-hidden'
                ]"
                :style="getStyles(fieldValue?.carousel_data?.card_container?.image_properties, screenType)"
              >
                <Image
                  v-if="card?.image?.source"
                  :src="card.image.source"
                  :alt="card.image.alt || `image-${index}`"
                  :style="getStyles(card?.image?.properties, screenType)"
                />
                <FontAwesomeIcon
                  v-else
                  :icon="faImage"
                  class="text-gray-400 text-4xl"
                />
              </div>
            </div>

            <div
              v-if="fieldValue?.carousel_data?.carousel_setting?.use_text"
              class="p-4 flex-1 flex flex-col justify-between"
            >
              <div v-html="card.text" />
            </div>
          </component>
        </SwiperSlide>
      </Swiper>
    </div>
  </div>
</template>

<style scoped lang="scss">
:deep(.swiper-pagination-bullet) {
  background-color: #d1d5db !important; // Tailwind's gray-300
  opacity: 1;
  transition: background-color 0.3s ease;
}

:deep(.swiper-pagination-bullet-active) {
  background: #4b5563 !important; // Tailwind's gray-600
}

.swiper {
  touch-action: pan-y;
}
</style>
