<script setup lang="ts">
import { ref, computed, provide, onMounted } from "vue"
import SlidesWorkshop from "@/Components/Banners/SlidesWorkshop.vue"
import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue"
import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue"
import SlidesWorkshopAddMode from "@/Components/Banners/SlidesWorkshopAddMode.vue"
import ScreenView from "@/Components/ScreenView.vue"
import { BannerWorkshop } from "@/types/BannerWorkshop"
import { routeType } from "@/types/route"

const props = defineProps<{
  modelValue: BannerWorkshop
  imagesUploadRoute: any
  banner: any
  ratio: string
  galleryRoute: {
    stock_images: routeType
    uploaded_images: routeType
  }
}>()

const emits = defineEmits<{
  (e: "update:modelValue", val: BannerWorkshop): void
}>()

const jumpToIndex = ref<string>("")
const screenView = ref<string>("desktop")
provide("screenView", screenView)

const data = computed<BannerWorkshop>({
  get() {
    return props.modelValue
  },
  set(v) {
    console.log('components', v)
    emits("update:modelValue", v)
  }
})
const hasSlides = computed(() => {
  return props.modelValue?.components?.some((item: any) => item.ulid != null)
})

const containerRef = ref<HTMLElement | null>(null)
const containerWidth = ref(0)

onMounted(() => {
  if (containerRef.value) {
    containerWidth.value = containerRef.value.offsetWidth
  }
})

const calculatedHeight = computed(() => {
  if (!props.ratio || !containerWidth.value) return 0

  if (props.ratio.includes('/')) {
    const [w, h] = props.ratio.split('/').map(Number)
    return containerWidth.value * (h / w)
  }

  const numeric = Number(ratio)
  if (!isNaN(numeric) && numeric > 0) {
    return containerWidth.value * (1 / numeric)
  }

  return 0
})

const scaleValue = computed(() => {
  if (calculatedHeight.value <= 500) return 1
  return 500 / calculatedHeight.value
})

const needsScale = computed(() => calculatedHeight.value > 500)

</script>

<template>
  <div v-if="hasSlides" class="w-full">
    <div class="flex justify-end pr-2">
      <ScreenView @screenView="val => (screenView = val)" />
    </div>

    <!-- Banner preview -->
    <div class="flex pr-0.5  editor-class" :class="[props.modelValue.type === 'square'
      ? 'justify-start 2xl:justify-center'
      : 'justify-center']">
      <div v-if="props.modelValue.type === 'square'" class="w-full min-h-[250px] max-h-[400px]">
        <SliderSquare :data="props.modelValue" :jumpToIndex="jumpToIndex" :view="screenView" :ratio />
      </div>

      <div ref="containerRef" class="w-full max-w-[1200px] mx-auto overflow-hidden relative"
        :style="needsScale ? { height: '500px' } : {}">
        <div :style="needsScale
          ? {
            transform: `scale(${scaleValue})`,
            transformOrigin: 'top center',
            width: '100%'
          }
          : {}">
          <SliderLandscape :data="props.modelValue" :jumpToIndex="jumpToIndex" :view="screenView" :ratio="ratio" />
        </div>
      </div>
    </div>

    <!-- Editor -->
    <SlidesWorkshop :bannerType="props.modelValue.type" class="clear-both mt-2 p-2.5" v-model="data"
      @jumpToIndex="val => (jumpToIndex = val)" :imagesUploadRoute="imagesUploadRoute" :screenView="screenView"
      :galleryRoute="galleryRoute" :ratio />
  </div>

  <!-- Empty state -->
  <div v-else>
    <SlidesWorkshopAddMode :data="props.modelValue" :imagesUploadRoute="imagesUploadRoute" :galleryRoute="galleryRoute"
      :ratio />
  </div>
</template>
