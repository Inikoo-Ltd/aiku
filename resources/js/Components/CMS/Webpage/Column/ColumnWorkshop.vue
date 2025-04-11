<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ColumnWebppage from "./ColumnWebppageWorkshop.vue";

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
	<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
		<div class="bg-white">
			<ColumnWebppage
				v-model="modelValue.column_1"
				@update:modelValue="() => emits('autoSave')"
				:webpageData="webpageData"
				:blockData="blockData"
			/>
		</div>
		<div class="bg-white">
			<ColumnWebppage
				v-model="modelValue.column_2"
				@update:modelValue="() => emits('autoSave')"
				:webpageData="webpageData"
				:blockData="blockData"
			/>
		</div>
	</div>
</template>
