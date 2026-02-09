<script setup lang="ts">
import { inject, computed } from "vue"
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Blueprint from "@/Components/CMS/Webpage/Cta1/Blueprint"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Image from "@/Components/Image.vue"
import { get, isPlainObject } from 'lodash-es'

library.add(faCube, faLink, faImage)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: object
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
	(e: "uploadImage", value: any): void
}>()

const imageSettings = {
	key: ["image", "source"],
	stencilProps: {
		aspectRatio: [16 / 9, null],
		movable: true,
		scalable: true,
		resizable: true,
	},
}

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []



const valueForField = computed(() => {
	const rawVal = get(props.modelValue, ['column_position'])
	if (!isPlainObject(rawVal)) return rawVal

	const view = props.screenType!
	return rawVal?.[view] ?? rawVal?.desktop ?? 'Image-right'
})

const isImageRight = computed(() => valueForField.value === 'Image-right')

</script>

<template>
	<div id="cta4" class="w-full">
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue.container?.properties, screenType),
		}">
			<div class="grid grid-cols-1 md:grid-cols-2 w-full min-h-[250px] md:min-h-[400px]">

				<!-- IMAGE -->
				<div class="relative cursor-pointer overflow-hidden w-full" :class="[
					!modelValue.image.source ? '' : 'h-[250px] sm:h-[300px] md:h-[400px]',
					isImageRight ? 'order-2' : 'order-1'
				]" :style="getStyles(modelValue.image.properties, screenType)" @click.stop="() => {
					sendMessageToParent('activeBlock', indexBlock)
					sendMessageToParent('activeChildBlock', bKeys[0])
				}" @dblclick.stop="() => sendMessageToParent('uploadImage', imageSettings)">
					<Image :src="modelValue.image.source" :imageCover="true"
						:alt="modelValue.image.alt || 'Image preview'"
						class="w-full h-full object-cover md:absolute md:inset-0"
						:imgAttributes="modelValue.image.attributes" />
				</div>

				<!-- TEXT -->
				<div class="flex flex-col justify-center m-auto p-4" :class="isImageRight ? 'order-1' : 'order-2'"
					:style="getStyles(modelValue?.text_block?.properties, screenType)">
					<div class="max-w-xl w-full" @click="() => {
						sendMessageToParent('activeBlock', indexBlock)
						sendMessageToParent('activeChildBlock', bKeys[1])
					}">
						<Editor v-if="modelValue?.text" v-model="modelValue.text"
							@focus="() => sendMessageToParent('activeChildBlock', bKeys[1])"
							@update:modelValue="() => emits('autoSave')" class="mb-4" :uploadImageRoute="{
								name: webpageData.images_upload_route.name,
								parameters: {
									...webpageData.images_upload_route.parameters,
									modelHasWebBlocks: blockData?.id,
								},
							}" />

						<div class="flex justify-center">
							<Button :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
								:label="modelValue?.button?.text" @click.stop="() => {
									sendMessageToParent('activeBlock', indexBlock)
									sendMessageToParent('activeChildBlock', bKeys[2])
								}" />
						</div>
					</div>
				</div>

			</div>

		</div>
	</div>
</template>
