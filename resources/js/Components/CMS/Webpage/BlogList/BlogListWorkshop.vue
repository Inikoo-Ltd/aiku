<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import BlogListIris from "@/Iris/Components/IrisBlocks/BlogListIris.vue"
import { sendMessageToParent } from "@/Composables/Workshop"

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Record<string, any>
	indexBlock?: number
	screenType: "mobile" | "tablet" | "desktop"
}>()

const hasBlogs = computed(() => (props.modelValue?.blogs?.length ?? 0) > 0)
</script>

<template>
	<div @click="() => sendMessageToParent('activeBlock', indexBlock)">
		<BlogListIris
			v-if="hasBlogs"
			:fieldValue="modelValue"
			:screenType="screenType"
			:indexBlock="indexBlock" />

		<div v-else class="bg-gray-200 px-4 py-10 text-center text-gray-400">
			<p class="font-semibold text-gray-700">
				{{ trans("Blog List Block Hidden") }}
			</p>

			<p class="mt-2 text-sm text-gray-500">
				{{ trans("This website has no published blog post yet.") }}
			</p>
		</div>
	</div>
</template>
