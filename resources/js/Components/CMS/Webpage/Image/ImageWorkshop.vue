<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { faPencil } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
/* import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue" */
import Image from "@/Components/Image.vue"
/* import { ref, toRaw, inject } from "vue" */
/* import Button from "@/Components/Elements/Buttons/Button.vue"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import Modal from "@/Components/Utils/Modal.vue" */
/* import { notify } from "@kyvg/vue3-notification"
import axios from "axios" */
/* import { trans } from "laravel-vue-i18n" */
/* import { set } from "lodash" */
/* import { routeType } from "@/types/route" */
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCube, faStar, faImage, faPencil)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

/* const isInWorkshop = inject("isInWorkshop", false)

const openGallery = ref(false)
const activeImageIndex = ref<number | null>(null) */

/* const submitImage = (imageData: { source: {} }[]) => {
	if (activeImageIndex.value !== null) {
		while (props.modelValue?.value?.images.length <= activeImageIndex.value) {
			props.modelValue?.value?.images.push({})
		}
		set(props.modelValue.value, ["images", activeImageIndex.value], {
			link_data: {},
			source: toRaw(imageData[0] || {})?.source,
		})

		emits("autoSave")
	} else {
		console.error("Invalid index or modelValue structure.")
	}

	openGallery.value = false
	activeImageIndex.value = null
} */

/* const onUpload = async (files: File[], clear: Function) => {
	try {
		const formData = new FormData()
		Array.from(files).forEach((file, index) => {
			formData.append(`images[${index}]`, file)
		})
		const response = await axios.post(
			route(props.webpageData?.images_upload_route.name, {
				modelHasWebBlocks: props.blockData?.id,
			}),
			formData,
			{
				headers: {
					"Content-Type": "multipart/form-data",
				},
			}
		)
		submitImage(response.data.data)
	} catch (error) {
		console.log(error)
		notify({
			title: "Failed",
			text: "Error while uploading data",
			type: "error",
		})
	}
} */

/* const openImageGallery = (index: number) => {
	activeImageIndex.value = index
	openGallery.value = true
} */

const getHref = (index: number) => {
	const image = props.modelValue?.value?.images?.[index]

	if (image?.link_data?.url) {
		return image.link_data.url
	}

	return image?.link_data?.workshop_url
}

const getColumnWidthClass = (layoutType: string, index: number) => {
	switch (layoutType) {
		case "12":
			return index === 0 ? " sm:w-1/2 md:w-1/3" : " sm:w-1/2 md:w-2/3"
		case "21":
			return index === 0 ? " sm:w-1/2 md:w-2/3" : " sm:w-1/2 md:w-1/3"
		case "13":
			return index === 0 ? " md:w-1/4" : " md:w-3/4"
		case "31":
			return index === 0 ? " sm:w-1/2 md:w-3/4" : " sm:w-1/2 md:w-1/4"
		case "211":
			return index === 0 ? " md:w-1/2" : " md:w-1/4"
		case "2":
			return index === 0 ? " md:w-1/2" : " md:w-1/2"
		case "3":
			return index === 0 ? " md:w-1/3" : " md:w-1/3"
		case "4":
			return index === 0 ? " md:w-1/4" : " md:w-1/4"
		default:
			return "w-full"
	}
}

const getImageSlots = (layoutType: string) => {
	switch (layoutType) {
		case "4":
			return 4
		case "3":
		case "211":
			return 3
		case "2":
		case "12":
		case "21":
		case "13":
		case "31":
			return 2
		default:
			return 1
	}
}

</script>

<template>
	<div :style="getStyles(modelValue?.container?.properties)" class="flex flex-wrap overflow-hidden">
		<div v-for="index in getImageSlots(modelValue?.value?.layout_type)"
			:key="`${index}-${modelValue?.value?.images?.[index - 1]?.source?.avif}`"
			class="group relative p-2 hover:bg-white/40 overflow-hidden"
			:class="getColumnWidthClass(modelValue?.value?.layout_type, index - 1)">

			<component v-if="modelValue?.value?.images?.[index - 1]?.source" :is="getHref(index - 1) ? 'a' : 'div'"
				target="_blank" rel="noopener noreferrer" class="block w-full h-full">
			
				<Image :style="{ ...getStyles(modelValue?.value?.layout?.properties), ...getStyles(modelValue?.value?.images?.[index - 1]?.properties)}"
					:src="modelValue?.value?.images?.[index - 1]?.source" :imageCover="true"
					class="w-full h-full aspect-square object-cover rounded-lg"
					:imgAttributes="modelValue?.value?.images?.[index - 1]?.attributes"
					:alt="modelValue?.value?.images?.[index - 1]?.properties?.alt || 'image alt'" />
			</component>

			<div v-else
				class="flex items-center justify-center w-full h-full bg-gray-200 rounded-lg aspect-square transition-all duration-300 hover:bg-gray-300 hover:shadow-lg hover:scale-105 cursor-pointer">
				<font-awesome-icon :icon="['fas', 'image']"
					class="text-gray-500 text-4xl transition-colors duration-300 group-hover:text-gray-700" />
			</div>

		</div>
	</div>

	<!-- 	<Modal
			:isOpen="openGallery"
			@onClose="() => ((openGallery = false), (activeImageIndex = null))"
			width="w-3/4">
			<GalleryManagement
				:maxSelected="1"
				:closePopup="() => (openGallery = false)"
				@submitSelectedImages="submitImage"
				:submitUpload="onUpload"
				:uploadRoute="{
					...webpageData?.images_upload_route,
					parameters: {
						modelHasWebBlocks: blockData?.id,
					},
				}" />
		</Modal> -->
</template>
