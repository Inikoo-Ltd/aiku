<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject } from "vue"
import { get, isPlainObject } from "lodash-es"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { getStyles } from "@/Composables/styles"

import Image from "@/Components/Image.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import { getBestOffer } from "@/Composables/useOffers"

import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation } from "swiper/modules"

import "swiper/css"
import "swiper/css/navigation"

library.add(faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: any
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
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
  return raw?.[props.screenType] ?? raw?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")

const imageOrder = computed(() => (isImageLeft.value ? "order-1" : "order-2"))
const textOrder = computed(() => (isImageLeft.value ? "order-2" : "order-1"))

const images = computed(() => {
  const data = props.fieldValue?.family?.description_image

  if (!data) return []

  return Object.values(data)
    .map((item: any) => item )
    .filter(Boolean)
})

console.log(props)
</script>

<template>
  <div class="w-full" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue?.container?.properties, screenType)
    }">

    <!-- 🔧 LIMIT WIDTH biar tidak melebar di 2xl -->
    <div class="mx-auto max-w-[2000px] w-full px-4 md:px-8 xl:px-12" >

      <div class="grid w-full grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">

        <!-- IMAGE -->
        <div
          v-if="showImage"
          :style="getStyles(fieldValue?.image?.container?.properties, screenType)"
          class="relative w-full overflow-hidden
                 aspect-[4/]
                 max-h-[500px] md:max-h-[550px] xl:max-h-[600px] 2xl:max-h-[650px]"
          :class="imageOrder"
        >

          <!-- NAV -->
          <div v-if="images.length > 1" class="nav-btn left-3 swiper-btn-prev">
            <FontAwesomeIcon icon="far fa-chevron-circle-left" />
          </div>

          <div v-if="images.length > 1" class="nav-btn right-3 swiper-btn-next">
            <FontAwesomeIcon icon="far fa-chevron-circle-right" />
          </div>

          <!-- MULTI -->
          <Swiper
            v-if="images.length > 1"
            :modules="[Navigation]"
            :slides-per-view="1"
            :loop="true"
            :navigation="{ prevEl: '.swiper-btn-prev', nextEl: '.swiper-btn-next' }"
            class="w-full h-full"
          >
            <SwiperSlide v-for="(img, i) in images" :key="i">
              <div class="relative w-full h-full">
                <Image
                  :src="img.original"
                  :imageCover="true"
                  class="absolute inset-0 w-full h-full object-cover object-center"
                />
              </div>
            </SwiperSlide>
          </Swiper>

          <!-- SINGLE -->
          <div v-else class="relative w-full h-full">
            <Image
              :src="images[0]?.original"
              :imageCover="true"
              class="absolute inset-0 w-full h-full object-cover object-center"
            />
          </div>

        </div>

        <!-- TEXT -->
        <div
          class="flex flex-col justify-center items-center text-center px-2 md:px-4
                 md:items-start md:text-left"
          :class="textOrder"
        >
          <h1 class="text-2xl md:text-3xl font-semibold text-gray-900">
            {{ fieldValue.family.name }}
          </h1>

           <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"  class="discount-wrapper">

              <div
                :class="bestOffer?.type === 'Category Quantity Ordered Order Interval' ? 'flex gap-3' : 'discount-grid'">

                <DiscountByType v-if="showTriggers" :offers_data="fieldValue?.family?.offers_data"
                  template="triggers_labels" class="discount-item discount-span" />

                <DiscountByType :offers_data="fieldValue?.family?.offers_data" :template="bestOffer?.type === 'Category Quantity Ordered Order Interval'
                  ? 'active-inactive-gr'
                  : 'max_discount'" class="discount-item" />
              </div>

            </div>

          <div
            v-html="cleanedDescription"
            class="text-gray-600 leading-relaxed text-sm md:text-base max-w-xl"
          />

          <div class="btn-wrapper">
              <LinkIris 
                :href="fieldValue?.button?.link?.href" 
                :canonical_url="fieldValue?.button?.link?.canonical_url"
                :target="fieldValue?.button?.link?.target" 
                :type="fieldValue?.button?.link?.type">
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
</template>

<style scoped>

.btn-wrapper {
  @apply flex justify-center md:justify-start mt-6;
}

.nav-btn {
  @apply absolute top-1/2 -translate-y-1/2 z-10 cursor-pointer
         text-gray-500 text-3xl opacity-70 hover:opacity-100;

         
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
  @apply bg-[#A80000] border border-red-900 text-gray-100 flex items-center rounded-sm
         px-1.5 py-1
         text-xs
         mb-2;
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