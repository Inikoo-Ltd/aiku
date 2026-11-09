<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-09 13:47
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronRight } from "@fal"

library.add(faChevronRight)

const props = defineProps<{
	label: string
	icon: string
	accent: { colour: string; tint: string }
	loading?: boolean
	clickable?: boolean
}>()

defineEmits<{ (e: "click"): void }>()
</script>

<template>
	<component
		:is="props.clickable ? 'button' : 'div'"
		:type="props.clickable ? 'button' : undefined"
		class="group flex h-full flex-col rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition"
		:class="props.clickable ? 'hover:border-gray-300 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400' : ''"
		@click="props.clickable && $emit('click')">
		<div class="flex items-center gap-2.5">
			<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md" :style="{ backgroundColor: accent.tint, color: accent.colour }">
				<FontAwesomeIcon :icon="icon" fixed-width aria-hidden="true" />
			</span>
			<h2 class="min-w-0 flex-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ label }}</h2>
			<FontAwesomeIcon v-if="clickable" icon="fal fa-chevron-right" class="text-xs text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" aria-hidden="true" />
		</div>
		<div v-if="loading" class="skeleton mt-3 h-14 rounded" />
		<div v-else class="mt-3">
			<slot />
		</div>
		<div v-if="$slots.note && !loading" class="mt-auto pt-4">
			<p class="border-t border-gray-100 pt-3 text-xs leading-relaxed text-gray-400">
				<slot name="note" />
			</p>
		</div>
	</component>
</template>
