<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import Image from "@/Components/Image.vue"

library.add(faCheck)

const props = defineProps<{
	fieldValue: {}
	theme?: any
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
		:style="getStyles(fieldValue.container.properties, screenType)">
		<div class="mx-auto px-10 md:px-8 py-14">
			<div class="mt-0 xl:mt-0 w-fit mx-auto">
				<div class="text-sm/6 font-semibold text-center sm:text-2xl hover-text-input" v-html="fieldValue.value?.headline">
				</div>

				<div class="mt-2 text-sm/6 text-center max-w-3xl" v-html="fieldValue.value?.description">
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
						:placeholder="fieldValue?.value?.input?.placeholder"
					/>

					<div class="mt-4 sm:ml-4 sm:mt-0 sm:shrink-0">
						<button type="submit"
							XXclass="flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
							class="rounded-lg w-full"
							:style="getStyles(fieldValue?.button?.container?.properties, screenType)"
						>
							<FontAwesomeIcon icon="" class="" fixed-width aria-hidden="true" />
							{{ fieldValue?.button?.text }}
						</button>
						<!-- <pre>{{ fieldValue.input.placeholder }}</pre> -->
					</div>
				</form>
			</div>
		</div>
	</div>
</template>
