<script setup lang="ts">
import { computed, ref, watchEffect } from "vue"
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

// SSR renders every block with screenType 'desktop' and the live value only flips after
// mount; changing a prop on a still-loading async component swaps its SSR DOM for the
// loading placeholder (a big layout shift). Hold 'desktop' until the chunk is in.
const chunkResolved = ref(false)

watchEffect(() => {
	const component: any = blockComponent.value
	if (!component) {
		return
	}
	if (component.__asyncResolved || typeof component.__asyncLoader !== "function") {
		chunkResolved.value = true
		return
	}
	component.__asyncLoader()
		.then(() => {
			chunkResolved.value = true
		})
		.catch(() => {
			chunkResolved.value = true
		})
})

const effectiveScreenType = computed(() => (chunkResolved.value ? props.screenType : "desktop"))
</script>

<template>
	<component
		:is="blockComponent"
		:screenType="effectiveScreenType"
		:code="code"
		:fieldValue="fieldValue"
		:indexBlock="indexBlock"
	/>
</template>
