<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import WebpageSeoPreview from "@/Components/DataDisplay/WebpageSeoPreview.vue"
import SeoContentSuggestions from "@/Components/Seo/SeoContentSuggestions.vue"
import type { ContentSuggestions } from "@/Components/Seo/types"
import { buildWebpageStructuredData, type WebpageStructuredDataSource } from "@/Iris/Composables/useWebpageStructuredData"
import { faCheckCircle, faTimesCircle } from "@fal"

const props = defineProps<{
	seo?: {
		title?: string
		page_title?: string
		description?: string
		breadcrumb_label?: string
		canonical_url?: string
		url?: string
		domain?: string
		site_name?: string
		index_page?: boolean
		follow_link?: boolean
		robots?: string
		use_title_prefix_suffix?: boolean
		title_prefix?: string
		title_suffix?: string
		share_image?: { url?: string; alt?: string }
		structured_data?: Record<string, any> | Array<Record<string, any>>
		structured_data_types?: string[]
		hreflang?: { hreflang: string; href: string }[]
		content_suggestions?: ContentSuggestions
	}
	structuredDataSource?: WebpageStructuredDataSource | null
	stacked?: boolean
}>()

const shareTitle = computed(() => props.seo?.title ?? props.seo?.page_title)
const shareDescription = computed(() => props.seo?.description)

const searchUrl = computed(() => (props.seo?.canonical_url ?? props.seo?.url ?? "").replace(/^https?:\/\//, ""))

const isStructuredDataLoading = computed(() => props.structuredDataSource === undefined)

const websiteStructuredData = computed(() => (props.structuredDataSource ? buildWebpageStructuredData(props.structuredDataSource) : []))

const isStructuredDataFromWebsite = computed(() => websiteStructuredData.value.length > 0)

const structuredData = computed(() => {
	if (isStructuredDataFromWebsite.value) {
		return websiteStructuredData.value.length === 1 ? websiteStructuredData.value[0] : websiteStructuredData.value
	}

	return props.seo?.structured_data ?? null
})

const structuredDataJson = computed(() => (structuredData.value ? JSON.stringify(structuredData.value, null, 2) : null))

const collectStructuredDataTypes = (value: unknown): string[] => {
	if (Array.isArray(value)) {
		return value.flatMap(collectStructuredDataTypes)
	}

	if (!value || typeof value !== "object") {
		return []
	}

	const node = value as Record<string, any>
	const ownTypes = [node["@type"]].flat().filter((type): type is string => typeof type === "string")

	return [...ownTypes, ...collectStructuredDataTypes(node["@graph"])]
}

const structuredDataTypes = computed(() =>
	isStructuredDataFromWebsite.value ? [...new Set(collectStructuredDataTypes(websiteStructuredData.value))] : (props.seo?.structured_data_types ?? [])
)

// Past these lengths Google cuts the line off in its result
const TITLE_LIMIT = 60
const DESCRIPTION_LIMIT = 150

const fields = computed(() => [
	{ label: ctrans("Meta title"), value: props.seo?.title, limit: TITLE_LIMIT, hint: ctrans("Rendered with the prefix and suffix of the website") },
	{ label: ctrans("Meta description"), value: props.seo?.description, limit: DESCRIPTION_LIMIT },
	{ label: ctrans("Breadcrumb label"), value: props.seo?.breadcrumb_label },
	{ label: ctrans("Canonical URL"), value: props.seo?.canonical_url },
	{ label: ctrans("Title prefix"), value: props.seo?.use_title_prefix_suffix ? props.seo?.title_prefix : null },
	{ label: ctrans("Title suffix"), value: props.seo?.use_title_prefix_suffix ? props.seo?.title_suffix : null },
	{ label: ctrans("Share image alt"), value: props.seo?.share_image?.alt },
])

const robotFlags = computed(() => [
	{ label: ctrans("Indexed by search engines"), on: props.seo?.index_page },
	{ label: ctrans("Links are followed"), on: props.seo?.follow_link },
])
</script>

<template>
	<div class="rounded-lg bg-white shadow">
		<div class="flex flex-wrap items-center gap-3 border-b px-6 py-3">
			<span class="text-sm font-semibold">{{ ctrans("SEO and sharing") }}</span>
			<span v-if="seo?.robots" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ seo.robots }}</span>
		</div>

		<div v-if="!seo" class="px-6 py-6 text-sm text-gray-600">
			{{ ctrans("No SEO data for this page") }}
		</div>

		<div v-else class="grid gap-6 p-6" :class="stacked ? '' : 'lg:grid-cols-2'">
			<dl class="space-y-4">
				<div v-for="field in fields" :key="field.label">
					<dt class="flex items-baseline gap-2 text-xs text-gray-500">
						{{ field.label }}
						<span v-if="field.limit && field.value" :class="field.value.length > field.limit ? 'text-red-500' : 'text-gray-400'">
							{{ field.value.length }}/{{ field.limit }}
						</span>
					</dt>
					<dd v-if="field.value" class="break-words text-sm text-gray-800">{{ field.value }}</dd>
					<dd v-else class="text-sm italic text-gray-400">{{ ctrans("Not set") }}</dd>
					<dd v-if="field.hint && field.value" class="text-[11px] text-gray-500">{{ field.hint }}</dd>
				</div>

				<div class="flex flex-wrap gap-4 border-t pt-4">
					<span v-for="flag in robotFlags" :key="flag.label" class="flex items-center gap-1.5 text-xs text-gray-600">
						<FontAwesomeIcon :icon="flag.on ? faCheckCircle : faTimesCircle" :class="flag.on ? 'text-green-600' : 'text-red-500'" fixed-width />
						{{ flag.label }}
					</span>
				</div>

				<div class="border-t pt-4">
					<dt class="text-xs text-gray-500">{{ ctrans("Language versions (hreflang)") }}</dt>
					<dd v-if="seo.hreflang?.length" class="mt-1 space-y-1">
						<div v-for="alternate in seo.hreflang" :key="alternate.hreflang" class="flex gap-2 text-sm">
							<span class="w-20 shrink-0 font-mono text-xs leading-5 text-gray-500">{{ alternate.hreflang }}</span>
							<a :href="alternate.href" target="_blank" rel="noopener" class="break-all text-[--app-accent] hover:underline">{{ alternate.href }}</a>
						</div>
					</dd>
					<dd v-else class="text-sm italic text-gray-400">
						{{ ctrans("None. Set a hreflang group on the website, and this page needs a live, indexed counterpart on another website of the group.") }}
					</dd>
				</div>

				<SeoContentSuggestions v-if="seo.content_suggestions" :data="seo.content_suggestions" />
			</dl>

			<WebpageSeoPreview
				:searchUrl="searchUrl"
				:domain="seo?.domain"
				:title="shareTitle"
				:description="shareDescription"
				:shareImage="seo?.share_image"
				:structuredDataJson="structuredDataJson"
				:structuredDataTypes="structuredDataTypes"
				:structuredDataNote="isStructuredDataFromWebsite ? ctrans('As the website renders it for a logged out visitor, including the structured data it generates automatically') : undefined"
				:isStructuredDataLoading="isStructuredDataLoading" />
		</div>
	</div>
</template>
