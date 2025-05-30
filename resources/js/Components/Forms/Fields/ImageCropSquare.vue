<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref, watch } from "vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faUndoAlt } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import Image from '@/Components/Image.vue'

import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faSpinnerThird, faExclamationCircle, faCheckCircle, faSpinnerThird, faUndoAlt)


/* const props = defineProps(['form', 'fieldName', 'options']) */

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        options: {
            aspectRatio: {
                width: Number,
                height: Number
            },
            minAspectRatio: {
                width: Number,
                height: Number
            },
            maxAspectRatio: {
                width: Number,
                height: Number
            },
        }
    }
}>()

console.log(props)
// const temporaryAvatar = ref(props.form[props.fieldName])

const numbKey = ref(0)
const tempImgToCrop = ref(null)
const imgAfterCrop = ref<Blob | null>(props.form[props.fieldName])
const onPickFile = async (file: File) => {
    _cropper.value?.reset()
    isOpenModalCrop.value = true
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = (e) => {
        tempImgToCrop.value = e.target?.result
    }
}

// Helper
const dataURLtoBlob = (dataurl) => {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);

    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }

    return new Blob([u8arr], {type: mime});
}

const isOpenModalCrop = ref(false)
const _cropper = ref(null)
const submitCrop = async () => {
    props.form.errors[props.fieldName] = null
    const { coordinates, canvas, } = await _cropper.value?.getResult()
    const imageDataURL = await canvas.toDataURL();
    // console.log('imageDataURL', imageDataURL, coordinates, canvas)
    imgAfterCrop.value = {
        original: imageDataURL
    }
    const imageBlob = dataURLtoBlob(imageDataURL);

    props.form[props.fieldName] = new File([imageBlob], "img.png", { type: "image/png" });
    isOpenModalCrop.value = false
}

const stencilProps =  (props.fieldData?.options?.minAspectRatio && props.fieldData?.options?.maxAspectRatio) ? 
    {
    minAspectRatio : props.fieldData?.options?.minAspectRatio,
    maxAspectRatio : props.fieldData?.options?.maxAspectRatio
    } : {aspectRatio : props.fieldData?.options?.aspectRatio ? props.fieldData?.options?.aspectRatio : 1/1}

watch(isOpenModalCrop, (value) => {
    _cropper.value?.refresh()
})
</script>

<template>
    <div class="w-fit min-w-32">      
        <Modal :isOpen="isOpenModalCrop" @close="isOpenModalCrop = false" width="max-w-xl w-full" :zIndex="999">
            <div class="w-full h-[300px] relative bg-gray-700">
                <Cropper
                    :key="numbKey"
                    ref="_cropper"
                    class="w-full h-full"
                    :src="tempImgToCrop"
                    :stencil-props="stencilProps"
                    imageClass="w-full h-full"
                    :auto-zoom="true"
                />
                <div @click="() => numbKey++" class="select-none px-2 py-1 cursor-pointer absolute top-2 text-white right-2 border border-gray-300 hover:bg-white/80 hover:text-gray-700 rounded">
                    <FontAwesomeIcon icon='fal fa-undo-alt' class='' fixed-width aria-hidden='true' />
                    {{ trans("Refresh") }}
                </div>
            </div>

            <div class="text-gray-500 italic text-xs mt-1">
                <FontAwesomeIcon icon='fal fa-info-circle' class='' fixed-width aria-hidden='true' />
                {{ trans("Use mouse scroll to zoom in and zoom out") }}
            </div>
            
            <div class="w-full mt-8">
                <Button @click="submitCrop" label="Crop" full size="xl" />
            </div>
        </Modal>

        <!-- Avatar Button: Large view -->
        <div class="bg-gray-100 relative overflow-hidden h-40 min-w-32 w-auto aspect-ratio rounded lg:inline-block ring-1 ring-gray-500 shadow"
            :class="form.errors[fieldName] ? 'errorShake' : ''"
        >
            <Image class="h-full rounded" :src="imgAfterCrop" alt="" />
            <label id="input-avatar-large-mask" for="input-avatar-large"
                class="absolute inset-0 flex h-full w-full items-center justify-center bg-black bg-opacity-50 text-sm font-medium text-white opacity-0 hover:opacity-100">
                <span>{{ trans("Change") }}</span>
                <input type="file" @input="onPickFile($event.target.files[0])" id="input-avatar-large" name="input-avatar-large" accept="image/*"
                    class="absolute inset-0 h-full w-full cursor-pointer rounded-md border-gray-300 opacity-0" />
            </label>
        </div>

        <!-- Icon: Error, Success, Loading -->
        <div class="absolute top-2 right-0 pr-3 flex items-center pointer-events-none">
            <FontAwesomeIcon v-if="form.errors[fieldName]" icon="fas fa-exclamation-circle" class="h-5 w-5 text-red-500" aria-hidden="true" />
            <FontAwesomeIcon v-if="form.recentlySuccessful" icon="fas fa-check-circle" class="h-5 w-5 text-green-500" aria-hidden="true" />
            <!-- <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third" class="h-5 w-5 animate-spin"/> -->
        </div>

        <div v-if="props.form.errors[props.fieldName]" class="text-red-700">
            {{ props.form.errors[props.fieldName] }}
        </div>
    </div>
</template>


