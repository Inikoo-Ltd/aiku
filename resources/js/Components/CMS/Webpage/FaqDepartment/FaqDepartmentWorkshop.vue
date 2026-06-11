<script setup lang="ts">
import { inject, computed } from "vue"
import FaqWorkshop from "@/Components/CMS/Webpage/Family2ExtraDescription/FaqWorkshop.vue"
import { getStyles } from "@/Composables/styles"
import EmptyState from "@/Components/Utils/EmptyState.vue"

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: object
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})
</script>

<template>
	<div
		v-if="modelValue?.faqs?.length"
		:id="modelValue?.id ? modelValue?.id : 'faq' + indexBlock"
		component="faq-department"
		class="bg-[#F3F3F3]">
		<div class="mx-auto max-w-[1600px] px-6 py-12 lg:px-12">
			<h2 class="mb-8 text-4xl font-bold tracking-tight text-[#0F1E2E] lg:text-5xl">FAQ</h2>

			<FaqWorkshop
				:modelValue="modelValue"
				:screen-type="screenType"
				:faqs="modelValue.faqs" />
		</div>
	</div>
	<EmptyState
		v-else
		:data="{
			title: ctrans('There is no FAQ for this department'),
			description: ctrans('Please edit it on the department page'),
		}" />
</template>
