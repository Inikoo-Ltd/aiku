<script setup lang="ts">
import { inject, ref, watch, computed, nextTick, onMounted } from "vue"

// FontAwesome - correct imports
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faLink } from '@fortawesome/free-solid-svg-icons'
import { faStar, faCircle } from '@fortawesome/free-regular-svg-icons'
import { faChevronCircleLeft, faChevronCircleRight } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

// Swiper (Vue 8+ / modules API)
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Pagination, Autoplay, Thumbs, FreeMode } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import 'swiper/css/free-mode'

import Family2Render from './Families2Render.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { getStyles } from '@/Composables/styles'
import { sendMessageToParent } from '@/Composables/Workshop'

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronCircleLeft,
  faChevronCircleRight
)


const props = defineProps<{
  modelValue: {
    families: any[]
    collections?: any[]
    container?: { properties?: any }
    chip?: { container?: { properties?: any } }
  }
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock?: number
}>()


const layout: any = inject('layout', {})
const visibleDrawer = inject('visibleDrawer', undefined)
const prevEl = ref<HTMLElement | null>(null)
const nextEl = ref<HTMLElement | null>(null)
const navigation = ref<any>(null)
const refreshTrigger = ref(0)

const refreshCarousel = async (delay = 100) => {
  await new Promise((r) => setTimeout(r, delay))
  refreshTrigger.value++
  await nextTick()
}



const allItems = computed(() => [
  ...(props.modelValue?.families || []),
  ...(props.modelValue?.collections || [])
])


const swiperInstance = ref<any>(null)


const spaceBetween = computed(() => (props.screenType === 'mobile' ? 8 : 24))

function scrollLeft() {
  if (swiperInstance.value?.slidePrev) swiperInstance.value.slidePrev()
}

function scrollRight() {
  if (swiperInstance.value?.slideNext) swiperInstance.value.slideNext()
}

function activateBlock() {
  sendMessageToParent('activeBlock', props.indexBlock)
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

watch(()=>props.screenType, async () => {
  await refreshCarousel(200)
})


onMounted(async () => {
  await nextTick()
  navigation.value = {
    prevEl: prevEl.value,
    nextEl: nextEl.value,
  }
  await nextTick()
  swiperInstance.value?.update()
})

</script>

<template>
  <div id="families-1" :key="refreshTrigger">
    <div v-if="allItems.length" class="px-4 py-10" :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
      ...getStyles(props.modelValue.container?.properties, props.screenType)
    }" @click="activateBlock">
      <div class="flex items-center gap-4 w-full">

        <!-- LEFT BUTTON -->
        <button class="p-2 rounded-full cursor-pointer shrink-0" @click.stop="scrollLeft" @keydown="onArrowKeyLeft"
          aria-label="Scroll left" type="button">
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>

        <!-- SWIPER -->
        <Swiper @swiper="(s) => (swiperInstance = s)"  :key="refreshTrigger" :modules="[Navigation, Pagination, Autoplay, Thumbs, FreeMode]"  :loop="true"
          slides-per-view="auto" :space-between="spaceBetween" :freeMode="true" class="flex-1" :navigation="navigation">
          <SwiperSlide v-for="(item, index) in allItems" :key="'item-' + index" class="!w-auto">
            <Family2Render :data="item" :style="getStyles(props.modelValue?.chip?.container?.properties, props.screenType)"  :screenType/>
          </SwiperSlide>
        </Swiper>

        <!-- RIGHT BUTTON -->
        <button class="p-2 rounded-full cursor-pointer shrink-0" @click.stop="scrollRight" @keydown="onArrowKeyRight"
          aria-label="Scroll right" type="button">
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </button>

      </div>
    </div>

    <EmptyState v-else :data="{ title: 'Empty Families' }">
      <template v-if="visibleDrawer !== undefined" #button-empty-state>
        <Button label="Select sub-department to preview family list" type="secondary" />
      </template>
    </EmptyState>
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
</style>
