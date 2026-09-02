<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faEnvelope } from "@fal"
import { faCheckCircle } from "@fas"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { GridProducts } from "@/Components/Product"
import BlogCardIris from "@/Iris/Components/IrisBlocks/BlogCardIris.vue"
import BlogCategoryCardIris from "@/Iris/Components/IrisBlocks/BlogCategoryCardIris.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import type { BlogPost, BlogCategory } from "@/types/Iris/Blog"
import { getBlogCategoryDisplayName } from "@/Iris/Composables/useBlogCategoryDisplayName"

library.add(faEnvelope, faCheckCircle)

type Panel = {
	eyebrow: string
	title: string
	description: string
	label?: string
}

const props = withDefaults(
	defineProps<{
		data: any
		title?: string
		blog_category?: string
		subtitle?: string
		categories?: BlogCategory[]
		explore?: Panel
		newsletter?: Panel
	}>(),
	{
		title: undefined,
		blog_category: undefined,
		subtitle: undefined,
		categories: undefined,
		explore: undefined,
		newsletter: undefined,
	}
)

const layout = inject("layout", retinaLayoutStructure)

const displayTitle = computed(() => getBlogCategoryDisplayName(props.blog_category, props.title))

const isLoadingSubmit = ref(false)
const currentState = ref("")
const inputEmail = ref("")
const errorMessage = ref("")
const hiddenField = ref("")

const onSubmitSubscribe = async () => {
	isLoadingSubmit.value = true
	errorMessage.value = ""
	currentState.value = ""

	if (hiddenField.value) {
		isLoadingSubmit.value = false
		return
	}

	if (!layout?.iris?.website?.id) {
		setTimeout(() => {
			inputEmail.value = ""
			currentState.value = "success"
			isLoadingSubmit.value = false
		}, 700)

		return
	}

	try {
		await axios.post(window.origin + "/app/webhooks/subscribe-newsletter", {
			email: inputEmail.value,
		})

		inputEmail.value = ""
		currentState.value = "success"
	} catch (error: any) {
		currentState.value = "error"
		errorMessage.value = error?.errors?.email || trans("An error occurred while subscribing.")
	}

	isLoadingSubmit.value = false
}
</script>

<template>
	<div class="min-h-screen overflow-x-hidden bg-white">
		<section>
			<div class="mx-auto max-w-7xl px-4 pt-12 text-center sm:px-6 sm:pt-12 lg:px-8">
				<h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl lg:text-5xl">
					{{ displayTitle ?? trans('Our Blog') }}
				</h1>
				<div v-if="subtitle" class="mx-auto mt-4  text-base text-gray-500">
					{{ subtitle }}
				</div>
				<div class="mx-auto mt-6 h-1 w-16 rounded-full bg-[var(--theme-color-0)]"></div>
			</div>
		</section>

		<section v-if="categories?.length" class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
			<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
				<BlogCategoryCardIris
					v-for="(category, index) in categories"
					:key="category.value"
					:category="category"
					:position="index + 1" />
			</div>
		</section>

		<section
			v-if="categories?.length"
			class="mx-auto max-w-7xl px-4 pb-10 sm:px-6 lg:px-8">
			<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
				<div
					class="flex flex-col justify-center gap-3 rounded-2xl bg-[color-mix(in_srgb,var(--theme-color-0)_7%,white)] p-8 ring-1 ring-[color-mix(in_srgb,var(--theme-color-0)_18%,white)]">
					<span class="text-[11px] font-semibold uppercase tracking-widest text-[var(--theme-color-0)]">
						{{ explore?.eyebrow ?? trans('New here?') }}
					</span>
					<h2 class="!text-2xl font-bold text-gray-900">
						{{ explore?.title ?? trans('Start exploring') }}
					</h2>
					<p class="max-w-md text-sm leading-relaxed text-gray-500">
						{{ explore?.description ?? trans('Dive into the latest stories, guides, and tips across all categories.') }}
					</p>
					<a
						href="#all-blogs"
						class="mt-2 inline-flex w-fit items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-[var(--theme-color-0)] shadow-sm ring-1 ring-[color-mix(in_srgb,var(--theme-color-0)_30%,white)] transition hover:bg-[var(--theme-color-0)] hover:text-[var(--theme-color-1)]">
						{{ explore?.label ?? trans('Browse All Blogs') }}
						<span aria-hidden="true">→</span>
					</a>
				</div>

				<div class="flex flex-col justify-center gap-3 rounded-2xl bg-gray-50 p-8 ring-1 ring-gray-200">
					<span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">
						{{ newsletter?.eyebrow ?? trans('Stay in the loop') }}
					</span>
					<h2 class="!text-2xl font-bold text-gray-900">
						{{ newsletter?.title ?? trans('Get the newsletter') }}
					</h2>
					<p class="max-w-md text-sm leading-relaxed text-gray-500">
						{{ newsletter?.description }}
					</p>

					<Transition>
						<div v-if="currentState !== 'success'" class="flex flex-col">
							<form class="mt-2 flex flex-col gap-2 sm:flex-row" @submit.prevent="onSubmitSubscribe">
								<label for="blog-newsletter-email" class="sr-only">{{ trans('Email address') }}</label>

								<input
									v-model="hiddenField"
									type="text"
									class="sr-only"
									aria-hidden="true"
									tabindex="-1"
									autocomplete="off" />

								<div class="relative w-full">
									<input
										id="blog-newsletter-email"
										v-model="inputEmail"
										type="email"
										name="email-address"
										autocomplete="email"
										required
										:disabled="isLoadingSubmit"
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
									:disabled="isLoadingSubmit"
									class="relative inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[var(--theme-color-0)] px-4 py-2 text-sm font-semibold text-[var(--theme-color-1)] transition hover:opacity-90 disabled:opacity-60">
									<Transition name="spin-to-right">
										<LoadingIcon v-if="isLoadingSubmit" />
									</Transition>
									<FontAwesomeIcon v-if="!isLoadingSubmit" icon="fal fa-envelope" fixed-width aria-hidden="true" />
									{{ trans('Subscribe') }}
								</button>
							</form>

							<div v-if="currentState === 'error'" class="mt-2 text-sm italic text-red-500">
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
		</section>

		<section id="all-blogs" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
			<GridProducts
				:resource="data"
				name="blogs"
				:label="trans('blog')"
				:preserve-scroll="true"
				:gridClass="'grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4'"
				:hideDefault="true"
				:selectedColumn="'-last_published_at'"
			>
				<template #card="{ item: post }">
					<BlogCardIris :post="(post as unknown as BlogPost)" />
				</template>
			</GridProducts>
		</section>
	</div>
</template>
