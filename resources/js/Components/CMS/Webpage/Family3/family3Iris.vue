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
import {
  faChevronCircleLeft,
  faChevronCircleRight
} from "@far"

import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"

const props = defineProps<{
  fieldValue: any
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
}>()

const layout: any = inject("layout", {})

const swiperId = `swiper-${props.indexBlock}`

const offersData = computed(() => props.fieldValue?.family?.offers_data)

const bestOffer = computed(() =>
  getBestOffer(offersData.value)
)

const showTriggers = computed(() => {
  if (!bestOffer.value) return false

  return (
    !(layout?.user?.gr_data?.amnesty ||
      layout?.user?.gr_data?.customer_is_gr) &&
    bestOffer.value.type ===
      "Category Quantity Ordered Order Interval"
  )
})

const columnPosition = computed(() => {
  const raw = get(props.fieldValue, ["column_position"])

  if (!isPlainObject(raw)) return raw

  return (
    raw?.[props.screenType] ??
    raw?.desktop ??
    "Image-right"
  )
})

const isImageLeft = computed(
  () => columnPosition.value === "Image-right"
)

const imageOrder = computed(() =>
  isImageLeft.value ? "lg:order-1" : "lg:order-2"
)

const textOrder = computed(() =>
  isImageLeft.value ? "lg:order-2" : "lg:order-1"
)

const images = computed(() => {
  const data = props.fieldValue?.family?.description_image

  if (!data) return []

  return Object.values(data).filter(Boolean)
})

const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description || ""

  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})


</script>

<template>
  <div
    :id="fieldValue?.id ? fieldValue?.id : 'family-3' + indexBlock"
    :style="{
      ...getStyles(
        layout?.app?.webpage_layout?.container?.properties,
        screenType
      ),
      ...getStyles(
        fieldValue?.container?.properties,
        screenType
      )
    }"
  >
    <div class="w-full px-4 py-6">
      <div
        class="grid grid-cols-1 lg:grid-cols-2 md:gap-8 gap-0 lg:gap-12 items-start"
      >
        <div
          class="flex flex-col gap-5 min-w-0"
          :class="imageOrder"
        >

          <!-- TITLE -->
          <div class="space-y-2">
            <h1 class="product-title">
              {{ fieldValue?.family?.name }}
            </h1>
          </div>

          <!-- IMAGE -->
          <div class="w-full relative hidden md:block">

            <!-- NAVIGATION -->
            <div
              v-if="images.length > 3"
              :class="`nav-btn left-3 swiper-btn-prev-${swiperId}`"
            >
              <FontAwesomeIcon
                :icon="faChevronCircleLeft"
              />
            </div>

            <div
              v-if="images.length > 3"
              :class="`nav-btn right-3 swiper-btn-next-${swiperId}`"
            >
              <FontAwesomeIcon
                :icon="faChevronCircleRight"
              />
            </div>

            <!-- SWIPER -->
            <Swiper
              :key="images.length"
              :modules="[Navigation]"
              :loop="images.length > 3"
              :navigation="{
                prevEl: `.swiper-btn-prev-${swiperId}`,
                nextEl: `.swiper-btn-next-${swiperId}`
              }"
              :breakpoints="{
                0: {
                  slidesPerView: 1,
                  spaceBetween: 12
                },
                768: {
                  slidesPerView: 2,
                  spaceBetween: 16
                },
                1280: {
                  slidesPerView: 3,
                  spaceBetween: 20
                }
              }"
            >
              <SwiperSlide
                v-for="(img, i) in images"
                :key="i"
              >
                <div
                  class="relative w-full aspect-square overflow-hidden rounded-2xl bg-gray-100"
                >
                  <Image
                    :src="img.original"
                    :imageCover="true"
                    :alt="`image-description-${i}`"
                    class="absolute inset-0 w-full h-full object-cover transition duration-300 hover:scale-105"
                  />
                </div>
              </SwiperSlide>
            </Swiper>

          </div>
        </div>

        <div
          class="flex flex-col min-w-0"
          :class="textOrder"
        >


          <div class="discount-wrapper" :class="{
            'invisible pointer-events-none':
              !layout.iris.is_logged_in ||
              !fieldValue?.family?.offers_data?.number_offers
          }">

            <DiscountByType
              v-if="showTriggers"
              :offers_data="
                fieldValue?.family?.offers_data
              "
              template="triggers_labels"
              class="discount-pill"
            />

            <DiscountByType
              :offers_data="
                fieldValue?.family?.offers_data
              "
              template="active-inactive-gr"
              class="discount-pill"
            />

          </div>

          <!-- DESCRIPTION -->
          <div
            v-html="cleanedDescription"
            class="description-content"
          />

          <!-- BUTTON -->
          <div class="mt-3 text-center md:text-left">
            <LinkIris
              :href="fieldValue?.button?.link?.href"
              :target="fieldValue?.button?.link?.target"
            >
              <Button
                :label="fieldValue?.button?.text"
                :injectStyle="
                  getStyles(
                    fieldValue?.button?.container
                      ?.properties,
                    screenType
                  )
                "
              />
            </LinkIris>
          </div>

        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>


