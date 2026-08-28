<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlaneDeparture, faBookOpen, faChartBar } from "@fal"
import Image from "@common/Components/Image.vue"
import type { BlogCategory } from "@/types/Iris/Blog"
import { getBlogCategoryDisplayName } from "@/Iris/Composables/useBlogCategoryDisplayName"

library.add(faPlaneDeparture, faBookOpen, faChartBar)

const props = defineProps<{
	category: BlogCategory
	position: number
}>()

const displayLabel = computed(() =>
	getBlogCategoryDisplayName(props.category.value, props.category.label)
)
</script>

<template>
	<article
		class="group relative flex h-full flex-col overflow-hidden rounded-2xl bg-white text-center ring-1 ring-gray-200 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:ring-gray-300">
		<div class="relative w-full">
			<div class="aspect-[16/9] w-full overflow-hidden bg-gray-100">
				<Image
					v-if="category.image_src"
					:src="category.image_src"
					:alt="category.image_alt ?? displayLabel"
					class="block h-full w-full transition duration-500 group-hover:scale-105"
					:imageCover="true" />
				<img
					v-else-if="category.third_party_image_preview"
					:src="category.third_party_image_preview"
					:alt="category.image_alt ?? displayLabel"
					class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
				<img
					v-else-if="category.fallback_image"
					:src="category.fallback_image"
					:alt="displayLabel"
					class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
				<div v-else class="h-full w-full bg-gradient-to-br from-gray-100 to-gray-200" />
			</div>

			<div
				class="absolute -bottom-6 left-1/2 flex h-12 w-12 -translate-x-1/2 items-center justify-center rounded-full bg-[var(--theme-color-0)] text-[var(--theme-color-1)] shadow-lg ring-4 ring-white">
				<FontAwesomeIcon :icon="category.icon" class="text-base" fixed-width aria-hidden="true" />
			</div>
		</div>

		<div class="flex flex-1 flex-col gap-3 px-6 pb-6 pt-10">
			<span class="text-[11px] font-semibold uppercase tracking-widest text-[var(--theme-color-0)]">
				{{ trans("Category :position", { position: position }) }}
			</span>

			<h2 class="!text-xl font-bold leading-snug text-gray-900">
				{{ displayLabel }}
			</h2>

			<p class="text-sm leading-relaxed text-gray-500">
				{{ category.description }}
			</p>

			<span
				class="mt-auto inline-flex items-center justify-center gap-1.5 pt-3 text-sm font-semibold text-[var(--theme-color-0)]">
				{{ trans("View Dashboard") }}
				<span aria-hidden="true" class="transition-transform duration-300 group-hover:translate-x-1">→</span>
			</span>

			<span
				class="mx-auto rounded-full bg-[color-mix(in_srgb,var(--theme-color-0)_10%,white)] px-3 py-1 text-xs font-medium text-[var(--theme-color-0)]">
				{{ category.url }}
			</span>
		</div>

		<a
			:href="category.url"
			class="absolute inset-0 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--theme-color-0)] focus-visible:ring-offset-2">
			<span class="sr-only">{{ displayLabel }}</span>
		</a>
	</article>
</template>
