<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import Modal from "@/Components/Utils/Modal.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faEdit } from "@far"
import GalleryManagement from "@/Components/Utils/GalleryManagement/GalleryManagement.vue"
import { FieldValue } from "@/types/webpageTypes";
library.add(faCube, faLink)

const props = defineProps<{
	fieldValue: FieldValue
	webpageData?: any
	web_block: Object
	id: Number
	type: String
	isEditable?: boolean
	properties: {}
}>()

const emits = defineEmits<{
	// (e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()


</script>

<template>
	<div :style="getStyles(fieldValue?.container?.properties)">
		<div class="mx-auto max-w-2xl px-6 lg:max-w-7xl lg:px-8">
			<div v-html="fieldValue.title" />
			<div class="mt-10 grid gap-4 sm:mt-16 lg:grid-cols-3 lg:grid-rows-2">
				<div class="relative lg:row-span-2">
					<div class="absolute inset-px rounded-lg bg-white lg:rounded-l-[2rem]" />
					<div
						class="relative flex h-full flex-col overflow-hidden rounded-[calc(theme(borderRadius.lg)+1px)] lg:rounded-l-[calc(2rem+1px)]">
						<div class="px-8 pb-3 pt-8 sm:px-10 sm:pb-0 sm:pt-10">
							<div v-html="fieldValue.column1.text" />
						</div>
						<div
							class="relative min-h-[30rem] w-full grow [container-type:inline-size] max-lg:mx-auto max-lg:max-w-sm">
							<!-- Images Structure (renders only if images are present) -->
							<div class="absolute">
								<Image
									:src="fieldValue?.column1?.source"
									:alt="fieldValue?.column1?.alt || 'Image aiku'"
									class="w-full h-full object-cover rounded-lg" />
							</div>
						</div>
					</div>
					<div
						class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 lg:rounded-l-[2rem]" />
				</div>
				<!-- Column 2 -->
				<div class="relative max-lg:row-start-1">
					<div
						class="absolute inset-px rounded-lg bg-white max-lg:rounded-t-[2rem]"></div>

					<!-- Conditional Content Wrapper for Column 2 -->
					<div
						class="relative flex h-full flex-col overflow-hidden rounded-[calc(theme(borderRadius.lg)+1px)] max-lg:rounded-t-[calc(2rem+1px)]">
						<!-- Text Editor Section -->
						<div class="px-8 pt-8 sm:px-10 sm:pt-10">
							<div v-html="fieldValue.column2.text" />
						</div>

						<!-- Conditional Image or Default Structure for Column 2 -->
						<div
							class="flex flex-1 items-center justify-center px-8 max-lg:pb-12 max-lg:pt-10 sm:px-10 lg:pb-2">
							<div class="relative w-full max-lg:max-w-xs">
								<Image
									:src="fieldValue?.column2?.source"
									:alt="fieldValue?.column2?.alt || 'Image aiku'"
									class="w-full object-cover rounded-lg shadow-lg" />
							</div>
						</div>
					</div>

					<div
						class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 max-lg:rounded-t-[2rem]"></div>
				</div>

				<!-- Column 3 -->
				<div class="relative max-lg:row-start-3 lg:col-start-2 lg:row-start-2">
					<div class="absolute inset-px rounded-lg bg-white"></div>

					<div
						class="relative flex h-full flex-col overflow-hidden rounded-[calc(theme(borderRadius.lg)+1px)]">
						<div class="px-8 pt-8 sm:px-10 sm:pt-10">
							<div v-html="fieldValue.column3.text" />
						</div>

						<div
							class="flex flex-1 items-center justify-center px-8 max-lg:py-6 lg:pb-2">
							<div class="relative w-full max-lg:max-w-xs">
								<Image
									:src="fieldValue?.column3?.source"
									:alt="fieldValue?.column3?.alt || 'Image aiku'"
									class="h-[min(152px,40cqw)] object-cover object-center rounded-lg shadow-lg" />
							</div>
						</div>
					</div>

					<div
						class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5"></div>
				</div>

				<!-- Column 4 -->
				<div class="relative lg:row-span-2">
					<div class="absolute inset-px rounded-lg bg-white lg:rounded-r-[2rem]"></div>

					<!-- Conditional Content Wrapper for Column 4 -->
					<div
						class="relative flex h-full flex-col overflow-hidden rounded-[calc(theme(borderRadius.lg)+1px)] lg:rounded-l-[calc(2rem+1px)]">
						<div class="px-8 pb-3 pt-8 sm:px-10 sm:pb-0 sm:pt-10">
							<div v-html="fieldValue.column4.text" />
						</div>

						<div
							class="relative min-h-[30rem] w-full grow [container-type:inline-size] max-lg:mx-auto max-lg:max-w-sm">
							<div class="absolute">
								<Image
									:src="fieldValue?.column4?.source"
									:alt="fieldValue?.column4?.alt || 'Image aiku'"
									class="size-full object-cover object-top" />
							</div>
						</div>
					</div>

					<div
						class="pointer-events-none absolute inset-px rounded-lg shadow ring-1 ring-black/5 lg:rounded-r-[2rem]"></div>
				</div>
			</div>
		</div>
	</div>
</template>
