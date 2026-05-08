<script setup lang="ts">
import {
  computed,
  inject,
  ref,
  nextTick,
  onMounted,
  watch
} from "vue"

import { get, isPlainObject } from "lodash-es"

import Image from "@/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"

import { getStyles } from "@/Composables/styles"
import { data } from "autoprefixer"

const props = defineProps<{
  fieldValue: any
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
}>()

const layout: any = inject("layout", {})

const expanded = ref(false)
const showReadMore = ref(false)

const descriptionRef = ref<HTMLElement | null>(null)

const MAX_HEIGHT = 420

const columnPosition = computed(() => {
  const raw = get(props.fieldValue, ["column_position"])

  if (!isPlainObject(raw)) return raw

  return (
    raw?.[props.screenType] ??
    raw?.desktop ??
    "Image-right"
  )
})

const isImageLeft = computed(
  () => columnPosition.value === "Image-right"
)

const imageOrder = computed(() =>
  isImageLeft.value ? "lg:order-1" : "lg:order-2"
)

const textOrder = computed(() =>
  isImageLeft.value ? "lg:order-2" : "lg:order-1"
)
const images = computed(() => {
  return props.fieldValue?.family?.extra_description_image || {}
})

const displayImages = computed(() => {
  const data = []

  for (const key in images.value) {
    data.push(get(images.value, key))
  }

  while (data.length < 4) {
    data.push(null)
  }

  console.log("data", data)
  return data.slice(0, 4)
})

const cleanedDescription = computed(() => {
  const html =
    props.fieldValue?.family?.description_extra || ""

  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const checkOverflow = async () => {
  await nextTick()

  if (!descriptionRef.value) return

  showReadMore.value =
    descriptionRef.value.scrollHeight > MAX_HEIGHT
}

watch(cleanedDescription, checkOverflow)

onMounted(checkOverflow)

console.log("fieldValue", props.fieldValue)
</script>

<template>
  <div
    :id="
      fieldValue?.id
        ? fieldValue?.id
        : 'family-3' + indexBlock
    "
    :style="{
      ...getStyles(
        layout?.app?.webpage_layout?.container
          ?.properties,
        screenType
      ),
      ...getStyles(
        fieldValue?.container?.properties,
        screenType
      )
    }"
  >
    <div class="w-full px-4 py-8 lg:py-4">
      <div
        class="grid grid-cols-1 lg:grid-cols-[0.9fr_1.1fr] gap-10 lg:gap-16 items-center"
      >
        <!-- IMAGE -->
        <div
          class="w-full flex justify-center lg:justify-end"
          :class="imageOrder"
        >
          <div
            class="grid grid-cols-2 gap-4 w-full max-w-[560px]"
          >
            <div
              v-for="(img, index) in displayImages"
              :key="index"
              class="aspect-square overflow-hidden rounded-3xl bg-white border border-gray-200"
            >
              <template v-if="img">
                <Image
                  :src="img"
                  :alt="fieldValue?.family?.name"
                  class="w-full h-full"
                  imgClass="w-full h-full object-cover transition duration-500 hover:scale-105"
                />
              </template>
            </div>
          </div>
        </div>

        <!-- CONTENT -->
        <div
          class="flex flex-col min-w-0 max-w-[720px]"
          :class="textOrder"
        >
          <!-- DESCRIPTION -->
          <div class="relative ">
            <div
              ref="descriptionRef"
              v-html="cleanedDescription"
              class="description-content"
              :class="{
                'description-collapsed': !expanded
              }"
            />
          </div>

          <!-- READ MORE -->
          <button
            v-if="showReadMore"
            type="button"
            class="read-more-btn"
            @click="expanded = !expanded"
          >
            {{
              expanded
                ? "Read less"
                : "Read more"
            }}
          </button>

          <!-- BUTTON -->
          <div class="mt-7 text-center md:text-right">
            <LinkIris
              :href="
                fieldValue?.button?.link?.href
              "
              :target="
                fieldValue?.button?.link?.target
              "
            >
            <button id="family-3-button" :label="fieldValue?.button?.text" class="!bg-transparent !shadow-none !border-0 !p-0 !h-auto 
             text-sm md:text-base font-medium
             hover:underline underline-offset-4 mr-5 italic
             transition-all duration-200" >{{ fieldValue?.button?.text }}</button>
            </LinkIris>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>

.description-content {
  @apply text-sm
  md:text-[15px]
  lg:text-base
  text-gray-600
  leading-7
  lg:leading-8
  text-center
  md:text-left
  transition-all
  duration-300;
}


.description-content :deep(p) {
  @apply mb-0;
}

.description-content :deep(h2),
.description-content :deep(h3),
.description-content :deep(h4) {
  @apply text-gray-900 font-semibold mt-6 mb-3;
}

.description-content :deep(ul) {
  @apply list-disc pl-5 space-y-2;
}

.description-content :deep(ol) {
  @apply list-decimal pl-5 space-y-2;
}


.description-content :deep(ul) {
  @apply list-disc pl-5 ml-0 mt-2 space-y-2 list-outside;
}


.description-collapsed {
  max-height: 390px;
  overflow: hidden;

  mask-image: linear-gradient(
    to bottom,
    black 75%,
    transparent 100%
  );

  -webkit-mask-image: linear-gradient(
    to bottom,
    black 75%,
    transparent 100%
  );

  transition:
    max-height 0.35s ease,
    mask-image 0.35s ease;
}


.description-content :deep(img) {
  @apply rounded-2xl
  overflow-hidden
  my-6;
}

.read-more-btn {
  @apply mt-5
  text-sm
  font-medium
  text-gray-900
  underline
  w-fit
  self-center
  md:self-start;
}
</style>