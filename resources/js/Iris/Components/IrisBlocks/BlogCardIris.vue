<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import type { BlogPost } from "@/types/Iris/Blog"

const props = withDefaults(
	defineProps<{
		post: BlogPost
		cardProperties?: any
		showPublishedDate?: boolean
		showCta?: boolean
		ctaLabel?: string
		screenType?: "mobile" | "tablet" | "desktop"
	}>(),
	{
		cardProperties: undefined,
		showPublishedDate: true,
		showCta: true,
		ctaLabel: undefined,
		screenType: "desktop",
	}
)

const label = computed(() => props.ctaLabel || trans("Read more"))
</script>

<template>
	<article
		class="group relative flex h-full flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:ring-gray-300"
		:style="getStyles(cardProperties, screenType)">
		<div class="relative aspect-[16/10] w-full overflow-hidden bg-gray-100">
			<Image
				v-if="post.image_src"
				:src="post.image_src"
				:alt="post.image_alt"
				class="block h-full w-full transition duration-500 group-hover:scale-105"
				:imageCover="true" />
			<img
				v-else-if="post.third_party_image_preview"
				:src="post.third_party_image_preview"
				:alt="post.image_alt ? post.image_alt : post.title"
				class="h-full w-full object-cover transition duration-500 group-hover:scale-105" />
			<div
				v-else
				class="flex h-full w-full items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
				<span class="text-3xl font-semibold text-gray-300">{{ post.title?.charAt(0) }}</span>
			</div>

			<time
				v-if="showPublishedDate && post.published_at"
				:datetime="post.published_at"
				class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-gray-700 shadow-sm backdrop-blur">
				{{ post.published_at }}
			</time>
		</div>

		<div class="flex flex-1 flex-col gap-3 p-5">
			<h2
				class="line-clamp-2 !text-base font-semibold leading-snug text-gray-900 transition-colors duration-200 group-hover:text-blue-600">
				{{ post.title }}
			</h2>

			<span
				v-if="showCta"
				class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-blue-600">
				{{ label }}
				<span
					aria-hidden="true"
					class="transition-transform duration-300 group-hover:translate-x-1">
					→
				</span>
			</span>
		</div>

		<a
			:href="post.url ? post.url : '#'"
			class="absolute inset-0 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2">
			<span class="sr-only">{{ post.title }}</span>
		</a>
	</article>
</template>
