<script setup lang="ts">
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'

import Family2Render from './Families2Render.vue'
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

type FamilyOrCollectionType = {
  name: string,
  description: string,
  images: { source: string }[],
  url: string
}

const props = defineProps<{
  fieldValue: {
    families: FamilyOrCollectionType[]
    collections: FamilyOrCollectionType[]
    settings?: {
      per_row?: {
        desktop?: number
        tablet?: number
        mobile?: number
      }
    }
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})
const visibleDrawer = inject('visibleDrawer', undefined)

const idxSlideLoading = ref<string | null>(null)

// MERGED ITEMS
const allItems = computed(() => [
  ...(props.fieldValue?.families || []),
  ...(props.fieldValue?.collections || [])
])

// SCROLL HANDLERS
const scrollRef = ref<HTMLElement | null>(null)

function scrollLeft() {
  scrollRef.value?.scrollBy({ left: -320, behavior: "smooth" })
}

function scrollRight() {
  scrollRef.value?.scrollBy({ left: 320, behavior: "smooth" })
}

// TOUCH SWIPE
let startX = 0
let deltaX = 0

function onTouchStart(e: TouchEvent) {
  startX = e.touches[0].clientX
}

function onTouchMove(e: TouchEvent) {
  deltaX = e.touches[0].clientX - startX
}

function onTouchEnd() {
  if (Math.abs(deltaX) > 80) {
    deltaX < 0 ? scrollRight() : scrollLeft()
  }
  startX = 0
  deltaX = 0
}

// RESPONSIVE GRID (Fallback desktop=4 / tablet=4 / mobile=2)
const responsiveGridClass = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row || {}

  const columnCount = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2
  }

  const count = columnCount[props.screenType] ?? 1
  return `grid-cols-${count}`
})

onMounted(() => {
  const el = scrollRef.value
  if (!el) return

  el.addEventListener("touchstart", onTouchStart, { passive: true })
  el.addEventListener("touchmove", onTouchMove, { passive: true })
  el.addEventListener("touchend", onTouchEnd)
})

onBeforeUnmount(() => {
  const el = scrollRef.value
  if (!el) return

  el.removeEventListener("touchstart", onTouchStart)
  el.removeEventListener("touchmove", onTouchMove)
  el.removeEventListener("touchend", onTouchEnd)
})
</script>

<template>
  <div id="families-1">
    <div
      v-if="allItems.length"
      class="px-4 py-10"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(props.fieldValue?.container?.properties, props.screenType)
      }"
    >
      <div class="flex items-center gap-4 w-full">

        <!-- LEFT BUTTON -->
        <button
          class="p-2 rounded-full cursor-pointer shrink-0"
          @click.stop="scrollLeft"
        >
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>

        <!-- SCROLLER WRAPPER -->
        <div
          ref="scrollRef"
          class="overflow-x-auto flex gap-6 py-2 flex-1 scrollbar-hide"
          style="touch-action: pan-x; -webkit-overflow-scrolling: touch;"
        >
          <!-- FAMILIES -->
          <LinkIris
            v-for="(item, index) in props.fieldValue.families"
            :key="'family' + index"
            :href="`${item.url}`"
            type="internal"
            class="relative flex-shrink-0"
            @start="() => idxSlideLoading = `family${index}`"
            @finish="() => idxSlideLoading = null"
          >
            <Family2Render :data="item" :style="getStyles(props.fieldValue?.chip?.container?.properties, props.screenType)"/>
            <div
              v-if="idxSlideLoading === `family${index}`"
              class="absolute inset-0 grid place-content-center bg-black/50 text-white text-5xl rounded"
            >
              <LoadingIcon />
            </div>
          </LinkIris>

          <!-- COLLECTIONS -->
          <LinkIris
            v-for="(item, index) in props.fieldValue.collections"
            :key="'collection' + index"
            :href="`${item.url}`"
            type="internal"
            class="relative flex-shrink-0"
            @start="() => idxSlideLoading = `collection${index}`"
            @finish="() => idxSlideLoading = null"
          >
            <Family1Render :data="item" />
            <div
              v-if="idxSlideLoading === `collection${index}`"
              class="absolute inset-0 grid place-content-center bg-black/50 text-white text-5xl rounded"
            >
              <LoadingIcon />
            </div>
          </LinkIris>
        </div>

        <!-- RIGHT BUTTON -->
        <button
          class="p-2 rounded-full cursor-pointer shrink-0"
          @click.stop="scrollRight"
        >
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </button>

      </div>
    </div>
  </div>
</template>
