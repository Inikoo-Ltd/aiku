<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'
import { inject, ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage } from '@fal'
import { getStyles } from '@/Composables/styles'
import Blueprint from './Blueprint'
import CardBlueprint from './SliderBlueprint'
import { sendMessageToParent } from "@/Composables/Workshop"

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop',
  indexBlock?: number
}>()

const keySwiper = ref(ulid())
const layout: any = inject("layout", {})

const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []

const refreshTrigger = ref(0)

const imageSettings = {
  key: ["image", "source"],
  stencilProps: {
    aspectRatio: [1, 4 / 3, 16 / 9, null],
    movable: true,
    scalable: true,
    resizable: true,
  },
}

/* ================= SAFE DATA ================= */

const cards = computed(() => props.modelValue?.slider_data?.cards ?? [])

/* duplicate for seamless loop */
const loopCards = computed(() => {
  if (!cards.value.length) return []
  return [...cards.value, ...cards.value]
})

/* ================= STYLES ================= */

const containerStyles = computed(() => ({
  ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
  ...getStyles(props.modelValue?.container?.properties, props.screenType),
}))

const imageContainerStyle = computed(() =>
  getStyles(props.modelValue?.slider_data?.card_container?.container_image, props.screenType)
)

const imageStyle = computed(() =>
  getStyles(props.modelValue?.image?.properties, props.screenType)
)

/* ================= SLIDER SETTINGS ================= */

const slidesPerView = computed(() => {
  const raw = props.modelValue?.slider_data?.slider_setting?.slidesPerView?.[props.screenType]
  if (!raw) return 1
  const num = Number(raw)
  return num > 0 ? num : 1
})

const spaceBetween = computed(() => {
  const raw = props.modelValue?.slider_data?.slider_setting?.spaceBetween
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
    transition: 'all .35s ease'
  }
})

/* ================= EVENTS ================= */

const handleClickImage = (index: number) => {
  sendMessageToParent('activeBlock', props.indexBlock)
  sendMessageToParent('activeChildBlock', bKeys[2])
  sendMessageToParent('activeChildBlockArray', index % cards.value.length)
  sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
}

const handleUploadImage = (index: number) => {
  const realIndex = index % cards.value.length
  sendMessageToParent('uploadImage', {
    ...imageSettings,
    key: ['slider_data', 'cards', realIndex, 'image', 'source']
  })
}

/* ================= AUTO MOVE ================= */

const wrapperRef = ref<HTMLElement | null>(null)
let rafId: number | null = null
let pos = 0
const speed = 0.35

const startAutoMove = () => {
  const el = wrapperRef.value
  if (!el) return

  const step = () => {
    pos += speed
    el.scrollLeft = pos

    if (pos >= el.scrollWidth / 2) {
      pos = 0
      el.scrollLeft = 0
    }

    rafId = requestAnimationFrame(step)
  }

  rafId = requestAnimationFrame(step)
}

const stopAutoMove = () => {
  if (rafId) cancelAnimationFrame(rafId)
}

onMounted(startAutoMove)
onBeforeUnmount(stopAutoMove)
</script>

<template>
  <div id="slider" class="relative overflow-hidden">
    <div :data-refresh="refreshTrigger" :key="keySwiper" :style="containerStyles">

      <div
        ref="wrapperRef"
        :style="wrapperStyle"
        class="slider-wrapper overflow-x-hidden"
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
                @click.stop="handleClickImage(index)"
                @dblclick.stop="handleUploadImage(index)"
              >
                <div
                  class="overflow-hidden w-full flex items-center justify-center relative image-hover-wrap"
                  :style="imageStyle"
                >
                  <Image
                    v-if="data?.image?.source"
                    :src="data.image.source"
                    :alt="data?.image?.alt || `image-${index}`"
                    class="slider-image"
                  />

                  <div
                    v-else
                    class="flex items-center justify-center w-full bg-gray-100 h-36 cursor-pointer"
                  >
                    <FontAwesomeIcon :icon="faImage" class="text-gray-400 text-4xl" />
                  </div>

                </div>
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
}

.slider-item{
  transition: all .4s ease;
}

/* hover effect */
.image-hover-wrap{
  position:relative;
  overflow:hidden;
}

.slider-image{
  object-fit:cover;
  transition: all .45s cubic-bezier(.2,.8,.2,1);
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
