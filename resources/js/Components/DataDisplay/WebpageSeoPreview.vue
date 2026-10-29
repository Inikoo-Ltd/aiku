<script setup lang="ts">
import { ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { faImage, faCode } from "@fal"
import { faFacebook, faXTwitter, faWhatsapp, faGoogle } from "@fortawesome/free-brands-svg-icons"

type PreviewTab = "search" | "facebook" | "x" | "whatsapp" | "structured_data"

defineProps<{
	searchUrl: string
	domain?: string
	title?: string | null
	description?: string | null
	shareImage?: { url?: string | null; alt?: string | null }
	structuredDataJson: string | null
	structuredDataTypes: string[]
	structuredDataNote?: string
	isStructuredDataLoading?: boolean
}>()

const previewTabs: Array<{ key: PreviewTab; label: string; icon: typeof faGoogle }> = [
	{ key: "search", label: ctrans("Search result"), icon: faGoogle },
	{ key: "facebook", label: ctrans("Facebook"), icon: faFacebook },
	{ key: "x", label: ctrans("X"), icon: faXTwitter },
	{ key: "whatsapp", label: ctrans("WhatsApp"), icon: faWhatsapp },
	{ key: "structured_data", label: ctrans("Structured data"), icon: faCode },
]

const previewTab = ref<PreviewTab>("search")
</script>

<template>
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

		<div v-if="previewTab === 'search'" class="rounded-lg border border-gray-200 p-4">
			<div class="truncate text-xs text-gray-600">{{ searchUrl }}</div>
			<div class="truncate text-lg text-[#1a0dab]">{{ title || ctrans("Not set") }}</div>
			<p class="line-clamp-2 text-sm text-gray-600">{{ description || ctrans("Not set") }}</p>
		</div>

		<div
			v-else-if="previewTab === 'structured_data' && isStructuredDataLoading"
			class="flex items-center justify-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-6 text-sm text-gray-500">
			<LoadingIcon />
			{{ ctrans("Building the structured data of the website") }}
		</div>
		<div v-else-if="previewTab === 'structured_data'" class="space-y-2">
			<p v-if="structuredDataNote" class="text-xs text-gray-500">{{ structuredDataNote }}</p>
			<div v-if="structuredDataTypes.length" class="flex flex-wrap gap-1.5">
				<span v-for="type in structuredDataTypes" :key="type" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ type }}</span>
			</div>
			<pre
				v-if="structuredDataJson"
				class="max-h-80 overflow-auto rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs text-gray-800"
			>{{ structuredDataJson }}</pre>
			<div v-else class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
				{{ ctrans("No structured data set for this page") }}
			</div>
		</div>

		<div v-else>
			<div class="overflow-hidden rounded-lg border border-gray-200" :class="previewTab === 'whatsapp' ? 'bg-[#dcf8c6]' : 'bg-white'">
				<div v-if="previewTab !== 'whatsapp'" class="aspect-[1.91/1] w-full bg-gray-100">
					<img v-if="shareImage?.url" :src="shareImage.url" :alt="shareImage.alt ?? undefined" class="h-full w-full object-cover" />
					<div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-gray-400">
						<FontAwesomeIcon :icon="faImage" size="2x" aria-hidden="true" />
						<span class="text-xs">{{ ctrans("No share image set") }}</span>
					</div>
				</div>

				<div class="flex gap-3 p-3">
					<div v-if="previewTab === 'whatsapp'" class="h-16 w-16 shrink-0 overflow-hidden rounded bg-gray-100">
						<img v-if="shareImage?.url" :src="shareImage.url" :alt="shareImage.alt ?? undefined" class="h-full w-full object-cover" />
						<div v-else class="flex h-full w-full items-center justify-center text-gray-400">
							<FontAwesomeIcon :icon="faImage" fixed-width aria-hidden="true" />
						</div>
					</div>

					<div class="min-w-0">
						<div class="truncate text-[11px] uppercase text-gray-500">{{ domain }}</div>
						<div class="truncate text-sm font-semibold text-gray-800">{{ title || ctrans("Not set") }}</div>
						<p class="line-clamp-2 text-xs text-gray-600">{{ description || ctrans("Not set") }}</p>
					</div>
				</div>
			</div>

			<p v-if="!shareImage?.url" class="mt-2 text-[11px] text-gray-500">
				{{ ctrans("Without a share image the site falls back to the first product image on the page") }}
			</p>
		</div>
	</div>
</template>
