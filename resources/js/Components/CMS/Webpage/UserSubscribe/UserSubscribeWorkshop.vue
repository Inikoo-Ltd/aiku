<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/Pricing/Blueprint"
import Image from "@/Components/Image.vue"
import { set } from "lodash"

library.add(faCheck)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

const getBackgroundStyle = (bg: any): Record<string, string> => {
	if (bg && bg.type === "color" && bg.color) {
		return { backgroundColor: bg.color }
	} else if (bg && bg.type === "image" && bg.image?.original) {
		return { backgroundImage: `url(${bg.image.original})` }
	}
	return {}
}
</script>

<template>
	<div
		class="flex flex-wrap justify-between"
		:style="getStyles(modelValue.container.properties, screenType)">
		<div class="mx-auto px-10 md:px-8 py-14">
			<div class="mt-0 xl:mt-0 w-fit mx-auto">
				<div class="text-sm/6 font-semibold text-center sm:text-2xl hover-text-input">
					<Editor
						:modelValue="modelValue.value.headline"
						@update:modelValue="(e) => {
							set(modelValue, ['value', 'headline'], e)
							emits('autoSave')
						}"
						class="hover-text-input"
						:toogle="[
							'heading', 'fontSize', 'bold', 'italic', 'underline', 'query', 'fontFamily',
							'blockquote', 'divider', 'alignLeft', 'alignRight', 'customLink',
							'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
						]"
					/>
				</div>

				<div class="mt-2 text-sm/6 text-center max-w-3xl">
					<Editor
						:modelValue="modelValue.value.description"
						@update:modelValue="(e) => {
							set(modelValue, ['value', 'description'], e)
							emits('autoSave')
						}"
						class="hover-text-input"
						:toogle="[
							'heading', 'fontSize', 'bold', 'italic', 'underline', 'query', 'fontFamily',
							'blockquote', 'divider', 'alignLeft', 'alignRight', 'customLink',
							'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
						]"
					/>
				</div>

				<form class="mt-6 sm:flex sm:max-w-lg sm:items-center sm:w-full mx-auto">
					<label for="email-address" class="sr-only">
						Email address
					</label>

					<input
						type="email"
						name="email-address"
						id="email-address"
						autocomplete="email"
						required
						class="text-gray-700 flex-1 w-full min-w-0 rounded-md bg-white px-3 py-1.5 text-base  outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 sm:w-64 sm:text-sm/6 xl:w-full"
						:placeholder="modelValue?.value?.input?.placeholder"
					/>

					<div class="mt-4 sm:ml-4 sm:mt-0 sm:shrink-0">
						<button type="submit"
							XXclass="flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
							class="rounded-lg w-full"
							:style="getStyles(modelValue?.button?.container?.properties, screenType)"
						>
							<FontAwesomeIcon icon="" class="" fixed-width aria-hidden="true" />
							{{ modelValue?.button?.text }}
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>
