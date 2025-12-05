<script setup lang="ts">
import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation, Pagination, Autoplay, Thumbs } from "swiper/modules"
import "swiper/css"
import "swiper/css/navigation"
import "swiper/css/pagination"

import Image from "@/Components/Image.vue"
import { inject, ref, watch, computed, nextTick, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import Blueprint from "./Blueprint"
import CardBlueprint from "./CardBlueprint"
import { sendMessageToParent } from "@/Composables/Workshop"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronCircleLeft, faChevronCircleRight } from "@fas"

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock?: number
}>()

const emits = defineEmits<{ (e: "autoSave"): void }>()

const layout: any = inject("layout", {})
const refreshTrigger = ref(0)
const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const navigation = ref<any>(null)
const swiperInstance = ref<any>(null)
const thumbsSwiper = ref(null)

const refreshCarousel = async (delay = 100) => {
  await new Promise((r) => setTimeout(r, delay))
  refreshTrigger.value++
  await nextTick()
}

const hasCards = computed(
  () =>
    Array.isArray(props.modelValue?.carousel_data?.cards) &&
    props.modelValue.carousel_data.cards.length > 0
)

const slidesPerView = computed(
  () =>
    props.modelValue?.carousel_data?.carousel_setting?.slidesPerView?.[
      props.screenType
    ] || 1
)

const isLooping = computed(() => {
  const loop = props.modelValue?.carousel_data?.carousel_setting?.loop || false
  return loop && props.modelValue.carousel_data.cards.length > slidesPerView.value
})

const showButton = computed(() => {
  return props.modelValue.carousel_data.cards.length > slidesPerView.value
})

const cardStyle = ref(
  getStyles(
    props.modelValue?.carousel_data?.card_container?.properties,
    props.screenType,
    false
  )
)

watch(
  () => [props.modelValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

watch(
  () => props.modelValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(
      props.modelValue?.carousel_data?.card_container?.properties,
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

const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []

const imageSettings = {
  key: ["image", "source"],
  stencilProps: {
    aspectRatio: [1 / 1, 3 / 4],
    movable: true,
    scalable: true,
    resizable: true,
  },
}


onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value,
  }
  await nextTick()
  swiperInstance.value?.update()
})
</script>

<template>
  <div id="carousel-background-image" class="relative w-full">
    <div :data-refresh="refreshTrigger" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(modelValue?.container?.properties, props.screenType),
      }">
      <Swiper v-if="hasCards && navigation" :modules="[Navigation, Autoplay, Thumbs]" :slides-per-view="slidesPerView"
        :loop="isLooping" :breakpoints="responsiveBreakpoints" :navigation="navigation"
        :pagination="{ clickable: true }" :autoplay="false" :thumbs="{ swiper: thumbsSwiper }" :key="refreshTrigger"
        class="w-full" @swiper="(s) => (swiperInstance = s)">
        <SwiperSlide v-for="(data, index) in modelValue.carousel_data.cards" :key="index">
          <div class="px-1 md:px-1 lg:px-1 space-card">
            <article @click.stop="
                () => {
                  sendMessageToParent('activeBlock', indexBlock)
                  sendMessageToParent('activeChildBlock', bKeys[3])
                  sendMessageToParent('activeChildBlockArray', index)
                  sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
                }
              " @dblclick.stop="
                () =>
                  sendMessageToParent('uploadImage', {
                    ...imageSettings,
                    key: ['carousel_data', 'cards', index, 'image', 'source'],
                  })
              "
              class="card relative isolate flex items-center justify-center overflow-hidden rounded-2xl hover:shadow-xl transition-all duration-300">
              <Image :src="data?.image?.source" :alt="data?.image?.alt" :imageCover="true"
                class="absolute inset-0 -z-10 w-full h-full object-cover" />

              <div class="absolute inset-0 flex flex-col justify-between p-6">
                <div v-html="data.text" class="w-full"></div>

                <div v-if="modelValue?.carousel_data?.carousel_setting?.button" class="flex mt-auto" :style="{
                    ...getStyles(modelValue?.button?.container_button?.properties, screenType),
                    ...getStyles(data?.button?.container_button?.properties, screenType),
                  }">
                  <Button :injectStyle="{
                      ...getStyles(modelValue?.button?.container?.properties, screenType),
                      ...getStyles(data?.button?.container?.properties, screenType),
                    }" :label="data?.button?.text || modelValue?.button?.text" @click.stop="
                      () => {
                        sendMessageToParent('activeBlock', indexBlock)
                        sendMessageToParent('activeChildBlock', bKeys[2])
                      }
                    " />
                </div>
              </div>
            </article>
          </div>
        </SwiperSlide>
      </Swiper>
    </div>
    <div class="absolute inset-0 pointer-events-none z-50">
      <div v-if="showButton" ref="prevEl" @click="swiperInstance?.slidePrev()"
        class="absolute left-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto">
        <FontAwesomeIcon fixed-width :icon="faChevronCircleLeft"
          :style="getStyles(props.modelValue?.carousel_data?.buttonStyle, screenType)" />
      </div>
      <div v-if="showButton" ref="nextEl" @click="swiperInstance?.slideNext()"
        class="absolute right-4 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-50 hover:opacity-100 pointer-events-auto">
        <FontAwesomeIcon fixed-width :icon="faChevronCircleRight"
          :style="getStyles(props.modelValue?.carousel_data?.buttonStyle, screenType)" />
      </div>
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
