<script setup lang="ts">
import { computed, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import BlogCardIris from "@/Iris/Components/IrisBlocks/BlogCardIris.vue"
import type { BlogPost } from "@/types/Iris/Blog"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
	fieldValue: {
		id?: string
		title?: string
		blogs?: BlogPost[]
		blog_index_url?: string
		show_published_date?: boolean
		show_cta?: boolean
		cta_label?: string
		show_view_all?: boolean
		view_all_label?: string
		number_of_posts?: number
		card?: { container?: { properties?: any } }
		container?: { properties?: any }
	}
	screenType?: "mobile" | "tablet" | "desktop"
	indexBlock?: number | string
}>()

const layout: any = inject("layout", {})

const blogs = computed(() => {
	const all = props.fieldValue?.blogs ?? []
	const limit = Number(props.fieldValue?.number_of_posts)

	return limit > 0 ? all.slice(0, limit) : all
})

const columnClass = computed(() => {
	const total = Math.min(blogs.value.length, 5)

	return {
		1: "sm:grid-cols-1",
		2: "sm:grid-cols-2",
		3: "sm:grid-cols-2 lg:grid-cols-3",
		4: "sm:grid-cols-2 lg:grid-cols-4",
		5: "sm:grid-cols-3 lg:grid-cols-5",
	}[total] ?? "sm:grid-cols-3 lg:grid-cols-5"
})
</script>

<template>
	<div
		v-if="blogs.length"
		:id="fieldValue?.id ? fieldValue.id : 'blog-list' + indexBlock"
		component="blog-list"
		:style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(fieldValue?.container?.properties, screenType),
		}">
		<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
			<div v-if="fieldValue?.title" class="mb-8 text-center">
				<span class="text-2xl font-bold tracking-tight sm:text-3xl">
					{{ fieldValue.title }}
				</span>
			</div>

			<div class="grid grid-cols-1 gap-6" :class="columnClass">
				<BlogCardIris
					v-for="post in blogs"
					:key="post.id"
					:post="post"
					:cardProperties="fieldValue?.card?.container?.properties"
					:showPublishedDate="fieldValue?.show_published_date !== false"
					:showCta="fieldValue?.show_cta !== false"
					:ctaLabel="fieldValue?.cta_label"
					:screenType="screenType" />
			</div>

			<div v-if="fieldValue?.show_view_all" class="mt-8 text-center">
				<a
					:href="fieldValue?.blog_index_url || '/blog'"
					class="inline-flex items-center rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50">
					{{ fieldValue?.view_all_label || trans("View all posts") }}
				</a>
			</div>
		</div>
	</div>
</template>
