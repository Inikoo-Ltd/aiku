<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "./Blueprint"

library.add(faCube, faLink)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	indexBlock: Number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
	(e: "autoSave"): void
}>()
const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
</script>

<template>
	<div id="cta2">
		<div :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue.container?.properties, screenType)
		}" @click="() => {
                sendMessageToParent('activeBlock', indexBlock)
                sendMessageToParent('activeChildBlock', bKeys[1])
            }">
			<div class="relative  px-6 py-24 text-center  l sm:px-16">
				<Editor v-model="modelValue.headline" @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
					name: webpageData.images_upload_route.name,
					parameters: {
						...webpageData.images_upload_route.parameters,
						modelHasWebBlocks: blockData?.id,
					}
				}" />

				<div class="mt-10 flex items-center justify-center gap-x-6">
					<Button :injectStyle="getStyles(modelValue?.button?.container?.properties, screenType)"
						:label="modelValue?.button?.text" @click.stop="() => {
							sendMessageToParent('activeBlock', indexBlock)
							sendMessageToParent('activeChildBlock', bKeys[0])
						}" />
				</div>
			</div>
		</div>
	</div>
</template>
