<script setup lang="ts">
import Carousel from 'primevue/carousel'
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

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop',
  indexBlock: number
}>()

const emits = defineEmits<{ (e: 'autoSave'): void }>()

const keySwiper = ref(ulid())
const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
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

const screenType = computed(() => props.screenType)
const cardStyle = ref(getStyles(props.modelValue?.carousel_data?.card_container?.properties, props.screenType, false))
const ImageContainer = ref(getStyles(props.modelValue.carousel_data.card_container?.container_image, props.screenType, false))

// Auto select aspect ratio based on slidesPerView
const selectedAspectRatio = computed(() => {
  if (!hasCards.value) return 1
  if (slidesPerView.value === 1) return 1
  if (slidesPerView.value <= 3) return 4 / 3
  return 16 / 9
})

const refreshCarousel = async (delay = 100) => {
  await new Promise(resolve => setTimeout(resolve, delay))
  keySwiper.value = ulid()
  await nextTick()
}

// Watch for settings or screen changes
watch(
  () => [props.modelValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

// Watch for card container property changes
watch(
  () => props.modelValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(props.modelValue?.carousel_data?.card_container?.properties, props.screenType, false)
    ImageContainer.value = getStyles(props.modelValue.carousel_data.card_container?.container_image, props.screenType, false)
    await refreshCarousel(200)
  },
  { deep: true }
)



const responsiveOptions = computed(() => {
  const settings = props.modelValue?.carousel_data?.carousel_setting || {}
  return [
    {
      breakpoint: '1200px',
      numVisible: settings.slidesPerView?.desktop || 4,
      numScroll: 1
    },
    {
      breakpoint: '992px',
      numVisible: settings.slidesPerView?.tablet || 2,
      numScroll: 1
    },
    {
      breakpoint: '576px',
      numVisible: settings.slidesPerView?.mobile || 1,
      numScroll: 1
    }
  ]
})
</script>

<template>
  <div id="carousel" class="relative">
    <div :key="keySwiper" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(modelValue?.container?.properties, props.screenType)
    }">
      <Carousel v-if="hasCards" :value="modelValue.carousel_data.cards" :numVisible="slidesPerView"
        :circular="isLooping" :autoplayInterval="0" :responsiveOptions="responsiveOptions" class="w-full">
        <template #item="{ data, index }">
          <div class="card flex flex-col h-full">
            <div class="flex flex-1 flex-col">
              <!-- Image Container -->
              <div class="flex justify-center overflow-visible"
                :style="getStyles(modelValue.carousel_data.card_container?.container_image, screenType)" @click.stop="() => {
                  sendMessageToParent('activeBlock', indexBlock)
                  sendMessageToParent('activeChildBlock', bKeys[2])
                  sendMessageToParent('activeChildBlockArray', index)
                  sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
                }"
                @dblclick.stop="() => sendMessageToParent('uploadImage', { ...imageSettings, key: ['carousel_data', 'cards', index, 'image', 'source'] })">
                <div class="overflow-hidden w-full flex items-center justify-center "
                  :style="{ aspectRatio: selectedAspectRatio, ...getStyles(modelValue.carousel_data.card_container?.image_properties, screenType) }">
                  <Image v-if="data?.image?.source" :src="data.image.source" :alt="data.image.alt || `image-${index}`"
                    :class="'image-container'" class="w-full h-full flex justify-center items-center" />
                  <div v-else class="flex items-center justify-center w-full h-full bg-gray-100">
                    <FontAwesomeIcon :icon="faImage" class="text-gray-400 text-4xl" />
                  </div>
                </div>
              </div>

              <!-- Text Content -->
              <div v-if="modelValue.carousel_data.carousel_setting?.use_text"
                class="p-4 flex flex-col flex-1 justify-between">
                <div  class="text-center leading-relaxed" >
                <EditorV2 v-model="data.text" @focus="() => sendMessageToParent('activeChildBlock', bKeys[1])"
                  @update:modelValue="() => emits('autoSave')"  :uploadImageRoute="{
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

.image-container {
  justify-content: v-bind('ImageContainer?.justifyContent || "center"') !important;
  ;
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
