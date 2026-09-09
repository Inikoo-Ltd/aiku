<script setup lang="ts">
import { computed, inject, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@fal"
import { faCheckCircle } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import BlogCardIris from "@/Iris/Components/IrisBlocks/BlogCardIris.vue"
import BlogCategoryCardIris from "@/Iris/Components/IrisBlocks/BlogCategoryCardIris.vue"
import type { BlogCategory, BlogCategoryContent, BlogPost } from "@/types/Iris/Blog"
import { getStyles } from "@/Composables/styles"

library.add(faEnvelope, faCheckCircle)

type Panel = {
	show?: boolean
	eyebrow?: string
	title?: string
	description?: string
	label?: string
}

const props = defineProps<{
	fieldValue: {
		id?: string
		title?: string
		subtitle?: string
		categories?: BlogCategory[]
		category_content?: Record<string, BlogCategoryContent>
		blogs?: BlogPost[]
		blogs_total?: number
		show_list?: boolean
		list_title?: string
		number_of_posts?: number
		list_show_published_date?: boolean
		list_show_cta?: boolean
		list_cta_label?: string
		list_load_more_label?: string
		explore?: Panel
		newsletter?: Panel
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

const categories = computed<BlogCategory[]>(() => {
	const categoryContent = props.fieldValue?.category_content ?? {}

	return (props.fieldValue?.categories ?? []).map(category => {
		const content = categoryContent[category.value]

		if (!content) {
			return category
		}

		const label = content.label?.trim()
		const description = content.description?.trim()
		const hasDescription = !!description && description.replace(/<[^>]*>/g, "").trim() !== ""

		return {
			...category,
			...(label ? { custom_label: label } : {}),
			...(hasDescription ? { description } : {}),
			...(content.image
				? {
						image_src: content.image,
						image_alt: content.image_alt || label || category.label,
						third_party_image_preview: undefined,
					}
				: {}),
		}
	})
})

const listId = computed(() => (props.fieldValue?.id ? props.fieldValue.id : "blog-categories" + props.indexBlock) + "-blogs")

const perPage = computed(() => {
	const limit = Number(props.fieldValue?.number_of_posts)

	return limit > 0 ? limit : 8
})

const serverBlogs = computed<BlogPost[]>(() => {
	if (props.fieldValue?.show_list === false) {
		return []
	}

	return (props.fieldValue?.blogs ?? []).slice(0, perPage.value)
})

/**
 * The block is rendered with the first page of blogs, the following ones are asked for the same way
 * the blog dashboard pages them, so the list is never capped by what the block was built with.
 */
const fetchedBlogs = ref<BlogPost[]>([])
const loadedPage = ref(1)
const lastPage = ref<number | null>(null)
const isLoadingMore = ref(false)

watch(serverBlogs, () => {
	fetchedBlogs.value = []
	loadedPage.value = 1
	lastPage.value = null
})

const blogs = computed<BlogPost[]>(() => [...serverBlogs.value, ...fetchedBlogs.value])

const isIris = computed(() => !!layout?.iris)

const canLoadMore = computed(() => {
	if (!isIris.value || !serverBlogs.value.length) {
		return false
	}

	if (lastPage.value !== null) {
		return loadedPage.value < lastPage.value
	}

	return blogs.value.length < Number(props.fieldValue?.blogs_total ?? 0)
})

const loadMoreBlogs = async () => {
	if (isLoadingMore.value || !canLoadMore.value) {
		return
	}

	isLoadingMore.value = true

	try {
		const { data } = await axios.get(route("iris.json.blogs.index"), {
			params: { page: loadedPage.value + 1, perPage: perPage.value },
		})

		fetchedBlogs.value = [...fetchedBlogs.value, ...(data?.data ?? [])]
		loadedPage.value = data?.meta?.current_page ?? loadedPage.value + 1
		lastPage.value = data?.meta?.last_page ?? loadedPage.value
	} catch (error) {
		lastPage.value = loadedPage.value
	} finally {
		isLoadingMore.value = false
	}
}

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

const showExplore = computed(() => props.fieldValue?.explore?.show !== false)
const showNewsletter = computed(() => props.fieldValue?.newsletter?.show !== false)
const showPanels = computed(() => showExplore.value || showNewsletter.value)

const isSubscribing = ref(false)
const subscribeState = ref("")
const inputEmail = ref("")
const errorMessage = ref("")
const hiddenField = ref("")

const onSubmitSubscribe = async () => {
	isSubscribing.value = true
	errorMessage.value = ""
	subscribeState.value = ""

	if (hiddenField.value) {
		isSubscribing.value = false
		return
	}

	if (!layout?.iris?.website?.id) {
		setTimeout(() => {
			inputEmail.value = ""
			subscribeState.value = "success"
			isSubscribing.value = false
		}, 700)

		return
	}

	try {
		await axios.post(window.origin + "/app/webhooks/subscribe-newsletter", {
			email: inputEmail.value,
		})

		inputEmail.value = ""
		subscribeState.value = "success"
	} catch (error: any) {
		subscribeState.value = "error"
		errorMessage.value = error?.errors?.email || trans("An error occurred while subscribing.")
	}

	isSubscribing.value = false
}

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
		v-if="categories.length || blogs.length"
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

				<div
					v-if="fieldValue?.subtitle"
					class="editor-class mx-auto mt-3 text-base text-gray-500"
					v-html="fieldValue.subtitle" />
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

			<div v-if="showPanels && categories.length" class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-2">
				<div
					v-if="showExplore"
					class="flex flex-col justify-center gap-3 rounded-2xl bg-[color-mix(in_srgb,var(--theme-color-0)_7%,white)] p-8 ring-1 ring-[color-mix(in_srgb,var(--theme-color-0)_18%,white)]">
					<span class="text-[11px] font-semibold uppercase tracking-widest text-[var(--theme-color-0)]">
						{{ fieldValue?.explore?.eyebrow ?? trans('New here?') }}
					</span>
					<h2 class="!text-2xl font-bold text-gray-900">
						{{ fieldValue?.explore?.title ?? trans('Start exploring') }}
					</h2>
					<div
						class="editor-class max-w-md text-sm leading-relaxed text-gray-500"
						v-html="fieldValue?.explore?.description ?? trans('Dive into the latest stories, guides, and tips across all categories.')" />
					<a
						:href="'#' + listId"
						class="mt-2 inline-flex w-fit items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[var(--theme-color-0)] shadow-sm ring-1 ring-[color-mix(in_srgb,var(--theme-color-0)_30%,white)] transition hover:bg-[var(--theme-color-0)] hover:text-[var(--theme-color-1)]">
						{{ fieldValue?.explore?.label ?? trans('Browse All Blogs') }}
						<span aria-hidden="true">→</span>
					</a>
				</div>

				<div
					v-if="showNewsletter"
					class="flex flex-col justify-center gap-3 rounded-2xl bg-gray-50 p-8 ring-1 ring-gray-200">
					<span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
						{{ fieldValue?.newsletter?.eyebrow ?? trans('Stay in the loop') }}
					</span>
					<h2 class="!text-2xl font-bold text-gray-900">
						{{ fieldValue?.newsletter?.title ?? trans('Get the newsletter') }}
					</h2>
					<div
						class="editor-class max-w-md text-sm leading-relaxed text-gray-500"
						v-html="fieldValue?.newsletter?.description" />

					<Transition>
						<div v-if="subscribeState !== 'success'" class="flex flex-col">
							<form class="mt-2 flex flex-col gap-2 sm:flex-row" @submit.prevent="onSubmitSubscribe">
								<label :for="listId + '-email'" class="sr-only">{{ trans('Email address') }}</label>

								<input
									v-model="hiddenField"
									type="text"
									class="sr-only"
									aria-hidden="true"
									tabindex="-1"
									autocomplete="off" />

								<div class="relative w-full">
									<input
										:id="listId + '-email'"
										v-model="inputEmail"
										type="email"
										name="email-address"
										autocomplete="email"
										required
										:disabled="isSubscribing"
										:placeholder="trans('Enter your email')"
										class="w-full rounded-lg border-0 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-[var(--theme-color-0)]" />
									<FontAwesomeIcon
										icon="fal fa-envelope"
										class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"
										fixed-width
										aria-hidden="true" />
								</div>

								<button
									type="submit"
									:disabled="isSubscribing"
									class="relative inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[var(--theme-color-0)] px-4 py-2 text-sm font-semibold text-[var(--theme-color-1)] transition hover:opacity-90 disabled:opacity-60">
									<Transition name="spin-to-right">
										<LoadingIcon v-if="isSubscribing" />
									</Transition>
									<FontAwesomeIcon v-if="!isSubscribing" icon="fal fa-envelope" fixed-width aria-hidden="true" />
									{{ fieldValue?.newsletter?.label ?? trans('Subscribe') }}
								</button>
							</form>

							<div v-if="subscribeState === 'error'" class="mt-2 text-sm italic text-red-500">
								*{{ errorMessage }}
							</div>
						</div>

						<div v-else class="mt-2 flex items-center gap-2 text-sm font-medium text-green-600">
							<FontAwesomeIcon icon="fas fa-check-circle" class="text-lg" fixed-width aria-hidden="true" />
							{{ trans('You have successfully subscribed') }}!
						</div>
					</Transition>
				</div>
			</div>

			<div v-if="blogs.length" :id="listId" class="mt-14">
				<div v-if="fieldValue?.list_title" class="mb-8 text-center">
					<span class="text-2xl font-bold tracking-tight sm:text-3xl">
						{{ fieldValue.list_title }}
					</span>
				</div>

				<div class="grid grid-cols-1 gap-6" :class="blogColumnClass">
					<BlogCardIris
						v-for="post in blogs"
						:key="post.id"
						:post="post"
						:cardProperties="fieldValue?.card?.container?.properties"
						:showPublishedDate="fieldValue?.list_show_published_date !== false"
						:showCta="fieldValue?.list_show_cta !== false"
						:ctaLabel="fieldValue?.list_cta_label"
						:screenType="screenType" />
				</div>

				<div v-if="canLoadMore" class="mt-8 text-center">
					<button
						type="button"
						:disabled="isLoadingMore"
						class="inline-flex items-center gap-2 rounded-full border border-gray-300 px-5 py-2 text-sm font-medium text-gray-800 transition hover:bg-gray-50 disabled:opacity-60"
						@click="loadMoreBlogs">
						<Transition name="spin-to-right">
							<LoadingIcon v-if="isLoadingMore" />
						</Transition>
						{{ fieldValue?.list_load_more_label || trans("Load more blogs") }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>
