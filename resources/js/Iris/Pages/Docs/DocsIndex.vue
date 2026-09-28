<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { docCategoryLabel, DOC_CATEGORY_ORDER, type DocSummary } from "@/Iris/Composables/useIrisDocs"

const props = defineProps<{
	docs: DocSummary[]
}>()

const search = ref("")

const matchingDocs = computed(() => {
	const words = search.value.toLowerCase().split(/\s+/).filter(Boolean)
	if (!words.length) {
		return props.docs
	}

	return props.docs.filter((doc) => {
		const haystack = `${doc.title} ${doc.summary}`.toLowerCase()
		return words.every((word) => haystack.includes(word))
	})
})

const groups = computed(() => {
	const byCategory = new Map<string, DocSummary[]>()
	matchingDocs.value.forEach((doc) => {
		const category = doc.category ?? "other"
		byCategory.set(category, [...(byCategory.get(category) ?? []), doc])
	})

	return [...byCategory.entries()]
		.sort(([a], [b]) => DOC_CATEGORY_ORDER.indexOf(a) - DOC_CATEGORY_ORDER.indexOf(b))
		.map(([category, docs]) => ({ category, docs }))
})
</script>

<template>
	<div class="bg-white">
		<section class="mx-auto max-w-5xl px-4 pt-12 sm:px-6 lg:px-8">
			<h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
				{{ ctrans("Help and guides") }}
			</h1>
			<p class="mt-3 max-w-2xl text-base text-gray-500">
				{{ ctrans("How to connect your store, add products and handle orders, step by step.") }}
			</p>

			<label for="docs-search" class="sr-only">{{ ctrans("Search the guides") }}</label>
			<input
				id="docs-search"
				v-model="search"
				type="search"
				:placeholder="ctrans('Search the guides')"
				class="mt-6 w-full max-w-md rounded-lg border-0 px-3 py-2 text-sm text-gray-700 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-[var(--theme-color-0)]" />
		</section>

		<section class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
			<p v-if="!groups.length" class="text-sm text-gray-500">
				{{ ctrans("No guide matches your search.") }}
			</p>

			<div v-for="group in groups" :key="group.category" class="mb-10">
				<h2 class="mb-4 !text-lg font-semibold text-gray-900">
					{{ docCategoryLabel(group.category) }}
				</h2>
				<ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
					<li v-for="doc in group.docs" :key="doc.slug">
						<Link
							:href="doc.url"
							class="block h-full rounded-lg p-4 ring-1 ring-gray-200 transition hover:ring-[var(--theme-color-0)]">
							<span class="block font-semibold text-gray-900">{{ doc.title }}</span>
							<span class="mt-1 block text-sm text-gray-500">{{ doc.summary }}</span>
						</Link>
					</li>
				</ul>
			</div>
		</section>
	</div>
</template>
