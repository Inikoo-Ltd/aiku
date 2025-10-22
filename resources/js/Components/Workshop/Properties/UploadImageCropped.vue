<script setup lang="ts">
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import Image from "@/Components/Image.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage, faPhotoVideo, faTrashAlt } from "@fal"
import { routeType } from "@/types/route"
import { cloneDeep } from "lodash-es"

// PrimeVue Dialog
import Dialog from "primevue/dialog"

// Vue Advanced Cropper
import { Cropper } from "vue-advanced-cropper"
import "vue-advanced-cropper/dist/style.css"

library.add(faImage, faPhotoVideo, faTrashAlt)

const props = withDefaults(
    defineProps<{
        modelValue: any
        uploadRoutes: routeType
        description?: string
        stencilProps?: Record<string, any>
    }>(),
    {
        stencilProps: () => ({
            aspectRatio: 1,
            movable: true,
            scalable: true,
            resizable: true,
        }),
    }
)

const emits = defineEmits<{
    (e: "update:modelValue", value: any): void
    (e: "onUpload", value: File[]): void
    (e: "autoSave"): void
}>()

// --- Refs ---
const isOpenGalleryImages = ref(false)
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const currentFile = ref<File | null>(null)
const currentFileUrl = ref<string | null>(null)
const cropperVisible = ref(false)
const cropperRef = ref<any>(null)
const isLoadingSubmit = ref(false)

// --- Drag & Drop ---
const dragOver = (event: DragEvent) => { event.preventDefault(); isDragging.value = true }
const dragLeave = () => isDragging.value = false
const drop = (event: DragEvent) => {
    event.preventDefault()
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) startCrop(event.dataTransfer.files[0])
    isDragging.value = false
}

// --- File Picker ---
const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files[0]) startCrop(target.files[0])
}

// --- Start Crop ---
const startCrop = (file: File) => {
    if (currentFileUrl.value) URL.revokeObjectURL(currentFileUrl.value)
    currentFile.value = file
    currentFileUrl.value = URL.createObjectURL(file)
    cropperVisible.value = true
}

// --- Confirm Crop ---
const confirmCrop = async () => {
    if (!cropperRef.value || !currentFile.value) return
    try {
        isLoadingSubmit.value = true
        const { canvas } = cropperRef.value.getResult()
        if (!canvas) return

        const blob: Blob = await new Promise(resolve =>
            canvas.toBlob(resolve, "image/jpeg", 0.9)
        )

        const formData = new FormData()
        formData.append("images[0]", blob, currentFile.value.name)

        const response = await axios.post(
            route(props.uploadRoutes.name, props.uploadRoutes.parameters),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        const updatedModelValue = cloneDeep(response.data.data[0].source)
        emits("update:modelValue", updatedModelValue)
        notify({ title: "Success", text: "Image uploaded successfully", type: "success" })

    } catch (error) {
        notify({ title: "Failed", text: "Error while uploading image", type: "error" })
    } finally {
        isLoadingSubmit.value = false
        cropperVisible.value = false
        currentFile.value = null
        if (currentFileUrl.value) {
            URL.revokeObjectURL(currentFileUrl.value)
            currentFileUrl.value = null
        }
    }
}

// --- Cancel Crop ---
const cancelCrop = () => {
    cropperVisible.value = false
    currentFile.value = null
    if (currentFileUrl.value) {
        URL.revokeObjectURL(currentFileUrl.value)
        currentFileUrl.value = null
    }
}

// --- Gallery Selection ---
const onPickImage = (selectedImages: any[]) => {
    isOpenGalleryImages.value = false
    emits("update:modelValue", cloneDeep(selectedImages[0].source))
    emits("autoSave")
}

// --- Delete ---
const deleteImage = () => emits("update:modelValue", null)
</script>

<template>
    <!-- Drag & Drop Area -->
    <div @dragover="dragOver" @dragleave="dragLeave" @drop="drop"
        class="group hover:bg-gray-100 relative border border-gray-400 border-dashed overflow-hidden rounded-md text-center cursor-pointer"
        @click="() => fileInput?.click()">
        <input type="file" accept="image/*" ref="fileInput" class="hidden" @change="onFileChange" />

        <div v-if="!modelValue" class="text-sm py-3">
            <p>{{ trans("Drag Images Here.") }}</p>
            <p class="text-xs">{{ trans("PNG, JPG, GIF up to 10MB") }}</p>
        </div>

        <div v-else class="h-32 relative flex justify-center items-center">
            <Image :src="modelValue" class="w-auto h-fit" />
            <div
                class="absolute hover:bg-black/60 z-10 inset-0 flex items-center justify-center text-white text-sm opacity-0 group-hover:opacity-100">
                {{ trans("Upload image") }}
            </div>
        </div>
    </div>

    <!-- Buttons -->
    <div class="flex justify-between gap-2 mt-2">
        <Button id="gallery" :style="`tertiary`" :icon="'fal fa-photo-video'" label="Open gallery" size="xs"
            @click="(event) => { event.stopPropagation(); isOpenGalleryImages = true }" />
        <Button v-if="modelValue" type="negative" icon="far fa-trash-alt" size="xs"
            @click="(event) => { event.stopPropagation(); deleteImage() }" />
    </div>

    <!-- Gallery Dialog -->
    <Dialog v-model:visible="isOpenGalleryImages" modal header="Select Image" :style="{ width: '75%' }" closable>
        <GalleryManagement :maxSelected="1" :tabs="['images_uploaded', 'stock_images']"
            :closePopup="() => (isOpenGalleryImages = false)" @submitSelectedImages="onPickImage" />
    </Dialog>

    <!-- Cropper Dialog -->
    <Dialog v-model:visible="cropperVisible" modal header="Crop Image" :style="{ width: '75%' }" closable>
        <div v-if="currentFileUrl" class="p-4">
            <Cropper :src="currentFileUrl" ref="cropperRef" :stencil-props="stencilProps"
                class="rounded-xl border border-gray-300 overflow-hidden w-full max-w-2xl h-[400px]" />
            <div class="flex justify-end gap-2 mt-2">
                <Button label="Cancel" @click="cancelCrop" size="xs" />
                <Button label="Upload" @click="confirmCrop" size="xs" :loading="isLoadingSubmit" />
            </div>
        </div>
    </Dialog>
</template>
