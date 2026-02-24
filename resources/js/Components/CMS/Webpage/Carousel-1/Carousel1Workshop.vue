<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'

import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'
import { inject, ref, watch, computed, nextTick } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fal'
import { getStyles } from '@/Composables/styles'
import Blueprint from './Blueprint'
import CardBlueprint from './CardBlueprint'
import { sendMessageToParent } from "@/Composables/Workshop"
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { faChevronRight, faChevronLeft } from '@fas'

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop',
  indexBlock?: number
}>()

const emits = defineEmits<{ (e: 'autoSave'): void }>()

const keySwiper = ref(ulid())
const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const refreshTrigger = ref(0)
const swiperInstance = ref<any>(null)
const imageSettings = {
  key: ["image", "source"],
  stencilProps: {
    aspectRatio: [1, 4 / 3, 16 / 9],
    movable: true,
    scalable: true,
    resizable: true,
  },
}

const hasCards = computed(() =>
  Array.isArray(props.modelValue?.carousel_data?.cards) &&
  props.modelValue.carousel_data.cards.length > 0
)

const slidesPerView = computed(() =>
  props.modelValue?.carousel_data?.carousel_setting?.slidesPerView?.[props.screenType] || 1
)

const isLooping = computed(() => {
  const settingsLoop = props.modelValue?.carousel_data?.carousel_setting?.loop || false
  return settingsLoop && props.modelValue.carousel_data.cards.length > slidesPerView.value
})

const cardStyle = ref(getStyles(props.modelValue?.carousel_data?.card_container?.properties, props.screenType, false))
const ImageContainer = ref(getStyles(props.modelValue.carousel_data.card_container?.container_image, props.screenType, false))

const spaceBetween = ref(((props.modelValue?.carousel_data?.carousel_setting?.spaceBetween || 0)))



const refreshCarousel = async (delay = 100) => {
  await new Promise(resolve => setTimeout(resolve, delay))
  keySwiper.value = ulid()
  await nextTick()
}

watch(
  () => [props.modelValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

watch(
  () => props.modelValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(props.modelValue?.carousel_data?.card_container?.properties, props.screenType, false)
    ImageContainer.value = getStyles(props.modelValue.carousel_data.card_container?.container_image, props.screenType, false)
    await refreshCarousel(200)
  },
  { deep: true }
)

watch(
  () => props.modelValue?.carousel_data?.carousel_setting?.spaceBetween,
  (newVal) => {
    spaceBetween.value = (newVal || 0)
    refreshCarousel()
  },
  { immediate: true, deep: true }
)

const breakpoints = computed(() => {
  const settings = props.modelValue?.carousel_data?.carousel_setting || {}
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

const activeEditorIndex = ref<number | null>(null)

const onEditorFocus = (key: string, index: number) => {
  activeEditorIndex.value = index
  sendMessageToParent('activeChildBlock', key)
}

const onEditorBlur = () => {
  activeEditorIndex.value = null
}


</script>

<template>
  <div id="carousel" class="relative">
    <div :data-refresh="refreshTrigger" :key="keySwiper" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(modelValue?.container?.properties, props.screenType)
    }">
      <button v-if="swiperInstance?.allowSlidePrev" ref="prevEl"
        class="absolute left-6 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full cursor-pointer text-gray-500"
        @click.stop="scrollLeft" @keydown="onArrowKeyLeft" aria-label="Scroll left" type="button">
        <FontAwesomeIcon :icon="faChevronLeft" />
      </button>

      <div class="mx-24">
        <Swiper v-if="hasCards" :modules="[Autoplay]" :slides-per-view="slidesPerView" :loop="isLooping"
          :space-between="spaceBetween" :breakpoints="breakpoints" :autoplay="false" @swiper="onSwiper" class="w-full">

          <SwiperSlide v-for="(data, index) in modelValue.carousel_data.cards" :key="index"
            class="!flex !justify-center !items-center" :style="{ zIndex: activeEditorIndex === index ? 99 : 1 }">
            <div class="space-card flex justify-center items-center w-full h-full">
              <div class="card flex flex-col h-full">
                <div class="flex flex-1 flex-col">

                  <!-- Image -->
                  <div class="flex justify-center overflow-visible"
                    :style="getStyles(modelValue.carousel_data.card_container?.container_image, screenType)"
                    @click.stop="() => {
                      sendMessageToParent('activeBlock', indexBlock)
                      sendMessageToParent('activeChildBlock', bKeys[2])
                      sendMessageToParent('activeChildBlockArray', index)
                      sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
                    }"
                    @dblclick.stop="() => sendMessageToParent('uploadImage', { ...imageSettings, key: ['carousel_data', 'cards', index, 'image', 'source'] })">
                    <div class="overflow-hidden w-full flex items-center justify-center"
                      :style="{ ...getStyles(modelValue.carousel_data.card_container?.image_properties, screenType) }">
                      <Image v-if="data?.image?.source" :src="data.image.source"
                        :alt="data.image.alt || `image-${index}`"
                        class="w-full h-full flex justify-center items-center" />
                      <div v-else class="flex items-center justify-center w-full h-full bg-gray-100">
                        <FontAwesomeIcon :icon="faImage" class="text-gray-400 text-4xl" />
                      </div>
                    </div>
                  </div>

                  <!-- Text -->
                  <div v-if="modelValue.carousel_data.carousel_setting?.use_text"
                    class="p-4 flex flex-col flex-1 justify-between">
                    <div class="text-center leading-relaxed">
                      <EditorV2 v-model="data.text" @focus="() => onEditorFocus(bKeys[1], index)" @blur="onEditorBlur"
                        @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                          name: webpageData.images_upload_route.name,
                          parameters: {
                            ...webpageData.images_upload_route.parameters,
                            modelHasWebBlocks: blockData?.id,
                          },
                        }" />

                    </div>
                  </div>

                </div>
              </div>
            </div>
          </SwiperSlide>
        </Swiper>

      </div>

      <button v-if="swiperInstance?.allowSlideNext" ref="nextEl"
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


:deep(.p-carousel-indicator-list) {
  display: none;
}
</style>
