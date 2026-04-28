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

  el.style.height = "0px"
  el.style.height = el.scrollHeight + "px"
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
  <div :id="modelValue?.id || 'family-2'+indexBlock" component="family-2" class="w-full">

    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue?.container?.properties, screenType)
    }">

      <div class="grid w-full min-h-[250px] md:min-h-[400px] grid-cols-1" :class="gridClass">

        <!-- IMAGE (hidden on mobile) -->
        <div v-if="showImage" class="relative w-full overflow-hidden"
          :class="[imageOrder, images.length ? 'h-[250px] sm:h-[300px] md:h-[400px]' : '']"
          :style="getStyles(modelValue?.image?.container?.properties, screenType)">

          <div v-if="images.length > 1"
            class="swiper-btn-prev absolute left-3 top-1/2 -translate-y-1/2 z-10 cursor-pointer">
            <FontAwesomeIcon icon="far fa-chevron-circle-left" class="text-gray-500 text-3xl" />
          </div>

          <div v-if="images.length > 1"
            class="swiper-btn-next absolute right-3 top-1/2 -translate-y-1/2 z-10 cursor-pointer">
            <FontAwesomeIcon icon="far fa-chevron-circle-right" class="text-gray-500 text-3xl" />
          </div>

          <Swiper v-if="images.length > 1" :modules="[Navigation]" :slides-per-view="1" :loop="true" :navigation="{
            prevEl: '.swiper-btn-prev',
            nextEl: '.swiper-btn-next'
          }" class="w-full h-full">
            <SwiperSlide v-for="(img, i) in images" :key="i">
              <div class="w-full h-full flex items-center justify-center">
                 <Image :src="img.original" :alt="modelValue?.image?.alt || 'Image preview'" :imgAttributes="{
                  ...modelValue?.image?.attributes,
                  class: 'w-full h-full object-cover'
                }" />
              </div>
            </SwiperSlide>
          </Swiper>

         <div v-else class="absolute inset-0">
            <Image :src="images[0]?.original" :alt="modelValue?.image?.alt || 'Image preview'" :imgAttributes="{
              ...modelValue?.image?.attributes,
              class: 'w-full h-full object-cover'
            }" />
          </div>

        </div>

        <!-- TEXT -->
        <div class="flex flex-col justify-center m-auto items-center md:items-start text-center md:text-left"
          :class="textOrder" :style="getStyles(modelValue?.text_block?.properties, screenType)">

          <div class="w-full max-w-xl">

            <textarea ref="titleRef" v-model="name" @input="autoResize" rows="1" placeholder="Family Title"
              class="w-full resize-none overflow-hidden box-border appearance-none bg-transparent border-none p-0 m-0 text-[1.5rem] leading-[2rem] font-semibold text-gray-800 focus:outline-none focus:ring-0 shadow-none text-center md:text-left" />

               <div class="relative w-full">
              <div class="overflow-hidden transition-all duration-300"
                :class="isExpanded ? 'max-h-none' : 'max-h-[120px]'">
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


                 

            <div class="flex justify-center md:justify-start mt-6">

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