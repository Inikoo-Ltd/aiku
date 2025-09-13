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
import { inject, ref, watch } from 'vue'
import Blueprint from './Blueprint'

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: 'autoSave'): void
}>()

const keySwiper = ref(ulid())

const activeEditorIndex = ref<number | null>(null)

// Swiper will be re-rendered if this key changes
watch(
    () => props.modelValue.carousel_data.carousel_setting,
    () => {
        keySwiper.value = ulid()
    },
    { deep: true }
)


const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
const getHref = (item: any) => !!item?.link?.href
</script>

<template>
  <div id="carousel">
    <div
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue?.container?.properties, screenType)
      }"
    >
      <Swiper
        :key="keySwiper"
        class="touch-pan-x touch-pan-y"
        direction="horizontal"
        :passiveListeners="true"
        :simulateTouch="false"
        :touchStartPreventDefault="false"
        :touchRatio="1"
        :touchAngle="30"
        :loop="modelValue?.carousel_data?.carousel_setting?.loop"
        :autoplay="modelValue?.carousel_data?.carousel_setting?.autoplay"
        :pagination="{ clickable: true }"
        :spaceBetween="modelValue?.carousel_data?.carousel_setting?.spaceBetween || 0"
        :slidesPerView="modelValue?.carousel_data?.carousel_setting?.slidesPerView?.desktop"
        :breakpoints="{
          0:    { slidesPerView: modelValue?.carousel_data?.carousel_setting?.slidesPerView?.mobile },
          640:  { slidesPerView: modelValue?.carousel_data?.carousel_setting?.slidesPerView?.mobile },
          768:  { slidesPerView: modelValue?.carousel_data?.carousel_setting?.slidesPerView?.tablet },
          1024: { slidesPerView: modelValue?.carousel_data?.carousel_setting?.slidesPerView?.desktop }
        }"
        :modules="[Pagination, Autoplay]"
      >
        <SwiperSlide
          v-for="(card, index) in modelValue.carousel_data.cards"
          :key="index"
          class="flex flex-col"
          :style="{  height: '100%' , overflow : 'auto', ...getStyles(modelValue?.carousel_data?.card_container?.properties, screenType)}"
        >
          <component
            :is="getHref(card) ? 'a' : 'div'"
            :href="card?.link?.href"
            :target="card?.link?.target"
            class="flex-1 flex flex-col"
          >
            <div
              class="flex justify-center overflow-visible"
              :style="getStyles(modelValue?.carousel_data?.card_container?.container_image, screenType)"
            >
              <div
                :class="[
                  !card?.image?.source && 'w-full  flex items-center justify-center overflow-auto',
                  'overflow-hidden'
                ]"
                :style="getStyles(modelValue?.carousel_data?.card_container?.image_properties, screenType)"
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
              v-if="modelValue?.carousel_data?.carousel_setting?.use_text"
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
    background: #4b5563 !important;
}

.swiper {
    touch-action: pan-y;
}
</style>
