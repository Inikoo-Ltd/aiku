<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref, toRefs, watch } from "vue"
import 'vue-advanced-cropper/dist/style.css'
import 'vue-advanced-cropper/dist/theme.compact.css'
import Modal from '@/Components/Utils/Modal.vue'
import CropImage from '@/Components/CropImage/CropImage.vue'
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue";
import Image from '@/Components/Image.vue'
import { set, get } from 'lodash-es'
import ScreenView from "@/Components/ScreenView.vue"
import { useBannerBackgroundColor } from "@/Composables/useStockList"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUpload } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { routeType } from "@/types/route"
library.add(faUpload)

const props = defineProps<{
    data: any
    fieldName: string
    fieldData: any
    bannerType: string
    uploadRoutes : routeType
}>()


const { data, fieldName } = toRefs(props)
const isOpen = ref(false)
const fileInput = ref(null)
const screenView = ref('desktop')
const closeModal = () => {
    isOpen.value = false
}
const isOpenCropModal = ref(false)

const closeModalisOpenCropModal = () => {
    addFiles.value = []
    isOpenCropModal.value = false
    fileInput.value.value = ''
}

const setFormValue = (data, fieldName) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

const getNestedValue = (obj, keys) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === 'object' && key in acc) return acc[key]
        return null
    }, obj)
}

const value = ref(setFormValue(props.data.layout, 'background'))

watch(data, (newValue) => {
    value.value = setFormValue(newValue, props.fieldName)
})

const addFiles = ref([])
const onFileChange = (event) => {
    addFiles.value = event.target.files
    isOpenCropModal.value = true
}

watch(value, (newValue) => {
    updateLocalFormValue({ ...newValue })
})

const updateLocalFormValue = (newValue) => {
    let localData = { ...props.data }
    if (Array.isArray(props.fieldName)) {
        set(localData, props.fieldName,newValue )
    } else {
        localData[props.fieldName] = newValue
    }
    set(props.data, [props.fieldName], newValue )
}

// When select image from modal Gallery
const uploadImageRespone = (res) => {
    props.data.image = {
        ...props.data.image,
        ...{[screenView.value || 'desktop']: res.data[0]}
    }
    props.data.layout.backgroundType = {
        ...props.data.layout.backgroundType,
        ...{[screenView.value || 'desktop']: 'image'}
    }

    isOpenCropModal.value = false
    isOpen.value = false
}

// When click on the list background color
const onChangeBackgroundColor = (bgColor: string) => {
    props.data.layout.background = {
        ...props.data.layout.background,
        [screenView.value || 'desktop']: bgColor
    }
    props.data.layout.backgroundType = {
        ...props.data.layout.backgroundType,
        [screenView.value || 'desktop']: 'color'
    }
}

const ratio = ref(props.bannerType == 'square' ? { w: 1 , h: 1} : { w: 4 , h: 1})  // if Square then 1:1

const screenViewChange = (value: string) => {
    screenView.value = value
    if(props.bannerType == 'square'){
        ratio.value = { w: 1 , h: 1}
    } else {
        switch (value) {
            case 'mobile':
                ratio.value = { w : 2 , h : 1};
                break;
            case 'tablet':
                ratio.value = { w : 3 , h : 1};
                break;
            case 'desktop':
                ratio.value = { w : 4 , h : 1};
                break;
            default:
                ratio.value = { w : 4 , h : 1}; // Default ratio value if none of the cases match
                break;
        }
    }
}

const backgroundColorList = useBannerBackgroundColor() // Fetch color list from Composables

const onPickImage = (res) => {
    props.data.image = {
        ...props.data.image,
        ...{[screenView.value || 'desktop']: res}
    }
    props.data.layout.backgroundType = {
        ...props.data.layout.backgroundType,
        ...{[screenView.value || 'desktop']: 'image'}
    }

    isOpenCropModal.value = false
    isOpen.value = false
}

</script>

