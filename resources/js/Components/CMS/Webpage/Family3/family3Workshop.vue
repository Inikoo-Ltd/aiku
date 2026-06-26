<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, ref, watch, toRefs, nextTick, onMounted } from "vue"
import { get, isPlainObject, debounce } from "lodash-es"

import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { getStyles } from "@/Composables/styles"
import { getBestOffer } from "@/Composables/useOffers"

import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import { Swiper, SwiperSlide } from "swiper/vue"
import { Navigation } from "swiper/modules"
import "swiper/css"
import "swiper/css/navigation"

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
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
}>()

const layout: any = inject("layout", {})

const { modelValue, webpageData, blockData } = toRefs(props)

const showExtra = ref(false)

/* hide image in mobile */
const showImage = computed(() => props.screenType !== "mobile")

const name = ref(
  modelValue.value?.family?.description_title ||
  modelValue.value?.family?.name ||
  ""
)

const cleanedDescription = computed(() => {
  const html = modelValue.value?.family?.description || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const columnPosition = computed(() => {
  const rawVal = get(modelValue.value, ["column_position"])

  if (!isPlainObject(rawVal)) return rawVal

  const view = props.screenType
  return rawVal?.[view] ?? rawVal?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")


const imageOrder = computed(() =>
  isImageLeft.value ? "order-1" : "order-2"
)

const textOrder = computed(() =>
  isImageLeft.value ? "order-2" : "order-1"
)

const images = computed(() => {
  const data = props.modelValue?.family?.description_image
  if (!data) return []
  return Object.values(data).filter(Boolean)
})
const saveDescription = debounce(async (key: string, value: string) => {
  try {
    const url = route("grp.models.product_category.update", {
      productCategory: modelValue.value.family.id,
    })

    await axios.patch(url, { [key]: value })
  } catch (error: any) {
    console.error("Save failed:", error)
  }
}, 1000)


watch(name, async (val) => {
  modelValue.value.family.description_title = val
  saveDescription("description_title", val)

  await nextTick()
  autoResize()
})

const titleRef = ref<HTMLTextAreaElement | null>(null)

const autoResize = () => {
  const el = titleRef.value
  if (!el) return

  requestAnimationFrame(() => {
    el.style.height = "auto"
    el.style.height = el.scrollHeight + "px"
  })
}

onMounted(async () => {
  await nextTick()
  autoResize()

  requestAnimationFrame(() => {
    autoResize()
  })
})

console.log("Family3 Workshop Props:", props)
</script>

<template>
 <div :id="modelValue?.id ? modelValue?.id : 'family-3' + indexBlock" :style="{
    ...getStyles(
      layout?.app?.webpage_layout?.container?.properties,
      screenType
    ),
    ...getStyles(
      modelValue?.container?.properties,
      screenType
    )
  }">
    <div class="w-full px-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 md:gap-8 gap-0 lg:gap-12 items-start">
        <div class="flex flex-col gap-5 min-w-0" :class="imageOrder">

          <!-- TITLE -->
          <div class="space-y-2">
            <h1 class="product-title">
                <textarea ref="titleRef" v-model="name" @input="autoResize" rows="1" placeholder="Family Title" class="w-full resize-none overflow-hidden bg-transparent border-none p-0 m-0
           text-2xl md:text-3xl font-semibold text-gray-900
           leading-tight
           focus:outline-none focus:ring-0
           text-center md:text-left"></textarea>

            </h1>
          </div>

          <!-- IMAGE -->
          <div class="w-full relative hidden md:block">

            <!-- NAVIGATION -->
            <div v-if="images.length > 3" :class="`nav-btn left-3 swiper-btn-prev-${swiperId}`">
              <FontAwesomeIcon :icon="faChevronCircleLeft" />
            </div>

            <div v-if="images.length > 3" :class="`nav-btn right-3 swiper-btn-next-${swiperId}`">
              <FontAwesomeIcon :icon="faChevronCircleRight" />
            </div>

            <!-- SWIPER -->
            <Swiper :key="images.length" :modules="[Navigation]" :loop="images.length > 3" :navigation="{
              prevEl: `.swiper-btn-prev-${swiperId}`,
              nextEl: `.swiper-btn-next-${swiperId}`
            }" :breakpoints="{
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
              }">
              <SwiperSlide v-for="(img, i) in images" :key="i">
                <div class="relative w-full aspect-square overflow-hidden rounded-2xl bg-gray-100">
                  <Image :src="img.original" :imageCover="true" :alt="`image-description-${i}`"
                    class="absolute inset-0 w-full h-full object-cover transition duration-300 hover:scale-105" />
                </div>
              </SwiperSlide>
            </Swiper>

          </div>
        </div>

        <div class="flex flex-col min-w-0" :class="textOrder">


          <div class="discount-wrapper" :class="'invisible pointer-events-none'">

           <!--  <DiscountByType v-if="showTriggers" :offers_data="modelValue?.family?.offers_data
              " template="triggers_labels" class="discount-pill" />

            <DiscountByType :offers_data="modelValue?.family?.offers_data
              " template="active-inactive-gr" class="discount-pill" /> -->

          </div>

          <!-- DESCRIPTION -->
          <div v-html="cleanedDescription" class="description-content" />

          <!-- BUTTON -->
          <div class="mt-3 text-center md:text-right">
              <button id="family-3-button" :label="modelValue?.button?.text" class="!bg-transparent !shadow-none !border-0 !p-0 !h-auto 
             text-sm md:text-base font-medium
             hover:underline underline-offset-4 mr-5 italic
             transition-all duration-200" >{{ modelValue?.button?.text }}</button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.product-title {
  @apply text-2xl font-semibold text-gray-900 leading-tight break-words md:text-start text-center min-h-[40px]
}


.description-content {
  @apply text-sm md:text-base text-gray-600 leading-7 text-center md:text-left;
}

.description-content :deep(p) {
  @apply mb-0;
}

.description-content :deep(h2),
.description-content :deep(h3),
.description-content :deep(h4) {
  @apply text-gray-900 font-semibold mt-6 mb-3;
}

.description-content :deep(ul) {
  @apply list-disc pl-5 space-y-2;
}

.description-content :deep(ol) {
  @apply list-decimal pl-5 space-y-2;
}


.description-content :deep(ul) {
  @apply list-disc pl-5 ml-0 mt-2 space-y-2 list-outside;
}

.discount-wrapper {
  @apply flex flex-wrap items-stretch justify-center gap-2 min-w-0 md:justify-start mt-3 md:mt-1 min-h-[40px];
}

.discount-pill {
  @apply flex min-w-0 md:max-w-[50%] max-w-full self-stretch;
}


.discount-wrapper :deep(.offer-max-discount) {
  @apply flex items-center rounded-lg overflow-hidden border border-gray-200 bg-white shadow-sm h-[38px];
}

.discount-wrapper :deep(.volume-discount-label) {
  @apply justify-start mb-0
}

/* LEFT SIDE */
.discount-wrapper :deep(.discount-percentage) {
  @apply flex items-center justify-center font-bold text-sm px-3 w-fit whitespace-nowrap;
}

.discount-wrapper :deep(.percentage-text) {
  @apply leading-none;
}

/* RIGHT SIDE */
.discount-wrapper :deep(.discount-content) {
  @apply flex items-center gap-1 px-3 min-w-0 h-full bg-white;
}

.discount-wrapper :deep(.discount-content > div) {
  @apply min-w-0;
}

/* TITLE */
.discount-wrapper :deep(.discount-title) {
  @apply text-[11px] font-medium text-gray-700 leading-tight truncate;
}

/* SUBTEXT */
.discount-wrapper :deep(.discount-triggers) {
  @apply text-xs leading-tight truncate;
}


.discount-wrapper :deep(.gr-wrapper) {
  @apply flex items-center gap-1 rounded-lg border shadow-sm px-3 min-h-[40px] h-full mb-0;
}


.discount-wrapper :deep(.inactive-text) {
  @apply text-xs truncate;
}

.discount-wrapper :deep(.gr-content) {
  @apply min-w-0;
}


.discount-wrapper :deep(.info-icon),
.discount-wrapper :deep(.gr-info-icon) {
  @apply flex items-center justify-center flex-shrink-0;
}

.discount-wrapper :deep(svg) {
  @apply w-3 h-3;
}


.discount-wrapper :deep(.gr-logo) {
  @apply h-4 w-auto;
}


.discount-wrapper :deep(.info-icon),
.discount-wrapper :deep(.gr-info-icon) {
  @apply flex items-center justify-center flex-shrink-0;
}

.discount-wrapper :deep(svg) {
  @apply w-3 h-3;
}


.discount-wrapper :deep(.gr-logo) {
  @apply h-4 w-auto;
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