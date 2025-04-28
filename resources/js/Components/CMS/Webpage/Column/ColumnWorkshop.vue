<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ColumnWebppage from "./ColumnWebppageWorkshop.vue"
import { getStyles } from "@/Composables/styles"

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
	<div class="grid grid-cols-1 md:grid-cols-2 gap-4"
		:style="getStyles(modelValue?.container?.properties)"
	>
		<!-- <pre>{{ modelValue.container.properties.background }}</pre> -->
		<ColumnWebppage
			v-model="modelValue.column_1"
			@update:modelValue="() => emits('autoSave')"
			:webpageData="webpageData"
			:blockData="blockData"
		/>
	
		<ColumnWebppage
			v-model="modelValue.column_2"
			@update:modelValue="() => emits('autoSave')"
			:webpageData="webpageData"
			:blockData="blockData"
		/>
	</div>
</template>
