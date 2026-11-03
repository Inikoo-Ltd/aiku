<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
	searchUrl: string
	title?: string | null
	description?: string | null
}>()

const TITLE_MAX_LENGTH = 60
const DESCRIPTION_MAX_LENGTH = 160
const ELLIPSIS = " ..."

const cutAtWord = (text: string, maxLength: number) => {
	if (text.length <= maxLength) {
		return text
	}

	const words = text.split(" ")
	while (words.length > 1 && words.join(" ").length > maxLength) {
		words.pop()
	}

	return words.join(" ").slice(0, maxLength).replace(/[\s,.;:|-]+$/, "") + ELLIPSIS
}

const serpTitle = computed(() => props.title ? cutAtWord(props.title.trim(), TITLE_MAX_LENGTH) : "")
const serpDescription = computed(() => props.description ? cutAtWord(props.description.trim(), DESCRIPTION_MAX_LENGTH) : "")
const serpBreadcrumb = computed(() => props.searchUrl.replace(/^https?:\/\//, "").split("/").filter(Boolean).join(" › "))
</script>

<template>
	<div class="space-y-2">
		<div class="max-w-[634px] space-y-1 break-words rounded-lg border border-gray-200 bg-white p-4 [font-family:Arial,sans-serif]">
			<div class="text-sm text-[#202124]">{{ serpBreadcrumb }}</div>
			<div class="text-xl leading-snug text-[#1a0dab]">{{ serpTitle || ctrans("Not set") }}</div>
			<p class="text-sm leading-relaxed text-[#4d5156]">{{ serpDescription || ctrans("Not set") }}</p>
		</div>

		<p class="text-[11px] text-gray-500">
			{{ ctrans(" :title characters for the title and :description for the description.", { title: TITLE_MAX_LENGTH, description: DESCRIPTION_MAX_LENGTH }) }}
		</p>
	</div>
</template>
