<script setup lang="ts">
import { computed, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@fal"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import BlogCardIris from "@/Iris/Components/IrisBlocks/BlogCardIris.vue"
import BlogCategoryCardIris from "@/Iris/Components/IrisBlocks/BlogCategoryCardIris.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import type { BlogCategory, BlogPost } from "@/types/Iris/Blog"
import { getBlogCategoryOptions, getShopType } from "@/Composables/useBlogCategories"

library.add(faEnvelope)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Record<string, any>
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

const layout: any = inject("layout", retinaLayoutStructure)

const uploadImageRoute = computed(() => ({
	name: props.webpageData?.images_upload_route?.name,
	parameters: { modelHasWebBlocks: props.blockData?.id },
}))

const textToggle = ["bold", "italic", "underline", "bulletList", "orderedList", "alignLeft", "alignCenter", "alignRight", "color", "clear", "undo", "redo"]

const placeholderContent: Record<string, { description: string; url: string; icon: string }> = {
	newsletters: {
		description: trans("Stories, updates and highlights sent to our subscribers."),
		url: "/david-aw-news",
		icon: "fal fa-plane-departure",
	},
	product_guides: {
		description: trans("Step by step guides to get the most out of every range."),
		url: "/product-guides",
		icon: "fal fa-book-open",
	},
	business_tips: {
		description: trans("Practical advice to help your business grow faster."),
		url: "/business-tips",
		icon: "fal fa-chart-bar",
	},
	integrations_guides: {
		description: trans("Walkthroughs for connecting your shop to the channels you already sell on."),
		url: "/integrations-guides",
		icon: "fal fa-plug",
	},
	dropshipping_guides: {
		description: trans("How to source, list and fulfil products without holding stock."),
		url: "/dropshipping-guides",
		icon: "fal fa-boxes",
	},
}

const placeholderCategories = computed<BlogCategory[]>(() =>
	getBlogCategoryOptions(getShopType(props.webpageData)).map(category => ({
		value: category.value,
		label: trans(category.label),
		count: 0,
		...placeholderContent[category.value],
	}))
)

const categories = computed<BlogCategory[]>(() => {
	const stored = props.modelValue?.categories?.length ? props.modelValue.categories : placeholderCategories.value

	return stored.map((category: BlogCategory) => {
		const content = props.modelValue?.category_content?.[category.value]
		const label = content?.label?.trim()

		return {
			...category,
			...(label ? { custom_label: label } : {}),
			...(content?.image
				? {
						image_src: content.image,
						image_alt: content.image_alt || label || category.label,
						third_party_image_preview: undefined,
					}
				: {}),
		}
	})
})

const blogs = computed<BlogPost[]>(() => {
	if (props.modelValue?.show_list === false) {
		return []
	}

	const limit = Number(props.modelValue?.number_of_posts)
	const posts = props.modelValue?.blogs ?? []

	return limit > 0 ? posts.slice(0, limit) : posts
})

const blogColumnClass = computed(() => {
	const total = Math.min(blogs.value.length, 4)

	return (
		{
			1: "sm:grid-cols-1",
			2: "sm:grid-cols-2",
			3: "sm:grid-cols-2 lg:grid-cols-3",
			4: "sm:grid-cols-2 lg:grid-cols-4",
		}[total] ?? "sm:grid-cols-2 lg:grid-cols-4"
	)
})

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
	const columns = props.modelValue?.columns ?? {}

	return [
		mobileColumnClass[Number(columns.mobile)] ?? "grid-cols-1",
		tabletColumnClass[Number(columns.tablet)] ?? "sm:grid-cols-2",
		desktopColumnClass[Number(columns.desktop)] ?? "lg:grid-cols-3",
	]
})

const showExplore = computed(() => props.modelValue?.explore?.show !== false)
const showNewsletter = computed(() => props.modelValue?.newsletter?.show !== false)
const showPanels = computed(() => showExplore.value || showNewsletter.value)

const categoryDescription = (category: BlogCategory) =>
	props.modelValue?.category_content?.[category.value]?.description ?? category.description

const onEdit = (path: string | string[], value: string) => {
	set(props.modelValue, path, value)
	emits("update:modelValue", props.modelValue)
	emits("autoSave")
}

const onActive = () => sendMessageToParent("activeBlock", props.indexBlock)

/**
 * The preview shows the same cards as the live block, links included. Following one would leave the
 * workshop, so every anchor is neutralised here instead of rendering the cards without their links.
 */
const onPreviewClick = (event: MouseEvent) => {
	const anchor = (event.target as HTMLElement)?.closest?.("a[href]")

	if (anchor) {
		event.preventDefault()
		event.stopPropagation()
	}

	onActive()
}
</script>

