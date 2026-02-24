<script setup lang="ts">
import { ref, watch, onMounted } from "vue"
import { useBannerBackgroundColor, useHeadlineText } from "@/Composables/useStockList"
import { trans } from "laravel-vue-i18n"
import { get, isNull } from "lodash-es"
import draggable from "vuedraggable"
import { ulid } from "ulid"

import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import Modal from "@/Components/Utils/Modal.vue"
import CropImage from "@/Components/CropImage/CropImage.vue"
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
  faTrashAlt,
  faAlignJustify,
  faCog,
  faImage,
  faLock,
  faTools,
} from "@fal"
import { faEye, faEyeSlash } from "@fas"
import { faClone } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from "@/types/route"

library.add(
  faEye,
  faEyeSlash,
  faTrashAlt,
  faAlignJustify,
  faCog,
  faImage,
  faLock,
  faTools,
  faClone
)


type SlideWorkshopData = any

type BannerWorkshop = {
  components: SlideWorkshopData[]
  type?: string
  [key: string]: any
}


const props = defineProps<{
  modelValue: BannerWorkshop
  imagesUploadRoute: routeType
  screenView: string
  currentComponentBeenEdited : any
  commonEditActive : boolean
  isOpen?: Object
  galleryRoute: {
    stock_images: routeType
    uploaded_images: routeType
  }
}>()

const emits = defineEmits<{
  (e: "updateCurrentComponentBeenEdited", val: any),
  (e: "update:modelValue", val: BannerWorkshop): void
  (e: "updateCommonEditActive", val: boolean),
}>()


const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const isOpenGalleryImages = ref(false)
const uploadedFilesList = ref<any[]>([])
const isOpenCropModal = ref(false)
const backgroundColorList = useBannerBackgroundColor()
const headlineList = useHeadlineText()


const components = () => props.modelValue?.components ?? []

const emitUpdate = (newComponents: SlideWorkshopData[]) => {
  const newValue: BannerWorkshop = {
    ...props.modelValue,
    components: newComponents,
  }
  emits("update:modelValue", newValue)
}

const findIndexByUlid = (id: string) =>
  components().findIndex((i: any) => i.ulid === id)

const randomFrom = (arr: any[]) =>
  arr[Math.floor(Math.random() * arr.length)]

const createBaseSlide = (data: Partial<SlideWorkshopData> = {}) => ({
  id: null,
  ulid: ulid(),
  visibility: true,
  ...data,
})


const dragover = (e: DragEvent) => {
  e.preventDefault()
  isDragging.value = true
}
const dragleave = () => (isDragging.value = false)

const drop = (e: DragEvent) => {
  e.preventDefault()
  if (!e.dataTransfer) return

  uploadedFilesList.value = Array.from(e.dataTransfer.files)
  if (uploadedFilesList.value.length) isOpenCropModal.value = true
  isDragging.value = false
}


const closeCropModal = () => {
  uploadedFilesList.value = []
  isOpenCropModal.value = false
  if (fileInput.value) fileInput.value.value = ""
}


const removeComponent = (slide: SlideWorkshopData) => {
  const list = [...components()]
  const index = findIndexByUlid(slide.ulid)
  if (index === -1) return

  list.splice(index, 1)
  emitUpdate(list)
}

const duplicateSlide = (slide: SlideWorkshopData) => {
  const cloned = JSON.parse(JSON.stringify(slide))
  cloned.ulid = ulid()

  const list = [...components()]
  const index = findIndexByUlid(slide.ulid)
  if (index === -1) return

  list.splice(index + 1, 0, cloned)
  emitUpdate(list)
}

const changeVisibility = (slide: SlideWorkshopData) => {
  const list = [...components()]
  const index = findIndexByUlid(slide.ulid)
  if (index === -1) return

  list[index] = {
    ...list[index],
    visibility: !(list[index].visibility ?? true),
  }

  emitUpdate(list)
}


const selectComponentForEdition = (slide: SlideWorkshopData) => {
    emits("updateCommonEditActive", false)
    emits("updateCurrentComponentBeenEdited", slide)
}



const setCommonEdit = () => {
  emits("updateCurrentComponentBeenEdited", null)
  emits("updateCommonEditActive", true)
}


