<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { FieldValue } from "@/types/webpageTypes"

library.add(faCube, faLink)

const props = defineProps<{
  fieldValue: FieldValue
  screenType: "mobile" | "tablet" | "desktop"
}>()
</script>

<template>
  <div :style="getStyles(fieldValue?.container?.properties, screenType)">
    <div class="mx-auto max-w-2xl px-6 lg:max-w-7xl lg:px-8">

      <div class="relative z-20">
        <div v-html="fieldValue.title"></div>
      </div>

      <div class="mt-10 grid gap-4 sm:mt-16 lg:grid-cols-3 lg:grid-rows-2">

        <div class="relative lg:row-span-2">
          <div class="absolute inset-px rounded-lg bg-white z-0 lg:rounded-l-[2rem]"></div>

          <div class="relative z-20 flex h-full flex-col rounded-[calc(theme(borderRadius.lg)+1px)] lg:rounded-l-[calc(2rem+1px)]">
            <div class="px-8 pb-3 pt-8 sm:px-10 sm:pb-0 sm:pt-10">
              <div v-html="fieldValue.column1.text"></div>
            </div>

            <div class="relative min-h-[30rem] w-full grow [container-type:inline-size] max-lg:mx-auto max-lg:max-w-sm">
              <div class="absolute z-0 inset-x-10 bottom-0 top-10 overflow-hidden rounded-t-[12cqw] border-x-[3cqw] border-t-[3cqw] border-gray-700 bg-gray-900 shadow-2xl">
                <img
                  v-if="!fieldValue?.column1?.image?.source"
                  src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
                  class="h-full w-full object-cover"
                />
                <Image
                  v-else
                  :src="fieldValue.column1.image.source"
                  :alt="fieldValue.column1.image.alt"
                  :imgAttributes="fieldValue.column1.image.attributes"
                  :style="getStyles(fieldValue.column1.image.properties, screenType)"
                  :imageCover="true"
                />
              </div>
            </div>
          </div>

          <div class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 z-0 lg:rounded-l-[2rem]"></div>
        </div>

        <div class="relative max-lg:row-start-1">
          <div class="absolute inset-px rounded-lg bg-white z-0 max-lg:rounded-t-[2rem]"></div>

          <div class="relative z-20 flex h-full flex-col rounded-[calc(theme(borderRadius.lg)+1px)] max-lg:rounded-t-[calc(2rem+1px)]">
            <div class="px-8 pt-8 sm:px-10 sm:pt-10">
              <div v-html="fieldValue.column2.text"></div>
            </div>

            <div class="flex flex-1 items-center justify-center px-8 max-lg:pb-12 max-lg:pt-10 sm:px-10 lg:pb-2">
              <div class="relative z-0 w-full max-lg:max-w-xs">
                <img
                  v-if="!fieldValue?.column2?.image?.source"
                  src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
                  class="h-full w-full object-cover rounded-lg shadow-lg"
                />
                <Image
                  v-else
                  :src="fieldValue.column2.image.source"
                  :alt="fieldValue.column2.image.alt"
                  :imgAttributes="fieldValue.column2.image.attributes"
                  :style="getStyles(fieldValue.column2.image.properties, screenType)"
                  :imageCover="true"
                />
              </div>
            </div>
          </div>

          <div class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 z-0 max-lg:rounded-t-[2rem]"></div>
        </div>

        <div class="relative max-lg:row-start-3 lg:col-start-2 lg:row-start-2">
          <div class="absolute inset-px rounded-lg bg-white z-0"></div>

          <div class="relative z-20 flex h-full flex-col rounded-[calc(theme(borderRadius.lg)+1px)]">
            <div class="px-8 pt-8 sm:px-10 sm:pt-10">
              <div v-html="fieldValue.column3.text"></div>
            </div>

            <div class="flex flex-1 items-center justify-center px-8 max-lg:py-6 lg:pb-2">
              <div class="relative z-0 w-full max-lg:max-w-xs">
                <img
                  v-if="!fieldValue?.column3?.image?.source"
                  src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
                  class="h-full w-full object-cover rounded-lg shadow-lg"
                />
                <Image
                  v-else
                  :src="fieldValue.column3.image.source"
                  :alt="fieldValue.column3.image.alt"
                  :imgAttributes="fieldValue.column3.image.attributes"
                  :style="getStyles(fieldValue.column3.image.properties, screenType)"
                  :imageCover="true"
                />
              </div>
            </div>
          </div>

          <div class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 z-0"></div>
        </div>

        <div class="relative lg:row-span-2">
          <div class="absolute inset-px rounded-lg bg-white z-0 lg:rounded-r-[2rem]"></div>

          <div class="relative z-20 flex h-full flex-col rounded-[calc(theme(borderRadius.lg)+1px)] lg:rounded-r-[calc(2rem+1px)]">
            <div class="px-8 pb-3 pt-8 sm:px-10 sm:pb-0 sm:pt-10">
              <div v-html="fieldValue.column4.text"></div>
            </div>

            <div class="relative min-h-[30rem] w-full grow [container-type:inline-size] max-lg:mx-auto max-lg:max-w-sm">
              <div class="absolute z-0 inset-x-10 bottom-0 top-10 overflow-hidden rounded-t-[12cqw] border-x-[3cqw] border-t-[3cqw] border-gray-700 bg-gray-900 shadow-2xl">
                <img
                  v-if="!fieldValue?.column4?.image?.source"
                  src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
                  class="h-full w-full object-cover"
                />
                <Image
                  v-else
                  :src="fieldValue.column4.image.source"
                  :alt="fieldValue.column4.image.alt"
                  :imgAttributes="fieldValue.column4.image.attributes"
                  :style="getStyles(fieldValue.column4.image.properties, screenType)"
                  :imageCover="true"
                />
              </div>
            </div>
          </div>

          <div class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 z-0 lg:rounded-r-[2rem]"></div>
        </div>

      </div>
    </div>
  </div>
</template>
