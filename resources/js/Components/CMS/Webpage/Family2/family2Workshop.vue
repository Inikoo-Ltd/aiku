<script setup lang="ts">
import { computed, inject, nextTick, onMounted, onUnmounted, ref, watch } from "vue"

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
  modelValue: FieldValue
  screenType: ScreenType
  indexBlock: number
}>()

const layout = inject<Record<string, any>>("layout", {})

const cleanedDescription = computed(() => {
  const html = props.modelValue?.family?.description || ""

  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, "")
})

const images = computed<FamilyImage[]>(() => {
  const data = props.modelValue?.family?.description_image

  if (!data) return []

  return Object.values(data).filter(
    (item) => item && item.original
  )
})

const hasImage = (index: number) => {
  return Boolean(images.value?.[index]?.original)
}

const bestOffer = computed(() => {
  return getBestOffer(props.modelValue?.family?.offers_data)
})


const titleRef = ref<HTMLElement | null>(null)
const titleState = ref<'single' | 'double' | 'truncated'>('single')
const descriptionRef = ref<HTMLElement | null>(null)
const imageRef = ref<HTMLElement | null>(null)
const expanded = ref(false)
const showReadMore = ref(false)
const maxDescriptionHeight = ref(0)
let resizeObserver: ResizeObserver | null = null

const titleStyles = computed(() => ({
  fontSize: titleState.value === 'single' ? '36px' : '25px',
}))

const measureLines = (el: HTMLElement, fontSize: string): number => {
  const clone = el.cloneNode(true) as HTMLElement

  clone.style.position = 'fixed'
  clone.style.visibility = 'hidden'
  clone.style.pointerEvents = 'none'
  clone.style.left = '-9999px'
  clone.style.top = '0'
  clone.style.width = `${el.clientWidth}px`
  clone.style.whiteSpace = 'normal'
  clone.style.fontSize = fontSize
  clone.style.lineHeight = getComputedStyle(el).lineHeight
  clone.style.padding = '0'
  clone.style.margin = '0'
  clone.style.border = 'none'
  clone.style.boxSizing = 'border-box'
  clone.style.overflow = 'visible'

  document.body.appendChild(clone)

  const lineHeight = parseFloat(getComputedStyle(clone).lineHeight)
  const lines = Math.max(1, Math.round(clone.scrollHeight / lineHeight))

  document.body.removeChild(clone)

  return lines
}

const updateTitleSize = () => {
  const el = titleRef.value

  if (!el) {
    return
  }

  requestAnimationFrame(() => {
    const linesAt36 = measureLines(el, '36px')

    if (linesAt36 <= 1) {
      titleState.value = 'single'
      return
    }

    const linesAt25 = measureLines(el, '25px')

    titleState.value = linesAt25 <= 2 ? 'double' : 'truncated'
  })
}

const calculateDescriptionHeight = async () => {
  await nextTick()

  if (!imageRef.value || !descriptionRef.value) {
    return
  }

  console.log(descriptionRef.value.scrollHeight,imageRef.value.offsetHeight )

  maxDescriptionHeight.value = imageRef.value.offsetHeight
  showReadMore.value = descriptionRef.value.scrollHeight > imageRef.value.offsetHeight - 110
}

onMounted(() => {
  updateTitleSize()
  calculateDescriptionHeight()

  resizeObserver = new ResizeObserver(() => {
    updateTitleSize()
    calculateDescriptionHeight()
  })

  if (titleRef.value) {
    resizeObserver.observe(titleRef.value)
  }

  if (imageRef.value) {
    resizeObserver.observe(imageRef.value)
  }
})

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
})

watch(
  () => [
    props.modelValue?.family?.name,
    props.modelValue?.family?.description,
    props.modelValue?.family?.description_image,
  ],
  () => {
    updateTitleSize()
    calculateDescriptionHeight()
  }
)

const contentClass = computed(() =>
  layout.rightbasket?.show
    ? 'flex flex-col gap-6'
    : 'flex flex-col gap-6 lg:flex-row lg:items-stretch'
)
</script>

