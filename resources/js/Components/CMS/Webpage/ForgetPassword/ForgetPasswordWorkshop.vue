<script setup lang="ts">
import { set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@fal"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"

library.add(faCheckCircle)

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
		<div class="mx-auto w-full max-w-md">
			<div class="text-xl font-semibold sm:text-2xl hover-text-input">
				<Editor
					:modelValue="modelValue?.forgot_password?.title"
					@update:modelValue="(e) => {
						set(modelValue, ['forgot_password', 'title'], e)
						emits('autoSave')
					}"
					:toggle="editorToggle" />
			</div>

			<div
				class="mt-6 overflow-hidden"
				:style="getStyles(modelValue?.forgot_password?.card?.container?.properties, screenType)">
				<div class="text-sm hover-text-input">
					<Editor
						:modelValue="modelValue?.forgot_password?.description"
						@update:modelValue="(e) => {
							set(modelValue, ['forgot_password', 'description'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<form class="mt-6 space-y-5" @submit.prevent>
					<div>
						<label class="block text-sm font-semibold">
							{{ modelValue?.forgot_password?.email?.label }}
						</label>
						<input
							type="email"
							disabled
							class="mt-2 w-full rounded-sm border border-gray-400 bg-white px-3 py-2 text-base text-gray-700 outline-none"
							:placeholder="modelValue?.forgot_password?.email?.placeholder" />
					</div>

					<div
						class="relative flex w-full items-center justify-center gap-x-2 rounded-sm"
						:style="getStyles(modelValue?.forgot_password?.button?.container?.properties, screenType)">
						{{ modelValue?.forgot_password?.button?.text }}
					</div>
				</form>
			</div>

			<div
				class="mt-6 overflow-hidden"
				:style="getStyles(modelValue?.forgot_password?.card?.container?.properties, screenType)">
				<div class="text-center">
					<FontAwesomeIcon icon="fal fa-check-circle" class="text-4xl text-green-500" fixed-width aria-hidden="true" />
				</div>

				<div class="mt-4 text-xl font-semibold hover-text-input">
					<Editor
						:modelValue="modelValue?.forgot_password?.success?.title"
						@update:modelValue="(e) => {
							set(modelValue, ['forgot_password', 'success', 'title'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<div class="mt-2 text-sm hover-text-input">
					<Editor
						:modelValue="modelValue?.forgot_password?.success?.description"
						@update:modelValue="(e) => {
							set(modelValue, ['forgot_password', 'success', 'description'], e)
							emits('autoSave')
						}"
						:toggle="editorToggle" />
				</div>

				<div class="mt-2 text-center text-sm font-semibold opacity-60">
					name@example.com
				</div>
			</div>

			<div class="mt-6 text-sm hover-text-input">
				<Editor
					:modelValue="modelValue?.forgot_password?.login?.note"
					@update:modelValue="(e) => {
						set(modelValue, ['forgot_password', 'login', 'note'], e)
						emits('autoSave')
					}"
					:toggle="editorToggle" />
			</div>

			<div v-if="modelValue?.forgot_password?.login?.text" class="mt-2 text-center text-sm">
				<span class="underline">
					{{ modelValue?.forgot_password?.login?.text }}
				</span>
			</div>
		</div>
	</div>
</template>
