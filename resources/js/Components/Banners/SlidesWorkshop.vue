<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 21 Jul 2023 22:21:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useBannerBackgroundColor, useHeadlineText } from "@/Composables/useStockList"
import { trans } from "laravel-vue-i18n"
import { get, isNull } from 'lodash-es'
import draggable from "vuedraggable"
import { ulid } from "ulid"
import { notify } from "@kyvg/vue3-notification"

import Button from '@/Components/Elements/Buttons/Button.vue'
import SliderCommonWorkshop from "@/Components/Banners/SlidesWorkshop/SliderCommonWorkshop.vue"
import SlideWorkshop from "@/Components/Banners/SlidesWorkshop/SlideWorkshop.vue"
import Image from "@/Components/Image.vue"
import Modal from '@/Components/Utils/Modal.vue'
import CropImage from "@/Components/CropImage/CropImage.vue" 
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue";

//types
import { BannerWorkshop, SlideWorkshopData } from '@/types/BannerWorkshop'
import { routeType } from '@/types/route'

//data
import CommonSlidesBluprint from './Blueprint/CommonSlidesBluprint'
import SlidesBluprint from './Blueprint/SlidesBluprint'

//icon
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt, faAlignJustify, faCog, faImage, faLock, faTools, } from '@fal'
import { faEye, faEyeSlash } from '@fas'
import { faClone } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faEye, faEyeSlash, faTrashAlt, faAlignJustify, faCog, faImage, faLock, faTools, faClone)


const props = defineProps<{
    data: BannerWorkshop
    imagesUploadRoute: routeType
    user: string
    screenView: string
    isOpen?:Object
    galleryRoute:{
        stock_images : routeType,
        uploaded_images : routeType
    }
}>()


const emits = defineEmits<{
    (e: "jumpToIndex", id: string): void;
}>()

const isDragging = ref(false);
const fileInput = ref();
const currentComponentBeenEdited = ref();
const commonEditActive = ref(false);
const isOpenGalleryImages = ref(false);
const _SlideWorkshop = ref(null);
const uploadedFilesList = ref([]);
const isOpenCropModal = ref(false);

const backgroundColorList = useBannerBackgroundColor() // Fetch color list from Composables

const closeCropModal = () => {
    uploadedFilesList.value = [];
    isOpenCropModal.value = false;
    fileInput.value.value = "";
};

const addComponent = async (element) => {
    uploadedFilesList.value = element.target.files;
    isOpenCropModal.value = true;
};

const removeComponent = (file) => {
    const index = props.data.components.findIndex((item) => item.ulid === file.ulid);
    if (index !== -1) {
        props.data.components.splice(index,1)
    }
};

const dragover = (e) => {
    e.preventDefault();
    isDragging.value = true;
};

const dragleave = () => {
    isDragging.value = false;
};

const drop = (e) => {
    e.preventDefault();
    uploadedFilesList.value = e.dataTransfer.files;
    if( e.dataTransfer.files.length > 0 ) isOpenCropModal.value = true;
    isDragging.value = false;
};

const selectComponentForEdition = (slide: SlideWorkshopData) => {
    commonEditActive.value = false;
    currentComponentBeenEdited.value = slide;
    if (!isNull(_SlideWorkshop.value)) _SlideWorkshop.value.current = 0;
}

watch(
    currentComponentBeenEdited,
    (newValue) => {
        if (newValue !== null) {  // If clicked on Slides not on 'Common Properties'
            const component = [...props.data.components]; // Create a shallow copy of the components array
            const index = component.findIndex((item) => item.ulid === newValue.ulid);
            if (index !== -1) {
                component[index] = { ...newValue };
                props.data.components = component;
            }
            emits('jumpToIndex', newValue.ulid)  // Jump to related Slide when update the data
        }
    },
    { deep: true }
);

// To change visibility of the each slide
const changeVisibility = (slide: any) => {
    const index = props.data.components.findIndex((item) => item.ulid === slide.ulid);
    if (index !== -1) {
        props.data.components[index].hasOwnProperty("visibility")
            ? (props.data.components[index].visibility = !props.data.components[index].visibility)
            : (props.data.components[index].visibility = false);
    }
};

const CommonBlueprint = ref(CommonSlidesBluprint.data)

const ComponentsBlueprint = ref(SlidesBluprint.data);


const setCommonEdit = () => {
    commonEditActive.value = !commonEditActive.value;

    if (commonEditActive.value) {
        currentComponentBeenEdited.value = null;
    } else {
        currentComponentBeenEdited.value = props.data.components[0];
    }

};

