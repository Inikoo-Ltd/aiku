<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { docCategoryLabel, type DocSummary } from "@/Iris/Composables/useIrisDocs"

defineProps<{
	doc: DocSummary & {
		html: string
		reading_minutes: number
		notice: string | null
		is_stale: boolean
		stale: string | null
	}
	translations: { lang: string; name: string; url: string }[]
	series: DocSummary[]
}>()
</script>

<template>
	<div class="bg-white">
		<div class="mx-auto flex max-w-5xl flex-col gap-10 px-4 py-10 sm:px-6 lg:flex-row lg:px-8">
			<article class="min-w-0 flex-1">
				<nav class="mb-4 text-sm text-gray-500">
					<Link href="/docs" class="hover:text-[var(--theme-color-0)]">{{ ctrans("Help and guides") }}</Link>
					<span aria-hidden="true"> / </span>
					<span>{{ docCategoryLabel(doc.category) }}</span>
				</nav>

				<h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ doc.title }}</h1>
				<p class="mt-2 text-sm text-gray-500">
					{{ ctrans(":minutes min read", { minutes: doc.reading_minutes }) }}
				</p>

				<div v-if="translations.length" class="mt-4 flex flex-wrap gap-2 text-sm">
					<template v-for="translation in translations" :key="translation.lang">
						<span v-if="translation.lang === doc.lang" class="rounded bg-gray-100 px-2 py-0.5 font-semibold text-gray-900">
							{{ translation.name }}
						</span>
						<Link v-else :href="translation.url" class="rounded px-2 py-0.5 text-gray-600 ring-1 ring-gray-200 hover:ring-[var(--theme-color-0)]">
							{{ translation.name }}
						</Link>
					</template>
				</div>

				<p v-if="doc.notice" class="mt-4 rounded-lg bg-gray-50 px-4 py-2 text-sm text-gray-600">
					{{ doc.is_stale ? doc.stale : doc.notice }}
				</p>

				<div class="doc-body mt-8" v-html="doc.html" />
			</article>

			<aside v-if="series.length > 1" class="lg:w-64 lg:shrink-0">
				<h2 class="mb-3 !text-sm font-semibold uppercase tracking-wide text-gray-500">
					{{ ctrans("In this series") }}
				</h2>
				<ol class="space-y-1 text-sm">
					<li v-for="item in series" :key="item.slug">
						<span v-if="item.slug === doc.slug" class="block rounded px-2 py-1 font-semibold text-gray-900 bg-gray-100">
							{{ item.title }}
						</span>
						<Link v-else :href="item.url" class="block rounded px-2 py-1 text-gray-600 hover:text-[var(--theme-color-0)]">
							{{ item.title }}
						</Link>
					</li>
				</ol>
			</aside>
		</div>
	</div>
</template>

<style scoped>
.doc-body {
	@apply text-base leading-7 text-gray-700;
}

.doc-body :deep(h2) {
	@apply mt-10 mb-3 !text-xl font-semibold text-gray-900;
}

.doc-body :deep(h3) {
	@apply mt-6 mb-2 !text-lg font-semibold text-gray-900;
}

.doc-body :deep(p) {
	@apply my-4;
}

.doc-body :deep(ul) {
	@apply my-4 list-disc pl-6;
}

.doc-body :deep(ol) {
	@apply my-4 list-decimal pl-6;
}

.doc-body :deep(li) {
	@apply my-1;
}

.doc-body :deep(a) {
	@apply font-medium text-[var(--theme-color-0)] underline underline-offset-2;
}

.doc-body :deep(code) {
	@apply rounded bg-gray-100 px-1 py-0.5 text-sm;
}

.doc-body :deep(table) {
	@apply my-6 w-full text-left text-sm;
}

.doc-body :deep(th),
.doc-body :deep(td) {
	@apply border-b border-gray-200 px-2 py-2 align-top;
}

.doc-body :deep(aside.tldr) {
	@apply my-6 rounded-lg border-l-4 border-[var(--theme-color-0)] bg-gray-50 px-5 py-4 text-gray-800;
}
</style>
