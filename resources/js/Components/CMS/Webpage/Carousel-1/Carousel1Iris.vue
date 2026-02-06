<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'
import { inject, ref, watch, computed, nextTick } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fal'
import { getStyles } from '@/Composables/styles'
import { faChevronRight, faChevronLeft } from '@fas'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

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


const keySwiper = ref(ulid())
const layout: any = inject("layout", {})
const refreshTrigger = ref(0)
const swiperInstance = ref<any>(null)


const hasCards = computed(() =>
  Array.isArray(props.fieldValue?.carousel_data?.cards) &&
  props.fieldValue.carousel_data.cards.length > 0
)

const slidesPerView = computed(() =>
  props.fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.[props.screenType] || 1
)

const isLooping = computed(() => {
  const settingsLoop = props.fieldValue?.carousel_data?.carousel_setting?.loop || false
  return settingsLoop && props.fieldValue.carousel_data.cards.length > slidesPerView.value
})

const cardStyle = ref(getStyles(props.fieldValue?.carousel_data?.card_container?.properties, props.screenType, false))
const ImageContainer = ref(getStyles(props.fieldValue.carousel_data.card_container?.container_image, props.screenType, false))

const spaceBetween = ref(((props.fieldValue?.carousel_data?.carousel_setting?.spaceBetween || 0)))



const refreshCarousel = async (delay = 100) => {
  await new Promise(resolve => setTimeout(resolve, delay))
  keySwiper.value = ulid()
  await nextTick()
}

watch(
  () => [props.fieldValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

watch(
  () => props.fieldValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(props.fieldValue?.carousel_data?.card_container?.properties, props.screenType, false)
    ImageContainer.value = getStyles(props.fieldValue.carousel_data.card_container?.container_image, props.screenType, false)
    await refreshCarousel(200)
  },
  { deep: true }
)

watch(
  () => props.fieldValue?.carousel_data?.carousel_setting?.spaceBetween,
  (newVal) => {
    spaceBetween.value = (newVal || 0)
    refreshCarousel()
  },
  { immediate: true, deep: true }
)

const breakpoints = computed(() => {
  const settings = props.fieldValue?.carousel_data?.carousel_setting || {}
  return {
    0: { slidesPerView: settings.slidesPerView?.mobile || 1 },
    768: { slidesPerView: settings.slidesPerView?.tablet || 2 },
    1200: { slidesPerView: settings.slidesPerView?.desktop || 4 }
  }
})

const onSwiper = (swiper: any) => {
  swiperInstance.value = swiper
}

const scrollLeft = () => {
  if (!swiperInstance.value) return
  swiperInstance.value.slidePrev()
}

const scrollRight = () => {
  if (!swiperInstance.value) return
  swiperInstance.value.slideNext()
}

const onArrowKeyLeft = (e: KeyboardEvent) => {
  if (e.key === 'Enter' || e.key === ' ') scrollLeft()
}

const onArrowKeyRight = (e: KeyboardEvent) => {
  if (e.key === 'Enter' || e.key === ' ') scrollRight()
}


const idxSlideLoading = ref<number | null>(null)

</script>

<template>
<div id="carousel" class="relative overflow-hidden">
    <div :data-refresh="refreshTrigger" :key="keySwiper" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(fieldValue?.container?.properties, props.screenType)
    }">
      <button v-if="swiperInstance?.allowSlidePrev && isLooping" ref="prevEl"
        class="absolute left-6 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full cursor-pointer text-gray-500"
        @click.stop="scrollLeft" @keydown="onArrowKeyLeft" aria-label="Scroll left" type="button">
        <FontAwesomeIcon :icon="faChevronLeft" />
      </button>
      <div class="mx-24 overflow-hidden">
         <Swiper v-if="hasCards" :modules="[Autoplay]" :slides-per-view="slidesPerView" :loop="isLooping"
        :space-between="spaceBetween" :breakpoints="breakpoints" 
        :autoplay="fieldValue.carousel_data.carousel_setting?.interval && fieldValue.carousel_data.carousel_setting?.autoplay
          ? {
            delay: fieldValue.carousel_data.carousel_setting.interval,
            disableOnInteraction: false,
            pauseOnMouseEnter: true
          }
          : false" @swiper="onSwiper" class="w-full">

        <SwiperSlide v-for="(data, index) in fieldValue.carousel_data.cards" :key="index" >
           <div class="space-card ">
             <div class="card flex flex-col h-full ">
                <component :is="data?.link?.href  ? LinkIris : 'div'" :canonical_url="data?.link?.canonical_url"
                  :href="data?.link?.href" :target="data?.link?.target" class="relative flex flex-1 flex-col" :type="data?.link?.type"
                  @start="() => idxSlideLoading = index"
                  @finish="() => idxSlideLoading = null"
                >
                  <!-- Image Container -->
                  <div class="flex justify-center overflow-visible"
                    :style="getStyles(fieldValue.carousel_data.card_container?.container_image, screenType)" >
                    <div class="overflow-hidden w-full flex items-center justify-center "
                      :style="{  ...getStyles(fieldValue.carousel_data.card_container?.image_properties, screenType) }">
                      <Image 
                        v-if="data?.image?.source" 
                        :src="data.image.source" 
                        :alt="data.image.alt || `image-${index}`"
                        :class="'image-container'" 
                        class="w-full h-full flex justify-center items-center" 
                        :height="getStyles(fieldValue.carousel_data.card_container?.container_image, screenType)?.height"
                        :width="getStyles(fieldValue.carousel_data.card_container?.container_image, screenType)?.width"
                      />
                      <div v-else class="flex items-center justify-center w-full h-full bg-gray-100">
                        <FontAwesomeIcon :icon="faImage" class="text-gray-400 text-4xl" />
                      </div>
                    </div>
                  </div>

                  <!-- Text Content -->
                  <div v-if="fieldValue.carousel_data.carousel_setting?.use_text"
                    class="p-4 flex flex-col flex-1 justify-between">
                    <div v-html="data.text" class="text-center leading-relaxed" />
                  </div>

                  <div v-if="idxSlideLoading == index" class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl">
                    <LoadingIcon />
                  </div>
                </component>
              </div>
          </div>
        </SwiperSlide>
      </Swiper>

      </div>
      <button v-if="swiperInstance?.allowSlideNext && isLooping" ref="nextEl"
        class="absolute right-6 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full cursor-pointer text-gray-500"
        @click.stop="scrollRight" @keydown="onArrowKeyRight" aria-label="Scroll right" type="button">
        <FontAwesomeIcon :icon="faChevronRight" />
      </button>
    </div>
  </div>
