<script setup lang="ts">
import { inject, ref, computed, nextTick, onMounted, watch, onBeforeUnmount } from "vue"

import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Pagination, Autoplay, Thumbs, FreeMode } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import 'swiper/css/free-mode'

import Family2Render from './Families2Render.vue'
import { getStyles } from '@/Composables/styles'
import { sendMessageToParent } from '@/Composables/Workshop'
import LinkIris from "@/Components/Iris/LinkIris.vue"

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
    settings?: { per_row?: { desktop?: number, tablet?: number, mobile?: number } }
    container?: any
    chip?: any
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock?: number
}>()

const layout: any = inject('layout', {})

const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)

// reactive refs
const navigation = ref<any>(null)
const refreshTrigger = ref(0)
const idxSlideLoading = ref<string | null>(null)
const swiperInstance = ref<any>(null)
const maxHeight = ref(0)
const containerRef = ref<HTMLElement | null>(null)

const refreshCarousel = async (delay = 100) => {
  await new Promise((r) => setTimeout(r, delay))
  refreshTrigger.value++
  await nextTick()
  if (swiperInstance.value && typeof swiperInstance.value.update === 'function') {
    swiperInstance.value.update()
  }
}

const allItems = computed(() => [
  ...(props.fieldValue?.families || []),
  ...(props.fieldValue?.collections || [])
])

const spaceBetween = computed(() => (props.screenType === 'mobile' ? 8 : 24))

function scrollLeft() {
  if (swiperInstance.value?.slidePrev) swiperInstance.value.slidePrev()
}

function scrollRight() {
  if (swiperInstance.value?.slideNext) swiperInstance.value.slideNext()
}

function activateBlock() {
  if (typeof props.indexBlock !== 'undefined') sendMessageToParent('activeBlock', props.indexBlock)
}

function onArrowKeyLeft(evt: KeyboardEvent) {
  if (evt.key === 'Enter' || evt.key === ' ' || evt.key === 'Spacebar') {
    evt.preventDefault()
    scrollLeft()
  }
}

function onArrowKeyRight(evt: KeyboardEvent) {
  if (evt.key === 'Enter' || evt.key === ' ' || evt.key === 'Spacebar') {
    evt.preventDefault()
    scrollRight()
  }
}

watch(() => props.screenType, async () => {
  await refreshCarousel(200)
})

const tryInitNavigation = async () => {
  await nextTick()

  const s = swiperInstance.value
  const prev = prevEl.value
  const next = nextEl.value

  if (!s || !prev || !next) return

  if (s.navigation && s.navigation.initialized) return

  s.params.navigation = {
    ...s.params.navigation,
    prevEl: prev,
    nextEl: next,
  }
  if (s.navigation) {
    try {
      s.navigation.init()
      s.navigation.update()
      s.navigation.initialized = true
    } catch (e) {
      console.warn('Navigation init failed:', e)
    }
  }
}

watch([prevEl, nextEl, swiperInstance], tryInitNavigation, { immediate: true })

async function computeMaxHeight() {
  await nextTick()
  if (!containerRef.value) return
  const nodes = containerRef.value.querySelectorAll<HTMLElement>(".family-item")
  if (!nodes || nodes.length === 0) {
    maxHeight.value = 0
    return
  }
  const heights = [...nodes].map(n => {
    return Math.ceil(n.getBoundingClientRect().height)
  })
  maxHeight.value = Math.max(...heights)
  if (swiperInstance.value && typeof swiperInstance.value.update === 'function') {
    swiperInstance.value.update()
  }
}

let resizeHandler = () => {
  // small debounce
  clearTimeout((resizeHandler as any)._t)
  ;(resizeHandler as any)._t = setTimeout(() => {
    computeMaxHeight()
  }, 120)
}

onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value,
  }
  await nextTick()
  if (swiperInstance.value && typeof swiperInstance.value.update === 'function') {
    swiperInstance.value.update()
    await tryInitNavigation()
  }

  // initial compute
  await computeMaxHeight()

  // watch resize
  window.addEventListener('resize', resizeHandler)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeHandler)
})

// recompute when items change or chip/container styles change or refreshTrigger toggles
watch([allItems, () => props.fieldValue?.chip, () => props.fieldValue?.container, refreshTrigger], async () => {
  await nextTick()
  await computeMaxHeight()
}, { deep: true })

</script>

<template>
  <div id="families-2" :key="refreshTrigger" ref="containerRef">
    <div v-if="allItems.length" class="px-4 py-10" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(props.fieldValue.container?.properties, props.screenType)
    }" @click="activateBlock" >
      <div class="flex items-center gap-4 w-full">

        <button  v-if="swiperInstance?.allowSlidePrev" ref="prevEl" class="p-2 rounded-full cursor-pointer shrink-0" @click.stop="scrollLeft"
          @keydown="onArrowKeyLeft" aria-label="Scroll left" type="button">
          <FontAwesomeIcon :icon="['fas', 'chevron-circle-left']" />
        </button>

        <Swiper
          @swiper="(s) => (swiperInstance = s)"
          :modules="[Autoplay, Thumbs, FreeMode, Navigation]"
          :loop="true"
          slides-per-view="auto"
          :space-between="spaceBetween"
          :freeMode="true"
          navigation
          class="flex-1"
        >
          <SwiperSlide
            v-for="(item, index) in allItems"
            :key="'item-' + index"
            class="!w-auto flex"
          >
            <LinkIris :href="item.url" :style="{ textDecoration: 'none' }"  @start="() => idxSlideLoading = `family${index}`" @finish="() => idxSlideLoading = null" class="h-full flex">
              <Family2Render
                class="family-item h-full flex items-center"
                :data="item"
                :style="{
                  ...getStyles(props.fieldValue?.chip?.container?.properties, props.screenType)
                }"
                :screenType="props.screenType"
              />
            </LinkIris>
          </SwiperSlide>
        </Swiper>

        <button v-if="swiperInstance?.allowSlideNext" ref="nextEl" class="p-2 rounded-full cursor-pointer shrink-0" @click.stop="scrollRight"
          @keydown="onArrowKeyRight" aria-label="Scroll right" type="button">
          <FontAwesomeIcon :icon="['fas', 'chevron-circle-right']"  />
        </button>

      </div>
    </div>
  </div>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}

.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

:deep(.swiper-button-prev),
:deep(.swiper-button-next) {
  display: none !important;
}
</style>
