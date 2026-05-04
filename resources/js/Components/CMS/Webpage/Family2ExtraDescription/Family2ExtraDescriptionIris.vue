<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject } from "vue"
import { get, isPlainObject } from "lodash-es"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"
import { getBestOffer } from "@/Composables/useOffers"

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

const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description_extra || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const columnPosition = computed(() => {
  const rawVal = get(props.fieldValue, ["column_position"])

  if (!isPlainObject(rawVal)) return rawVal

  const view = props.screenType
  return rawVal?.[view] ?? rawVal?.desktop ?? "Image-right"
})

const isImageLeft = computed(() => columnPosition.value === "Image-right")
</script>

<template>
  <section
    :id="fieldValue?.id || 'family-extra-description' + indexBlock"
    class="w-full bg-gray-100 py-10"
    :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue?.container?.properties, screenType)
      }"
  >
    <div
      class="max-w-[2000px] mx-auto px-4 lg:px-8"
      
    >
      <!-- GRID -->
      <div
        class="grid items-center gap-10"
        :class="isImageLeft
          ? 'lg:grid-cols-[1fr_1.2fr]'
          : 'lg:grid-cols-[1.2fr_1fr]'"
      >
        <!-- IMAGE -->
        <div
          class="flex justify-center"
          :class="isImageLeft ? 'order-1' : 'order-2'"
        >
          <div class="flex justify-center w-full max-w-md lg:max-w-lg 2xl:max-w-xl">
            <Image
              :src="fieldValue?.family?.extra_description_image"
              :alt="fieldValue?.family?.extra_description_image?.alt || 'Image preview'"
              :imageCover="false"
              class="w-full h-auto object-contain"
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
          <div class="max-w-xl">
            <!-- CONTENT -->
            <div
              class="text-gray-700 leading-relaxed space-y-4"
              v-html="cleanedDescription"
            />

            <!-- BUTTON -->
            <div
              class="mt-6 flex"
              :class="screenType === 'mobile' ? 'justify-center' : 'justify-start'"
            >
              <LinkIris
                :href="fieldValue?.button?.link?.href"
                :canonical_url="fieldValue?.button?.link?.canonical_url"
                :target="fieldValue?.button?.link?.target"
                :type="fieldValue?.button?.link?.type"
              >
                <Button
                  :label="fieldValue?.button?.text"
                  class="px-6 py-3"
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