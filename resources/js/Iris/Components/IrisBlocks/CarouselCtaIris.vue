<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from "@common/Components/Image.vue"
import Button from "@iris/Components/IrisButton.vue"
import { getStyles } from "@/Composables/styles"
import LinkIris from '@/Iris/Components/LinkIris.vue';
import { computed, inject, onMounted, ref } from 'vue'


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

const isCarouselMounted = ref(false)
onMounted(() => {
  isCarouselMounted.value = true
})

const isLooping = computed(() => {
  const settingsLoop = props.fieldValue?.carousel_data?.carousel_setting?.loop || false
  return settingsLoop && (props.fieldValue?.carousel_data?.cards?.length || 0) > 1
})
</script>

<template>
  <div :id="fieldValue?.id ? fieldValue?.id : 'carousel-cta' + indexBlock" component="carousel-cta"
    :class="{ 'carousel-cta-pending': !isCarouselMounted }">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType)
    }">
      <Carousel :value="fieldValue.carousel_data.cards" :numVisible="1" :numScroll="1"
        :autoplayInterval="fieldValue.carousel_data.carousel_setting.autoplay ? 3000 : null"
        :circular="isLooping">
        <template #item="{ data, index }">


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

        </template>
      </Carousel>
    </div>
  </div>
</template>
<style scoped>
[component="carousel-cta"] :deep(.p-carousel-viewport) {
  min-width: 0;
}

:deep(.p-carousel-indicator-list) {
  display: none;
}

@media (max-width: 639px) {
  [component="carousel-cta"] :deep(.p-carousel-content) {
    position: relative;
  }

  [component="carousel-cta"] :deep(.p-carousel-prev-button),
  [component="carousel-cta"] :deep(.p-carousel-next-button) {
    position: absolute;
    top: 50%;
    z-index: 2;
    margin: 0;
    transform: translateY(-50%);
  }

  [component="carousel-cta"] :deep(.p-carousel-prev-button) {
    left: 0.25rem;
  }

  [component="carousel-cta"] :deep(.p-carousel-next-button) {
    right: 0.25rem;
  }
}
</style>
