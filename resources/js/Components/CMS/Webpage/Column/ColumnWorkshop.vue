<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ColumnWebppage from "@/Components/CMS/Webpage/WorkshopComponentsHelper/ColumnWebppageWorkshop.vue"
import { getStyles } from "@/Composables/styles"
import { ulid } from "ulid"
import { ref, watch } from "vue"

library.add(faCube, faLink, faImage)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
	screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: string): void
	(e: "autoSave"): void
}>()


const key = ref(ulid())

watch(
  () => props.screenType,
  () => {
    key.value = ulid()
  }
)

</script>

<template>
	<div class="grid grid-cols-1 md:grid-cols-2 gap-4"
		:style="getStyles(modelValue?.container?.properties,screenType)"
	>
		<ColumnWebppage
			v-model="modelValue.column_1"
			@update:modelValue="() => emits('autoSave')"
			:webpageData="webpageData"
			:blockData="blockData"
			:screenType="screenType"
			:key="`col-1-${key}`"
		/>
	
		<ColumnWebppage
			v-model="modelValue.column_2"
			@update:modelValue="() => emits('autoSave')"
			:webpageData="webpageData"
			:blockData="blockData"
			:screenType="screenType"
			:key="`col-2-${key}`"
		/>

	</div>
</template>
