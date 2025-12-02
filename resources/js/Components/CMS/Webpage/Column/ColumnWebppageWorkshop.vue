<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { trans } from 'laravel-vue-i18n'
import { getStyles } from "@/Composables/styles"
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure";

library.add(faCube, faLink, faImage)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData: Object
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()


const layout = inject('layout', layoutStructure);

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()

const updateData = (newVal: any) => {
	emits('update:modelValue', { ...newVal })
}
</script>

<template>
		
	<section class="w-full min-h-[100px] flex items-center justify-center"
		:style="getStyles(modelValue?.data?.fieldValue?.container?.properties,screenType)"
	>
		<div
			v-if="!modelValue"
			class="flex flex-col items-center justify-center text-center text-gray-500  rounded-xl p-8"
		>
			<p class="text-lg font-semibold mb-2">{{ trans("No component selected") }}</p>
			<p class="text-sm">{{ trans("Please select or add a content block first.") }}</p>
		</div>

	
		<component
			v-else
			class="w-full"
			:is="getComponent(modelValue.code, { shop_type: layout?.shopState?.type })"
			:webpageData="webpageData"
			:blockData="{...modelValue, id : blockData.id }"
			@autoSave="() => updateData(modelValue)"
			v-model="modelValue.data.fieldValue"
			:screenType="screenType"
		/>
	</section>
</template>

