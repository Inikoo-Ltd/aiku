<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from "vue"

import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { trans } from "laravel-vue-i18n"

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, FreeMode } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/free-mode'

import Family3Render from '@/Iris/Components/Families3Render.vue'
import { getStyles } from '@/Composables/styles'

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

type FamilyOrCollectionType = {
  name: string
  description: string
  images: { source: string }[]
  url: string
}

const props = defineProps<{
  modelValue: {
    families: FamilyOrCollectionType[]
    collections: FamilyOrCollectionType[]
    settings?: { per_row?: { desktop?: number, tablet?: number, mobile?: number } }
    container?: any
    card?: any
    chip?: any
    button?: any
    show_overview_button?: boolean
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock?: number
}>()

const swiperInstance = ref<any>(null)
const containerRef = ref<HTMLElement | null>(null)
const maxHeight = ref(0)

const allItems = computed(() => [...(props.modelValue?.families || [])])

const perRow = computed(() => {
  const cfg = props.modelValue?.settings?.per_row

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

function onSwiper(swiper: any) {
  swiperInstance.value = swiper
}

watch([perRow, spaceBetween], async () => {
  await nextTick()
  swiperInstance.value?.update?.()
})

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
  await computeMaxHeight()
  window.addEventListener('resize', resizeHandler)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeHandler)
})

watch([allItems, () => props.modelValue?.chip, () => props.modelValue?.container, () => props.screenType], async () => {
  await nextTick()
  await computeMaxHeight()
}, { deep: true })
</script>

<template>
  <div ref="containerRef">
    <div class="px-4 py-10" :style="getStyles(props.modelValue?.container?.properties, props.screenType)">
      <div class="relative flex-1 overflow-hidden group">
        <div class="swiper-mask px-8">
          <Swiper :modules="[Navigation, FreeMode]" :loop="false" :slides-per-view="perRow"
            :space-between="spaceBetween" :freeMode="true" :grabCursor="true" :touchRatio="1.2" :allow-touch-move="true"
            :initial-slide="0" @swiper="onSwiper" class="w-full swiper-inner">

            <SwiperSlide v-if="props.modelValue?.show_overview_button" class="flex !w-[220px]">
              <div
                class="family-item w-full h-full cursor-pointer flex flex-col rounded-xl overflow-hidden border bg-white hover:bg-gray-50 transition-all">
                <div :style="{
                  fontWeight: 600,
                  minHeight: maxHeight ? maxHeight + 'px' : undefined,
                  ...getStyles(props.modelValue?.button?.view_more?.properties, props.screenType),
                }" class="flex-1 flex items-center justify-center bg-gray-100">
                  <span class="text-sm font-semibold">
                    {{ trans('View All') }}
                  </span>
                </div>
              </div>
            </SwiperSlide>

            <SwiperSlide v-for="(item, index) in allItems" :key="'item-' + index" class="flex h-auto">
              <div class="w-full h-full flex">
                <Family3Render class="family-item w-full h-full" :data="item" :style="{
                  ...getStyles(props.modelValue?.chip?.container?.properties, props.screenType),
                  fontWeight: 600
                }" :screenType="props.screenType" />
              </div>
            </SwiperSlide>
          </Swiper>
        </div>
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

@media (max-width:768px) {
  .swiper-inner {
    padding-left: 0;
    padding-right: 0;
  }
}
</style>
