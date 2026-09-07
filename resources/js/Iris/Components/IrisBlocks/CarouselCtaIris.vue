<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'
import 'swiper/css'
import Image from "@common/Components/Image.vue"
import Button from "@iris/Components/IrisButton.vue"
import { getStyles } from "@/Composables/styles"
import LinkIris from '@/Iris/Components/LinkIris.vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fas'
import { computed, inject, ref } from 'vue'


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
  indexBlock:number
}>()


const layout: any = inject("layout", {})

const cards = computed(() => props.fieldValue?.carousel_data?.cards || [])

const isLooping = computed(() => {
  const settingsLoop = props.fieldValue?.carousel_data?.carousel_setting?.loop || false
  return settingsLoop && cards.value.length > 1
})

const autoplayOptions = computed(() =>
  props.fieldValue?.carousel_data?.carousel_setting?.autoplay
    ? { delay: 3000, disableOnInteraction: false, pauseOnMouseEnter: true }
    : false
)

const swiperInstance = ref<any>(null)
const isBeginning = ref(true)
const reachedEnd = ref(false)
const isEnd = computed(() => cards.value.length <= 1 || reachedEnd.value)

const syncNavigatorState = (swiper: any) => {
  isBeginning.value = swiper.isBeginning
  reachedEnd.value = swiper.isEnd
}

const onSwiper = (swiper: any) => {
  swiperInstance.value = swiper
  syncNavigatorState(swiper)
}

const slidePrev = () => swiperInstance.value?.slidePrev()
const slideNext = () => swiperInstance.value?.slideNext()
</script>

<template>
  <div :id="fieldValue?.id ? fieldValue?.id : 'carousel-cta' + indexBlock" component="carousel-cta">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType)
    }">
      <div class="carousel-cta-content">
        <button type="button" class="carousel-cta-nav carousel-cta-nav-prev" aria-label="Previous"
          :disabled="!isLooping && isBeginning" @click.stop="slidePrev">
          <FontAwesomeIcon :icon="faChevronLeft" />
        </button>

        <Swiper class="w-full min-w-0 carousel-cta-swiper" :modules="[Autoplay]" :slides-per-view="1"
          :space-between="0" :loop="isLooping" :autoplay="autoplayOptions" :allow-touch-move="cards.length > 1"
          :simulate-touch="true" :grab-cursor="cards.length > 1" :threshold="5" :resistance-ratio="0.6"
          @swiper="onSwiper" @slide-change="syncNavigatorState">
          <SwiperSlide v-for="(data, index) in cards" :key="index">

            <div class="w-full" :style="{
              ...getStyles(data.container?.properties, screenType),
            }">
              <div class="grid grid-cols-1 sm:grid-cols-2 w-full">
                <component 
                    :is="data?.image?.link?.href ? LinkIris : 'div'" 
                    :href="data?.image?.link?.href" 
                    :target="data?.image?.link?.target"
                    :type="data?.image?.link?.type"  
                    class="relative w-full cursor-pointer overflow-hidden h-[250px] sm:h-[300px] lg:h-[400px]"
                    :style="getStyles(fieldValue?.image?.container?.properties, screenType)"
                >
                  <Image 
                    :src="data.image.source" 
                    :imageCover="true" 
                    :alt="data.image.alt || 'Image preview'"
                    :imgAttributes="data.image.attributes" 
                    class="absolute inset-0 w-full h-full object-cover"
                    :height="getStyles(fieldValue?.image?.container?.properties, screenType,false)?.height"
                    :width="getStyles(fieldValue?.image?.container?.properties, screenType,false)?.width"
                    :preload="Number(indexBlock) === 0 && index === 0"
                  />
                </component>

                <div class="flex flex-col justify-center m-auto w-full min-w-0 px-4 py-6 sm:p-5 lg:p-4"
                  :style="getStyles(data?.text_block?.properties, screenType)">
                  <div class="max-w-xl w-full mx-auto">
                    <div v-html="data.text" />
                    <div class="flex justify-center mt-6">
                      <LinkIris :type="data?.button?.link?.type"
                        :href="data?.button?.link?.href"
                        :canonical_url="data?.button?.link?.canonical_url"
                        :target="data?.button?.link?.target">
                        <Button
                          :injectStyle="getStyles(data?.button?.container?.properties, screenType)"
                          :label="data?.button?.text" />
                      </LinkIris>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </SwiperSlide>
        </Swiper>

        <button type="button" class="carousel-cta-nav carousel-cta-nav-next" aria-label="Next"
          :disabled="!isLooping && isEnd" @click.stop="slideNext">
          <FontAwesomeIcon :icon="faChevronRight" />
        </button>
      </div>
    </div>
  </div>
</template>
<style scoped>
.carousel-cta-swiper {
  touch-action: pan-y;
}

.carousel-cta-swiper :deep(img) {
  -webkit-user-drag: none;
  user-select: none;
}

.carousel-cta-content {
  display: flex;
  flex-direction: row;
  gap: 0.25rem;
}

.carousel-cta-nav {
  align-self: center;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
  border: 0 none;
  border-radius: 50%;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s;
}

.carousel-cta-nav:hover:not(:disabled) {
  background: rgba(0, 0, 0, 0.05);
  color: #374151;
}

.carousel-cta-nav:disabled {
  opacity: 0.6;
  cursor: default;
}

@media (max-width: 639px) {
  .carousel-cta-content {
    position: relative;
  }

  .carousel-cta-nav {
    position: absolute;
    top: 50%;
    z-index: 2;
    margin: 0;
    transform: translateY(-50%);
  }

  .carousel-cta-nav-prev {
    left: 0.25rem;
  }

  .carousel-cta-nav-next {
    right: 0.25rem;
  }
}
</style>
