<script setup lang="ts">
import { inject } from "vue"
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import Blueprint from "@/Components/CMS/Webpage/Cta1/Blueprint"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: object
	indexBlock : number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
</script>

<template>
	<div id="cta1">
		<div class="grid grid-cols-1 md:grid-cols-2" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue.container?.properties, screenType)
		}">
			<!-- 🖼️ Image Block -->
			<div @click="() => {
				sendMessageToParent('activeBlock', indexBlock)
				sendMessageToParent('activeChildBlock', bKeys[0])
				sendMessageToParent('activeChildBlock', bKeys[0])
			}
			">
				<div class="w-full flex" :style="getStyles(modelValue?.image?.container?.properties, screenType)">
					<Image v-if="modelValue?.image?.source" :src="modelValue.image.source" :imageCover="true"
						:alt="modelValue.image.alt || 'Image preview'" :imgAttributes="modelValue.image.attributes"
						:style="getStyles(modelValue.image.properties, screenType)" />
					<img v-else
						src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
						:alt="modelValue?.image?.alt || 'Default placeholder image'" />
				</div>
			</div>

			<!-- 📝 Text & Button Block -->
			<div class="flex flex-col justify-center"
				:style="getStyles(modelValue?.text_block?.properties, screenType)">
				<div class="max-w-xl mx-auto w-full" @click="() => {
					sendMessageToParent('activeBlock', indexBlock)
					sendMessageToParent('activeChildBlock', bKeys[1])
				}">
					<Editor v-if="modelValue?.text" v-model="modelValue.text" @focus="() => {
						sendMessageToParent('activeChildBlock', bKeys[1])
					}" @update:modelValue="() => emits('autoSave')" class="mb-6" :uploadImageRoute="{
				name: webpageData.images_upload_route.name,
				parameters: {
					...webpageData.images_upload_route.parameters,
					modelHasWebBlocks: blockData?.id,
				}
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
</template>
