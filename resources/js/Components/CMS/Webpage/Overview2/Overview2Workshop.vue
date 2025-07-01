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
	indexBlock: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()

const isModalGallery = ref(false)

function onSave() {
	emits("autoSave")
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
			<!-- Section 1: Image -->
			<div class="w-full h-64 sm:h-72 md:h-auto md:w-1/3 lg:w-1/2 bg-cover bg-center bg-no-repeat" @click="() => {
				sendMessageToParent('activeBlock', indexBlock)
				sendMessageToParent('activeChildBlock', bKeys[0])
			}
			">
				<img v-if="!modelValue?.image?.source"
					src="https://flowbite.s3.amazonaws.com/docs/gallery/square/image.jpg" :alt="modelValue?.image?.alt"
					class="h-full w-full object-cover" />
				<Image v-else :src="modelValue?.image?.source" :imageCover :alt="modelValue?.image?.alt"
					:imgAttributes="modelValue?.image?.attributes" :style="getStyles(modelValue?.image?.properties)" />
			</div>

			<!-- Section 2: Content -->
			<div @click="() => {
				sendMessageToParent('activeBlock', indexBlock)
			}" class="flex items-center justify-center w-full md:w-2/3 lg:w-1/2 bg-white bg-opacity-90 backdrop-blur px-6 py-12 sm:px-12 lg:px-20">
				<Editor v-model="modelValue.text" @update:modelValue="() => emits('autoSave')"  />
			</div>
		</div>
	</div>
</template>
