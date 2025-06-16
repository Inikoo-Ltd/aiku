<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getIrisComponent } from "@/Composables/getIrisComponents"
import { trans } from 'laravel-vue-i18n'
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

</script>

<template>
	<section class="w-full min-h-[100px] flex items-center justify-center"
		:style="getStyles(fieldValue?.data?.fieldValue?.container?.properties,screenType)"
	>
		<!-- If no component selected -->
		<div
			v-if="!fieldValue"
			class="flex flex-col items-center justify-center text-center text-gray-500  rounded-xl p-8"
		>
			<p class="text-lg font-semibold mb-2">{{ trans("No component selected") }}</p>
			<p class="text-sm">{{ trans("Please select or add a content block first.") }}</p>
		</div>

		<!-- If modelValue is present -->
		<component
			v-else
			class="w-full"
			:is="getIrisComponent(fieldValue.code)"
			:fieldValue="fieldValue.data.fieldValue"
			:screenType="screenType"
		/>
	</section>
</template>

