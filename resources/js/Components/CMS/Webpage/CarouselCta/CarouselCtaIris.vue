<script setup lang="ts">
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { getStyles } from "@/Composables/styles"
import LinkIris from '@/Components/Iris/LinkIris.vue';
import { inject } from 'vue'


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


          <div :style="{
            ...getStyles(data.container?.properties, screenType),
          }">
            <div class="grid grid-cols-1 md:grid-cols-2 w-full  min-h-[250px] md:min-h-[400px]">
              <div class="relative w-full cursor-pointer overflow-hidden"
                  :style="getStyles(fieldValue?.image?.container?.properties, screenType)">
                <Image :src="data.image.source" :imageCover="true" :alt="data.image.alt || 'Image preview'"
                  :imgAttributes="data.image.attributes" class="absolute inset-0 w-full h-full object-cover"
                   />
              </div>

              <div class="flex flex-col justify-center m-auto p-4"
                :style="getStyles(data?.text_block?.properties, screenType)">
                <div class="max-w-xl w-full">
                  <div v-html="fieldValue.carousel_data.cards[index].text" />
                  <div class="flex justify-center mt-6">
                    <LinkIris :type="fieldValue.carousel_data.cards[index].button.link.type"
                      :href="fieldValue.carousel_data.cards[index].button.link.href"
                      :canonical_url="fieldValue.carousel_data.cards[index].button.link.canonical_url"
                      :target="fieldValue.carousel_data.cards[index].button.link.target">
                      <Button
                        :injectStyle="getStyles(fieldValue.carousel_data.cards[index].button.container?.properties, screenType)"
                        :label="fieldValue.carousel_data.cards[index]?.button?.text" />
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
