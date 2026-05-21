<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from "vue"

import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { trans } from "laravel-vue-i18n"

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'

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
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock?: number
}>()


const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const swiperInstance = ref<any>(null)
const refreshTrigger = ref(0)
const containerRef = ref<HTMLElement | null>(null)

const allItems = computed(() => [
  {
    __type: 'view_all'
  },
  ...(props.modelValue?.families || [])
])
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





/* ==== SAFE NAVIGATION INIT ==== */
function bindNavigation(swiper: any) {
  nextTick(() => {
    if (!swiper) return
    if (!prevEl.value || !nextEl.value) return

    swiper.params.navigation.prevEl = prevEl.value
    swiper.params.navigation.nextEl = nextEl.value

    if (swiper.navigation) {
      swiper.navigation.destroy()
      swiper.navigation.init()
      swiper.navigation.update()
    }
  })
}

const maxHeight = ref(0)

function onSwiper(swiper: any) {
  swiperInstance.value = swiper
  bindNavigation(swiper)
}

/* update when screen/perRow change */
watch(() => props.screenType, async () => {
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
  maxHeight.value = Math.max(...heights) - 5
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

watch([allItems, () => props.modelValue?.chip, () => props.modelValue?.container, refreshTrigger], async () => {
  await nextTick()
  await computeMaxHeight()
}, { deep: true })

</script>

<template>
  <!-- swiper + view all -->
  <div class="mx-8 flex items-stretch" :style="{ gap: `${spaceBetween}px`}">

    <!-- view all -->
    <div class="shrink-0" :style="{
      width: `calc((100% - (${perRow - 1} * ${spaceBetween}px)) / ${perRow})`
    }">
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
    </div>

    <!-- swiper -->
    <div class="min-w-0" :style="{
      width: `calc(100% - ((100% - (${perRow - 1} * ${spaceBetween}px)) / ${perRow}) - ${spaceBetween}px)`
    }">
      <Swiper :modules="[Navigation]" :loop="true" :slides-per-view="perRow" :space-between="spaceBetween"
        :allow-touch-move="true" :navigation="true" :initial-slide="0" @swiper="onSwiper" class="w-full swiper-mask">
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
</template>

<style scoped>
:deep(.swiper-button-prev),
:deep(.swiper-button-next) {
  display: none !important;
}
</style>
