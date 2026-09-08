<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import BlogCategoriesIris from "@/Iris/Components/IrisBlocks/BlogCategoriesIris.vue"
import { sendMessageToParent } from "@/Composables/Workshop"
import type { BlogCategory } from "@/types/Iris/Blog"

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Record<string, any>
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const placeholderCategories: BlogCategory[] = [
	{
		value: "newsletters",
		label: trans("Newsletters"),
		description: trans("Stories, updates and highlights sent to our subscribers."),
		url: "/blog/newsletters",
		icon: "fal fa-plane-departure",
		count: 0,
	},
	{
		value: "product_guides",
		label: trans("Product Guides"),
		description: trans("Step by step guides to get the most out of every range."),
		url: "/blog/product-guides",
		icon: "fal fa-book-open",
		count: 0,
	},
	{
		value: "business_tips",
		label: trans("Business Tips"),
		description: trans("Practical advice to help your business grow faster."),
		url: "/blog/business-tips",
		icon: "fal fa-chart-bar",
		count: 0,
	},
]

const previewValue = computed(() => ({
	...props.modelValue,
	categories: props.modelValue?.categories?.length
		? props.modelValue.categories
		: placeholderCategories,
}))
</script>

<template>
	<div @click="() => sendMessageToParent('activeBlock', indexBlock)">
		<BlogCategoriesIris
			:fieldValue="previewValue"
			:screenType="screenType"
			:indexBlock="indexBlock" />
	</div>
</template>
