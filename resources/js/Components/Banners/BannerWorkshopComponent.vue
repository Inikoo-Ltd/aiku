<script setup lang="ts">
import { ref, computed, provide } from "vue"
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

</script>

<template>
  <div v-if="hasSlides" class="w-full">
    <div class="flex justify-end pr-2">
      <ScreenView @screenView="val => (screenView = val)" />
    </div>

    <!-- Banner preview -->
    <div
      class="flex pr-0.5"
      :class="[props.modelValue.type === 'square'
        ? 'justify-start 2xl:justify-center'
        : 'justify-center']"
    >
      <div
        v-if="props.modelValue.type === 'square'"
        class="w-full min-h-[250px] max-h-[400px]"
      >
        <SliderSquare
          :data="props.modelValue"
          :jumpToIndex="jumpToIndex"
          :view="screenView"
        />
      </div>

      <SliderLandscape
        v-else
        :data="props.modelValue"
        :jumpToIndex="jumpToIndex"
        :view="screenView"
      />
    </div>

    <!-- Editor -->
    <SlidesWorkshop
      :bannerType="props.modelValue.type"
      class="clear-both mt-2 p-2.5"
      v-model="data"
      @jumpToIndex="val => (jumpToIndex = val)"
      :imagesUploadRoute="imagesUploadRoute"
      :screenView="screenView"
      :galleryRoute="galleryRoute"
    />
  </div>

  <!-- Empty state -->
  <div v-else>
    <SlidesWorkshopAddMode
      :data="props.modelValue"
      :imagesUploadRoute="imagesUploadRoute"
      :galleryRoute="galleryRoute"
    />
  </div>
</template>