<template>
  <section :id="`family-2`" component="family-2-iris" class="editor-class">
    <div class="mx-auto w-full  bg-white  py-4 sm:px-8  " :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue?.container?.properties),
      width: 'auto'
    }">
      <div :class="contentClass">
        <!-- IMAGE SECTION -->
        <div class="flex shrink-0 justify-center gap-[6px]">
          <!-- IMAGE 1 -->
          <template v-if="hasImage(0)">
            <Image  :src="images[0].original" :imageCover="true" :alt="images[0]?.alt || 'family image'"
              class="
                h-[280px]
                w-[220px]
                object-cover
                sm:w-[290px]
                lg:h-[320px]
                lg:w-[340px]
              " />
          </template>

          <div v-else  class="
              flex items-center justify-center
              h-[280px]
              w-[220px]
              border border-gray-200
              bg-gray-100
              sm:w-[290px]
              lg:h-[320px]
              lg:w-[340px]
            ">
            <FontAwesomeIcon :icon="faImage" class="h-14 w-14 text-gray-400" />
          </div>

          <div ref="imageRef" class="flex flex-col gap-[6px]">
            <!-- IMAGE 2 -->
            <template v-if="hasImage(1)">
              <Image :src="images[1].original" :imageCover="true" :alt="images[1]?.alt || 'family image'" class="
                  h-[137px]
                  w-[105px]
                  object-cover
                  sm:w-[140px]
                  lg:h-[157px]
                  lg:w-[160px]
                " />
            </template>

            <div v-else class="
                flex items-center justify-center
                h-[137px]
                w-[105px]
                border border-gray-200
                bg-gray-100
                sm:w-[140px]
                lg:h-[157px]
                lg:w-[160px]
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
                  lg:h-[157px]
                  lg:w-[160px]
                " />
            </template>

            <div v-else class="
                flex items-center justify-center
                h-[137px]
                w-[105px]
                border border-gray-200
                bg-gray-100
                sm:w-[140px]
                lg:h-[157px]
                lg:w-[160px]
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
      lg:flex-row
      lg:items-start
      lg:justify-between
    ">
            <div class="min-w-0 flex-1">
              <h1 ref="titleRef" :style="titleStyles" :class="[
                'font-bold leading-[1.15] break-words',
                titleState === 'truncated' ? 'title--truncated' : ''
              ]">
                {{ modelValue.family?.name }}
              </h1>
            </div>
          </div>

          <!-- Description fills remaining space -->
          <div class="
    relative
    flex-1
    min-h-0
    space-y-[4px]
    text-[14px]
    leading-[1.6]
    text-[#1d2430]
    sm:text-[15px]
    lg:text-[16px]
    overflow-hidden
  " ref="descriptionRef" :style="!expanded && showReadMore
    ? { maxHeight: `${maxDescriptionHeight - 110}px` }
    : {}">
            <div v-html="cleanedDescription"></div>

            <!-- Fade overlay -->
            <div v-if="!expanded && showReadMore" class="
      absolute
      bottom-0
      left-0
      right-0
      h-6
      pointer-events-none
      bg-gradient-to-t
      from-white
      via-white/90
      to-transparent
    " />
          </div>

          <div v-if="showReadMore" class="mt-2 flex justify-end">
            <button type="button" class="text-xs italic underline  " @click="expanded = !expanded">
              {{ expanded ? ctrans('Read Less') : ctrans('Read More') }}
            </button>
          </div>

          <!-- Always bottom -->
          <div class="
      mt-auto
      pt-1
      flex
      items-center
      gap-4
      flex-wrap
    ">
            <a href="#family-2-extra-description" class="shrink-0">
              <button class="
          h-[38px]
          rounded-xl
          border
          border-[#333]
          px-8
          text-sm
          font-medium
          transition
          hover:bg-gray-50
        " :style="{
          ...getStyles(modelValue?.button?.container?.properties)
        }">
                {{ modelValue?.button?.text || ctrans('Learn more') }}
              </button>
            </a>

            <div v-for="data in modelValue.family.tags" :key="data.name" class="
        flex
        items-center
        px-3
        py-1.5
        sm:px-2
        lg:px-2
        lg:py-2
      ">
              <Image :src="data.web_image" class="h-4 w-4 shrink-0" image-class="object-contain" />

              <span class="
          whitespace-nowrap
          text-[11px]
          font-medium
          text-[#555]
          sm:text-xs
          lg:text-sm
        ">
                {{ data.name }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
</style>