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
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()
</script>

<template>
	<div class="relative" :style="getStyles(modelValue.container.properties)">
		<div
			@click="
				() =>
					sendMessageToParent(
						'activeChildBlock',
						Blueprint?.blueprint?.[0]?.key?.join('-')
					)
			"
			class="relative h-80 overflow-hidden md:absolute md:left-0 md:h-full md:w-1/3 lg:w-1/2">
			<template v-if="modelValue?.image?.source">
				<Image
					:src="modelValue?.image?.source"
					:imageCover="true"
					:alt="modelValue?.image?.alt"
					:imgAttributes="modelValue?.image?.attributes"
					:style="getStyles(modelValue?.image?.properties)" />
			</template>
			<template v-else>
				<img
					src="https://flowbite.s3.amazonaws.com/blocks/marketing-ui/content/content-gallery-3.png"
					:alt="modelValue?.image?.alt"
					class="h-full w-full object-cover" />
			</template>
		</div>

		<div class="py-16 sm:py-32 lg:px-8 lg:py-40">
			<div class="pl-6 pr-6 md:ml-auto md:w-2/3 md:pl-16 lg:w-1/2 lg:pl-24 lg:pr-0 xl:pl-32">
				<Editor
					v-if="modelValue?.text"
					v-model="modelValue.text"
					@update:modelValue="() => emits('autoSave')"
					class="mb-4"
					:uploadImageRoute="{
                        name: webpageData.images_upload_route.name,
                        parameters: {
                            ...webpageData.images_upload_route.parameters,
                            modelHasWebBlocks: blockData?.id,
                        }
                    }"
				/>
          <div class="flex justify-center">
				<div
					typeof="button"
					@click="
						() =>
							sendMessageToParent(
								'activeChildBlock',
								Blueprint?.blueprint?.[1]?.key?.join('-')
							)
					"
					:style="getStyles(modelValue?.button?.container?.properties)"
					class="mt-10 flex items-center justify-center w-64 mx-auto gap-x-6">
					{{ modelValue?.button?.text }}
				</div>
      </div>
			</div>
		</div>
	</div>
</template>
