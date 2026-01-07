<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import axios from 'axios'
import { ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import Modal from '@/Components/Utils/Modal.vue'
import CropImage from "@/Components/CropImage/CropImage.vue"

library.add(faCube, faStar, faImage)

const props = defineProps<{
    uploadRoutes: string
    useCrop: Boolean,
    cropProps: Object
    imagesUploadedRoutes: routeType,
    stockImageRoutes: routeType
}>()

const isDragging = ref(false);
const uploadedFilesList = ref([])
const fileInput = ref();
const isOpenModalCrop = ref(false)

const emits = defineEmits<{
    (e: 'onUpload', value: Object): void
    (e: 'pick', value: Object): void
}>()

const onUpload = async () => {
    if (!props.useCrop) {
        try {
            const formData = new FormData();
            Array.from(uploadedFilesList.value).forEach((file, index) => {
                formData.append(`images[${index}]`, file);
            });
            const response = await axios.post(props.uploadRoutes, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
            emits('onUpload', response.data)
        } catch (error) {
            console.error('error', error);
            notify({
                title: "Failed",
                text: "Error while Uploading data",
                type: "error"
            })
        }
    } else {
        isOpenModalCrop.value = true
    }
};

const addComponent = (event) => {
    uploadedFilesList.value = event.target.files;
    onUpload();
}

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
    isDragging.value = false;
    onUpload();
};

</script>

<template>
    <div @dragover="dragover" @dragleave="dragleave" @drop="drop"
        class="relative block h-full w-full rounded-lg border-2 border-dashed border-gray-300 p-12 text-center hover:border-gray-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 p-auto">
        <font-awesome-icon :icon="['fas', 'image']" class="mx-auto h-12 w-12 text-gray-400" />
        <label class="bg-transparent inset-0 absolute inline-block cursor-pointer" for="fileInput">
        </label>
        <input ref="fileInput" type="file" multiple name="file" id="fileInput" accept="image/*"
            class="absolute cursor-pointer rounded-md border-gray-300 sr-only" @change="addComponent" />
        <span class="mt-2 block text-sm font-semibold text-gray-900">Drag and Drop Image Here</span>
        <span class="mt-2 block text-xs font-semibold text-gray-300">Click here to upload</span>
    </div>

    <Modal :isOpen="isOpenModalCrop" @onClose="isOpenModalCrop = false">
        <div>
            <CropImage v-bind="cropProps" :data="uploadedFilesList" :imagesUploadRoute="uploadRoutes"
                :response="(e) => emits('onUpload', e)" />
        </div>
    </Modal>
</template>
