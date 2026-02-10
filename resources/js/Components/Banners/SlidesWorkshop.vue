<script setup lang="ts">
import { ref, watch, onMounted, computed } from "vue"
import { useBannerBackgroundColor, useHeadlineText } from "@/Composables/useStockList"
import { ulid } from "ulid"

import SliderCommonWorkshop from "@/Components/Banners/SlidesWorkshop/SliderCommonWorkshop.vue"
import SlideWorkshop from "@/Components/Banners/SlidesWorkshop/SlideWorkshop.vue"
import Modal from "@/Components/Utils/Modal.vue"
import CropImage from "@/Components/CropImage/CropImage.vue"
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue"

import { routeType } from "@/types/route"
import CommonSlidesBluprint from "./Blueprint/CommonSlidesBluprint"
import SlidesBluprint from "./Blueprint/SlidesBluprint"
import IndexSlidesControl from "./SlidesWorkshop/IndexSlidesControl.vue"

type SlideWorkshopData = any

const props = defineProps<{
  modelValue: any
  imagesUploadRoute: routeType
  user: string
  screenView: string
  isOpen?: Object
  galleryRoute: {
    stock_images: routeType
    uploaded_images: routeType
  }
}>()

const emits = defineEmits<{
  (e: "jumpToIndex", id: string): void
  (e: "update:modelValue", val: BannerWorkshop): void
}>()

/* ---------------- state ---------------- */
const currentComponentBeenEdited = ref<SlideWorkshopData | null>(null)
const commonEditActive = ref(false)
const isOpenGalleryImages = ref(false)
const uploadedFilesList = ref<any[]>([])
const isOpenCropModal = ref(false)


const CommonBlueprint = ref(CommonSlidesBluprint.data)
const ComponentsBlueprint = ref(SlidesBluprint.data)

/* ---------------- helpers ---------------- */
const getComponents = () => props.modelValue.components || []
const setComponents = (val: SlideWorkshopData[]) => {
  props.modelValue.components = val
}
const findIndex = (ulid: string) =>
  getComponents().findIndex((i: any) => i.ulid === ulid)

/* ---------------- crop ---------------- */
const closeCropModal = () => {
  uploadedFilesList.value = []
  isOpenCropModal.value = false
}

watch(
  currentComponentBeenEdited,
  (val) => {
    if (!val?.ulid) return

    const list = [...getComponents()]
    const index = findIndex(val.ulid)
    if (index === -1) return

    list[index] = { ...val }
    setComponents(list)
    emits("jumpToIndex", val.ulid)
  },
  { deep: true }
)


const removeComponent = (slide: SlideWorkshopData) => {
  const list = [...getComponents()]
  const index = findIndex(slide.ulid)
  if (index === -1) return

  list.splice(index, 1)
  setComponents(list)
}


/* ---------------- upload/gallery ---------------- */
const appendSlides = (slides: any[]) => {
  const updated = [...getComponents(), ...slides]
  setComponents(updated)

  currentComponentBeenEdited.value = updated.at(-1) ?? null
  commonEditActive.value = false
  isOpenCropModal.value = false
  isOpenGalleryImages.value = false
}

const uploadImageRespone = (res: any) => {
  const slides = res.data.map((img: any) => ({
    id: null,
    ulid: ulid(),
    layout: { imageAlt: img.name },
    image: { desktop: img },
    backgroundType: { desktop: "image" },
    visibility: true,
  }))

  appendSlides(slides)
}

const onPickImageGalery = (image: any) => {
  appendSlides([
    {
      id: null,
      ulid: ulid(),
      layout: { imageAlt: image.name },
      image: { desktop: image },
      backgroundType: { desktop: "image" },
      visibility: true,
    },
  ])
}

onMounted(() => {
  commonEditActive.value = true
})

const data = computed<BannerWorkshop>({
  get() {
    return props.modelValue
  },
  set(v) {
    console.log('sdsd', v)
    emits("update:modelValue", v)
  }
})

</script>


<template>
    <div class="flex flex-grow gap-2.5">
        <IndexSlidesControl 
            v-model="data"
            :imagesUploadRoute="imagesUploadRoute"
            :screenView="screenView"
            :isOpen="isOpen"
            :galleryRoute="galleryRoute"
            :commonEditActive="commonEditActive"
            :currentComponentBeenEdited="currentComponentBeenEdited"
            @updateCurrentComponentBeenEdited="e => currentComponentBeenEdited = e"
            @update-common-edit-active="e => commonEditActive = e"
        />

        <!-- The Editor: Common Properties -->
        <div class="border border-gray-300 w-3/4 rounded-md" v-if="commonEditActive">
            <SliderCommonWorkshop 
                ref="_SlideWorkshop" 
                v-model="data"
                :blueprint="CommonBlueprint" 
            />
        </div>

        <!-- The Editor: Slide -->
        <div class="border border-gray-300 w-3/4 rounded-md" v-if="currentComponentBeenEdited != null">
            <SlideWorkshop 
                ref="_SlideWorkshop" 
                :bannerType="modelValue.type" 
                :common="modelValue.common"
                v-model="currentComponentBeenEdited" 
                :blueprint="ComponentsBlueprint"
                :remove="removeComponent"  
                :uploadRoutes="imagesUploadRoute" 
            />
        </div>

        <!-- Modal: Gallery -->
        <Gallery 
            :open="isOpenGalleryImages" 
            @on-close="() => isOpenGalleryImages = false"  
            :uploadRoutes="route(imagesUploadRoute.name,imagesUploadRoute.parameters)"
            :tabs="['upload','images_uploaded', 'stock_images']" 
            @onPick="onPickImageGalery" 
            @on-upload="uploadImageRespone"
            :use-crop="true" 
            :crop-props="{ratio: modelValue.type == 'square' ? {w: 1, h: 1} : {w: 4, h: 1}}" 
            :stockImageRoutes="galleryRoute.stock_images"
            :imagesUploadedRoutes="galleryRoute.uploaded_images"
        />


        <!-- Modal: Crop (add slide) -->
         <Modal :isOpen="isOpenCropModal" @onClose="closeCropModal">
            <div>
                <CropImage
                    :ratio="modelValue.type == 'square' ? {w: 1, h: 1} : {w: 4, h: 1}"
                    :data="uploadedFilesList"
                    :imagesUploadRoute="route(imagesUploadRoute.name,imagesUploadRoute.parameters)"
                    :response="uploadImageRespone" />
            </div>
        </Modal>
    </div>
</template>
