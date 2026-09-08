<script setup lang="ts">
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faChevronCircleLeft,
  faChevronCircleRight,
  faSearchPlus,
  faSearchMinus,
} from '@fal'
import Image from '@common/Components/Image.vue'
import Dialog from 'primevue/dialog'

const props = defineProps<{
  images: { source: any; thumbnail?: any; zoom?: any; alt?: string }[]
  video?: string
  showVideo?: boolean
}>()

const visible = defineModel<boolean>('visible', { required: true })
const selectedIndex = defineModel<number>('index', { default: 0 })

const MIN_ZOOM = 1
const MAX_ZOOM = 4
const ZOOM_STEP = 0.5
const CLICK_ZOOM_SCALE = 2

const zoomScale = ref(MIN_ZOOM)
const zoomOrigin = ref({ x: 50, y: 50 })
const isZoomed = computed(() => zoomScale.value > MIN_ZOOM)

const currentImage = computed(() => props.images[selectedIndex.value])

const zoomImageStyle = computed(() => ({
  objectFit: 'contain',
  transform: `scale(${zoomScale.value})`,
  transformOrigin: `${zoomOrigin.value.x}% ${zoomOrigin.value.y}%`,
  transition: 'transform 120ms ease-out',
  willChange: 'transform',
}))

function setZoom(scale: number, event?: MouseEvent) {
  zoomScale.value = Math.min(Math.max(scale, MIN_ZOOM), MAX_ZOOM)

  if (!isZoomed.value) {
    zoomOrigin.value = { x: 50, y: 50 }
    return
  }
  if (!event) return

  const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect()
  zoomOrigin.value = {
    x: ((event.clientX - bounds.left) / bounds.width) * 100,
    y: ((event.clientY - bounds.top) / bounds.height) * 100,
  }
}

let wasPannedByTouch = false

function toggleZoom(event: MouseEvent) {
  if (wasPannedByTouch) {
    wasPannedByTouch = false
    return
  }
  setZoom(isZoomed.value ? MIN_ZOOM : CLICK_ZOOM_SCALE, event)
}

function panZoom(event: PointerEvent) {
  if (!isZoomed.value) return
  wasPannedByTouch = event.pointerType !== 'mouse'
  setZoom(zoomScale.value, event)
}

function wheelZoom(event: WheelEvent) {
  setZoom(zoomScale.value + (event.deltaY < 0 ? ZOOM_STEP : -ZOOM_STEP), event)
}

watch([selectedIndex, visible], () => setZoom(MIN_ZOOM))

const onPrevNavigation = () => {
  selectedIndex.value = (selectedIndex.value - 1 + props.images.length) % props.images.length
}

const onNextNavigation = () => {
  selectedIndex.value = (selectedIndex.value + 1) % props.images.length
}

function onKeydown(e: KeyboardEvent) {
  if (!visible.value) return
  if (e.key === 'Escape' || e.key === 'Esc') visible.value = false
  if (props.showVideo) return
  if (e.key === 'ArrowLeft') onPrevNavigation()
  if (e.key === 'ArrowRight') onNextNavigation()
  if (e.key === '+') setZoom(zoomScale.value + ZOOM_STEP)
  if (e.key === '-') setZoom(zoomScale.value - ZOOM_STEP)
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Dialog v-model:visible="visible" modal dismissable-mask :closable="false"
    class="w-[calc(100vw-3rem)] max-w-6xl !bg-transparent !shadow-none !border-0 !border-transparent">    
    <div class="relative w-full flex flex-col items-center justify-center">
      <!-- Image Viewer -->
      <div v-if="!showVideo" class="relative w-full h-[75vh] overflow-hidden rounded select-none"
        :class="isZoomed ? 'cursor-zoom-out touch-none' : 'cursor-zoom-in'" @click="toggleZoom"
        @pointermove="panZoom" @wheel.prevent="wheelZoom">
        <Image :src="currentImage?.zoom || currentImage?.source"
          :alt="currentImage?.alt || `Image ${selectedIndex + 1}`" :style="zoomImageStyle" :imageCover="true" />
      </div>

      <!-- Video Viewer -->
      <div v-else class="w-full aspect-video flex items-center justify-center">
        <iframe class="w-full h-full rounded-lg" :src="video" frameborder="0" allow="autoplay; fullscreen"
          allowfullscreen></iframe>
      </div>

      <!-- Zoom controls (for image only) -->
      <div v-if="!showVideo" class="mt-3 flex items-center gap-4 rounded-full bg-black/60 px-4 py-2 text-white text-sm">
        <button type="button" aria-label="Zoom out" :disabled="!isZoomed"
          class="opacity-80 hover:opacity-100 disabled:opacity-30 disabled:cursor-default"
          @click="setZoom(zoomScale - ZOOM_STEP)">
          <FontAwesomeIcon fixed-width :icon="faSearchMinus" />
        </button>
        <span class="w-12 text-center text-xs tabular-nums">{{ Math.round(zoomScale * 100) }}%</span>
        <button type="button" aria-label="Zoom in" :disabled="zoomScale >= MAX_ZOOM"
          class="opacity-80 hover:opacity-100 disabled:opacity-30 disabled:cursor-default"
          @click="setZoom(zoomScale + ZOOM_STEP)">
          <FontAwesomeIcon fixed-width :icon="faSearchPlus" />
        </button>
      </div>

      <!-- Navigation (for image only) -->
      <template v-if="!showVideo && images.length > 1">
        <button class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl z-40" aria-label="Previous image"
          @click="onPrevNavigation">
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>
        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl z-40" aria-label="Next image"
          @click="onNextNavigation">
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </button>
      </template>
    </div>
  </Dialog>
</template>

<style scoped lang="scss">
button {
  outline: none;
}

:deep(.p-dialog-mask) {
  background-color: rgba(0, 0, 0, 0.9) !important;
}

:deep(.p-dialog) {
  background: transparent !important;
  box-shadow: none !important;
  border: none !important;
}
</style>
