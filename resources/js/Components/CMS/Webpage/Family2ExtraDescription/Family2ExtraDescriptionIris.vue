<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject, ref, onMounted, nextTick, watch, onUnmounted } from "vue"
import { get, isPlainObject } from "lodash-es"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"

import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

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
  indexBlock: number
}>()

const layout: any = inject("layout", {})

/* =========================
   CONTENT CLEANING
========================= */
const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description_extra || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

/* =========================
   LAYOUT LOGIC (TETAP)
========================= */
const columnPosition = computed(() => {
  const rawVal = get(props.fieldValue, ["column_position"])
  if (!isPlainObject(rawVal)) return rawVal

  const view = props.screenType
  return rawVal?.[view] ?? rawVal?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")

const containerStyle = computed(() => ({
  ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
  ...getStyles(props.fieldValue?.container?.properties, props.screenType)
}))

/* =========================
   READ MORE LOGIC
========================= */
const imageRef = ref<HTMLElement | null>(null)
const contentRef = ref<HTMLElement | null>(null)

const maxHeight = ref<number | null>(null)
const isOverflow = ref(false)
const expanded = ref(false)

const calculateHeight = () => {
  if (!imageRef.value || !contentRef.value) return

  const imgHeight = imageRef.value.offsetHeight
  const contentHeight = contentRef.value.scrollHeight

  maxHeight.value = imgHeight
  isOverflow.value = contentHeight > imgHeight
}

const onImageLoad = () => {
  calculateHeight()
}

onMounted(async () => {
  await nextTick()
  calculateHeight()
  window.addEventListener("resize", calculateHeight)
})

onUnmounted(() => {
  window.removeEventListener("resize", calculateHeight)
})

watch(() => props.fieldValue, async () => {
  await nextTick()
  calculateHeight()
})

</script>

<template>
  <section
    :id="fieldValue?.id || 'family-extra-description' + indexBlock"
    class="w-full bg-gray-100 py-12 lg:py-16"
    :style="containerStyle"
  >
    <div class="max-w-7xl mx-auto px-4 lg:px-8">

      <!-- GRID -->
      <div
        class="grid items-center gap-8 lg:gap-12"
        :class="isImageLeft
          ? 'lg:grid-cols-[1fr_1.2fr]'
          : 'lg:grid-cols-[1.2fr_1fr]'"
      >

        <!-- IMAGE -->
        <div
          class="flex justify-center"
          :class="isImageLeft ? 'order-1' : 'order-2'"
        >
          <div
            ref="imageRef"
            class="w-full max-w-sm md:max-w-md lg:max-w-lg"
          >
            <Image
              :src="fieldValue?.family?.extra_description_image"
              :alt="fieldValue?.family?.extra_description_image?.alt || 'Image preview'"
              :imageCover="false"
              class="w-full h-auto object-contain"
              @load="onImageLoad"
            />
          </div>
        </div>

        <!-- TEXT -->
        <div
          class="flex flex-col justify-center"
          :class="[
            isImageLeft ? 'order-2' : 'order-1',
            screenType === 'mobile' ? 'text-center' : 'text-left'
          ]"
        >
          <div class="max-w-xl mx-auto lg:mx-0 space-y-6">

            <!-- CONTENT WRAPPER -->
            <div class="relative">
              <div
                ref="contentRef"
                class="text-gray-700 text-sm md:text-base leading-relaxed space-y-4 overflow-hidden transition-all duration-300"
                :style="!expanded && maxHeight
                  ? { maxHeight: maxHeight + 'px' }
                  : {}"
                v-html="cleanedDescription"
              />

              <!-- GRADIENT FADE -->
              <div
                v-if="isOverflow && !expanded"
                class="absolute bottom-0 left-0 w-full h-16 bg-gradient-to-t from-gray-300 pointer-events-none"
              />
            </div>

            <!-- READ MORE BUTTON -->
            <div
              v-if="isOverflow"
              class="flex"
              :class="screenType === 'mobile' ? 'justify-center' : 'justify-start'"
            >
              <button
                class="text-sm font-medium underline"
                @click="expanded = !expanded"
              >
                {{ expanded ? 'Show less' : 'Read more' }}
              </button>
            </div>

            <!-- ORIGINAL CTA BUTTON (TETAP ADA) -->
            <div
              class="flex"
              :class="screenType === 'mobile' ? 'justify-center' : 'justify-start'"
            >
              <LinkIris
                :href="fieldValue?.button?.link?.href"
                :canonical_url="fieldValue?.button?.link?.canonical_url"
                :target="fieldValue?.button?.link?.target"
                :type="fieldValue?.button?.link?.type"
                class="flex"
              >
                <Button
                  :label="fieldValue?.button?.text"
                  class="flex items-center justify-center px-6 py-3 leading-none"
                  :injectStyle="getStyles(fieldValue?.button?.container?.properties, screenType)"
                />
              </LinkIris>
            </div>

          </div>
        </div>

      </div>
    </div>
  </section>
</template>