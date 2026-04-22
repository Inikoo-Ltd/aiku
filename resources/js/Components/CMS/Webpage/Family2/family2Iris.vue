<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject } from "vue"
import { get, isPlainObject } from "lodash-es"
import { ref, onMounted, nextTick } from "vue"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"
import { getBestOffer } from "@/Composables/useOffers"

import { faInfoCircle } from "@fal"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation } from "swiper/modules"

import "swiper/css"
import "swiper/css/navigation"

library.add(faInfoCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})

const showImage = computed(() => props.screenType !== "mobile")

const offersData = computed(() => props.fieldValue?.family?.offers_data)

const bestOffer = computed(() => getBestOffer(offersData.value))

const showTriggers = computed(() => {
  if (!bestOffer.value) return false
  return (
    !(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr) &&
    bestOffer.value.type === "Category Quantity Ordered Order Interval"
  )
})

const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const columnPosition = computed(() => {
  const raw = get(props.fieldValue, ["column_position"])
  if (!isPlainObject(raw)) return raw

  const view = props.screenType
  return raw?.[view] ?? raw?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")

const gridClass = computed(() =>
  isImageLeft.value ? "md:grid-cols-[40%_60%]" : "md:grid-cols-[60%_40%]"
)

const imageOrder = computed(() => (isImageLeft.value ? "order-1" : "order-2"))
const textOrder = computed(() => (isImageLeft.value ? "order-2" : "order-1"))
const isExpanded = ref(false)
const images = computed(() => {
  const src = props.fieldValue?.family?.web_images?.all
  if (!src) return []
  return Array.isArray(src) ? src : [src]
})
</script>

<template>
  <div :id="fieldValue?.id || 'family-2'" class="w-full">

    <div class="mx-auto w-full" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue?.container?.properties, screenType)
    }">
      <div class="grid w-full min-h-[250px] md:min-h-[400px] grid-cols-1" :class="gridClass">

        <!-- IMAGE -->
        <div v-if="showImage" class="relative w-full overflow-hidden aspect-[3/2]" :class="imageOrder"
          :style="getStyles(fieldValue?.image?.container?.properties, screenType)">

          <div v-if="images.length > 1" class="swiper-btn-prev nav-btn left-3">
            <FontAwesomeIcon icon="far fa-chevron-circle-left" class="text-gray-500 text-3xl" />
          </div>

          <div v-if="images.length > 1" class="swiper-btn-next nav-btn right-3">
            <FontAwesomeIcon icon="far fa-chevron-circle-right" class="text-gray-500 text-3xl" />
          </div>

          <Swiper v-if="images.length > 1" :modules="[Navigation]" :slides-per-view="1" :loop="true"
            :navigation="{ prevEl: '.swiper-btn-prev', nextEl: '.swiper-btn-next' }" class="w-full h-full">
            <SwiperSlide v-for="(img, i) in images" :key="i">
              <div class="img-wrapper">
                <Image :src="img.original" :alt="fieldValue?.image?.alt || 'Image preview'" :imgAttributes="{
                  ...fieldValue?.image?.attributes,
                  class: 'w-full h-full object-cover'
                }" />
              </div>
            </SwiperSlide>
          </Swiper>

          <div v-else class="absolute inset-0 overflow-hidden">
            <Image :src="images[0]?.original" :alt="fieldValue?.image?.alt || 'Image preview'" :imgAttributes="{
              ...fieldValue?.image?.attributes,
              class: 'w-full h-full object-cover'
            }" />
          </div>
        </div>

        <!-- TEXT -->
        <div class="text-container mx-4" :class="textOrder"
          :style="getStyles(fieldValue?.text_block?.properties, screenType)">

          <div class="content-wrapper">

            <!-- DISCOUNT -->
            <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"
              class="discount-wrapper">

              <div
                :class="bestOffer.type === 'Category Quantity Ordered Order Interval' ? 'flex gap-3' : 'discount-grid'">

                <DiscountByType v-if="showTriggers" :offers_data="fieldValue?.family?.offers_data"
                  template="triggers_labels" class="discount-item discount-span" />

                <DiscountByType :offers_data="fieldValue?.family?.offers_data" :template="bestOffer.type === 'Category Quantity Ordered Order Interval'
                  ? 'active-inactive-gr'
                  : 'max_discount'" class="discount-item" />
              </div>

            </div>

            <h1 v-if="fieldValue.family.name" class="title">
              {{ fieldValue.family.name }}
            </h1>

            <div class="relative w-full">
              <div class="overflow-hidden transition-all duration-300"
                :class="isExpanded ? 'max-h-none' : 'max-h-[160px]'">
                <div v-html="cleanedDescription"></div>
              </div>


              <!-- fade effect when collapsed -->
              <div v-if="!isExpanded"
                class="absolute bottom-0 left-0 w-full h-10 bg-gradient-to-t from-white to-transparent"></div>
            </div>

            <div class="mt-2">
              <button class="text-sm text-gray-800 hover:underline" @click="isExpanded = !isExpanded">
                {{ isExpanded ? 'Show less' : 'Show more' }}
              </button>
            </div>

            <div class="btn-wrapper">
              <LinkIris :href="fieldValue?.button?.link?.href" :canonical_url="fieldValue?.button?.link?.canonical_url"
                :target="fieldValue?.button?.link?.target" :type="fieldValue?.button?.link?.type">
                <Button :label="fieldValue?.button?.text"
                  :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)" />
              </LinkIris>
            </div>

          </div>

        </div>

      </div>

    </div>

  </div>
</template>

<style scoped>
/* swiper navigation */
.nav-btn {
  @apply absolute top-1/2 -translate-y-1/2 z-10 cursor-pointer opacity-80 transition;
}

.nav-btn:hover {
  @apply opacity-100;
}

/* image */
.img-wrapper {
  @apply w-full h-full;
}

/* .img-fit {
  @apply w-full h-full object-cover;
} */

/* text layout */
.text-container {
  @apply flex flex-col justify-start items-center md:items-start text-center md:text-left;
}

.content-wrapper {
  @apply w-full pt-4 md:pt-0;
}

.title {
  @apply text-[1.8rem] font-semibold;
}

.btn-wrapper {
  @apply flex justify-center md:justify-start mt-6;
}

/* discount layout */
.discount-wrapper {
  @apply w-full mt-4 2xl:mt-5;
}

.discount-grid {
  @apply grid grid-cols-3 gap-2 2xl:gap-3 items-center;
}

.discount-span {
  @apply col-span-2;
}

.discount-item {
  @apply min-w-0;
}

/* discount styles */
.discount-wrapper :deep(.offer-max-discount) {
  @apply bg-[#A80000] border border-red-900 text-gray-100 flex items-center rounded-sm px-1 py-0.5 sm:px-1.5 sm:py-1 md:px-2 md:py-1 text-xl;
}

.discount-span :deep(.percentage-text) {
  @apply text-xs md:text-xs 2xl:text-base;
}

.discount-wrapper :deep(.gr-content) {
  @apply w-full relative flex items-center text-xs md:text-xs 2xl:text-base;
}

.discount-wrapper :deep(.discount-percentage) {
  @apply flex items-center text-white font-bold text-center px-2 md:px-7 text-lg md:text-xs 2xl:text-sm max-w-fit;
}

.discount-wrapper :deep(.discount-title) {
  @apply whitespace-nowrap capitalize text-sm md:text-xxs 2xl:text-xs;
}

.discount-span :deep(.discount-triggers) {
  @apply text-xxs md:text-xs whitespace-pre-line;
}

.discount-wrapper :deep(.gr-logo) {
  height: 3em;
}
</style>