<template>
	<div
		:id="modelValue?.id ? modelValue.id : 'blog-categories' + indexBlock"
		component="blog-categories"
		@click.capture="onPreviewClick"
		:style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			...getStyles(modelValue?.container?.properties, screenType),
		}">
		<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
			<div class="mb-8 text-center">
				<span v-if="modelValue?.title" class="text-2xl font-bold tracking-tight sm:text-3xl">
					{{ modelValue.title }}
				</span>

				<EditorV2
					:modelValue="modelValue?.subtitle"
					:toggle="textToggle"
					:uploadImageRoute="uploadImageRoute"
					class="editor-class mx-auto mt-3 text-base text-gray-500"
					@focus="onActive"
					@update:modelValue="value => onEdit('subtitle', value)" />
			</div>

			<div class="grid gap-6" :class="columnClass">
				<BlogCategoryCardIris
					v-for="(category, index) in categories"
					:key="category.value"
					:category="category"
					:position="index + 1"
					:cardProperties="modelValue?.card?.container?.properties"
					:showIcon="modelValue?.show_icon !== false"
					:showPosition="modelValue?.show_position !== false"
					:showDescription="modelValue?.show_description !== false"
					:showCta="modelValue?.show_cta !== false"
					:ctaLabel="modelValue?.cta_label"
					:showUrl="modelValue?.show_url !== false"
					:screenType="screenType">
					<template #description>
						<EditorV2
							:modelValue="categoryDescription(category)"
							:toggle="textToggle"
							:uploadImageRoute="uploadImageRoute"
							@focus="onActive"
							@update:modelValue="
								value => onEdit(['category_content', category.value, 'description'], value)
							" />
					</template>
				</BlogCategoryCardIris>
			</div>

			<div v-if="showPanels" class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-2">
				<div
					v-if="showExplore"
					class="flex flex-col justify-center gap-3 rounded-2xl bg-[color-mix(in_srgb,var(--theme-color-0)_7%,white)] p-8 ring-1 ring-[color-mix(in_srgb,var(--theme-color-0)_18%,white)]">
					<span class="text-[11px] font-semibold uppercase tracking-widest text-[var(--theme-color-0)]">
						{{ modelValue?.explore?.eyebrow ?? trans('New here?') }}
					</span>
					<h2 class="!text-2xl font-bold text-gray-900">
						{{ modelValue?.explore?.title ?? trans('Start exploring') }}
					</h2>

					<EditorV2
						:modelValue="modelValue?.explore?.description"
						:toggle="textToggle"
						:uploadImageRoute="uploadImageRoute"
						class="editor-class max-w-md text-sm leading-relaxed text-gray-500"
						@focus="onActive"
						@update:modelValue="value => onEdit(['explore', 'description'], value)" />

					<span
						class="mt-2 inline-flex w-fit items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[var(--theme-color-0)] shadow-sm ring-1 ring-[color-mix(in_srgb,var(--theme-color-0)_30%,white)]">
						{{ modelValue?.explore?.label ?? trans('Browse All Blogs') }}
						<span aria-hidden="true">→</span>
					</span>
				</div>

				<div
					v-if="showNewsletter"
					class="flex flex-col justify-center gap-3 rounded-2xl bg-gray-50 p-8 ring-1 ring-gray-200">
					<span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
						{{ modelValue?.newsletter?.eyebrow ?? trans('Stay in the loop') }}
					</span>
					<h2 class="!text-2xl font-bold text-gray-900">
						{{ modelValue?.newsletter?.title ?? trans('Get the newsletter') }}
					</h2>

					<EditorV2
						:modelValue="modelValue?.newsletter?.description"
						:toggle="textToggle"
						:uploadImageRoute="uploadImageRoute"
						class="editor-class max-w-md text-sm leading-relaxed text-gray-500"
						@focus="onActive"
						@update:modelValue="value => onEdit(['newsletter', 'description'], value)" />

					<div class="mt-2 flex flex-col gap-2 sm:flex-row">
						<div class="relative w-full">
							<input
								type="email"
								disabled
								:placeholder="trans('Enter your email')"
								class="w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400" />
							<FontAwesomeIcon
								icon="fal fa-envelope"
								class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"
								fixed-width
								aria-hidden="true" />
						</div>

						<span
							class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[var(--theme-color-0)] px-4 py-2 text-sm font-semibold text-[var(--theme-color-1)]">
							<FontAwesomeIcon icon="fal fa-envelope" fixed-width aria-hidden="true" />
							{{ modelValue?.newsletter?.label ?? trans('Subscribe') }}
						</span>
					</div>
				</div>
			</div>

			<div v-if="blogs.length" class="mt-14">
				<div v-if="modelValue?.list_title" class="mb-8 text-center">
					<span class="text-2xl font-bold tracking-tight sm:text-3xl">
						{{ modelValue.list_title }}
					</span>
				</div>

				<div class="grid grid-cols-1 gap-6" :class="blogColumnClass">
					<BlogCardIris
						v-for="post in blogs"
						:key="post.id"
						:post="post"
						:cardProperties="modelValue?.card?.container?.properties"
						:showPublishedDate="modelValue?.list_show_published_date !== false"
						:showCta="modelValue?.list_show_cta !== false"
						:ctaLabel="modelValue?.list_cta_label"
						:screenType="screenType" />
				</div>

				<div v-if="modelValue?.blogs_total > blogs.length" class="mt-8 text-center">
					<span class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-800">
						{{ modelValue?.list_load_more_label || trans("Load more blogs") }}
					</span>
				</div>
			</div>
		</div>
	</div>
</template>
