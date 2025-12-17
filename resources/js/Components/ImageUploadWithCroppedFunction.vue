<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from "vue"
import { Cropper } from "vue-advanced-cropper"
import "vue-advanced-cropper/dist/style.css"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { cloneDeep } from "lodash-es"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage, faPhotoVideo, faTrashAlt } from "@fal"
import { routeType } from "@/types/route"

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
			width: 400,
		}),
	}
)

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "dialog", value: any): void
}>()

// States
const isLoadingSubmit = ref(false)
const isCropping = ref(false)
const selectedFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const cropperRef = ref()

// Computed aspect ratios
const aspectRatios = computed(() => {
	const ratio = props.stencilProps?.aspectRatio
	return Array.isArray(ratio) ? ratio : [ratio]
})
const selectedRatio = ref(aspectRatios.value[0])

// Update selected ratio if prop changes
watch(aspectRatios, (newRatios) => {
	if (!newRatios.includes(selectedRatio.value)) {
		selectedRatio.value = newRatios[0]
	}
})

// Known ratio labels
const knownRatios: Record<number, string> = {
	1: "1:1",
	[4 / 3]: "4:3",
	[3 / 4]: "3:4",
	[16 / 9]: "16:9",
	[9 / 16]: "9:16",
	[5 / 4]: "5:4",
	[4 / 5]: "4:5",
}

// Format ratio labels
const formatRatioLabel = (ratio: number) => {
	if (ratio === null) return "custom"
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

// Recommended resolution display
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

/**
 * Handle image selection from gallery
 */
const onPickImage = (selectedImages: any[]) => {
	emits("update:modelValue", cloneDeep(selectedImages[0].source))
	emits("dialog", false)
}

/**
 * Handle new upload
 */
const handleUpload = async (files: File[]) => {
	if (!files || !files.length) return
	const file = files[0]

	// Validate file type (allow gif)
	if (!file.type.startsWith("image/")) {
		notify({
			title: "Invalid File",
			text: "Please upload a valid image file.",
			type: "warn",
		})
		return
	}

	if (file.type === "image/gif") {
		try {
			isLoadingSubmit.value = true

			const formData = new FormData()
			formData.append("images[0]", file, file.name)

			console.log('Uploading GIF file directly', file)

			const response = await axios.post(
				route(props.uploadRoutes.name, props.uploadRoutes.parameters),
				formData,
				{
					headers: { "Content-Type": "multipart/form-data" },
				}
			)

			const merged = {
				...props.modelValue,
				...cloneDeep(response.data.data[0].source),
			}

			emits("update:modelValue", merged)
			emits("dialog", false)

			notify({
				title: "Success",
				text: "GIF uploaded successfully.",
				type: "success",
			})
		} catch (error: any) {
			const message = error.response?.data?.message || "Error uploading GIF"
			notify({
				title: "Upload Failed",
				text: message,
				type: "error",
			})
		} finally {
			isLoadingSubmit.value = false
		}

		return 
	}

	selectedFile.value = file
	imagePreview.value = URL.createObjectURL(file)
	isCropping.value = true
}


/**
 * Confirm and upload cropped image
 */
const confirmCrop = async () => {
	try {
		isLoadingSubmit.value = true
		const { canvas } = cropperRef.value.getResult()
		if (!canvas) return

		const originalFile = selectedFile.value
		const isPNG = originalFile?.type === "image/png"

		const blob: Blob = await new Promise((resolve) =>
			canvas.toBlob(resolve, isPNG ? "image/png" : "image/jpeg", isPNG ? 1.0 : 0.9)
		)

		const formData = new FormData()
		formData.append(
			"images[0]",
			blob,
			originalFile?.name || (isPNG ? "cropped.png" : "cropped.jpg")
		)

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
		notify({
			title: "Success",
			text: `Image uploaded successfully as ${isPNG ? "PNG" : "JPEG"}.`,
			type: "success",
		})

		cancelCrop()
	} catch (error: any) {
		console.log('error', error)
		const message = error.response?.data?.message || "Errorzzz while uploading image"
		notify({
			title: "Upload Failed",
			text: message,
			type: "error",
		})
	} finally {
		isLoadingSubmit.value = false
	}
}

/**
 * Cancel cropping and clean up
 */
const cancelCrop = () => {
	if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
	isCropping.value = false
	selectedFile.value = null
	imagePreview.value = null
}

onBeforeUnmount(() => {
	if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
})
</script>

<template>
	<div>
		<!-- CROPPER MODE -->
		<div v-if="isCropping" class="w-full">
			<div class="flex flex-col items-center gap-4">
				<h2 class="font-semibold text-gray-700 text-lg" aria-label="Crop and adjust image before upload">
					Adjust Image Before Upload
				</h2>

				<!-- Aspect ratio selector -->
				<div v-if="aspectRatios.length > 1" class="flex gap-2 justify-center mb-2">
					<Button v-for="ratio in aspectRatios" :key="ratio + selectedRatio" :label="formatRatioLabel(ratio)"
						:type="selectedRatio === ratio ? 'primary' : 'tertiary'" size="xs"
						@click="selectedRatio = ratio" />
				</div>

				<!-- Cropper Component -->
				<Cropper ref="cropperRef" :src="imagePreview"
					:stencil-props="{ ...stencilProps, aspectRatio: selectedRatio }"
					alt="Image being cropped before upload"
					class="rounded-xl border border-gray-300 overflow-hidden w-full max-w-2xl h-[400px]" />

				<!-- Action buttons -->
				<div class="flex justify-center gap-3">
					<Button @click="cancelCrop" type="negative" label="Cancel" aria-label="Cancel cropping" />

					<Button @click="confirmCrop" :disabled="isLoadingSubmit" :loading="isLoadingSubmit"
						:label="isLoadingSubmit ? 'Uploading...' : 'Confirm & Upload'"
						aria-label="Confirm and upload cropped image" />
				</div>
			</div>
		</div>

		<!-- GALLERY MODE -->
		<GalleryManagement v-else :submitUpload="handleUpload" :maxSelected="1"
			:tabs="['upload', 'images_uploaded', 'stock_images']" @submitSelectedImages="onPickImage"
			:isLoadingSubmit="isLoadingSubmit" />
	</div>

	<!-- Recommended size info -->
	<div v-if="stencilProps?.aspectRatio" class="text-gray text-sm mt-2">
		Recommended image size: {{ recommendedPixels }}
	</div>
</template>
