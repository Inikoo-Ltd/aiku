<script setup lang="ts">
import { inject } from "vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import VideoCarousel1 from "@/Components/CMS/Webpage/VideoCarousel1/VideoCarousel1.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import type { VideoCarouselFieldValue } from "@/Components/CMS/Webpage/VideoCarousel1/types"

const props = defineProps<{
	modelValue: VideoCarouselFieldValue
	indexBlock?: number
	webpageData?: any
	blockData?: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "autoSave"): void
}>()

const layout: any = inject("layout", {})

const selectBlock = (childBlock?: string, arrayIndex?: number) => {
	sendMessageToParent("activeBlock", props.indexBlock)
	if (childBlock) {
		sendMessageToParent("activeChildBlock", childBlock)
	}
	if (arrayIndex !== undefined && arrayIndex >= 0) {
		sendMessageToParent("activeChildBlockArray", arrayIndex)
	}
}
</script>

<template>
	<div :id="modelValue?.id ? modelValue.id : 'video-carousel-1' + indexBlock"
		:style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue?.container?.properties, screenType),
		}">
		<VideoCarousel1 :fieldValue="modelValue" :screenType="screenType" isEditing @tileClick="(index: number) => selectBlock('videos', index)">
			<template #heading>
				<div class="mb-6" @click="() => selectBlock()">
					<Editor v-model="modelValue.text" @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
						name: webpageData?.images_upload_route?.name,
						parameters: {
							...webpageData?.images_upload_route?.parameters,
							modelHasWebBlocks: blockData?.id,
						},
					}" />
				</div>
			</template>
			<template #button>
				<div v-if="modelValue?.button?.show !== false && modelValue?.button?.text" class="mt-6 flex justify-center">
					<Button :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
						:label="modelValue.button.text" @click.stop="() => selectBlock('button')" />
				</div>
			</template>
		</VideoCarousel1>
	</div>
</template>
