<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject, ref } from "vue"
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
  indexBlock:number
}>()

const layout: any = inject("layout", {})

const bestOffer = computed(() => {
  return getBestOffer(props.fieldValue?.family?.offers_data)
})

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

const hideImageOnMobile = computed(() => props.screenType === "mobile")

const textAlignClass = computed(() =>
  props.screenType === "mobile" ? "text-center" : "text-left"
)

const buttonJustifyClass = computed(() =>
  props.screenType === "mobile" ? "justify-center" : "justify-start"
)
</script>

<template>
  <div
    :id="fieldValue?.id || 'family-extra-description'+indexBlock"
    class="w-full"
  >
    <div
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue?.container?.properties, screenType)
      }"
    >
      <!-- GRID -->
      <div
        class="grid w-full grid-cols-1 items-stretch"
        :class="gridClass"
      >
        <!-- IMAGE -->
        <div
          class="w-full h-full flex items-center justify-center"
          :class="[imageOrder]"
          :style="getStyles(fieldValue?.image?.container?.properties, screenType)"
        >
          <Image
            :src="fieldValue?.family?.extra_description_image"
            :alt="fieldValue?.family?.extra_description_image?.alt || 'Image preview'"
            :imageCover="false"
            class="max-h-full w-auto object-contain"
          />
        </div>

        <!-- TEXT -->
        <div
          class="flex flex-col justify-center m-auto p-4 mx-5"
          :class="[textOrder, textAlignClass]"
          :style="getStyles(fieldValue?.text_block?.properties, screenType)"
        >
          <div class="w-full">
            <div v-html="cleanedDescription"></div>

            <div class="flex mt-6" :class="buttonJustifyClass">
              <LinkIris
                :href="fieldValue?.button?.link?.href"
                :canonical_url="fieldValue?.button?.link?.canonical_url"
                :target="fieldValue?.button?.link?.target"
                :type="fieldValue?.button?.link?.type"
              >
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
  </div>
</template>