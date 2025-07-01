<script setup lang="ts">
import { faCube, faStar, faImage, faPencil } from "@fortawesome/free-solid-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Swiper, SwiperSlide } from "swiper/vue"
import { Autoplay, Pagination } from "swiper/modules"
import { inject } from "vue"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { resolveResponsiveValue } from "@/Composables/Workshop"
import Blueprint from './Blueprint'

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
  caption?: string
}

interface LayoutData {
  layout_type: string
  properties?: Record<string, any>
  images?: ImageData[]
  caption?: { use_caption: boolean, properties?: Record<string, any> }
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

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

const getHref = (image?: ImageData) => {
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


const getVal = (base: any, path?: string[]) => {
  return resolveResponsiveValue(base, props.screenType, path)
}
</script>

<template>
  <div id="image">
    <section
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width : 'auto'
      }"
      aria-label="Image Gallery Section"
    >
      <!-- MOBILE: Swiper -->
      <Swiper
        v-if="screenType === 'mobile' && modelValue?.mobile?.type === 'carousel'"
        :slides-per-view="1"
        :loop="true"
        :autoplay="false"
        :pagination="{ clickable: true }"
        :modules="[Autoplay, Pagination]"
        class="w-full"
      >
        <SwiperSlide
          v-for="(image, index) in modelValue?.value?.images || []"
          :key="index"
          class="w-full"
        >
          <component
            :is="getHref(image) ? 'a' : 'div'"
            :href="getHref(image) || undefined"
            target="_blank"
            rel="noopener noreferrer"
            class="block w-full h-full"
          >
            <Image
              :src="image.source"
              :alt="image.properties?.alt || `image ${index + 1}`"
              :imageCover="true"
              :style="{
                ...getStyles(modelValue.value.layout?.properties, screenType),
                ...getStyles(image.properties, screenType)
              }"
              :imgAttributes="{ ...image.attributes, loading: 'lazy' }"
            />
          </component>
        </SwiperSlide>
      </Swiper>

      <!-- DESKTOP/TABLET GRID -->
      <div v-else class="flex flex-wrap">
        <div
          v-for="(image, index) in modelValue?.value?.images || []"
          :key="index"
          class="group relative hover:bg-white/40 flex flex-col h-full"
          :class="getColumnWidthClass(getVal(modelValue.value.layout_type), index)"
        >
          <component
            :is="getHref(image) ? 'a' : 'div'"
            :href="getHref(image) || undefined"
            target="_blank"
            rel="noopener noreferrer"
            class="block w-full h-full"
          >
            <Image
              v-if="image?.source"
              :src="image.source"
              :alt="image.properties?.alt || `image ${index + 1}`"
              :imageCover="true"
              class="w-full h-full aspect-square object-cover rounded-lg"
              :style="{
                ...getStyles(modelValue.value.layout?.properties, screenType),
                ...getStyles(image.properties, screenType)
              }"
              :imgAttributes="{ ...image.attributes, loading: 'lazy' }"
            />
            <div
              v-else
              class="flex items-center justify-center w-full h-32 bg-gray-200 rounded-lg aspect-square transition-all duration-300 hover:bg-gray-300 hover:shadow-lg hover:scale-105 cursor-pointer"
            >
              <font-awesome-icon
                :icon="['fas', 'image']"
                class="text-gray-500 text-4xl group-hover:text-gray-700"
              />
            </div>
          </component>

          <div class="flex justify-center mt-2">
            <div v-if="modelValue.value.caption?.use_caption">
              <span
                v-if="image.caption"
                :style="getStyles(modelValue.value.caption?.properties, screenType)"
              >
                {{ image.caption }}
              </span>
              <span v-else class="text-gray-300 font-semibold">No caption</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped lang="scss">
:deep(.swiper-pagination-bullet) {
  background-color: #d1d5db !important;
  opacity: 1;
  transition: background-color 0.3s ease;
}

:deep(.swiper-pagination-bullet-active) {
  background: #4b5563 !important;
}
</style>