<template>
    <div class="block w-full">
        <!-- Popup: add image from Gallery -->

        <Gallery 
            :open="isOpen" 
            @on-close="closeModal"  
            :uploadRoutes="route(uploadRoutes.name,uploadRoutes.parameters)"
            :tabs="['images_uploaded', 'stock_images']" 
            @on-pick="onPickImage"
        />

        <!-- Popup: Crop when add image (landscape) -->
        <Modal :isOpen="isOpenCropModal" @onClose="closeModalisOpenCropModal">
            <div>    
                <CropImage
                    :data="addFiles"
                    :imagesUploadRoute="route(uploadRoutes.name,uploadRoutes.parameters)"
                    :response="uploadImageRespone"
                    :ratio="ratio"/>
            </div>
        </Modal>

        <div v-if="bannerType != 'square'" class="flex justify-end">
            <!-- Screenview only for landscape view on each breakpoints -->
            <ScreenView @screenView="screenViewChange" />
        </div>

        <!-- Preview -->
        <div class="flex justify-center w-full">
            <div class="w-fit max-h-20 lg:max-h-32 border border-gray-300 rounded-md overflow-hidden shadow transition-all duration-200 ease-in-out" :class="[
                bannerType == 'square'
                    ? 'aspect-square'  // If banner is a square
                    : screenView
                        ? `aspect-[${ratio.w}/${ratio.h}]`
                        : 'aspect-[2/1] md:aspect-[3/1] lg:aspect-[4/1]'
            ]">
                <div class="h-full relative flex items-center" >
                    <div v-if="get(data, ['layout', 'backgroundType', screenView || 'desktop'], 'image') === 'image'"
                        class="group h-full relative"
                    >
                        <Image :src="get(data, ['image', screenView || 'desktop', 'source'])"
                            :alt="data.image?.name" :imageCover="true"/>
                    </div>
                    <div v-else class="h-full w-96" :style="{ background: get(data, ['layout', 'background', screenView], 'gray')}">
                        <!-- If the background is a color -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Button -->
        <div class="w-full relative space-y-4 mt-2.5">
            <div class="flex flex-col gap-y-2">
                <div class="flex items-center gap-x-4 py-1"
                    :class="[get(data, ['layout', 'backgroundType', screenView || 'desktop'], 'image') == 'image' ? 'navigationSecondActiveCustomer pl-2' : 'navigationSecondCustomer']"
                >
                    <div>An Image:</div>
                    <div class="flex items-center gap-x-2">
                        <Button  :style="`secondary`" class="relative" size="xs">
                            <FontAwesomeIcon icon='fas fa-upload' class='' aria-hidden='true' />
                            {{ trans(`Upload image ${screenView}`) }}
                            <label class="bg-transparent inset-0 absolute inline-block cursor-pointer" id="input-slide-large-mask"
                                for="input-slide-large" />
                            <input type="file" @change="onFileChange" id="input-slide-large" name="input-slide-large"
                                ref="fileInput" accept="image/*"
                                class="absolute cursor-pointer rounded-md border-gray-300 sr-only" />
                        </Button>
                        <Button :style="`tertiary`" icon="fal fa-photo-video" label="Gallery" size="xs" class="relative" @click="isOpen = !isOpen" />
                        
                        <div v-if="bannerType === 'landscape'" class="overflow-hidden h-7 rounded shadow-md"
                            :class="[get(data, ['layout', 'backgroundType', screenView || 'desktop'], 'image') == 'image' ? 'ring-2 ring-offset-2 ring-gray-600' : 'hover:ring-2 hover:ring-offset-2 hover:ring-gray-400', `aspect-[${ratio.w}/${ratio.h}]`]">
                            <Image
                                :src="get(data, ['image', screenView || 'desktop', 'thumbnail'])"
                                :alt="data.image?.name" :imageCover="true"
                                @click="data.layout.backgroundType[screenView || 'desktop'] = 'image'"
                                class="h-auto cursor-pointer rounded overflow-hidden"
                            />
                        </div>
                        <div v-else class="ml-1 h-10 aspect-square overflow-hidden rounded shadow-md cursor-pointer"
                            :class="[get(data, ['layout', 'backgroundType', 'desktop'], 'image') == 'image' ? 'ring-2 ring-offset-2 ring-gray-600' : 'hover:ring-2 hover:ring-offset-2 hover:ring-gray-400']"
                            @click="data.layout.backgroundType['desktop'] = 'image'"
                        >
                            <Image
                                :src="get(data, ['image', 'desktop', 'thumbnail'])"
                                :alt="data.image?.name" :imageCover="true"
                                class="rounded h-full"
                            />
                        </div>
                    </div>
                </div>
                
                <!-- List: Background Color -->
                <div class="flex items-center gap-x-4"
                    :class="get(data, ['layout', 'backgroundType', screenView || 'desktop'], get(data, ['layout', 'backgroundType', 'desktop'], '')) === 'color'
                        ? 'navigationSecondActiveCustomer pl-2'
                        : 'navigationSecondCustomer'"
                >
                    <div class="whitespace-nowrap">Or a color:</div>
                    <!-- Add conditional click() to avoid user change color via inspect -->
                    <div class="h-8 flex items-center w-fit gap-x-1.5">
                        <div v-for="bgColor in backgroundColorList"
                            @click="onChangeBackgroundColor(bgColor)"
                            class="w-full rounded h-full aspect-square shadow cursor-pointer"
                            :class="data?.background?.[screenView || 'desktop'] ===  bgColor && data?.layout.backgroundType?.[screenView || 'desktop'] === 'color' 
                                ? 'ring-2 ring-offset-2 ring-gray-600'
                                : 'hover:ring-2 hover:ring-offset-0 hover:ring-gray-500'"
                            :style="{background: bgColor}" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>