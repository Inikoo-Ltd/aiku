<script setup lang="ts">
import { ref } from "vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop";
import Blueprint from "@/Components/CMS/Webpage/CTA3/Blueprint"

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
	<div class="relative grid rounded-lg" :style="getStyles(modelValue.container.properties,screenType)">
		<!-- Background Image Layer -->
		<div class="absolute inset-0 bg-cover bg-center bg-no-repeat z-0" :style="{
			backgroundImage: modelValue?.image?.source
				? `url('${modelValue.image.source.original}')`
				: `url('https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png')`,
			...getStyles(modelValue.image.properties, screenType)
		}" @click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))"></div>


		<div :style="getStyles(modelValue.container.properties?.block,screenType)" class="relative z-10 w-full bg-white bg-opacity-75 p-6 backdrop-blur backdrop-filter
         sm:flex sm:flex-col sm:items-start
         lg:w-96">

			<div class="text-center lg:text-left text-gray-600 pr-3 mb-4 w-full">
				<Editor v-if="modelValue?.text" v-model="modelValue.text" @update:modelValue="() => emits('autoSave')"
					:uploadImageRoute="{
						name: webpageData.images_upload_route.name,
						parameters: {
							...webpageData.images_upload_route.parameters,
							modelHasWebBlocks: blockData?.id,
						},
					}" />
			</div>

			<div typeof="button"
				@click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[1]?.key?.join('-'))"
				:style="getStyles(modelValue.button.container.properties, screenType)"
				class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6 cursor-pointer">
				{{ modelValue.button.text }}
			</div>
		</div>
	</div>

</template>
