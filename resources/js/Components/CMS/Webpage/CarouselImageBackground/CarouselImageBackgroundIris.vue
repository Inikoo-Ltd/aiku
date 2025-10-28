<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import { inject, ref, computed } from 'vue'
import { getStyles } from '@/Composables/styles'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

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

const refreshTrigger = ref(0)
const layout: any = inject('layout', {})
const getHref = (data: any) => data?.link?.href


const hasCards = computed(() =>
  Array.isArray(props.fieldValue?.carousel_data?.cards) &&
  props.fieldValue.carousel_data.cards.length > 0
)

const slidesPerView = computed(() =>
  props.fieldValue?.carousel_data?.carousel_setting?.slidesPerView?.[props.screenType] || 1
)

const isLooping = computed(() => {
  const loop = props.fieldValue?.carousel_data?.carousel_setting?.loop || false
  return loop && props.fieldValue.carousel_data.cards.length > slidesPerView.value
})

const cardStyle = ref(getStyles(props.fieldValue?.carousel_data?.card_container?.properties, props.screenType, false))




const responsiveOptions = computed(() => {
  const settings = props.fieldValue?.carousel_data?.carousel_setting || {}
  return [
    { breakpoint: '1200px', numVisible: settings.slidesPerView?.desktop || 4, numScroll: 1 },
    { breakpoint: '992px', numVisible: settings.slidesPerView?.tablet || 2, numScroll: 1 },
    { breakpoint: '576px', numVisible: settings.slidesPerView?.mobile || 1, numScroll: 1 }
  ]
})
</script>

<template>
  <div id="carousel-background-image" class="relative w-full">
    <!-- Carousel -->
    <div :data-refresh="refreshTrigger" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(fieldValue?.container?.properties, props.screenType)
    }">
      <Carousel v-if="hasCards" :value="fieldValue.carousel_data.cards" :numVisible="slidesPerView"
        :circular="isLooping" :autoplayInterval="0" :responsiveOptions="responsiveOptions" class="w-full">
        <template #item="{ data }">
          <!-- WRAPPER: This adds gap safely -->
          <div class="px-1 md:px-1 lg:px-1">
            <component :is="getHref(data) ? LinkIris : 'div'" :canonical_url="data?.link?.canonical_url"
              :href="data?.link?.href" :target="data?.link?.target"
              class="card relative isolate flex flex-col justify-end overflow-hidden rounded-2xl hover:shadow-xl transition-all duration-300">
              <Image :src="data?.image?.source" :alt="data?.image?.alt" :imageCover="true"
                class="absolute inset-0 -z-10 w-full h-full object-cover" />
              <div class="absolute inset-0 flex flex-col justify-start items-start p-6">
                <div v-html="data.text" class="mb-4"></div>
                <div v-if="fieldValue?.carousel_data?.carousel_setting.button">
                  <LinkIris :href="data?.button?.link?.href" :canonical_url="data?.button?.link?.canonical_url"
                    :target="data?.button?.link?.taget" typeof="button" :type="data?.button?.link?.type">
                    <Button :injectStyle="getStyles(data?.button?.container?.properties, screenType)"
                      :label="data?.button?.text" />
                  </LinkIris>
                </div>
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

  border-top-left-radius: v-bind('cardStyle?.borderTopLeftRadius || null') !important;
  border-top-right-radius: v-bind('cardStyle?.borderTopRightRadius || null') !important;
  border-bottom-left-radius: v-bind('cardStyle?.borderBottomLeftRadius || null') !important;
  border-bottom-right-radius: v-bind('cardStyle?.borderBottomRightRadius || null') !important;

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
