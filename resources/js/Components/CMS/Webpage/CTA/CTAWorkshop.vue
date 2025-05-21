<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/CTA/Blueprint"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()
</script>

<template>
	<div class="grid grid-cols-1 md:grid-cols-2 relative"
		:style="getStyles(modelValue.container?.properties, screenType)">
		<!-- 📷 Image Column -->
		<div @click="
			() =>
				sendMessageToParent(
					'activeChildBlock',
					Blueprint?.blueprint?.[0]?.key?.join('-')
				)
		" class="relative overflow-hidden">
			<div class="w-full flex" :style="getStyles(modelValue?.image?.container?.properties, screenType)" >
				<template v-if="modelValue?.image?.source">
					<Image :src="modelValue.image.source" :imageCover="true"
						:alt="modelValue.image.alt || 'Image preview'" :imgAttributes="modelValue.image.attributes"
						:style="getStyles(modelValue.image.properties, screenType)" :class="null" />
				</template>
				<template v-else>
					<img src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
						:alt="modelValue?.image?.alt || 'Default placeholder image'"
						 />
				</template>
			</div>

		</div>

		<!-- 📝 Text & Button Column -->
		<div class="flex flex-col justify-center" :style="getStyles(modelValue?.text_block?.properties, screenType)">

			<div class="max-w-xl mx-auto w-full">
				<!-- Rich Text Editor -->
				<Editor v-if="modelValue?.text" v-model="modelValue.text" @update:modelValue="() => emits('autoSave')"
					class="mb-6" :uploadImageRoute="{
						name: webpageData.images_upload_route.name,
						parameters: {
							...webpageData.images_upload_route.parameters,
							modelHasWebBlocks: blockData?.id,
						}
					}" />

				<!-- CTA Button -->
				<div class="flex justify-center">
					<div typeof="button" @click="
						() =>
							sendMessageToParent(
								'activeChildBlock',
								Blueprint?.blueprint?.[1]?.key?.join('-')
							)
					" :style="getStyles(modelValue?.button?.container?.properties, screenType)"
						class="mt-10 flex items-center justify-center w-64 gap-x-6">
						{{ modelValue?.button?.text }}
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
