<script setup lang="ts">
import { inject, ref, computed, nextTick, watch } from "vue"

import { library } from '@fortawesome/fontawesome-svg-core'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'

import Family3Render from './Families3Render.vue'
import { getStyles } from '@/Composables/styles'
import { sendMessageToParent } from '@/Composables/Workshop'

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

const layout:any = inject('layout', {})

const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const swiperInstance = ref<any>(null)

const allItems = computed(() => [
  ...(props.modelValue?.families || [])
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

const slideWidth = computed(() => {
  const gap = spaceBetween.value
  const totalGap = gap * (perRow.value - 1)
  return `calc((100% - ${totalGap}px) / ${perRow.value})`
})

function scrollLeft() {
  swiperInstance.value?.slidePrev()
}

function scrollRight() {
  swiperInstance.value?.slideNext()
}

function activateBlock() {
  if (typeof props.indexBlock !== 'undefined') {
    sendMessageToParent('activeBlock', props.indexBlock)
  }
}

/* ==== SAFE NAVIGATION INIT ==== */
function bindNavigation(swiper:any) {
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

function onSwiper(swiper:any) {
  swiperInstance.value = swiper
  bindNavigation(swiper)
}

/* update when screen/perRow change */
watch(() => props.screenType, async () => {
  await nextTick()
  swiperInstance.value?.update?.()
})
</script>

<template>
  <div id="families-3">

    <div
      v-if="allItems.length"
      class="px-4 py-10"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(props.modelValue.container?.properties, props.screenType)
      }"
      @click="activateBlock"
    >
      <div class="relative w-full">

        <!-- left -->
        <button
          ref="prevEl"
          type="button"
          class="absolute left-0 top-1/2 -translate-y-1/2 z-10 p-2 text-gray-500"
          @click.stop="scrollLeft"
        >
          <FontAwesomeIcon :icon="['fas','chevron-circle-left']"/>
        </button>

        <!-- swiper -->
        <div class="mx-8">
          <Swiper
            :modules="[Navigation]"
            :loop="true"
            :slides-per-view="perRow"
            :space-between="spaceBetween"
            :allow-touch-move="true"
            :navigation="true"
            @swiper="onSwiper"
            class="w-full swiper-mask"
          >
            <SwiperSlide v-for="(item, index) in allItems" :key="'item-' + index" class="flex h-auto">
                <div  class="w-full h-full flex">
                  <Family3Render class="family-item w-full h-full" :data="item" :style="{
                    ...getStyles(props.modelValue?.chip?.container?.properties, props.screenType),
                    fontWeight: 600
                  }" :screenType="props.screenType" />
                </div>
              </SwiperSlide>
          </Swiper>
        </div>

        <!-- right -->
        <button
          ref="nextEl"
          type="button"
          class="absolute right-0 top-1/2 -translate-y-1/2 z-10 p-2 text-gray-500"
          @click.stop="scrollRight"
        >
          <FontAwesomeIcon :icon="['fas','chevron-circle-right']"/>
        </button>

      </div>
    </div>

    <EmptyState v-else :data="{ title:'Empty Families' }" />
  </div>
</template>

<style scoped>
:deep(.swiper-button-prev),
:deep(.swiper-button-next){
  display:none !important;
}


</style>
