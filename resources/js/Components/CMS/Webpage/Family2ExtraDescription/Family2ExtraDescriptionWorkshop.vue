<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, inject, ref, watch, onMounted, nextTick  } from "vue"
import { get, isPlainObject, debounce } from "lodash-es"

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
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock:number
}>()

const layout: any = inject("layout", {})

const expanded = ref(false)
const showReadMore = ref(false)

const descriptionRef = ref<HTMLElement | null>(null)

const MAX_HEIGHT = 420

const columnPosition = computed(() => {
  const raw = get(props.modelValue, ["column_position"])

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

const image = computed(() => {
  const data =
    props.modelValue?.family?.extra_description_image

  if (!data) return null

  return data
})

const cleanedDescription = computed(() => {
  const html =
    props.modelValue?.family?.description_extra || ""

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
</script>

<template>
  <div :id="modelValue?.id
    ? modelValue?.id
    : 'family-3' + indexBlock
    " :style="{
      ...getStyles(
        layout?.app?.webpage_layout?.container
          ?.properties,
        screenType
      ),
      ...getStyles(
        modelValue?.container?.properties,
        screenType
      )
    }">
    <div class="w-full px-4 py-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-start">
        <!-- IMAGE -->
        <div class="w-full flex justify-center" :class="imageOrder">
          <div class="w-full max-w-[500px] aspect-square overflow-hidden rounded-2xl bg-gray-100">
            <Image v-if="image" :src="image" :alt="modelValue?.family?.name" class="w-full h-full"
              imgClass="w-full h-full object-cover" />
          </div>
        </div>

        <!-- CONTENT -->
        <div class="flex flex-col min-w-0" :class="textOrder">
          <!-- DESCRIPTION -->
          <div class="relative">
            <div ref="descriptionRef" v-html="cleanedDescription" class="description-content" :class="{
              'description-collapsed': !expanded
            }" />
          </div>

          <!-- READ MORE -->
          <button v-if="showReadMore" type="button" class="read-more-btn" @click="expanded = !expanded">
            {{
              expanded
                ? "Read less"
                : "Read more"
            }}
          </button>

          <!-- BUTTON -->
          <div class="mt-5 text-center md:text-left">
          <!--   <LinkIris :href="modelValue?.button?.link?.href
              " :target="modelValue?.button?.link?.target
                "> -->
              <Button :label="modelValue?.button?.text
                " :injectStyle="getStyles(
                  modelValue?.button
                    ?.container?.properties,
                  screenType
                )
                  " />
          <!--   </LinkIris> -->
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.description-content {
  @apply text-sm md:text-base text-gray-600 leading-7 text-center md:text-left transition-all duration-300;
}

.description-collapsed {
  max-height: 390px;
  overflow: hidden;

  mask-image: linear-gradient(to bottom,
      black 75%,
      transparent 100%);

  -webkit-mask-image: linear-gradient(to bottom,
      black 75%,
      transparent 100%);

  transition:
    max-height 0.35s ease,
    mask-image 0.35s ease;
}

.description-content :deep(p) {
  @apply mb-4;
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


.read-more-btn {
  @apply mt-4 text-sm font-medium text-gray-900 underline w-fit self-center md:self-start;
}
</style>