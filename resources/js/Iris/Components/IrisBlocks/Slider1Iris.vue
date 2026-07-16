<script setup lang="ts">
import Image from "@common/Components/Image.vue"
import { ulid } from 'ulid'
import { inject, ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fal'
import { faSpinnerThird } from '@fas'
import { getStyles } from '@/Composables/styles'
import LinkIris from '@/Iris/Components/LinkIris.vue'


const props = defineProps<{
  fieldValue: {
    container?: { properties?: any }
    carousel_data: {
      carousel_setting: {
        slidesPerView: { mobile: number; tablet: number; desktop: number }
        loop?: boolean
        autoplay?: any
        spaceBetween?: number
        use_text?: boolean
      }
      cards: Array<any>
      card_container: {
        properties?: any
        container_image?: any
        image_properties?: any
      }
    }
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: 'mobile' | 'tablet' | 'desktop'
  indexBlock : number
}>()


const keySwiper = ref(ulid())
const layout: any = inject("layout", {})

const refreshTrigger = ref(0)


const cards = computed(() => props.fieldValue?.slider_data?.cards ?? [])

/* duplicate for seamless loop */
const loopCards = computed(() => {
  if (!cards.value.length) return []
  return [...cards.value, ...cards.value]
})



const containerStyles = computed(() => ({
  ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
  ...getStyles(props.fieldValue?.container?.properties, props.screenType),
}))

const imageContainerStyle = computed(() =>
  getStyles(props.fieldValue?.slider_data?.card_container?.container_image, props.screenType)
)

const imageStyle = computed(() =>
  getStyles(props.fieldValue?.image?.properties, props.screenType)
)



const slidesPerView = computed(() => {
  const raw = props.fieldValue?.slider_data?.slider_setting?.slidesPerView?.[props.screenType]
  if (!raw) return 1
  const num = Number(raw)
  return num > 0 ? num : 1
})

const spaceBetween = computed(() => {
  const raw = props.fieldValue?.slider_data?.slider_setting?.spaceBetween
  if (!raw) return 0
  return Number(raw)
})

const wrapperStyle = computed(() => ({
  display: 'flex',
  alignItems: 'center',
  gap: `${spaceBetween.value}px`,
  overflow: 'hidden'
}))

const itemStyle = computed(() => {
  const width = 100 / slidesPerView.value
  return {
    flex: `0 0 ${width}%`,
    maxWidth: `${width}%`,
    transition: 'all .18s ease'
  }
})



const DEFAULT_SPEED = 20000

const speed = computed(() => {
  const raw = props.fieldValue?.slider_data?.slider_setting?.speed
  if (raw == null) return DEFAULT_SPEED
  const num = Number(raw)
  return num > 0 ? num : DEFAULT_SPEED
})

const DRAG_THRESHOLD = 5

const wrapperRef = ref<HTMLElement | null>(null)
const isDragging = ref(false)
let rafId: number | null = null
let pos = 0
let lastTimestamp = 0
let isPointerDown = false
let dragStartX = 0
let dragStartPos = 0
let suppressClick = false

const loopDistance = () => {
  const el = wrapperRef.value
  return el ? el.scrollWidth / 2 : 0
}

const wrapPos = (value: number) => {
  const distance = loopDistance()
  if (distance <= 0) return 0
  const wrapped = value % distance
  return wrapped < 0 ? wrapped + distance : wrapped
}

const startAutoMove = () => {
  const el = wrapperRef.value
  if (!el || rafId) return

  const step = (timestamp: number) => {
    const distance = loopDistance()

    if (lastTimestamp && distance > 0) {
      pos = wrapPos(pos + (distance / speed.value) * (timestamp - lastTimestamp))
      el.scrollLeft = pos
    }

    lastTimestamp = timestamp
    rafId = requestAnimationFrame(step)
  }

  rafId = requestAnimationFrame(step)
}

const stopAutoMove = () => {
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  lastTimestamp = 0
}

const onPointerDown = (event: PointerEvent) => {
  const el = wrapperRef.value
  if (!el || event.button !== 0) return

  stopAutoMove()
  suppressClick = false
  isPointerDown = true
  dragStartX = event.clientX
  dragStartPos = pos
  el.setPointerCapture(event.pointerId)
}

const onPointerMove = (event: PointerEvent) => {
  const el = wrapperRef.value
  if (!el || !isPointerDown) return

  const delta = event.clientX - dragStartX
  if (!isDragging.value && Math.abs(delta) < DRAG_THRESHOLD) return

  isDragging.value = true
  pos = wrapPos(dragStartPos - delta)
  el.scrollLeft = pos
}

const onPointerUp = (event: PointerEvent) => {
  const el = wrapperRef.value
  if (!isPointerDown) return

  isPointerDown = false
  if (el?.hasPointerCapture(event.pointerId)) {
    el.releasePointerCapture(event.pointerId)
  }

  suppressClick = isDragging.value
  isDragging.value = false
  startAutoMove()
}

const onClickCapture = (event: MouseEvent) => {
  if (!suppressClick) return
  suppressClick = false
  event.preventDefault()
  event.stopPropagation()
}

onMounted(startAutoMove)
onBeforeUnmount(stopAutoMove)
</script>
<template>
  <div :id="fieldValue?.id ? fieldValue?.id  : 'slider'+indexBlock"  component="slider" class="relative overflow-hidden">
    <div :data-refresh="refreshTrigger" :key="keySwiper" :style="containerStyles">

      <div
        ref="wrapperRef"
        :style="wrapperStyle"
        class="slider-wrapper overflow-x-hidden"
        :class="{ 'is-dragging': isDragging }"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerUp"
        @click.capture="onClickCapture"
        @dragstart.prevent
      >
        <div
          v-for="(data, index) in loopCards"
          :key="data?.id ? data.id + '-' + index : index"
          :style="itemStyle"
          class="flex justify-center items-center slider-item"
        >
          <div class="card flex flex-col w-full h-full group relative">
            <div class="flex flex-1 flex-col">
              <div
                class="flex justify-center overflow-visible"
                :style="imageContainerStyle"
              >
                <component
                  :is="data?.link?.href ? LinkIris : 'div'"
                  :href="data?.link?.url ?? data?.link?.href "
                  :target="data?.link?.target"
                  :type="data?.link?.type"
                  class="overflow-hidden w-full flex items-center justify-center relative image-hover-wrap"
                  :style="imageStyle"
                >
                  <template #default="slotProps: any">
                    <Image
                      v-if="data?.image?.source"
                      :src="data.image.source"
                      :alt="data?.image?.alt || `image-${index}`"
                      class="slider-image"
                      :height="getStyles(fieldValue?.image?.properties, screenType, false)?.height"
                      :width="getStyles(fieldValue?.image?.properties, screenType, false)?.width"
                    />

                    <div
                      v-else
                      class="flex items-center justify-center w-full bg-gray-100 h-36 cursor-pointer"
                    >
                      <FontAwesomeIcon :icon="faImage" class="text-gray-400 text-4xl" />
                    </div>

                    <div
                      v-if="slotProps?.isLoading"
                      class="absolute inset-0 bg-black/40 flex items-center justify-center z-10 pointer-events-none"
                    >
                      <FontAwesomeIcon :icon="faSpinnerThird" spin class="text-white text-3xl" fixed-width aria-hidden="true" />
                    </div>
                  </template>
                </component>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.slider-wrapper{
  width:100%;
  overflow:hidden;
  touch-action: pan-y;
  cursor: grab;
  user-select: none;
}

.slider-wrapper.is-dragging{
  cursor: grabbing;
}

.slider-wrapper.is-dragging .slider-image{
  pointer-events: none;
}

.slider-item{
  transition: all .18s ease;
}

/* hover effect */
.image-hover-wrap{
  position:relative;
  overflow:hidden;
}

.slider-image{
  object-fit:cover;
  transition: all .2s cubic-bezier(.2,.8,.2,1);
}

/* hover opacity + zoom 1.5x */
.group:hover .slider-image{
  opacity:.55;
  z-index:5;
}

/* bring hovered card above others */
.group:hover{
  z-index:20;
}
</style>