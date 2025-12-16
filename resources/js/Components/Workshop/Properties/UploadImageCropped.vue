<script setup lang="ts">
import { ref, computed, watch } from "vue"
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
import Dialog from "primevue/dialog"
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

const isOpenGalleryImages = ref(false)
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const currentFile = ref<File | null>(null)
const currentFileUrl = ref<string | null>(null)
const cropperVisible = ref(false)
const cropperRef = ref<any>(null)
const isLoadingSubmit = ref(false)

// --- Aspect Ratio Control ---
const aspectRatios = computed(() => {
    const ratio = props.stencilProps?.aspectRatio
    return Array.isArray(ratio) ? ratio : [ratio]
})
const selectedRatio = ref(aspectRatios.value[0])

// update selected ratio if prop changes
watch(aspectRatios, (newRatios) => {
    if (!newRatios.includes(selectedRatio.value)) {
        selectedRatio.value = newRatios[0]
    }
})

// --- Drag & Drop ---
const dragOver = (e: DragEvent) => { e.preventDefault(); isDragging.value = true }
const dragLeave = () => (isDragging.value = false)
const drop = (e: DragEvent) => {
    e.preventDefault()
    if (e.dataTransfer?.files?.[0]) startCrop(e.dataTransfer.files[0])
    isDragging.value = false
}

// --- File Picker ---
const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files?.[0]) startCrop(target.files[0])
}

// --- Start Crop ---
const startCrop = (file: File) => {
    // 🔍 Jika GIF → langsung upload, jangan crop
    if (file.type === "image/gif") {
        uploadGifDirectly(file)
        return
    }

    // --- normal crop flow ---
    if (currentFileUrl.value) URL.revokeObjectURL(currentFileUrl.value)
    currentFile.value = file
    currentFileUrl.value = URL.createObjectURL(file)
    cropperVisible.value = true
}

const uploadGifDirectly = async (file: File) => {
    try {
        isLoadingSubmit.value = true

        const formData = new FormData()
        formData.append("images[0]", file, file.name)

        const response = await axios.post(
            route(props.uploadRoutes.name, props.uploadRoutes.parameters),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        emits("update:modelValue", cloneDeep(response.data.data[0].source))
        notify({ title: "Success", text: "GIF uploaded successfully", type: "success" })
    } catch (error) {
        notify({ title: "Failed", text: "Error while uploading GIF", type: "error" })
    } finally {
        isLoadingSubmit.value = false
    }
}


// --- Confirm Crop ---
const confirmCrop = async () => {
    if (!cropperRef.value || !currentFile.value) return
    try {
        isLoadingSubmit.value = true
        const { canvas } = cropperRef.value.getResult()
        if (!canvas) return

        const fileType = currentFile.value.type === "image/png" ? "image/png" : "image/jpeg"
        const fileExtension = fileType === "image/png" ? "png" : "jpg"
        const blob: Blob = await new Promise(resolve => canvas.toBlob(resolve, fileType, 0.9))

        const formData = new FormData()
        formData.append("images[0]", blob, currentFile.value.name.replace(/\.[^/.]+$/, `.${fileExtension}`))

        const response = await axios.post(
            route(props.uploadRoutes.name, props.uploadRoutes.parameters),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        emits("update:modelValue", cloneDeep(response.data.data[0].source))
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

const cancelCrop = () => {
    cropperVisible.value = false
    currentFile.value = null
    if (currentFileUrl.value) {
        URL.revokeObjectURL(currentFileUrl.value)
        currentFileUrl.value = null
    }
}

const onPickImage = (selectedImages: any[]) => {
    isOpenGalleryImages.value = false
    emits("update:modelValue", cloneDeep(selectedImages[0].source))
    emits("autoSave")
}

const recommendedPixels = computed(() => {
	const width = props.stencilProps?.width || 400
	const ratio = props.stencilProps?.aspectRatio

	// if aspectRatio is an array → show all recommended sizes (skip null / "Free")
	if (Array.isArray(ratio)) {
		return ratio
			.filter((r) => r !== null) // 👈 ignore "Free" mode
			.map((r) => `${width} × ${Math.round(width / r)} px`)
			.join(", ")
	}

	// single ratio (null means "Free")
	if (ratio === null) {
		return "Free cropping allowed"
	}

	// single fixed ratio
	const height = Math.round(width / (ratio || 1))
	return `${width} × ${height} px`
})


const deleteImage = () => emits("update:modelValue", null)

const knownRatios: Record<number, string> = {
  1: "1:1",
  [4 / 3]: "4:3",
  [3 / 4]: "3:4",
  [16 / 9]: "16:9",
  [9 / 16]: "9:16",
  [5 / 4]: "5:4",
  [4 / 5]: "4:5",
}

const formatRatioLabel = (ratio: number | null) => {
  if (ratio === null) return "Custom"
  const closest = Object.keys(knownRatios)
    .map(Number)
    .reduce((prev, curr) =>
      Math.abs(curr - ratio) < Math.abs(prev - ratio) ? curr : prev
    )
  if (Math.abs(closest - ratio) < 0.01) {
    return knownRatios[closest]
  }
  return `${Math.round(ratio * 100) / 100}:1`
}

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

    <div class="text-gray text-xs mt-2" v-if="stencilProps?.aspectRatio">Recommended image: {{ recommendedPixels }}</div>

    <!-- Buttons -->
    <div class="flex justify-between gap-2 mt-2">
        <Button id="gallery" style="tertiary" icon="fal fa-photo-video" label="Open gallery" size="xs"
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
        <div v-if="currentFileUrl" class="p-4 space-y-3">
            <!-- Aspect Ratio Options -->
            <div v-if="aspectRatios.length > 1" class="flex gap-2 justify-center mb-2">
                <Button v-for="ratio in aspectRatios" :key="ratio + selectedRatio" :label="formatRatioLabel(ratio)"
                    :type="selectedRatio === ratio ? 'primary' : 'tertiary'" size="xs"
                    @click="selectedRatio = ratio" />
            </div>

            <!-- Cropper -->
            <Cropper :src="currentFileUrl" ref="cropperRef"
                :stencil-props="{ ...stencilProps, aspectRatio: selectedRatio }"
                class="rounded-xl border border-gray-300 overflow-hidden w-full max-w-2xl h-[400px] mx-auto" />

            <div class="flex justify-center gap-2 mt-3">
                <Button label="Cancel" type="negative" @click="cancelCrop" />
                <Button @click="confirmCrop" :disabled="isLoadingSubmit" :loading="isLoadingSubmit"
                    :label="isLoadingSubmit ? 'Uploading...' : 'Confirm & Upload'" />
            </div>
        </div>
    </Dialog>
</template>