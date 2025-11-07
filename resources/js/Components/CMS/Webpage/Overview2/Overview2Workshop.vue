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
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/Overview2/Blueprint"
import { inject } from "vue"
library.add(faCube, faLink)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()



const imageSettings = {
	key: ["image", "source"],
	stencilProps: {
		aspectRatio: 16 / 9,
		movable: true,
		scalable: true,
		resizable: true,
	},
}

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
</script>


<template>
	<div id="overview-2">
		<div class="flex flex-col md:flex-row w-full rounded-lg overflow-hidden" :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue.container?.properties, screenType)
		}">
			<!-- Section 1: Image (fixed ratio) -->
			<div class="w-full md:w-1/3 lg:w-1/2 relative cursor-pointer overflow-hidden bg-center bg-cover bg-no-repeat"
				:style="{ aspectRatio: '16/9' }"
				@dblclick.stop="() => sendMessageToParent('uploadImage', imageSettings)" @click="() => {
					sendMessageToParent('activeBlock', indexBlock)
					sendMessageToParent('activeChildBlock', bKeys[0])
				}">
				<Image :src="modelValue?.image?.source" :imageCover="true"
					:alt="modelValue?.image?.alt" :imgAttributes="modelValue?.image?.attributes"
					:style="getStyles(modelValue?.image?.properties)" class="w-full h-full object-cover" />
			</div>

			<!-- Section 2: Content -->
			<div @click="() => { sendMessageToParent('activeBlock', indexBlock) }"
				class="flex items-center justify-center w-full md:w-2/3 lg:w-1/2 bg-white/90 backdrop-blur px-6 py-12 sm:px-12 lg:px-20">
				<Editor v-model="modelValue.text" @update:modelValue="() => emits('autoSave')" />
			</div>
		</div>
	</div>
</template>
