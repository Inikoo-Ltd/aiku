<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { faPencil } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faStar, faImage, faPencil)

const props = defineProps<{
	fieldValue: {
		value: {
			images: {
				source: string
				link_data: {
					url: string
				}
				attributes?: {
					fetchpriority?: string
				}
			}[]
			layout_type: string
		}
		container: {}
	}
	webpageData?: any
	web_block?: Object
	id?: number
	type?: string
	isEditable?: boolean
}>()

const getComponentDivOrA = (index: number) => {
	const image = props.fieldValue?.value?.images?.[index]

	if (image?.link_data?.url) {
		return image.link_data.url
	}

	return null
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

const getImageData = (index: number) => {
	return props.fieldValue?.value?.images?.[index]
}

// Method: get href depends on 'internal' or 'external' link
const getHref = (index: number) => {
	const image = getImageData(index)

	if (image.type === 'internal') {
		return image?.link_data?.data?.href || null
	} else {
		return image?.link_data?.href || null
	}
}
</script>

<template>
	<div :style="getStyles(fieldValue?.container?.properties)" class="flex flex-wrap overflow-hidden">
		<div v-for="index in getImageSlots(fieldValue?.value?.layout_type)"
			:key="`${index}-${fieldValue?.value?.images?.[index - 1]?.source?.avif}`"
			class="group relative p-2 hover:bg-white/40 overflow-hidden"
			:class="getColumnWidthClass(fieldValue?.value?.layout_type, index - 1)">
			<component
				v-if="fieldValue?.value?.images?.[index - 1]?.source"
				:is="getComponentDivOrA(index - 1) ? 'a' : 'div'"
				:href="getHref(index - 1)"
				:target="getImageData(index-1)?.link_data?.target || '_blank'"
				rel="noopener noreferrer"
				class="block w-full h-full"
			>
				<Image :style="{ ...getStyles(fieldValue?.value.layout?.properties), ...getStyles(fieldValue?.value?.images?.[index - 1]?.properties) ,}"
				:src="fieldValue?.value?.images?.[index - 1]?.source" :imageCover="true"
				class="w-full h-full aspect-square object-cover rounded-lg"
				:imgAttributes="fieldValue?.value?.images?.[index - 1]?.attributes"
				:alt="fieldValue?.value?.images?.[index - 1]?.properties?.alt || 'image alt'" />
			</component>
		</div>
	</div>

</template>

