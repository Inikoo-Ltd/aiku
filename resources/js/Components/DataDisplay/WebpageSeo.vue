<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faCheckCircle, faTimesCircle, faCode } from "@fal"
import { faFacebook, faXTwitter, faWhatsapp, faGoogle } from "@fortawesome/free-brands-svg-icons"

type PreviewTab = "search" | "facebook" | "x" | "whatsapp" | "structured_data"

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
	}
	stacked?: boolean
}>()

const previewTabs: Array<{ key: PreviewTab; label: string; icon: typeof faGoogle }> = [
	{ key: "search", label: ctrans("Search result"), icon: faGoogle },
	{ key: "facebook", label: ctrans("Facebook"), icon: faFacebook },
	{ key: "x", label: ctrans("X"), icon: faXTwitter },
	{ key: "whatsapp", label: ctrans("WhatsApp"), icon: faWhatsapp },
	{ key: "structured_data", label: ctrans("Structured data"), icon: faCode },
]

const previewTab = ref<PreviewTab>("search")

const shareImage = computed(() => props.seo?.share_image?.url)
const shareTitle = computed(() => props.seo?.title ?? props.seo?.page_title)
const shareDescription = computed(() => props.seo?.description)

const searchUrl = computed(() => (props.seo?.canonical_url ?? props.seo?.url ?? "").replace(/^https?:\/\//, ""))

const structuredDataJson = computed(() => (props.seo?.structured_data ? JSON.stringify(props.seo.structured_data, null, 2) : null))

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
			</dl>

			<div class="space-y-3">
				<div class="flex flex-wrap rounded-md bg-gray-100 p-0.5">
					<button
						v-for="tab in previewTabs"
						:key="tab.key"
						type="button"
						:aria-pressed="previewTab === tab.key"
						class="flex items-center gap-1.5 rounded px-2.5 py-1 text-xs"
						:class="previewTab === tab.key ? 'bg-white font-semibold text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-800'"
						@click="previewTab = tab.key">
						<FontAwesomeIcon :icon="tab.icon" fixed-width />
						{{ tab.label }}
					</button>
				</div>

				<!-- How Google shows the page -->
				<div v-if="previewTab === 'search'" class="rounded-lg border border-gray-200 p-4">
					<div class="truncate text-xs text-gray-600">{{ searchUrl }}</div>
					<div class="truncate text-lg text-[#1a0dab]">{{ shareTitle ?? ctrans("Not set") }}</div>
					<p class="line-clamp-2 text-sm text-gray-600">{{ shareDescription ?? ctrans("Not set") }}</p>
				</div>

				<!-- The JSON-LD the page carries -->
				<div v-else-if="previewTab === 'structured_data'" class="space-y-2">
					<div v-if="seo?.structured_data_types?.length" class="flex flex-wrap gap-1.5">
						<span v-for="type in seo.structured_data_types" :key="type" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ type }}</span>
					</div>
					<pre
						v-if="structuredDataJson"
						class="max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-800"
					>{{ structuredDataJson }}</pre>
					<div v-else class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
						{{ ctrans("No structured data set for this page") }}
					</div>
				</div>

				<!-- How a social network shows the page -->
				<div v-else>
					<div class="overflow-hidden rounded-lg border border-gray-200" :class="previewTab === 'whatsapp' ? 'bg-[#dcf8c6]' : 'bg-white'">
						<div v-if="previewTab !== 'whatsapp'" class="aspect-[1.91/1] w-full bg-gray-100">
							<img v-if="shareImage" :src="shareImage" :alt="seo?.share_image?.alt" class="h-full w-full object-cover" />
							<div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-gray-400">
								<FontAwesomeIcon :icon="faImage" size="2x" aria-hidden="true" />
								<span class="text-xs">{{ ctrans("No share image set") }}</span>
							</div>
						</div>

						<div class="flex gap-3 p-3">
							<div v-if="previewTab === 'whatsapp'" class="h-16 w-16 shrink-0 overflow-hidden rounded bg-gray-100">
								<img v-if="shareImage" :src="shareImage" :alt="seo?.share_image?.alt" class="h-full w-full object-cover" />
								<div v-else class="flex h-full w-full items-center justify-center text-gray-400">
									<FontAwesomeIcon :icon="faImage" fixed-width aria-hidden="true" />
								</div>
							</div>

							<div class="min-w-0">
								<div class="truncate text-[11px] uppercase text-gray-500">{{ seo?.domain }}</div>
								<div class="truncate text-sm font-semibold text-gray-800">{{ shareTitle ?? ctrans("Not set") }}</div>
								<p class="line-clamp-2 text-xs text-gray-600">{{ shareDescription ?? ctrans("Not set") }}</p>
							</div>
						</div>
					</div>

					<p v-if="!shareImage" class="mt-2 text-[11px] text-gray-500">
						{{ ctrans("Without a share image the site falls back to the first product image on the page") }}
					</p>
				</div>
			</div>
		</div>
	</div>
</template>
