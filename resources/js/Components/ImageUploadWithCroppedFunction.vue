<script setup lang="ts">
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { Cropper } from "vue-advanced-cropper"
import "vue-advanced-cropper/dist/style.css"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage, faPhotoVideo, faTrashAlt } from "@fal"
import { routeType } from "@/types/route"
import { cloneDeep } from "lodash-es"
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

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
  (e: "dialog", value: any): void
}>()

// states
const isLoadingSubmit = ref(false)
const isCropping = ref(false)
const selectedFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const cropperRef = ref()

/**
 * When user selects images from gallery (already uploaded)
 */
const onPickImage = (selectedImages: any[]) => {
  emits("update:modelValue", cloneDeep(selectedImages[0].source))
  emits("dialog", false)
}

/**
 * When user uploads new image(s)
 * -> Open cropper to adjust before uploading
 */
const handleUpload = async (files: File[]) => {
  if (!files || !files.length) return
  const file = files[0]
  selectedFile.value = file
  imagePreview.value = URL.createObjectURL(file)
  isCropping.value = true
}

/**
 * Confirm cropped image and upload to server
 */
const confirmCrop = async () => {
  try {
    isLoadingSubmit.value = true
    const { canvas } = cropperRef.value.getResult()
    if (!canvas) return

    const blob: Blob = await new Promise(resolve =>
      canvas.toBlob(resolve, "image/jpeg", 0.9)
    )

    const formData = new FormData()
    formData.append("images[0]", blob, selectedFile.value?.name || "cropped.jpg")

    const response = await axios.post(
      route(props.uploadRoutes.name, props.uploadRoutes.parameters),
      formData,
      { headers: { "Content-Type": "multipart/form-data" } }
    )

    const updatedModelValue = {
      ...props.modelValue,
      ...cloneDeep(response.data.data[0].source),
    }

    emits("update:modelValue", updatedModelValue)
    emits("dialog", false)
    notify({ title: "Success", text: "Image uploaded successfully", type: "success" })

    // reset
    isCropping.value = false
    selectedFile.value = null
    imagePreview.value = null
  } catch (error) {
    notify({ title: "Failed", text: "Error while uploading image", type: "error" })
  } finally {
    isLoadingSubmit.value = false
  }
}

/**
 * Cancel cropping and go back to gallery
 */
const cancelCrop = () => {
  isCropping.value = false
  selectedFile.value = null
  imagePreview.value = null
}

</script>

<template>
  <div>
    <!-- Show Cropper if user is cropping -->
    <div v-if="isCropping" class="w-full">
      <div class="flex flex-col items-center gap-4">
        <h2 class="font-semibold text-gray-700 text-lg">Adjust Image Before Upload</h2>

        <Cropper
          ref="cropperRef"
          :src="imagePreview"
          :stencil-props="stencilProps"
          class="rounded-xl border border-gray-300 overflow-hidden w-full max-w-2xl h-[400px]"
        />

        <div class="flex justify-center gap-3">
          <Button
            @click="cancelCrop"
            type="negative"
            :label="'cancel'"
          />

          <Button
            @click="confirmCrop"
            :disabled="isLoadingSubmit"
            :loading="isLoadingSubmit"
            :label="isLoadingSubmit ? 'Uploading...' : 'Confirm & Upload'"
          />
        </div>
      </div>
    </div>

    <!-- Show Gallery if not cropping -->
    <GalleryManagement
      v-else
      :submitUpload="handleUpload"
      :maxSelected="1"
      :tabs="['upload', 'images_uploaded', 'stock_images']"
      @submitSelectedImages="onPickImage"
      :isLoadingSubmit="isLoadingSubmit"
    />
  </div>
</template>
