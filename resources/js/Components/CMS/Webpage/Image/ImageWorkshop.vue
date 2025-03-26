<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { faPencil } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
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
</template>
