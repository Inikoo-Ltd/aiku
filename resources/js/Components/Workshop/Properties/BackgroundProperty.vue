<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { inject, onBeforeMount, onMounted, ref } from 'vue'
import Image from '@/Components/Image.vue'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureRadio from '@/Components/Pure/PureRadio.vue'
import ColorPicker from '@/Components/Utils/ColorPicker.vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faImage, faPalette } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { routeType } from '@/types/route'
import { ImageData } from '@/types/Image'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import RadioButton from 'primevue/radiobutton'
import { set } from 'lodash-es'
library.add(faImage, faPalette)

interface BackgroundProperty {
    type: string
    color: string
    image: ImageData
}

const props = defineProps<{
    uploadImageRoute?: routeType
}>()

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

const model = defineModel<BackgroundProperty>({
    required: true,
    default : {
        type : 'color'
    }
})

/* const onSaveWorkshopFromId: Function = inject('onSaveWorkshopFromId', (e?: number) => { console.log('onSaveWorkshopFromId not provided') })
const side_editor_block_id = inject('side_editor_block_id', () => { console.log('side_editor_block_id not provided') }) */

const isOpenGallery = ref(false)

const route_list = inject('route_list', null)

const onSubmitSelectedImage = (images: ImageData[]) => {
    model.value.image = images[0]
    isOpenGallery.value = false
    model.value.type = 'image'
    emits('update:modelValue', model.value)
  /*   onSaveWorkshopFromId(side_editor_block_id, 'background property') */
}



const isLoadingSubmit = ref(false)
const onSubmitUpload = async (files: File[], galleryUploadRef : any) => {
    const formData = new FormData()
    Array.from(files).forEach((file, index) => {
        formData.append(`images[${index}]`, file)
    })

    // console.log('form', files, formData)
    isLoadingSubmit.value = true
    try {

        const aaa = await axios.post(route(props.uploadImageRoute.name, props.uploadImageRoute.parameters),
            formData,
            {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            }
        )
        
        model.value.image = aaa.data.data[0]
        emits('update:modelValue', model.value)

        // Assuming you want to notify on success
        notify({
            title: trans('Success'),
            text: trans('New image added'),
            type: 'success',
        });

        console.log(galleryUploadRef)
        isOpenGallery.value = false
        // Clear the input or perform any other success actions
        if (galleryUploadRef.value) {
            galleryUploadRef.value.fileUploadRef.uploadedFiles = files
            galleryUploadRef.value.fileUploadRef.files = []
        }


    } catch (error) {
        console.error('Upload error:', error);

        // Notify on error
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to add new image'),
            type: 'error',
        });
    } finally {
        // This block will always execute, regardless of success or error
        isLoadingSubmit.value = false;
    }
}
</script>

<template>
    <div v-if="model?.type" class="flex items-center justify-between gap-x-3 flex-wrap px-6 w-full relative">
        <div class="relative flex items-center gap-x-2 py-1" >
            <div class="group rounded-md relative shadow-lg border border-gray-300">
                <div class="relative h-12 w-12 cursor-pointer rounded overflow-hidden">
                    <Image
                        :src="model?.image?.thumbnail"
                        :key="model?.image?.id"
                        :alt="model?.image?.name"
                        :imageCover="true"
                        class="h-full"
                        v-tooltip="trans('Image background')"
                    />
                    
                    <div v-if="model?.type === 'image'" @click="() => isOpenGallery = true" class="hidden group-hover:flex absolute inset-0 bg-black/30 items-center justify-center cursor-pointer">
                        <FontAwesomeIcon icon='fal fa-image' class='text-white' fixed-width aria-hidden='true' />
                    </div>

                    <div v-else @click="() => (model.type = 'image',emits('update:modelValue', model))" class="flex absolute inset-0 bg-gray-200/70 hover:bg-gray-100/40 items-center justify-center cursor-pointer" />
                </div>
            </div>
            <PureRadio
                v-model="model.type"
                @update:modelValue="() => emits('update:modelValue', model)"
                :options="[{ name: 'image'}]"
                by="name"
                key="image1"
            />
        </div>
        
        <!-- {{ model }} -->
        <!-- List: Background Color -->
        <div class="flex items-center gap-x-4 h-min" >
            <div class="relative h-12 aspect-square rounded-md shadow">
                <ColorPicker
                    :color="model.color || '#111111'"
                    class=""
                    @changeColor="(newColor)=> {
                        model.color = `rgba(${newColor.rgba.r}, ${newColor.rgba.g}, ${newColor.rgba.b}, ${newColor.rgba.a})`,
                        model.type = 'color',
                        emits('update:modelValue', model)
                    }"
                    closeButton
                    v-tooltip="trans('Color background')"
                    :isEditable="!model.color?.includes('var')"
                >
                    <template #button>
                        <div class="group relative h-12 w-12 overflow-hidden rounded" :style="{
                            backgroundColor: model.color
                        }">
                            <div class="hidden group-hover:flex absolute inset-0 bg-black/30 items-center justify-center cursor-pointer">
                                <FontAwesomeIcon icon='fal fa-palette' class='text-white' fixed-width aria-hidden='true' />
                            </div>
                        </div>

                    </template>

                    <template #before-main-picker>
                        <div class="flex items-center gap-2">
                            <RadioButton size="small" v-model="model.color" @update:modelValue="() => emits('update:modelValue', model)" inputId="bg-color-picker-1" name="bg-color-picker" value="var(--iris-color-primary)" />
                            <label class="cursor-pointer" for="bg-color-picker-1">{{ trans("Primary color") }} 
                                <a  :href="route(route().params.shop ? 'grp.org.shops.show.web.websites.workshop' : 'grp.org.fulfilments.show.web.websites.workshop', {...route().params, tab: 'website_layout', section: 'theme_colors'})" as="a" target="_blank" class="text-xs text-blue-600">{{ trans("themes") }}</a>
                            </label>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <RadioButton size="small"
                                :modelValue="!model.color?.includes('var') ? '#111111' : null"
                                @update:modelValue="(e) => model.color.includes('var') ? (model.color = '#111111', emits('update:modelValue', model)) : false"
                                inputId="bg-color-picker-3"
                                name="bg-color-picker"
                                value="#111111" />
                            <label class="cursor-pointer" for="bg-color-picker-3">{{ trans("Custom") }}</label>
                        </div>
                    </template>
                </ColorPicker>
                
                <div v-if="model.type !== 'color'" @click="() => (model.type = 'color', emits('update:modelValue', model))" class="flex absolute inset-0 items-center justify-center cursor-pointer" />

            </div>
            <!-- <div v-else class="h-8 w-8 rounded-md border border-gray-300 shadow" :style="{background: model.color}" /> -->
            <PureRadio
                v-model="model.type"
                @update:modelValue="() => emits('update:modelValue', model)"
                :options="[{ name: 'color'}]"
                by="name"
                key="color2"
            />
        </div>
    </div>

    <Modal :isOpen="isOpenGallery" @onClose="() => isOpenGallery = false" width="w-3/4" >
        <GalleryManagement
            :uploadRoute="route_list?.upload_image"
            :maxSelected="1"
            :multiple="false"
            :submitUpload="onSubmitUpload"
            @selectImage="(image: {}) => false"
            @submitSelectedImages="(images: ImageData[]) => onSubmitSelectedImage(images)"
        />
    </Modal>
</template>