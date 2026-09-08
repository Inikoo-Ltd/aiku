<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { routeType } from "@/types/route"

defineOptions({ inheritAttrs: false })

const props = defineProps<{
	form: any
	fieldName: string
	options?: any
	refForms?: any
	fieldData?: {
		placeholder?: string
		editable?: boolean
		uploadImageRoute?: routeType
		routeGetInternalLink?: routeType
	}
}>()
</script>

<template>
	<Editor
		v-model="form[fieldName]"
		:editable="fieldData?.editable ?? true"
		:placeholder="fieldData?.placeholder"
		:uploadImageRoute="fieldData?.uploadImageRoute"
		:routeGetInternalLink="fieldData?.routeGetInternalLink" />
</template>
