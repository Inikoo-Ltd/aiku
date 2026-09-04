<script setup lang="ts">
import { set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"

defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

const editorToggle = [
	"heading1", "heading2", "heading3", "fontSize", "bold", "italic", "underline", "query", "fontFamily",
	"blockquote", "divider", "alignLeft", "alignRight", "customLink", "bulletList", "orderedList",
	"alignCenter", "undo", "redo", "highlight", "color", "clear",
]

const previewFields = [
	{ label: "Email", span: "sm:col-span-6" },
	{ label: "Password", span: "sm:col-span-3" },
	{ label: "Retype Password", span: "sm:col-span-3" },
	{ label: "Name", span: "sm:col-span-3" },
	{ label: "Phone Number", span: "sm:col-span-3" },
	{ label: "Business Name", span: "sm:col-span-6" },
	{ label: "Website", span: "sm:col-span-6" },
	{ label: "Country", span: "sm:col-span-6" },
	{ label: "Tax number", span: "sm:col-span-6" },
]
</script>

<template>
	<div :style="getStyles(modelValue?.container?.properties, screenType)">
		<div class="mx-auto w-full max-w-2xl">
			<div class="text-3xl font-semibold hover-text-input">
				<Editor
					:modelValue="modelValue?.register?.title"
					@update:modelValue="(e) => {
						set(modelValue, ['register', 'title'], e)
						emits('autoSave')
					}"
					:toggle="editorToggle" />
			</div>

			<div
				class="mt-6 overflow-hidden"
				:style="getStyles(modelValue?.register?.card?.container?.properties, screenType)">
				<div class="hover-text-input">
					<Editor
						:modelValue="modelValue?.register?.description"
						@update:modelValue="(e) => {
							set(modelValue, ['register', 'description'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<div class="mt-8 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6">
					<div v-for="field in previewFields" :key="field.label" :class="field.span">
						<label class="block text-sm font-medium text-gray-700">
							{{ trans(field.label) }}
						</label>
						<div class="mt-2 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base text-gray-400">
							&nbsp;
						</div>
					</div>

					<div class="flex gap-2 sm:col-span-6 text-sm">
						<input type="checkbox" disabled class="mt-1" />
						<span>{{ trans("Opt in to our newsletter for updates and offers.") }}</span>
					</div>

					<div class="flex gap-2 sm:col-span-6 text-sm">
						<input type="checkbox" disabled class="mt-1" />
						<span class="underline">
							{{ modelValue?.register?.terms?.text || trans("I agree with the terms and conditions") }}
						</span>
					</div>
				</div>

				<div
					class="mt-10 flex w-full items-center justify-center"
					:style="getStyles(modelValue?.register?.button?.container?.properties, screenType)">
					{{ modelValue?.register?.button?.text }}
				</div>
			</div>

			<div class="mt-4 text-sm hover-text-input">
				<Editor
					:modelValue="modelValue?.register?.login?.note"
					@update:modelValue="(e) => {
						set(modelValue, ['register', 'login', 'note'], e)
						emits('autoSave')
					}"
					:toggle="editorToggle" />
			</div>
		</div>
	</div>
</template>
