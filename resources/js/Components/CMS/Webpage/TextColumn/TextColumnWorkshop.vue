<script setup lang="ts">
import { faCube, faStar, faImage } from "@fas";
import { faPencil } from "@far";
import { library } from "@fortawesome/fontawesome-svg-core";
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue";
import { getStyles } from "@/Composables/styles";

// Add FontAwesome icons to the library
library.add(faCube, faStar, faImage, faPencil);

const props = defineProps<{
    modelValue: any;
    webpageData?: any;
    blockData?: Record<string, any>;
	screenType: "mobile" | "tablet" | "desktop"
}>();

const emits = defineEmits<{
    (e: "update:modelValue", value: any): void;
    (e: "autoSave"): void;
}>();

/**
 * Get the CSS class for column width based on layout type and index
 */
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

/**
 * Get the number of image slots based on layout type
 */
const getImageSlots = (layoutType: string): number => {
    const slotCounts: Record<string, number> = {
        "4": 4,
        "3": 3,
        "211": 3,
        "2": 2,
        "12": 2,
        "21": 2,
        "13": 2,
        "31": 2,
    };
    return slotCounts[layoutType] || 1;
};
</script>

<template>
    <div :style="getStyles(modelValue.container.properties,screenType)" class="flex flex-wrap">
        <div v-for="index in getImageSlots(modelValue?.value?.layout_type)"
            :key="`${index}-${modelValue?.value?.images?.[index - 1]}`"
            class=" relative p-2 hover:bg-white/40"
            :class="getColumnWidthClass(modelValue?.value?.layout_type, index - 1)">
            <EditorV2 
                placeholder="Text......" 
                v-model="modelValue.value.text[index - 1]" 
                @update:modelValue="() => emits('autoSave')"  
                :uploadImageRoute="{ 
                    name: webpageData.images_upload_route.name, 
                    parameters: { modelHasWebBlocks: blockData.id } 
                }" />
        </div>
    </div>
</template>
