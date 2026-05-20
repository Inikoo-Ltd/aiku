<script setup lang="ts">
import { inject, ref, computed, nextTick, onMounted, watch, onBeforeUnmount } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Autoplay, Thumbs, FreeMode, Mousewheel } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/free-mode'

import Family3Render from '@/Iris/Components/Families3Render.vue'
import { getStyles } from '@/Composables/styles'
import { sendMessageToParent } from '@/Composables/Workshop'
import LinkIris from "@/Iris/Components/LinkIris.vue"

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
    webpage_data?: {
      webpage_type : string
      overview_url : string
    }
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock?: number | string
}>()

const layout: any = inject('layout', {})

const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const swiperInstance = ref<any>(null)

const refreshTrigger = ref(0)
const containerRef = ref<HTMLElement | null>(null)
const maxHeight = ref(0)

const isBeginning = ref(true)
const isEnd = ref(false)

const updateEdges = (swiper: any) => {
  isBeginning.value = swiper.isBeginning
  isEnd.value = swiper.isEnd
}

const allItems = computed(() => [
  ...(props.fieldValue?.families || []),
])

const perRow = computed(() => {
  const cfg = props.fieldValue?.settings?.per_row

  if (props.screenType === 'mobile') {
    return cfg?.mobile ?? 2.2
  }

  if (props.screenType === 'tablet') {
    return cfg?.tablet ?? 4
  }

  return cfg?.desktop ?? 6.5
})

const spaceBetween = computed(() => {
  if (props.screenType === 'mobile') return 12
  if (props.screenType === 'tablet') return 16
  return 24
})


function scrollLeft() {
  if (swiperInstance.value?.slidePrev) swiperInstance.value.slidePrev()
}

function scrollRight() {
  if (swiperInstance.value?.slideNext) swiperInstance.value.slideNext()
}

function activateBlock() {
  if (typeof props.indexBlock !== 'undefined') {
    sendMessageToParent('activeBlock', props.indexBlock)
  }
}

watch(() => props.screenType, async () => {
  await refreshCarousel(200)
})

const refreshCarousel = async (delay = 120) => {
  await new Promise(r => setTimeout(r, delay))
  refreshTrigger.value++
  await nextTick()
  swiperInstance.value?.update?.()
}

const tryInitNavigation = async () => {
  await nextTick()

  if (props.screenType === 'mobile') return

  const s = swiperInstance.value
  const prev = prevEl.value
  const next = nextEl.value

  if (!s || !prev || !next) return
  if (s.navigation?.initialized) return

  s.params.navigation = {
    ...s.params.navigation,
    prevEl: prev,
    nextEl: next,
  }

  try {
    s.navigation.init()
    s.navigation.update()
    s.navigation.initialized = true
  } catch (e) {
    console.warn('nav init fail', e)
  }
}

watch([prevEl, nextEl, swiperInstance], tryInitNavigation, { immediate: true })


async function computeMaxHeight() {
  await nextTick()
  if (!containerRef.value) return

  const nodes = containerRef.value.querySelectorAll<HTMLElement>(".family-item")
  if (!nodes.length) {
    maxHeight.value = 0
    return
  }

  const heights = [...nodes].map(n => Math.ceil(n.getBoundingClientRect().height))
  maxHeight.value = Math.max(...heights)
  swiperInstance.value?.update?.()
}

let resizeHandler = () => {
  clearTimeout((resizeHandler as any)._t)
    ; (resizeHandler as any)._t = setTimeout(() => computeMaxHeight(), 120)
}

onMounted(async () => {
  await nextTick()
  swiperInstance.value?.update?.()
  await tryInitNavigation()
  await computeMaxHeight()
  window.addEventListener('resize', resizeHandler)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeHandler)
})

watch([allItems, () => props.fieldValue?.chip, () => props.fieldValue?.container, refreshTrigger], async () => {
  await nextTick()
  await computeMaxHeight()
}, { deep: true })
console.log('families',props)
</script>

<template>
  <div :id="fieldValue?.id ? fieldValue?.id : 'families-3'+indexBlock" component="families-3" :key="refreshTrigger"
    ref="containerRef">
    <div v-if="allItems.length" class="px-4 py-10" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(props.fieldValue.container?.properties, props.screenType)
    }" @click="activateBlock">
      <div class="relative flex-1 overflow-hidden group">

        <button v-if="props.screenType !== 'mobile' && !isBeginning" ref="prevEl"
          class="absolute left-0 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
          @click.stop="scrollLeft" type="button">
          <FontAwesomeIcon :icon="['fas', 'chevron-circle-left']" class="text-4xl" />
        </button>

        <div class="swiper-mask px-8">
          <Swiper @swiper="(s: any) => { swiperInstance = s; updateEdges(s) }" @slideChange="updateEdges"
            @reachBeginning="updateEdges" @reachEnd="updateEdges"
            :modules="[Autoplay, Thumbs, FreeMode, Navigation, Mousewheel]" :loop="false" :slides-per-view="perRow"
            :space-between="spaceBetween" :freeMode="true" :grabCursor="true" :touchRatio="1.2" navigation
            class="w-full swiper-inner" :mousewheel="{
              forceToAxis: true,
              releaseOnEdges: true,
              sensitivity: 1
            }">


            <SwiperSlide class="flex !w-[220px]" v-if="fieldValue?.show_overview_button" >
              <LinkIris :href="fieldValue?.webpage_data?.overview_url" type="internal" class="w-full h-full flex">
                <div
                  class="family-item w-full h-full cursor-pointer flex flex-col rounded-xl overflow-hidden border bg-white hover:bg-gray-50 transition-all"
                 >
                  <!-- TOP AREA (fill space like image) -->
                  <div :style="{
                    fontWeight: 600,
                    minHeight: maxHeight ? maxHeight + 'px' : undefined,
                    ...getStyles(props.fieldValue?.button?.view_more?.properties, props.screenType),
                  }"  class="flex-1 flex items-center justify-center bg-gray-100">
                    <span class="text-sm font-semibold">
                      {{trans("View All")}}
                    </span>
                  </div>
                </div>
              </LinkIris>
            </SwiperSlide>

            <SwiperSlide v-for="(item, index) in allItems" :key="'item-' + index" class="flex h-auto">

              <LinkIris :href="item.url" class="w-full h-full flex" v-slot="{ isLoading } = { isLoading: false }">
                <Family3Render
                  class="family-item w-full h-full"
                  :data="item"
                  :isLoading="isLoading"
                  :style="{
                    ...getStyles(props.fieldValue?.chip?.container?.properties, props.screenType),
                    fontWeight: 600
                  }"
                  :screenType="props.screenType"
                />
              </LinkIris>
            </SwiperSlide>
          </Swiper>
        </div>

        <button v-if="props.screenType !== 'mobile' && swiperInstance?.allowSlideNext && !isEnd" ref="nextEl" class="absolute right-0 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full text-gray-800
           opacity-0 group-hover:opacity-100 transition-opacity duration-200" @click.stop="scrollRight" type="button">
          <FontAwesomeIcon :icon="['fas', 'chevron-circle-right']" class="text-4xl" />
        </button>

      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.swiper-button-prev),
:deep(.swiper-button-next) {
  display: none !important;
}



.swiper-inner {
  box-sizing: border-box;
}

/* mobile optimization */
@media (max-width:768px) {
  .swiper-inner {
    padding-left: 0;
    padding-right: 0;
  }
}
</style>
