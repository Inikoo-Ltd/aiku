<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faChevronCircleLeft,
  faChevronCircleRight,
  faTimes,
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

const currentImage = computed(() => props.images[selectedIndex.value])

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
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Dialog v-model:visible="visible" modal dismissable-mask close-on-escape :closable="false"
    class="w-[calc(100vw-3rem)] max-w-6xl !bg-transparent !shadow-none !border-0 !border-transparent">    
    <div class="relative w-full flex flex-col items-center justify-center">
      <div class="w-full flex justify-end mb-2">
        <button class="text-white text-3xl hover:text-gray-300 z-50" aria-label="Close viewer"
          @click="visible = false">
          <FontAwesomeIcon :icon="faTimes" />
        </button>
      </div>

      <!-- Image Viewer -->
      <div v-if="!showVideo" class="relative w-full h-[75vh] overflow-hidden rounded select-none">
        <Image :src="currentImage?.zoom || currentImage?.source"
          :alt="currentImage?.alt || `Image ${selectedIndex + 1}`" :style="{ objectFit: 'contain' }" :imageCover="true" />
      </div>

      <!-- Video Viewer -->
      <div v-else class="w-full aspect-video flex items-center justify-center">
        <iframe class="w-full h-full rounded-lg" :src="video" frameborder="0" allow="autoplay; fullscreen"
          allowfullscreen></iframe>
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
