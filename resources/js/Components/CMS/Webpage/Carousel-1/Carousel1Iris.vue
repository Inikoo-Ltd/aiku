<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'
import { inject, ref, computed, watch, nextTick } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fal'
import { getStyles } from '@/Composables/styles'
import LinkIris from '@/Components/Iris/LinkIris.vue'
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


const layout: any = inject("layout", {})

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

const screenType = computed(() => props.screenType)
const cardStyle = ref(getStyles(props.fieldValue?.carousel_data?.card_container?.properties, props.screenType, false))
const ImageContainer = ref(getStyles(props.fieldValue.carousel_data.card_container?.container_image, props.screenType, false))

// Auto select aspect ratio based on slidesPerView
const selectedAspectRatio = computed(() => {
  if (!hasCards.value) return 1
  if (slidesPerView.value === 1) return 1
  if (slidesPerView.value <= 3) return 4 / 3
  return 16 / 9
})


const responsiveOptions = computed(() => {
  const settings = props.fieldValue?.carousel_data?.carousel_setting || {}
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

const refreshTrigger = ref(0)

const refreshCarousel = async (delay = 100) => {
  await new Promise(resolve => setTimeout(resolve, delay))
  refreshTrigger.value++
  await nextTick()
}

const spaceBetween = ref(((props.fieldValue?.carousel_data?.carousel_setting?.spaceBetween || 0) / 2) + 'px')
watch(
  () => props.fieldValue?.carousel_data?.carousel_setting?.spaceBetween,
  (newVal) => {
    spaceBetween.value = ((newVal || 0) / 2) + 'px'
    refreshCarousel()
  },
  { immediate: true, deep: true }
)

// Watch for settings or screen changes
watch(
  () => [props.fieldValue?.carousel_data?.carousel_setting, props.screenType],
  () => refreshCarousel(),
  { deep: true }
)

// Watch for card container property changes
watch(
  () => props.fieldValue?.carousel_data?.card_container,
  async () => {
    cardStyle.value = getStyles(props.fieldValue?.carousel_data?.card_container?.properties, props.screenType, false)
    ImageContainer.value = getStyles(props.fieldValue.carousel_data.card_container?.container_image, props.screenType, false)
    await refreshCarousel(200)
  },
  { deep: true }
)

const idxSlideLoading = ref<number | null>(null)

</script>

<template>
  <div id="carousel" class="relative">
    <div :data-refresh="refreshTrigger" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(fieldValue?.container?.properties, props.screenType)
    }">
      <Carousel
        v-show="hasCards"
        :value="fieldValue.carousel_data.cards"
        :numVisible="slidesPerView"
        :circular="isLooping"
        :numScroll="1"
        :autoplayInterval="fieldValue?.carousel_data?.carousel_setting?.autoplay ? 5000 : 0"
        :responsiveOptions="responsiveOptions"
        :showNavigators="fieldValue?.carousel_data?.cards?.length > slidesPerView"
        class="w-full">
        <template #item="{ data, index }" :showNavigators="false" :showIndicators="false">
          <div class="space-card ">
             <div class="card flex flex-col h-full ">
                <component :is="data?.link?.href != '/' ? LinkIris : 'div'" :canonical_url="data?.link?.canonical_url"
                  :href="data?.link?.href" :target="data?.link?.target" class="relative flex flex-1 flex-col" :type="data?.link?.type"
                  @start="() => idxSlideLoading = index"
                  @finish="() => idxSlideLoading = null"
                >
                  <!-- Image Container -->
                  <div class="flex justify-center overflow-visible"
                    :style="getStyles(fieldValue.carousel_data.card_container?.container_image, screenType)" >
                    <div class="overflow-hidden w-full flex items-center justify-center "
                      :style="{  ...getStyles(fieldValue.carousel_data.card_container?.image_properties, screenType) }">
                      <Image v-if="data?.image?.source" :src="data.image.source" :alt="data.image.alt || `image-${index}`"
                        :class="'image-container'" class="w-full h-full flex justify-center items-center" />
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
        </template>
      </Carousel>
    </div>
  </div>
</template>

<style scoped>



:deep(.p-carousel-items-container) {
  align-items: stretch !important;
}

:deep(.p-carousel-indicator-list) {
  display: none;
}

:deep(.space-card) {
  margin-left: v-bind(spaceBetween);
  margin-right: v-bind(spaceBetween);
}

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

.image-container {
  justify-content: v-bind('ImageContainer?.justifyContent || "center"') !important;;
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

