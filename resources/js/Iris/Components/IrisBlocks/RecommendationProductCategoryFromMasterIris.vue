<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from "vue"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink, faChevronCircleLeft, faChevronCircleRight } from '@fal'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"


import { faChevronLeft, faChevronRight, faImage } from "@far"


library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)


const props = defineProps<{
    fieldValue: {
        value: string
    }
    screenType: "mobile" | "tablet" | "desktop"
    indexBlock:number
    code?: string
}>()


const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const swiperInstance = ref<any>(null)
const refreshTrigger = ref(0)
const containerRef = ref<HTMLElement | null>(null)

const allItems = computed(() => [
  ...(props.fieldValue?.product_category_recommended || [])
])

const perRow = computed(() => {
  if (props.screenType === 'mobile') {
    return  2
  }

  if (props.screenType === 'tablet') {
    return 4
  }

  return  5
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

watch([allItems, () => props.fieldValue?.chip, () => props.fieldValue?.container, refreshTrigger], async () => {
  await nextTick()
  bindNavigation(swiperInstance.value)
  await computeMaxHeight()
}, { deep: true })

console.log(props)
</script>

<template>
  <div v-if="allItems.length >= (fieldValue?.recommendation_settings?.min_amt_shown || 5)"
    :id="fieldValue?.id ? fieldValue?.id : 'recommended-productCategory-from-master' + indexBlock" class="w-full pb-6 md:px-[50px]"
    :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType),
      width: 'auto'
    }" :dropdown-type="props.fieldValue?.settings?.products_data?.type">

    <div class="px-4 py-6  flex items-center justify-center">
      <div class="text-3xl font-semibold text-gray-800">
        <div v-if="fieldValue?.recommendation_settings.title" v-html="fieldValue?.recommendation_settings.title">
        </div>
        <div v-else>
          {{ ctrans("Related Categories") }}
        </div>
      </div>
    </div>

    <div class="relative w-full">
      <button v-if="showNavigation" ref="prevEl" type="button"
        class="absolute left-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full  transition  md:flex"
        aria-label="Previous category">
        <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl" fixed-width />
      </button>

       <div class="py-4 md:px-12 lg:px-[44px] px-4">
          <Swiper :modules="[Navigation]" :loop="true" :slides-per-view="perRow" :space-between="spaceBetween"
        :allow-touch-move="true" :navigation="true" :initial-slide="0" @swiper="onSwiper" class="w-full">
        <SwiperSlide v-for="(data, index) in allItems" :key="'item-' + index" class="h-auto">
          <a :href="data?.url || '#'" class="group flex h-full flex-col ">
            <!-- Image -->
            <div class="aspect-square overflow-hidden bg-gray-100">
              <Image v-if="data?.web_images?.main?.original" :src="data?.web_images?.main?.original" :alt="data.name"
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

       </div>
    

      <button v-if="showNavigation" ref="nextEl" type="button"
        class="absolute right-0 top-1/2 z-10 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full  transition  md:flex"
        aria-label="Next category">
        <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl" fixed-width />
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
