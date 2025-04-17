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
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()

const openGallery = ref(false)

</script>

<template>
	<div class="relative overflow-hidden rounded-lg lg:h-96" :style="getStyles(properties)">
		<div class="absolute inset-0" @click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[0]?.key?.join('-'))" >
			<template v-if="modelValue?.image?.source">
				<Image :src="modelValue.image.source" :imageCover="true" :alt="modelValue.image.alt"
					:imgAttributes="modelValue.image.attributes" :style="getStyles(modelValue.image.properties)" />
			</template>
			<template v-else>
				<img src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
					:alt="modelValue?.image?.alt" class="h-full w-full object-cover" />
			</template>
		</div>

		<div aria-hidden="true" class="relative h-96 w-full lg:hidden" />
		<div aria-hidden="true" class="relative h-32 w-full lg:hidden" />

		<div
			class="absolute inset-x-0 bottom-0 rounded-bl-lg rounded-br-lg bg-white bg-opacity-75 p-6 backdrop-blur backdrop-filter sm:flex sm:items-center sm:justify-between lg:inset-x-auto lg:inset-y-0 lg:w-96 lg:flex-col lg:items-start lg:rounded-br-none lg:rounded-tl-lg">
			<div class="text-center lg:text-left text-gray-600 pr-3 overflow-y-auto mb-4">
				<Editor
					v-if="modelValue?.text"
					v-model="modelValue.text"
					@update:modelValue="() => emits('autoSave')"
					:uploadImageRoute="{
                        name: webpageData.images_upload_route.name,
                        parameters: {
                            ...webpageData.images_upload_route.parameters,
                            modelHasWebBlocks: blockData?.id,
                        }
                    }"
				/>
			</div>

			<div typeof="button" 
			@click="() => sendMessageToParent('activeChildBlock', Blueprint?.blueprint?.[1]?.key?.join('-'))"
			 :style="getStyles(modelValue.button.container.properties)"
				class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6 cursor-pointer">
				{{ modelValue.button.text }}
			</div>
		</div>
	</div>
</template>
