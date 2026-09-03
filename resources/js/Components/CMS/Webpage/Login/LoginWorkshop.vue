<script setup lang="ts">
import { set } from "lodash-es"
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
</script>

<template>
	<div :style="getStyles(modelValue?.container?.properties, screenType)">
		<div class="mx-auto grid w-full max-w-6xl grid-cols-1 gap-x-16 gap-y-14 px-4 md:grid-cols-2">
			<div class="mx-auto w-full max-w-md">
				<div class="text-xl font-semibold sm:text-2xl hover-text-input">
					<Editor
						:modelValue="modelValue?.login?.title"
						@update:modelValue="(e) => {
							set(modelValue, ['login', 'title'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<form class="mt-8 space-y-5" @submit.prevent>
					<div>
						<label class="block text-sm font-semibold">
							{{ modelValue?.login?.username?.label }}
						</label>
						<input
							type="text"
							disabled
							class="mt-2 w-full rounded-sm border border-gray-400 bg-white px-3 py-2 text-base text-gray-700 outline-none"
							:placeholder="modelValue?.login?.username?.placeholder" />
					</div>

					<div>
						<label class="block text-sm font-semibold">
							{{ modelValue?.login?.password?.label }}
						</label>
						<input
							type="password"
							disabled
							class="mt-2 w-full rounded-sm border border-gray-400 bg-white px-3 py-2 text-base text-gray-700 outline-none"
							:placeholder="modelValue?.login?.password?.placeholder" />

						<div class="mt-2 flex items-center justify-between">
							<label v-if="modelValue?.login?.remember?.visible" class="flex select-none items-center gap-x-2 text-sm">
								<input type="checkbox" disabled />
								{{ modelValue?.login?.remember?.text }}
							</label>
							<span v-else />

							<span class="text-sm underline">
								{{ modelValue?.login?.forgot_password?.text }}
							</span>
						</div>
					</div>

					<div
						class="relative flex w-full items-center justify-center gap-x-2 rounded-sm"
						:style="getStyles(modelValue?.login?.button?.container?.properties, screenType)">
						{{ modelValue?.login?.button?.text }}
					</div>

					<div class="text-sm hover-text-input">
						<Editor
							:modelValue="modelValue?.login?.help"
							@update:modelValue="(e) => {
								set(modelValue, ['login', 'help'], e)
								emits('autoSave')
							}"
							:toggle="editorToggle" />
					</div>
				</form>
			</div>

			<div class="mx-auto w-full max-w-md">
				<div class="text-xl font-semibold sm:text-2xl hover-text-input">
					<Editor
						:modelValue="modelValue?.register?.title"
						@update:modelValue="(e) => {
							set(modelValue, ['register', 'title'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<div class="mt-6 flex justify-center">
					<div
						class="inline-flex items-center justify-center rounded-sm"
						:style="getStyles(modelValue?.register?.button?.container?.properties, screenType)">
						{{ modelValue?.register?.button?.text }}
					</div>
				</div>

				<div class="mt-2 text-sm hover-text-input">
					<Editor
						:modelValue="modelValue?.register?.note"
						@update:modelValue="(e) => {
							set(modelValue, ['register', 'note'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<div class="mt-8 text-sm hover-text-input">
					<Editor
						:modelValue="modelValue?.register?.description"
						@update:modelValue="(e) => {
							set(modelValue, ['register', 'description'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<div
					class="mt-2 text-sm hover-text-input"
					:style="getStyles(modelValue?.register?.benefits?.container?.properties, screenType)">
					<Editor
						:modelValue="modelValue?.register?.benefits?.text"
						@update:modelValue="(e) => {
							set(modelValue, ['register', 'benefits', 'text'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>
			</div>
		</div>
	</div>
</template>
