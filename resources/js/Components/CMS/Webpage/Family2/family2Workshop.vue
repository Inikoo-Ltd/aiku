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
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})

const showExtra = ref(false)

const toggleShowExtra = () => {
  showExtra.value = !showExtra.value
}

const bestOffer = computed(() => {
  return getBestOffer(props.modelValue?.family?.offers_data)
})

const cleanedDescription = computed(() => {
  const html = props.modelValue?.family?.description || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const columnPosition = computed(() => {
  const rawVal = get(props.modelValue, ["column_position"])

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
</script>

<template>
  <div
    :id="modelValue?.id || 'family-2'"
    component="family-2"
    class="w-full"
  >
    <div
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue?.container?.properties, screenType)
      }"
    >
      <div
        class="grid w-full min-h-[250px] md:min-h-[400px] grid-cols-1"
        :class="gridClass"
      >
        <!-- IMAGE -->
        <component
          :is="modelValue?.image?.link?.href ? LinkIris : 'div'"
          :href="modelValue?.image?.link?.href"
          :target="modelValue?.image?.link?.target"
          :type="modelValue?.image?.link?.type"
          class="relative w-full overflow-hidden cursor-pointer"
          :class="[imageOrder,
            modelValue?.image?.source
              ? 'h-[250px] sm:h-[300px] md:h-[400px]'
              : ''
          ]"
          :style="getStyles(modelValue?.image?.container?.properties, screenType)"
        >
          <Image
            :src="modelValue?.image?.source"
            :alt="modelValue?.image?.alt || 'Image preview'"
            :imgAttributes="modelValue?.image?.attributes"
            :imageCover="true"
            class="absolute inset-0 w-full h-full object-fill"
            :height="getStyles(modelValue?.image?.container?.properties, screenType, false)?.height"
            :width="getStyles(modelValue?.image?.container?.properties, screenType, false)?.width"
          />
        </component>

        <!-- TEXT -->
        <div
          class="flex flex-col justify-center m-auto"
          :class="textOrder"
          :style="getStyles(modelValue?.text_block?.properties, screenType)"
        >
          <div class="w-full max-w-xl">
            <h1 v-if="modelValue.family.name" class="!text-[1.8rem] font-semibold"> {{ modelValue.family.name }}</h1>

            <div v-html="cleanedDescription"></div>

            <div class="flex justify-start mt-6">
              <LinkIris
                :href="modelValue?.button?.link?.href"
                :canonical_url="modelValue?.button?.link?.canonical_url"
                :target="modelValue?.button?.link?.target"
                :type="modelValue?.button?.link?.type"
              >
                <Button
                  :label="modelValue?.button?.text"
                  :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
                />
              </LinkIris>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>