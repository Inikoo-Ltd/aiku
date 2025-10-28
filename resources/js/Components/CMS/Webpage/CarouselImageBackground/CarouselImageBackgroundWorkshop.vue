<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import { inject, ref, watch, computed, nextTick } from 'vue'
import { getStyles } from '@/Composables/styles'
import Blueprint from './Blueprint'
import CardBlueprint from './CardBlueprint'
import { sendMessageToParent } from "@/Composables/Workshop"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock: number
}>()

const emits = defineEmits<{ (e: 'autoSave'): void }>()

const refreshTrigger = ref(0)
const layout: any = inject('layout', {})

// Refresh carousel when layout or settings change
const refreshCarousel = async (delay = 100) => {
  await new Promise(resolve => setTimeout(resolve, delay))
  refreshTrigger.value++
  await nextTick()
}

const hasCards = computed(() =>
  Array.isArray(props.modelValue?.carousel_data?.cards) &&
  props.modelValue.carousel_data.cards.length > 0
)

const slidesPerView = computed(() =>
  props.modelValue?.carousel_data?.carousel_setting?.slidesPerView?.[props.screenType] || 1
)

const isLooping = computed(() => {
  const loop = props.modelValue?.carousel_data?.carousel_setting?.loop || false
  return loop && props.modelValue.carousel_data.cards.length > slidesPerView.value
})

const cardStyle = ref(getStyles(props.modelValue?.carousel_data?.card_container?.properties, props.screenType, false))


watch(
  () => [props.modelValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

watch(
  () => props.modelValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(props.modelValue?.carousel_data?.card_container?.properties, props.screenType, false)
    await refreshCarousel(200)
  },
  { deep: true }
)

const responsiveOptions = computed(() => {
  const settings = props.modelValue?.carousel_data?.carousel_setting || {}
  return [
    { breakpoint: '1200px', numVisible: settings.slidesPerView?.desktop || 4, numScroll: 1 },
    { breakpoint: '992px', numVisible: settings.slidesPerView?.tablet || 2, numScroll: 1 },
    { breakpoint: '576px', numVisible: settings.slidesPerView?.mobile || 1, numScroll: 1 }
  ]
})

const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []

const imageSettings = {
  key: ["image", "source"],
  stencilProps: {
    aspectRatio: 1 / 1,
    movable: true,
    scalable: true,
    resizable: true,
  },
}

</script>

<template>
  <div id="carousel-background-image" class="relative w-full">
    <!-- Carousel -->
    <div :data-refresh="refreshTrigger" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(modelValue?.container?.properties, props.screenType)
    }" @click.stop="
      () => {
        sendMessageToParent('activeBlock', indexBlock)
        sendMessageToParent('activeChildBlock', bKeys[0])
      }">
      <Carousel v-if="hasCards" :value="modelValue.carousel_data.cards" :numVisible="slidesPerView"
        :circular="isLooping" :autoplayInterval="0" :responsiveOptions="responsiveOptions" class="w-full">
        <template #item="{ data, index }">
          <!-- WRAPPER: This adds gap safely -->
          <div class="px-1 md:px-1 lg:px-1">
            <article @click.stop="
              () => {
                sendMessageToParent('activeBlock', indexBlock)
                sendMessageToParent('activeChildBlock', bKeys[2])
                sendMessageToParent('activeChildBlockArray', index)
                sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
              }
            " @dblclick.stop="() => sendMessageToParent('uploadImage', { ...imageSettings, key: ['carousel_data', 'cards', index, 'image', 'source'] })"
              class="card relative isolate flex items-center justify-center overflow-hidden rounded-2xl hover:shadow-xl transition-all duration-300">
              <!-- Background image -->
              <Image :src="data?.image?.source" :alt="data?.image?.alt" :imageCover="true"
                class="absolute inset-0 -z-10 w-full h-full object-cover" />
              <div
                class="absolute inset-0 flex flex-col justify-start items-start p-6 text-white ">
                <div v-html="data.text" class="mb-4"></div>
                <div v-if="modelValue?.carousel_data?.carousel_setting?.button">
                  <Button :injectStyle="getStyles(data?.button?.container?.properties, screenType)"
                    :label="data?.button?.text" @click.stop="
                      () => {
                        sendMessageToParent('activeBlock', indexBlock)
                        sendMessageToParent('activeChildBlock', bKeys[2])
                      }
                    " />
                </div>
              </div>
            </article>
          </div>
        </template>
      </Carousel>
    </div>
  </div>
</template>

<style scoped>
:deep(.p-carousel-indicator-list) {
  display: none;
}

.card {
  background: v-bind('cardStyle?.background || "transparent"') !important;
  padding-top: v-bind('cardStyle?.paddingTop || "0px"') !important;
  padding-right: v-bind('cardStyle?.paddingRight || "0px"') !important;
  padding-bottom: v-bind('cardStyle?.paddingBottom || "0px"') !important;
  padding-left: v-bind('cardStyle?.paddingLeft || "0px"') !important;

  margin-top: v-bind('cardStyle?.marginTop || "0px"') !important;
  margin-right: v-bind('cardStyle?.marginRight || "0px"') !important;
  margin-bottom: v-bind('cardStyle?.marginBottom || "10px"') !important;
  margin-left: v-bind('cardStyle?.marginLeft || "10px"') !important;

  border-radius: v-bind('cardStyle?.borderRadius || "0px"') !important;
  border-top: v-bind('cardStyle?.borderTop || "0px solid transparent"') !important;
  border-bottom: v-bind('cardStyle?.borderBottom || "0px solid transparent"') !important;
  border-left: v-bind('cardStyle?.borderLeft || "0px solid transparent"') !important;
  border-right: v-bind('cardStyle?.borderRight || "0px solid transparent"') !important;

  height: v-bind('cardStyle?.height || "17rem"') !important;
  width: v-bind('cardStyle?.width || null') !important;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
