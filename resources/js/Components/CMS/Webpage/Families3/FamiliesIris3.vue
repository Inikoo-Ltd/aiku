<script setup lang="ts">
import { inject, ref, computed, nextTick, onMounted, watch, onBeforeUnmount } from "vue"

import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Autoplay, Thumbs, FreeMode } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/free-mode'

import Family3Render from './Families3Render.vue'
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
const swiperInstance = ref<any>(null)

const refreshTrigger = ref(0)
const containerRef = ref<HTMLElement | null>(null)
const maxHeight = ref(0)

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

  return cfg?.desktop ?? 8.7
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
  ;(resizeHandler as any)._t = setTimeout(() => computeMaxHeight(), 120)
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
</script>

<template>
  <div id="families-3" :key="refreshTrigger" ref="containerRef">
    <div
      v-if="allItems.length"
      class="px-4 py-10"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(props.fieldValue.container?.properties, props.screenType)
      }"
      @click="activateBlock"
    >
      <div class="flex items-center gap-4 w-full">
        <div class="relative flex-1 overflow-hidden">

          <button
            v-if="props.screenType !== 'mobile' && swiperInstance?.allowSlidePrev"
            ref="prevEl"
            class="absolute left-0 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full text-gray-500"
            @click.stop="scrollLeft"
            type="button"
          >
            <FontAwesomeIcon :icon="['fas','chevron-circle-left']" />
          </button>

          <div class="swiper-mask">
            <Swiper
              @swiper="(s:any)=> swiperInstance = s"
              :modules="[Autoplay, Thumbs, FreeMode, Navigation]"
              :loop="props.screenType !== 'mobile'"
              :slides-per-view="perRow"
              :space-between="spaceBetween"
              :freeMode="true"
              :grabCursor="props.screenType === 'mobile'"
              :touchRatio="1.2"
              navigation
              class="w-full swiper-inner"
            >
              <SwiperSlide
                v-for="(item,index) in allItems"
                :key="'item-'+index"
                class="flex"
              >
                <LinkIris :href="item.url">
                  <Family3Render
                    class="family-item"
                    :data="item"
                    :style="{
                      ...getStyles(props.fieldValue?.chip?.container?.properties, props.screenType),
                    }"
                    :screenType="props.screenType"
                  />
                </LinkIris>
              </SwiperSlide>
            </Swiper>
          </div>

          <button
            v-if="props.screenType !== 'mobile' && swiperInstance?.allowSlideNext"
            ref="nextEl"
            class="absolute right-0 top-1/2 -translate-y-1/2 z-20 p-2 rounded-full text-gray-500"
            @click.stop="scrollRight"
            type="button"
          >
            <FontAwesomeIcon :icon="['fas','chevron-circle-right']" />
          </button>

        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.swiper-button-prev),
:deep(.swiper-button-next){
  display:none!important;
}

/* desktop fade mask */
.swiper-mask{
  --mask-size:48px;
  overflow:hidden;

  -webkit-mask-image:linear-gradient(to right,
    transparent 0,
    black var(--mask-size),
    black calc(100% - var(--mask-size)),
    transparent 100%);

  mask-image:linear-gradient(to right,
    transparent 0,
    black var(--mask-size),
    black calc(100% - var(--mask-size)),
    transparent 100%);
}

.swiper-inner{
  padding-left:48px;
  padding-right:48px;
  box-sizing:border-box;
}

/* mobile optimization */
@media (max-width:768px){
  .swiper-mask{
    -webkit-mask-image:none;
    mask-image:none;
  }

  .swiper-inner{
    padding-left:0;
    padding-right:0;
  }
}
</style>
