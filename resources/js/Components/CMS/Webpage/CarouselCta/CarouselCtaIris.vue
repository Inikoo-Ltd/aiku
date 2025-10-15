<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { getStyles } from "@/Composables/styles"
import LinkIris from '@/Components/Iris/LinkIris.vue';


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
</script>

<template>
  <div id="carousel-cta">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType)
    }">
      <Carousel :value="fieldValue.carousel_data.cards" :numVisible="1" :numScroll="1"
        :autoplayInterval="fieldValue.carousel_data.carousel_setting.autoplay ? 3000 : null"
        :circular="fieldValue.carousel_data.carousel_setting.loop">
        <template #item="{ data, index }">
          <div class="relative bg-white" :style="{
            ...getStyles(fieldValue?.carousel_data?.cards[index]?.container?.properties, screenType)
          }">
            <div class="relative h-80 overflow-hidden bg-indigo-600 md:absolute md:left-0 md:h-full md:w-1/3 lg:w-1/2">
              <Image :src="data.image.source" :alt="data.image.alt" class="size-full object-cover" :imageCover="true" />
            </div>
            <div class="relative mx-auto max-w-7xl py-24 sm:py-32 lg:px-8 lg:py-40">
              <div class="pl-6 pr-6 md:ml-auto md:w-2/3 md:pl-16 lg:w-1/2 lg:pl-24 lg:pr-0 xl:pl-32">
                <div v-html="fieldValue.carousel_data.cards[index].text" />
                <div class="flex justify-center">
                  <LinkIris :type="fieldValue.carousel_data.cards[index].button.link.type"
                    :href="fieldValue.carousel_data.cards[index].button.link.href"
                    :canonical_url="fieldValue.carousel_data.cards[index].button.link.canonical_url"
                    :traget="fieldValue.carousel_data.cards[index].button.link.target">
                    <Button
                      :injectStyle="getStyles(fieldValue.carousel_data.cards[index].button.container?.properties, screenType)"
                      :label="fieldValue.carousel_data.cards[index]?.button?.text" />
                  </LinkIris>
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
#carousel-cta {
  .p-carousel-item {
    display: flex;
    justify-content: center;
  }
}

:deep(.p-carousel-indicator-list) {
  display: none;
}
</style>
