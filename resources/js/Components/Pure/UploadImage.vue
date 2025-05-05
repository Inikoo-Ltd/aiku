<script setup lang="ts">
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import Image from "@/Components/Image.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import Modal from "@/Components/Utils/Modal.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage, faPhotoVideo, faTrashAlt } from "@fal"
import { routeType } from "@/types/route"
import { cloneDeep } from "lodash-es"
import { router } from "@inertiajs/vue3"

library.add(faImage, faPhotoVideo, faTrashAlt)

const props = defineProps<{ modelValue: any; uploadRoutes: routeType; description?: string, content?: any }>()
const emits = defineEmits<{
    (e: "update:modelValue", value: any): void
    (e: "onUpload", value: Files[]): void
    (e: "autoSave"): void
}>()


const isOpenGalleryImages = ref(false)
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const addedFiles = ref<File[]>([])

const setAlt = async (imageFile) => {
    const payload = new FormData();
    payload.append('image', imageFile);
    payload.append('prompt', 'alt');

    try {
        const response = await axios.post(route("grp.ask-bot.vision.index"), payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        console.log("Alt text request successful:", response.data);
        /* emits("update:modelValue",  response.data.content,['alt']) */

    } catch (error) {
        console.error("Alt text request failed:", error);
    }
};


const handleUpload = async () => {
    try {
        const formData = new FormData()
        addedFiles.value.forEach((file, index) => {
            formData.append(`images[${index}]`, file)
        })

        const response = await axios.post(route(props.uploadRoutes.name, props.uploadRoutes.parameters), formData, {
            headers: { "Content-Type": "multipart/form-data" },
        })
        
        const updatedModelValue = { ...props.modelValue, ...cloneDeep(response.data.data[0].source) }
        emits("update:modelValue", updatedModelValue)
        emits("autoSave")
        if (addedFiles.value.length > 0) {
            setAlt(addedFiles.value[0]);
        }
        addedFiles.value = []
    } catch (error) {
        console.log(error)
        notify({ title: "Failed", text: "Error while uploading data", type: "error" })
    }
}

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files) {
        addedFiles.value = Array.from(target.files)
        handleUpload()
    }
}

const dragOver = (event: DragEvent) => {
    event.preventDefault()
    isDragging.value = true
}

const dragLeave = () => {
    isDragging.value = false
}

const drop = (event: DragEvent) => {
    event.preventDefault()
    if (event.dataTransfer?.files) {
        addedFiles.value = Array.from(event.dataTransfer.files)
        handleUpload()
    }
    isDragging.value = false
}

const onPickImage = (selectedImages: any[]) => {
    isOpenGalleryImages.value = false
    emits("update:modelValue", cloneDeep(selectedImages[0].source))
    emits("autoSave")
}

const deleteImage = () => {
    emits("update:modelValue", null)
}
</script>

<template>
    <div @dragover="dragOver" @dragleave="dragLeave" @drop="drop"
        class="group hover:bg-gray-100 relative border border-gray-400 border-dashed overflow-hidden rounded-md text-center cursor-pointer"
        @click="() => fileInput?.click()">
        <input type="file" multiple ref="fileInput" class="hidden" @change="onFileChange" />

        <div v-if="!modelValue" class="text-sm">
            <div class="py-3">
                <p>{{ trans("Drag Images Here.") }}</p>
                <p class="text-xs">{{ trans("PNG, JPG, GIF up to 10MB") }}</p>
            </div>
        </div>

        <div v-else class="h-32 relative flex justify-center items-center">
            <Image :src="modelValue" class="w-auto h-fit" />
            <div class="absolute hover:bg-black/50 z-10 inset-0 flex items-center justify-center text-white text-sm opacity-0 group-hover:opacity-100">
                {{ trans("Upload image") }}
            </div>
        </div>
    </div>

    <div class="flex justify-between gap-2 mt-2">
        <Button id="gallery" :style="`tertiary`" :icon="'fal fa-photo-video'" label="Open gallery" size="xs"
            @click="(event) => { event.stopPropagation(); isOpenGalleryImages = true }" />

        <Button
            v-if="modelValue"
            id="gallery"
            type="negative"
            icon="far fa-trash-alt"
            size="xs"
            class="relative hover:text-gray-700"
            @click="(event) => { event.stopPropagation(); deleteImage() }" />
    </div>

    <Modal :isOpen="isOpenGalleryImages" @onClose="() => (isOpenGalleryImages = false)" width="w-3/4">
        <GalleryManagement :maxSelected="1" :tabs="['images_uploaded', 'stock_images']"
            :closePopup="() => (isOpenGalleryImages = false)" @submitSelectedImages="onPickImage" />
    </Modal>
</template>

<style scoped></style>
