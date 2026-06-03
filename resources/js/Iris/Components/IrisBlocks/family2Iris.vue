<script setup lang="ts">
import { computed, inject } from "vue"

import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage } from "@far"
import { getBestOffer } from '@/Composables/useOffers'
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'

interface FamilyImage {
  original: string
  alt?: string
}

interface FamilyData {
  name?: string
  description?: string
  description_image?: Record<string, FamilyImage>
  offers_data: object
}

interface FieldValue {
  id?: string | number
  family?: FamilyData
  container?: {
    properties?: Record<string, unknown>
  }
}

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
  fieldValue: FieldValue
  screenType: ScreenType
  indexBlock: number
}>()

const layout = inject<Record<string, any>>("layout", {})

const cleanedDescription = computed(() => {
  const html = props.fieldValue?.family?.description || ""
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const images = computed<FamilyImage[]>(() => {
  const data = props.fieldValue?.family?.description_image

  if (!data) return []

  return Object.values(data).filter(
    (item) => item && item.original
  )
})

const hasImage = (index: number) => {
  return Boolean(images.value?.[index]?.original)
}

const bestOffer = computed(() => {
  return getBestOffer(props.fieldValue?.family?.offers_data)
})

</script>

<template>
  <section :id="`family-2`" component="family-2-iris">
    <div class="mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 xl:px-14 2xl:max-w-[1800px] 2xl:px-14" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue?.container?.properties),
      width: 'auto'
    }">
      <div class="flex flex-col gap-6 lg:flex-row">
        <!-- IMAGE SECTION -->
        <div class="flex shrink-0 justify-center gap-[6px]">
          <!-- IMAGE 1 -->
          <template v-if="hasImage(0)">
            <Image :src="images[0].original" :imageCover="true" :alt="images[0]?.alt || 'family image'" class="
                h-[280px]
                w-[220px]
                object-cover
                sm:w-[290px]
                xl:h-[320px]
                xl:w-[340px]
                2xl:h-[380px]
                2xl:w-[420px]
              " />
          </template>

          <div v-else class="
              flex items-center justify-center
              h-[280px]
              w-[220px]
              border border-gray-200
              bg-gray-100
              sm:w-[290px]
              xl:h-[320px]
              xl:w-[340px]
              2xl:h-[380px]
              2xl:w-[420px]
            ">
            <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
          </div>

          <div class="flex flex-col gap-[6px]">
            <!-- IMAGE 2 -->
            <template v-if="hasImage(1)">
              <Image :src="images[1].original" :imageCover="true" :alt="images[1]?.alt || 'family image'" class="
                  h-[137px]
                  w-[105px]
                  object-cover
                  sm:w-[140px]
                  xl:h-[157px]
                  xl:w-[160px]
                  2xl:h-[187px]
                  2xl:w-[200px]
                " />
            </template>

            <div v-else class="
                flex items-center justify-center
                h-[137px]
                w-[105px]
                border border-gray-200
                bg-gray-100
                sm:w-[140px]
                xl:h-[157px]
                xl:w-[160px]
                2xl:h-[187px]
                2xl:w-[200px]
              ">
              <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
            </div>

            <!-- IMAGE 3 -->
            <template v-if="hasImage(2)">
              <Image :src="images[2].original" :imageCover="true" :alt="images[2]?.alt || 'family image'" class="
                  h-[137px]
                  w-[105px]
                  object-cover
                  sm:w-[140px]
                  xl:h-[157px]
                  xl:w-[160px]
                  2xl:h-[187px]
                  2xl:w-[200px]
                " />
            </template>

            <div v-else class="
                flex items-center justify-center
                h-[137px]
                w-[105px]
                border border-gray-200
                bg-gray-100
                sm:w-[140px]
                xl:h-[157px]
                xl:w-[160px]
                2xl:h-[187px]
                2xl:w-[200px]
              ">
              <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
            </div>
          </div>
        </div>

        <!-- CONTENT -->
        <div class="flex min-w-0 flex-1 flex-col">
          <div class="
              flex
              flex-col
              gap-4
              text-center
              lg:text-left
              xl:flex-row
              xl:items-start
              xl:justify-between
            ">
            <div class="min-w-0 flex-1">
              <h1 class="
                  text-[18px]
                  font-bold
                  leading-[1.15]
                  text-[#12243c]
                  sm:text-[24px]
                  2xl:text-[30px]
                ">
                {{ fieldValue.family?.name }}
              </h1>
            </div>
            <div class="flex  gap-x-1 gap-y-1 md:gap-y-2 offer">
              <DiscountByType :offers_data="fieldValue?.family?.offers_data"
                :template="bestOffer?.type == 'Category Quantity Ordered Order Interval' ? 'active-inactive-gr-v2' : 'max_discount'" />
              <DiscountByType
                v-if="!(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr) && bestOffer?.type == 'Category Quantity Ordered Order Interval'"
                :offers_data="fieldValue?.family?.offers_data" :template="'triggers_labels_v2'" />
            </div>
          </div>

          <div class="
              flex-1
              space-y-[4px]
              text-[14px]
              leading-[1.6]
              text-[#1d2430]
              sm:text-[15px]
              xl:text-[16px]
              2xl:space-y-2
              2xl:text-[19px]
            " v-html="cleanedDescription" />

          <div class="
              mt-5
              flex
              flex-col
              items-center
              gap-4
              xl:flex-row
              xl:items-center
              xl:justify-start
              2xl:mt-8
            ">

            <a href="#family-2-extra-description">
              <button class="h-[38px]
                rounded-xl
                border
                border-[#333]
                px-8
                text-sm
                font-medium
                2xl:h-[48px]
                2xl:px-12
                2xl:text-base" :style="{
                  ...getStyles(fieldValue?.button?.container?.properties)
                }">
                <span v-if="fieldValue?.button?.text">{{ fieldValue?.button?.text }}</span>
                <span v-else>{{ ctrans('Learn more') }}</span>
              </button>
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
:deep(.offer .vd-content) {
    @apply flex flex-col justify-center -ml-4 pl-7 pr-3 my-0.5 mr-0.5 rounded-md bg-gray-900 shadow-sm min-w-0;
}

:deep(.offer .vd-triggers) {
    @apply text-[10px] leading-tight opacity-80 truncate max-w-[65px];
}

.editor-class h1 {
    font-size: 1.75rem; /* mobile */
}

@media (min-width: 1280px) {
    .editor-class h1 {
        font-size: 1.8rem; /* xl */
    }
}

@media (min-width: 1536px) {
    .editor-class h1 {
        font-size: 2.5rem; /* 2xl */
    }
}

</style>