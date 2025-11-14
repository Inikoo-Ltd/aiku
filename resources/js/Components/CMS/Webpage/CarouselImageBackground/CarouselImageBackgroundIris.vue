<script setup lang="ts">
import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation, Pagination, Autoplay, Thumbs } from "swiper/modules"
import "swiper/css"
import "swiper/css/navigation"
import "swiper/css/pagination"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import Image from "@/Components/Image.vue"
import { inject, ref, watch, computed, nextTick, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronCircleLeft, faChevronCircleRight } from "@fas"
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
        button?: boolean
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
const refreshTrigger = ref(0)
const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const navigation = ref<any>(null)
const swiperInstance = ref<any>(null)
const thumbsSwiper = ref(null)

const getHref = (data: any) => data?.link?.href

const refreshCarousel = async (delay = 100) => {
  await new Promise((r) => setTimeout(r, delay))
  refreshTrigger.value++
  await nextTick()
}

const hasCards = computed(
  () =>
    Array.isArray(props.fieldValue?.carousel_data?.cards) &&
    props.fieldValue.carousel_data.cards.length > 0
)

const slidesPerView = computed(
  () =>
    props.fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.[
      props.screenType
    ] || 1
)

const isLooping = computed(() => {
  const loop = props.fieldValue?.carousel_data?.carousel_setting?.loop || false
  return loop && props.fieldValue.carousel_data.cards.length > slidesPerView.value
})

const cardStyle = ref(
  getStyles(
    props.fieldValue?.carousel_data?.card_container?.properties,
    props.screenType,
    false
  )
)

watch(
  () => [props.fieldValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

watch(
  () => props.fieldValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(
      props.fieldValue?.carousel_data?.card_container?.properties,
      props.screenType,
      false
    )
    await refreshCarousel(200)
  },
  { deep: true }
)

const responsiveBreakpoints = computed(() => {
  const settings = props.modelValue?.carousel_data?.carousel_setting || {}
  return {
    0: {
      slidesPerView: settings.slidesPerView?.mobile || 1,
      spaceBetween: settings.spaceBetween || 10,
    },
    768: {
      slidesPerView: settings.slidesPerView?.tablet || 2,
      spaceBetween: settings.spaceBetween || 10,
    },
    1024: {
      slidesPerView: settings.slidesPerView?.desktop || 4,
      spaceBetween: settings.spaceBetween || 10,
    },
  }
})


onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value,
  }
  await nextTick()
  swiperInstance.value?.update()
})

const idxSlideLoading = ref<null | number>(null)
</script>

