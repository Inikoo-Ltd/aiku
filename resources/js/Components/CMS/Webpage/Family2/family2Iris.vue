<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, ref } from "vue"
import { get, isPlainObject } from "lodash-es"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"
import { getBestOffer } from "@/Composables/useOffers"

import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation, Pagination } from "swiper/modules"

import "swiper/css"
import "swiper/css/navigation"
import "swiper/css/pagination"

library.add(
  faCube,
  faLink,
  faInfoCircle,
  faStar,
  faCircle,
  faBadgePercent,
  faChevronCircleLeft,
  faChevronCircleRight
)

const props = defineProps<{
  fieldValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})

const showExtra = ref(false)

const toggleShowExtra = () => {
  showExtra.value = !showExtra.value
}

const bestOffer = computed(() => {
  return getBestOffer(props.fieldValue?.family?.offers_data)
})

const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const columnPosition = computed(() => {
  const rawVal = get(props.fieldValue, ["column_position"])

  if (!isPlainObject(rawVal)) return rawVal

  const view = props.screenType
  return rawVal?.[view] ?? rawVal?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")

const gridClass = computed(() =>
  isImageLeft.value
    ? "md:grid-cols-[40%_60%]"
    : "md:grid-cols-[60%_40%]"
)

const imageOrder = computed(() =>
  isImageLeft.value ? "order-1" : "order-2"
)

const textOrder = computed(() =>
  isImageLeft.value ? "order-2" : "order-1"
)

const images = computed(() => {
  const src = props.fieldValue?.family?.web_images?.all
  if (!src) return []
  return Array.isArray(src) ? src : [src]
})
</script>

<template>
  <div :id="fieldValue?.id || 'family-2'" component="family-2" class="w-full">

    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue?.container?.properties, screenType)
    }">

      <div class="grid w-full min-h-[250px] md:min-h-[400px] grid-cols-1" :class="gridClass">

        <!-- IMAGE -->
        <div
          class="relative w-full overflow-hidden"
          :class="[imageOrder, images.length ? 'h-[250px] sm:h-[300px] md:h-[400px]' : '']"
          :style="getStyles(fieldValue?.image?.container?.properties, screenType)"
        >

          <!-- CUSTOM NAVIGATION -->
          <div
            v-if="images.length > 1"
            class="swiper-btn-prev absolute left-3 top-1/2 -translate-y-1/2 z-10 cursor-pointer"
          >
            <FontAwesomeIcon icon="far fa-chevron-circle-left" class="text-white text-3xl"/>
          </div>

          <div
            v-if="images.length > 1"
            class="swiper-btn-next absolute right-3 top-1/2 -translate-y-1/2 z-10 cursor-pointer"
          >
            <FontAwesomeIcon icon="far fa-chevron-circle-right" class="text-white text-3xl"/>
          </div>

          <!-- SWIPER -->
          <Swiper
            v-if="images.length > 1"
            :modules="[Navigation]"
            :slides-per-view="1"
            :loop="true"
            :navigation="{
              prevEl: '.swiper-btn-prev',
              nextEl: '.swiper-btn-next'
            }"
            :pagination="{ clickable: true }"
            class="w-full h-full"
          >
            <SwiperSlide v-for="(img, i) in images" :key="i">
              <div class="w-full h-full">
                <Image
                  :src="img.original"
                  :alt="fieldValue?.image?.alt || 'Image preview'"
                  :imgAttributes="fieldValue?.image?.attributes"
                  :imageCover="true"
                  class="w-full h-full object-fill"
                />
              </div>
            </SwiperSlide>
          </Swiper>

          <!-- SINGLE IMAGE -->
          <component
            v-else
            :is="fieldValue?.image?.link?.href ? LinkIris : 'div'"
            :href="fieldValue?.image?.link?.href"
            :target="fieldValue?.image?.link?.target"
            :type="fieldValue?.image?.link?.type"
            class="absolute inset-0"
          >
            <Image
              :src="images[0]?.original"
              :alt="fieldValue?.image?.alt || 'Image preview'"
              :imgAttributes="fieldValue?.image?.attributes"
              :imageCover="true"
              class="w-full h-full object-fill"
            />
          </component>

        </div>

        <!-- TEXT -->
        <div
          class="flex flex-col justify-center m-auto"
          :class="textOrder"
          :style="getStyles(fieldValue?.text_block?.properties, screenType)"
        >

          <div class="w-full max-w-xl">

            <h1
              v-if="fieldValue.family.name"
              class="!text-[1.8rem] font-semibold"
            >
              {{ fieldValue.family.name }}
            </h1>

            <div v-html="cleanedDescription"></div>

            <div class="flex justify-start mt-6">

              <LinkIris
                :href="fieldValue?.button?.link?.href"
                :canonical_url="fieldValue?.button?.link?.canonical_url"
                :target="fieldValue?.button?.link?.target"
                :type="fieldValue?.button?.link?.type"
              >
                <Button
                  :label="fieldValue?.button?.text"
                  :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
                />
              </LinkIris>

            </div>

          </div>

        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.swiper-btn-prev,
.swiper-btn-next {
  opacity: 0.85;
  transition: opacity 0.2s ease;
}

.swiper-btn-prev:hover,
.swiper-btn-next:hover {
  opacity: 1;
}
</style>