const uploadImageRespone = (res) => {
    let setData = [];
    for (const set of res.data) {
        setData.push({
            id:  null,
            ulid: ulid(),
            layout: {
                imageAlt: set.name,
            },
            image: {
                desktop: set,
            },
            backgroundType: {
                desktop: 'image'
            },
            visibility: true,
        });
    }
    const newFiles = [...setData];
    props.data.components = [...props.data.components, ...newFiles];
    isOpenCropModal.value = false;
    isOpenGalleryImages.value = false
    commonEditActive.value = false
    currentComponentBeenEdited.value = props.data.components[ props.data.components.length - 1 ]
};

// Onclick button 'Add Slide'
const addNewSlide = () => {
    let setData = []
    setData.push({
        id:  null,
        ulid: ulid(),
        layout: {
            imageAlt: 'New slide',
            centralStage: {
                title: useHeadlineText()[Math.floor(Math.random() * useHeadlineText().length)],
                style: {
                    color: "rgba(253, 224, 71, 255)",
                    fontSize: {
                        fontTitle: "text-[18px] lg:text-[32px]",
                        fontSubtitle: "text-[10px] lg:text-[15px]"
                    }
                }
            },
            background: {
                desktop: backgroundColorList[Math.floor(Math.random() * backgroundColorList.length)], // To random the background color on new slide
                tablet: backgroundColorList[Math.floor(Math.random() * backgroundColorList.length)],
                mobile: backgroundColorList[Math.floor(Math.random() * backgroundColorList.length)],
            },
            backgroundType: {
                desktop: 'color'
            },
        },
        image: {
            desktop: {},
            tablet: {},
            mobile: {},
        },
        visibility: true,
    });
    const newFiles = [...setData];
    props.data.components = [...props.data.components, ...newFiles];
}

// When on click to icon 'clone'
const duplicateSlide = (selectedSlide: SlideWorkshopData) => {
    const modifiedSlide = {
        ...selectedSlide,
        ulid: ulid()
    }
    let indexOfSelectedSlide = props.data.components.findIndex(item => item.ulid == selectedSlide.ulid)
    props.data.components.splice(indexOfSelectedSlide+1, 0, modifiedSlide)
}


const onPickImageGalery = (image) => {
    let setData = [];
    setData.push({
        id:  null,
        ulid: ulid(),
        layout: {
            imageAlt: image.name,
        },
        image: {
            desktop: image,
        },
        backgroundType: {
            desktop: 'image'
        },
        visibility: true,
    });
    const newFiles = [...setData];
    props.data.components = [...props.data.components, ...newFiles];
    isOpenCropModal.value = false;
    isOpenGalleryImages.value = false
    commonEditActive.value = false
    currentComponentBeenEdited.value = props.data.components[ props.data.components.length - 1 ]
}

onMounted(() => {
    commonEditActive.value = true
})

</script>

<template>
    <!-- <pre>{{ commonEditActive }}</pre> -->
    <div class="flex flex-grow gap-2.5">
        <div class="p-2.5 border rounded h-fit shadow w-1/4" v-if="data.components" @dragover="dragover"
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
            <div class="text-lg font-medium leading-none">{{ trans("Slides") }} <span class='text-dase'>({{ data.components.length }})</span></div>
            <draggable :list="data.components" group="slide " item-key="ulid" handle=".handle" class="max-h-96 overflow-auto p-0.5">
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
                            <div v-if="data.type === 'square'" class="">
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

        <!-- The Editor: Common Properties -->
        <div class="border border-gray-300 w-3/4 rounded-md" v-if="commonEditActive">
            <SliderCommonWorkshop ref="_SlideWorkshop" :currentComponentBeenEdited="props.data"
                :blueprint="CommonBlueprint" />
        </div>

        <!-- The Editor: Slide -->
        <div class="border border-gray-300 w-3/4 rounded-md" v-if="currentComponentBeenEdited != null">
            <SlideWorkshop ref="_SlideWorkshop" :bannerType="data.type" :common="data.common"
                :currentComponentBeenEdited="currentComponentBeenEdited" :blueprint="ComponentsBlueprint"
                :remove="removeComponent"  :uploadRoutes="imagesUploadRoute" />
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
            :crop-props="{ratio: data.type == 'square' ? {w: 1, h: 1} : {w: 4, h: 1}}" 
            :stockImageRoutes="galleryRoute.stock_images"
            :imagesUploadedRoutes="galleryRoute.uploaded_images"
        />


        <!-- Modal: Crop (add slide) -->
         <Modal :isOpen="isOpenCropModal" @onClose="closeCropModal">
            <div>
                <CropImage
                    :ratio="data.type == 'square' ? {w: 1, h: 1} : {w: 4, h: 1}"
                    :data="uploadedFilesList"
                    :imagesUploadRoute="route(imagesUploadRoute.name,imagesUploadRoute.parameters)"
                    :response="uploadImageRespone" />
            </div>
        </Modal>
    </div>
</template>
