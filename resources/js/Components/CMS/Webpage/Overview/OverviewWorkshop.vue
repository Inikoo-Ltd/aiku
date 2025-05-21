<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { ref, onMounted, onBeforeUnmount } from "vue"
import Moveable from "vue3-moveable"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from "@kyvg/vue3-notification"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faEdit } from "@far"

const props = defineProps<{
    modelValue: any
	webpageData?: any
	blockData?: Object
}>()


const emits = defineEmits<{
	(e: "autoSave"): void
}>()


const activeTextIndex = ref(-1)
const activeImageIndex = ref(-1)
const activeImageIndexModal = ref(-1)
const isModalGallery = ref(false)
const _textRefs = ref([])
const _imageRefs = ref([])

function onDragImage({ top = 0, bottom = 0, left = 0, right = 0 }) {
	props.modelValue.images[activeImageIndex.value].properties.position.top = `${top}px`
	props.modelValue.images[activeImageIndex.value].properties.position.bottom = `${bottom}px`
	props.modelValue.images[activeImageIndex.value].properties.position.left = `${left}px`
	props.modelValue.images[activeImageIndex.value].properties.position.right = `${right}px`
	onSave()
}

function onDragText({ top = 0, bottom = 0, left = 0, right = 0 }) {
	props.modelValue.texts.values[activeTextIndex.value].properties.position.top = `${top}px`
	props.modelValue.texts.values[activeTextIndex.value].properties.position.bottom = `${bottom}px`
	props.modelValue.texts.values[activeTextIndex.value].properties.position.left = `${left}px`
	props.modelValue.texts.values[activeTextIndex.value].properties.position.right = `${right}px`
	onSave()
}

function onImageScale({ offsetHeight = 100, offsetWidth = 100, transform }) {
	let transformString = transform
	let transformVal = transformString
		.match(/scale\(([^)]+)\)/)[1]
		.split(", ")
		.map(Number)
	props.modelValue.images[activeImageIndex.value].properties.width = `${
		offsetWidth * transformVal[0]
	}px`
	props.modelValue.images[activeImageIndex.value].properties.height = `${
		offsetHeight * transformVal[1]
	}px`
	onSave()
}

function onTextScale({ offsetHeight = 100, offsetWidth = 100, transform }) {
	let transformString = transform
	let transformVal = transformString
		.match(/scale\(([^)]+)\)/)[1]
		.split(", ")
		.map(Number)
	props.modelValue.texts.values[activeTextIndex.value].properties.width = `${
		offsetWidth * transformVal[0]
	}px`
	props.modelValue.texts.values[activeTextIndex.value].properties.height = `${
		offsetHeight * transformVal[1]
	}px`
	onSave()
}

// Save position and size for text
function onSave() {
	emits("autoSave")
}

function activateMoveableText(index: number) {
	activeTextIndex.value = index
	activeImageIndex.value = -1
}

function activateMoveableImage(index: number) {
	activeImageIndex.value = index
	activeTextIndex.value = -1
}

function handleClickOutside() {
	activeTextIndex.value = -1
	activeImageIndex.value = -1
}

const onChangeImage = (image) => {
	const data = {...props.modelValue}
	data.images[activeImageIndexModal.value].sources = {...image[0].source}
	isModalGallery.value = false
	activeImageIndexModal.value = -1
	props.modelValue = data
	onSave()
}


const onUpload = async (files: File[], clear: Function) => {
	try {
		const formData = new FormData()
		Array.from(files).forEach((file, index) => {
			formData.append(`images[${index}]`, file)
		})
		const response = await axios.post(
			route(props.webpageData?.images_upload_route.name, { modelHasWebBlocks: props.blockData?.id }),
			formData,
			{
				headers: {
					"Content-Type": "multipart/form-data",
				},
			}
		)
		onChangeImage(response.data.data)
	} catch (error) {
		console.log(error)
		notify({
			title: "Failed",
			text: "Error while uploading data",
			type: "error",
		})
	}
}

onMounted(() => {
	document.addEventListener("click", handleClickOutside)
})

onBeforeUnmount(() => {
	document.removeEventListener("click", handleClickOutside)
})
</script>

<template>
	<div
		ref="_parentComponent"
		class="relative  transition-all hidden md:block"
		:style="getStyles(modelValue.container.properties)">
		<!-- Render text elements -->
		<div v-for="(text, index) in modelValue.texts.values" :key="index">
			<div
				class="absolute"
				:class="`text-${index}`"
				@dblclick="activateMoveableText(index)"
				:ref="el => _textRefs[index] = el"
				:style="{
					width: text?.properties?.width ? `${text?.properties?.width}` : 'auto',
					height: text?.properties?.height ? `${text?.properties?.height}` : 'auto',
					top: text?.properties?.position?.top
						? `${text?.properties?.position?.top}`
						: 'auto',
					left: text?.properties?.position?.left
						? `${text?.properties?.position?.left}`
						: 'auto',
				}">
				<Editor
					v-model="text.text"
					:editable="activeTextIndex === index ? false : true"
					@update:modelValue="() => emits('autoSave')" />
			</div>
			<Moveable
				v-if="activeTextIndex === index"
				class="moveable"
				:target="_textRefs[index]"
				:draggable="true"
				:scalable="true"
				@drag="onDragText"
				@scale="onTextScale"
				:snapDirections="{ top: true, left: true, bottom: true, right: true }"
				:elementSnapDirections="{
					top: true,
					left: true,
					bottom: true,
					right: true,
					center: true,
					middle: true,
				}" />
		</div>

		<!-- Render image elements -->
		<div>
			<div v-for="(image, index) in modelValue.images" :key="index">
				<div
					class="absolute"
					:class="`image-${index}`"
					@dblclick="activateMoveableImage(index)"
					:ref="el => _imageRefs[index] = el"
					:style="{
						width: image?.properties?.width ? `${image?.properties?.width}` : 'auto',
						height: image?.properties?.height ? `${image?.properties?.height}` : 'auto',
						top: image?.properties?.position?.top
							? `${image?.properties?.position?.top}`
							: 'auto',
						left: image?.properties?.position?.left
							? `${image?.properties?.position?.left}`
							: 'auto',
					}">
					<button
						@click="
							() => {
								isModalGallery = true
								activeImageIndexModal = index
							}
						"
						style="position: absolute; top: 0; left: 10px; z-index: 10">
						<FontAwesomeIcon :icon="faImage" class="text-lg h-4 text-indigo-500" />
					</button>

					<Image :src="image.sources" />
				</div>

				<Moveable
					v-if="activeImageIndex === index"
					class="moveable"
					:target="_imageRefs[index]"
					:draggable="true"
					:scalable="true"
					@drag="onDragImage"
					@scale="onImageScale"
					:snapDirections="{ top: true, left: true, bottom: true, right: true }"
					:elementSnapDirections="{
						top: true,
						left: true,
						bottom: true,
						right: true,
						center: true,
						middle: true,
					}" />
			</div>
		</div>
	</div>
	<div class="block md:hidden p-6">
		<div v-for="(image, index) in modelValue.images" :key="index">
			<Image :src="image.sources" />
		</div>
		<div v-for="(text, index) in modelValue.texts.values" :key="index">
			<div v-html="text.text" />
		</div>
	</div>
	<Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
		<GalleryManagement
			:maxSelected="1"
			:uploadRoute="{
				...webpageData.images_upload_route,
				parameters: {
						modelHasWebBlocks: blockData?.id
					},
			}"
			:closePopup="() => (isModalGallery = false)"
			@submitSelectedImages="onChangeImage" 
			:submitUpload="onUpload"
			/>
	</Modal>
</template>