</template>

<style scoped>
/* hide indicator if you later enable pagination */
:deep(.swiper-pagination) {
  display: none;
}


/* :deep(.p-carousel-indicator-list) {
  display: none;
} */

/* spacing between cards (same behavior as previous carousel gap logic) */
.space-card {
  box-sizing: border-box;
  height: 100%;
}

/* main card styling (UNCHANGED — keep exactly same dynamic binding) */
.card {
  display: flex;
  flex-direction: column;
  height: v-bind('cardStyle?.height || "100%"') !important;
  width: v-bind('cardStyle?.width || "95%"') !important;
  background: v-bind('cardStyle?.background || "transparent"') !important;

  padding-top: v-bind('cardStyle?.paddingTop || "0px"') !important;
  padding-right: v-bind('cardStyle?.paddingRight || "0px"') !important;
  padding-bottom: v-bind('cardStyle?.paddingBottom || "0px"') !important;
  padding-left: v-bind('cardStyle?.paddingLeft || "0px"') !important;

  margin-top: v-bind('cardStyle?.marginTop || "0px"') !important;
  margin-right: v-bind('cardStyle?.marginRight || "0px"') !important;
  margin-bottom: v-bind('cardStyle?.marginBottom || "0px"') !important;
  margin-left: v-bind('cardStyle?.marginLeft || "0px"') !important;

  border-top-left-radius: v-bind('cardStyle?.borderTopLeftRadius || "0px"') !important;
  border-top-right-radius: v-bind('cardStyle?.borderTopRightRadius || "0px"') !important;
  border-bottom-left-radius: v-bind('cardStyle?.borderBottomLeftRadius || "0px"') !important;
  border-bottom-right-radius: v-bind('cardStyle?.borderBottomRightRadius || "0px"') !important;

  border-top: v-bind('cardStyle?.borderTop || "0px solid transparent"') !important;
  border-bottom: v-bind('cardStyle?.borderBottom || "0px solid transparent"') !important;
  border-left: v-bind('cardStyle?.borderLeft || "0px solid transparent"') !important;
  border-right: v-bind('cardStyle?.borderRight || "0px solid transparent"') !important;
}

/* ensure swiper slide takes full height */
:deep(.swiper-slide) {
  height: auto;
  display: flex;
}

/* allow card stretch full height */
:deep(.swiper-slide > .space-card) {
  width: 100%;
  display: flex;
}

/* image alignment dynamic */
.image-container {
  justify-content: v-bind('ImageContainer?.justifyContent || "center"') !important;
}

/* smooth fade (optional if you use transitions later) */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* optional: remove overflow clipping if builder needs edit outside */
:deep(.swiper) {
  overflow: visible;
}

:deep(.swiper-wrapper) {
  align-items: stretch;
}
</style>