<template>
   <div id="carousel-background-image" class="relative w-full">
    <div
      :data-refresh="refreshTrigger"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(fieldValue?.container?.properties, props.screenType),
      }"
    >
      <Swiper
        v-if="hasCards && navigation"
        :modules="[Navigation, Autoplay, Thumbs]"
        :slides-per-view="slidesPerView"
        :loop="isLooping"
        :breakpoints="responsiveBreakpoints"
        :navigation="navigation"
        :pagination="{ clickable: true }"
        :autoplay="fieldValue.carousel_data.carousel_setting.autoplay ? { delay: 1000, disableOnInteraction: false } : false"
        :thumbs="{ swiper: thumbsSwiper }"
        :key="refreshTrigger"
        class="w-full"
        @swiper="(s) => (swiperInstance = s)"
      >
        <SwiperSlide
          v-for="(data, index) in fieldValue.carousel_data.cards"
          :key="index"
        >
          <div class="px-1 md:px-1 lg:px-1 space-card">
            <component :is="getHref(data) ? LinkIris : 'div'" :canonical_url="data?.link?.canonical_url"
              :href="data?.link?.href" :target="data?.link?.target"
              class="card relative isolate flex flex-col justify-end overflow-hidden rounded-2xl hover:shadow-xl transition-all duration-300"
              @start="() => (idxSlideLoading = index)"
              @finish="() => (idxSlideLoading = null)"
            >
              <Image :src="data?.image?.source" :alt="data?.image?.alt" :imageCover="true"
                class="absolute inset-0 -z-10 w-full h-full object-cover" />
              <div class="absolute inset-0 flex flex-col justify-start items-start p-6">
                <div v-html="data.text" class="w-full"></div>
                <div v-if="fieldValue?.carousel_data?.carousel_setting.button"  class="flex mt-auto w-full" :style="{...getStyles(fieldValue?.button?.container_button?.properties, screenType),...getStyles(data?.button?.container_button?.properties, screenType)}" >
                  <LinkIris
                    :href="data?.button?.link?.href"
                    :canonical_url="data?.button?.link?.canonical_url" 
                    :target="data?.button?.link?.taget"
                    typeof="button"
                    :type="data?.button?.link?.type"
                >
                    <Button :injectStyle="{...getStyles(fieldValue?.button?.container?.properties, screenType),...getStyles(data?.button?.container?.properties, screenType)}"
                      :label="data?.button?.text" />
                  </LinkIris>
                </div>
              </div>
              <div v-if="idxSlideLoading == index" class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl">
                <LoadingIcon />
              </div>
            </component>
          </div>
        </SwiperSlide>

        <!-- Navigation Buttons -->
        <div class="absolute inset-0 pointer-events-none z-50">
          <div
            v-if="isLooping"
            ref="prevEl"
            @click="swiperInstance?.slidePrev()"
            class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto"
          >
            <FontAwesomeIcon fixed-width :icon="faChevronCircleLeft" :style="getStyles(props.fieldValue?.carousel_data?.buttonStyle, screenType)" />
          </div>
          <div
            v-if="isLooping"
            ref="nextEl"
            @click="swiperInstance?.slideNext()"
            class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto"
          >
            <FontAwesomeIcon fixed-width :icon="faChevronCircleRight"  :style="getStyles(props.fieldValue?.carousel_data?.buttonStyle, screenType)"/>
          </div>
        </div>
      </Swiper>
    </div>
  </div>
</template>

<style scoped>
:deep(.swiper-pagination-bullets) {
  bottom: 10px;
  display: flex;
  justify-content: center;
}

.card {
  background: v-bind('cardStyle?.background || "transparent"') !important;
  padding-top: v-bind('cardStyle?.paddingTop || "0px"') !important;
  padding-right: v-bind('cardStyle?.paddingRight || "0px"') !important;
  padding-bottom: v-bind('cardStyle?.paddingBottom || "0px"') !important;
  padding-left: v-bind('cardStyle?.paddingLeft || "0px"') !important;

  margin-top: v-bind('cardStyle?.marginTop || "0px"') !important;
  margin-right: v-bind('cardStyle?.marginRight || "0px"') !important;
  margin-bottom: v-bind('cardStyle?.marginBottom || "0px"') !important;
  margin-left: v-bind('cardStyle?.marginLeft || "0px"') !important;

  border-radius: v-bind('cardStyle?.borderRadius || "0px"') !important;
  border-top: v-bind('cardStyle?.borderTop || "0px solid transparent"') !important;
  border-bottom: v-bind('cardStyle?.borderBottom || "0px solid transparent"') !important;
  border-left: v-bind('cardStyle?.borderLeft || "0px solid transparent"') !important;
  border-right: v-bind('cardStyle?.borderRight || "0px solid transparent"') !important;

  border-top-left-radius: v-bind('cardStyle?.borderTopLeftRadius || null') !important;
  border-top-right-radius: v-bind('cardStyle?.borderTopRightRadius || null') !important;
  border-bottom-left-radius: v-bind('cardStyle?.borderBottomLeftRadius || null') !important;
  border-bottom-right-radius: v-bind('cardStyle?.borderBottomRightRadius || null') !important;

  height: v-bind('cardStyle?.height || "17rem"') !important;
  width: v-bind('cardStyle?.width || null') !important;
}

</style>
