<script setup lang="ts">
import { computed, watch } from "vue"
import PaddingMarginProperty from "@/Components/Workshop/Properties/PaddingMarginProperty.vue"
import { trans } from "laravel-vue-i18n"
import draggable from "vuedraggable"

import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome"
import {
	faBrowser,
	faDraftingCompass,
	faRectangleWide,
	faStars,
	faBars,
	faText,
	faEye,
	faEyeSlash,
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fad"

library.add(
	faBrowser,
	faDraftingCompass,
	faRectangleWide,
	faStars,
	faBars,
	faText,
	faEye,
	faEyeSlash
)

const props = defineProps<{
	modelValue: any
	type: string
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string | number): void
}>()

const addText = () => {
	if (props.type == "text") {
		props.modelValue.values.push({
			properties: {
				width: "auto",
				height: "auto",
				position: {
					top: "0px",
					left: "0px",
					bottom: "0px",
					right: "0px",
				},
			},
			text: "New Text",
		})
	}
	emits("update:modelValue", props.modelValue)
}

const addImage = () => {
	if (props.type == "images") {
		props.modelValue.push({
			properties: {
				width: "100px",
				height: "100px",
				position: {
					top: 0,
					left: 0,
					bottom: 0,
					right: 0,
				},
			},
			sources: {
				avif: "http://10.0.0.100:8080/2bm_AyX-KHViYhTBhS2z9_W6Ep0Pzz_eXjP26ClWQ-8/bG9jYWw6Ly8vYWlrdS9hcHAvbWVkaWEvbWVkaWEvMjc2MTUvYmU0MTQ3Nzk5NGI2MDEwM2NkZDI5NzUyNmQxYmM4MDguanBn.avif",
				webp: "http://10.0.0.100:8080/_r_0qxma2l01eS-xcmJB7WNqKNYEnTEWJ3GrnAVSX0s/bG9jYWw6Ly8vYWlrdS9hcHAvbWVkaWEvbWVkaWEvMjc2MTUvYmU0MTQ3Nzk5NGI2MDEwM2NkZDI5NzUyNmQxYmM4MDguanBn.webp",
				original:
					"http://10.0.0.100:8080/5GYU0tMJNtINwmg559U7ADtCTmKeA-AAa5UDwYG_GGA/bG9jYWw6Ly8vYWlrdS9hcHAvbWVkaWEvbWVkaWEvMjc2MTUvYmU0MTQ3Nzk5NGI2MDEwM2NkZDI5NzUyNmQxYmM4MDguanBn",
			},
		})
	}
	emits("update:modelValue", props.modelValue)
}

const deleteText = (index: number) => {
	if (props.type == "text") {
		const value = props.modelValue
		value.values.splice(index, 1)
		emits("update:modelValue", value)
	}
}

const deleteImages = (index: number) => {
	if (props.type == "images") {
		const value = props.modelValue
		value.splice(index, 1)
		emits("update:modelValue", value)
	}
}
</script>

<template>
	<div v-if="type == 'text'" class="border-t border-gray-300 pb-3">
		<div class="w-full text-center py-1 font-semibold select-none text-gray-700">
			{{ type }}
		</div>
		<draggable
			:list="modelValue.values"
			handle=".handle"
			ghost-class="ghost"
			group="column"
			itemKey="column_id"
			class="mt-2 space-y-2">
			<template #item="{ element, index }">
				<div
					class="bg-white border border-gray-300 shadow-md rounded-lg transition-transform transform hover:scale-105">
					<div
						class="group flex justify-between items-center gap-x-2 relative px-3 py-2 w-full cursor-pointer">
						<div class="flex gap-x-2 items-center">
							<div class="flex items-center justify-center">
								<FontAwesomeIcon
									icon="fal fa-bars"
									class="handle text-sm cursor-grab pr-3 mr-2 text-gray-500" />
							</div>
							<h3
								class="lg:text-sm text-xs font-medium select-none text-gray-700">
								Text {{ index + 1 }}
							</h3>
						</div>
						<div
							class="p-1.5 text-base text-gray-400 hover:text-red-500 cursor-pointer transition-colors">
							<FontAwesomeIcon
								@click="() => deleteText(index)"
								icon="fal fa-times"
								v-tooltip="'Delete this block'"
								class="text-base sm:text-lg md:text-xl lg:text-2xl"
								fixed-width
								aria-hidden="true" />
						</div>
					</div>
				</div>
			</template>
		</draggable>
		<div
			class="bg-slate-50 border border-dashed border-gray-300 my-3 hover:border-solid hover:border-blue-500 transition-colors rounded-lg">
			<div
				@click="addText"
				class="group flex justify-center items-center gap-x-2 relative px-3 py-2 w-full cursor-pointer transition-transform transform hover:scale-105">
				<FontAwesomeIcon :icon="faPlus" class="text-gray-500 group-hover:text-blue-500" />
				<h3
					class="lg:text-sm text-xs font-medium select-none text-gray-700 group-hover:text-blue-500">
					Add Text
				</h3>
			</div>
		</div>
	</div>

	<div v-if="type == 'images'" class="border-t border-gray-300 pb-3">
		<div class="w-full text-center py-1 font-semibold select-none text-gray-700">
			{{ type }}
		</div>
		<draggable
			:list="modelValue"
			handle=".handle"
			ghost-class="ghost"
			group="column"
			itemKey="column_id"
			class="mt-2 space-y-2">
			<template #item="{ element, index }">
				<div
					class="bg-white border border-gray-300 shadow-md rounded-lg transition-transform transform hover:scale-105">
					<div
						class="group flex justify-between items-center gap-x-2 relative px-3 py-2 w-full cursor-pointer">
						<div class="flex gap-x-2 items-center">
							<div class="flex items-center justify-center">
								<FontAwesomeIcon
									icon="fal fa-bars"
									class="handle text-sm cursor-grab pr-3 mr-2 text-gray-500" />
							</div>
							<h3
								class="lg:text-sm text-xs font-medium select-none text-gray-700">
								Images {{ index + 1 }}
							</h3>
						</div>
						<div
							class="p-1.5 text-base text-gray-400 hover:text-red-500 cursor-pointer transition-colors">
							<FontAwesomeIcon
								@click="() => deleteImages(index)"
								icon="fal fa-times"
								v-tooltip="'Delete this block'"
								class="text-base sm:text-lg md:text-xl lg:text-2xl"
								fixed-width
								aria-hidden="true" />
						</div>
					</div>
				</div>
			</template>
		</draggable>
		<div
			class="bg-slate-50 border border-dashed border-gray-300 my-3 hover:border-solid hover:border-blue-500 transition-colors rounded-lg">
			<div
				@click="addImage"
				class="group flex justify-center items-center gap-x-2 relative px-3 py-2 w-full cursor-pointer transition-transform transform hover:scale-105">
				<FontAwesomeIcon :icon="faPlus" class="text-gray-500 group-hover:text-blue-500" />
				<h3
					class="lg:text-sm text-xs font-medium select-none text-gray-700 group-hover:text-blue-500">
					Add Image
				</h3>
			</div>
		</div>
	</div>
</template>

<style scoped>
.ghost {
	opacity: 0.5;
}
</style>
