<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref, watch } from "vue"

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

const trimmedDescription = computed(() => {
  const html = cleanedDescription.value

  if (typeof DOMParser === "undefined") {
        return html
  }

  const parser = new DOMParser()
  const doc = parser.parseFromString(html, "text/html")

  let count = 0
  const limit = 400

  const walk = (node: Node): boolean => {
    if (count >= limit) {
      node.parentNode?.removeChild(node)
      return true
    }

    if (node.nodeType === Node.TEXT_NODE) {
      const text = node.textContent || ""
      const remaining = limit - count

      if (text.length > remaining) {
        node.textContent = text.slice(0, remaining) + "..."
        count = limit
        return true
      }

      count += text.length
    }

    const children = [...node.childNodes]

    for (const child of children) {
      if (walk(child)) {
        const siblings = [...node.childNodes]
        const index = siblings.indexOf(child)

        siblings.slice(index + 1).forEach((sibling) => {
          sibling.parentNode?.removeChild(sibling)
        })

        return true
      }
    }

    return false
  }

  walk(doc.body)

  return doc.body.innerHTML
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


const titleRef = ref<HTMLElement | null>(null)
const titleState = ref<'single' | 'double' | 'truncated'>('single')
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

onMounted(() => {
  updateTitleSize()

  resizeObserver = new ResizeObserver(() => {
    updateTitleSize()
  })

  if (titleRef.value) {
    resizeObserver.observe(titleRef.value)
  }
})

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
})

watch(
  () => props.fieldValue?.family?.name,
  () => {
    updateTitleSize()
  }
)

const contentClass = computed(() =>
  layout.rightbasket?.show
    ? 'flex flex-col gap-6'
    : 'flex flex-col gap-6 lg:flex-row lg:items-stretch'
)

</script>

<template>
  <section :id="`family-2`" component="family-2-iris">
    <div class="mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 lg:px-14 2xl:max-w-[1800px] 2xl:px-14" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue?.container?.properties),
      width: 'auto'
    }">
      <div :class="contentClass">
        <!-- IMAGE SECTION -->
        <div class="flex shrink-0 justify-center gap-[6px]">
          <!-- IMAGE 1 -->
          <template v-if="hasImage(0)">
            <Image :src="images[0].original" :imageCover="true" :alt="images[0]?.alt || 'family image'" class="
                h-[280px]
                w-[220px]
                object-cover
                sm:w-[290px]
                lg:h-[320px]
                lg:w-[340px]
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
              lg:h-[320px]
              lg:w-[340px]
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
                  lg:h-[157px]
                  lg:w-[160px]
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
                lg:h-[157px]
                lg:w-[160px]
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
                  lg:h-[157px]
                  lg:w-[160px]
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
                lg:h-[157px]
                lg:w-[160px]
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
      lg:flex-row
      lg:items-start
      lg:justify-between
    ">
            <div class="min-w-0 flex-1">
              <h1 ref="titleRef" :style="titleStyles" :class="[
                'font-bold leading-[1.15] break-words',
                titleState === 'truncated' ? 'title--truncated' : ''
              ]">
                {{ fieldValue.family?.name }}
              </h1>
            </div>

            <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"
              class="flex gap-x-1 gap-y-1 md:gap-y-2 offer">
              <DiscountByType :offers_data="fieldValue?.family?.offers_data" :template="bestOffer?.type == 'Category Quantity Ordered Order Interval'
                ? 'active-inactive-gr-v2'
                : 'max_discount'
                " />

              <DiscountByType v-if="
                !(layout?.user?.gr_data?.amnesty ||
                  layout?.user?.gr_data?.customer_is_gr) &&
                bestOffer?.type == 'Category Quantity Ordered Order Interval'
              " :offers_data="fieldValue?.family?.offers_data" :template="'triggers_labels_v2'" />
            </div>
          </div>

          <!-- Description fills remaining space -->
          <div class="
      flex-1
      min-h-0
      space-y-[4px]
      text-[14px]
      leading-[1.6]
      text-[#1d2430]
      sm:text-[15px]
      lg:text-[16px]
      2xl:space-y-2
      2xl:text-[19px]
    " v-html="trimmedDescription" />

          <!-- Always bottom -->
          <div class="
      mt-auto
      pt-1
      flex
      items-center
      gap-4
      flex-wrap
      2xl:pt-8
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
          2xl:h-[48px]
          2xl:px-12
          2xl:text-base
        " :style="{
          ...getStyles(fieldValue?.button?.container?.properties)
        }">
                {{ fieldValue?.button?.text || ctrans('Learn more') }}
              </button>
            </a>

            <div v-for="data in fieldValue.family.tags" :key="data.name" class="
        flex
        items-center
        gap-2
        px-3
        py-1.5
        sm:px-2
        lg:px-2
        lg:py-2
        2xl:px-6
        2xl:py-2.5
      ">
              <Image :src="data.web_image" class="h-4 w-4 shrink-0 2xl:h-5 2xl:w-5" image-class="object-contain" />

              <span class="
          whitespace-nowrap
          text-[11px]
          font-medium
          text-[#555]
          sm:text-xs
          lg:text-sm
          2xl:text-base
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
:deep(.offer .vd-content) {
  @apply flex flex-col justify-center -ml-4 pl-7 pr-3 my-0.5 mr-0.5 rounded-md bg-gray-900 shadow-sm min-w-0;
}

:deep(.offer .vd-triggers) {
  @apply text-[10px] leading-tight opacity-80 truncate max-w-[65px];
}

.editor-class h1 {
  font-size: 1.75rem;
  /* mobile */
}

@media (min-width: 1280px) {
  .editor-class h1 {
    font-size: 1.8rem;
    line-height: 1.5rem;
    /* lg */
  }
}

@media (min-width: 1536px) {
  .editor-class h1 {
    font-size: 2.5rem;
    /* 2xl */
  }
}

.title {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  line-clamp: 3;

  font-size: 2rem;
  line-height: 1.15;
}

.title--truncated {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
}

@container (max-height: 4.6em) {
  .title {
    font-size: 1.75rem;
  }
}
</style>