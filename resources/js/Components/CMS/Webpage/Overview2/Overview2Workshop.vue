<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faEdit } from "@far"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/Overview2/Blueprint"

library.add(faCube, faLink)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()

const isModalGallery = ref(false)

const onChangeImage = (image) => {
	const data = { ...props.modelValue }
	data.image.source = { ...image[0].source }
	isModalGallery.value = false
	props.modelValue = data
	onSave()
}

function onSave() {
	emits("autoSave")
}
</script>

<template>
	<div class="container flex flex-wrap justify-between" :style="getStyles(modelValue.container.properties)">
		<!-- Image Section -->
		<div class="imgBx relative w-1/2 transition-all duration-300">
			<div class="absolute inset-0" 	@click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))">
				<img v-if="!modelValue?.image?.source" src="https://flowbite.s3.amazonaws.com/docs/gallery/square/image.jpg" :alt="modelValue?.image?.alt" class="h-full w-full object-cover" />
				<Image 
					v-else
					:src="modelValue?.image?.source" 
					:imageCover=true 
					:alt="modelValue?.image?.alt"
					:imgAttributes="modelValue?.image?.attributes"
					:style="getStyles(modelValue?.image?.properties)"
				/>
			</div>
		</div>

		<!-- Details Section -->
		<div class="details flex flex-col justify-center w-1/2 p-10">
			<Editor v-model="modelValue.text" />
		</div>
	</div>
</template>
