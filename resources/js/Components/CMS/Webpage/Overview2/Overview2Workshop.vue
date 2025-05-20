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

library.add(faCube, faLink)

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

const isModalGallery = ref(false)

function onSave() {
	emits("autoSave")
}
</script>

<template>
	<div class="relative" :style="getStyles(modelValue.container.properties,screenType)">
		<!-- Image Section -->
		<div class="relative h-auto overflow-hidden md:absolute md:left-0 md:h-full md:w-1/3 lg:w-1/2">
			<div	@click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))">
				<img v-if="!modelValue?.image?.source" src="https://flowbite.s3.amazonaws.com/docs/gallery/square/image.jpg" :alt="modelValue?.image?.alt" class="h-full w-full object-cover" />
				<Image 
					v-else
					:src="modelValue?.image?.source" 
					:imageCover=true 
					:alt="modelValue?.image?.alt"
					:imgAttributes="modelValue?.image?.attributes"
					:style="getStyles(modelValue?.image?.properties)"
				/>
			</div>
		</div>

		<!-- Details Section -->
		<div class="py-16 sm:py- lg:px-8">
			<div class="pl-6 pr-6 md:ml-auto md:w-2/3 md:pl-16 lg:w-1/2 lg:pl-24 lg:pr-0 xl:pl-32">
				<Editor v-model="modelValue.text" @update:modelValue="() => emits('autoSave')"/>	
			</div>
		</div>
	</div>
</template>
