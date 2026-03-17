<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject, ref, watch } from "vue"
import { get, isPlainObject, debounce } from "lodash-es"
import axios from "axios"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"
import { getBestOffer } from "@/Composables/useOffers"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"

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



const description = ref("")

const cleanHtml = (html: string) => {
  return (html || "").replace(/<h1[^>]*>.*?<\/h1>/gis, "")
}

watch(
  () => props.modelValue?.family?.description_extra,
  (val) => {
    description.value = cleanHtml(val)
  },
  { immediate: true }
)



const bestOffer = computed(() => {
  return getBestOffer(props.modelValue?.family?.offers_data)
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



const hideImageOnMobile = computed(() => props.screenType === "mobile")

const textAlignClass = computed(() =>
  props.screenType === "mobile" ? "text-center" : "text-left"
)

const buttonJustifyClass = computed(() =>
  props.screenType === "mobile" ? "justify-center" : "justify-start"
)



const saveDescription = debounce(async (key: string, value: string) => {
  try {
    const url = route("grp.models.product_category.update", {
      productCategory: props.modelValue?.family?.id,
    })

    await axios.patch(url, { [key]: value })
  } catch (error: any) {
    console.error("Save failed:", error)
  }
}, 1000)
</script>

<template>
  <div :id="modelValue?.id || 'family-2'" class="w-full">
    <div :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue?.container?.properties, screenType)
    }">
      <div class="grid w-full min-h-[250px] md:min-h-[400px] grid-cols-1" :class="gridClass">
        <!-- IMAGE -->
        <div v-if="!hideImageOnMobile" class="relative w-full overflow-hidden cursor-pointer"
          :class="[imageOrder, modelValue?.family?.extra_description_image ? 'h-full' : '']"
          :style="getStyles(modelValue?.image?.container?.properties, screenType)">
          <Image :src="modelValue?.family?.extra_description_image"
            :alt="modelValue?.family?.extra_description_image?.alt || 'Image preview'" :imageCover="true"
            class="absolute inset-0 w-full h-full object-cover"
            :height="getStyles(modelValue?.image?.container?.properties, screenType, false)?.height"
            :width="getStyles(modelValue?.image?.container?.properties, screenType, false)?.width" />
        </div>

        <!-- TEXT -->
        <div class="flex flex-col justify-center m-auto p-4 mx-4" :class="[textOrder, textAlignClass]"
          :style="getStyles(modelValue?.text_block?.properties, screenType)">
          <div class="w-full">
            <EditorV2 v-model="description" placeholder="Family Description"
              @update:model-value="(e) => saveDescription('description_extra', e)" :uploadImageRoute="{
                name: webpageData?.images_upload_route?.name,
                parameters: { modelHasWebBlocks: blockData?.id }
              }" />

            <div class="flex mt-6" :class="buttonJustifyClass">
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