const addNewSlide = () => {
  const newSlide = createBaseSlide({
    layout: {
      imageAlt: "New slide",
      centralStage: {
        title: randomFrom(headlineList),
        style: {
          color: "rgba(253, 224, 71, 255)",
          fontSize: {
            fontTitle: "text-[18px] lg:text-[32px]",
            fontSubtitle: "text-[10px] lg:text-[15px]",
          },
        },
      },
      background: {
        desktop: randomFrom(backgroundColorList),
        tablet: randomFrom(backgroundColorList),
        mobile: randomFrom(backgroundColorList),
      },
      backgroundType: { desktop: "color" },
    },
    image: { desktop: {}, tablet: {}, mobile: {} },
  })

  emitUpdate([...components(), newSlide])
}


const appendSlides = (slides: any[]) => {
  const newList = [...components(), ...slides]
  emitUpdate(newList)

  const last = slides.at(-1)
  emits("updateCurrentComponentBeenEdited", last)
  emits("updateCommonEditActive", false)
  isOpenCropModal.value = false
  isOpenGalleryImages.value = false
}

const uploadImageRespone = (res: any) => {
  const slides = res.data.map((img: any) =>
    createBaseSlide({
      layout: { imageAlt: img.name },
      image: { desktop: img },
      backgroundType: { desktop: "image" },
    })
  )
  appendSlides(slides)
}

const onPickImageGalery = (image: any) => {
  appendSlides([
    createBaseSlide({
      layout: { imageAlt: image.name },
      image: { desktop: image },
      backgroundType: { desktop: "image" },
    }),
  ])
}


</script>




