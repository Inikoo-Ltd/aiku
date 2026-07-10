<script setup lang="ts">
import { computed } from "vue"
import { getIrisComponent } from "@/Iris/Composables/getIrisComponents"

const props = defineProps<{
	type: string
	shopType?: string
	code: string
	fieldValue: any
	indexBlock: number
	screenType?: string
}>()

const blockComponent = computed(() => getIrisComponent(props.type, { shop_type: props.shopType }))
</script>

<template>
	<component
		:is="blockComponent"
		:screenType="screenType"
		:code="code"
		:fieldValue="fieldValue"
		:indexBlock="indexBlock"
	/>
</template>
