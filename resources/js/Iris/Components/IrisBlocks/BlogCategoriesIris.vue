<script setup lang="ts">
import { computed, inject } from "vue"
import BlogCategoryCardIris from "@/Iris/Components/IrisBlocks/BlogCategoryCardIris.vue"
import type { BlogCategory } from "@/types/Iris/Blog"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: {
		id?: string
		title?: string
		subtitle?: string
		categories?: BlogCategory[]
		columns?: { desktop?: number; tablet?: number; mobile?: number }
		show_icon?: boolean
		show_position?: boolean
		show_description?: boolean
		show_cta?: boolean
		cta_label?: string
		show_url?: boolean
		card?: { container?: { properties?: any } }
		container?: { properties?: any }
	}
	screenType?: "mobile" | "tablet" | "desktop"
	indexBlock?: number | string
}>()

const layout: any = inject("layout", {})

const categories = computed(() => props.fieldValue?.categories ?? [])

const mobileColumnClass: Record<number, string> = {
	1: "grid-cols-1",
	2: "grid-cols-2",
}

const tabletColumnClass: Record<number, string> = {
	1: "sm:grid-cols-1",
	2: "sm:grid-cols-2",
	3: "sm:grid-cols-3",
	4: "sm:grid-cols-4",
}

const desktopColumnClass: Record<number, string> = {
	1: "lg:grid-cols-1",
	2: "lg:grid-cols-2",
	3: "lg:grid-cols-3",
	4: "lg:grid-cols-4",
	5: "lg:grid-cols-5",
	6: "lg:grid-cols-6",
}

const columnClass = computed(() => {
	const columns = props.fieldValue?.columns ?? {}

	return [
		mobileColumnClass[Number(columns.mobile)] ?? "grid-cols-1",
		tabletColumnClass[Number(columns.tablet)] ?? "sm:grid-cols-2",
		desktopColumnClass[Number(columns.desktop)] ?? "lg:grid-cols-3",
	]
})
</script>

<template>
	<div
		v-if="categories.length"
		:id="fieldValue?.id ? fieldValue.id : 'blog-categories' + indexBlock"
		component="blog-categories"
		:style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue?.container?.properties, screenType),
		}">
		<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
			<div v-if="fieldValue?.title || fieldValue?.subtitle" class="mb-8 text-center">
				<span v-if="fieldValue?.title" class="text-2xl font-bold tracking-tight sm:text-3xl">
					{{ fieldValue.title }}
				</span>

				<p v-if="fieldValue?.subtitle" class="mx-auto mt-3 text-base text-gray-500">
					{{ fieldValue.subtitle }}
				</p>
			</div>

			<div class="grid gap-6" :class="columnClass">
				<BlogCategoryCardIris
					v-for="(category, index) in categories"
					:key="category.value"
					:category="category"
					:position="index + 1"
					:cardProperties="fieldValue?.card?.container?.properties"
					:showIcon="fieldValue?.show_icon !== false"
					:showPosition="fieldValue?.show_position !== false"
					:showDescription="fieldValue?.show_description !== false"
					:showCta="fieldValue?.show_cta !== false"
					:ctaLabel="fieldValue?.cta_label"
					:showUrl="fieldValue?.show_url !== false"
					:screenType="screenType" />
			</div>
		</div>
	</div>
</template>
