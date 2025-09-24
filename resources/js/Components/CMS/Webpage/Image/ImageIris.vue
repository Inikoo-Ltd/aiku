<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { faPencil } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay, Pagination } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/autoplay'
import { resolveResponsiveValue } from "@/Composables/Workshop"
import { inject } from "vue"
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"


library.add(faCube, faStar, faImage, faPencil)

interface LinkData {
  url?: string
  workshop_url?: string
}

interface ImageData {
  source: string
  properties?: Record<string, any>
  attributes?: Record<string, any>
  link_data?: LinkData
}

interface LayoutData {
  layout_type: string
  properties?: Record<string, any>
  images?: ImageData[]
}

interface fieldValue {
  value: LayoutData
  mobile?: { type?: string }
  container?: { properties?: Record<string, any> }
}

const props = defineProps<{
  fieldValue: fieldValue
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()


const layout = inject('layout', {})


const getHref = (index: number) => {
  const image = props.fieldValue?.value?.images?.[index]
  return image?.link_data?.url || image?.link_data?.workshop_url || ''
}

const getHrefFromImageData = (image: {}) => {
  return image?.link_data?.url || ''
}

const getTarget = (index: number) => {
  const image = props.fieldValue?.value?.images?.[index]
  return image?.link_data?.target || '_blank'
}


const getGridTemplate = (layoutType: string) => {
  switch (layoutType) {
    case "12": return { gridTemplateColumns: "repeat(2, 1fr)" }
    case "21": return { gridTemplateColumns: "repeat(2, 1fr)" }
    case "13": return { gridTemplateColumns: "1fr 3fr" }
    case "31": return { gridTemplateColumns: "3fr 1fr" }
    case "211": return { gridTemplateColumns: "2fr 1fr 1fr" }
    case "2": return { gridTemplateColumns: "repeat(2, 1fr)" }
    case "3": return { gridTemplateColumns: "repeat(3, 1fr)" }
    case "4": return { gridTemplateColumns: "repeat(4, 1fr)" }
    case "6": return { gridTemplateColumns: "repeat(6, 1fr)" }
    default: return { gridTemplateColumns: "1fr" }
  }
}


const getVal = (base: any, path?: string[]) => {
  return resolveResponsiveValue(base, props.screenType, path);
}


const resolvedGap = computed(() => {
  return ( props.fieldValue?.value?.gap?.[props.screenType || 'desktop'] || 0 ) + 'px'
})
</script>

<template>
  <div id="image_iris">
    <section :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType),
      width: 'auto'
    }" aria-label="Image Gallery Section">
      <!-- Mobile Carousel -->
      <Swiper v-if="screenType === 'mobile' && fieldValue?.mobile?.type === 'carousel'" :slides-per-view="1"
        :loop="true" :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay, Pagination]"
        class="w-full" :style="getStyles(fieldValue?.value?.layout?.properties, screenType)">
        <SwiperSlide v-for="(image, index) in fieldValue?.value?.images" :key="index" class="w-full">
            <component
                v-if="getHref(index)"
                :is="getHrefFromImageData(image)
                    ? image.link_data?.target === '_self' && image.link_data?.type === 'internal'
                        ? Link : 'a'
                    : 'div'"
                :href="getHrefFromImageData(image) || undefined"
                :target="image.link_data?.target"
                rel="noopener noreferrer"
                class="block w-full h-full"
            >
                <Image
                    :src="image?.source"
                    :alt="image?.properties?.alt || `image ${index + 1}`"
                    :imageCover="true"
                    :style="{
                        ...getStyles(fieldValue?.value?.layout?.properties, screenType),
                        ...getStyles(image?.properties, screenType)
                    }"
                    :imgAttributes="{ ...image?.attributes, loading: 'lazy' }"
                />
            </component>
          <div v-else class="block w-full h-full">
            <Image :src="image?.source" :alt="image?.properties?.alt || `image ${index + 1}`" :imageCover="true" :style="{
              ...getStyles(fieldValue?.value?.layout?.properties, screenType),
              ...getStyles(image?.properties, screenType)
            }" :imgAttributes="{ ...image?.attributes, loading: 'lazy' }" />
          </div>
        </SwiperSlide>
      </Swiper>

      <!-- Desktop/Tablet Grid -->
     <!--  <div v-else class="flex  flex-wrap" :style="{
        gap: resolvedGap
      }">
        <div v-for="index in fieldValue?.value?.images?.length"
          :key="`${index}-${fieldValue?.value?.images?.[index - 1]?.source}`"
          class="flex flex-col group relative  hover:bg-white/40 h-full"
          :class="getColumnWidthClass(getVal(fieldValue?.value.layout_type), index - 1)">
          <template v-if="fieldValue?.value?.images?.[index - 1]?.source">

            <a v-if="getHref(index - 1)" :href="getHref(index - 1)" :target="getTarget(index - 1)"
              rel="noopener noreferrer" class="block w-full h-full">
              <Image :src="fieldValue?.value?.images?.[index - 1]?.source"
                :alt="fieldValue?.value?.images?.[index - 1]?.properties?.alt || `image ${index}`" :imageCover="true"
                class="w-full h-full aspect-square object-cover rounded-lg" :style="{
                  ...getStyles(fieldValue?.value?.layout?.properties, screenType),
                  ...getStyles(fieldValue?.value?.images?.[index - 1]?.properties, screenType)
                }" :imgAttributes="{ ...fieldValue?.value?.images?.[index - 1]?.attributes, loading: 'lazy' }" />
            </a>

            <div v-else class="block w-full h-full">
              <Image :src="fieldValue?.value?.images?.[index - 1]?.source"
                :alt="fieldValue?.value?.images?.[index - 1]?.properties?.alt || `image ${index}`" :imageCover="true"
                class="w-full h-full aspect-square object-cover rounded-lg" :style="{
                  ...getStyles(fieldValue?.value?.layout?.properties, screenType),
                  ...getStyles(fieldValue?.value?.images?.[index - 1]?.properties, screenType),
                }" :imgAttributes="{ ...fieldValue?.value?.images?.[index - 1]?.attributes, loading: 'lazy' }" />
            </div>

            <div v-if="fieldValue?.value?.caption?.use_caption" class="flex justify-center">
              <span v-if="fieldValue?.value?.images?.[index - 1]?.caption"
                :style="getStyles(fieldValue?.value?.caption?.properties, screenType)">{{
                fieldValue?.value?.images?.[index
                - 1]?.caption}}</span>
              <span v-else class="text-gray-300 font-semibold">No caption</span>

            </div>
          </template>
        </div>
      </div> -->
    
    
    
      <div v-else class="grid w-full" :style="{
        gap: resolvedGap,
        ...getGridTemplate(getVal(fieldValue.value.layout_type))
      }">
        <div v-for="(image, index) in fieldValue?.value?.images || []" :key="index"
          class="group relative hover:bg-white/40 flex flex-col h-full">
          <component
            :is="getHrefFromImageData(image) ? image.link_data?.target === '_self' && image.link_data.type === 'internal' ? Link : 'a' : 'div'"
            :href="getHrefFromImageData(image) || undefined"
            :target="image.link_data?.target"
            rel="noopener noreferrer"
            class="block w-full h-full"
          >
            <Image v-if="image?.source" :src="image.source" :alt="image.properties?.alt || `image ${index + 1}`"
              :imageCover="true" class="w-full h-full aspect-square object-cover rounded-lg" :style="{
                ...getStyles(fieldValue.value.layout?.properties, screenType),
                ...getStyles(image.properties, screenType)
              }" :imgAttributes="{ ...image.attributes, loading: 'lazy' }" />
            <div v-else
              class="flex items-center justify-center w-full h-32 bg-gray-200 rounded-lg aspect-square transition-all duration-300 hover:bg-gray-300 hover:shadow-lg hover:scale-105 cursor-pointer">
              <FontAwesomeIcon :icon="['fas', 'image']" class="text-gray-500 text-4xl group-hover:text-gray-700" />
            </div>
          </component>

          <div class="flex justify-center mt-2">
            <div v-if="fieldValue.value.caption?.use_caption">
              <span v-if="image.caption" :style="getStyles(fieldValue.value.caption?.properties, screenType)">
                {{ image.caption }}
              </span>
              <span v-else class="text-gray-300 font-semibold">{{ trans("No caption") }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped lang="scss">
:deep(.swiper-pagination-bullet) {
  background-color: #d1d5db !important; // Tailwind's gray-300
  opacity: 1;
  transition: background-color 0.3s ease;
}


:deep(.swiper-pagination-bullet-active) {
  background: #4b5563 !important;
}
</style>
