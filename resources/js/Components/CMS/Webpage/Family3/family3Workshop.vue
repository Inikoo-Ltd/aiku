<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, ref, watch, toRefs, nextTick, onMounted } from "vue"
import { get, isPlainObject, debounce } from "lodash-es"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

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

const bestOffer = computed(() => {
  return getBestOffer(modelValue.value?.family?.offers_data)
})

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
  const src = modelValue.value?.family?.web_images?.all
  if (!src) return []
  return Array.isArray(src) ? src : [src]
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

const isExpanded = ref(false)

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

console.log("Family2 Workshop Props:", props)
</script>

<template>
  <div
    class="w-full"
    :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue?.container?.properties, screenType)
    }"
  >
    <div class="mx-auto max-w-[2000px] w-full px-4 py-4">

      <!-- ================= HEADER (TITLE + DISCOUNT RIGHT) ================= -->
      <div class="flex items-center justify-between mb-6 gap-4 flex-wrap sm:flex-nowrap">

        <h1 class="text-xl md:text-xl font-semibold text-gray-900">
          {{ modelValue?.family?.name }}
        </h1>
      </div>

      <!-- ================= CONTENT ================= -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        <!-- IMAGE -->
        <div class="w-full relative" :class="imageOrder">

          <!-- NAV -->
          <div
            v-if="images.length > 3"
            :class="`nav-btn left-3 swiper-btn-prev-${swiperId}`"
          >
            <FontAwesomeIcon :icon="faChevronCircleLeft" />
          </div>

          <div
            v-if="images.length > 3"
            :class="`nav-btn right-3 swiper-btn-next-${swiperId}`"
          >
            <FontAwesomeIcon :icon="faChevronCircleRight" />
          </div>

          <Swiper
            :key="images.length"
            :modules="[Navigation]"
            :loop="images.length > 3"
            :navigation="{
              prevEl: `.swiper-btn-prev-${swiperId}`,
              nextEl: `.swiper-btn-next-${swiperId}`
            }"
            :breakpoints="{
              0: { slidesPerView: 1, spaceBetween: 12 },
              768: { slidesPerView: 2, spaceBetween: 16 },
              1024: { slidesPerView: 3, spaceBetween: 20 }
            }"
          >
            <SwiperSlide v-for="(img, i) in images" :key="i">
              <div class="relative w-full aspect-square overflow-hidden rounded-lg">
                <Image
                  :src="img.original"
                  :imageCover="true"
                  class="absolute inset-0 w-full h-full object-cover transition duration-300 hover:scale-105"
                />
              </div>
            </SwiperSlide>
          </Swiper>
        </div>

        <!-- TEXT -->
        <div
          class="flex flex-col justify-center text-center md:text-left"
          :class="textOrder"
        >
          <div
            v-html="cleanedDescription"
            class="text-gray-600 leading-relaxed text-sm md:text-base max-w-xl"
          />

          <div class="flex justify-center md:justify-start">
              <Button
                :label="modelValue?.button?.text"
                :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
              />
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



</style>