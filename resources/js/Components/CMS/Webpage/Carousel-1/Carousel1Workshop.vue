<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'
import { inject, ref, watch, computed, nextTick } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fal'
import { getStyles } from '@/Composables/styles'
import { faTimes } from '@fal'

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{ (e: 'autoSave'): void }>()

const keySwiper = ref(ulid())
const layout: any = inject("layout", {})
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

const refreshCarousel = async (delay = 100) => {
  await new Promise(resolve => setTimeout(resolve, delay))
  keySwiper.value = ulid()
  await nextTick()
}

// Watch for any carousel setting or screen change
watch(
  () => [props.modelValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

// Watch for card container property change (style update)
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
    <!-- Loading overlay -->
    <div  :key="keySwiper" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(modelValue?.container?.properties, props.screenType)
    }">
      <Carousel v-if="hasCards" :value="modelValue.carousel_data.cards" :numVisible="slidesPerView"
        :circular="isLooping" :autoplayInterval="0" :responsiveOptions="responsiveOptions" class="w-full">
        <template #item="{ data, index }" :showNavigators="false" :showIndicators="false">
          <div class="card flex flex-col h-full">
            <component :is="'div'" class="flex flex-1 flex-col">
              <!-- Image Container -->
              <div class="flex justify-center overflow-visible"
                :style="getStyles(modelValue.carousel_data.card_container?.container_image, screenType)">
                <div class="overflow-hidden w-full flex items-center justify-center h-[185px]">
                  <Image v-if="data?.image?.source" :src="data.image.source" :alt="data.image.alt || `image-${index}`"
                     :style="getStyles(modelValue.carousel_data.card_container?.container_image, screenType)" />
                  <div v-else class="flex items-center justify-center w-full h-full bg-gray-100">
                    <FontAwesomeIcon :icon="faImage" class="text-gray-400 text-4xl" />
                  </div>
                </div>
              </div>

              <!-- Text Content -->
              <div v-if="modelValue.carousel_data.carousel_setting?.use_text"
                class="p-4 flex flex-col flex-1 justify-between">
                <div v-html="data.text" class="text-center leading-relaxed" />
              </div>
            </component>

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

  /* Padding */
  padding-top: v-bind('cardStyle?.paddingTop || "0px"') !important;
  padding-right: v-bind('cardStyle?.paddingRight || "0px"') !important;
  padding-bottom: v-bind('cardStyle?.paddingBottom || "0px"') !important;
  padding-left: v-bind('cardStyle?.paddingLeft || "0px"') !important;

  /* Margin */
  margin-top: v-bind('cardStyle?.marginTop || "0px"') !important;
  margin-right: v-bind('cardStyle?.marginRight || "0px"') !important;
  margin-bottom: v-bind('cardStyle?.marginBottom || "0px"') !important;
  margin-left: v-bind('cardStyle?.marginLeft || "0px"') !important;

  /* Border radius */
  border-top-left-radius: v-bind('cardStyle?.borderTopLeftRadius || "0px"') !important;
  border-top-right-radius: v-bind('cardStyle?.borderTopRightRadius || "0px"') !important;
  border-bottom-left-radius: v-bind('cardStyle?.borderBottomLeftRadius || "0px"') !important;
  border-bottom-right-radius: v-bind('cardStyle?.borderBottomRightRadius || "0px"') !important;

  /* Border sides individually */
  border-top: v-bind('cardStyle?.borderTop || "0px solid transparent"') !important;
  border-bottom: v-bind('cardStyle?.borderBottom || "0px solid transparent"') !important;
  border-left: v-bind('cardStyle?.borderLeft || "0px solid transparent"') !important;
  border-right: v-bind('cardStyle?.borderRight || "0px solid transparent"') !important;
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
