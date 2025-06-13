<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { faPencil } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay, Pagination, Navigation } from 'swiper/modules'
import { resolveResponsiveValue } from "@/Composables/Workshop"
import 'swiper/css'
import 'swiper/css/autoplay'


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

interface ModelValue {
  value: LayoutData
  mobile?: { type?: string }
  container?: { properties?: Record<string, any> }
}

const props = defineProps<{
  modelValue: ModelValue
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const getHref = (index: number) => {
  const image = props.modelValue?.value?.images?.[index]
  return image?.link_data?.url || image?.link_data?.workshop_url || ''
}

const getColumnWidthClass = (layoutType: string, index: number) => {
  const layout = props.modelValue?.value?.layout_type || {}
  const hasMobile = !!layout.mobile
  const hasTablet = !!layout.tablet

  switch (layoutType) {
    case "12":
      return [
        hasMobile ? "w-1/2" : "sm:w-1/2",
        hasTablet ? "" : index === 0 ? "md:w-1/3" : "md:w-2/3"
      ].filter(Boolean).join(" ")

    case "21":
      return [
        hasMobile ? "w-1/2" : "sm:w-1/2",
        hasTablet ? "" : index === 0 ? "md:w-2/3" : "md:w-1/3"
      ].filter(Boolean).join(" ")

    case "13":
      return hasTablet ? "w-full" : index === 0 ? "md:w-1/4" : "md:w-3/4"

    case "31":
      return [
        hasMobile ? "w-1/2" : "sm:w-1/2",
        hasTablet ? "" : index === 0 ? "md:w-3/4" : "md:w-1/4"
      ].filter(Boolean).join(" ")

    case "211":
      return hasTablet ? "w-full" : index === 0 ? "md:w-1/2" : "md:w-1/4"

    case "2":
      return hasMobile ? "w-1/2" : hasTablet ? "w-1/2" : "md:w-1/2"

    case "3":
      return hasTablet ? "w-full" : "md:w-1/3"

    case "4":
      return hasTablet ? "w-full" : "md:w-1/4"

    case "6":
      return hasTablet ? "w-full" : "md:w-1/6"

    default:
      return "w-full"
  }
}


/* const getImageSlots = (layoutType: string) => {
  switch (layoutType) {
    case "4": return 4
    case "3":
    case "211": return 3
    case "2":
    case "12":
    case "21":
    case "13":
    case "31": return 2
    default: return 1
  }
} */

const getVal = (base: any, path?: string[]) =>{
      return  resolveResponsiveValue(base, props.screenType, path);
}

</script>
<template>
  <section :style="getStyles(modelValue?.container?.properties, screenType)" aria-label="Image Gallery Section">
    <!-- Mobile Carousel -->
    <Swiper v-if="screenType === 'mobile' && modelValue?.mobile?.type === 'carousel'" :slides-per-view="1" :loop="true"
      :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay, Pagination]" class="w-full"
      :style="getStyles(modelValue?.value?.layout?.properties, screenType)">
      <SwiperSlide v-for="(image, index) in modelValue?.value?.images" :key="index" class="w-full">
        <a v-if="getHref(index)" :href="getHref(index)" target="_blank" rel="noopener noreferrer"
          class="block w-full h-full">
          <Image :src="image?.source" :alt="image?.properties?.alt || `image ${index + 1}`" :imageCover="true" :style="{
            ...getStyles(modelValue?.value?.layout?.properties, screenType),
            ...getStyles(image?.properties, screenType)
          }" :imgAttributes="{ ...image?.attributes, loading: 'lazy' }" />
        </a>
        <div v-else class="block w-full h-full">
          <Image :src="image?.source" :alt="image?.properties?.alt || `image ${index + 1}`" :imageCover="true" :style="{
            ...getStyles(modelValue?.value?.layout?.properties, screenType),
            ...getStyles(image?.properties, screenType)
          }" :imgAttributes="{ ...image?.attributes, loading: 'lazy' }" />
        </div>
      </SwiperSlide>
    </Swiper>

   
    <!-- Desktop/Tablet Grid -->
    <div v-else class="flex flex-wrap">
      <div v-for="index in modelValue?.value?.images?.length"
        :key="`${index}-${modelValue?.value?.images?.[index - 1]?.source}`"
           class="flex flex-col group relative  hover:bg-white/40 h-full"
        :class="getColumnWidthClass(getVal(modelValue?.value.layout_type), index - 1)">
        <template v-if="modelValue?.value?.images?.[index - 1]?.source">
          <a v-if="getHref(index - 1)" :href="getHref(index - 1)" target="_blank" rel="noopener noreferrer"
            class="block w-full h-full">
            <Image :src="modelValue?.value?.images?.[index - 1]?.source"
              :alt="modelValue?.value?.images?.[index - 1]?.properties?.alt || `image ${index}`" :imageCover="true"
              class="w-full h-full aspect-square object-cover rounded-lg" :style="{
                ...getStyles(modelValue?.value?.layout?.properties, screenType),
                ...getStyles(modelValue?.value?.images?.[index - 1]?.properties, screenType)
              }" :imgAttributes="{ ...modelValue?.value?.images?.[index - 1]?.attributes, loading: 'lazy' }" />
            <div class="flex justify-center">
              <div v-if="modelValue?.value?.caption?.use_caption">
                <span v-if="modelValue?.value?.images?.[index - 1]?.caption" :style="getStyles(modelValue?.value?.caption?.properties, screenType)">{{modelValue?.value?.images?.[index - 1]?.caption}}</span>
                <span v-else class="text-gray-300 font-semibold">No caption</span>
              </div>
           
            </div>
          </a>
          <div v-else class="block w-full h-full">
            <Image :src="modelValue?.value?.images?.[index - 1]?.source"
              :alt="modelValue?.value?.images?.[index - 1]?.properties?.alt || `image ${index}`" :imageCover="true"
              class="w-full h-full aspect-square object-cover rounded-lg" :style="{
                ...getStyles(modelValue?.value?.layout?.properties, screenType),
                ...getStyles(modelValue?.value?.images?.[index - 1]?.properties, screenType)
              }" :imgAttributes="{ ...modelValue?.value?.images?.[index - 1]?.attributes, loading: 'lazy' }" />
            <div class="flex justify-center">
              <div v-if="modelValue?.value?.caption?.use_caption">
                <span v-if="modelValue?.value?.images?.[index - 1]?.caption" :style="getStyles(modelValue?.value?.caption?.properties, screenType)">{{modelValue?.value?.images?.[index - 1]?.caption}}</span>
                <span v-else class="text-gray-300 font-semibold">No caption</span>
              </div>
           
            </div>
          </div>

        </template>

        <!-- Empty placeholder -->
        <div v-else>
          <div
            class="flex items-center justify-center w-full h-32 bg-gray-200 rounded-lg aspect-square transition-all duration-300 hover:bg-gray-300 hover:shadow-lg hover:scale-105 cursor-pointer"
            :style="{
              ...getStyles(modelValue?.value?.layout?.properties, screenType),
              ...getStyles(modelValue?.value?.images?.[index - 1]?.properties, screenType)
            }">
            <font-awesome-icon :icon="['fas', 'image']"
              class="text-gray-500 text-4xl transition-colors duration-300 group-hover:text-gray-700" />
          </div>
          <div class="flex justify-center">
            <div v-if="modelValue?.value?.caption?.use_caption">
                <span v-if="modelValue?.value?.images?.[index - 1]?.caption" :style="getStyles(modelValue?.value?.caption?.properties, screenType)">{{modelValue?.value?.images?.[index - 1]?.caption}}</span>
                <span v-else class="text-gray-300 font-semibold">No caption</span>
              </div>
          </div>
        </div>


      </div>
    </div>
  </section>
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