<template>
        <div class="p-2.5 border rounded h-fit shadow w-1/4" v-if="modelValue.components" @dragover="dragover"
            @dragleave="dragleave" @drop="drop">
            <!-- Common Properties -->
            <div :class="[
                'p-2 mb-4 md:pl-3 cursor-pointer space-x-3 md:space-x-2 ring-1 ring-gray-300 flex flex-row items-center md:block',
                commonEditActive
                    ? 'sm:border-l-4 sm:border-amber-300 text-amber-300 transition-all duration-100 ease-in-out bg-gray-200/60 font-medium'
                    : 'hover:bg-gray-200/30 text-white transition-all duration-100 ease-in-out hover:bg-gray-100 border-gray-300',
                ]" @click="setCommonEdit">
                <FontAwesomeIcon 
                icon="fal fa-cog" class="text-xl md:text-base text-gray-500" aria-hidden="true" />
                <span class="text-gray-600 text-sm hidden sm:inline">{{ trans("Common properties") }}</span>
            </div>

            <!-- Slides/Drag area -->
            <div class="text-lg font-medium leading-none">{{ trans("Slides") }} <span class='text-dase'>({{ modelValue.components.length }})</span></div>
            <draggable :list="modelValue.components" group="slide " item-key="ulid" handle=".handle" class="max-h-96 overflow-auto p-0.5">
                <template #item="{ element: slide }">
                    <div @mousedown="selectComponentForEdition(slide)" v-if="slide.ulid" :class="[
                            'grid grid-flow-col relative sm:py-1 mb-2 items-center justify-between ring-1 ring-gray-300',
                            slide.ulid == get(currentComponentBeenEdited, 'ulid')
                                ? 'sm:border-l-4 sm:border-amber-300 text-amber-300 transition-all duration-100 ease-in-out font-medium'
                                : 'hover:bg-gray-100 text-gray-400 hover:text-gray-500 transition-all duration-100 ease-in-out',
                        ]">
                        <!-- Slide -->
                        <div class="grid grid-flow-col gap-x-1 lg:gap-x-0 ssm:py-1 lg:py-0">
                            <!-- Icon: Bars, class 'handle' to grabable -->
                            <FontAwesomeIcon icon="fal fa-bars"
                                class="handle p-1 text-xs sm:text-base sm:p-2.5 text-gray-700 cursor-grab place-self-center" />

                            <!-- Image slide: if Image is selected in SlideBackground -->
                            <div v-if="modelValue.type === 'square'" class="">
                                <!-- If Banner Square -->
                                <Image v-if="get(slide, ['layout', 'backgroundType', 'desktop'], 'image') === 'image'"
                                    :src="get(slide, ['image', 'desktop', 'thumbnail'], get(slide, ['image', 'desktop', 'thumbnail']))"
                                    class="h-full w-10 sm:w-10 flex items-center justify-center py-1" />

                                <!-- If the slide is color -->
                                <div v-else
                                    :style="{ background: get(slide, ['layout', 'background', 'desktop'], 'gray')}"
                                    class="h-full w-10 sm:w-10 flex items-center justify-center py-1" />
                            </div>

                            <div v-else>
                                <Image
                                    v-if="get(slide, ['layout', 'backgroundType', screenView ], get(slide, ['layout', 'backgroundType', 'desktop'], 'image')) === 'image'"
                                    :src="get(slide, ['image', screenView, 'thumbnail'], get(slide, ['image','desktop', 'thumbnail'], false))"
                                    class="h-full w-10 sm:w-10 flex items-center justify-center py-1" />

                                <!-- If the slide is color -->
                                <div v-else
                                    :style="{ background: get(slide, ['layout', 'background', screenView], get(slide, ['layout', 'background','desktop'], 'gray'))}"
                                    class="h-full w-10 sm:w-10 flex items-center justify-center py-1" />
                            </div>

                            <!-- Label slide -->
                            <div
                                class="hidden lg:inline-flex overflow-hidden whitespace-nowrap overflow-ellipsis pl-2 leading-tight flex-auto items-center">
                                <div class="overflow-hidden whitespace-nowrap overflow-ellipsis lg:text-xs xl:text-sm">
                                    {{ slide?.layout?.imageAlt ?? "Image " + slide.id }}
                                </div>
                            </div>
                        </div>

                        <!-- Button: Show/hide, delete slide -->
                        <div class="flex justify-center items-center pr-2 justify-self-end">
                            <button v-if="!slide.visibility"
                                class="px-2 py-1 bg-grays-500 text-red-500/60 hover:text-red-500" type="button" @click="(e)=>{ e.stopPropagation()
                                    removeComponent(slide)}" title="Delete this slide">
                                <FontAwesomeIcon :icon="['fal', 'fa-trash-alt']" class="text-xs sm:text-sm" />
                            </button>
                            <button class="qwezxcpx-2 py-1 text-gray-400 hover:text-gray-500" type="button"
                                @click="changeVisibility(slide)" title="Show/hide this slide">
                                <FontAwesomeIcon v-if="slide.hasOwnProperty('visibility') ? slide.visibility : true"
                                    icon="fas fa-eye" class="text-xs sm:text-sm " />
                                <FontAwesomeIcon v-else icon="fas fa-eye-slash" class="text-xs sm:text-sm" />
                            </button>
                            <button class="px-2 py-1 text-gray-400 hover:text-gray-500" type="button"
                                @click="duplicateSlide(slide)" title="Duplicate this slide">
                                <FontAwesomeIcon icon="fad fa-clone" class="text-xs sm:text-sm " />
                            </button>
                        </div>
                    </div>
                </template>
            </draggable>

            <!-- Button: Add slide, Gallery -->
            <div class="flex flex-wrap md:flex-row gap-x-2 gap-y-1 lg:gap-y-0 w-full justify-between">
                <Button @click="isOpenGalleryImages = true" :style="`tertiary`" icon="fal fa-photo-video"
                    label="Gallery" size="xs" class="relative w-full flex justify-center lg:w-fit lg:inline space-x-2"
                    id="gallery" />

                <Button :style="`secondary`" size="xs" @click="addNewSlide"
                    class="relative w-full flex justify-center lg:w-fit lg:inline space-x-2">
                    <FontAwesomeIcon icon='fas fa-plus' class='' aria-hidden='true' />
                    <span>{{ trans("Add slide") }}</span>
                </Button>
            </div>
            <div class="text-xs text-gray-400 pt-2">Max file size 25 MB</div>
            <div class="text-xs text-gray-400 py-1">The recommended image size is 1800 x 450</div>
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
</template>
