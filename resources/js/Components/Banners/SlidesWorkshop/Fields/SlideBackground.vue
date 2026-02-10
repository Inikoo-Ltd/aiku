<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref, watch, computed } from "vue"
import 'vue-advanced-cropper/dist/style.css'
import 'vue-advanced-cropper/dist/theme.compact.css'
import Modal from '@/Components/Utils/Modal.vue'
import CropImage from '@/Components/CropImage/CropImage.vue'
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue"
import Image from '@/Components/Image.vue'
import { set, get, cloneDeep } from 'lodash-es'
import ScreenView from "@/Components/ScreenView.vue"
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

const local = ref(cloneDeep(props.modelValue))

watch(() => props.modelValue, v => {
    local.value = cloneDeep(v)
}, { deep: true })

const updateValue = (path: string | string[], value: any) => {
    const cloned = cloneDeep(local.value)
    set(cloned, path, value)
    local.value = cloned
    emit("update:modelValue", cloned)
}

const isOpen = ref(false)
const isOpenCropModal = ref(false)
const fileInput = ref(null)
const addFiles = ref([])
const screenView = ref("desktop")

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
    updateValue(['image', screenView.value], res.data[0])
    updateValue(['layout','backgroundType',screenView.value], 'image')
    closeCropModal()
    closeModal()
}

const onPickImage = (res:any) => {
    updateValue(['image', screenView.value], res)
    updateValue(['layout','backgroundType',screenView.value], 'image')
    closeCropModal()
    closeModal()
}

const onChangeBackgroundColor = (bg:string) => {
    updateValue(['layout','background',screenView.value], bg)
    updateValue(['layout','backgroundType',screenView.value], 'color')
}

const screenViewChange = (v:string) => {
    screenView.value = v

    if (props.bannerType === 'square') {
        ratio.value = { w:1, h:1 }
        return
    }

    if (v === 'mobile') ratio.value = { w:2, h:1 }
    else if (v === 'tablet') ratio.value = { w:3, h:1 }
    else ratio.value = { w:4, h:1 }
}
</script>

<template>
    <div class="block w-full">
        <!-- Gallery modal -->
        <Gallery 
            :open="isOpen" 
            @on-close="closeModal"  
            :uploadRoutes="route(uploadRoutes.name, uploadRoutes.parameters)"
            :tabs="['images_uploaded', 'stock_images']" 
            @on-pick="onPickImage"
        />

        <!-- Crop modal -->
        <Modal :isOpen="isOpenCropModal" @onClose="closeCropModal">
            <div>
                <CropImage
                    :data="addFiles"
                    :imagesUploadRoute="route(uploadRoutes.name, uploadRoutes.parameters)"
                    :response="uploadImageRespone"
                    :ratio="ratio"
                />
            </div>
        </Modal>

        <!-- Screen view -->
        <div v-if="bannerType !== 'square'" class="flex justify-end">
            <ScreenView @screenView="screenViewChange" />
        </div>

        <!-- Preview -->
        <div class="flex justify-center w-full">
            <div
                class="w-fit max-h-20 lg:max-h-32 border border-gray-300 rounded-md overflow-hidden shadow transition-all duration-200 ease-in-out"
                :class="[
                    bannerType === 'square'
                        ? 'aspect-square'
                        : screenView
                            ? `aspect-[${ratio.w}/${ratio.h}]`
                            : 'aspect-[2/1] md:aspect-[3/1] lg:aspect-[4/1]'
                ]"
            >
                <div class="h-full relative flex items-center">
                    <!-- IMAGE -->
                    <div
                        v-if="get(local, ['layout','backgroundType',screenView],'image') === 'image'"
                        class="group h-full relative"
                    >
                        <Image
                            :src="get(local, ['image',screenView,'source'])"
                            :alt="local?.image?.name"
                            :imageCover="true"
                        />
                    </div>

                    <!-- COLOR -->
                    <div
                        v-else
                        class="h-full w-96"
                        :style="{ background: get(local, ['layout','background',screenView],'gray') }"
                    />
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="w-full relative space-y-4 mt-2.5">
            <div class="flex flex-col gap-y-2">

                <!-- IMAGE PICKER -->
                <div
                    class="flex items-center gap-x-4 py-1"
                    :class="[
                        get(local,['layout','backgroundType',screenView],'image') === 'image'
                            ? 'navigationSecondActiveCustomer pl-2'
                            : 'navigationSecondCustomer'
                    ]"
                >
                    <div>An Image:</div>

                    <div class="flex items-center gap-x-2">
                        <!-- upload -->
                        <Button style="secondary" size="xs" class="relative">
                            <FontAwesomeIcon icon="fas fa-upload" />
                            {{ trans(`Upload image ${screenView}`) }}

                            <label
                                class="bg-transparent inset-0 absolute inline-block cursor-pointer"
                                for="input-upload"
                            />
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

                        <!-- thumbnail -->
                        <div
                            v-if="bannerType === 'landscape'"
                            class="overflow-hidden h-7 rounded shadow-md cursor-pointer"
                            :class="[
                                get(local,['layout','backgroundType',screenView],'image') === 'image'
                                    ? 'ring-2 ring-offset-2 ring-gray-600'
                                    : 'hover:ring-2 hover:ring-offset-2 hover:ring-gray-400',
                                `aspect-[${ratio.w}/${ratio.h}]`
                            ]"
                            @click="updateValue(['layout','backgroundType',screenView],'image')"
                        >
                            <Image
                                :src="get(local,['image',screenView,'thumbnail'])"
                                :alt="local?.image?.name"
                                :imageCover="true"
                                class="h-auto rounded"
                            />
                        </div>

                        <div
                            v-else
                            class="ml-1 h-10 aspect-square overflow-hidden rounded shadow-md cursor-pointer"
                            :class="[
                                get(local,['layout','backgroundType','desktop'],'image') === 'image'
                                    ? 'ring-2 ring-offset-2 ring-gray-600'
                                    : 'hover:ring-2 hover:ring-offset-2 hover:ring-gray-400'
                            ]"
                            @click="updateValue(['layout','backgroundType','desktop'],'image')"
                        >
                            <Image
                                :src="get(local,['image','desktop','thumbnail'])"
                                :alt="local?.image?.name"
                                :imageCover="true"
                                class="h-full rounded"
                            />
                        </div>
                    </div>
                </div>

                <!-- COLOR LIST -->
                <div
                    class="flex items-center gap-x-4"
                    :class="
                        get(local,['layout','backgroundType',screenView],'color') === 'color'
                            ? 'navigationSecondActiveCustomer pl-2'
                            : 'navigationSecondCustomer'
                    "
                >
                    <div class="whitespace-nowrap">Or a color:</div>

                    <div class="h-8 flex items-center w-fit gap-x-1.5">
                        <div
                            v-for="bgColor in backgroundColorList"
                            :key="bgColor"
                            class="rounded h-full aspect-square shadow cursor-pointer"
                            :style="{ background: bgColor }"
                            :class="
                                get(local,['layout','background',screenView]) === bgColor &&
                                get(local,['layout','backgroundType',screenView]) === 'color'
                                    ? 'ring-2 ring-offset-2 ring-gray-600'
                                    : 'hover:ring-2 hover:ring-gray-400'
                            "
                            @click="onChangeBackgroundColor(bgColor)"
                        />
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
