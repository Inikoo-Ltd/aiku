<script setup lang="ts">
import { computed, inject } from "vue"
import { get, isPlainObject } from "lodash-es"

import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation } from "swiper/modules"
import "swiper/css"
import "swiper/css/navigation"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import { getBestOffer } from "@/Composables/useOffers"

import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"

const props = defineProps<{
  fieldValue: any
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
}>()

const layout: any = inject("layout", {})

// UNIQUE SWIPER ID
const swiperId = `swiper-${props.indexBlock}`

// ================= OFFER =================
const offersData = computed(() => props.fieldValue?.family?.offers_data)
const bestOffer = computed(() => getBestOffer(offersData.value))

const showTriggers = computed(() => {
  if (!bestOffer.value) return false
  return (
    !(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr) &&
    bestOffer.value.type === "Category Quantity Ordered Order Interval"
  )
})

// ================= POSITION =================
const columnPosition = computed(() => {
  const raw = get(props.fieldValue, ["column_position"])
  if (!isPlainObject(raw)) return raw
  return raw?.[props.screenType] ?? raw?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")
const imageOrder = computed(() => (isImageLeft.value ? "order-1" : "order-2"))
const textOrder = computed(() => (isImageLeft.value ? "order-2" : "order-1"))

// ================= DATA =================
const images = computed(() => {
  const data = props.fieldValue?.family?.description_image
  if (!data) return []
  return Object.values(data).filter(Boolean)
})

const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

console.log("Family2 Workshop Props:", props)
</script>

<template>
  <div class="w-full" :id="fieldValue?.id ? fieldValue?.id : 'family-3' + indexBlock" :style="{
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
    ...getStyles(fieldValue?.container?.properties, screenType)
  }">
    <div class="mx-auto max-w-[2000px] w-full px-4 py-4">

      <!-- ================= HEADER (TITLE + DISCOUNT RIGHT) ================= -->
      <!--  <div class="flex items-center justify-between mb-6 gap-4 flex-wrap sm:flex-nowrap">

        <h1 class="text-xl md:text-xl font-semibold text-gray-900">
          {{ fieldValue?.family?.name }}
        </h1>

        <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in" class="discount-wrapper">
          <div :class="bestOffer?.type === 'Category Quantity Ordered Order Interval'
            ? 'block md:flex md:flex-nowrap md:gap-3'
            : 'discount-grid'">
            <DiscountByType v-if="showTriggers" :offers_data="fieldValue?.family?.offers_data"
              template="triggers_labels" class="discount-item discount-span" />

            <DiscountByType :offers_data="fieldValue?.family?.offers_data" :template="bestOffer?.type === 'Category Quantity Ordered Order Interval'
              ? 'active-inactive-gr'
              : 'max_discount'" class="discount-item" />
          </div>
        </div>

      </div> -->

      <!-- ================= CONTENT ================= -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        <!-- IMAGE -->
        <div class="w-full relative hidden md:block" :class="imageOrder">

          <!-- NAV -->
          <div v-if="images.length > 3" :class="`nav-btn left-3 swiper-btn-prev-${swiperId}`">
            <FontAwesomeIcon :icon="faChevronCircleLeft" />
          </div>

          <div v-if="images.length > 3" :class="`nav-btn right-3 swiper-btn-next-${swiperId}`">
            <FontAwesomeIcon :icon="faChevronCircleRight" />
          </div>

          <Swiper :key="images.length" :modules="[Navigation]" :loop="images.length > 3" :navigation="{
            prevEl: `.swiper-btn-prev-${swiperId}`,
            nextEl: `.swiper-btn-next-${swiperId}`
          }" :breakpoints="{
            0: { slidesPerView: 1, spaceBetween: 12 },
            768: { slidesPerView: 2, spaceBetween: 16 },
            1024: { slidesPerView: 3, spaceBetween: 20 }
          }">
            <SwiperSlide v-for="(img, i) in images" :key="i">
              <div class="relative w-full aspect-square overflow-hidden rounded-lg">
                <Image :src="img.original" :imageCover="true"
                  class="absolute inset-0 w-full h-full object-cover transition duration-300 hover:scale-105" />
              </div>
            </SwiperSlide>
          </Swiper>
        </div>

        <!-- TEXT -->
        <div class="flex flex-col justify-center text-center md:text-left" :class="textOrder">
          <h1 class="text-xl md:text-xl font-semibold text-gray-900">
            {{ fieldValue?.family?.name }}
          </h1>
          <div v-html="cleanedDescription" class="text-gray-600 leading-relaxed text-sm md:text-base max-w-xl" />
          <div class="flex flex-col items-center md:flex-row md:justify-start gap-3 mt-4">
            <!-- DISCOUNT -->
            <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"
              class="discount-wrapper w-fit">
              <div :class="bestOffer?.type === 'Category Quantity Ordered Order Interval'
                  ? 'block md:flex md:flex-nowrap md:gap-3'
                  : 'discount-grid'
                ">
                <DiscountByType v-if="showTriggers" :offers_data="fieldValue?.family?.offers_data"
                  template="triggers_labels" class="discount-item discount-span" />

                <DiscountByType :offers_data="fieldValue?.family?.offers_data" :template="bestOffer?.type === 'Category Quantity Ordered Order Interval'
                    ? 'active-inactive-gr'
                    : 'max_discount'
                  " class="discount-item" />
              </div>
            </div>

            <!-- BUTTON -->
            <LinkIris :href="fieldValue?.button?.link?.href" :target="fieldValue?.button?.link?.target">
              <Button :label="fieldValue?.button?.text"
                :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)" />
            </LinkIris>

          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* NAVIGATION */
.nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 20;
  cursor: pointer;
  font-size: 16px;
  opacity: 0.7;
  color: #111;
}

.nav-btn:hover {
  opacity: 1;
}

.left-3 {
  left: 10px;
}

.right-3 {
  right: 10px;
}

/* BASE */
.discount-wrapper {
  margin: 0 auto;
}

.discount-grid {
  @apply grid grid-cols-1 gap-2 items-center md:grid-cols-3 2xl:gap-3;
  ;
}

.discount-span {
  @apply col-span-2;
}

.discount-item {
  @apply min-w-0;
}

/* BADGE */
.discount-wrapper :deep(.offer-max-discount) {
  @apply bg-[#A80000] border border-red-900 text-gray-100 flex items-center rounded-sm px-2 py-1 text-xs 2xl:px-3 2xl:py-2 2xl:text-sm;
}

/* PERCENTAGE */
.discount-wrapper :deep(.discount-percentage) {
  @apply text-white font-bold px-2 w-[30%] text-sm 2xl:text-base 2xl:px-3;
}

.discount-wrapper :deep(.percentage-text) {
  @apply text-sm 2xl:text-lg;
}

/* TITLE */
.discount-wrapper :deep(.discount-title) {
  @apply text-sm leading-5 2xl:text-base 2xl:leading-6;
}

/* CONTENT WRAPPER */
.discount-wrapper :deep(.discount-content) {
  @apply flex items-center gap-1 min-w-0 max-w-[160px] 2xl:gap-2 2xl:max-w-[280px];
}

/* TEXT WRAPPER */
.discount-wrapper :deep(.discount-content > div) {
  @apply min-w-0 flex-1;
}

/* TRIGGERS (TRUNCATE) */
.discount-wrapper :deep(.discount-triggers) {
  @apply block truncate text-sm leading-5 opacity-70 2xl:text-base 2xl:leading-6;
}

/* GR WRAPPER */
.discount-wrapper :deep(.gr-wrapper) {
  @apply flex items-center gap-2;
}

/* GR TEXT (TRUNCATE) */
.discount-wrapper :deep(.inactive-text) {
  @apply min-w-0 flex-1 max-w-[170px] truncate text-sm leading-5 opacity-70 md:max-w-[120px] 2xl:max-w-[200px] 2xl:text-base 2xl:leading-6;
}

/* ICON FIX */
.discount-wrapper :deep(.gr-info-icon) {
  @apply flex-shrink-0;
}
</style>