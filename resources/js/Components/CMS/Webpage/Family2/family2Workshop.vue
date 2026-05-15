<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject, ref, watch, toRefs, nextTick, onMounted } from "vue"
import { get, isPlainObject, debounce } from "lodash-es"

import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"
import { getBestOffer } from "@/Composables/useOffers"

import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"

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
  <div class="w-full"  :id="modelValue?.id ? modelValue?.id : 'family-3'+indexBlock"  :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue?.container?.properties, screenType)
    }">

    <!-- 🔧 LIMIT WIDTH biar tidak melebar di 2xl -->
    <div class="mx-auto max-w-[2000px] w-full px-4 md:px-8 xl:px-12" >

      <div class="grid w-full grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">

        <!-- IMAGE -->
        <div
          v-if="showImage"
          :style="getStyles(modelValue?.image?.container?.properties, screenType)"
          class="relative w-full overflow-hidden
                 aspect-[4/2]
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
        <h1 class="mb-3 w-full max-w-xl">
  <textarea
    ref="titleRef"
    v-model="name"
    @input="autoResize"
    rows="1"
    placeholder="Family Title"
    class="w-full resize-none overflow-hidden bg-transparent border-none p-0 m-0
           text-2xl md:text-3xl font-semibold text-gray-900
           leading-tight
           focus:outline-none focus:ring-0
           text-center md:text-left"
  ></textarea>
</h1>

          <div
            v-if="modelValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"
            class="discount-wrapper"
          >
          </div>

          <div
            v-html="cleanedDescription"
            class="text-gray-600 leading-relaxed text-sm md:text-base max-w-xl"
          />

          <div class="btn-wrapper">
              <LinkIris :href="modelValue?.button?.link?.href" :canonical_url="modelValue?.button?.link?.canonical_url"
                :target="modelValue?.button?.link?.target" :type="modelValue?.button?.link?.type">
                <Button :label="modelValue?.button?.text"
                  :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)" />
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
</style>