<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref, inject } from "vue"
import 'vue-advanced-cropper/dist/style.css'
import 'vue-advanced-cropper/dist/theme.compact.css'
import Modal from '@/Components/Utils/Modal.vue'
import CropImage from '@/Components/CropImage/CropImage.vue'
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue"
import Image from '@/Components/Image.vue'
import { get } from 'lodash-es'
import { useBannerBackgroundColor } from "@/Composables/useStockList"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUpload } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { routeType } from "@/types/route"
library.add(faUpload)

const props = defineProps<{
    modelValue: any
    fieldData: any
    bannerType: string
    uploadRoutes: routeType
}>()

const emit = defineEmits(["update:modelValue"])
const screenView = inject('screenView')

const updatePath = (value: any) => {
    emit("update:modelValue", value)
}

const isOpen = ref(false)
const isOpenCropModal = ref(false)
const fileInput = ref<any>(null)
const addFiles = ref<any[]>([])

const ratio = ref(props.bannerType === 'square' ? { w:1, h:1 } : { w:4, h:1 })
const backgroundColorList = useBannerBackgroundColor()

const closeModal = () => isOpen.value = false

const closeCropModal = () => {
    addFiles.value = []
    isOpenCropModal.value = false
    if (fileInput.value) fileInput.value.value = ''
}

const onFileChange = (e:any) => {
    addFiles.value = e.target.files
    isOpenCropModal.value = true
}

const uploadImageRespone = (res:any) => {
    updatePath(res.data[0])
    closeCropModal()
    closeModal()
}

const onPickImage = (res:any) => {
    updatePath(res)
    closeCropModal()
    closeModal()
}

</script>

<template>
    <div class="block w-full">

        <!-- Gallery -->
        <Gallery 
            :open="isOpen" 
            @on-close="closeModal"  
            :uploadRoutes="route(uploadRoutes.name, uploadRoutes.parameters)"
            :tabs="['images_uploaded','stock_images']"
            @on-pick="onPickImage"
        />

        <!-- Crop -->
        <Modal :isOpen="isOpenCropModal" @onClose="closeCropModal">
            <CropImage
                :data="addFiles"
                :imagesUploadRoute="route(uploadRoutes.name, uploadRoutes.parameters)"
                :response="uploadImageRespone"
                :ratio="ratio"
            />
        </Modal>


        <!-- Preview -->
        <div class="flex justify-center w-full">
            <div
                class="w-fit max-h-20 lg:max-h-32 border border-gray-300 rounded-md overflow-hidden shadow"
                :class="[
                    bannerType === 'square'
                        ? 'aspect-square'
                        : `aspect-[${ratio.w}/${ratio.h}]`
                ]"
            >
                <div class="h-full relative flex items-center">

                    <!-- IMAGE -->
                    <div
                        class="h-full relative"
                    >
                        <Image
                            :src="get(props.modelValue,'source')"
                            :alt="props.modelValue?.image?.name"
                            :imageCover="true"
                        />
                    </div>

                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="w-full space-y-4 mt-2.5">

            <!-- IMAGE PICKER -->
            <div class="flex items-center gap-x-4 py-1">
                <div>An Image:</div>

                <div class="flex items-center gap-x-2">

                    <!-- upload -->
                    <Button style="secondary" size="xs" class="relative">
                        <FontAwesomeIcon icon="fas fa-upload" />
                        {{ trans(`Upload image ${screenView}`) }}

                        <label class="absolute inset-0 cursor-pointer" for="input-upload"/>
                        <input
                            id="input-upload"
                            type="file"
                            ref="fileInput"
                            accept="image/*"
                            class="sr-only"
                            @change="onFileChange"
                        />
                    </Button>

                    <!-- gallery -->
                    <Button
                        style="tertiary"
                        icon="fal fa-photo-video"
                        label="Gallery"
                        size="xs"
                        @click="isOpen = true"
                    />

                </div>

            </div>


        </div>
    </div>
</template>
