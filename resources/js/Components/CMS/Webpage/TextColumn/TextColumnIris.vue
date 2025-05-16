<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas"
import { faPencil } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles";

library.add(faCube, faStar, faImage, faPencil)

const props = defineProps<{
    fieldValue: {
		value: {
			images: {
				source: string
				link_data: {
					url: string
				}
			}[]
			layout_type: string
		}
	}
	webpageData?: any
	web_block?: Object
	id?: Number
	type?: String
	isEditable?: Boolean
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: "update:fieldValue", value: any): void
    (e: "autoSave"): void
}>()


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
    <div class="flex flex-wrap" :style="getStyles(fieldValue?.container?.properties,screenType)">
        <div v-for="index in getImageSlots(fieldValue?.value?.layout_type)"
            :key="`${index}-${fieldValue?.value?.images?.[index - 1]}`"
            class="group relative p-2 hover:bg-white/40"
            :class="getColumnWidthClass(fieldValue?.value?.layout_type, index - 1)">
            <div
                v-html="fieldValue.value.text[index-1]"
            />
        </div>
    </div>


</template>
