<script setup lang="ts">
import { computed } from "vue"
import { NodeViewWrapper } from "@tiptap/vue-3"
import ProductCarouselPreview from "./ProductCarouselPreview.vue"
import { ctrans } from "@/Composables/useTrans"
import type { ProductCarouselAttributes } from "./productCarouselAttributes"

const props = defineProps<{
	node: { attrs: ProductCarouselAttributes }
	extension: { options: { onEdit: (position: number) => void, title: string, previewRoute: () => { name: string, parameters: Record<string, any> } | undefined } }
	getPos: () => number | undefined
	selected: boolean
}>()

const previewRoute = computed(() => props.extension.options.previewRoute())

const edit = (event: MouseEvent) => {
	event.preventDefault()
	const position = props.getPos()
	if (typeof position === "number") {
		props.extension.options.onEdit(position)
	}
}
</script>

<template>
	<NodeViewWrapper
		class="tiptap-product-carousel not-prose my-4 cursor-pointer rounded-lg border border-dashed px-4 py-3"
		:class="selected ? 'border-[var(--app-accent)] ring-1 ring-[var(--app-accent)]' : 'border-[var(--app-accent-muted)]'"
		contenteditable="false"
		data-drag-handle
		@dblclick="edit"
	>
		<div class="mb-2 flex select-none items-center gap-2 text-xs font-semibold text-gray-500">
			<span>{{ extension.options.title }}</span>
			<span v-if="node.attrs.width === 'wide'" class="rounded-full bg-[var(--app-accent-soft)] px-2 py-0.5 text-[var(--app-accent-deep)]">{{ ctrans("Wide") }}</span>
		</div>
		<ProductCarouselPreview :attributes="node.attrs" :previewRoute="previewRoute" />
	</NodeViewWrapper>
</template>
