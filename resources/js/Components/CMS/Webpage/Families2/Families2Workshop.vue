<script setup lang="ts">
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'

import Family2Render from './Families2Render.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from './Blueprint'
import { routeType } from '@/types/route'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  modelValue: {
    families: any[]
    collections?: any[]
    container?: { properties?: any }
    settings?: any
  }
  routeEditfamily?: routeType
  webpageData?: any
  blockData?: Object
  indexBlock?: number
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const layout: any = inject("layout", {})
const visibleDrawer = inject('visibleDrawer', undefined)

const allItems = computed(() => [
  ...(props.modelValue?.families || []),
  ...(props.modelValue?.collections || [])
])

const scrollRef = ref<HTMLElement | null>(null)

function scrollLeft() {
  scrollRef.value?.scrollBy({ left: -300, behavior: "smooth" })
}

function scrollRight() {
  scrollRef.value?.scrollBy({ left: 300, behavior: "smooth" })
}

function activateBlock() {
  sendMessageToParent('activeBlock', props.indexBlock)
}

/* ----------------------------------------
   TOUCH SWIPE HANDLER
---------------------------------------- */
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
    if (deltaX < 0) scrollRight()
    else scrollLeft()
  }
  startX = 0
  deltaX = 0
}

onMounted(() => {
  if (scrollRef.value) {
    scrollRef.value.addEventListener("touchstart", onTouchStart, { passive: true })
    scrollRef.value.addEventListener("touchmove", onTouchMove, { passive: true })
    scrollRef.value.addEventListener("touchend", onTouchEnd)
  }
})

onBeforeUnmount(() => {
  if (scrollRef.value) {
    scrollRef.value.removeEventListener("touchstart", onTouchStart)
    scrollRef.value.removeEventListener("touchmove", onTouchMove)
    scrollRef.value.removeEventListener("touchend", onTouchEnd)
  }
})
</script>

<template>
  <div id="families-1">
    <div
      v-if="allItems.length"
      class="px-4 py-10 mx-[30px]"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(props.modelValue.container?.properties, props.screenType)
      }"
      @click="activateBlock"
    >
      <div class="flex items-center gap-4 w-full">

        <!-- LEFT BUTTON -->
        <button class="p-2 rounded-full cursor-pointer shrink-0" @click.stop="scrollLeft">
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>

        <!-- CONTENT SCROLLER WITH SWIPE -->
        <div
          ref="scrollRef"
          class="overflow-x-auto flex gap-6 scrollbar-none py-2 flex-1"
        >
          <div v-for="(item, index) in allItems" :key="`item-${index}`" class="shrink-0">
            <Family2Render :data="item"  :style="getStyles(props.modelValue?.chip?.container?.properties, props.screenType)"/>
          </div>
        </div>

        <!-- RIGHT BUTTON -->
        <button class="p-2 rounded-full cursor-pointer shrink-0" @click.stop="scrollRight">
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

<style>
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