.product-title {
  @apply text-2xl
  font-semibold
  text-gray-900
  leading-tight
  break-words
  md:text-start
  text-center
}



.description-content {
  @apply text-sm
  md:text-base
  text-gray-600
  leading-7
  text-center
  md:text-left;
}

.description-content :deep(p) {
  @apply mb-4;
}

.description-content :deep(h2),
.description-content :deep(h3),
.description-content :deep(h4) {
  @apply text-gray-900
  font-semibold
  mt-6
  mb-3;
}

.description-content :deep(ul) {
  @apply list-disc
  pl-5
  space-y-2;
}

.description-content :deep(ol) {
  @apply list-decimal
  pl-5
  space-y-2;
}

.discount-wrapper {
  @apply flex
  flex-wrap
  items-center
  justify-center
  gap-2
  min-w-0
  md:justify-start
  mt-3
  md:mt-0
  min-h-[40px];
}

.discount-pill {
  @apply min-w-0 md:max-w-[50%] max-w-full;
}

.discount-wrapper :deep(.offer-max-discount) {
  @apply flex
  items-center
  rounded-lg
  overflow-hidden
  border
  border-gray-200
  bg-white
  shadow-sm
  h-[38px];
}

.discount-wrapper :deep(.volume-discount-label) {
  @apply justify-start
}

/* LEFT SIDE */
.discount-wrapper :deep(.discount-percentage) {
  @apply flex
  items-center
  justify-center
  font-bold
  text-sm
  px-3
  w-fit
  whitespace-nowrap;
}

.discount-wrapper :deep(.percentage-text) {
  @apply leading-none;
}

/* RIGHT SIDE */
.discount-wrapper :deep(.discount-content) {
  @apply flex
  items-center
  gap-1
  px-3
  min-w-0
  h-full
  bg-white;
}

.discount-wrapper :deep(.discount-content > div) {
  @apply min-w-0 ;
}

/* TITLE */
.discount-wrapper :deep(.discount-title) {
  @apply text-[11px]
  font-medium
  text-gray-700
  leading-tight
  truncate;
}

/* SUBTEXT */
.discount-wrapper :deep(.discount-triggers) {
  @apply text-xs 
  leading-tight
  truncate;
}


.discount-wrapper :deep(.gr-wrapper) {
  @apply flex
  items-center
  gap-1
  rounded-lg
  border
  shadow-sm
  px-3
  h-[38px]
  w-fit
}
.discount-wrapper :deep(.inactive-text) {
  @apply text-xs 
  truncate
  leading-tight;
}

.discount-wrapper :deep(.gr-content) {
  @apply min-w-0;
}


.discount-wrapper :deep(.info-icon),
.discount-wrapper :deep(.gr-info-icon) {
  @apply flex
  items-center
  justify-center
  flex-shrink-0;
}

.discount-wrapper :deep(svg) {
  @apply w-3
  h-3;
}


.discount-wrapper :deep(.gr-logo) {
  @apply h-4
  w-auto;
}


.discount-wrapper :deep(.info-icon),
.discount-wrapper :deep(.gr-info-icon) {
  @apply flex
  items-center
  justify-center
  flex-shrink-0;
}

.discount-wrapper :deep(svg) {
  @apply w-3
  h-3;
}


.discount-wrapper :deep(.gr-logo) {
  @apply h-4
  w-auto;
}


.nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 20;

  display: flex;
  align-items: center;
  justify-content: center;

  width: 36px;
  height: 36px;

  border-radius: 9999px;

  background: rgba(255, 255, 255, 0.9);

  cursor: pointer;

  font-size: 18px;
  color: #111;

  box-shadow:
    0 2px 10px rgba(0, 0, 0, 0.08);

  transition: 0.2s ease;
}

.nav-btn:hover {
  transform: translateY(-50%) scale(1.05);
}

.left-3 {
  left: 12px;
}

.right-3 {
  right: 12px;
}
</style>