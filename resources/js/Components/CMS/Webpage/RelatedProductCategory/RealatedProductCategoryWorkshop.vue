<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from "vue"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"

import { faChevronLeft, faChevronRight, faImage } from "@far"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"


library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

type FamilyOrCollectionType = {
  name: string
  description: string
  images: { source: string }[]
  image?: string
  url: string
}

const props = defineProps<{
  modelValue: {
    settings?: {
      product_category?: FamilyOrCollectionType[]
      per_row?: { desktop?: number, tablet?: number, mobile?: number }
    }
    container?: any
    chip?: any
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock?: number
}>()

const emits = defineEmits<{
  (e: "update:modelValue", value: string): void
  (e: "autoSave"): void
}>()

const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const swiperInstance = ref<any>(null)
const refreshTrigger = ref(0)
const containerRef = ref<HTMLElement | null>(null)

const allItems = computed(() => [
  ...(props.modelValue?.settings?.product_category || [])
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

const showNavigation = computed(() => allItems.value.length > Math.ceil(perRow.value))

const wrapperSpacingClass = computed(() => {
  if (props.screenType === 'mobile') return 'px-4'
  if (props.screenType === 'tablet') return 'px-6'
  return 'px-8'
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
  bindNavigation(swiperInstance.value)
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
  bindNavigation(swiperInstance.value)
  await computeMaxHeight()
}, { deep: true })

console.log(props)
</script>

<template>
  <div ref="containerRef" class="w-full overflow-hidden" :class="wrapperSpacingClass">
    <div class="px-3 py-6 pb-2">
      <div class="px-4 py-6 pb-2">
        <div class="text-3xl text-center flex justify-center font-semibold text-gray-800">
          <EditorV2 v-model="modelValue.title" @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
            name: webpageData.images_upload_route.name,
            parameters: { modelHasWebBlocks: blockData.id }
          }" />

        </div>
      </div>
    </div>
    <div class="relative w-full">
      <button v-if="showNavigation" ref="prevEl" type="button"
        class="absolute left-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full  transition  hover:text-gray-950 md:flex"
        aria-label="Previous category">
        <FontAwesomeIcon :icon="faChevronLeft" class="text-xl" fixed-width />
      </button>

      <Swiper :modules="[Navigation]" :loop="true" :slides-per-view="perRow" :space-between="spaceBetween"
        :allow-touch-move="true" :navigation="true" :initial-slide="0" @swiper="onSwiper" class="w-full">
        <SwiperSlide v-for="(data, index) in allItems" :key="'item-' + index" class="h-auto">
          <a :href="data?.url || '#'" class="group flex h-full flex-col">
            <!-- Image -->
            <div class="aspect-square overflow-hidden bg-gray-100">
              <Image v-if="data?.image" :src="data.image" :alt="data.name"
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" />

              <div v-else class="flex h-full items-center justify-center">
                <FontAwesomeIcon :icon="faImage" class="text-3xl text-gray-300" />
              </div>
            </div>

            <!-- Title -->
            <div class="mt-3 min-h-[48px]">
              <h4 class="text-[0.8rem] leading-6 text-gray-900 line-clamp-2">
                {{ data?.name }}
              </h4>
            </div>
          </a>
        </SwiperSlide>
      </Swiper>

      <button v-if="showNavigation" ref="nextEl" type="button"
        class="absolute right-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 iitems-center justify-center rounded-full  transition  hover:text-gray-950 md:flex"
        aria-label="Next category">
        <FontAwesomeIcon :icon="faChevronRight" class="text-xl" fixed-width />
      </button>
    </div>
  </div>
</template>

<style scoped>
:deep(.swiper) {
  width: 100%;
}

:deep(.swiper-button-prev),
:deep(.swiper-button-next) {
  display: none !important;
}


:deep(.swiper-wrapper) {
  align-items: stretch;
}

:deep(.swiper-slide) {
  height: auto;
  display: flex;
}

:deep(.swiper-slide > a) {
  width: 100%;
}
</style>
